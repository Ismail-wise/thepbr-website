#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

PROD="/var/www/thepbr-laravel"
FEATURE="feature/course-chapters-1-to-10-build"
DB_NAME="thepbr_laravel"
DB_USER="thepbr_app"
DB_PASSWORD_FILE="/root/.thepbr_db_password"
BACKUP_ROOT="/root/thepbr-deploy-backups"
STAMP="$(date +%Y%m%d_%H%M%S)"
WORKTREE="/tmp/pbr-operating-system-${STAMP}"
ROLLBACK_BRANCH="backup/pre-pbr-operating-system-${STAMP}"
MAINTENANCE=0
APP_KEY_VALUE=""
TARGET=""
CURRENT=""

run_artisan() {
    runuser -u www-data -- env \
        HOME=/tmp \
        XDG_CONFIG_HOME=/tmp \
        php artisan "$@"
}

run_isolated_artisan() {
    env \
        APP_ENV=testing \
        APP_KEY="$APP_KEY_VALUE" \
        APP_MAINTENANCE_DRIVER=file \
        BCRYPT_ROUNDS=4 \
        BROADCAST_CONNECTION=null \
        CACHE_STORE=array \
        DB_CONNECTION=sqlite \
        DB_DATABASE=:memory: \
        DB_URL="" \
        MAIL_MAILER=array \
        QUEUE_CONNECTION=sync \
        SESSION_DRIVER=array \
        PULSE_ENABLED=false \
        TELESCOPE_ENABLED=false \
        NIGHTWATCH_ENABLED=false \
        php artisan "$@"
}

cleanup() {
    if [[ "$MAINTENANCE" == "1" && -d "$PROD" ]]; then
        echo
        echo "=== SAFETY: BRING WEBSITE BACK ONLINE ==="
        (
            cd "$PROD"
            run_artisan up >/dev/null 2>&1 || true
        )
    fi

    if [[ -d "$WORKTREE" ]]; then
        git -C "$PROD" worktree remove --force "$WORKTREE" >/dev/null 2>&1 || rm -rf "$WORKTREE"
    fi
}

on_error() {
    local line="$1"
    local command="${2:-unknown}"
    echo
    echo "=== PBR OPERATING SYSTEM DEPLOY STOPPED SAFELY ==="
    echo "A command failed near script line ${line}."
    echo "Failed command: ${command}"
    echo "Production rollback branch (if created): ${ROLLBACK_BRANCH}"
    echo "Website maintenance mode will be released automatically."
    echo "Do not run random repair commands. Send this terminal output back for review."
}

trap 'on_error "$LINENO" "$BASH_COMMAND"' ERR
trap cleanup EXIT

fail() {
    echo "ERROR: $1" >&2
    false
}

cd "$PROD"

echo "=== PBR CHAPTER 1-10 ONE-PASS VALIDATION + DEPLOY ==="
echo "Production stays unchanged until isolated validation passes."

[[ -f artisan ]] || fail "Laravel production directory was not found."
[[ -f "$DB_PASSWORD_FILE" ]] || fail "Database password file is missing."
[[ -d .git ]] || fail "Production directory is not a Git repository."
[[ -d vendor ]] || fail "Production vendor directory is missing."
command -v composer >/dev/null 2>&1 || fail "Composer is required for isolated autoload validation."

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Working tree has changes:"
    git status --short
    fail "Production working tree must be clean before deployment."
fi

CURRENT="$(git rev-parse HEAD)"
echo "Current production commit: $(git rev-parse --short HEAD)"
echo "Current branch: $(git branch --show-current)"

APP_KEY_VALUE="$(sed -n 's/^APP_KEY=//p' .env | head -n 1)"
[[ -n "$APP_KEY_VALUE" ]] || fail "APP_KEY could not be read from production environment."

echo
echo "=== FETCH FEATURE BRANCH ==="
git fetch origin "$FEATURE"
TARGET="$(git rev-parse "origin/${FEATURE}")"
echo "Target commit: $(git rev-parse --short "$TARGET")"

git merge-base --is-ancestor "$CURRENT" "$TARGET" \
    || fail "Feature branch is not a safe fast-forward from current production."

echo
echo "=== VERIFY TEST DATABASE IS ISOLATED ==="
git show "$TARGET:phpunit.xml" | grep -q 'name="DB_CONNECTION" value="sqlite"' \
    || fail "phpunit.xml does not force SQLite testing."
git show "$TARGET:phpunit.xml" | grep -q 'name="DB_DATABASE" value=":memory:"' \
    || fail "phpunit.xml does not force an in-memory test database."
echo "Test DB isolation: PASS (SQLite :memory:)"

echo
echo "=== PREPARE ISOLATED FEATURE WORKTREE ==="
git worktree add --detach "$WORKTREE" "$TARGET"

