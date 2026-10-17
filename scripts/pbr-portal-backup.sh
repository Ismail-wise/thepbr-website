#!/usr/bin/env bash
# Backup and restore rehearsal only. Does not deploy, migrate or enable the portal.
set -Eeuo pipefail
umask 077

APP_ROOT="${1:-/var/www/thepbr-laravel}"
EXPECTED_HEAD="b71122dee5f0585807223b94b33211e1399df4dd"
BACKUP_PARENT="/root/thepbr-deploy-backups"
BACKUP=""
STAGE="prerequisites"
DRILL_DB=""
DRILL_USER=""
DB_CREATED=0
USER_CREATED=0
VERIFIED=0
ADMIN=()

echo "PBR_PORTAL_BACKUP=START"
stop() { echo "BACKUP_STOP_REASON=$1"; exit 2; }

finish() {
    local result=$? cleanup_ok=1
    trap - EXIT ERR
    set +e
    if [[ "$USER_CREATED" == 1 ]]; then
        printf "DROP USER '%s'@'localhost';\n" "$DRILL_USER" | "${ADMIN[@]}" >>"$BACKUP/private.log" 2>&1
        if [[ $? == 0 ]]; then echo "TEMP_USER_REMOVED=YES"; else cleanup_ok=0; echo "TEMP_USER_REMOVED=NO"; fi
    fi
    if [[ "$DB_CREATED" == 1 && "$DRILL_DB" =~ ^pbr_portal_drill_[a-f0-9]{16}$ && "$DRILL_DB" != "${DB_NAME:-}" ]]; then
        printf 'DROP DATABASE `%s`;\n' "$DRILL_DB" | "${ADMIN[@]}" >>"$BACKUP/private.log" 2>&1
        if [[ $? == 0 ]]; then echo "TEMP_DATABASE_REMOVED=YES"; else cleanup_ok=0; echo "TEMP_DATABASE_REMOVED=NO"; fi
    fi
    if [[ -n "$BACKUP" ]]; then
        rm -f -- "$BACKUP/drill.cnf" "$BACKUP/restore-check.sql" "$BACKUP/source.schema" "$BACKUP/restore.schema" "$BACKUP/source.rows" "$BACKUP/restore.rows"
        rm -rf -- "$BACKUP/restore-app"
        echo "BACKUP_PATH=$BACKUP"
    fi
    if [[ "$result" == 0 && "$VERIFIED" == 1 && "$cleanup_ok" == 1 ]]; then
        if ! printf 'SOURCE_HEAD=%s\nVERIFIED_UTC=%s\n' "$EXPECTED_HEAD" "$(date -u +%FT%TZ)" >"$BACKUP/restore-verified.txt"; then
            echo "PBR_PORTAL_BACKUP=INCOMPLETE"
            exit 2
        fi
        echo "LIVE_PORTAL_SWITCHED=NO"
        echo "PBR_PORTAL_BACKUP=COMPLETE"
    else
        echo "BACKUP_FAILED_STAGE=$STAGE"
        echo "PBR_PORTAL_BACKUP=INCOMPLETE"
        result=2
    fi
    exit "$result"
}
trap finish EXIT
trap 'echo "BACKUP_STEP_FAILED=$STAGE"' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

