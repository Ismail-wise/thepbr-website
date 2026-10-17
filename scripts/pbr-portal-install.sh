#!/usr/bin/env bash
# Explicitly installs the reviewed Student Partner Portal. Never runs a broad
# migration, a seeder, migrate:fresh or a destructive database rollback.
set -Eeuo pipefail
umask 022

TARGET="${1:-}"
VERIFIED_BACKUP="${2:-}"
APP_ROOT="${3:-/var/www/thepbr-laravel}"
BASE="b71122dee5f0585807223b94b33211e1399df4dd"
AI_BASE="cd16ba3b543fdbc66863f1f71128f4e7f02bb18c"
REPOSITORY="https://github.com/Ismail-wise/thepbr-website.git"
MIGRATION="database/migrations/2026_10_17_060000_create_partner_portal_tables.php"
BACKUP_PARENT="/root/thepbr-deploy-backups"
WEB_USER="www-data"
STATE=""
SOURCE=""
STAGE="prerequisites"
MAINTENANCE=0
CODE_ATTEMPTED=0
COMPLETE=0
DRILL_CREATED=0
USER_CREATED=0
ADMIN=()
DRILL_DB=""
DRILL_USER=""
ROLLBACK_OK=0

echo "PBR_PORTAL_INSTALL=START"
stop() { echo "INSTALL_STOP_REASON=$1"; exit 2; }
artisan() {
    (cd "$APP_ROOT" && runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" artisan "$@") >>"$STATE/private.log" 2>&1
}
isolated() {
    (cd "$SOURCE" && env -i PATH="$PATH" APP_ENV=testing APP_KEY="$TEST_KEY" \
        APP_MAINTENANCE_DRIVER=file DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
        CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
        PBR_AI_BASE_URL=http://127.0.0.1:9 PBR_AI_INTERNAL_SECRET=isolated-test-secret-never-production \
        PULSE_ENABLED=false TELESCOPE_ENABLED=false NIGHTWATCH_ENABLED=false \
        "$@") >>"$STATE/private.log" 2>&1
}
release_check() {
    env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$SOURCE/scripts/pbr-portal-release-check.php" "$@"
}
drop_drill() {
    local ok=1
    if [[ "$USER_CREATED" == 1 ]]; then
        if printf "DROP USER '%s'@'localhost';\n" "$DRILL_USER" | "${ADMIN[@]}" >>"$STATE/private.log" 2>&1; then USER_CREATED=0; else ok=0; fi
    fi
    if [[ "$DRILL_CREATED" == 1 && "$DRILL_DB" =~ ^pbr_portal_release_[a-f0-9]{16}$ && "$DRILL_DB" != "${DB_NAME:-}" ]]; then
        if printf 'DROP DATABASE `%s`;\n' "$DRILL_DB" | "${ADMIN[@]}" >>"$STATE/private.log" 2>&1; then DRILL_CREATED=0; else ok=0; fi
    fi
    [[ "$ok" == 1 ]]
}
rollback() {
    local ok=1
    echo "PORTAL_ROLLBACK=START"
    # Close requests before restoring code if the final public probe failed.
    artisan down --retry=30 --render=errors::503 || ok=0
    if [[ "$CODE_ATTEMPTED" == 1 ]]; then
        git -C "$APP_ROOT" checkout --detach "$BASE" >>"$STATE/private.log" 2>&1 || ok=0
    fi
    cp -p -- "$STATE/original.env" "$APP_ROOT/.env" || ok=0
    (cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1 || ok=0
    artisan config:clear || ok=0
    artisan route:clear || ok=0
    artisan view:clear || ok=0
    artisan config:cache || ok=0
    artisan view:cache || ok=0
    if [[ "$ok" == 1 ]] && artisan up; then
        MAINTENANCE=0
        ROLLBACK_OK=1
        echo "PORTAL_ROLLBACK=OK"
        echo "NEW_PORTAL_RECORDS=RETAINED"
    else
        echo "PORTAL_ROLLBACK=NEEDS_REVIEW"
        echo "WEBSITE_MAINTENANCE=ON"
    fi
}
finish() {
    local result=$?
    trap - EXIT ERR
    set +e
    if [[ "$COMPLETE" != 1 && "$MAINTENANCE" == 1 ]]; then rollback; fi
    if ! drop_drill; then result=2; echo "TEMP_DATABASE_CLEANUP=NEEDS_REVIEW"; else echo "TEMP_DATABASE_CLEANUP=OK"; fi
    if [[ -n "$STATE" ]]; then
        rm -f -- "$STATE/drill.cnf"
        rm -rf -- "$SOURCE"
        echo "RELEASE_RECEIPT_PATH=$STATE"
    fi
    if [[ "$result" == 0 && "$COMPLETE" == 1 ]]; then
        echo "LIVE_PORTAL_SWITCHED=YES"
        echo "LIVE_SOURCE_HEAD=$TARGET"
        echo "PBR_PORTAL_INSTALL=COMPLETE"
    else
        echo "INSTALL_FAILED_STAGE=$STAGE"
        if [[ "$ROLLBACK_OK" == 1 ]]; then echo "LIVE_PORTAL_SWITCHED=NO"; fi
        echo "PBR_PORTAL_INSTALL=INCOMPLETE"
        result=2
    fi
    exit "$result"
}
trap finish EXIT
trap 'echo "INSTALL_STEP_FAILED=$STAGE"' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

[[ "$EUID" == 0 ]] || stop "RUN_WITH_SUDO_ON_PBR_SERVER"
[[ $# -ge 2 && $# -le 3 && "$TARGET" =~ ^[a-f0-9]{40}$ ]] || stop "EXPECTED_TARGET_SHA_AND_VERIFIED_BACKUP_PATH"
for executable in php composer git mysql mysqldump curl openssl tar gzip sha256sum cmp sed awk grep flock runuser realpath stat tee install basename systemctl du df; do
    command -v "$executable" >/dev/null || stop "MISSING_${executable^^}"
done
PHP_BIN="$(command -v php)"
NODE_BIN="$(command -v node || true)"
if [[ -z "$NODE_BIN" && -x /usr/local/bin/pbr-node ]]; then NODE_BIN=/usr/local/bin/pbr-node; fi
[[ -n "$NODE_BIN" ]] || stop "NODE_RUNTIME_MISSING"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_sqlite") && extension_loaded("pdo_mysql") ? 0 : 2);' || stop "PHP_84_SQLITE_MYSQL_REQUIRED"
id "$WEB_USER" >/dev/null || stop "WEB_PROCESS_USER_MISSING"
APP_ROOT="$(realpath -e -- "$APP_ROOT")"
VERIFIED_BACKUP="$(realpath -e -- "$VERIFIED_BACKUP")"
[[ "$VERIFIED_BACKUP" == "$BACKUP_PARENT"/pre-partner-portal-* && -f "$VERIFIED_BACKUP/restore-verified.txt" ]] || stop "VERIFIED_BACKUP_REQUIRED"
grep -qx "SOURCE_HEAD=$BASE" "$VERIFIED_BACKUP/restore-verified.txt" || stop "BACKUP_SOURCE_MISMATCH"
(cd "$VERIFIED_BACKUP" && sha256sum --check --status SHA256SUMS) || stop "BACKUP_CHECKSUM_MISMATCH"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$BASE" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_CHANGED_RECHECK_REQUIRED"
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/storage" || stop "STORAGE_NOT_WRITABLE_BY_WEB_USER"
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/bootstrap/cache" || stop "BOOTSTRAP_CACHE_NOT_WRITABLE_BY_WEB_USER"
exec 8>"$BACKUP_PARENT/.partner-portal-install.lock"
flock -n 8 || stop "ANOTHER_PORTAL_INSTALL_IS_RUNNING"
STATE="$BACKUP_PARENT/portal-release-$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 4)"
mkdir -m 700 -- "$STATE"
exec 2>>"$STATE/private.log"
cp -p -- "$APP_ROOT/.env" "$STATE/original.env"
VENDOR_BYTES="$(du -sb "$APP_ROOT/vendor" | awk '{print $1}')"
STAGING_FREE="$(df -Pk "$STATE" | awk 'NR==2 {print $4*1024}')"
awk -v free="$STAGING_FREE" -v vendor="$VENDOR_BYTES" 'BEGIN {exit !(free > vendor*2+1073741824)}' || stop "ISOLATED_STAGING_DISK_SPACE_INSUFFICIENT"

STAGE="fetch_reviewed_portal"
echo "STEP=$STAGE"
SOURCE="$STATE/source"
git clone --quiet --no-checkout "$REPOSITORY" "$SOURCE" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" fetch --quiet --no-tags origin "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" merge-base --is-ancestor "$BASE" "$TARGET" || stop "TARGET_NOT_BASED_ON_LIVE_SOURCE"
git -C "$SOURCE" diff --quiet "$BASE" "$TARGET" -- composer.json composer.lock package.json package-lock.json resources/css resources/js vite.config.js || stop "DEPENDENCY_OR_BUNDLED_ASSET_CHANGE_REQUIRES_REVIEW"
git -C "$SOURCE" diff --quiet "$BASE" "$TARGET" -- \
    resources/views/home.blade.php resources/views/about.blade.php resources/views/articles \
    resources/views/classes resources/views/videos resources/views/legal resources/views/layouts/site.blade.php \
    resources/views/partials resources/views/partner-dynamics resources/views/workspaces/partner-dynamics.blade.php \
    resources/views/workspaces/partner-dynamics-profile.blade.php 'app/Http/Controllers/*PartnerDynamics*' \
    app/Services/PartnerDynamics 'config/partner_dynamics*' 'public/css/partner-dynamics*' public/images/partner-dynamics || stop "PROTECTED_PUBLIC_OR_PARTNER_DYNAMICS_CHANGE"
[[ "$(git -C "$SOURCE" diff --name-only "$BASE" "$TARGET" -- database/migrations)" == "$MIGRATION" ]] || stop "UNEXPECTED_MIGRATION_CHANGE"
[[ "$(sha256sum "$SOURCE/scripts/pbr-portal-backup.sh" | awk '{print $1}')" == 15766d8d1eb23d79495974bf2dfd96b73decc17b6b2f6367e17f5ee12909b87b ]] || stop "BACKUP_HELPER_CHANGED_REQUIRES_REVIEW"
release_check inspect "$APP_ROOT" "$VERIFIED_BACKUP/database-meta.json" "$STATE/cached-config.sha"
env -i PATH="$PATH" APP_CONFIG_CACHE="$STATE/uncached-config.php" "$PHP_BIN" -d display_errors=0 \
    "$SOURCE/scripts/pbr-portal-release-check.php" inspect "$APP_ROOT" "$VERIFIED_BACKUP/database-meta.json" "$STATE/uncached-config.sha"
cmp -s "$STATE/cached-config.sha" "$STATE/uncached-config.sha" || stop "CACHED_ENVIRONMENT_CONFIG_MISMATCH"
echo "PUBLIC_AND_PARTNER_DYNAMICS_SOURCE=PRESERVED"

STAGE="isolated_server_tests"
echo "STEP=$STAGE"
cp -a -- "$APP_ROOT/vendor" "$SOURCE/vendor"
TEST_KEY="base64:$(openssl rand -base64 32)"
cp "$SOURCE/.env.example" "$SOURCE/.env"
mkdir -p "$SOURCE/storage/framework/cache/data" "$SOURCE/storage/framework/views" "$SOURCE/storage/framework/sessions" "$SOURCE/storage/logs"
if [[ ! -f "$SOURCE/vendor/bin/pest" ]]; then
    (cd "$SOURCE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --no-interaction --prefer-dist --no-progress) >>"$STATE/private.log" 2>&1
fi
(cd "$SOURCE" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --optimize --no-interaction) >>"$STATE/private.log" 2>&1
isolated "$PHP_BIN" artisan view:cache
isolated "$PHP_BIN" vendor/bin/pest --ci --colors=never
"$NODE_BIN" --check "$SOURCE/public/js/partner-portal.js" >>"$STATE/private.log" 2>&1
echo "SERVER_ISOLATED_TESTS=OK"

STAGE="original_partner_ai_generation"
echo "STEP=$STAGE"
AI_ROOT=/opt/pbr-partner-ai
if [[ ! -d "$AI_ROOT/.git" ]]; then AI_ROOT="$(systemctl show pbr-ai-advisor.service --property=WorkingDirectory --value)"; fi
[[ "$(git -C "$AI_ROOT" rev-parse HEAD)" == "$AI_BASE" ]] || stop "ORIGINAL_PARTNER_AI_SOURCE_MISMATCH"
release_check ai "$APP_ROOT" "$SOURCE" "$STATE/ai-answer.txt"

STAGE="maintenance_and_fresh_verified_backup"
echo "STEP=$STAGE"
release_check inspect "$APP_ROOT" "$VERIFIED_BACKUP/database-meta.json"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$BASE" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_CHANGED_DURING_TESTS"
cmp -s -- "$APP_ROOT/.env" "$STATE/original.env" || stop "LIVE_ENV_CHANGED_DURING_TESTS"
MAINTENANCE=1
artisan down --retry=30 --render=errors::503
bash "$SOURCE/scripts/pbr-portal-backup.sh" "$APP_ROOT" | tee "$STATE/fresh-backup-output.txt"
FRESH_BACKUP="$(sed -n 's/^BACKUP_PATH=//p' "$STATE/fresh-backup-output.txt")"
grep -qx 'PBR_PORTAL_BACKUP=COMPLETE' "$STATE/fresh-backup-output.txt" || stop "FRESH_RESTORE_DRILL_INCOMPLETE"
[[ "$FRESH_BACKUP" == "$BACKUP_PARENT"/pre-partner-portal-* && -f "$FRESH_BACKUP/restore-verified.txt" ]] || stop "FRESH_BACKUP_PATH_INVALID"
(cd "$FRESH_BACKUP" && sha256sum --check --status SHA256SUMS)
echo "FRESH_VERIFIED_BACKUP=$FRESH_BACKUP"
DB_NAME="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["database"];' "$FRESH_BACKUP/database-meta.json")"
DB_SOCKET="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["socket"];' "$FRESH_BACKUP/database-meta.json")"
ADMIN=(mysql --protocol=socket --socket="$DB_SOCKET" --user=root --batch --skip-column-names)
DUMP_FLAGS=(--single-transaction --quick --skip-lock-tables --skip-add-locks --skip-comments --skip-triggers --hex-blob --no-tablespaces --default-character-set=utf8mb4 --order-by-primary)
DUMP_HELP="$(mysqldump --help)"
if [[ "$DUMP_HELP" == *set-gtid-purged* ]]; then DUMP_FLAGS+=(--set-gtid-purged=OFF); fi
if [[ "$DUMP_HELP" == *column-statistics* ]]; then DUMP_FLAGS+=(--column-statistics=0); fi
mapfile -t ORIGINAL_TABLES < <("${ADMIN[@]}" --database="$DB_NAME" --execute="SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name NOT IN ('migrations','sessions','cache','cache_locks','jobs','job_batches','failed_jobs') ORDER BY table_name;")
[[ ${#ORIGINAL_TABLES[@]} -gt 0 ]] || stop "ORIGINAL_TABLE_LIST_EMPTY"
mysqldump --protocol=socket --socket="$DB_SOCKET" --user=root "${DUMP_FLAGS[@]}" "$DB_NAME" "${ORIGINAL_TABLES[@]}" >"$STATE/original-records-before.sql"

STAGE="isolated_mysql_migration"
echo "STEP=$STAGE"
TOKEN="$(openssl rand -hex 8)"
DRILL_DB="pbr_portal_release_$TOKEN"
DRILL_USER="pbrpr_$TOKEN"
DRILL_PASSWORD="$(openssl rand -hex 32)"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$DRILL_DB" | "${ADMIN[@]}"
DRILL_CREATED=1
printf "CREATE USER '%s'@'localhost' IDENTIFIED BY '%s';\n" "$DRILL_USER" "$DRILL_PASSWORD" | "${ADMIN[@]}"
USER_CREATED=1
DRILL_GRANT="${DRILL_DB//_/\\_}"
printf 'GRANT ALL PRIVILEGES ON `%s`.* TO '\''%s'\''@'\''localhost'\'';\n' "$DRILL_GRANT" "$DRILL_USER" | "${ADMIN[@]}"
(umask 077; printf '[client]\nuser=%s\npassword=%s\nprotocol=socket\nsocket=%s\ndefault-character-set=utf8mb4\n' "$DRILL_USER" "$DRILL_PASSWORD" "$DB_SOCKET" >"$STATE/drill.cnf")
if mysql --defaults-file="$STATE/drill.cnf" --database="$DB_NAME" --execute='SELECT 1;' >/dev/null 2>&1; then stop "REHEARSAL_ACCOUNT_NOT_ISOLATED"; fi
mysql --defaults-file="$STATE/drill.cnf" --database="$DRILL_DB" <"$FRESH_BACKUP/database.sql"
mysqldump --defaults-file="$STATE/drill.cnf" "${DUMP_FLAGS[@]}" "$DRILL_DB" "${ORIGINAL_TABLES[@]}" >"$STATE/rehearsal-before.sql"
(umask 077; cat >"$SOURCE/.env" <<EOF
APP_ENV=testing
APP_KEY=$TEST_KEY
APP_DEBUG=false
APP_URL=https://thepbr.io
DB_CONNECTION=mysql
DB_HOST=localhost
DB_SOCKET=$DB_SOCKET
DB_DATABASE=$DRILL_DB
DB_USERNAME=$DRILL_USER
DB_PASSWORD=$DRILL_PASSWORD
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
PBR_AI_BASE_URL=http://127.0.0.1:9
PBR_PARTNER_PORTAL_ENABLED=false
EOF
)
unset DRILL_PASSWORD
rm -f -- "$SOURCE/bootstrap/cache/config.php"
(
    cd "$SOURCE"
    env -i PATH="$PATH" "$PHP_BIN" artisan migrate --database=mysql --path="$MIGRATION" --force
) >>"$STATE/private.log" 2>&1
mysqldump --defaults-file="$STATE/drill.cnf" "${DUMP_FLAGS[@]}" "$DRILL_DB" "${ORIGINAL_TABLES[@]}" >"$STATE/rehearsal-after.sql"
cmp -s -- "$STATE/rehearsal-before.sql" "$STATE/rehearsal-after.sql" || stop "REHEARSAL_CHANGED_EXISTING_RECORDS"
[[ "$("${ADMIN[@]}" --database="$DRILL_DB" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('portal_conversations','portal_messages','portal_saved_items','portal_access_events');")" == 4 ]] || stop "REHEARSAL_PORTAL_SCHEMA_INCOMPLETE"
drop_drill || stop "REHEARSAL_CLEANUP_FAILED"
rm -f -- "$STATE/drill.cnf"
echo "ISOLATED_MYSQL_MIGRATION=OK"
echo "REHEARSAL_EXISTING_RECORDS=PRESERVED"

STAGE="install_portal_code_and_additive_schema"
echo "STEP=$STAGE"
git -C "$APP_ROOT" fetch --quiet --no-tags "$SOURCE" "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$APP_ROOT" branch "backup/pre-partner-portal-$(basename "$STATE")" "$BASE"
CODE_ATTEMPTED=1
git -C "$APP_ROOT" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
(cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1
artisan config:clear
artisan route:clear
artisan migrate --path="$MIGRATION" --force
release_check enable "$APP_ROOT"
artisan config:cache
artisan view:cache
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"
mysqldump --protocol=socket --socket="$DB_SOCKET" --user=root "${DUMP_FLAGS[@]}" "$DB_NAME" "${ORIGINAL_TABLES[@]}" >"$STATE/original-records-after.sql"
cmp -s -- "$STATE/original-records-before.sql" "$STATE/original-records-after.sql" || stop "ORIGINAL_PRODUCTION_RECORDS_CHANGED"
echo "EXISTING_ACCOUNTS_ACCESS_AND_DATA=PRESERVED"

STAGE="public_website_and_portal_checks"
echo "STEP=$STAGE"
artisan up
# Keep rollback armed until the actual public HTTPS probes succeed.
for path in / /login /articles /classes; do
    code="$(curl -sS -L --max-redirs 3 --connect-timeout 5 --max-time 20 -H 'Cache-Control: no-cache' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?portal_release=${TARGET}")"
    [[ "$code" == 200 ]] || stop "PUBLIC_HTTPS_CHECK_FAILED"
done
code="$(curl -sS --connect-timeout 5 --max-time 20 -H 'Accept: text/html' -o /dev/null -w '%{http_code}' "https://thepbr.io/portal?portal_release=${TARGET}")"
[[ "$code" == 302 || "$code" == 303 ]] || stop "ANONYMOUS_PORTAL_AUTH_CHECK_FAILED"
curl -fsS --connect-timeout 5 --max-time 20 "https://thepbr.io/css/partner-portal.css?portal_release=${TARGET}" -o "$STATE/public-portal.css"
cmp -s -- "$STATE/public-portal.css" "$APP_ROOT/public/css/partner-portal.css" || stop "PUBLIC_PORTAL_ASSET_MISMATCH"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$TARGET" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "FINAL_LIVE_SOURCE_NOT_CLEAN"
printf 'TARGET=%s\nBASE=%s\nBACKUP=%s\nCOMPLETED_UTC=%s\n' "$TARGET" "$BASE" "$FRESH_BACKUP" "$(date -u +%FT%TZ)" >"$STATE/install-complete.txt"
echo "PUBLIC_HTTPS_AND_ASSETS=OK"
echo "ANONYMOUS_PORTAL_REQUIRES_LOGIN=YES"
echo "SIGNED_IN_USER_WORKFLOW_UAT=PENDING"
MAINTENANCE=0
COMPLETE=1
