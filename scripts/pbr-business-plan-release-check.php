<?php

// CLI-only release verification. Record contents and identities never go to stdout.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const PLAN_MIGRATION = '2026_10_27_060000_create_workspace_business_plans';
const PLAN_COLUMNS = [
    'workspace_business_plans' => ['id', 'workspace_id', 'draft', 'draft_base_revision', 'lock_version', 'created_at', 'updated_at'],
    'workspace_business_plan_revisions' => ['id', 'business_plan_id', 'revision', 'based_on_revision', 'content', 'confirmed_by_user_id', 'knowledge_source_id', 'created_at'],
];

function planRequire(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

function planTables(): array
{
    $tables = array_map(fn ($row) => $row->name, DB::select("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"));
    $tables = array_values(array_diff($tables, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']));
    foreach ($tables as $table) {
        planRequire(preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $table) === 1, 'UNSUPPORTED_TABLE_IDENTIFIER');
    }
    sort($tables, SORT_STRING);

    return $tables;
}

function planSchemaDigest(string $table): string
{
    $row = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
    $sql = (string) array_values($row)[1];
    if ($table === 'migrations') {
        // Only the reviewed migration journal row may advance this sequence.
        $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
    }

    return hash('sha256', $sql);
}

function planRecordDigest(array $projection): array
{
    $digest = [];
    foreach ($projection as $table => $columns) {
        $query = DB::table($table)->select($columns);
        if ($table === 'migrations') {
            $query->where('migration', '!=', PLAN_MIGRATION);
        }
        $rows = [];
        foreach ($query->cursor() as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row->{$column} === null ? null : base64_encode((string) $row->{$column});
            }
            $rows[] = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        }
        sort($rows, SORT_STRING);
        $digest[$table] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }

    return $digest;
}

function planInspectSchema(): void
{
    foreach (PLAN_COLUMNS as $table => $columns) {
        planRequire(Schema::hasTable($table) && Schema::getColumnListing($table) === $columns, 'BUSINESS_PLAN_SCHEMA_INCOMPLETE');
    }
    planRequire(Schema::getColumnType('workspace_business_plans', 'draft') === 'json'
        && Schema::getColumnType('workspace_business_plan_revisions', 'content') === 'json', 'BUSINESS_PLAN_JSON_REQUIRED');
    foreach ([
        ['workspace_business_plans', ['workspace_id']],
        ['workspace_business_plan_revisions', ['business_plan_id', 'revision']],
    ] as [$table, $columns]) {
        planRequire(collect(Schema::getIndexes($table))->contains(fn ($index) => $index['unique'] && $index['columns'] === $columns), 'BUSINESS_PLAN_UNIQUE_INDEX_REQUIRED');
    }
    foreach ([
        ['workspace_business_plans', 'workspace_id', 'partnership_workspaces', 'cascade'],
        ['workspace_business_plan_revisions', 'business_plan_id', 'workspace_business_plans', 'cascade'],
        ['workspace_business_plan_revisions', 'confirmed_by_user_id', 'users', 'set null'],
        ['workspace_business_plan_revisions', 'knowledge_source_id', 'workspace_knowledge_sources', 'set null'],
    ] as [$table, $column, $parent, $delete]) {
        planRequire(collect(Schema::getForeignKeys($table))->contains(fn ($key) => $key['columns'] === [$column]
            && $key['foreign_table'] === $parent && strtolower((string) $key['on_delete']) === $delete), 'BUSINESS_PLAN_FOREIGN_KEY_REQUIRED');
    }
    planRequire(DB::table('migrations')->where('migration', PLAN_MIGRATION)->count() === 1, 'BUSINESS_PLAN_JOURNAL_REQUIRED');
}

try {
    [$script, $mode, $root] = array_pad($argv, 3, '');
    planRequire(is_file($root.'/vendor/autoload.php'), 'APP_REQUIRED');
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    planRequire(config('partner_portal.enabled') && config('app.debug') === false
        && parse_url((string) config('app.url'), PHP_URL_HOST) === 'thepbr.io', 'PRIVATE_PORTAL_CONFIG_REQUIRED');
    planRequire(DB::connection()->getDriverName() === 'mysql', 'MYSQL_REQUIRED');
    $aiUrl = parse_url((string) config('pbr_ai.base_url'));
    planRequire(is_array($aiUrl) && in_array($aiUrl['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
        && ! isset($aiUrl['user']) && ! isset($aiUrl['pass']), 'ORIGINAL_LOCAL_AI_ENDPOINT_REQUIRED');

    if ($mode === 'preflight') {
        $present = array_filter(array_keys(PLAN_COLUMNS), fn ($table) => Schema::hasTable($table));
        $journal = DB::table('migrations')->where('migration', PLAN_MIGRATION)->count();
        planRequire(($present === [] && $journal === 0) || (count($present) === 2 && $journal === 1), 'PARTIAL_BUSINESS_PLAN_SCHEMA_REQUIRES_REVIEW');
        if ($present !== []) {
            planInspectSchema();
        }
        echo "BUSINESS_PLAN_SCHEMA_PREFLIGHT=OK\nORIGINAL_AI_ENDPOINT_LOCAL=YES\n";
    } elseif ($mode === 'snapshot') {
        $tables = planTables();
        $columns = $schema = [];
        foreach ($tables as $table) {
            $columns[$table] = Schema::getColumnListing($table);
            $schema[$table] = planSchemaDigest($table);
        }
        planRequire(isset($columns['users'], $columns['partnership_workspaces'], $columns['migrations'], $columns['workspace_knowledge_sources']), 'BASELINE_TABLES_REQUIRED');
        $present = array_intersect(array_keys(PLAN_COLUMNS), $tables);
        $journal = DB::table('migrations')->where('migration', PLAN_MIGRATION)->count();
        planRequire(($present === [] && $journal === 0) || (count($present) === 2 && $journal === 1), 'PARTIAL_BUSINESS_PLAN_SCHEMA_REQUIRES_REVIEW');
        if ($present !== []) {
            planInspectSchema();
        }
        planRequire(file_put_contents($argv[3], json_encode(['columns' => $columns, 'schema' => $schema], JSON_THROW_ON_ERROR)) !== false, 'PROJECTION_WRITE_FAILED');
        planRequire(file_put_contents($argv[4], json_encode(planRecordDigest($columns), JSON_THROW_ON_ERROR)) !== false, 'DIGEST_WRITE_FAILED');
        chmod($argv[3], 0600);
        chmod($argv[4], 0600);
        echo "EXISTING_BUSINESS_AND_PORTAL_RECORDS_SNAPSHOTTED=YES\n";
    } elseif ($mode === 'verify') {
        $projection = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
        $expected = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
        planRequire(planRecordDigest($projection['columns']) === $expected, 'PREEXISTING_RECORDS_CHANGED');
        $tables = array_values(array_unique(array_merge(array_keys($projection['columns']), array_keys(PLAN_COLUMNS))));
        sort($tables, SORT_STRING);
        planRequire(planTables() === $tables, 'UNREVIEWED_TABLE_CHANGE');
        foreach ($projection['columns'] as $table => $columns) {
            planRequire(Schema::getColumnListing($table) === $columns && planSchemaDigest($table) === $projection['schema'][$table], 'PREEXISTING_SCHEMA_CHANGED');
        }
        planInspectSchema();
        foreach (PLAN_COLUMNS as $table => $columns) {
            if (! array_key_exists($table, $projection['columns'])) {
                planRequire(DB::table($table)->count() === 0, 'NEW_BUSINESS_RECORDS_DURING_CUTOVER');
            }
        }
        echo "ALL_PREEXISTING_RECORDS_AND_SCHEMA=PRESERVED\nONLY_TWO_BUSINESS_PLAN_TABLES=YES\n";
    } elseif ($mode === 'inspect') {
        planInspectSchema();
        echo "BUSINESS_PLAN_TABLES_AND_CONSTRAINTS=OK\n";
    } elseif ($mode === 'render') {
        planInspectSchema();
        foreach (['edit', 'save', 'review', 'confirm', 'history', 'revision', 'copy', 'knowledge'] as $name) {
            planRequire($app['router']->has('portal.workspaces.business-plan.'.$name), 'BUSINESS_PLAN_ROUTE_REQUIRED');
        }
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $student = new App\Models\User(['name' => 'Synthetic plan preview', 'email' => 'plan-render@example.invalid', 'role' => 'student', 'account_status' => 'active']);
        $student->id = -1;
        $request = Illuminate\Http\Request::create('https://thepbr.io/portal/workspaces');
        $request->setUserResolver(fn () => $student);
        $session = $app['session']->driver('array');
        $session->start();
        $request->setLaravelSession($session);
        $app->instance('request', $request);
        $app['url']->setRequest($request);
        $app['view']->share('errors', new Illuminate\Support\ViewErrorBag);
        Illuminate\Support\Facades\Auth::guard()->setUser($student);
        $workspace = new App\Models\PartnershipWorkspace(['name' => 'Synthetic plan preview', 'owner_user_id' => -1, 'status' => 'active']);
        $workspace->id = -1;
        $values = ['business_name' => 'Synthetic workshop', 'value_proposition' => 'Synthetic owner workshops', 'customer_segments' => 'Synthetic business owners'];
        $plan = new App\Models\WorkspaceBusinessPlan(['workspace_id' => -1, 'draft' => $values, 'lock_version' => 1]);
        $plan->id = -1;
        $revision = new App\Models\WorkspaceBusinessPlanRevision(['business_plan_id' => -1, 'revision' => 1, 'content' => $values, 'created_at' => now()]);
        $revision->id = -1;
        $views = [
            view('portal.workspaces.business-plan.edit', compact('workspace', 'plan', 'values') + ['latest' => $revision]),
            view('portal.workspaces.business-plan.review', compact('workspace', 'plan', 'values') + ['missing' => []]),
            view('portal.workspaces.business-plan.revision', compact('workspace', 'plan', 'values', 'revision') + ['latest' => $revision, 'source' => null]),
            view('portal.workspaces.business-plan.conflict', compact('workspace', 'plan') + ['submitted' => $values]),
            $app->make(App\Http\Controllers\PortalBusinessPlanController::class)->history($request, $workspace),
            $app->make(App\Http\Controllers\PortalWorkspaceController::class)->show($request, $workspace),
        ];
        foreach ($views as $view) {
            planRequire(strlen($view->render()) > 100, 'BUSINESS_PLAN_RENDER_FAILED');
        }
        echo "OWNER_BUSINESS_PLAN_DRAFT_REVIEW_HISTORY_AND_OVERVIEW=OK\nLIVE_USER_RECORDS_CREATED_FOR_RENDER=NONE\n";
    } else {
        throw new RuntimeException('UNKNOWN_MODE');
    }
} catch (Throwable $error) {
    $reason = preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'UNEXPECTED_ERROR';
    fwrite(STDERR, 'BUSINESS_PLAN_CHECK_FAILED='.$reason."\n");
    if ($reason === 'UNEXPECTED_ERROR') {
        fwrite(STDERR, 'BUSINESS_PLAN_ERROR_KIND='.get_class($error).';LINE='.$error->getLine()."\n");
    }
    exit(2);
}
