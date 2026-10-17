#!/usr/bin/env bash
# Synthetic disposable Actions fixture only. MySQL, cascades, backup/restore,
# code switch, renderer, transactions and repeat protection all run for real.
# Public HTTPS probes and one intentionally corrupted plan use test doubles.
set -Eeuo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${CI:-}" == true && "$EUID" != 0 ]] || exit 2
umask 022
ROOT="$(pwd)"
TARGET="$(git rev-parse HEAD)"
BASE=ca1595f8f758d4e1d3376b19a3639b3c0629197f
FIXTURE="$(mktemp -d /tmp/pbr-reset-fixture-XXXXXXXX)"
ARTIFACTS="$(mktemp -d /tmp/pbr-reset-test-XXXXXXXX)"
FIXTURE_DB="pbr_reset_fixture_$(openssl rand -hex 8)"
JOURNAL=""
ADMIN_CONFIG_CREATED=0
RECEIPTS=()
BACKUPS=()
cleanup() {
    local result=$?
    trap - EXIT
    set +e
    if [[ "$ADMIN_CONFIG_CREATED" == 1 ]]; then
        printf 'DROP DATABASE IF EXISTS `%s`;\n' "$FIXTURE_DB" | sudo mysql
        sudo rm -f /root/.my.cnf
    fi
    if [[ "$JOURNAL" == /root/thepbr-deploy-backups/.portal-test-reset-* ]]; then sudo rm -f -- "$JOURNAL"; fi
    for path in "${RECEIPTS[@]}" "${BACKUPS[@]}"; do
        if [[ "$path" == /root/thepbr-deploy-backups/* ]]; then sudo rm -rf -- "$path"; fi
    done
    git worktree remove --force "$FIXTURE" >/dev/null 2>&1 || true
    sudo rm -rf -- "$FIXTURE" "$ARTIFACTS"
    exit "$result"
}
trap cleanup EXIT
sudo test ! -e /root/.my.cnf
printf '[client]\nuser=root\npassword=root\n' | sudo install -m 600 /dev/stdin /root/.my.cnf
ADMIN_CONFIG_CREATED=1
git worktree add --detach "$FIXTURE" "$BASE"
cp -a vendor "$FIXTURE/vendor"
(cd "$FIXTURE" && composer dump-autoload --no-scripts --no-interaction --optimize >/dev/null)
chmod -R a+rX "$FIXTURE"
mkdir -p "$ARTIFACTS/bin"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$FIXTURE_DB" | sudo mysql
cat >"$FIXTURE/.env" <<EOF
APP_NAME=PBRResetFixture
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
PBR_AI_BASE_URL=http://127.0.0.1:9
PBR_AI_INTERNAL_SECRET=synthetic-reset-fixture-secret
PBR_PARTNER_PORTAL_ENABLED=true
EOF
cp "$FIXTURE/.env" "$ARTIFACTS/original.env"
chmod 600 "$ARTIFACTS/original.env"
sudo chown www-data:www-data "$FIXTURE/.env"
sudo chmod 640 "$FIXTURE/.env"
sudo chown -R www-data:www-data "$FIXTURE/storage" "$FIXTURE/bootstrap/cache"
(cd "$FIXTURE" && sudo -u www-data php artisan migrate --force >"$ARTIFACTS/setup.log" 2>&1)
(cd "$FIXTURE" && sudo -u www-data php artisan filament:cache-components >>"$ARTIFACTS/setup.log" 2>&1)
sudo mysql --database="$FIXTURE_DB" --default-character-set=utf8mb4 <<'SQL'
INSERT INTO users(id,name,email,password,role,is_admin,account_status,remember_token,created_at,updated_at) VALUES
(1,'Synthetic Admin','fixture-admin@example.invalid','retain-this-credential','admin',1,'active','retain-remember-token',NOW(),NOW()),
(2,'Inactive Admin','inactive-admin@example.invalid','retain-inactive-credential','public',1,'inactive',NULL,NOW(),NOW()),
(3,'Synthetic Student','fixture-student@example.invalid','synthetic','student',0,'active',NULL,NOW(),NOW()),
(4,'Synthetic Pending','fixture-pending@example.invalid','synthetic','public',0,'active',NULL,NOW(),NOW());
INSERT INTO articles(title,slug,category,excerpt,body,published_at,created_at,updated_at)
VALUES ('Keep public article','keep-public-article','Agreement','Keep excerpt','မြန်မာ 🤝 body',NOW(),NOW(),NOW());
INSERT INTO videos(title,slug,youtube_id,published_at,created_at,updated_at)
VALUES ('Keep public video','keep-public-video','dQw4w9WgXcQ',NOW(),NOW(),NOW());
INSERT INTO class_sessions(title,starts_on,location,enrolled,created_at,updated_at) VALUES ('Keep public class','2026-11-01','Online',12,NOW(),NOW());
INSERT INTO course_chapters(id,chapter_number,slug,phase,title_en,title_mm) VALUES (1,1,'keep-chapter','foundation','Keep chapter','သင်ခန်းစာ');
INSERT INTO chapter_tools(id,course_chapter_id,tool_key,slug,title_en,tool_type) VALUES (1,1,'keep-tool','keep-tool','Keep tool','calculator');
INSERT INTO partnership_workspaces(id,owner_user_id,name) VALUES (1,1,'Admin test workspace'),(2,3,'Student test workspace');
INSERT INTO workspace_members(workspace_id,user_id,member_role,invitation_status) VALUES (1,1,'owner','accepted'),(2,3,'owner','accepted');
INSERT INTO tool_sessions(user_id,chapter_tool_id,workspace_id,input_data) VALUES (1,1,1,'{}'),(3,1,2,'{}');
INSERT INTO business_feasibility_assessments(workspace_id,user_id,inputs,result) VALUES (1,1,'{}','{}');
INSERT INTO business_valuations(workspace_id,user_id,inputs,result) VALUES (2,3,'{}','{}');
INSERT INTO workspace_partner_profiles(workspace_id,user_id,partner_key,display_name) VALUES (1,1,'00000000-0000-0000-0000-000000000001','Synthetic owner');
INSERT INTO workspace_operating_snapshots(workspace_id,domain_key,payload) VALUES (1,'capital','{}');
INSERT INTO workspace_operating_records(workspace_id,user_id,record_type,data) VALUES (1,1,'capital','{}');
INSERT INTO ai_conversations(id,workspace_id,user_id,title) VALUES (1,1,1,'Admin workspace chat'),(2,2,3,'Student workspace chat');
INSERT INTO ai_messages(conversation_id,role,content) VALUES (1,'user','Workspace test history'),(2,'assistant','Workspace test reply');
INSERT INTO portal_conversations(id,user_id,title) VALUES (1,1,'Admin personal chat'),(2,3,'Student chat');
INSERT INTO portal_messages(conversation_id,role,content) VALUES (1,'user','Keep Admin personal history'),(2,'user','Remove student history');
INSERT INTO portal_saved_items(user_id,kind,title,input,output,created_at,updated_at) VALUES (1,'capital','Admin personal save','{}','{}',NOW(),NOW()),(3,'capital','Student save','{}','{}',NOW(),NOW());
INSERT INTO portal_access_events(user_id,actor_id,action,before_state,after_state,created_at) VALUES (1,3,'synthetic_old_event','{}','{}',NOW());
INSERT INTO student_access_codes(code_hash,code_encrypted,code_last4,created_by_user_id,used_by_user_id) VALUES (REPEAT('a',64),'synthetic','TEST',1,3);
INSERT INTO student_enrollments(user_id,status,student_access_code_id) VALUES (3,'active',1);
INSERT INTO partner_dynamics_assessments(user_id) VALUES (1),(3);
INSERT INTO sessions(id,user_id,payload,last_activity) VALUES ('admin-session',1,'synthetic',1),('student-session',3,'synthetic',1);
INSERT INTO password_reset_tokens(email,token) VALUES ('fixture-admin@example.invalid','synthetic'),('fixture-student@example.invalid','synthetic');
CREATE TABLE fixture_keep (id BIGINT PRIMARY KEY, payload JSON, bytes LONGBLOB) ENGINE=InnoDB;
INSERT INTO fixture_keep VALUES (1,JSON_OBJECT('label','မြန်မာ 🤝'),0x000a22ff);
SQL
JOURNAL="/root/thepbr-deploy-backups/.portal-test-reset-$(php -r 'echo hash("sha256",$argv[1]."\n".$argv[2]."\n".$argv[3]);' "$FIXTURE" "$FIXTURE_DB" "$BASE").json"

# One deliberate wrong postcondition exercises an actual MySQL transaction
# rollback after the DELETE/cascades, with no production fault switches.
{
    printf '#!/usr/bin/env bash\nset -euo pipefail\nREAL_COMPOSER=%q\nFIXTURE=%q\nFAULT=%q\n' "$(command -v composer)" "$FIXTURE" "$ARTIFACTS/corrupt-plan"
    cat <<'SH'
"$REAL_COMPOSER" "$@"
if [[ "$PWD" == "$FIXTURE" && -f "$FAULT" ]]; then
    rm -f -- "$FAULT"
    plan="$(find /root/thepbr-deploy-backups -mindepth 2 -maxdepth 2 -name reset-plan.json -printf '%T@ %p\n' | sort -nr | head -n 1 | cut -d' ' -f2-)"
    php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $v["after_fingerprint"]=str_repeat("0",64); file_put_contents($argv[1],json_encode($v,JSON_THROW_ON_ERROR));' "$plan"
fi
SH
} >"$ARTIFACTS/bin/composer"
cat >"$ARTIFACTS/bin/curl" <<'SH'
#!/usr/bin/env bash
set -eu
url=""
for argument in "$@"; do if [[ "$argument" == https://thepbr.io/* ]]; then url="$argument"; fi; done
[[ -n "$url" ]] || exit 2
code=200
if [[ "$url" == https://thepbr.io/portal\?* || "$url" == https://thepbr.io/admin\?* ]]; then code=302; fi
if [[ "${PBR_FIXTURE_PROBE_FAIL:-}" == yes && "$url" == https://thepbr.io/articles\?* ]]; then code=500; fi
printf '%s' "$code"
SH
chmod +x "$ARTIFACTS/bin/composer" "$ARTIFACTS/bin/curl"
DUMP=(sudo mysqldump --user=root --single-transaction --skip-comments --skip-triggers --hex-blob --no-tablespaces --order-by-primary)
DUMP_HELP="$(mysqldump --help)"
if [[ "$DUMP_HELP" == *column-statistics* ]]; then DUMP+=(--column-statistics=0); fi
if [[ "$DUMP_HELP" == *set-gtid-purged* ]]; then DUMP+=(--set-gtid-purged=OFF); fi
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/before.sql"
"${DUMP[@]}" "$FIXTURE_DB" articles videos class_sessions course_chapters chapter_tools migrations fixture_keep >"$ARTIFACTS/public-before.sql"
"${DUMP[@]}" --where='is_admin=1 OR role="admin"' "$FIXTURE_DB" users >"$ARTIFACTS/admin-before.sql"
run_case() {
    local fault="$1" result=0
    set +e
    OUTPUT="$(sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" \
        PATH="$ARTIFACTS/bin:$PATH" PBR_FIXTURE_PROBE_FAIL="$fault" bash "$ROOT/scripts/pbr-portal-reset.sh" "$TARGET" "$FIXTURE")"
    result=$?
    set -e
    printf '%s\n' "$OUTPUT"
    RECEIPT="$(printf '%s\n' "$OUTPUT" | sed -n 's/^PORTAL_RESET_RECEIPT_PATH=//p')"
    RECEIPTS+=("$RECEIPT")
    BACKUPS+=("$(printf '%s\n' "$OUTPUT" | sed -n 's/^FRESH_VERIFIED_BACKUP=//p')")
    if [[ -n "$RECEIPT" && "$result" != 0 ]]; then sudo tail -n 65 "$RECEIPT/private.log"; fi
    CASE_RESULT=$result
}
touch "$ARTIFACTS/corrupt-plan"
run_case no
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *DATABASE_RESET_ROLLED_BACK=YES* && "$OUTPUT" == *PORTAL_RESET_CODE_ROLLBACK=OK* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$BASE" ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
sudo test ! -e "$JOURNAL"
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/rolled-back.sql"
cmp -s "$ARTIFACTS/before.sql" "$ARTIFACTS/rolled-back.sql"
echo 'MySQL failed postcondition: all original rows, credentials and code rolled back: PASS'

# A public probe failure after commit must leave the target up and must never
# restore the old database. The next run verifies, without deleting new users.
run_case yes
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *DATABASE_RESET_COMMITTED=YES* && "$OUTPUT" == *ISOLATED_MYSQL_RESET=OK* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
[[ "$(sudo mysql -N --database="$FIXTURE_DB" --execute='SELECT COUNT(*) FROM users;')" == 2 ]]
for table in partnership_workspaces workspace_members tool_sessions ai_conversations ai_messages student_enrollments student_access_codes portal_access_events business_feasibility_assessments business_valuations workspace_partner_profiles workspace_operating_snapshots workspace_operating_records; do
    [[ "$(sudo mysql -N --database="$FIXTURE_DB" --execute="SELECT COUNT(*) FROM $table;")" == 0 ]]
done
for table in portal_conversations portal_messages portal_saved_items partner_dynamics_assessments sessions password_reset_tokens; do
    [[ "$(sudo mysql -N --database="$FIXTURE_DB" --execute="SELECT COUNT(*) FROM $table;")" == 1 ]]
done
"${DUMP[@]}" "$FIXTURE_DB" articles videos class_sessions course_chapters chapter_tools migrations fixture_keep >"$ARTIFACTS/public-after.sql"
"${DUMP[@]}" --where='is_admin=1 OR role="admin"' "$FIXTURE_DB" users >"$ARTIFACTS/admin-after.sql"
cmp -s "$ARTIFACTS/public-before.sql" "$ARTIFACTS/public-after.sql"
cmp -s "$ARTIFACTS/admin-before.sql" "$ARTIFACTS/admin-after.sql"
sudo cmp -s "$ARTIFACTS/original.env" "$FIXTURE/.env"
echo 'Committed MySQL cleanup preserves Admins, credentials, public content and Admin personal history: PASS'
sudo mysql --database="$FIXTURE_DB" --execute="INSERT INTO users(name,email,password,role,is_admin,account_status) VALUES ('New real member','new-real-member@example.invalid','synthetic','public',0,'active');"
[[ "$(sudo mysql -N --database="$FIXTURE_DB" --execute="SELECT id > 4 FROM users WHERE email='new-real-member@example.invalid';")" == 1 ]]
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/repeat-before.sql"
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_PORTAL_TEST_RESET=COMPLETE* && "$OUTPUT" == *ALREADY_COMPLETED=YES* && "$OUTPUT" == *PUBLIC_WEBSITE_AND_ADMIN_HTTPS=OK* ]]
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/repeat-after.sql"
cmp -s "$ARTIFACTS/repeat-before.sql" "$ARTIFACTS/repeat-after.sql"
[[ "$OUTPUT" != *fixture-admin* && "$OUTPUT" != *DB_PASSWORD* && "$OUTPUT" != *APP_KEY* ]]
echo 'Repeated cleanup preserves new member and IDs, verifies target, and never repeats deletion: PASS'
echo PBR_PORTAL_RESET_SMOKE=PASS
