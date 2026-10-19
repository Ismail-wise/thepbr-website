#!/usr/bin/env bash
# One-time, explicitly authorized removal of test workspaces/non-admin accounts.
# Never restores an old database automatically after a committed cleanup.
set -Eeuo pipefail
umask 022
TARGET="${1:-}"
APP_ROOT="${2:-/var/www/thepbr-laravel}"
BASE=ca1595f8f758d4e1d3376b19a3639b3c0629197f
REPOSITORY=https://github.com/Ismail-wise/thepbr-website.git
BACKUP_PARENT=/root/thepbr-deploy-backups
WEB_USER=www-data
STATE="" SOURCE="" JOURNAL="" FRESH_BACKUP=""
DRILL_DB="" DRILL_USER=""
DB_CREATED=0 USER_CREATED=0 MAINTENANCE=0 CODE_ATTEMPTED=0 COMPLETE=0 APPLY_ATTEMPTED=0
STAGE=prerequisites

echo PBR_PORTAL_TEST_RESET=START
stop() { echo "PORTAL_RESET_STOP_REASON=$1"; exit 2; }
artisan() {
    (cd "$APP_ROOT" && runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" artisan "$@") >>"$STATE/private.log" 2>&1
}
reset_helper() {
    env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$SOURCE/scripts/pbr-portal-reset.php" "$@" 2>>"$STATE/private.log"
}
public_checks() {
    for path in / /login /articles /videos /classes /admin/login; do
        code="$(curl -sS -L --max-redirs 3 --connect-timeout 5 --max-time 20 -H 'Cache-Control: no-cache' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?portal_reset=${TARGET}")"
        [[ "$code" == 200 ]] || stop PUBLIC_OR_ADMIN_HTTPS_CHECK_FAILED
    done
    for path in /portal /admin; do
        code="$(curl -sS --connect-timeout 5 --max-time 20 -H 'Accept: text/html' -o /dev/null -w '%{http_code}' "https://thepbr.io${path}?portal_reset=${TARGET}")"
        [[ "$code" == 302 || "$code" == 303 ]] || stop ANONYMOUS_AUTH_CHECK_FAILED
    done
    echo PUBLIC_WEBSITE_AND_ADMIN_HTTPS=OK
}
journal_status() {
    [[ -f "$JOURNAL" ]] || { echo absent; return; }
    "$PHP_BIN" -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $v["status"]??"unknown";' "$JOURNAL"
}
drop_drill() {
    if [[ "$USER_CREATED" == 1 ]]; then
        printf "DROP USER '%s'@'localhost';\n" "$DRILL_USER" | "${ADMIN[@]}" >>"$STATE/private.log" 2>&1 || return 1
        USER_CREATED=0
    fi
    if [[ "$DB_CREATED" == 1 && "$DRILL_DB" =~ ^pbr_reset_drill_[a-f0-9]{16}$ && "$DRILL_DB" != "${DB_NAME:-}" ]]; then
        printf 'DROP DATABASE `%s`;\n' "$DRILL_DB" | "${ADMIN[@]}" >>"$STATE/private.log" 2>&1 || return 1
        DB_CREATED=0
    fi
}
rollback_code() {
    local ok=1
    echo PORTAL_RESET_CODE_ROLLBACK=START
    artisan filament:clear-cached-components || ok=0
    if [[ "$CODE_ATTEMPTED" == 1 ]]; then
        git -C "$APP_ROOT" checkout --quiet --detach "$BASE" >>"$STATE/private.log" 2>&1 || ok=0
        (cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1 || ok=0
    fi
    artisan config:clear || ok=0
    artisan route:clear || ok=0
    artisan view:clear || ok=0
    artisan config:cache || ok=0
    artisan filament:cache-components || ok=0
    artisan view:cache || ok=0
    if [[ "$ok" == 1 ]] && artisan up; then
        MAINTENANCE=0
        echo PORTAL_RESET_CODE_ROLLBACK=OK
        echo DATABASE_RESET_COMMITTED=NO
    else
        echo PORTAL_RESET_CODE_ROLLBACK=NEEDS_REVIEW
        echo WEBSITE_MAINTENANCE=ON
    fi
}
finish() {
    local result=$? status=absent
    trap - EXIT ERR
    set +e
    if [[ -n "$STATE" ]]; then
        drop_drill || { echo TEMP_DATABASE_CLEANUP=NEEDS_REVIEW; result=2; }
        if [[ "$COMPLETE" != 1 && "$MAINTENANCE" == 1 ]]; then
            status="$(journal_status)"
            if [[ "$APPLY_ATTEMPTED" == 1 && "$status" == pending ]]; then
                reset_helper outcome "$APP_ROOT" "$STATE" "$JOURNAL"
                status="$(journal_status)"
            fi
            case "$status" in
                absent|rolled_back)
                    # A transaction either never began or was verified rolled back.
                    rollback_code
                    if [[ "$status" == rolled_back ]]; then rm -f -- "$JOURNAL"; fi
                    ;;
                committed)
                    echo DATABASE_RESET_COMMITTED=YES
                    echo WEBSITE_MAINTENANCE=ON
                    echo PORTAL_RESET_RECOVERY=VERIFY_TARGET_CODE_BEFORE_REOPENING
                    ;;
                *)
                    echo DATABASE_RESET_OUTCOME=NEEDS_REVIEW
                    echo WEBSITE_MAINTENANCE=ON
                    ;;
            esac
        fi
        if [[ -n "$SOURCE" && "$SOURCE" == "$STATE/source" ]]; then rm -rf -- "$SOURCE"; fi
        echo "PORTAL_RESET_RECEIPT_PATH=$STATE"
    fi
    if [[ "$result" == 0 && "$COMPLETE" == 1 ]]; then
        echo "LIVE_SOURCE_HEAD=$TARGET"
        echo PBR_PORTAL_TEST_RESET=COMPLETE
    else
        echo "PORTAL_RESET_FAILED_STAGE=$STAGE"
        echo PBR_PORTAL_TEST_RESET=INCOMPLETE
        result=2
    fi
    exit "$result"
}
trap finish EXIT
trap 'echo "PORTAL_RESET_STEP_FAILED=$STAGE"' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