# Never symlink production/vendor into the worktree. Composer's generated
# PSR-4 paths are rooted to the directory where the autoloader was generated;
# a symlink would make feature App\\ classes resolve against production code.
cp -a "$PROD/vendor" "$WORKTREE/vendor"
mkdir -p \
    "$WORKTREE/storage/framework/cache/data" \
    "$WORKTREE/storage/framework/sessions" \
    "$WORKTREE/storage/framework/views" \
    "$WORKTREE/storage/logs" \
    "$WORKTREE/bootstrap/cache"

cd "$WORKTREE"

echo
echo "=== BUILD ISOLATED COMPOSER AUTOLOAD ==="
composer dump-autoload --no-scripts --no-interaction --optimize
php -r '
require "vendor/autoload.php";
$required = [
    "App\\Http\\Controllers\\WorkspacePartnerProfileController",
    "App\\Http\\Controllers\\WorkspaceOperatingToolController",
    "App\\Services\\PbrTools\\PbrOperatingSystemService",
    "App\\Services\\PbrTools\\PbrOperatingToolEngine",
    "App\\Models\\WorkspacePartnerProfile",
];
foreach ($required as $class) {
    if (! class_exists($class)) {
        fwrite(STDERR, "Feature autoload failed: {$class}\n");
        exit(1);
    }
}
echo "Feature application autoload: PASS\n";
'

echo
echo "=== STATIC PHP VALIDATION ==="
while IFS= read -r file; do
    [[ -z "$file" || ! -f "$WORKTREE/$file" ]] && continue
    php -l "$WORKTREE/$file" >/dev/null
    echo "PASS  $file"
done < <(git -C "$PROD" diff --name-only "$CURRENT" "$TARGET" -- '*.php')

echo
echo "=== JAVASCRIPT VALIDATION ==="
node --check public/js/pbr-operating-system.js
echo "PBR operating JavaScript: PASS"

echo
echo "=== LARAVEL BOOT / CONFIG / VIEW VALIDATION ==="
run_isolated_artisan optimize:clear
run_isolated_artisan config:cache
run_isolated_artisan view:cache
run_isolated_artisan route:list --name=workspaces.tools.operating
run_isolated_artisan route:list --name=workspaces.tools.scenarios.approve
run_isolated_artisan route:list --name=workspaces.partner-roster
echo "Laravel config / Blade / routes: PASS"

echo
echo "=== RUN ISOLATED OPERATING-SYSTEM TEST SUITE ==="
run_isolated_artisan test \
    tests/Feature/PbrOperatingToolEngineTest.php \
    tests/Feature/PbrOperatingSystemLifecycleTest.php \
    tests/Feature/PbrCourseCatalogIntegrityTest.php

echo
echo "=== PREDEPLOY VALIDATION COMPLETE ==="
echo "Production has not changed yet."

cd "$PROD"

echo
echo "=== CREATE VERIFIED PRODUCTION BACKUP ==="
mkdir -p "$BACKUP_ROOT/pre-pbr-operating-system-${STAMP}"
chmod 700 "$BACKUP_ROOT/pre-pbr-operating-system-${STAMP}"
DB_BACKUP="$BACKUP_ROOT/pre-pbr-operating-system-${STAMP}/thepbr_laravel.sql"
DB_PASSWORD="$(cat "$DB_PASSWORD_FILE")"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    -u "$DB_USER" \
    "$DB_NAME" > "$DB_BACKUP"
chmod 600 "$DB_BACKUP"

BACKUP_SIZE="$(stat -c%s "$DB_BACKUP")"
[[ "$BACKUP_SIZE" -gt 10000 ]] || fail "Database backup is unexpectedly small."
grep -q 'CREATE TABLE' "$DB_BACKUP" || fail "Database backup verification failed."
echo "Verified DB backup: $DB_BACKUP"
echo "Backup size: ${BACKUP_SIZE} bytes"

echo
echo "=== CREATE ROLLBACK BRANCH ==="
git branch "$ROLLBACK_BRANCH" "$CURRENT"
echo "Rollback branch: $ROLLBACK_BRANCH"

echo
echo "=== VERIFY CURRENT COURSE CATALOG ==="
TOOL_COUNT_BEFORE="$(MYSQL_PWD="$DB_PASSWORD" mysql -N -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(DISTINCT tool_key) FROM chapter_tools;")"
CHAPTER_COUNT_BEFORE="$(MYSQL_PWD="$DB_PASSWORD" mysql -N -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(DISTINCT chapter_number) FROM course_chapters;")"
echo "Existing distinct tool keys: $TOOL_COUNT_BEFORE"
echo "Existing chapter numbers: $CHAPTER_COUNT_BEFORE"

if [[ "$TOOL_COUNT_BEFORE" -lt 64 || "$CHAPTER_COUNT_BEFORE" -lt 10 ]]; then
    CATALOG_SEED_NEEDED=1
    echo "Catalog seed will run after migration because production catalog is incomplete."
else
    CATALOG_SEED_NEEDED=0
    echo "Existing catalog already contains the complete 10-chapter / 64-tool library."
