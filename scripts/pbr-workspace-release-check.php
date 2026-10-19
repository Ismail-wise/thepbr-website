<?php

// CLI-only deployment checks: private hashes, no record values on stdout.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const WORKSPACE_MIGRATION = '2026_10_18_160000_add_description_to_partnership_workspaces';

function workspaceRequire(bool $condition, string $code): void
{
    if (! $condition) throw new RuntimeException($code);
}

function workspaceTables(): array
{
    $tables = array_map(fn ($row) => $row->name, DB::select("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"));
    $tables = array_values(array_diff($tables, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']));
    sort($tables, SORT_STRING);

    return $tables;
}

function workspaceDigest(array $projection): array
{
    $result = [];
    foreach ($projection as $table => $columns) {
        $query = DB::table($table)->select($columns);
        if ($table === 'migrations') $query->where('migration', '!=', WORKSPACE_MIGRATION);
        $hashes = [];
        foreach ($query->cursor() as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row->{$column};
                $values[] = $value === null ? null : base64_encode((string) $value);
            }
            $hashes[] = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        }
        sort($hashes, SORT_STRING);
        $result[$table] = ['count' => count($hashes), 'sha256' => hash('sha256', implode("\n", $hashes))];
    }

    return $result;
}

try {
    [$script, $mode, $root] = array_pad($argv, 3, '');
    workspaceRequire(is_file($root.'/vendor/autoload.php'), 'APP_REQUIRED');
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    workspaceRequire(config('partner_portal.enabled') && parse_url((string) config('app.url'), PHP_URL_HOST) === 'thepbr.io', 'PORTAL_CONFIG_REQUIRED');
    workspaceRequire(DB::connection()->getDriverName() === 'mysql', 'MYSQL_REQUIRED');

    if ($mode === 'snapshot') {
        $tables = workspaceTables();
        sort($tables, SORT_STRING);
        $projection = [];
        foreach ($tables as $table) $projection[$table] = Schema::getColumnListing($table);
        workspaceRequire(isset($projection['partnership_workspaces'], $projection['users'], $projection['migrations']), 'EXPECTED_TABLES_REQUIRED');
        $hasColumn = in_array('description', $projection['partnership_workspaces'], true);
        $hasJournal = DB::table('migrations')->where('migration', WORKSPACE_MIGRATION)->exists();
        workspaceRequire($hasColumn === $hasJournal, 'PARTIAL_SCHEMA_REQUIRES_REVIEW');
        workspaceRequire(file_put_contents($argv[3], json_encode($projection, JSON_THROW_ON_ERROR)) !== false, 'PROJECTION_WRITE_FAILED');
        workspaceRequire(file_put_contents($argv[4], json_encode(workspaceDigest($projection), JSON_THROW_ON_ERROR)) !== false, 'DIGEST_WRITE_FAILED');
        echo "EXISTING_RECORDS_SNAPSHOTTED=YES\n";
    } elseif ($mode === 'verify') {
        $projection = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
        $expected = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
        workspaceRequire(workspaceDigest($projection) === $expected, 'EXISTING_RECORDS_CHANGED');
        $actualTables = workspaceTables();
        sort($actualTables, SORT_STRING);
        workspaceRequire($actualTables === array_keys($projection), 'UNEXPECTED_TABLE_CHANGE');
        foreach ($projection as $table => $columns) {
            if ($table === 'partnership_workspaces' && ! in_array('description', $columns, true)) $columns[] = 'description';
            workspaceRequire(Schema::getColumnListing($table) === $columns, 'UNEXPECTED_COLUMN_CHANGE');
        }
        $description = collect(Schema::getColumns('partnership_workspaces'))->firstWhere('name', 'description');
        workspaceRequire($description !== null && $description['nullable'] && Schema::getColumnType('partnership_workspaces', 'description') === 'text', 'DESCRIPTION_SCHEMA_INCOMPLETE');
        workspaceRequire(DB::table('migrations')->where('migration', WORKSPACE_MIGRATION)->count() === 1, 'MIGRATION_JOURNAL_INCOMPLETE');
        echo "ALL_PREEXISTING_RECORDS=PRESERVED\nONLY_REVIEWED_ADDITIVE_SCHEMA=YES\n";
    } elseif ($mode === 'render') {
        workspaceRequire(Schema::hasColumn('partnership_workspaces', 'description'), 'DESCRIPTION_COLUMN_REQUIRED');
        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'partners'] as $action) {
            workspaceRequire($app['router']->has('portal.workspaces.'.$action), 'WORKSPACE_ROUTE_REQUIRED');
        }
        // Unsaved negative-ID models exercise real templates without adding any
        // accounts, memberships, sessions, records or knowledge to the database.
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $student = new App\Models\User(['name' => 'Workspace render check', 'email' => 'render@example.invalid', 'role' => 'student', 'account_status' => 'active']);
        $student->id = -1;
        $request = Illuminate\Http\Request::create('https://thepbr.io/portal/workspaces');
        $request->setUserResolver(fn () => $student);
        $session = $app['session']->driver('array');$session->start();$request->setLaravelSession($session);
        $app->instance('request', $request);$app['url']->setRequest($request);
        $app['view']->share('errors', new Illuminate\Support\ViewErrorBag);
        Illuminate\Support\Facades\Auth::guard()->setUser($student);
        $controller = $app->make(App\Http\Controllers\PortalWorkspaceController::class);
        $workspace = new App\Models\PartnershipWorkspace(['owner_user_id' => -1, 'name' => 'Workspace render check', 'status' => 'active']);$workspace->id = -1;
        foreach ([$controller->index($request), $controller->create($request), $controller->show($request, $workspace), $controller->edit($request, $workspace), $controller->partners($request, $workspace)] as $view) {
            workspaceRequire(strlen($view->render()) > 100, 'WORKSPACE_RENDER_FAILED');
        }
        echo "OWNER_WORKSPACE_FORMS_AND_PARTNERS=OK\n";
    } else {
        throw new RuntimeException('UNKNOWN_MODE');
    }
} catch (Throwable $error) {
    $code = preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'UNEXPECTED_ERROR';
    fwrite(STDERR, 'WORKSPACE_CHECK_FAILED='.$code."\n");
    exit(2);
}