[[ "$EUID" == 0 ]] || stop "RUN_WITH_SUDO_ON_PBR_SERVER"
[[ $# -le 1 ]] || stop "EXPECTED_ONE_APP_PATH"
for executable in php git mysql mysqldump tar gzip openssl sha256sum cmp diff sort sed flock realpath find du df awk; do
    command -v "$executable" >/dev/null || stop "MISSING_${executable^^}"
done
APP_ROOT="$(realpath -e -- "$APP_ROOT")"
[[ -f "$APP_ROOT/artisan" && -f "$APP_ROOT/vendor/autoload.php" && -f "$APP_ROOT/.env" ]] || stop "LARAVEL_FILES_MISSING"
[[ ! -L "$APP_ROOT/.env" ]] || stop "LINKED_ENVIRONMENT_FILE_NEEDS_REVIEW"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$EXPECTED_HEAD" ]] || stop "LIVE_SOURCE_CHANGED_RECHECK_REQUIRED"
[[ -z "$(git -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_NOT_CLEAN"

[[ ! -L "$BACKUP_PARENT" ]] || stop "BACKUP_PARENT_IS_SYMLINK"
mkdir -p -- "$BACKUP_PARENT"
chmod 700 -- "$BACKUP_PARENT"
exec 9>"$BACKUP_PARENT/.partner-portal-backup.lock"
flock -n 9 || stop "ANOTHER_PORTAL_BACKUP_IS_RUNNING"
BACKUP="$BACKUP_PARENT/pre-partner-portal-$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 4)"
mkdir -m 700 -- "$BACKUP"
exec 2>>"$BACKUP/private.log"

STAGE="read_live_configuration"
echo "STEP=$STAGE"
php -d display_errors=0 -d log_errors=0 /dev/stdin "$APP_ROOT" "$BACKUP/database-meta.json" <<'PHP'
<?php
try {
    $root = $argv[1];
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $db = Illuminate\Support\Facades\DB::connection();
    if (! $app->environment('production') || parse_url((string) config('app.url'), PHP_URL_HOST) !== 'thepbr.io'
        || config('partner_portal.enabled') || $db->getDriverName() !== 'mysql') {
        throw new RuntimeException('Unexpected application environment.');
    }
    $config = $db->getConfig();
    if (! in_array($config['host'] ?? 'localhost', ['localhost', '127.0.0.1', '::1'], true)
        || ! empty($config['read']) || ! empty($config['write'])) {
        throw new RuntimeException('Only the verified local database is supported.');
    }
    $name = $db->getDatabaseName();
    $pdo = $db->getPdo();
    $socket = (string) $pdo->query('SELECT @@socket')->fetchColumn();
    $host = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
    if (! preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name) || ! preg_match('~^/[A-Za-z0-9_./-]+$~D', $socket)) {
        throw new RuntimeException('Unsupported database identifier or socket.');
    }
    file_put_contents($argv[2], json_encode(['database' => $name, 'socket' => $socket, 'server' => $host], JSON_THROW_ON_ERROR));
    echo "LIVE_CONFIGURATION_MATCH=YES\n";
} catch (Throwable $error) {
    echo 'CONFIGURATION_ERROR_CLASS='.get_class($error)."\n";
    exit(2);
}
PHP
DB_NAME="$(php -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["database"];' "$BACKUP/database-meta.json")"
DB_SOCKET="$(php -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["socket"];' "$BACKUP/database-meta.json")"
DB_SERVER="$(php -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["server"];' "$BACKUP/database-meta.json")"
ADMIN=(mysql --protocol=socket --socket="$DB_SOCKET" --user=root --batch --skip-column-names)

STAGE="local_database_admin"
echo "STEP=$STAGE"
[[ "$("${ADMIN[@]}" --execute='SELECT @@hostname;')" == "$DB_SERVER" ]] || stop "LOCAL_DATABASE_ADMIN_UNAVAILABLE"
echo "LOCAL_DATABASE_ADMIN=OK"
UNSUPPORTED="$("${ADMIN[@]}" --database="$DB_NAME" --execute="SELECT
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND (table_type <> 'BASE TABLE' OR engine <> 'InnoDB')) +
    (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE()) +
    (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE()) +
    (SELECT COUNT(*) FROM information_schema.events WHERE event_schema=DATABASE());")"
[[ "$UNSUPPORTED" == 0 ]] || stop "CUSTOM_DATABASE_OBJECTS_NEED_REVIEW"
TABLE_COUNT="$("${ADMIN[@]}" --database="$DB_NAME" --execute='SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();')"
[[ "$TABLE_COUNT" =~ ^[0-9]+$ && "$TABLE_COUNT" -gt 0 ]] || stop "DATABASE_HAS_NO_TABLES"
echo "DATABASE_OBJECTS_SUPPORTED=YES"

STAGE="storage_space"
echo "STEP=$STAGE"
[[ ! -L "$APP_ROOT/storage" && ! -L "$APP_ROOT/storage/app" ]] || stop "LINKED_UPLOAD_STORAGE_NEEDS_REVIEW"
if [[ -d "$APP_ROOT/storage/app" ]] && [[ -n "$(find "$APP_ROOT/storage/app" -type l -print -quit)" ]]; then
    stop "LINKED_UPLOAD_STORAGE_NEEDS_REVIEW"
fi
DB_BYTES="$("${ADMIN[@]}" --database="$DB_NAME" --execute='SELECT COALESCE(SUM(data_length+index_length),0) FROM information_schema.tables WHERE table_schema=DATABASE();')"
DATA_DIR="$("${ADMIN[@]}" --execute='SELECT @@datadir;')"
APP_BYTES="$(du -sb --exclude=.git --exclude=vendor --exclude=node_modules "$APP_ROOT" | awk '{print $1}')"
BACKUP_FREE="$(df -Pk "$BACKUP_PARENT" | awk 'NR==2 {print $4 * 1024}')"
DATABASE_FREE="$(df -Pk "$DATA_DIR" | awk 'NR==2 {print $4 * 1024}')"
# Conservative headroom for dumps, sorting, the archive, extraction and clone.
awk -v free="$BACKUP_FREE" -v db="$DB_BYTES" -v app="$APP_BYTES" 'BEGIN {exit !(free > db*5 + app*3 + 1073741824)}' || stop "BACKUP_DISK_SPACE_INSUFFICIENT"
awk -v free="$DATABASE_FREE" -v db="$DB_BYTES" 'BEGIN {exit !(free > db*4 + 1073741824)}' || stop "DATABASE_DISK_SPACE_INSUFFICIENT"
echo "STORAGE_SPACE=OK"

STAGE="database_backup"
echo "STEP=$STAGE"
DUMP_FLAGS=(--single-transaction --quick --skip-lock-tables --skip-add-locks --skip-comments --skip-triggers --hex-blob --no-tablespaces --default-character-set=utf8mb4 --order-by-primary --skip-extended-insert --complete-insert)
DUMP_HELP="$(mysqldump --help)"
if [[ "$DUMP_HELP" == *set-gtid-purged* ]]; then DUMP_FLAGS+=(--set-gtid-purged=OFF); fi
if [[ "$DUMP_HELP" == *column-statistics* ]]; then DUMP_FLAGS+=(--column-statistics=0); fi
mysqldump --protocol=socket --socket="$DB_SOCKET" --user=root "${DUMP_FLAGS[@]}" "$DB_NAME" >"$BACKUP/database.sql"
[[ -s "$BACKUP/database.sql" ]] || stop "EMPTY_DATABASE_BACKUP"
echo "DATABASE_BACKUP=OK"

STAGE="isolated_database_restore"
echo "STEP=$STAGE"
TOKEN="$(openssl rand -hex 8)"
DRILL_DB="pbr_portal_drill_$TOKEN"
DRILL_USER="pbrpd_$TOKEN"
DRILL_PASSWORD="$(openssl rand -hex 32)"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$DRILL_DB" | "${ADMIN[@]}"
DB_CREATED=1
printf "CREATE USER '%s'@'localhost' IDENTIFIED BY '%s';\n" "$DRILL_USER" "$DRILL_PASSWORD" | "${ADMIN[@]}"
USER_CREATED=1
DRILL_GRANT="${DRILL_DB//_/\\_}"
printf 'GRANT ALL PRIVILEGES ON `%s`.* TO '\''%s'\''@'\''localhost'\'';\n' "$DRILL_GRANT" "$DRILL_USER" | "${ADMIN[@]}"
printf '[client]\nuser=%s\npassword=%s\nprotocol=socket\nsocket=%s\ndefault-character-set=utf8mb4\n' "$DRILL_USER" "$DRILL_PASSWORD" "$DB_SOCKET" >"$BACKUP/drill.cnf"
unset DRILL_PASSWORD
# This identity has access only to the temporary database, never the live one.
if mysql --defaults-file="$BACKUP/drill.cnf" --database="$DB_NAME" --execute='SELECT 1;' >/dev/null 2>&1; then
    stop "RESTORE_ACCOUNT_IS_NOT_ISOLATED"
fi
echo "RESTORE_ACCOUNT_ISOLATED=YES"
mysql --defaults-file="$BACKUP/drill.cnf" --database="$DRILL_DB" <"$BACKUP/database.sql"
echo "DATABASE_RESTORE=OK"
RESTORED_COUNT="$("${ADMIN[@]}" --database="$DRILL_DB" --execute='SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();')"
[[ "$RESTORED_COUNT" == "$TABLE_COUNT" ]] || stop "RESTORED_TABLE_COUNT_MISMATCH"
mysqldump --defaults-file="$BACKUP/drill.cnf" "${DUMP_FLAGS[@]}" "$DRILL_DB" >"$BACKUP/restore-check.sql"
# MySQL can re-emit an explicit column CHARACTER SET after restore. COLLATE
# already specifies that charset; normalize only this redundant DDL spelling.
# Keep schema order intact and compare data separately, with exact row bytes.
normalize_dump() {
    local input="$1" label="$2"
    awk '!/^INSERT INTO / {print}' "$input" \
        | sed -E '/^  `/s/ CHARACTER SET [A-Za-z0-9_]+ COLLATE / COLLATE /g' >"$BACKUP/$label.schema"
    awk '/^INSERT INTO / {print}' "$input" | LC_ALL=C sort -T "$BACKUP" >"$BACKUP/$label.rows"
}
normalize_dump "$BACKUP/database.sql" source
normalize_dump "$BACKUP/restore-check.sql" restore
if ! cmp -s -- "$BACKUP/source.schema" "$BACKUP/restore.schema" || ! cmp -s -- "$BACKUP/source.rows" "$BACKUP/restore.rows"; then
    diff -u -- "$BACKUP/source.schema" "$BACKUP/restore.schema" >"$BACKUP/database-restore-diff.private" || true
    diff -u -- "$BACKUP/source.rows" "$BACKUP/restore.rows" >>"$BACKUP/database-restore-diff.private" || true
    stop "DATABASE_ROUNDTRIP_MISMATCH"
fi
echo "DATABASE_ROUNDTRIP=OK"

STAGE="application_backup_and_restore"
echo "STEP=$STAGE"
# Do not follow public/storage or other links into an unexpected location.
tar --exclude='./.git' --exclude='./vendor' --exclude='./node_modules' \
    --exclude='./storage/logs' --exclude='./storage/framework/cache' \
    --exclude='./storage/framework/views' --exclude='./storage/framework/sessions' \
    -czf "$BACKUP/application.tar.gz" -C "$APP_ROOT" .
gzip -t "$BACKUP/application.tar.gz"
echo "APPLICATION_BACKUP=OK"
mkdir -m 700 -- "$BACKUP/restore-app"
tar -xzf "$BACKUP/application.tar.gz" -C "$BACKUP/restore-app"
for file in .env artisan composer.lock; do
    cmp -s -- "$APP_ROOT/$file" "$BACKUP/restore-app/$file" || stop "APPLICATION_RESTORE_MISMATCH"
done
# Verify every archived regular file, including local uploaded files.
tar --compare --gzip --file="$BACKUP/application.tar.gz" --directory="$BACKUP/restore-app" >"$BACKUP/archive-compare.log"
echo "APPLICATION_RESTORE_CHECK=OK"
(cd "$BACKUP" && sha256sum database.sql application.tar.gz >SHA256SUMS && sha256sum --check --status SHA256SUMS)
echo "BACKUP_CHECKSUMS=OK"

STAGE="final_source_check"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$EXPECTED_HEAD" && -z "$(git -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_CHANGED_DURING_BACKUP"
echo "LIVE_SOURCE_UNCHANGED=YES"
VERIFIED=1