fi

echo
echo "=== ENTER MAINTENANCE MODE ==="
run_artisan down --retry=60
MAINTENANCE=1

echo
echo "=== FAST-FORWARD PRODUCTION CODE ==="
git merge --ff-only "$TARGET"
echo "Production code commit: $(git rev-parse --short HEAD)"

# Keep source readable after earlier deployment permission issues. Git creates
# normal files with umask 022; explicitly repair new core directories only.
find app/Services/PbrTools app/Http/Controllers resources/views/workspaces/tools \
    -type d -exec chmod 755 {} +
find app/Services/PbrTools app/Http/Controllers resources/views/workspaces/tools \
    -type f -exec chmod 644 {} +
chmod 644 \
    config/pbr_operating_tools.php \
    routes/web.php \
    public/css/pbr-operating-system.css \
    public/css/pbr-operating-dashboard.css \
    public/css/pbr-operating-fixes.css \
    public/css/pbr-roster.css \
    public/js/pbr-operating-system.js \
    database/migrations/2026_08_12_120000_create_pbr_operating_system_tables.php
chmod 755 scripts/pbr-operating-system-production-install.sh
chown -R www-data:www-data storage bootstrap/cache

echo
echo "=== MIGRATE DATABASE ==="
run_artisan optimize:clear
run_artisan migrate --force

if [[ "$CATALOG_SEED_NEEDED" == "1" ]]; then
    echo
    echo "=== REPAIR COURSE CATALOG ==="
    run_artisan db:seed --class=Database\\Seeders\\CourseCatalogSeeder --force
fi

echo
echo "=== BUILD PRODUCTION CACHES ==="
run_artisan config:cache
run_artisan view:cache

echo
echo "=== VERIFY MIGRATION + CATALOG ==="
run_artisan migrate:status | grep '2026_08_12_120000_create_pbr_operating_system_tables'
TOOL_COUNT_AFTER="$(MYSQL_PWD="$DB_PASSWORD" mysql -N -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(DISTINCT tool_key) FROM chapter_tools;")"
CHAPTER_COUNT_AFTER="$(MYSQL_PWD="$DB_PASSWORD" mysql -N -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(DISTINCT chapter_number) FROM course_chapters;")"
[[ "$TOOL_COUNT_AFTER" -ge 64 ]] || fail "Production course catalog has fewer than 64 distinct tool keys."
[[ "$CHAPTER_COUNT_AFTER" -ge 10 ]] || fail "Production course catalog has fewer than 10 chapters."
echo "Course catalog: ${CHAPTER_COUNT_AFTER} chapters / ${TOOL_COUNT_AFTER} distinct tools"

echo
echo "=== VERIFY CONNECTED ROUTES ==="
run_artisan route:list --name=workspaces.tools.operating | grep -q 'workspaces.tools.operating.show'
run_artisan route:list --name=workspaces.tools.scenarios.approve | grep -q 'workspaces.tools.scenarios.approve'
run_artisan route:list --name=workspaces.partner-roster | grep -q 'workspaces.partner-roster.index'
echo "Operating tool routes: PASS"
echo "Agreed-rule route: PASS"
echo "Partner Roster routes: PASS"

echo
echo "=== BRING WEBSITE ONLINE ==="
run_artisan up
MAINTENANCE=0

echo
echo "=== PUBLIC WEBSITE HEALTH ==="
for URL in \
    "https://thepbr.io/" \
    "https://thepbr.io/login" \
    "https://thepbr.io/articles" \
    "https://thepbr.io/classes"
do
    CODE="$(curl -L -s -o /dev/null -w "%{http_code}" --max-time 20 "$URL")"
    echo "$CODE  $URL"
    [[ "$CODE" == "200" ]] || fail "Public health check failed for $URL"
done

echo
echo "=== VERIFY PBR AI ADVISOR WAS NOT BROKEN ==="
systemctl is-active --quiet pbr-ai-advisor.service
AI_HEALTH="$(curl -fsS --max-time 10 http://127.0.0.1:3107/health)"
echo "$AI_HEALTH"
echo "$AI_HEALTH" | grep -q '"knowledgeReady":true' \
    || fail "PBR AI Advisor knowledge health check failed."
echo "PBR AI Advisor: PASS"

echo
echo "=== FINAL GIT STATE ==="
echo "Commit: $(git rev-parse --short HEAD)"
echo "Branch: $(git branch --show-current)"
if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "Production working tree is not clean after deployment."
fi
echo "Working tree: CLEAN"

echo
echo "=== PBR OPERATING SYSTEM DEPLOY COMPLETE ==="
echo "Chapters: 10"
echo "Tool library: ${TOOL_COUNT_AFTER} distinct tools"
echo "Rollback branch: $ROLLBACK_BRANCH"
echo "Verified DB backup: $DB_BACKUP"
echo "Production commit: $(git rev-parse --short HEAD)"
