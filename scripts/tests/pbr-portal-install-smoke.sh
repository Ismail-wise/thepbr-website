#!/usr/bin/env bash
# Disposable CI only. Real Laravel/MySQL/filesystem operations; the private AI
# protocol and public DNS/HTTPS front door use explicit synthetic test doubles.
set -Eeuo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${CI:-}" == true && "$EUID" != 0 ]] || exit 2
umask 022
ROOT="$(pwd)"
TARGET="$(git rev-parse HEAD)"
FIXTURE="$(mktemp -d /tmp/pbr-install-fixture-XXXXXXXX)"
ARTIFACTS="$(mktemp -d /tmp/pbr-install-test-XXXXXXXX)"
FIXTURE_DB="pbr_install_fixture_$(openssl rand -hex 8)"
ADMIN_CONFIG_CREATED=0
AI_CHECKOUT_CREATED=0
AI_PID=""
RECEIPTS=()
BACKUPS=()

cleanup() {
    local result=$?
    trap - EXIT
    set +e
    if [[ -n "$AI_PID" ]]; then kill "$AI_PID"; wait "$AI_PID" 2>/dev/null; fi
    if [[ "$ADMIN_CONFIG_CREATED" == 1 ]]; then
        printf 'DROP DATABASE IF EXISTS `%s`;\n' "$FIXTURE_DB" | sudo mysql
        sudo rm -f /root/.my.cnf
    fi
    if [[ "$AI_CHECKOUT_CREATED" == 1 ]]; then sudo rm -rf /opt/pbr-partner-ai; fi
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
git worktree add --detach "$FIXTURE" b71122dee5f0585807223b94b33211e1399df4dd
cp -a vendor "$FIXTURE/vendor"
(cd "$FIXTURE" && composer dump-autoload --no-scripts --no-interaction --optimize >/dev/null)
chmod -R a+rX "$FIXTURE"
mkdir -p "$ARTIFACTS/bin"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$FIXTURE_DB" | sudo mysql

cat >"$ARTIFACTS/ai-fixture.cjs" <<'JS'
const fs = require('fs');
const http = require('http');
const server = http.createServer((req, res) => {
  if (req.url === '/health') {
    res.setHeader('content-type', 'application/json');
    return res.end(JSON.stringify({ok:true, service:'pbr-ai-advisor', knowledgeReady:true, vectors:657}));
  }
  if (req.url !== '/internal/pbr/chat' || req.headers['x-pbr-internal-secret'] !== process.env.FIXTURE_AI_SECRET) {
    res.statusCode = 403; return res.end('{}');
  }
  let body = '';
  req.on('data', value => body += value);
  req.on('end', () => {
    const data = JSON.parse(body);
    if (!data.message) {res.statusCode=422; return res.end('{}');}
    res.setHeader('content-type', 'text/event-stream');
    const fault = fs.readFileSync(process.argv[3], 'utf8').trim();
    const events = [{type:'meta',mode:fault==='general'?'general':'rag'}, {type:'delta',text:'စမ်းသပ်မှုအတွက်သာ synthetic AI fixture 🤝'}];
    if (fault !== 'incomplete') events.push({type:'done'});
    res.end(events.map(event => 'data: '+JSON.stringify(event)+'\r\n\r\n').join(''));
  });
});
server.listen(0, '127.0.0.1', () => fs.writeFileSync(process.argv[2], String(server.address().port)));
JS
printf normal >"$ARTIFACTS/ai-mode"
FIXTURE_SECRET="$(openssl rand -hex 32)"
FIXTURE_AI_SECRET="$FIXTURE_SECRET" node "$ARTIFACTS/ai-fixture.cjs" "$ARTIFACTS/ai-port" "$ARTIFACTS/ai-mode" >"$ARTIFACTS/ai.log" 2>&1 &
AI_PID=$!
for attempt in {1..100}; do [[ -s "$ARTIFACTS/ai-port" ]] && break; sleep 0.1; done
[[ -s "$ARTIFACTS/ai-port" ]]
AI_PORT="$(cat "$ARTIFACTS/ai-port")"
cat >"$FIXTURE/.env" <<EOF
APP_NAME=PBRInstallerFixture
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
PBR_AI_BASE_URL=http://127.0.0.1:$AI_PORT
PBR_AI_INTERNAL_SECRET=$FIXTURE_SECRET
PBR_PARTNER_PORTAL_ENABLED=false
EOF
cp "$FIXTURE/.env" "$ARTIFACTS/initial.env"
chmod 600 "$ARTIFACTS/initial.env"
sudo chown www-data:www-data "$FIXTURE/.env"
sudo chmod 640 "$FIXTURE/.env"
sudo chown -R www-data:www-data "$FIXTURE/storage" "$FIXTURE/bootstrap/cache"
(cd "$FIXTURE" && sudo -u www-data php artisan migrate --force >"$ARTIFACTS/setup.log" 2>&1)
sudo mysql --database="$FIXTURE_DB" --default-character-set=utf8mb4 <<'SQL'
INSERT INTO users(name,email,password,role,is_admin,account_status,created_at,updated_at) VALUES
('Synthetic Admin','fixture-admin@example.invalid','never-used-to-login','admin',1,'active',NOW(),NOW()),
('Synthetic Student','fixture-student@example.invalid','never-used-to-login','student',0,'active',NOW(),NOW());
INSERT INTO student_enrollments(user_id,status,started_at,access_expires_at,created_at,updated_at) VALUES (2,'active',NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW());
CREATE TABLE fixture_keep (id BIGINT PRIMARY KEY, payload JSON, bytes LONGBLOB) ENGINE=InnoDB;
INSERT INTO fixture_keep VALUES (1,JSON_OBJECT('label','မြန်မာ 🤝'),0x000a22ff);
SQL
sudo test ! -e /opt/pbr-partner-ai
sudo git clone --quiet --filter=blob:none --no-checkout https://github.com/Ismail-wise/partner-ai.git /opt/pbr-partner-ai
AI_CHECKOUT_CREATED=1
sudo git -C /opt/pbr-partner-ai update-ref --no-deref HEAD cd16ba3b543fdbc66863f1f71128f4e7f02bb18c

# Only the front-door curl probes are doubled. The actual PHP renderer, MySQL
# migration, entitlement data checks, backups, AI HTTP/SSE and rollback run.
cat >"$ARTIFACTS/bin/curl" <<'SH'
#!/usr/bin/env bash
set -eu
url="" output="" format=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    -o) output="$2"; shift 2;;
    -w) format="$2"; shift 2;;
    -H|--connect-timeout|--max-time|--max-redirs) shift 2;;
    https://thepbr.io/*) url="$1"; shift;;
    *) shift;;
  esac
