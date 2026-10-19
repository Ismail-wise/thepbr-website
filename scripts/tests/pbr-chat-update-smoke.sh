#!/usr/bin/env bash
# Synthetic disposable Actions fixture only. Real backup/restore, code switch,
# data preservation, web-user render and rollback; public HTTPS probes doubled.
set -Eeuo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${CI:-}" == true && "$EUID" != 0 ]] || exit 2
umask 022
ROOT="$(pwd)"
# Keep future feature changes outside this reviewed deployment rehearsal.
TARGET="${PBR_CHAT_TEST_TARGET:-$(git rev-parse HEAD)}"
BASE=2567dc48a3d6d83565a7e078d1274cbf810a276d
FIXTURE="$(mktemp -d /tmp/pbr-chat-fixture-XXXXXXXX)"
ARTIFACTS="$(mktemp -d /tmp/pbr-chat-test-XXXXXXXX)"
FIXTURE_DB="pbr_chat_fixture_$(openssl rand -hex 8)"
AI_FIXTURE="$ARTIFACTS/ai"
AI_CACHE="$ARTIFACTS/ai-cache"
AI_BASE=a0701941890e57267d2c4ab3ad39e1f0f284ae84
AI_TARGET=48d978daeebb996f729cbf559914adc4c204381b
REAL_GIT="$(command -v git)"
REAL_PHP="$(command -v php)"
AI_FIXTURE_CREATED=0
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
    if [[ "$AI_FIXTURE_CREATED" == 1 ]]; then sudo rm -f /etc/pbr-ai-advisor.env /etc/systemd/system/pbr-ai-advisor.service /usr/local/bin/pbr-node; fi
    git worktree remove --force "$FIXTURE" >/dev/null 2>&1 || true
    sudo rm -rf -- "$FIXTURE" "$ARTIFACTS"
    exit "$result"
}
trap cleanup EXIT
for fixture_path in /etc/pbr-ai-advisor.env /etc/systemd/system/pbr-ai-advisor.service /usr/local/bin/pbr-node; do sudo test ! -e "$fixture_path"; done
sudo test ! -e /root/.my.cnf
printf '[client]\nuser=root\npassword=root\n' | sudo install -m 600 /dev/stdin /root/.my.cnf
ADMIN_CONFIG_CREATED=1
git worktree add --detach "$FIXTURE" "$BASE"
cp -a vendor "$FIXTURE/vendor"
(cd "$FIXTURE" && composer dump-autoload --no-scripts --no-interaction --optimize >/dev/null)
chmod -R a+rX "$FIXTURE"
mkdir -p "$ARTIFACTS/bin" "$AI_FIXTURE/.git" "$AI_CACHE"
printf '%s\n' "$AI_BASE" >"$ARTIFACTS/ai-head"
printf 'synthetic shared AI cache\n' >"$AI_CACHE/shared.json"
printf '/* synthetic AI process fixture */\n' >"$AI_FIXTURE/pbr-service.js"
printf '/* synthetic private module fixture */\n' >"$AI_FIXTURE/private-knowledge.js"
printf '/* synthetic bilingual fixture */\n' >"$AI_FIXTURE/chat-style.js"
printf '# synthetic environment, no credentials\n' | sudo install -m 600 /dev/stdin /etc/pbr-ai-advisor.env
printf '# synthetic service fixture\n' | sudo install -m 600 /dev/stdin /etc/systemd/system/pbr-ai-advisor.service
printf '#!/usr/bin/env bash\nexit 0\n' | sudo install -m 755 /dev/stdin /usr/local/bin/pbr-node
AI_FIXTURE_CREATED=1
chmod a+rx "$ARTIFACTS" "$ARTIFACTS/bin"
chmod -R a+rX "$AI_FIXTURE"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$FIXTURE_DB" | sudo mysql
cat >"$FIXTURE/.env" <<EOF
APP_NAME=PBRKnowledgeFixture
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
PBR_AI_INTERNAL_SECRET=synthetic-workspace-fixture-secret
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
INSERT INTO partnership_workspaces(id,owner_user_id,name,business_name,status,created_at,updated_at) VALUES (1,2,'Retain business','Retain business','active',NOW(),NOW());
INSERT INTO student_enrollments(user_id,status,started_at,access_expires_at,created_at,updated_at) VALUES (2,'active',NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW());
INSERT INTO articles(title,slug,category,excerpt,body,published_at,created_at,updated_at) VALUES ('Keep public article','keep-public-article','Agreement','Keep excerpt','မြန်မာ 🤝 body',NOW(),NOW(),NOW());
INSERT INTO videos(title,slug,youtube_id,published_at,created_at,updated_at) VALUES ('Keep public video','keep-public-video','dQw4w9WgXcQ',NOW(),NOW(),NOW());
INSERT INTO class_sessions(title,starts_on,location,enrolled,created_at,updated_at) VALUES ('Keep public class','2026-11-01','Online',12,NOW(),NOW());
INSERT INTO portal_conversations(id,user_id,title,created_at,updated_at) VALUES (1,2,'Keep private chat',NOW(),NOW());
INSERT INTO portal_messages(conversation_id,role,content,created_at,updated_at) VALUES (1,'user','Keep private conversation မြန်မာ 🤝',NOW(),NOW());
INSERT INTO portal_saved_items(user_id,kind,title,input,output,created_at,updated_at) VALUES (2,'capital','Keep scenario','{}','{}',NOW(),NOW());
CREATE TABLE fixture_keep (id BIGINT PRIMARY KEY, payload JSON, bytes LONGBLOB) ENGINE=InnoDB;
INSERT INTO fixture_keep VALUES (1,JSON_OBJECT('label','မြန်မာ 🤝'),0x000a22ff);
INSERT INTO workspace_knowledge_sources(id,workspace_id,title,kind,business_status,processing_status,reviewed_text,file_path,embedding_key,ready_at,created_at,updated_at) VALUES (8,1,'Existing reviewed note','text','current','ready','Private Burmese မြန်မာ source','workspaces/1/keep.txt','test:128:private-v1',NOW(),NOW(),NOW());
INSERT INTO workspace_knowledge_chunks(workspace_id,source_id,revision,content,embedding,embedding_key) VALUES (1,8,1,'Private source retained','[0.25,0.25]','test:128:private-v1');
INSERT INTO workspace_ai_conversations(id,workspace_id,user_id,title,knowledge_signature,created_at,updated_at) VALUES (8,1,2,'Existing Workspace chat',REPEAT('a',64),NOW(),NOW());
INSERT INTO workspace_ai_messages(conversation_id,role,content,created_at,updated_at) VALUES (8,'user','Retain existing private history',NOW(),NOW());
SQL
sudo install -d -o www-data -g www-data -m 700 "$FIXTURE/storage/app/pbr-private/workspaces/1"
printf 'Private original မြန်မာ \000\377' | sudo tee "$FIXTURE/storage/app/pbr-private/workspaces/1/keep.txt" >/dev/null
sudo chown www-data:www-data "$FIXTURE/storage/app/pbr-private/workspaces/1/keep.txt"
sudo sha256sum "$FIXTURE/storage/app/pbr-private/workspaces/1/keep.txt" >"$ARTIFACTS/private-original.sha256"
cat >"$ARTIFACTS/bin/curl" <<'SH'
#!/usr/bin/env bash
set -eu
url="" jar="" output=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --config) [[ "$2" == - ]] || exit 2; config="$(cat)"; url="$(printf '%s\n' "$config" | sed -n 's/^url = "\(.*\)"$/\1/p')"; shift 2;;
        -c|--cookie-jar) jar="$2"; shift 2;;
        -o|--output) output="$2"; shift 2;;
        https://thepbr.io/*|http://127.0.0.1:3107/*) url="$1"; shift;;
        *) shift;;
    esac
