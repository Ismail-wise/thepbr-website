#!/usr/bin/env bash
# Code-only update of the already installed Partner Portal. Keeps the original
# Filament Content resources and all existing records. No migration or seeder.
set -Eeuo pipefail
umask 022

TARGET="${1:-}"
APP_ROOT="${2:-/var/www/thepbr-laravel}"
BASE="450e7e3f0d765117692f14384ddd662982fd2911"
REPOSITORY="https://github.com/Ismail-wise/thepbr-website.git"
BACKUP_PARENT="/root/thepbr-deploy-backups"
WEB_USER=www-data
STATE="" SOURCE="" FRESH_BACKUP=""
STAGE=prerequisites
MAINTENANCE=0 CODE_ATTEMPTED=0 COMPLETE=0

echo "PBR_FILAMENT_UPDATE=START"
stop() { echo "FILAMENT_UPDATE_STOP_REASON=$1"; exit 2; }
artisan() {
    (cd "$APP_ROOT" && runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" artisan "$@") >>"$STATE/private.log" 2>&1
}
isolated() {
    (cd "$SOURCE" && env -i PATH="$PATH" APP_ENV=testing APP_KEY="$TEST_KEY" \
        APP_MAINTENANCE_DRIVER=file DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
        CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
        PBR_AI_BASE_URL=http://127.0.0.1:9 PBR_AI_INTERNAL_SECRET=isolated-test-secret-never-production \
        PULSE_ENABLED=false TELESCOPE_ENABLED=false NIGHTWATCH_ENABLED=false "$@") >>"$STATE/private.log" 2>&1
}
rollback() {
    local ok=1
    echo "FILAMENT_CODE_ROLLBACK=START"
    artisan down --retry=30 --render=errors::503 || ok=0
    if [[ "$CODE_ATTEMPTED" == 1 ]]; then
        git -C "$APP_ROOT" checkout --quiet --detach "$BASE" >>"$STATE/private.log" 2>&1 || ok=0
    fi
    (cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1 || ok=0
    artisan config:clear || ok=0
    artisan route:clear || ok=0
    artisan view:clear || ok=0
    artisan config:cache || ok=0
    artisan view:cache || ok=0
    if [[ "$ok" == 1 ]] && artisan up; then
        MAINTENANCE=0
        echo "FILAMENT_CODE_ROLLBACK=OK"
        echo "ALL_PORTAL_RECORDS=RETAINED"
    else
        echo "FILAMENT_CODE_ROLLBACK=NEEDS_REVIEW"
        echo "WEBSITE_MAINTENANCE=ON"
    fi
}
finish() {
    local result=$?
    trap - EXIT ERR
    set +e
    if [[ "$COMPLETE" != 1 && "$MAINTENANCE" == 1 ]]; then rollback; fi
    if [[ -n "$STATE" ]]; then
        rm -rf -- "$SOURCE"
        echo "FILAMENT_RECEIPT_PATH=$STATE"
    fi
    if [[ "$result" == 0 && "$COMPLETE" == 1 ]]; then
        echo "LIVE_SOURCE_HEAD=$TARGET"
        echo "PBR_FILAMENT_UPDATE=COMPLETE"
    else
        echo "FILAMENT_UPDATE_FAILED_STAGE=$STAGE"
        echo "PBR_FILAMENT_UPDATE=INCOMPLETE"
        result=2
    fi
    exit "$result"
}
trap finish EXIT
trap 'echo "FILAMENT_UPDATE_STEP_FAILED=$STAGE"' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

[[ "$EUID" == 0 ]] || stop "RUN_WITH_SUDO_ON_PBR_SERVER"
[[ $# -ge 1 && $# -le 2 && "$TARGET" =~ ^[a-f0-9]{40}$ ]] || stop "EXPECTED_REVIEWED_COMMIT_SHA"
for executable in php composer git mysql mysqldump curl openssl tar gzip sha256sum cmp sed awk grep flock runuser realpath stat tee du df; do
    command -v "$executable" >/dev/null || stop "MISSING_${executable^^}"
done
PHP_BIN="$(command -v php)"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_sqlite") && extension_loaded("pdo_mysql") ? 0 : 2);' || stop "PHP_84_SQLITE_MYSQL_REQUIRED"
APP_ROOT="$(realpath -e -- "$APP_ROOT")"
[[ -f "$APP_ROOT/.env" && ! -L "$APP_ROOT/.env" && -f "$APP_ROOT/vendor/autoload.php" ]] || stop "REGULAR_APP_AND_ENV_REQUIRED"
[[ -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_NOT_CLEAN"
CURRENT="$(git -C "$APP_ROOT" rev-parse HEAD)"
if [[ "$CURRENT" == "$TARGET" ]]; then
    echo "ALREADY_AT_REVIEWED_VERSION=YES"
    COMPLETE=1
    exit 0
fi
[[ "$CURRENT" == "$BASE" ]] || stop "LIVE_SOURCE_CHANGED_RECHECK_REQUIRED"
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/storage" || stop "STORAGE_NOT_WRITABLE_BY_WEB_USER"
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/bootstrap/cache" || stop "BOOTSTRAP_CACHE_NOT_WRITABLE_BY_WEB_USER"
[[ ! -L "$BACKUP_PARENT" ]] || stop "BACKUP_PARENT_IS_SYMLINK"
mkdir -p -- "$BACKUP_PARENT"
chmod 700 -- "$BACKUP_PARENT"
exec 8>"$BACKUP_PARENT/.partner-portal-install.lock"
flock -n 8 || stop "ANOTHER_PORTAL_INSTALL_IS_RUNNING"
STATE="$BACKUP_PARENT/filament-release-$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 4)"
mkdir -m 700 -- "$STATE"
exec 2>>"$STATE/private.log"
cp -p -- "$APP_ROOT/.env" "$STATE/original.env"
VENDOR_BYTES="$(du -sb "$APP_ROOT/vendor" | awk '{print $1}')"
STAGING_FREE="$(df -Pk "$STATE" | awk 'NR==2 {print $4*1024}')"
awk -v free="$STAGING_FREE" -v vendor="$VENDOR_BYTES" 'BEGIN {exit !(free > vendor*2+1073741824)}' || stop "ISOLATED_STAGING_DISK_SPACE_INSUFFICIENT"

STAGE=fetch_reviewed_filament_update
echo "STEP=$STAGE"
SOURCE="$STATE/source"
git clone --quiet --no-checkout "$REPOSITORY" "$SOURCE" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" fetch --quiet --no-tags origin "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" merge-base --is-ancestor "$BASE" "$TARGET" || stop "TARGET_NOT_BASED_ON_INSTALLED_PORTAL"
# Only the reviewed admin integration, tests and deployment documentation may
# change. Public publishing resources, website, AI and PartnerDynamics stay exact.
while IFS= read -r path; do
    case "$path" in
        app/Filament/Resources/UserAccess/Tables/UserAccessTable.php|app/Filament/Resources/PortalAccessEvents/*|app/Filament/Widgets/PortalAccessWidget.php|\
        app/Http/Controllers/AccountAuthController.php|app/Http/Controllers/AccountController.php|app/Http/Controllers/PartnerPortalController.php|\
        app/Providers/Filament/AdminPanelProvider.php|app/Services/AccessAdministrationService.php|app/Services/Portal/StudentPortalAccess.php|\
        app/Services/Portal/PortalAccessAudit.php|app/Models/PortalAccessEvent.php|resources/views/portal/home.blade.php|\
        scripts/pbr-portal-release-check.php|scripts/pbr-filament-backup.sh|scripts/pbr-filament-update.sh|scripts/tests/pbr-portal-install-smoke.sh|\
        tests/*|docs/*) ;;
        *) stop "CHANGE_OUTSIDE_REVIEWED_ADMIN_SCOPE";;
    esac
done < <(git -C "$SOURCE" diff --name-only "$BASE" "$TARGET")
env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$SOURCE/scripts/pbr-portal-release-check.php" filament-inspect "$APP_ROOT" "$STATE/database-meta.json" "$STATE/cached-config.sha"
env -i PATH="$PATH" APP_CONFIG_CACHE="$STATE/uncached-config.php" "$PHP_BIN" -d display_errors=0 \
    "$SOURCE/scripts/pbr-portal-release-check.php" filament-inspect "$APP_ROOT" "$STATE/uncached-database-meta.json" "$STATE/uncached-config.sha"
cmp -s "$STATE/cached-config.sha" "$STATE/uncached-config.sha" || stop "CACHED_ENVIRONMENT_CONFIG_MISMATCH"
cmp -s "$STATE/database-meta.json" "$STATE/uncached-database-meta.json" || stop "CACHED_DATABASE_MISMATCH"
echo "PUBLIC_CONTENT_AI_AND_PARTNER_DYNAMICS_SOURCE=PRESERVED"

STAGE=isolated_filament_and_publishing_tests
echo "STEP=$STAGE"
cp -a -- "$APP_ROOT/vendor" "$SOURCE/vendor"
cp "$SOURCE/.env.example" "$SOURCE/.env"
TEST_KEY="base64:$(openssl rand -base64 32)"
mkdir -p "$SOURCE/storage/framework/cache/data" "$SOURCE/storage/framework/views" "$SOURCE/storage/framework/sessions" "$SOURCE/storage/logs"
if [[ ! -f "$SOURCE/vendor/bin/pest" ]]; then
    (cd "$SOURCE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --no-interaction --prefer-dist --no-progress) >>"$STATE/private.log" 2>&1
fi
(cd "$SOURCE" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1
isolated "$PHP_BIN" artisan view:cache
isolated "$PHP_BIN" vendor/bin/pest --ci --colors=never
echo "SERVER_ISOLATED_FILAMENT_AND_PUBLISHING_TESTS=OK"

STAGE=maintenance_and_verified_current_portal_backup
echo "STEP=$STAGE"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$BASE" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_CHANGED_DURING_TESTS"
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop "LIVE_ENV_CHANGED_DURING_TESTS"
MAINTENANCE=1
artisan down --retry=30 --render=errors::503
bash "$SOURCE/scripts/pbr-filament-backup.sh" "$APP_ROOT" | tee "$STATE/backup-output.txt"
FRESH_BACKUP="$(sed -n 's/^BACKUP_PATH=//p' "$STATE/backup-output.txt")"
grep -qx 'PBR_PORTAL_BACKUP=COMPLETE' "$STATE/backup-output.txt" || stop "CURRENT_PORTAL_RESTORE_DRILL_INCOMPLETE"
[[ "$FRESH_BACKUP" == "$BACKUP_PARENT"/pre-filament-admin-* && -f "$FRESH_BACKUP/restore-verified.txt" ]] || stop "CURRENT_PORTAL_BACKUP_REQUIRED"
grep -qx "SOURCE_HEAD=$BASE" "$FRESH_BACKUP/restore-verified.txt" || stop "CURRENT_PORTAL_BACKUP_SOURCE_MISMATCH"
cmp -s "$STATE/database-meta.json" "$FRESH_BACKUP/database-meta.json" || stop "FRESH_BACKUP_DATABASE_MISMATCH"
(cd "$FRESH_BACKUP" && sha256sum --check --status SHA256SUMS)
echo "FRESH_VERIFIED_BACKUP=$FRESH_BACKUP"
DB_NAME="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["database"];' "$STATE/database-meta.json")"
DB_SOCKET="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["socket"];' "$STATE/database-meta.json")"
ADMIN=(mysql --protocol=socket --socket="$DB_SOCKET" --user=root --batch --skip-column-names)
DUMP_FLAGS=(--single-transaction --quick --skip-lock-tables --skip-add-locks --skip-comments --skip-triggers --hex-blob --no-tablespaces --default-character-set=utf8mb4 --order-by-primary)
DUMP_HELP="$(mysqldump --help)"
if [[ "$DUMP_HELP" == *set-gtid-purged* ]]; then DUMP_FLAGS+=(--set-gtid-purged=OFF); fi
if [[ "$DUMP_HELP" == *column-statistics* ]]; then DUMP_FLAGS+=(--column-statistics=0); fi
"${ADMIN[@]}" --database="$DB_NAME" --execute="SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name NOT IN ('sessions','cache','cache_locks','jobs','job_batches','failed_jobs') ORDER BY table_name;" >"$STATE/data-tables.txt"
mapfile -t DATA_TABLES <"$STATE/data-tables.txt"
[[ ${#DATA_TABLES[@]} -gt 0 ]] || stop "DATABASE_TABLES_MISSING"
mysqldump --protocol=socket --socket="$DB_SOCKET" --user=root "${DUMP_FLAGS[@]}" "$DB_NAME" "${DATA_TABLES[@]}" >"$STATE/records-before.sql"

STAGE=install_original_filament_with_portal_features
echo "STEP=$STAGE"
git -C "$APP_ROOT" fetch --quiet --no-tags "$SOURCE" "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$APP_ROOT" branch "backup/pre-filament-$(basename "$STATE")" "$BASE"
CODE_ATTEMPTED=1
git -C "$APP_ROOT" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
(cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1
artisan config:clear
artisan route:clear
artisan config:cache
artisan view:cache
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"
mysqldump --protocol=socket --socket="$DB_SOCKET" --user=root "${DUMP_FLAGS[@]}" "$DB_NAME" "${DATA_TABLES[@]}" >"$STATE/records-after.sql"
cmp -s "$STATE/records-before.sql" "$STATE/records-after.sql" || stop "EXISTING_CONTENT_OR_PORTAL_RECORDS_CHANGED"
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop "LIVE_CONFIGURATION_CHANGED"
echo "EXISTING_CONTENT_ACCOUNTS_CHATS_AND_ACCESS=PRESERVED"
echo "SCHEMA_AND_ENVIRONMENT=UNCHANGED"

STAGE=public_website_and_admin_checks
echo "STEP=$STAGE"
artisan up
for path in / /login /articles /videos /classes /admin/login; do
    code="$(curl -sS -L --max-redirs 3 --connect-timeout 5 --max-time 20 -H 'Cache-Control: no-cache' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?filament_release=${TARGET}")"
    [[ "$code" == 200 ]] || stop "PUBLIC_OR_ADMIN_HTTPS_CHECK_FAILED"
done
for path in /portal /admin; do
    code="$(curl -sS --connect-timeout 5 --max-time 20 -H 'Accept: text/html' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?filament_release=${TARGET}")"
    [[ "$code" == 302 || "$code" == 303 ]] || stop "ANONYMOUS_AUTH_CHECK_FAILED"
done
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$TARGET" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "FINAL_SOURCE_NOT_CLEAN"
printf 'TARGET=%s\nBASE=%s\nBACKUP=%s\nCOMPLETED_UTC=%s\n' "$TARGET" "$BASE" "$FRESH_BACKUP" "$(date -u +%FT%TZ)" >"$STATE/update-complete.txt"
echo "PUBLIC_WEBSITE_AND_ADMIN_HTTPS=OK"
echo "SIGNED_IN_FILAMENT_AND_PUBLISHING_UAT=PENDING"
MAINTENANCE=0
COMPLETE=1
