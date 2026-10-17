<?php

// Installer support. No credentials, user identities or raw provider errors go
// to stdout. This file never creates accounts or grants Student access.
function pbrReleaseRequire(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

function pbrPortalFlag(string $path, bool $enabled): void
{
    pbrReleaseRequire(is_file($path) && ! is_link($path), 'REGULAR_ENV_FILE_REQUIRED');
    $stat = stat($path);
    $text = file_get_contents($path);
    $text = preg_replace('/^[\t ]*(?:export[\t ]+)?PBR_PARTNER_PORTAL_ENABLED[\t ]*=.*(?:\r?\n|$)/m', '', $text);
    $text = rtrim($text, "\r\n")."\nPBR_PARTNER_PORTAL_ENABLED=".($enabled ? 'true' : 'false')."\n";
    $temporary = dirname($path).'/.pbr-portal-env-'.bin2hex(random_bytes(8));
    try {
        pbrReleaseRequire(file_put_contents($temporary, $text, LOCK_EX) === strlen($text), 'ENV_WRITE_FAILED');
        pbrReleaseRequire(chmod($temporary, $stat['mode'] & 0777), 'ENV_MODE_FAILED');
        pbrReleaseRequire(chown($temporary, $stat['uid']) && chgrp($temporary, $stat['gid']), 'ENV_OWNER_FAILED');
        pbrReleaseRequire(rename($temporary, $path), 'ENV_REPLACE_FAILED');
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

try {
    $mode = $argv[1] ?? '';
    $root = $argv[2] ?? '';
    if (in_array($mode, ['enable', 'disable'], true)) {
        pbrPortalFlag($root.'/.env', $mode === 'enable');
        echo "PORTAL_ENV_UPDATED=YES\n";
        exit;
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    if ($mode === 'inspect') {
        pbrReleaseRequire($app->environment('production') && parse_url((string) config('app.url'), PHP_URL_HOST) === 'thepbr.io', 'PRODUCTION_APP_MISMATCH');
        pbrReleaseRequire(! config('partner_portal.enabled'), 'PORTAL_ALREADY_ENABLED');
        pbrReleaseRequire(config('app.maintenance.driver') === 'file' && ! $app->maintenanceMode()->active(), 'EXISTING_MAINTENANCE_REQUIRES_REVIEW');
        $connection = Illuminate\Support\Facades\DB::connection();
        pbrReleaseRequire($connection->getDriverName() === 'mysql', 'MYSQL_REQUIRED');
        $metadata = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
        pbrReleaseRequire($connection->getDatabaseName() === $metadata['database']
            && $connection->getPdo()->query('SELECT @@socket')->fetchColumn() === $metadata['socket'], 'BACKUP_DATABASE_MISMATCH');
        $existing = 0;
        foreach (['portal_conversations', 'portal_messages', 'portal_saved_items', 'portal_access_events'] as $table) {
            $existing += (int) Illuminate\Support\Facades\Schema::hasTable($table);
        }
        pbrReleaseRequire($existing === 0 || ($existing === 4 && Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_10_17_060000_create_partner_portal_tables')->exists()), 'PARTIAL_PORTAL_SCHEMA_REQUIRES_REVIEW');
        if (isset($argv[4])) {
            $fingerprint = hash('sha256', serialize([
                config('app.key'), config('app.url'), config('app.display_timezone'),
                config('session.driver'), config('pbr_ai'), $connection->getConfig(),
            ]));
            pbrReleaseRequire(file_put_contents($argv[4], $fingerprint) === strlen($fingerprint), 'CONFIG_FINGERPRINT_WRITE_FAILED');
            chmod($argv[4], 0600);
        }
        echo "LIVE_RELEASE_CONFIGURATION=OK\n";
    } elseif ($mode === 'ai') {
        $url = parse_url((string) config('pbr_ai.base_url'));
        pbrReleaseRequire(in_array($url['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
            && ! isset($url['user']) && ! isset($url['pass']), 'LOCAL_AI_ENDPOINT_REQUIRED');
        // Load the reviewed gateway without changing the live baseline code.
        require_once $argv[3].'/app/Services/Portal/PartnerAiGateway.php';
        $response = (new App\Services\Portal\PartnerAiGateway)->open(
            'Partnership Business မှာ Partner တွေရဲ့ capital contribution၊ စတင်ရင်းနှီးငွေ၊ ownership နဲ့ profit sharing ကို ဘယ်လို ခွဲခြားသတ်မှတ်သင့်သလဲ? မူလ PBR knowledge ကို အခြေခံပြီး အကျဉ်းချုပ်ရှင်းပြပါ။',
            [],
        );
        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $answer = '';
        $rag = false;
        $done = false;
        $failed = false;
        $bytes = 0;
        $started = microtime(true);
        try {
            while (! $body->eof()) {
                pbrReleaseRequire(microtime(true) - $started < 210 && $bytes < 2_000_000, 'AI_STREAM_LIMIT');
                $chunk = $body->read(4096);
                $bytes += strlen($chunk);
                $buffer .= $chunk;
                while (($position = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $position), "\r");
                    $buffer = substr($buffer, $position + 1);
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $event = json_decode(trim(substr($line, 5)), true, 512, JSON_THROW_ON_ERROR);
                    if (($event['type'] ?? '') === 'meta') {
                        $rag = ($event['mode'] ?? '') === 'rag';
                    } elseif (($event['type'] ?? '') === 'delta') {
                        $answer .= (string) ($event['text'] ?? '');
                    } elseif (($event['type'] ?? '') === 'error') {
                        $failed = true;
                    } elseif (($event['type'] ?? '') === 'done') {
                        $done = true;
                    }
                }
            }
        } finally {
            $body->close();
        }
        pbrReleaseRequire($done && ! $failed && trim($answer) !== '', 'AI_GENERATION_NOT_COMPLETE');
        echo "LIVE_AI_GENERATION=OK\n";
        echo 'LIVE_AI_RETRIEVAL='.($rag ? 'rag' : 'unconfirmed')."\n";
        pbrReleaseRequire($rag, 'PBR_KNOWLEDGE_RETRIEVAL_NOT_CONFIRMED');
        pbrReleaseRequire(file_put_contents($argv[4], $answer, LOCK_EX) === strlen($answer), 'AI_PROOF_WRITE_FAILED');
        chmod($argv[4], 0600);
        echo 'LIVE_AI_ANSWER_BYTES='.strlen($answer)."\n";
    } elseif ($mode === 'render') {
        pbrReleaseRequire($app->environment('production') && config('partner_portal.enabled'), 'PORTAL_CONFIG_NOT_ENABLED');
        foreach (['portal_conversations', 'portal_messages', 'portal_saved_items', 'portal_access_events'] as $table) {
            pbrReleaseRequire(Illuminate\Support\Facades\Schema::hasTable($table), 'PORTAL_TABLE_MISSING');
        }
        foreach (['portal.home', 'portal.ai', 'portal.ai.chat', 'portal.tool', 'portal.library', 'portal.accounts', 'portal.grant', 'portal.suspend', 'partner-dynamics.index'] as $route) {
            pbrReleaseRequire($app['router']->has($route), 'PORTAL_ROUTE_MISSING');
        }
        $admin = App\Models\User::query()->where('account_status', 'active')->where(fn ($query) => $query->where('is_admin', true)->orWhere('role', 'admin'))->first();
        pbrReleaseRequire($admin !== null && $admin->isAdmin(), 'ACTIVE_ADMIN_REQUIRED');
        // An in-memory guard/session renders real templates without logging in
        // or creating sessions, accounts, conversations or saved business data.
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $request = Illuminate\Http\Request::create('https://thepbr.io/portal');
        $request->setUserResolver(fn () => $admin);
        $request->setLaravelSession($app['session']->driver('array'));
        $app->instance('request', $request);
        $app['url']->setRequest($request);
        Illuminate\Support\Facades\Auth::guard()->setUser($admin);
        $portal = $app->make(App\Http\Controllers\PartnerPortalController::class);
        $home = $portal->home($request, $app->make(App\Services\Portal\StudentPortalAccess::class))->render();
        pbrReleaseRequire(str_contains($home, 'PBR AI') && str_contains($home, 'PartnerDynamics') && str_contains($home, 'Accounts &amp; Access'), 'PORTAL_HOME_RENDER_FAILED');
        pbrReleaseRequire(strlen($app->make(App\Http\Controllers\PortalAiController::class)->index($request)->render()) > 100, 'AI_PAGE_RENDER_FAILED');
        foreach (['capital', 'shares', 'checklist'] as $tool) {
            pbrReleaseRequire(strlen($app->make(App\Http\Controllers\PortalToolsController::class)->show($request, $tool)->render()) > 100, 'TOOL_RENDER_FAILED');
        }
        pbrReleaseRequire(strlen($portal->library($request)->render()) > 100 && strlen($portal->accounts($request, $app->make(App\Services\Portal\StudentPortalAccess::class))->render()) > 100, 'PORTAL_LIBRARY_OR_ADMIN_RENDER_FAILED');
        echo "PORTAL_TABLES_AND_ROUTES=OK\nPORTAL_RUNTIME_RENDER=OK\n";
    } else {
        throw new RuntimeException('UNKNOWN_RELEASE_CHECK');
    }
} catch (Throwable $error) {
    // Only our enumerated guard reasons are printable. Provider, database and
    // framework exception messages can contain sensitive configuration.
    $reason = $error instanceof RuntimeException && preg_match('/^[A-Z_]{3,80}$/D', $error->getMessage())
        ? $error->getMessage() : get_class($error);
    echo 'RELEASE_CHECK_FAILED='.$reason."\n";
    exit(2);
}