done
if [[ "$url" == http://127.0.0.1:3107/health ]]; then printf '%s' '{"service":"pbr-ai-advisor","knowledgeReady":true,"vectors":657,"privateKnowledgeProtocol":1,"chatInterfaceProtocol":1}'; exit 0; fi
[[ -n "$url" && -f "$PBR_FIXTURE_APP/storage/framework/down" ]] || exit 2
if [[ "$url" == https://thepbr.io/css/partner-chat.css* || "$url" == https://thepbr.io/js/partner-portal.js* ]]; then
    [[ -n "$output" ]] || exit 2
    asset="${url#https://thepbr.io/}";asset="${asset%%\?*}"
    cp "$PBR_FIXTURE_APP/public/$asset" "$output"
    if [[ "${PBR_FIXTURE_PROBE_FAIL:-}" == asset ]]; then printf 'broken CDN output' >"$output"; fi
    exit 0
fi
if [[ "$url" =~ ^https://thepbr.io/[A-Za-z0-9]{16,128}$ ]]; then
    [[ -n "$jar" ]] || exit 2
    printf '# Netscape HTTP Cookie File\n#HttpOnly_thepbr.io\tFALSE\t/\tTRUE\t0\tlaravel_maintenance\tfixture\n' >"$jar"
    printf 302
    exit 0
fi
code=200
if [[ "$url" == https://thepbr.io/portal\?* || "$url" == https://thepbr.io/portal/workspaces\?* || "$url" == https://thepbr.io/admin\?* ]]; then code=302; fi
if [[ "${PBR_FIXTURE_PROBE_FAIL:-}" != no && "$url" == https://thepbr.io/articles\?* ]]; then
    if [[ "$PBR_FIXTURE_PROBE_FAIL" == changed ]]; then mysql --database="$PBR_FIXTURE_DB" --execute='UPDATE fixture_keep SET bytes=0x001122ff WHERE id=1;'; fi
    code=500
fi
printf '%s' "$code"
SH
cat >"$ARTIFACTS/bin/git" <<'SH'
#!/usr/bin/env bash
set -eu
ai=0
for arg in "$@"; do
    if [[ "$arg" == "$PBR_FIXTURE_AI" || "$arg" == */source-ai ]]; then ai=1; fi
done
if [[ "$ai" == 1 ]]; then
    case " $* " in
        *' clone '*) dest="${@: -1}"; mkdir -p "$dest/.git" "$dest/tests"; printf '// synthetic AI source\n' >"$dest/pbr-service.js"; printf '// synthetic private source\n' >"$dest/private-knowledge.js"; printf '// synthetic chat source\n' >"$dest/chat-style.js"; exit 0;;
        *' rev-parse HEAD '*) cat "$PBR_FIXTURE_AI_HEAD"; exit 0;;
        *' checkout '*)
            for arg in "$@"; do
                if [[ "$arg" == "$PBR_FIXTURE_AI" ]]; then printf '%s\n' "${@: -1}" >"$PBR_FIXTURE_AI_HEAD"; fi
            done
            exit 0;;
        *' diff --name-only '*) printf '%s\n' pbr-service.js chat-style.js; exit 0;;
        *' status --porcelain '*|*' merge-base '*|*' fetch '*|*' branch '*) exit 0;;
    esac
    exit 2
fi
exec "$PBR_FIXTURE_REAL_GIT" "$@"
SH
cat >"$ARTIFACTS/bin/systemctl" <<'SH'
#!/usr/bin/env bash
set -eu
[[ "$*" == 'is-active --quiet pbr-ai-advisor.service' ]] && exit 0
if [[ "$*" == 'restart pbr-ai-advisor.service' ]]; then
    if [[ "${PBR_FIXTURE_PROBE_FAIL:-}" == ai && "$(cat "$PBR_FIXTURE_AI_HEAD")" == "$PBR_FIXTURE_AI_TARGET" ]]; then exit 2; fi
    exit 0
fi
exit 2
SH
cat >"$ARTIFACTS/bin/php" <<SH
#!/usr/bin/env bash
set -eu
for arg in "\$@"; do
    if [[ "\$arg" == ai-check || "\$arg" == language-check ]]; then echo CI_AI_GENERATION_AND_LANGUAGES=EXPLICIT_SYNTHETIC_DOUBLE; exit 0; fi
done
exec "$REAL_PHP" "\$@"
SH
chmod +x "$ARTIFACTS/bin/"*
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-knowledge-release-check.php" snapshot "$FIXTURE" "$ARTIFACTS/original-projection.json" "$ARTIFACTS/original-records.json"
run_case() {
    local fault="$1" result=0
    set +e
    OUTPUT="$(sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" \
        PATH="$ARTIFACTS/bin:$PATH" PBR_FIXTURE_AI="$AI_FIXTURE" PBR_FIXTURE_AI_HEAD="$ARTIFACTS/ai-head" PBR_FIXTURE_AI_TARGET="$AI_TARGET" PBR_FIXTURE_REAL_GIT="$REAL_GIT" PBR_FIXTURE_PROBE_FAIL="$fault" PBR_FIXTURE_APP="$FIXTURE" PBR_FIXTURE_DB="$FIXTURE_DB" bash "$ROOT/scripts/pbr-chat-update.sh" "$TARGET" "$FIXTURE" "$AI_FIXTURE" "$AI_CACHE")"
    result=$?
    set -e
    printf '%s\n' "$OUTPUT"
    RECEIPT="$(printf '%s\n' "$OUTPUT" | sed -n 's/^CHAT_RECEIPT_PATH=//p')"
    RECEIPTS+=("$RECEIPT")
    BACKUPS+=("$(printf '%s\n' "$OUTPUT" | sed -n 's/^FRESH_VERIFIED_BACKUP=//p')")
    if [[ -n "$RECEIPT" && "$result" != 0 && "$fault" == no ]]; then sudo tail -n 65 "$RECEIPT/private.log"; fi
    CASE_RESULT=$result
}
set +e
DEBUG_OUTPUT="$(sudo env -i PATH="$PATH" APP_DEBUG=true APP_CONFIG_CACHE="$ARTIFACTS/no-debug-cache.php" php "$ROOT/scripts/pbr-knowledge-release-check.php" config "$FIXTURE" 2>&1)"
DEBUG_RESULT=$?
set -e
[[ "$DEBUG_RESULT" != 0 && "$DEBUG_OUTPUT" == *PRIVATE_KNOWLEDGE_REQUIRES_DEBUG_OFF* ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
echo 'Private Knowledge requires debug off before maintenance or data changes: PASS'
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_CHAT_UPDATE=COMPLETE* && "$OUTPUT" == *FILAMENT_ADMIN_AND_PUBLISHING_FORMS=OK* ]]
[[ "$OUTPUT" == *ALL_PREEXISTING_RECORDS=PRESERVED* && "$OUTPUT" == *ONLY_REVIEWED_ADDITIVE_SCHEMA=YES* ]]
[[ "$OUTPUT" == *SCHEMA_AND_EXISTING_PRIVATE_RECORDS=UNCHANGED* && "$OUTPUT" == *MAINTENANCE_PROBES_BEFORE_REOPEN=YES* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-knowledge-release-check.php" verify "$FIXTURE" "$ARTIFACTS/original-projection.json" "$ARTIFACTS/original-records.json"
sudo cmp -s "$ARTIFACTS/original.env" "$FIXTURE/.env"
echo 'Chat interface release keeps the existing schema and all records: PASS'
sudo mysql --database="$FIXTURE_DB" --default-character-set=utf8mb4 <<'SQL'
INSERT INTO users(id,name,email,password,role,is_admin,account_status,created_at,updated_at) VALUES (3,'New real member','new-member@example.invalid','retain-new-credential','public',0,'active',NOW(),NOW());
INSERT INTO partnership_workspaces(id,owner_user_id,name,business_name,description,status,created_at,updated_at) VALUES (7,2,'New real Workspace','New real Workspace','Retain new private description မြန်မာ','active',NOW(),NOW());
INSERT INTO workspace_knowledge_sources(id,workspace_id,title,kind,business_status,processing_status,reviewed_text,created_at,updated_at) VALUES (1,7,'Keep private knowledge','text','current','needs_review','Retain confidential မြန်မာ',NOW(),NOW());
INSERT INTO workspace_ai_conversations(id,workspace_id,user_id,title,knowledge_signature,created_at,updated_at) VALUES (1,7,2,'Keep private Workspace chat',REPEAT('a',64),NOW(),NOW());
INSERT INTO workspace_ai_messages(conversation_id,role,content,created_at,updated_at) VALUES (1,'user','Retain private conversation',NOW(),NOW());
SQL
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-knowledge-release-check.php" snapshot "$FIXTURE" "$ARTIFACTS/current-projection.json" "$ARTIFACTS/current-records.json"
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *ALREADY_AT_REVIEWED_VERSION=YES* ]]
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-knowledge-release-check.php" verify "$FIXTURE" "$ARTIFACTS/current-projection.json" "$ARTIFACTS/current-records.json"
echo 'Repeated interface update retains accounts and private descriptions created after deployment: PASS'
(cd "$FIXTURE" && sudo -u www-data php artisan filament:clear-cached-components >/dev/null)
sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" checkout --quiet --detach "$BASE"
(cd "$FIXTURE" && sudo env COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction >"$ARTIFACTS/reset.log" 2>&1)
(cd "$FIXTURE" && sudo -u www-data php artisan config:clear >/dev/null && sudo -u www-data php artisan route:clear >/dev/null && sudo -u www-data php artisan view:clear >/dev/null)
(cd "$FIXTURE" && sudo -u www-data php artisan filament:cache-components >/dev/null)
printf '%s\n' "$AI_BASE" >"$ARTIFACTS/ai-head"
run_case ai
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *CHAT_CODE_ROLLBACK=OK* ]]
[[ "$(cat "$ARTIFACTS/ai-head")" == "$AI_BASE" ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
echo 'AI restart failure rolls both repositories back without restoring a database: PASS'
run_case asset
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *LIVE_CHAT_ASSET_MISMATCH* && "$OUTPUT" == *CHAT_CODE_ROLLBACK=OK* ]]
echo 'A stale or incorrect CDN asset triggers paired code rollback: PASS'
run_case yes
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *CHAT_CODE_ROLLBACK=OK* && "$OUTPUT" == *PBR_CHAT_UPDATE=INCOMPLETE* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$BASE" ]]
sudo test ! -e "$FIXTURE/storage/framework/down"
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-knowledge-release-check.php" verify "$FIXTURE" "$ARTIFACTS/current-projection.json" "$ARTIFACTS/current-records.json"
sudo cmp -s "$ARTIFACTS/original.env" "$FIXTURE/.env"
[[ "$OUTPUT" != *fixture-admin* && "$OUTPUT" != *DB_PASSWORD* && "$OUTPUT" != *APP_KEY* ]]
echo 'Failed public probe restores the private Knowledge baseline and keeps all data: PASS'
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_CHAT_UPDATE=COMPLETE* ]]
sudo env -i PATH="$PATH" php "$ROOT/scripts/pbr-knowledge-release-check.php" verify "$FIXTURE" "$ARTIFACTS/current-projection.json" "$ARTIFACTS/current-records.json"
echo 'Retry after rollback safely retains schema and private data: PASS'
(cd "$FIXTURE" && sudo -u www-data php artisan filament:clear-cached-components >/dev/null)
sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" checkout --quiet --detach "$BASE"
(cd "$FIXTURE" && sudo env COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-scripts --no-plugins --optimize --no-interaction >"$ARTIFACTS/reset.log" 2>&1)
(cd "$FIXTURE" && sudo -u www-data php artisan config:clear >/dev/null && sudo -u www-data php artisan route:clear >/dev/null && sudo -u www-data php artisan view:clear >/dev/null)
(cd "$FIXTURE" && sudo -u www-data php artisan filament:cache-components >/dev/null)
printf '%s\n' "$AI_BASE" >"$ARTIFACTS/ai-head"
run_case changed
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *CHAT_CODE_ROLLBACK=NEEDS_REVIEW* && "$OUTPUT" == *CUTOVER_RECORDS_CHANGED=REVIEW_REQUIRED* ]]
sudo test -f "$FIXTURE/storage/framework/down"
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
[[ "$(sudo mysql --database="$FIXTURE_DB" --batch --skip-column-names --execute='SELECT HEX(bytes) FROM fixture_keep WHERE id=1;')" == 001122FF ]]
echo 'Unexpected cutover writes stay intact and keep maintenance/new access rules closed for review: PASS'
sudo sha256sum --check --status "$ARTIFACTS/private-original.sha256"
echo PBR_CHAT_UPDATE_SMOKE=PASS