done
[[ -n "$url" ]] || exit 2
code=200
if [[ "$url" == https://thepbr.io/portal\?* ]]; then code=302; fi
if [[ "${PBR_FIXTURE_PROBE_FAIL:-}" == yes && "$url" == https://thepbr.io/articles\?* ]]; then code=500; fi
if [[ "$url" == https://thepbr.io/css/partner-portal.css\?* ]]; then cp "$PBR_FIXTURE_APP/public/css/partner-portal.css" "$output"; fi
if [[ -n "$format" ]]; then printf '%s' "$code"; fi
SH
chmod +x "$ARTIFACTS/bin/curl"
run_root() {
    sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" \
        PATH="$ARTIFACTS/bin:$PATH" PBR_FIXTURE_APP="$FIXTURE" PBR_FIXTURE_PROBE_FAIL="${1:-no}" \
        bash "$ROOT/scripts/pbr-portal-install.sh" "$TARGET" "$BACKUP" "$FIXTURE"
}
OUTPUT="$(sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" bash "$ROOT/scripts/pbr-portal-backup.sh" "$FIXTURE")"
BACKUP="$(printf '%s\n' "$OUTPUT" | sed -n 's/^BACKUP_PATH=//p')"
BACKUPS+=("$BACKUP")
[[ "$OUTPUT" == *PBR_PORTAL_BACKUP=COMPLETE* ]]

run_case() {
    local fault="$1" result=0
    set +e
    OUTPUT="$(run_root "$fault")"
    result=$?
    set -e
    printf '%s\n' "$OUTPUT"
    RECEIPT="$(printf '%s\n' "$OUTPUT" | sed -n 's/^RELEASE_RECEIPT_PATH=//p')"
    RECEIPTS+=("$RECEIPT")
    BACKUPS+=("$(printf '%s\n' "$OUTPUT" | sed -n 's/^FRESH_VERIFIED_BACKUP=//p')")
    if [[ -n "$RECEIPT" && "$result" != 0 && "$fault" == no ]]; then sudo tail -n 45 "$RECEIPT/private.log"; fi
    CASE_RESULT=$result
}
run_case no
[[ "$CASE_RESULT" == 0 && "$OUTPUT" == *PBR_PORTAL_INSTALL=COMPLETE* && "$OUTPUT" == *EXISTING_ACCOUNTS_ACCESS_AND_DATA=PRESERVED* ]]
[[ "$OUTPUT" != *fixture-admin* && "$OUTPUT" != *DB_PASSWORD* && "$OUTPUT" != *APP_KEY* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == "$TARGET" ]]
sudo test -s "$RECEIPT/install-complete.txt"
[[ "$(sudo mysql -N --database="$FIXTURE_DB" --execute='SELECT COUNT(*) FROM users;')" == 2 ]]
echo "Real install, MySQL migration, runtime rendering and data preservation: PASS"

# Return only code/config to the baseline. The new tables/data stay in place,
# exercising safe retries without deleting any portal records.
sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" checkout --detach b71122dee5f0585807223b94b33211e1399df4dd
sudo install -o www-data -g www-data -m 640 "$ARTIFACTS/initial.env" "$FIXTURE/.env"
(cd "$FIXTURE" && sudo -u www-data php artisan config:clear >/dev/null)
run_case yes
[[ "$CASE_RESULT" != 0 && "$OUTPUT" == *PORTAL_ROLLBACK=OK* && "$OUTPUT" == *PBR_PORTAL_INSTALL=INCOMPLETE* ]]
[[ "$(sudo git -C "$FIXTURE" -c safe.directory="$FIXTURE" rev-parse HEAD)" == b71122dee5f0585807223b94b33211e1399df4dd ]]
sudo cmp -s "$FIXTURE/.env" "$ARTIFACTS/initial.env"
sudo test ! -e "$FIXTURE/storage/framework/down"
[[ "$(sudo mysql -N --database="$FIXTURE_DB" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'portal_%';")" == 4 ]]
[[ "$(sudo mysql -N --execute="SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'pbr_portal_release_%';")" == 0 ]]
[[ "$(sudo mysql -N --execute="SELECT COUNT(*) FROM mysql.user WHERE user LIKE 'pbrpr_%';")" == 0 ]]
echo "Failed public probe restores old code/config and retains portal records: PASS"
