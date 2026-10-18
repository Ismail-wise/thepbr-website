<?php

// CLI-only deployment checks: private hashes, no record values on stdout.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const KNOWLEDGE_MIGRATION = '2026_10_18_180000_create_private_workspace_knowledge';
const KNOWLEDGE_TABLES = ['workspace_knowledge_sources', 'workspace_knowledge_chunks', 'workspace_ai_conversations', 'workspace_ai_messages'];

function knowledgeRequire(bool $condition, string $code): void
{
    if (! $condition) throw new RuntimeException($code);
}

function knowledgeTables(): array
{
    $tables = array_map(fn ($row) => $row->name, DB::select("SELECT TABLE_NAME AS name FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"));
    $tables = array_values(array_diff($tables, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']));
    sort($tables, SORT_STRING);

    return $tables;
}

function knowledgeDigest(array $projection): array
{
    $result = [];
    foreach ($projection as $table => $columns) {
        $query = DB::table($table)->select($columns);
        if ($table === 'migrations') $query->where('migration', '!=', KNOWLEDGE_MIGRATION);
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
    knowledgeRequire(is_file($root.'/vendor/autoload.php'), 'APP_REQUIRED');
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    knowledgeRequire(config('partner_portal.enabled') && parse_url((string) config('app.url'), PHP_URL_HOST) === 'thepbr.io', 'PORTAL_CONFIG_REQUIRED');
    knowledgeRequire(DB::connection()->getDriverName() === 'mysql', 'MYSQL_REQUIRED');

    if ($mode === 'snapshot') {
        $tables = knowledgeTables();
        sort($tables, SORT_STRING);
        $projection = [];
        foreach ($tables as $table) $projection[$table] = Schema::getColumnListing($table);
        knowledgeRequire(isset($projection['partnership_workspaces'], $projection['users'], $projection['migrations']), 'EXPECTED_TABLES_REQUIRED');
        $present = array_values(array_intersect(KNOWLEDGE_TABLES, array_keys($projection)));
        $hasJournal = DB::table('migrations')->where('migration', KNOWLEDGE_MIGRATION)->exists();
        knowledgeRequire((count($present) === 0 && ! $hasJournal) || (count($present) === 4 && $hasJournal), 'PARTIAL_SCHEMA_REQUIRES_REVIEW');
        knowledgeRequire(file_put_contents($argv[3], json_encode($projection, JSON_THROW_ON_ERROR)) !== false, 'PROJECTION_WRITE_FAILED');
        knowledgeRequire(file_put_contents($argv[4], json_encode(knowledgeDigest($projection), JSON_THROW_ON_ERROR)) !== false, 'DIGEST_WRITE_FAILED');
        echo "EXISTING_RECORDS_SNAPSHOTTED=YES\n";
    } elseif ($mode === 'verify') {
        $projection = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
        $expected = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
        knowledgeRequire(knowledgeDigest($projection) === $expected, 'EXISTING_RECORDS_CHANGED');
        $actualTables = knowledgeTables();
        $expectedTables = array_values(array_unique(array_merge(array_keys($projection), KNOWLEDGE_TABLES)));
        sort($expectedTables, SORT_STRING);
        knowledgeRequire($actualTables === $expectedTables, 'UNEXPECTED_TABLE_CHANGE');
        foreach ($projection as $table => $columns) knowledgeRequire(Schema::getColumnListing($table) === $columns, 'UNEXPECTED_COLUMN_CHANGE');
        foreach (KNOWLEDGE_TABLES as $table) {
            knowledgeRequire(Schema::hasTable($table), 'KNOWLEDGE_SCHEMA_INCOMPLETE');
            if (! array_key_exists($table, $projection)) knowledgeRequire(DB::table($table)->count() === 0, 'NEW_KNOWLEDGE_ROWS_DURING_CUTOVER');
        }
        $newColumns = [
            'workspace_knowledge_sources' => ['id','workspace_id','title','kind','business_status','processing_status','extracted_text','reviewed_text','file_path','original_filename','mime_type','file_bytes','file_sha256','extracted_partial','revision','embedding_key','ready_at','created_at','updated_at'],
            'workspace_knowledge_chunks' => ['id','workspace_id','source_id','revision','content','embedding','embedding_key'],
            'workspace_ai_conversations' => ['id','workspace_id','user_id','title','knowledge_signature','created_at','updated_at'],
            'workspace_ai_messages' => ['id','conversation_id','role','content','metadata','created_at','updated_at'],
        ];
        foreach ($newColumns as $table => $columns) knowledgeRequire(Schema::getColumnListing($table) === $columns, 'UNEXPECTED_PRIVATE_SCHEMA');
        knowledgeRequire(DB::table('migrations')->where('migration', KNOWLEDGE_MIGRATION)->count() === 1, 'MIGRATION_JOURNAL_INCOMPLETE');
        echo "ALL_PREEXISTING_RECORDS=PRESERVED\nONLY_REVIEWED_ADDITIVE_SCHEMA=YES\n";
    } elseif ($mode === 'render') {
        foreach (KNOWLEDGE_TABLES as $table) knowledgeRequire(Schema::hasTable($table), 'KNOWLEDGE_TABLE_REQUIRED');
        knowledgeRequire(config('filesystems.disks.workspace_private.serve') === false, 'PRIVATE_FILE_DELIVERY_REQUIRED');
        foreach (['knowledge.index', 'knowledge.create', 'knowledge.store', 'knowledge.show', 'knowledge.update', 'knowledge.download', 'knowledge.extract', 'knowledge.destroy', 'ai', 'ai.chat', 'ai.destroy'] as $action) {
            knowledgeRequire($app['router']->has('portal.workspaces.'.$action), 'WORKSPACE_ROUTE_REQUIRED');
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
        $controller = $app->make(App\Http\Controllers\PortalKnowledgeController::class);
        $workspace = new App\Models\PartnershipWorkspace(['owner_user_id' => -1, 'name' => 'Private Knowledge render check', 'status' => 'active']);$workspace->id = -1;
        $source = new App\Models\WorkspaceKnowledgeSource(['workspace_id' => -1, 'title' => 'Private review render check', 'kind' => 'text', 'business_status' => 'current', 'processing_status' => 'needs_review', 'reviewed_text' => 'Synthetic review text', 'revision' => 1]);$source->id = -1;
        $ai = $app->make(App\Http\Controllers\PortalWorkspaceAiController::class);
        foreach ([$controller->index($request, $workspace), $controller->create($request, $workspace), $controller->show($request, $workspace, $source), $ai->index($request, $workspace)] as $view) {
            knowledgeRequire(strlen($view->render()) > 100, 'PRIVATE_KNOWLEDGE_RENDER_FAILED');
        }
        echo "OWNER_KNOWLEDGE_REVIEW_AND_WORKSPACE_AI_FORMS=OK\n";
    } elseif ($mode === 'ai-check') {
        $gateway = $app->make(App\Services\Portal\PartnerAiGateway::class);
        $vectors = $gateway->embedPrivate(['Synthetic partnership review 42']);
        knowledgeRequire(count($vectors['vectors']) === 1, 'PRIVATE_EMBEDDING_FAILED');
        $fixture = base64_decode(trim(file_get_contents(__DIR__.'/fixtures/pbr-private-ocr.png.base64')), true);
        knowledgeRequire(is_string($fixture), 'OCR_FIXTURE_REQUIRED');
        $extracted = $gateway->extractPrivate($fixture, 'image/png');
        knowledgeRequire(str_contains($extracted['text'], '42') && stripos($extracted['text'], 'PBR') !== false, 'PRIVATE_OCR_FAILED');
        foreach (['personal', 'workspace'] as $scope) {
            $response = $scope === 'personal' ? $gateway->open('In one short sentence, what should business partners agree before starting?', [])
                : $gateway->openWorkspace('What number is in the reviewed source? Reply in one short sentence with a source reference.', [], [
                    'scope' => 'owner_private_knowledge_v1', 'workspace' => ['name' => 'Synthetic release check'],
                    'sources' => [['reference' => 'S1', 'title' => 'Release check', 'business_status' => 'current']],
                    'excerpts' => [['reference' => 'S1', 'business_status' => 'current', 'text' => 'The synthetic reviewed number is 42.']],
                ]);
            $body = $response->toPsrResponse()->getBody();$text = '';$done = false;$failed = false;$buffer = '';
            $deadline = microtime(true) + (int) config('pbr_ai.timeout', 180);
            try {
                while (! $body->eof()) {
                    knowledgeRequire(microtime(true) < $deadline, 'AI_GENERATION_TIMED_OUT');
                    $buffer .= $body->read(4096);
                    knowledgeRequire(strlen($buffer) < 1048576, 'AI_RESPONSE_LIMIT');
                }
                foreach (explode("\n", $buffer) as $line) {
                    if (! str_starts_with($line, 'data:')) continue;
                    $event = json_decode(trim(substr($line, 5)), true);
                    if (($event['type'] ?? '') === 'delta') $text .= $event['text'] ?? '';
                    if (($event['type'] ?? '') === 'done') $done = true;
                    if (($event['type'] ?? '') === 'error') $failed = true;
                }
                knowledgeRequire($done && ! $failed && trim($text) !== '', 'AI_GENERATION_FAILED');
                if ($scope === 'workspace') knowledgeRequire(str_contains($text, '42') && str_contains($text, '[S1]'), 'AI_PRIVATE_CITATION_FAILED');
            } finally { $body->close(); }
        }
        echo "ORIGINAL_AI_AND_PRIVATE_KNOWLEDGE_GENERATION=OK\nPRIVATE_OCR_AND_EMBEDDING=OK\nLIVE_USER_DATA_SENT_FOR_TESTING=NONE\n";
    } else {
        throw new RuntimeException('UNKNOWN_MODE');
    }
} catch (Throwable $error) {
    $code = preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'UNEXPECTED_ERROR';
    fwrite(STDERR, 'KNOWLEDGE_CHECK_FAILED='.$code."\n");
    exit(2);
}