[[ "$EUID" == 0 ]] || stop RUN_WITH_SUDO_ON_PBR_SERVER
[[ $# -ge 1 && $# -le 2 && "$TARGET" =~ ^[a-f0-9]{40}$ ]] || stop EXPECTED_REVIEWED_COMMIT_SHA
for executable in php composer git mysql mysqldump curl openssl tar gzip sha256sum cmp sed awk grep flock runuser realpath stat tee du df; do
    command -v "$executable" >/dev/null || stop "MISSING_${executable^^}"
done
PHP_BIN="$(command -v php)"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_sqlite") && extension_loaded("pdo_mysql") ? 0 : 2);' || stop PHP_84_SQLITE_MYSQL_REQUIRED
APP_ROOT="$(realpath -e -- "$APP_ROOT")"
[[ -f "$APP_ROOT/.env" && ! -L "$APP_ROOT/.env" && -f "$APP_ROOT/vendor/autoload.php" ]] || stop REGULAR_APP_AND_ENV_REQUIRED
[[ -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop LIVE_SOURCE_NOT_CLEAN
CURRENT="$(git -C "$APP_ROOT" rev-parse HEAD)"
[[ "$CURRENT" == "$BASE" || "$CURRENT" == "$TARGET" ]] || stop LIVE_SOURCE_CHANGED_RECHECK_REQUIRED
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/storage" || stop STORAGE_NOT_WRITABLE_BY_WEB_USER
runuser -u "$WEB_USER" -- test -w "$APP_ROOT/bootstrap/cache" || stop BOOTSTRAP_CACHE_NOT_WRITABLE_BY_WEB_USER
[[ ! -L "$BACKUP_PARENT" ]] || stop BACKUP_PARENT_IS_SYMLINK
mkdir -p -- "$BACKUP_PARENT"
chmod 700 -- "$BACKUP_PARENT"
exec 8>"$BACKUP_PARENT/.partner-portal-install.lock"
flock -n 8 || stop ANOTHER_PORTAL_INSTALL_IS_RUNNING
STATE="$BACKUP_PARENT/portal-test-reset-$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 4)"
mkdir -m 700 -- "$STATE"
exec 2>>"$STATE/private.log"
cp -p -- "$APP_ROOT/.env" "$STATE/original.env"
printf '%s\n' "$TARGET" >"$STATE/target.txt"
env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" \
    filament-inspect "$APP_ROOT" "$STATE/database-meta.json" "$STATE/cached-config.sha"
env -i PATH="$PATH" APP_CONFIG_CACHE="$STATE/uncached-config.php" "$PHP_BIN" -d display_errors=0 \
    "$APP_ROOT/scripts/pbr-portal-release-check.php" filament-inspect "$APP_ROOT" "$STATE/uncached-database-meta.json" "$STATE/uncached-config.sha"
cmp -s "$STATE/cached-config.sha" "$STATE/uncached-config.sha" || stop CACHED_ENVIRONMENT_CONFIG_MISMATCH
cmp -s "$STATE/database-meta.json" "$STATE/uncached-database-meta.json" || stop CACHED_DATABASE_MISMATCH
DB_NAME="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR)["database"];' "$STATE/database-meta.json")"
DB_SOCKET="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR)["socket"];' "$STATE/database-meta.json")"
JOURNAL="$BACKUP_PARENT/.portal-test-reset-$("$PHP_BIN" -r 'echo hash("sha256",$argv[1]."\n".$argv[2]."\n".$argv[3]);' "$APP_ROOT" "$DB_NAME" "$BASE").json"
if [[ "$(journal_status)" != absent ]]; then
    [[ "$CURRENT" == "$TARGET" && "$(journal_status)" == committed ]] || stop PREVIOUS_RESET_REQUIRES_REVIEW_DO_NOT_REPEAT
    "$PHP_BIN" -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); exit(($v["target"]??null)===$argv[2]?0:2);' "$JOURNAL" "$TARGET" || stop PREVIOUS_RESET_TARGET_MISMATCH
    runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"
    public_checks
    echo ALREADY_COMPLETED=YES
    echo NON_ADMIN_ACCOUNTS_REMOVED_THIS_RUN=0
    COMPLETE=1
    exit 0
fi
[[ "$CURRENT" == "$BASE" ]] || stop RESET_RECEIPT_MISSING_DO_NOT_REPEAT

STAGE=fetch_reviewed_cleanup
echo "STEP=$STAGE"
SOURCE="$STATE/source"
git clone --quiet --no-checkout "$REPOSITORY" "$SOURCE" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" fetch --quiet --no-tags origin "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$SOURCE" merge-base --is-ancestor "$BASE" "$TARGET" || stop TARGET_NOT_BASED_ON_CURRENT_FILAMENT
while IFS= read -r path; do
    case "$path" in
        app/Filament/Widgets/ActivationFunnelWidget.php|app/Filament/Widgets/AreaProgressWidget.php|app/Filament/Widgets/PortalAccessWidget.php|\
        app/Filament/Resources/UserAccess/Tables/UserAccessTable.php|app/Filament/Resources/StudentEnrollments/StudentEnrollmentResource.php|\
        app/Filament/Resources/StudentAccessCodes/StudentAccessCodeResource.php|app/Filament/Resources/WorkspaceMembers/WorkspaceMemberResource.php|\
        app/Services/Portal/PortalTestDataCleanup.php|scripts/pbr-filament-backup.sh|scripts/pbr-portal-reset.sh|scripts/pbr-portal-reset.php|\
        scripts/tests/*|tests/*|docs/*|.github/workflows/tests.yml) ;;
        *) stop CHANGE_OUTSIDE_REVIEWED_CLEANUP_SCOPE;;
    esac
done < <(git -C "$SOURCE" diff --name-only "$BASE" "$TARGET")
echo PUBLIC_CONTENT_AI_AND_PARTNER_DYNAMICS_SOURCE=PRESERVED

STAGE=isolated_cleanup_and_publishing_tests
echo "STEP=$STAGE"
VENDOR_BYTES="$(du -sb "$APP_ROOT/vendor" | awk '{print $1}')"
STAGING_FREE="$(df -Pk "$STATE" | awk 'NR==2 {print $4*1024}')"
awk -v free="$STAGING_FREE" -v vendor="$VENDOR_BYTES" 'BEGIN {exit !(free > vendor*2+1073741824)}' || stop ISOLATED_STAGING_DISK_SPACE_INSUFFICIENT
cp -a -- "$APP_ROOT/vendor" "$SOURCE/vendor"
cp "$SOURCE/.env.example" "$SOURCE/.env"
TEST_KEY="base64:$(openssl rand -base64 32)"
mkdir -p "$SOURCE/storage/framework/cache/data" "$SOURCE/storage/framework/views" "$SOURCE/storage/framework/sessions" "$SOURCE/storage/logs"
if [[ ! -f "$SOURCE/vendor/bin/pest" ]]; then
    (cd "$SOURCE" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --no-interaction --prefer-dist --no-progress) >>"$STATE/private.log" 2>&1
fi
(cd "$SOURCE" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1
(cd "$SOURCE" && env -i PATH="$PATH" APP_ENV=testing APP_KEY="$TEST_KEY" APP_MAINTENANCE_DRIVER=file \
    DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
    PBR_AI_BASE_URL=http://127.0.0.1:9 PBR_AI_INTERNAL_SECRET=isolated-test-secret-never-production \
    PULSE_ENABLED=false TELESCOPE_ENABLED=false NIGHTWATCH_ENABLED=false "$PHP_BIN" vendor/bin/pest --ci --colors=never) >>"$STATE/private.log" 2>&1
echo SERVER_ISOLATED_CLEANUP_AND_PUBLISHING_TESTS=OK

STAGE=maintenance_and_verified_backup
echo "STEP=$STAGE"
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$BASE" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop LIVE_SOURCE_CHANGED_DURING_TESTS
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop LIVE_ENV_CHANGED_DURING_TESTS
MAINTENANCE=1
artisan down --retry=30 --render=errors::503
reset_helper plan "$APP_ROOT" "$STATE"
bash "$SOURCE/scripts/pbr-filament-backup.sh" "$APP_ROOT" "$BASE" | tee "$STATE/backup-output.txt"
FRESH_BACKUP="$(sed -n 's/^BACKUP_PATH=//p' "$STATE/backup-output.txt")"
grep -qx PBR_PORTAL_BACKUP=COMPLETE "$STATE/backup-output.txt" || stop BACKUP_RESTORE_DRILL_INCOMPLETE
[[ "$FRESH_BACKUP" == "$BACKUP_PARENT"/pre-filament-admin-* && -f "$FRESH_BACKUP/restore-verified.txt" ]] || stop VERIFIED_CURRENT_BACKUP_REQUIRED
grep -qx "SOURCE_HEAD=$BASE" "$FRESH_BACKUP/restore-verified.txt" || stop BACKUP_SOURCE_MISMATCH
cmp -s "$STATE/database-meta.json" "$FRESH_BACKUP/database-meta.json" || stop BACKUP_DATABASE_MISMATCH
(cd "$FRESH_BACKUP" && sha256sum --check --status SHA256SUMS)
echo "FRESH_VERIFIED_BACKUP=$FRESH_BACKUP"

STAGE=isolated_mysql_cleanup_rehearsal
echo "STEP=$STAGE"
ADMIN=(mysql --protocol=socket --socket="$DB_SOCKET" --user=root --batch --skip-column-names)
EXTERNAL_FKS="$("${ADMIN[@]}" --execute="SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA='$DB_NAME' AND TABLE_SCHEMA <> '$DB_NAME';")"
[[ "$EXTERNAL_FKS" == 0 ]] || stop CROSS_DATABASE_DEPENDENCIES_REQUIRE_REVIEW
TOKEN="$(openssl rand -hex 8)"
DRILL_DB="pbr_reset_drill_$TOKEN"
DRILL_USER="pbrprst_$TOKEN"
DRILL_PASSWORD="$(openssl rand -hex 32)"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$DRILL_DB" | "${ADMIN[@]}"
DB_CREATED=1
printf "CREATE USER '%s'@'localhost' IDENTIFIED BY '%s';\n" "$DRILL_USER" "$DRILL_PASSWORD" | "${ADMIN[@]}"
USER_CREATED=1
DRILL_GRANT="${DRILL_DB//_/\\_}"
printf 'GRANT ALL PRIVILEGES ON `%s`.* TO '\''%s'\''@'\''localhost'\'';\n' "$DRILL_GRANT" "$DRILL_USER" | "${ADMIN[@]}"
printf '[client]\nuser=%s\npassword=%s\nprotocol=socket\nsocket=%s\ndefault-character-set=utf8mb4\n' "$DRILL_USER" "$DRILL_PASSWORD" "$DB_SOCKET" >"$STATE/drill.cnf"
chmod 600 "$STATE/drill.cnf"
"$PHP_BIN" -r '$v=parse_ini_file($argv[1],true,INI_SCANNER_RAW)["client"]; file_put_contents($argv[2],json_encode(["database"=>$argv[3],"username"=>$v["user"],"password"=>$v["password"],"socket"=>$v["socket"]],JSON_THROW_ON_ERROR)); chmod($argv[2],0600);' "$STATE/drill.cnf" "$STATE/drill-connection.json" "$DRILL_DB"
unset DRILL_PASSWORD
if mysql --defaults-file="$STATE/drill.cnf" --database="$DB_NAME" --execute='SELECT 1;' >>"$STATE/private.log" 2>&1; then stop REHEARSAL_USER_CAN_ACCESS_PRODUCTION; fi
mysql --defaults-file="$STATE/drill.cnf" --database="$DRILL_DB" <"$FRESH_BACKUP/database.sql"
reset_helper rehearse "$APP_ROOT" "$STATE" "$STATE/drill-connection.json"
drop_drill
echo TEMP_DATABASE_CLEANUP=OK

STAGE=install_simplified_filament
echo "STEP=$STAGE"
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop LIVE_ENV_CHANGED
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$BASE" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop LIVE_SOURCE_CHANGED
git -C "$APP_ROOT" fetch --quiet --no-tags "$SOURCE" "$TARGET" >>"$STATE/private.log" 2>&1
git -C "$APP_ROOT" branch "backup/pre-test-reset-$(basename "$STATE")" "$BASE"
CODE_ATTEMPTED=1
artisan filament:clear-cached-components
git -C "$APP_ROOT" checkout --quiet --detach "$TARGET" >>"$STATE/private.log" 2>&1
(cd "$APP_ROOT" && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction) >>"$STATE/private.log" 2>&1
artisan config:clear
artisan route:clear
artisan config:cache
artisan filament:cache-components
artisan view:cache
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"

STAGE=atomic_test_data_cleanup
echo "STEP=$STAGE"
APPLY_ATTEMPTED=1
reset_helper apply "$APP_ROOT" "$STATE" "$JOURNAL"
runuser -u "$WEB_USER" -- env -i PATH="$PATH" "$PHP_BIN" -d display_errors=0 "$APP_ROOT/scripts/pbr-portal-release-check.php" render "$APP_ROOT"
cmp -s "$APP_ROOT/.env" "$STATE/original.env" || stop LIVE_CONFIGURATION_CHANGED
[[ "$(git -C "$APP_ROOT" rev-parse HEAD)" == "$TARGET" && -z "$(git --no-optional-locks -C "$APP_ROOT" status --porcelain)" ]] || stop FINAL_SOURCE_NOT_CLEAN
echo SCHEMA_ENVIRONMENT_AND_ID_SEQUENCES=UNCHANGED
artisan up
MAINTENANCE=0

STAGE=public_website_and_admin_checks
echo "STEP=$STAGE"
public_checks
printf 'TARGET=%s\nBASE=%s\nBACKUP=%s\nCOMPLETED_UTC=%s\n' "$TARGET" "$BASE" "$FRESH_BACKUP" "$(date -u +%FT%TZ)" >"$STATE/reset-complete.txt"
echo SIGNED_IN_ADMIN_AND_NEW_SIGNUP_UAT=PENDING
COMPLETE=1
