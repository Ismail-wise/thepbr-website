<?php

// Root-operated release helper. Stdout contains counts/guard reasons only.
function pbrResetRequire(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

function pbrResetRead(string $path): array
{
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function pbrResetWrite(string $path, array $value): void
{
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(8));
    $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    pbrResetRequire(file_put_contents($temporary, $json, LOCK_EX) === strlen($json), 'RESET_JOURNAL_WRITE_FAILED');
    chmod($temporary, 0600);
    pbrResetRequire(rename($temporary, $path), 'RESET_JOURNAL_RENAME_FAILED');
}

try {
    $mode = $argv[1] ?? '';
    $root = realpath($argv[2] ?? '');
    $state = realpath($argv[3] ?? '');
    pbrResetRequire($root !== false && $state !== false
        && str_starts_with($state, '/root/thepbr-deploy-backups/portal-test-reset-'), 'PRIVATE_RESET_STATE_REQUIRED');
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    pbrResetRequire($app->environment('production') && config('partner_portal.enabled')
        && parse_url((string) config('app.url'), PHP_URL_HOST) === 'thepbr.io', 'PRODUCTION_PORTAL_REQUIRED');
    pbrResetRequire(config('app.maintenance.driver') === 'file' && $app->maintenanceMode()->active(), 'RESET_REQUIRES_MAINTENANCE');
    require_once dirname(__DIR__).'/app/Services/Portal/PortalTestDataCleanup.php';
    $db = Illuminate\Support\Facades\DB::connection();
    pbrResetRequire($db->getDriverName() === 'mysql', 'RESET_REQUIRES_MYSQL');
    $planPath = $state.'/reset-plan.json';

    if ($mode === 'plan') {
        $cleanup = new App\Services\Portal\PortalTestDataCleanup($db);
        $plan = $cleanup->plan();
        pbrResetWrite($planPath, $plan);
        echo 'PLANNED_WORKSPACES_TO_REMOVE='.count($plan['workspace_ids'])."\n";
        echo 'PLANNED_NON_ADMIN_ACCOUNTS_TO_REMOVE='.count($plan['user_ids'])."\n";
        echo 'PLANNED_ADMIN_ACCOUNTS_TO_KEEP='.count($plan['admin_ids'])."\n";
        echo "RESET_SCOPE_VERIFIED=YES\n";
    } elseif ($mode === 'rehearse') {
        $clone = pbrResetRead($argv[4]);
        pbrResetRequire(preg_match('/^pbr_reset_drill_[a-f0-9]{16}$/D', $clone['database']) === 1
            && $clone['database'] !== $db->getDatabaseName(), 'ISOLATED_RESET_DATABASE_REQUIRED');
        $config = array_replace($db->getConfig(), [
            'url' => null, 'host' => 'localhost', 'unix_socket' => $clone['socket'],
            'database' => $clone['database'], 'username' => $clone['username'], 'password' => $clone['password'],
        ]);
        config(['database.connections.pbr_reset_rehearsal' => $config]);
        $rehearsal = Illuminate\Support\Facades\DB::connection('pbr_reset_rehearsal');
        $cleanup = new App\Services\Portal\PortalTestDataCleanup($rehearsal);
        $plan = pbrResetRead($planPath);
        $result = $cleanup->apply($plan);
        $plan['after_fingerprint'] = $result['after_fingerprint'];
        pbrResetWrite($planPath, $plan);
        pbrResetWrite($state.'/rehearsal-result.json', $result);
        echo "ISOLATED_MYSQL_RESET=OK\nADMIN_CREDENTIALS_AND_PUBLIC_CONTENT_REHEARSAL=PRESERVED\n";
    } elseif (in_array($mode, ['apply', 'outcome'], true)) {
        $journal = $argv[4] ?? '';
        pbrResetRequire(dirname($journal) === '/root/thepbr-deploy-backups'
            && preg_match('/^\.portal-test-reset-[a-f0-9]{64}\.json$/D', basename($journal)) === 1, 'PRIVATE_RESET_JOURNAL_REQUIRED');
        $plan = pbrResetRead($planPath);
        pbrResetRequire(isset($plan['after_fingerprint']) && is_file($state.'/rehearsal-result.json'), 'MYSQL_RESET_REHEARSAL_REQUIRED');
        $cleanup = new App\Services\Portal\PortalTestDataCleanup($db);
        $lock = 'pbr-reset-'.substr(hash('sha256', $db->getDatabaseName()), 0, 40);
        pbrResetRequire((int) $db->selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lock])->acquired === 1, 'RESET_DATABASE_LOCK_BUSY');
        try {
            if ($mode === 'apply') {
                pbrResetRequire(! file_exists($journal), 'ONE_TIME_RESET_ALREADY_STARTED');
                $entry = ['status' => 'pending', 'state' => $state, 'database' => $db->getDatabaseName(),
                    'target' => trim(file_get_contents($state.'/target.txt'))];
                pbrResetWrite($journal, $entry);
                try {
                    $result = $cleanup->apply($plan);
                    pbrResetWrite($state.'/reset-result.json', $result);
                    pbrResetWrite($journal, array_merge($entry, ['status' => 'committed', 'completed_utc' => gmdate('c')]));
                    echo 'WORKSPACES_REMOVED='.$result['workspaces_removed']."\n";
                    echo 'NON_ADMIN_ACCOUNTS_REMOVED='.$result['accounts_removed']."\n";
                    echo 'ADMIN_ACCOUNTS_RETAINED='.$result['admins_retained']."\n";
                    echo "ADMIN_CREDENTIALS_AND_PUBLIC_CONTENT=PRESERVED\nDATABASE_RESET_COMMITTED=YES\n";
                } catch (Throwable $error) {
                    $digest = $cleanup->fingerprint();
                    // A zero-change reset has equal before/after digests;
                    // prefer committed so a retry cannot target future users.
                    if ($digest === $plan['after_fingerprint']) {
                        pbrResetWrite($journal, array_merge($entry, ['status' => 'committed']));
                        echo "DATABASE_RESET_COMMITTED=YES\n";
                    } elseif ($digest === $plan['before_fingerprint']) {
                        pbrResetWrite($journal, array_merge($entry, ['status' => 'rolled_back']));
                        echo "DATABASE_RESET_ROLLED_BACK=YES\n";
                    }
                    throw $error;
                }
            } else {
                $entry = pbrResetRead($journal);
                pbrResetRequire(($entry['state'] ?? '') === $state, 'RESET_JOURNAL_STATE_MISMATCH');
                $digest = $cleanup->fingerprint();
                if ($digest === $plan['after_fingerprint']) {
                    pbrResetWrite($journal, array_merge($entry, ['status' => 'committed']));
                    echo "DATABASE_RESET_COMMITTED=YES\n";
                } elseif ($digest === $plan['before_fingerprint']) {
                    pbrResetWrite($journal, array_merge($entry, ['status' => 'rolled_back']));
                    echo "DATABASE_RESET_ROLLED_BACK=YES\n";
                } else {
                    echo "DATABASE_RESET_OUTCOME=NEEDS_REVIEW\n";
                    exit(2);
                }
            }
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } else {
        throw new RuntimeException('UNKNOWN_RESET_MODE');
    }
} catch (Throwable $error) {
    error_log(get_class($error).': '.$error->getMessage());
    $reason = $error instanceof RuntimeException && preg_match('/^[A-Z_]{3,100}$/D', $error->getMessage())
        ? $error->getMessage() : get_class($error);
    echo 'RESET_CHECK_FAILED='.$reason."\n";
    exit(2);
}
