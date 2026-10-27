#!/usr/bin/env bash
# Disposable Actions fixture: real MySQL/backup/render; HTTPS and AI are doubles.
set -Eeuo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${CI:-}" == true && "$EUID" != 0 ]] || exit 2
umask 022
ROOT="$(pwd)"
TARGET="$(git rev-parse HEAD)"
BASE=369a1ec3bb2e947cfc0c18482b23ff96cf78fbcd
AI_HEAD=902dbbe52c7ac38924ba18a36101ab0a7b701ba0
FIXTURE="$(mktemp -d /tmp/pbr-plan-fixture-XXXXXXXX)"
AI_FIXTURE="$(mktemp -d /tmp/pbr-plan-ai-XXXXXXXX)"
ARTIFACTS="$(mktemp -d /tmp/pbr-plan-test-XXXXXXXX)"
FIXTURE_DB="pbr_plan_fixture_$(openssl rand -hex 8)"
ADMIN_CONFIG_CREATED=0
PROVIDER_PID=""
RECEIPTS=()
BACKUPS=()
cleanup() {
    local result=$?
    trap - EXIT
    set +e
    if [[ -n "$PROVIDER_PID" ]]; then kill "$PROVIDER_PID"; wait "$PROVIDER_PID"; fi
    if [[ "$ADMIN_CONFIG_CREATED" == 1 ]]; then
        printf 'DROP DATABASE IF EXISTS `%s`;\n' "$FIXTURE_DB" | sudo mysql
        sudo rm -f /root/.my.cnf
    fi
    for path in "${RECEIPTS[@]}" "${BACKUPS[@]}"; do
        if [[ "$path" == /root/thepbr-deploy-backups/* ]]; then sudo rm -rf -- "$path"; fi
    done
    git worktree remove --force "$FIXTURE" >/dev/null 2>&1 || true
    sudo rm -rf -- "$FIXTURE" "$AI_FIXTURE" "$ARTIFACTS"
    exit "$result"
}
trap cleanup EXIT
sudo test ! -e /root/.my.cnf
printf '[client]\nuser=root\npassword=root\n' | sudo install -m 600 /dev/stdin /root/.my.cnf
ADMIN_CONFIG_CREATED=1
git worktree add --detach "$FIXTURE" "$BASE"
git clone --quiet https://github.com/Ismail-wise/partner-ai.git "$AI_FIXTURE"
git -C "$AI_FIXTURE" checkout --quiet --detach "$AI_HEAD"
cp -a vendor "$FIXTURE/vendor"
(cd "$FIXTURE" && composer dump-autoload --no-scripts --no-interaction --optimize >/dev/null)
chmod -R a+rX "$FIXTURE" "$AI_FIXTURE"
node "$ROOT/scripts/tests/pbr-business-plan-provider-fixture.cjs" "$ARTIFACTS/provider-port" >"$ARTIFACTS/provider.log" 2>&1 &
PROVIDER_PID=$!
for attempt in {1..50}; do [[ ! -s "$ARTIFACTS/provider-port" ]] || break; sleep .1; done
PORT="$(cat "$ARTIFACTS/provider-port")"
[[ "$PORT" =~ ^[0-9]+$ ]]
mkdir -p "$ARTIFACTS/bin"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$FIXTURE_DB" | sudo mysql
cat >"$FIXTURE/.env" <<EOF
APP_NAME=PBRBusinessPlanFixture
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://thepbr.io
APP_MAINTENANCE_DRIVER=file
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=$FIXTURE_DB
DB_USERNAME=root
DB_PASSWORD=root
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=array
LOG_CHANNEL=single
PBR_AI_BASE_URL=http://127.0.0.1:$PORT
PBR_AI_INTERNAL_SECRET=synthetic-business-plan-fixture-secret
PBR_PARTNER_PORTAL_ENABLED=true
EOF
cp "$FIXTURE/.env" "$ARTIFACTS/original.env"
chmod 600 "$ARTIFACTS/original.env"
sudo chown www-data:www-data "$FIXTURE/.env"
sudo chmod 640 "$FIXTURE/.env"
sudo chown -R www-data:www-data "$FIXTURE/storage" "$FIXTURE/bootstrap/cache"
(cd "$FIXTURE" && sudo -u www-data php artisan migrate --force >"$ARTIFACTS/setup.log" 2>&1)
(cd "$FIXTURE" && sudo -u www-data php artisan filament:cache-components >>"$ARTIFACTS/setup.log" 2>&1)
sudo -u www-data mkdir -p "$FIXTURE/storage/app/pbr-private/workspaces/1"
printf 'Synthetic private original to retain.' | sudo -u www-data tee "$FIXTURE/storage/app/pbr-private/workspaces/1/original.txt" >/dev/null
sudo mysql --database="$FIXTURE_DB" --default-character-set=utf8mb4 <<'SQL'
INSERT INTO users(id,name,email,password,role,is_admin,account_status,created_at,updated_at) VALUES
(1,'Synthetic Admin','plan-admin@example.invalid','retain-admin-credential','admin',1,'active',NOW(),NOW()),
(2,'Synthetic Student','plan-student@example.invalid','retain-student-credential','student',0,'active',NOW(),NOW());
INSERT INTO partnership_workspaces(id,owner_user_id,name,business_name,status,created_at,updated_at) VALUES
(1,2,'Retain business','Retain business','active',NOW(),NOW()),(7,2,'Other private business','Other private business','active',NOW(),NOW());
INSERT INTO student_enrollments(user_id,status,started_at,access_expires_at,created_at,updated_at) VALUES (2,'active',NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW());
INSERT INTO articles(title,slug,category,excerpt,body,published_at,created_at,updated_at) VALUES ('Keep article','keep-plan-article','Agreement','Keep excerpt','မြန်မာ 🤝 body',NOW(),NOW(),NOW());
INSERT INTO videos(title,slug,youtube_id,published_at,created_at,updated_at) VALUES ('Keep video','keep-plan-video','dQw4w9WgXcQ',NOW(),NOW(),NOW());
INSERT INTO class_sessions(title,starts_on,location,enrolled,created_at,updated_at) VALUES ('Keep class','2026-11-01','Online',12,NOW(),NOW());
INSERT INTO portal_conversations(id,user_id,title,created_at,updated_at) VALUES (1,2,'Keep chat',NOW(),NOW());
INSERT INTO portal_messages(conversation_id,role,content,created_at,updated_at) VALUES (1,'user','Keep private conversation မြန်မာ 🤝',NOW(),NOW());
INSERT INTO portal_saved_items(user_id,kind,title,input,output,created_at,updated_at) VALUES (2,'capital','Keep scenario','{}','{}',NOW(),NOW());
INSERT INTO workspace_knowledge_sources(workspace_id,title,kind,business_status,processing_status,extracted_text,reviewed_text,created_at,updated_at) VALUES (1,'Keep private note','text','current','needs_review','Keep facts','Keep facts',NOW(),NOW());
CREATE TABLE fixture_keep (id BIGINT PRIMARY KEY, payload JSON, bytes LONGBLOB) ENGINE=InnoDB;
INSERT INTO fixture_keep VALUES (1,JSON_OBJECT('label','မြန်မာ 🤝'),0x000a22ff);
SQL
cat >"$ARTIFACTS/bin/curl" <<'SH'
#!/usr/bin/env bash
set -eu
url="" jar="" output="" format=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --config) [[ "$2" == - ]] || exit 2; config="$(cat)"; url="$(printf '%s\n' "$config" | sed -n 's/^url = "\(.*\)"$/\1/p')"; shift 2;;
        -c|--cookie-jar) jar="$2"; shift 2;;
        -o|--output) output="$2"; shift 2;;
        -w|--write-out) format="$2"; shift 2;;
        https://thepbr.io/*) url="$1"; shift;;
        *) shift;;
    esac
done
[[ -n "$url" && -f "$PBR_FIXTURE_APP/storage/framework/down" ]] || exit 2
if [[ "$url" =~ ^https://thepbr.io/[A-Za-z0-9]{16,128}$ ]]; then
    [[ -n "$jar" ]] || exit 2
    printf '# Netscape HTTP Cookie File\n#HttpOnly_thepbr.io\tFALSE\t/\tTRUE\t0\tlaravel_maintenance\tfixture\n' >"$jar"
    printf 302
    exit 0
fi
if [[ "$url" == https://thepbr.io/css/* ]]; then
    [[ -n "$output" ]]
    asset="${url#https://thepbr.io/css/}"; asset="${asset%%\?*}"
    [[ "$asset" == portal-business-plan.css || "$asset" == partner-chat.css ]]
    if [[ "$PBR_FIXTURE_PROBE_FAIL" == asset ]]; then printf 'Incorrect asset' >"$output"; else cp "$PBR_FIXTURE_APP/public/css/$asset" "$output"; fi
    exit 0
fi
code=200
if [[ "$url" == https://thepbr.io/portal\?* || "$url" == https://thepbr.io/portal/* || "$url" == https://thepbr.io/admin\?* ]]; then code=302; fi
if [[ "$url" == https://thepbr.io/articles\?* ]]; then
    if [[ "$PBR_FIXTURE_PROBE_FAIL" == changed ]]; then
        mysql --database="$PBR_FIXTURE_DB" --execute="INSERT INTO workspace_business_plans(workspace_id,draft,lock_version,created_at,updated_at) VALUES (7,JSON_OBJECT('business_name','Preserve unexpected new draft'),1,NOW(),NOW());"
        code=500
    elif [[ "$PBR_FIXTURE_PROBE_FAIL" == private ]]; then
        printf 'Preserve unexpected private original change.' >"$PBR_FIXTURE_APP/storage/app/pbr-private/workspaces/1/original.txt"
        code=500
    fi
fi
[[ -z "$format" ]] || printf '%s' "$code"
SH
chmod +x "$ARTIFACTS/bin/curl"
run_case() {
    local fault="$1" result=0
    set +e
    OUTPUT="$(sudo env GIT_CONFIG_COUNT=2 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" \
        GIT_CONFIG_KEY_1=safe.directory GIT_CONFIG_VALUE_1="$AI_FIXTURE" \
        PATH="$ARTIFACTS/bin:$PATH" PBR_FIXTURE_PROBE_FAIL="$fault" PBR_FIXTURE_APP="$FIXTURE" PBR_FIXTURE_DB="$FIXTURE_DB" \
        bash "$ROOT/scripts/pbr-business-plan-update.sh" "$TARGET" "$FIXTURE" "$AI_FIXTURE")"
    result=$?
    set -e
    printf '%s\n' "$OUTPUT"
    RECEIPT="$(printf '%s\n' "$OUTPUT" | sed -n 's/^BUSINESS_PLAN_RECEIPT_PATH=//p')"
    RECEIPTS+=("$RECEIPT")
    BACKUPS+=("$(printf '%s\n' "$OUTPUT" | sed -n 's/^FRESH_VERIFIED_BACKUP=//p')")
    if [[ -n "$RECEIPT" && "$result" != 0 && "$fault" == no ]]; then sudo tail -n 70 "$RECEIPT/private.log"; fi
    CASE_RESULT=$result
}
reset_code_to_baseline() {
    (cd "$FIXTURE" && sudo -u www-data php artisan filament:clear-cached-components >/dev/null)
    sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" checkout --quiet --detach "$BASE"
    (cd "$FIXTURE" && sudo env COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction >"$ARTIFACTS/reset.log" 2>&1)
    (cd "$FIXTURE" && sudo -u www-data php artisan config:clear >/dev/null && sudo -u www-data php artisan route:clear >/dev/null && sudo -u www-data php artisan view:clear >/dev/null)
    (cd "$FIXTURE" && sudo -u www-data php artisan filament:cache-components >/dev/null)
}
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-business-plan-release-check.php" snapshot "$FIXTURE" "$ARTIFACTS/before.json" "$ARTIFACTS/records-before.json"
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_BUSINESS_PLAN_UPDATE=COMPLETE* && "$OUTPUT" == *FILAMENT_ADMIN_AND_PUBLISHING_FORMS=OK* ]]
[[ "$OUTPUT" == *ALL_PREEXISTING_RECORDS_AND_SCHEMA=PRESERVED* && "$OUTPUT" == *ONLY_TWO_BUSINESS_PLAN_TABLES=YES* ]]
[[ "$OUTPUT" == *ISOLATED_MYSQL_BUSINESS_PLAN_MIGRATION=OK* && "$OUTPUT" == *REHEARSAL_ACCOUNT_ISOLATED=YES* ]]
[[ "$OUTPUT" == *OWNER_BUSINESS_PLAN_DRAFT_REVIEW_HISTORY_AND_OVERVIEW=OK* && "$OUTPUT" == *PRIVATE_ORIGINALS_AND_AI_SOURCE=PRESERVED* ]]
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-business-plan-release-check.php" verify "$FIXTURE" "$ARTIFACTS/before.json" "$ARTIFACTS/records-before.json"
sudo cmp -s "$ARTIFACTS/original.env" "$FIXTURE/.env"
echo 'Business Plan migration and real template render preserve the existing portal: PASS'
sudo mysql --database="$FIXTURE_DB" --default-character-set=utf8mb4 <<'SQL'
INSERT INTO workspace_business_plans(id,workspace_id,draft,lock_version,created_at,updated_at) VALUES (1,1,JSON_OBJECT('business_name','New real draft'),1,NOW(),NOW());
INSERT INTO workspace_business_plan_revisions(business_plan_id,revision,content,confirmed_by_user_id,created_at) VALUES (1,1,JSON_OBJECT('business_name','Retain confirmed plan','value_proposition','Retain offer','customer_segments','Retain audience'),2,NOW());
SQL
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-business-plan-release-check.php" snapshot "$FIXTURE" "$ARTIFACTS/current.json" "$ARTIFACTS/current-records.json"
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *ALREADY_AT_REVIEWED_VERSION=YES* ]]
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-business-plan-release-check.php" verify "$FIXTURE" "$ARTIFACTS/current.json" "$ARTIFACTS/current-records.json"
echo 'Repeated installation retains new business drafts and confirmed versions: PASS'
reset_code_to_baseline
run_case asset
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *BUSINESS_PLAN_CODE_ROLLBACK=OK* && "$OUTPUT" == *PBR_BUSINESS_PLAN_UPDATE=INCOMPLETE* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$BASE" ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-business-plan-release-check.php" verify "$FIXTURE" "$ARTIFACTS/current.json" "$ARTIFACTS/current-records.json"
echo 'Bad served CSS restores old code without rolling back any business data: PASS'
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_BUSINESS_PLAN_UPDATE=COMPLETE* ]]
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-business-plan-release-check.php" verify "$FIXTURE" "$ARTIFACTS/current.json" "$ARTIFACTS/current-records.json"
echo 'Retry after rollback reuses additive schema and retains business records: PASS'
reset_code_to_baseline
run_case private
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *BUSINESS_PLAN_CODE_ROLLBACK=NEEDS_REVIEW* && "$OUTPUT" == *CUTOVER_RECORDS_CHANGED=REVIEW_REQUIRED* ]]
sudo test -f "$FIXTURE/storage/framework/down"
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
sudo grep -qx 'Preserve unexpected private original change.' "$FIXTURE/storage/app/pbr-private/workspaces/1/original.txt"
echo 'An unexpected private-file change stays intact with maintenance held for review: PASS'
# Restore only this known synthetic fixture file to set up the next fault case.
printf 'Synthetic private original to retain.' | sudo -u www-data tee "$FIXTURE/storage/app/pbr-private/workspaces/1/original.txt" >/dev/null
(cd "$FIXTURE" && sudo -u www-data php artisan up >/dev/null)
reset_code_to_baseline
run_case changed
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *BUSINESS_PLAN_CODE_ROLLBACK=NEEDS_REVIEW* && "$OUTPUT" == *CUTOVER_RECORDS_CHANGED=REVIEW_REQUIRED* ]]
sudo test -f "$FIXTURE/storage/framework/down"
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
[[ "$(sudo mysql --database="$FIXTURE_DB" --batch --skip-column-names --execute='SELECT COUNT(*) FROM workspace_business_plans WHERE workspace_id=7;')" == 1 ]]
[[ "$OUTPUT" != *plan-admin* && "$OUTPUT" != *DB_PASSWORD* && "$OUTPUT" != *APP_KEY* ]]
echo 'Unexpected new plan rows are preserved and keep current code closed for review: PASS'
echo PBR_BUSINESS_PLAN_UPDATE_SMOKE=PASS
