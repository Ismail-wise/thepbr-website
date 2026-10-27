#!/usr/bin/env bash
# Owner-private Business Plan: drafts, confirmed snapshots and reviewed Knowledge.
# Two additive tables, isolated MySQL rehearsal, no reset or database rollback.
set -Eeuo pipefail
umask 022

TARGET="${1:-}"
APP_ROOT="${2:-/var/www/thepbr-laravel}"
AI_DIR="${3:-/opt/pbr-partner-ai}"
AI_EXPECTED="902dbbe52c7ac38924ba18a36101ab0a7b701ba0"
BASE="369a1ec3bb2e947cfc0c18482b23ff96cf78fbcd"
MIGRATION="database/migrations/2026_10_27_060000_create_workspace_business_plans.php"
REPOSITORY="https://github.com/Ismail-wise/thepbr-website.git"
BACKUP_PARENT="/root/thepbr-deploy-backups"
WEB_USER=www-data
STATE="" SOURCE="" FRESH_BACKUP=""
DRILL_DB="" DRILL_USER="" DRILL_CREATED=0 USER_CREATED=0
ADMIN=()
STAGE=prerequisites
MAINTENANCE=0 CODE_ATTEMPTED=0 COMPLETE=0

echo "PBR_BUSINESS_PLAN_UPDATE=START"
stop() { echo "BUSINESS_PLAN_UPDATE_STOP_REASON=$1"; exit 2; }
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
private_digest() {
    [[ -d "$APP_ROOT/storage/app/pbr-private" && ! -L "$APP_ROOT/storage/app/pbr-private" ]] || return 1
    [[ -z "$(find "$APP_ROOT/storage/app/pbr-private" -type l -print -quit)" ]] || return 1
    (cd "$APP_ROOT/storage/app/pbr-private" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum)
}
files_and_ai_preserved() {
    cmp -s "$APP_ROOT/.env" "$STATE/original.env" &&
    [[ "$(git -C "$AI_DIR" rev-parse HEAD)" == "$AI_EXPECTED" && -z "$(git --no-optional-locks -C "$AI_DIR" status --porcelain)" ]] &&
    private_digest >"$STATE/private-files-current.sha256" &&
    cmp -s "$STATE/private-files-before.sha256" "$STATE/private-files-current.sha256"
}
drop_drill() {
    local ok=1
    if [[ "$USER_CREATED" == 1 ]]; then
        printf "DROP USER '%s'@'localhost';\n" "$DRILL_USER" | "${ADMIN[@]}" >>"$STATE/private.log" 2>&1 || ok=0
        [[ "$ok" != 1 ]] || USER_CREATED=0
    fi
    if [[ "$DRILL_CREATED" == 1 && "$DRILL_DB" =~ ^pbr_plan_release_[a-f0-9]{16}$ && "$DRILL_DB" != "${DB_NAME:-}" ]]; then
        printf 'DROP DATABASE `%s`;\n' "$DRILL_DB" | "${ADMIN[@]}" >>"$STATE/private.log" 2>&1 || ok=0
        [[ "$ok" != 1 ]] || DRILL_CREATED=0
    fi
    [[ "$ok" == 1 ]]
}
rollback() {
    local ok=1
    echo "BUSINESS_PLAN_CODE_ROLLBACK=START"
    artisan down --retry=30 --render=errors::503 || ok=0
    artisan filament:clear-cached-components || ok=0
    if [[ "$CODE_ATTEMPTED" == 1 ]]; then
        # Never reopen the broader old permission model if new business data
        # arrived during cutover. Preserve it and hold the new code closed.
        if ! env -i PATH="$PATH" "$PHP_BIN" "$SOURCE/scripts/pbr-business-plan-release-check.php" verify "$APP_ROOT" "$STATE/projection.json" "$STATE/records-before.json" >>"$STATE/private.log" 2>&1 || ! files_and_ai_preserved; then
            echo "BUSINESS_PLAN_CODE_ROLLBACK=NEEDS_REVIEW"
            echo "CUTOVER_RECORDS_CHANGED=REVIEW_REQUIRED"
            echo "WEBSITE_MAINTENANCE=ON"
            return 1
        fi
    fi
    if [[ "$CODE_ATTEMPTED" == 1 ]]; then
        git -C "$APP_ROOT" checkout --quiet --detach "$BASE" >>"$STATE/private.log" 2>&1 || ok=0
    fi
    (cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1 || ok=0
    artisan config:clear || ok=0
    artisan route:clear || ok=0
    artisan view:clear || ok=0
    artisan config:cache || ok=0
    artisan filament:cache-components || ok=0
    artisan view:cache || ok=0
    if [[ "$ok" == 1 ]] && artisan up; then
        MAINTENANCE=0
        echo "BUSINESS_PLAN_CODE_ROLLBACK=OK"
        echo "ALL_PORTAL_RECORDS=RETAINED"
    else
        echo "BUSINESS_PLAN_CODE_ROLLBACK=NEEDS_REVIEW"
        echo "WEBSITE_MAINTENANCE=ON"
    fi
}
finish() {
    local result=$?
    trap - EXIT ERR
    set +e
    if [[ "$COMPLETE" != 1 && "$MAINTENANCE" == 1 ]]; then rollback; fi
    if [[ -n "$STATE" ]]; then
        if ! drop_drill; then echo "ISOLATED_DATABASE_CLEANUP=NEEDS_REVIEW"; result=2; COMPLETE=0; fi
        rm -f -- "$STATE/drill.cnf" "$STATE/maintenance.cookies"
        rm -rf -- "$SOURCE"
        echo "BUSINESS_PLAN_RECEIPT_PATH=$STATE"
    fi
    if [[ "$result" == 0 && "$COMPLETE" == 1 ]]; then
        echo "LIVE_SOURCE_HEAD=$TARGET"
        echo "AI_SOURCE_HEAD=$AI_EXPECTED"
        echo "PBR_BUSINESS_PLAN_UPDATE=COMPLETE"
    else
        echo "BUSINESS_PLAN_UPDATE_FAILED_STAGE=$STAGE"
        echo "PBR_BUSINESS_PLAN_UPDATE=INCOMPLETE"
        result=2
    fi
    exit "$result"
}
trap finish EXIT
trap 'echo "BUSINESS_PLAN_UPDATE_STEP_FAILED=$STAGE"' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

[[ "$EUID" == 0 ]] || stop "RUN_WITH_SUDO_ON_PBR_SERVER"
[[ $# -ge 1 && $# -le 3 && "$TARGET" =~ ^[a-f0-9]{40}$ ]] || stop "EXPECTED_REVIEWED_COMMIT_SHA"
for executable in php composer git mysql mysqldump curl openssl tar gzip sha256sum cmp sed awk grep flock runuser realpath stat tee du df find sort xargs; do
    command -v "$executable" >/dev/null || stop "MISSING_${executable^^}"
done
PHP_BIN="$(command -v php)"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_sqlite") && extension_loaded("pdo_mysql") ? 0 : 2);' || stop "PHP_84_SQLITE_MYSQL_REQUIRED"
APP_ROOT="$(realpath -e -- "$APP_ROOT")"
AI_DIR="$(realpath -e -- "$AI_DIR")"
[[ -d "$AI_DIR/.git" && "$(git -C "$AI_DIR" rev-parse HEAD)" == "$AI_EXPECTED" && -z "$(git --no-optional-locks -C "$AI_DIR" status --porcelain)" ]] || stop "ORIGINAL_AI_SOURCE_CHANGED_RECHECK_REQUIRED"
[[ -f "$APP_ROOT/.env" && ! -L "$APP_ROOT/.env" && -f "$APP_ROOT/vendor/autoload.php" ]] || stop "REGULAR_APP_AND_ENV_REQUIRED"
[[ -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_NOT_CLEAN"
CURRENT="$(git -C "$APP_ROOT" rev-parse HEAD)"
[[ "$CURRENT" == "$BASE" || "$CURRENT" == "$TARGET" ]] || stop "LIVE_SOURCE_CHANGED_RECHECK_REQUIRED"
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/storage" || stop "STORAGE_NOT_WRITABLE_BY_WEB_USER"
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/bootstrap/cache" || stop "BOOTSTRAP_CACHE_NOT_WRITABLE_BY_WEB_USER"
[[ ! -L "$BACKUP_PARENT" ]] || stop "BACKUP_PARENT_IS_SYMLINK"
mkdir -p -- "$BACKUP_PARENT"
chmod 700 -- "$BACKUP_PARENT"
exec 8>"$BACKUP_PARENT/.partner-portal-install.lock"
flock -n 8 || stop "ANOTHER_PORTAL_INSTALL_IS_RUNNING"
STATE="$BACKUP_PARENT/business-plan-release-$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 4)"
mkdir -m 700 -- "$STATE"
exec 2>>"$STATE/private.log"
cp -p -- "$APP_ROOT/.env" "$STATE/original.env"
if [[ "$CURRENT" == "$TARGET" ]]; then
    STAGE=verify_already_installed_version
    env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" \
        filament-inspect "$APP_ROOT" "$STATE/database-meta.json" "$STATE/config.sha"
    runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"
    runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-business-plan-release-check.php" render "$APP_ROOT"
    env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-business-plan-release-check.php" inspect "$APP_ROOT"
    echo "ALREADY_AT_REVIEWED_VERSION=YES"
    COMPLETE=1
    exit 0
fi
VENDOR_BYTES="$(du -sb "$APP_ROOT/vendor" | awk '{print $1}')"
STAGING_FREE="$(df -Pk "$STATE" | awk 'NR==2 {print $4*1024}')"
awk -v free="$STAGING_FREE" -v vendor="$VENDOR_BYTES" 'BEGIN {exit !(free > vendor*2+1073741824)}' || stop "ISOLATED_STAGING_DISK_SPACE_INSUFFICIENT"

STAGE=fetch_reviewed_business_plan
echo "STEP=$STAGE"
SOURCE="$STATE/source"
git clone --quiet --no-checkout "$REPOSITORY" "$SOURCE" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" fetch --quiet --no-tags origin "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" merge-base --is-ancestor "$BASE" "$TARGET" || stop "TARGET_NOT_BASED_ON_INSTALLED_PORTAL"
# Only the reviewed Business Plan slice and its isolated validation may change.
while IFS= read -r path; do
    case "$path" in
        app/Http/Controllers/PortalWorkspaceController.php|app/Http/Controllers/PortalBusinessPlanController.php|\
        app/Models/WorkspaceBusinessPlan.php|app/Models/WorkspaceBusinessPlanRevision.php|\
        app/Services/Portal/BusinessPlanContent.php|app/Services/Portal/PortalTestDataCleanup.php|routes/partner-portal.php|\
        public/css/portal-business-plan.css|resources/views/portal/layout.blade.php|\
        resources/views/portal/workspaces/show.blade.php|resources/views/portal/workspaces/tabs.blade.php|resources/views/portal/workspaces/business-plan/*|\
        "$MIGRATION"|scripts/pbr-business-plan-update.sh|scripts/pbr-business-plan-release-check.php|scripts/pbr-business-plan-check-support.php|scripts/tests/*|tests/*|docs/*|.github/workflows/tests.yml) ;;
        *) stop "CHANGE_OUTSIDE_REVIEWED_BUSINESS_PLAN_SCOPE";;
    esac
done < <(git -C "$SOURCE" diff --name-only "$BASE" "$TARGET")
[[ "$(git -C "$SOURCE" diff --name-only "$BASE" "$TARGET" -- database/migrations)" == "$MIGRATION" ]] || stop "UNREVIEWED_MIGRATION_CHANGE"
env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$SOURCE/scripts/pbr-portal-release-check.php" filament-inspect "$APP_ROOT" "$STATE/database-meta.json" "$STATE/cached-config.sha"
env -i PATH="$PATH" APP_CONFIG_CACHE="$STATE/uncached-config.php" "$PHP_BIN" -d display_errors=0 \
    "$SOURCE/scripts/pbr-portal-release-check.php" filament-inspect "$APP_ROOT" "$STATE/uncached-database-meta.json" "$STATE/uncached-config.sha"
cmp -s "$STATE/cached-config.sha" "$STATE/uncached-config.sha" || stop "CACHED_ENVIRONMENT_CONFIG_MISMATCH"
cmp -s "$STATE/database-meta.json" "$STATE/uncached-database-meta.json" || stop "CACHED_DATABASE_MISMATCH"
env -i PATH="$PATH" "$PHP_BIN" "$SOURCE/scripts/pbr-knowledge-release-check.php" config "$APP_ROOT"
env -i PATH="$PATH" APP_CONFIG_CACHE="$STATE/uncached-private-config.php" "$PHP_BIN" "$SOURCE/scripts/pbr-knowledge-release-check.php" config "$APP_ROOT"
env -i PATH="$PATH" "$PHP_BIN" "$SOURCE/scripts/pbr-business-plan-release-check.php" preflight "$APP_ROOT"
echo "PUBLIC_CONTENT_AI_AND_ASSESSMENT_SCORING=PRESERVED"

STAGE=isolated_business_plan_and_publishing_tests
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
echo "SERVER_ISOLATED_BUSINESS_PLAN_AND_PUBLISHING_TESTS=OK"

STAGE=validate_original_partner_ai
echo "STEP=$STAGE"
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" "$APP_ROOT/scripts/pbr-knowledge-release-check.php" ai-check "$APP_ROOT"
[[ "$(git -C "$AI_DIR" rev-parse HEAD)" == "$AI_EXPECTED" && -z "$(git --no-optional-locks -C "$AI_DIR" status --porcelain)" ]] || stop "ORIGINAL_AI_SOURCE_CHANGED_DURING_CHECKS"
echo "ORIGINAL_PARTNER_AI_SOURCE=PRESERVED"

STAGE=maintenance_and_verified_current_portal_backup
echo "STEP=$STAGE"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$BASE" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "LIVE_SOURCE_CHANGED_DURING_TESTS"
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop "LIVE_ENV_CHANGED_DURING_TESTS"
MAINTENANCE=1
artisan down --retry=30 --render=errors::503 --with-secret
bash "$SOURCE/scripts/pbr-filament-backup.sh" "$APP_ROOT" "$BASE" | tee "$STATE/backup-output.txt"
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
env -i PATH="$PATH" "$PHP_BIN" "$SOURCE/scripts/pbr-business-plan-release-check.php" snapshot "$APP_ROOT" "$STATE/projection.json" "$STATE/records-before.json" >>"$STATE/private.log" 2>&1
private_digest >"$STATE/private-files-before.sha256" || stop "PRIVATE_ORIGINAL_SNAPSHOT_FAILED"

STAGE=isolated_mysql_business_plan_migration
echo "STEP=$STAGE"
TOKEN="$(openssl rand -hex 8)"
DRILL_DB="pbr_plan_release_$TOKEN"
DRILL_USER="pbrplan_$TOKEN"
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
PBR_PARTNER_PORTAL_ENABLED=true
EOF
)
unset DRILL_PASSWORD
rm -f -- "$SOURCE/bootstrap/cache/config.php"
(
    cd "$SOURCE"
    env -i PATH="$PATH" "$PHP_BIN" artisan migrate --database=mysql --path="$MIGRATION" --force
    env -i PATH="$PATH" "$PHP_BIN" scripts/pbr-business-plan-release-check.php verify "$SOURCE" "$STATE/projection.json" "$STATE/records-before.json"
    env -i PATH="$PATH" "$PHP_BIN" scripts/pbr-business-plan-release-check.php render "$SOURCE"
) >>"$STATE/private.log" 2>&1
drop_drill || stop "REHEARSAL_CLEANUP_FAILED"
rm -f -- "$STATE/drill.cnf"
echo "ISOLATED_MYSQL_BUSINESS_PLAN_MIGRATION=OK"
echo "REHEARSAL_ACCOUNT_ISOLATED=YES"
echo "REHEARSAL_EXISTING_RECORDS=PRESERVED"

STAGE=install_private_business_plan
echo "STEP=$STAGE"
git -C "$APP_ROOT" fetch --quiet --no-tags "$SOURCE" "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$APP_ROOT" branch "backup/pre-business-plan-$(basename "$STATE")" "$BASE"
CODE_ATTEMPTED=1
artisan filament:clear-cached-components
git -C "$APP_ROOT" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
(cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1
artisan config:clear
artisan route:clear
artisan migrate --path="$MIGRATION" --force
artisan config:cache
artisan filament:cache-components
artisan view:cache
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"
env -i PATH="$PATH" "$PHP_BIN" "$APP_ROOT/scripts/pbr-business-plan-release-check.php" verify "$APP_ROOT" "$STATE/projection.json" "$STATE/records-before.json"
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" "$APP_ROOT/scripts/pbr-business-plan-release-check.php" render "$APP_ROOT"
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" "$APP_ROOT/scripts/pbr-knowledge-release-check.php" render "$APP_ROOT"
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop "LIVE_CONFIGURATION_CHANGED"
files_and_ai_preserved || stop "PRIVATE_FILES_OR_ORIGINAL_AI_CHANGED"
echo "EXISTING_CONTENT_ACCOUNTS_CHATS_AND_ACCESS=PRESERVED"
echo "SCHEMA_CHANGE=TWO_BUSINESS_PLAN_TABLES_ONLY"
echo "ENVIRONMENT_AND_ACCOUNT_ACCESS=UNCHANGED"

STAGE=public_website_and_admin_checks
echo "STEP=$STAGE"
# Probe the actual front door through Laravel's temporary maintenance bypass.
# Real visitors remain in maintenance until every route and record check passes.
[[ -r "$APP_ROOT/storage/framework/down" ]] || stop "FILE_MAINTENANCE_REQUIRED"
MAINTENANCE_SECRET="$("$PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $s=$d["secret"]??""; if(!is_string($s)||!preg_match("/^[A-Za-z0-9]{16,128}$/D",$s)) exit(2); echo $s;' "$APP_ROOT/storage/framework/down")"
BYPASS_CODE="$(printf 'url = "https://thepbr.io/%s"\n' "$MAINTENANCE_SECRET" | curl --config - -sS --connect-timeout 5 --max-time 20 -c "$STATE/maintenance.cookies" -o /dev/null -w '%{http_code}')"
unset MAINTENANCE_SECRET
[[ "$BYPASS_CODE" == 302 || "$BYPASS_CODE" == 303 ]] || stop "MAINTENANCE_BYPASS_UNAVAILABLE"
grep -q 'laravel_maintenance' "$STATE/maintenance.cookies" || stop "MAINTENANCE_COOKIE_MISSING"
for path in / /login /articles /videos /classes /admin/login; do
    code="$(curl -sS --cookie "$STATE/maintenance.cookies" -L --max-redirs 3 --connect-timeout 5 --max-time 20 -H 'Cache-Control: no-cache' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?business_plan_release=${TARGET}")"
    [[ "$code" == 200 ]] || stop "PUBLIC_OR_ADMIN_HTTPS_CHECK_FAILED"
done
for path in /portal /portal/workspaces /portal/workspaces/0/business-plan /portal/workspaces/0/business-plan/history /admin; do
    code="$(curl -sS --cookie "$STATE/maintenance.cookies" --connect-timeout 5 --max-time 20 -H 'Accept: text/html' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?business_plan_release=${TARGET}")"
    [[ "$code" == 302 || "$code" == 303 ]] || stop "ANONYMOUS_AUTH_CHECK_FAILED"
done
for asset in portal-business-plan.css partner-chat.css; do
    curl -fsS --cookie "$STATE/maintenance.cookies" --connect-timeout 5 --max-time 20 \
        "https://thepbr.io/css/${asset}?business_plan_release=${TARGET}" -o "$STATE/$asset"
    cmp -s "$APP_ROOT/public/css/$asset" "$STATE/$asset" || stop "BUSINESS_PLAN_ASSET_MISMATCH"
done
env -i PATH="$PATH" "$PHP_BIN" "$APP_ROOT/scripts/pbr-business-plan-release-check.php" verify "$APP_ROOT" "$STATE/projection.json" "$STATE/records-before.json"
files_and_ai_preserved || stop "PRIVATE_FILES_OR_ORIGINAL_AI_CHANGED"
echo "MAINTENANCE_PROBES_BEFORE_REOPEN=YES"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$TARGET" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop "FINAL_SOURCE_NOT_CLEAN"
printf 'TARGET=%s\nBASE=%s\nBACKUP=%s\nCOMPLETED_UTC=%s\n' "$TARGET" "$BASE" "$FRESH_BACKUP" "$(date -u +%FT%TZ)" >"$STATE/update-complete.txt"
echo "PUBLIC_WEBSITE_AND_ADMIN_HTTPS=OK"
echo "PRIVATE_ORIGINALS_AND_AI_SOURCE=PRESERVED"
echo "BUSINESS_PLAN_DRAFT_REVIEW_HISTORY_AND_KNOWLEDGE=INSTALLED"
echo "SIGNED_IN_BUSINESS_PLAN_KNOWLEDGE_AND_MOBILE_UAT=PENDING"
artisan up
MAINTENANCE=0
COMPLETE=1
