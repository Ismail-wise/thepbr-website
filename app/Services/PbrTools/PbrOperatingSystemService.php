<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\WorkspaceOperatingRecord;
use App\Models\WorkspaceOperatingSnapshot;
use App\Models\WorkspacePartnerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PbrOperatingSystemService
{
    public const DOMAINS = [
        1 => 'capital',
        2 => 'ownership',
        3 => 'contribution',
        4 => 'distribution',
        5 => 'financial_controls',
        6 => 'governance',
        7 => 'exit',
        8 => 'continuity',
        9 => 'share_transfer',
        10 => 'dispute_resolution',
    ];

    public function canManage(User $user, PartnershipWorkspace $workspace): bool
    {
        return $user->isAdmin()
            || (
                $user->isStudent()
                && (int) $workspace->owner_user_id === (int) $user->id
            );
    }

    public function domainForChapter(int $chapterNumber): string
    {
        abort_unless(isset(self::DOMAINS[$chapterNumber]), 404);

        return self::DOMAINS[$chapterNumber];
    }

    public function syncWorkspacePartners(PartnershipWorkspace $workspace): Collection
    {
        $workspace->loadMissing(['owner:id,name', 'acceptedMemberships.user:id,name']);

        $people = collect([
            [
                'user_id' => $workspace->owner_user_id,
                'display_name' => $workspace->owner?->name ?: 'Business Owner',
                'profile_data' => ['workspace_role' => 'owner'],
            ],
        ]);

        foreach ($workspace->acceptedMemberships as $membership) {
            if (! $membership->user_id || ! $membership->user) {
                continue;
            }

            $people->push([
                'user_id' => $membership->user_id,
                'display_name' => $membership->user->name,
                'profile_data' => [
                    'workspace_role' => $membership->member_role ?: 'partner',
                ],
            ]);
        }

        foreach ($people->unique('user_id') as $person) {
            $existing = WorkspacePartnerProfile::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $person['user_id'])
                ->first();

            if ($existing) {
                $existing->update([
                    'display_name' => $person['display_name'],
                    'status' => 'active',
                    'profile_data' => array_merge(
                        $existing->profile_data ?? [],
                        $person['profile_data']
                    ),
                ]);
                continue;
            }

            WorkspacePartnerProfile::create([
                'workspace_id' => $workspace->id,
                'user_id' => $person['user_id'],
                'partner_key' => (string) Str::uuid(),
                'display_name' => $person['display_name'],
                'status' => 'active',
                'profile_data' => $person['profile_data'],
            ]);
        }

        return WorkspacePartnerProfile::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->get();
    }

    public function addPlannedPartner(
        User $actor,
        PartnershipWorkspace $workspace,
        string $displayName,
        array $profileData = []
    ): WorkspacePartnerProfile {
        abort_unless($this->canManage($actor, $workspace), 403);

        return WorkspacePartnerProfile::create([
            'workspace_id' => $workspace->id,
            'user_id' => null,
            'partner_key' => (string) Str::uuid(),
            'display_name' => trim($displayName),
            'status' => 'planned',
            'profile_data' => $profileData,
        ]);
    }

    public function latestSnapshot(
        PartnershipWorkspace $workspace,
        string $domainKey,
        ?string $status = null
    ): ?WorkspaceOperatingSnapshot {
        $query = WorkspaceOperatingSnapshot::query()
            ->where('workspace_id', $workspace->id)
            ->where('domain_key', $domainKey);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->first();
    }

    public function readableSnapshot(
        User $actor,
        PartnershipWorkspace $workspace,
        string $domainKey
    ): ?WorkspaceOperatingSnapshot {
        return $this->canManage($actor, $workspace)
            ? $this->latestSnapshot($workspace, $domainKey)
            : $this->latestSnapshot($workspace, $domainKey, 'agreed');
    }

    public function saveSnapshot(
        User $actor,
        PartnershipWorkspace $workspace,
        string $domainKey,
        array $payload,
        array $summary = [],
        string $status = 'draft'
    ): WorkspaceOperatingSnapshot {
        abort_unless($this->canManage($actor, $workspace), 403);
        abort_unless(in_array($domainKey, array_values(self::DOMAINS), true), 422);
        abort_unless(in_array($status, ['draft', 'agreed'], true), 422);

        return DB::transaction(function () use (
            $actor,
            $workspace,
            $domainKey,
            $payload,
            $summary,
            $status
        ): WorkspaceOperatingSnapshot {
            $latest = WorkspaceOperatingSnapshot::query()
                ->where('workspace_id', $workspace->id)
                ->where('domain_key', $domainKey)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            $revision = ($latest?->revision ?? 0) + 1;
            $now = now();

            return WorkspaceOperatingSnapshot::create([
                'workspace_id' => $workspace->id,
                'domain_key' => $domainKey,
                'revision' => $revision,
                'status' => $status,
                'schema_version' => 'v1',
                'payload' => $payload,
                'summary' => $summary,
                'generated_by_user_id' => $actor->id,
                'generated_at' => $now,
                'agreed_at' => $status === 'agreed' ? $now : null,
            ]);
        });
    }

    public function saveRecord(
        User $actor,
        PartnershipWorkspace $workspace,
        ?ChapterTool $tool,
        string $recordType,
        array $data,
        ?string $title = null,
        ?string $recordDate = null,
        array $metadata = []
    ): WorkspaceOperatingRecord {
        abort_unless($this->canManage($actor, $workspace), 403);

        return WorkspaceOperatingRecord::create([
            'workspace_id' => $workspace->id,
            'chapter_tool_id' => $tool?->id,
            'user_id' => $actor->id,
            'record_type' => $recordType,
            'status' => 'active',
            'title' => $title ? trim($title) : null,
            'record_date' => $recordDate,
            'effective_at' => now(),
            'data' => $data,
            'metadata' => $metadata,
        ]);
    }

    public function latestDomainMap(User $actor, PartnershipWorkspace $workspace): array
    {
        $result = [];

        foreach (self::DOMAINS as $domainKey) {
            $snapshot = $this->readableSnapshot($actor, $workspace, $domainKey);

            if (! $snapshot) {
                continue;
            }

            $result[$domainKey] = [
                'revision' => $snapshot->revision,
                'status' => $snapshot->status,
                'schema_version' => $snapshot->schema_version,
                'payload' => $snapshot->payload,
                'summary' => $snapshot->summary,
                'generated_at' => $snapshot->generated_at?->toIso8601String(),
                'agreed_at' => $snapshot->agreed_at?->toIso8601String(),
            ];
        }

        return $result;
    }

    public function agreedDomainMap(User $actor, PartnershipWorkspace $workspace): array
    {
        abort_unless($actor->canAccessWorkspace($workspace), 403);

        $result = [];

        foreach (self::DOMAINS as $domainKey) {
            $snapshot = $this->latestSnapshot($workspace, $domainKey, 'agreed');

            if (! $snapshot) {
                continue;
            }

            $result[$domainKey] = [
                'revision' => $snapshot->revision,
                'status' => $snapshot->status,
                'schema_version' => $snapshot->schema_version,
                'payload' => $snapshot->payload,
                'summary' => $snapshot->summary,
                'generated_at' => $snapshot->generated_at?->toIso8601String(),
                'agreed_at' => $snapshot->agreed_at?->toIso8601String(),
            ];
        }

        return $result;
    }
}
