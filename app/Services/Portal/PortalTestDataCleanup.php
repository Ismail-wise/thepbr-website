<?php

namespace App\Services\Portal;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Connection;
use RuntimeException;

/** CLI-only, explicitly authorized test reset. Never invoked by a web route. */
class PortalTestDataCleanup
{
    private const PROTECTED_TABLES = ['articles', 'videos', 'class_sessions', 'course_chapters', 'chapter_tools', 'migrations'];

    private const VOLATILE_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const RELATED_TABLES = [
        'users', 'partnership_workspaces', 'workspace_members', 'student_enrollments', 'student_access_codes',
        'chapter_progress', 'tool_sessions', 'workspace_tool_outputs', 'workspace_tool_actions',
        'workspace_partner_profiles', 'workspace_operating_snapshots', 'workspace_operating_records',
        'partner_dynamics_assessments', 'partner_dynamics_reports', 'ai_conversations', 'ai_messages',
        'portal_conversations', 'portal_messages', 'portal_saved_items', 'portal_access_events',
        'business_feasibility_assessments', 'business_valuations',
    ];

    public function __construct(private Connection $db) {}

    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new RuntimeException($reason);
        }
    }

    private function tables(): array
    {
        $rows = $this->db->getDriverName() === 'mysql'
            ? $this->db->select("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'", [$this->db->getDatabaseName()])
            : $this->db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $tables = array_map(fn ($row): string => $row->name, $rows);
        foreach ($tables as $table) {
            $this->require(preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $table) === 1, 'UNSUPPORTED_TABLE_NAME');
        }
        sort($tables, SORT_STRING);

        return $tables;
    }

    private function rowsHash(iterable $rows): string
    {
        $hashes = [];
        foreach ($rows as $row) {
            // serialize preserves binary values and duplicate rows exactly.
            $hashes[] = hash('sha256', serialize((array) $row));
        }
        sort($hashes, SORT_STRING);

        return hash('sha256', serialize($hashes));
    }

    public function fingerprint(): string
    {
        $hashes = [];
        foreach ($this->tables() as $table) {
            if (! in_array($table, self::VOLATILE_TABLES, true)) {
                $hashes[$table] = $this->rowsHash($this->db->table($table)->get());
            }
        }

        return hash('sha256', serialize($hashes));
    }

    private function protectedHash(array $adminIds): string
    {
        $hashes = ['admins' => $this->rowsHash($this->db->table('users')->whereIn('id', $adminIds)->get())];
        foreach (self::PROTECTED_TABLES as $table) {
            $hashes[$table] = $this->rowsHash($this->db->table($table)->get());
        }

        return hash('sha256', serialize($hashes));
    }

    private function validateSchema(): void
    {
        $tables = $this->tables();
        foreach (array_merge(self::PROTECTED_TABLES, self::RELATED_TABLES, ['sessions', 'password_reset_tokens']) as $table) {
            $this->require(in_array($table, $tables, true), 'REQUIRED_RESET_TABLE_MISSING');
        }
        if ($this->db->getDriverName() === 'mysql') {
            $this->require((int) $this->db->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled === 1, 'FOREIGN_KEYS_MUST_REMAIN_ENABLED');
            $foreignKeys = $this->db->select('SELECT TABLE_NAME AS child_table, REFERENCED_TABLE_NAME AS parent_table FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$this->db->getDatabaseName()]);
        } else {
            $this->require($this->db->getDriverName() === 'sqlite', 'UNSUPPORTED_RESET_DATABASE');
            $this->require((int) $this->db->selectOne('PRAGMA foreign_keys')->foreign_keys === 1, 'FOREIGN_KEYS_MUST_REMAIN_ENABLED');
            $foreignKeys = [];
            foreach ($tables as $table) {
                foreach ($this->db->select('PRAGMA foreign_key_list("'.$table.'")') as $key) {
                    $foreignKeys[] = (object) ['child_table' => $table, 'parent_table' => $key->table];
                }
            }
        }
        foreach ($foreignKeys as $key) {
            $this->require(! in_array($key->parent_table, self::RELATED_TABLES, true)
                || in_array($key->child_table, self::RELATED_TABLES, true), 'UNREVIEWED_DATA_DEPENDENCY');
        }
        $this->require(! in_array('jobs', $tables, true) || $this->db->table('jobs')->count() === 0, 'PENDING_JOBS_REQUIRE_REVIEW');
    }

    public function plan(): array
    {
        $this->validateSchema();
        $users = $this->db->table('users')->orderBy('id')->get();
        $admins = $users->filter(fn ($user): bool => (bool) $user->is_admin || $user->role === 'admin');
        $this->require($admins->contains(function ($row): bool {
            $user = (new User)->newFromBuilder((array) $row);

            return $user->isAdmin() && $user->canAccessPanel(Filament::getPanel('admin'));
        }), 'ACTIVE_FILAMENT_ADMIN_REQUIRED');
        $ids = $admins->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        return [
            'scope' => 'all_test_workspaces_and_non_admin_accounts',
            'admin_ids' => $ids,
            'admin_emails' => $admins->pluck('email')->values()->all(),
            'user_ids' => $users->reject(fn ($user): bool => in_array((int) $user->id, $ids, true))->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'workspace_ids' => $this->db->table('partnership_workspaces')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'protected_fingerprint' => $this->protectedHash($ids),
            'before_fingerprint' => $this->fingerprint(),
        ];
    }

    public function apply(array $plan): array
    {
        $this->require(($plan['scope'] ?? '') === 'all_test_workspaces_and_non_admin_accounts', 'WRONG_RESET_SCOPE');

        return $this->db->transaction(function () use ($plan): array {
            foreach ($this->tables() as $table) {
                if (! in_array($table, self::VOLATILE_TABLES, true) || $table === 'jobs') {
                    $this->db->table($table)->lockForUpdate()->get();
                }
            }
            $current = $this->plan();
            $this->require($current['before_fingerprint'] === $plan['before_fingerprint']
                && $current['admin_ids'] === $plan['admin_ids']
                && $current['user_ids'] === $plan['user_ids']
                && $current['workspace_ids'] === $plan['workspace_ids'], 'RESET_PLAN_CHANGED_NO_DATA_REMOVED');
            $ids = $plan['user_ids'];
            $this->db->table('portal_access_events')->where(fn ($query) => $query->whereIn('user_id', $ids)->orWhereIn('actor_id', $ids))->delete();
            $this->db->table('student_access_codes')->where(fn ($query) => $query->whereIn('used_by_user_id', $ids)->orWhereIn('created_by_user_id', $ids))->delete();
            $this->db->table('sessions')->whereIn('user_id', $ids)->delete();
            $this->db->table('password_reset_tokens')->whereNotIn('email', $plan['admin_emails'])->delete();
            // Its workspace FK uses SET NULL, so remove attached test sessions
            // before workspace deletion instead of leaving orphaned results.
            $this->db->table('tool_sessions')->whereIn('workspace_id', $plan['workspace_ids'])->delete();
            $this->db->table('partnership_workspaces')->whereIn('id', $plan['workspace_ids'])->delete();
            $this->db->table('users')->whereIn('id', $ids)->delete();
            $this->require($this->db->table('partnership_workspaces')->count() === 0
                && $this->db->table('users')->count() === count($plan['admin_ids'])
                && $this->protectedHash($plan['admin_ids']) === $plan['protected_fingerprint'], 'RESET_POSTCONDITION_FAILED');
            $after = $this->fingerprint();
            if (isset($plan['after_fingerprint'])) {
                $this->require($after === $plan['after_fingerprint'], 'RESET_DIFFERS_FROM_MYSQL_REHEARSAL');
            }

            return ['admins_retained' => count($plan['admin_ids']), 'accounts_removed' => count($ids),
                'workspaces_removed' => count($plan['workspace_ids']), 'after_fingerprint' => $after];
        });
    }
}
