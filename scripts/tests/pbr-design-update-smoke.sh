#!/usr/bin/env bash
# Synthetic disposable Actions fixture only. Real backup/restore, code switch,
# data preservation, web-user render and rollback; public HTTPS probes doubled.
set -Eeuo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${CI:-}" == true && "$EUID" != 0 ]] || exit 2
umask 022
ROOT="$(pwd)"
TARGET="$(git rev-parse HEAD)"
BASE=93da710fa086bf5ae9320250da0011683afd2520
FIXTURE="$(mktemp -d /tmp/pbr-design-fixture-XXXXXXXX)"
ARTIFACTS="$(mktemp -d /tmp/pbr-design-test-XXXXXXXX)"
FIXTURE_DB="pbr_design_fixture_$(openssl rand -hex 8)"
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
APP_NAME=PBRDesignFixture
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
PBR_AI_INTERNAL_SECRET=synthetic-design-fixture-secret
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
INSERT INTO users(id,name,email,password,role,is_admin,account_status,created_at,updated_at) VALUES
(1,'Synthetic Admin','fixture-admin@example.invalid','retain-admin-credential','admin',1,'active',NOW(),NOW()),
(2,'Synthetic Student','fixture-student@example.invalid','retain-student-credential','student',0,'active',NOW(),NOW());
INSERT INTO student_enrollments(user_id,status,started_at,access_expires_at,created_at,updated_at) VALUES (2,'active',NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW());
INSERT INTO articles(title,slug,category,excerpt,body,published_at,created_at,updated_at) VALUES ('Keep public article','keep-public-article','Agreement','Keep excerpt','မြန်မာ 🤝 body',NOW(),NOW(),NOW());
INSERT INTO videos(title,slug,youtube_id,published_at,created_at,updated_at) VALUES ('Keep public video','keep-public-video','dQw4w9WgXcQ',NOW(),NOW(),NOW());
INSERT INTO class_sessions(title,starts_on,location,enrolled,created_at,updated_at) VALUES ('Keep public class','2026-11-01','Online',12,NOW(),NOW());
INSERT INTO portal_conversations(id,user_id,title,created_at,updated_at) VALUES (1,2,'Keep private chat',NOW(),NOW());
INSERT INTO portal_messages(conversation_id,role,content,created_at,updated_at) VALUES (1,'user','Keep private conversation မြန်မာ 🤝',NOW(),NOW());
INSERT INTO portal_saved_items(user_id,kind,title,input,output,created_at,updated_at) VALUES (2,'capital','Keep scenario','{}','{}',NOW(),NOW());
CREATE TABLE fixture_keep (id BIGINT PRIMARY KEY, payload JSON, bytes LONGBLOB) ENGINE=InnoDB;
INSERT INTO fixture_keep VALUES (1,JSON_OBJECT('label','မြန်မာ 🤝'),0x000a22ff);
SQL
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
chmod +x "$ARTIFACTS/bin/curl"
DUMP=(sudo mysqldump --user=root --single-transaction --skip-comments --skip-triggers --hex-blob --no-tablespaces --order-by-primary)
DUMP_HELP="$(mysqldump --help)"
if [[ "$DUMP_HELP" == *column-statistics* ]]; then DUMP+=(--column-statistics=0); fi
if [[ "$DUMP_HELP" == *set-gtid-purged* ]]; then DUMP+=(--set-gtid-purged=OFF); fi
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/before.sql"
run_case() {
    local fault="$1" result=0
    set +e
    OUTPUT="$(sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" \
        PATH="$ARTIFACTS/bin:$PATH" PBR_FIXTURE_PROBE_FAIL="$fault" bash "$ROOT/scripts/pbr-design-update.sh" "$TARGET" "$FIXTURE")"
    result=$?
    set -e
    printf '%s\n' "$OUTPUT"
    RECEIPT="$(printf '%s\n' "$OUTPUT" | sed -n 's/^PREMIUM_RECEIPT_PATH=//p')"
    RECEIPTS+=("$RECEIPT")
    BACKUPS+=("$(printf '%s\n' "$OUTPUT" | sed -n 's/^FRESH_VERIFIED_BACKUP=//p')")
    if [[ -n "$RECEIPT" && "$result" != 0 && "$fault" == no ]]; then sudo tail -n 65 "$RECEIPT/private.log"; fi
    CASE_RESULT=$result
}
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_PREMIUM_UPDATE=COMPLETE* && "$OUTPUT" == *FILAMENT_ADMIN_AND_PUBLISHING_FORMS=OK* ]]
[[ "$OUTPUT" == *EXISTING_CONTENT_ACCOUNTS_CHATS_AND_ACCESS=PRESERVED* && "$OUTPUT" == *SCHEMA_AND_ENVIRONMENT=UNCHANGED* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/after.sql"
cmp -s "$ARTIFACTS/before.sql" "$ARTIFACTS/after.sql"
sudo cmp -s "$ARTIFACTS/original.env" "$FIXTURE/.env"
sudo test -f "$FIXTURE/public/css/pbr-experience.css"
echo 'Premium release renders native Filament/forms and preserves every database row: PASS'
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *ALREADY_AT_REVIEWED_VERSION=YES* ]]
echo 'Repeated premium update verifies runtime without another cutover: PASS'
(cd "$FIXTURE" && sudo -u www-data php artisan filament:clear-cached-components >/dev/null)
sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" checkout --quiet --detach "$BASE"
(cd "$FIXTURE" && sudo env COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction >"$ARTIFACTS/reset.log" 2>&1)
(cd "$FIXTURE" && sudo -u www-data php artisan config:clear >/dev/null && sudo -u www-data php artisan route:clear >/dev/null && sudo -u www-data php artisan view:clear >/dev/null)
(cd "$FIXTURE" && sudo -u www-data php artisan filament:cache-components >/dev/null)
run_case yes
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *PREMIUM_CODE_ROLLBACK=OK* && "$OUTPUT" == *PBR_PREMIUM_UPDATE=INCOMPLETE* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$BASE" ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
"${DUMP[@]}" "$FIXTURE_DB" >"$ARTIFACTS/rollback.sql"
cmp -s "$ARTIFACTS/before.sql" "$ARTIFACTS/rollback.sql"
sudo cmp -s "$ARTIFACTS/original.env" "$FIXTURE/.env"
[[ "$OUTPUT" != *fixture-admin* && "$OUTPUT" != *DB_PASSWORD* && "$OUTPUT" != *APP_KEY* ]]
echo 'Failed premium public probe restores original code/configuration with all records intact: PASS'
echo PBR_PREMIUM_UPDATE_SMOKE=PASS
