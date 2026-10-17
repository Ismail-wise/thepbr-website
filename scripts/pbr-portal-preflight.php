<?php

// Read-only deployment discovery. Never print environment contents or credentials.
$root = $argv[1] ?? '/var/www/thepbr-laravel';
echo "PBR_PORTAL_PREFLIGHT=START\n";
echo 'PHP_VERSION='.PHP_VERSION."\n";
if (! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
    echo "APP_FOUND=NO\n";
    exit(2);
}
chdir($root);
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
