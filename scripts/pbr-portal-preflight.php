<?php

// Read-only deployment discovery. Never print environment contents or credentials.
$root = $argv[1] ?? '/var/www/thepbr-laravel';
echo "PBR_PORTAL_PREFLIGHT=START\n";
echo 'PHP_VERSION='.PHP_VERSION."\n";
echo 'SERVER_HOST='.php_uname('n')."\n";
function pbrReadCommand(array $command): ?string
{
    if (! function_exists('proc_open')) {
        return null;
    }
    $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (! is_resource($process)) {
        return null;
    }
    $value = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return proc_close($process) === 0 ? trim($value) : null;
}
if (! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
    echo "APP_FOUND=NO\n";
    exit(2);
}
chdir($root);
$head = pbrReadCommand(['git', '-C', $root, 'rev-parse', 'HEAD']);
echo 'LIVE_SOURCE_HEAD='.(preg_match('/^[a-f0-9]{40}$/', $head ?? '') ? $head : 'UNKNOWN')."\n";
$dirty = pbrReadCommand(['git', '-C', $root, 'status', '--porcelain']);
echo 'LIVE_SOURCE_CLEAN='.($dirty === null ? 'UNKNOWN' : ($dirty === '' ? 'YES' : 'NO'))."\n";
$remote = pbrReadCommand(['git', '-C', $root, 'remote', 'get-url', 'origin']);
echo 'LIVE_REPO_MATCH='.(preg_match('/github\\.com[:\\/]Ismail-wise\\/thepbr-website(?:\\.git)?$/i', $remote ?? '') ? 'YES' : 'NO')."\n";
$aiRoot = '/opt/pbr-partner-ai';
if (! is_dir($aiRoot)) {
    $serviceDirectory = pbrReadCommand(['systemctl', 'show', 'pbr-ai-advisor.service', '--property=WorkingDirectory', '--value']);
    if ($serviceDirectory && is_dir($serviceDirectory)) {
        $aiRoot = $serviceDirectory;
    }
}
$aiHead = pbrReadCommand(['git', '-C', $aiRoot, 'rev-parse', 'HEAD']);
echo 'AI_SOURCE_HEAD='.(preg_match('/^[a-f0-9]{40}$/', $aiHead ?? '') ? $aiHead : 'UNKNOWN')."\n";
require $root.'/vendor/autoload.php';
try {
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo "APP_FOUND=YES\n";
    echo 'APP_ENV='.$app->environment()."\n";
    echo 'APP_HOST='.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'unset')."\n";
    echo 'PORTAL_ENABLED='.(config('partner_portal.enabled') ? 'YES' : 'NO')."\n";
    echo 'DB_DRIVER='.Illuminate\Support\Facades\DB::connection()->getDriverName()."\n";
    Illuminate\Support\Facades\DB::select('SELECT 1');
    echo "DATABASE_CONNECTION=OK\n";
    foreach (['users', 'student_enrollments', 'partner_dynamics_assessments', 'ai_conversations', 'portal_conversations', 'portal_messages', 'portal_saved_items', 'portal_access_events'] as $table) {
        echo strtoupper($table).'_TABLE='.(Illuminate\Support\Facades\Schema::hasTable($table) ? 'YES' : 'NO')."\n";
    }
    $admins = App\Models\User::query()->where('account_status', 'active')->where(function ($query): void {
        $query->where('is_admin', true)->orWhere('role', 'admin');
    })->count();
    echo 'ACTIVE_ADMIN_EXISTS='.($admins > 0 ? 'YES' : 'NO')."\n";
    $base = rtrim((string) config('pbr_ai.base_url'), '/');
    $configured = strlen((string) config('pbr_ai.internal_secret')) >= 32;
    echo 'AI_SHARED_SECRET_CONFIGURED='.($configured ? 'YES' : 'NO')."\n";
    $url = parse_url($base);
    if (! in_array($url['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || isset($url['user']) || isset($url['pass'])) {
        echo "AI_ENDPOINT_LOCAL=NO\n";
    } else {
        echo "AI_ENDPOINT_LOCAL=YES\n";
        try {
            $health = Illuminate\Support\Facades\Http::connectTimeout(3)->timeout(5)->get($base.'/health');
            echo 'AI_HTTP_STATUS='.$health->status()."\n";
            echo 'AI_SERVICE_MATCH='.($health->json('service') === 'pbr-ai-advisor' ? 'YES' : 'NO')."\n";
            echo 'AI_KNOWLEDGE_READY='.($health->json('knowledgeReady') === true ? 'YES' : 'NO')."\n";
            echo 'AI_VECTORS='.(int) $health->json('vectors')."\n";
            if ($configured && $health->successful()) {
                // An empty message is rejected before retrieval/generation; no API charge or saved chat.
                $probe = Illuminate\Support\Facades\Http::withHeaders([
                    'X-PBR-Internal-Secret' => (string) config('pbr_ai.internal_secret'),
                ])->connectTimeout(3)->timeout(5)->post($base.'/internal/pbr/chat', ['message' => '']);
                echo 'AI_SHARED_SECRET_MATCH='.($probe->status() === 422 ? 'YES' : 'NO')."\n";
            }
        } catch (Throwable $error) {
            echo "AI_SERVICE_REACHABLE=NO\n";
        }
    }
    echo "PBR_PORTAL_PREFLIGHT=COMPLETE\n";
} catch (Throwable $error) {
    echo 'PREFLIGHT_ERROR_CLASS='.get_class($error)."\n";
    echo "PBR_PORTAL_PREFLIGHT=INCOMPLETE\n";
    exit(2);
}
