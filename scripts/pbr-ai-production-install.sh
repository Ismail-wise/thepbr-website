#!/usr/bin/env bash
set -Eeuo pipefail

PBR_DIR="/var/www/thepbr-laravel"
AI_DIR="/opt/pbr-partner-ai"
AI_CACHE_DIR="/var/lib/pbr-ai"
AI_ENV="/etc/pbr-ai-advisor.env"
AI_SERVICE="/etc/systemd/system/pbr-ai-advisor.service"
AI_KEY="/root/.ssh/pbr_partner_ai_github"
PBR_BRANCH="feature/pbr-ai-advisor-v1"
AI_BRANCH="feature/pbr-integration-v1"
VALIDATION_DIR="/tmp/thepbr-ai-advisor-validation"
BACKUP_ROOT="/root/thepbr-deploy-backups"
MAINTENANCE_STARTED=0

on_error() {
    local line="$1"
    echo
    echo "=== PBR AI INSTALL STOPPED SAFELY ==="
    echo "A command failed near line ${line}."
    if [ "$MAINTENANCE_STARTED" -eq 1 ] && [ -d "$PBR_DIR" ]; then
        cd "$PBR_DIR" || true
        runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan up >/dev/null 2>&1 || true
        echo "Website maintenance mode was turned OFF."
    fi
    echo "Send me the output above this message. Do not rerun random commands."
}
trap 'on_error "$LINENO"' ERR

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this installer as root on the PBR VPS."
    false
fi

for command_name in git ssh-keygen openssl curl mysqldump php systemctl sha256sum; do
    command -v "$command_name" >/dev/null || {
        echo "Missing required command: $command_name"
        false
    }
done

if [ -s /root/.nvm/nvm.sh ]; then
    # shellcheck disable=SC1091
    source /root/.nvm/nvm.sh
fi

command -v node >/dev/null || { echo "Node.js was not found."; false; }
command -v npm >/dev/null || { echo "npm was not found."; false; }

echo "=== PBR AI ADVISOR ONE-PASS INSTALL ==="
echo "Production code will not change until both AI repositories pass validation."

cd "$PBR_DIR"

if [ -n "$(git status --porcelain)" ]; then
    echo "STOP: PBR production working tree is not clean."
    git status --short
    false
fi

PBR_CURRENT="$(git rev-parse HEAD)"
echo "PBR current: $(git rev-parse --short HEAD)"

echo
echo "=== FETCH PBR AI FEATURE ==="
git fetch origin "$PBR_BRANCH"
PBR_TARGET="$(git rev-parse "origin/$PBR_BRANCH")"

git merge-base --is-ancestor "$PBR_CURRENT" "$PBR_TARGET" || {
    echo "STOP: AI feature is not a safe fast-forward from current production."
    false
}
echo "PBR target: $(git rev-parse --short "$PBR_TARGET")"

echo
echo "=== PARTNER-AI READ-ONLY DEPLOY KEY ==="
if [ ! -s "$AI_KEY" ] || [ ! -s "${AI_KEY}.pub" ]; then
    ssh-keygen -q -t ed25519 -f "$AI_KEY" -N '' -C "pbr-partner-ai-readonly"
    chmod 600 "$AI_KEY"
    chmod 644 "${AI_KEY}.pub"
fi

echo
echo "Copy ONLY the public key below:"
echo "------------------------------------------------------------"
cat "${AI_KEY}.pub"
echo "------------------------------------------------------------"
echo "GitHub -> Ismail-wise/partner-ai -> Settings -> Deploy keys -> Add deploy key"
echo "Title: PBR Production Read Only"
echo "IMPORTANT: Leave 'Allow write access' UNCHECKED."
echo
read -r -p "After the deploy key is added in GitHub, press Enter here to continue... " _

echo
echo "=== VERIFY PARTNER-AI ACCESS ==="
GIT_SSH_COMMAND="ssh -i $AI_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new" \
    git ls-remote git@github.com:Ismail-wise/partner-ai.git "$AI_BRANCH" >/tmp/pbr-ai-lsremote.txt

grep -q "refs/heads/$AI_BRANCH" /tmp/pbr-ai-lsremote.txt || {
    echo "GitHub access works, but the required AI branch was not found."
    false
}
rm -f /tmp/pbr-ai-lsremote.txt
echo "Partner-AI access: PASS"

echo
echo "=== PREPARE PARTNER-AI ENGINE ==="
if [ -e "$AI_DIR" ] && [ ! -d "$AI_DIR/.git" ]; then
    echo "STOP: $AI_DIR exists but is not the expected Git repository."
    false
fi

if [ ! -d "$AI_DIR/.git" ]; then
    mkdir -p "$(dirname "$AI_DIR")"
    GIT_SSH_COMMAND="ssh -i $AI_KEY -o IdentitiesOnly=yes" \
        git clone --single-branch --branch "$AI_BRANCH" \
        git@github.com:Ismail-wise/partner-ai.git "$AI_DIR"
else
    cd "$AI_DIR"
    if [ -n "$(git status --porcelain)" ]; then
        echo "STOP: Existing Partner-AI checkout is not clean."
        git status --short
        false
    fi
    git config core.sshCommand "ssh -i $AI_KEY -o IdentitiesOnly=yes"
    git fetch origin "$AI_BRANCH"
    git checkout -B "$AI_BRANCH" "origin/$AI_BRANCH"
fi

cd "$AI_DIR"
git config core.sshCommand "ssh -i $AI_KEY -o IdentitiesOnly=yes"
AI_TARGET="$(git rev-parse HEAD)"
echo "Partner-AI target: $(git rev-parse --short HEAD)"

LEGACY_CACHE_SHA_BEFORE=""
if [ -f vectordb_cache.json ]; then
    LEGACY_CACHE_SHA_BEFORE="$(sha256sum vectordb_cache.json | awk '{print $1}')"
    echo "Existing friend RAG cache: FOUND and protected"
else
    echo "Existing friend RAG cache: not present in checkout; PDF fallback will be used"
fi

PDF_COUNT="$(find docs -maxdepth 1 -type f -iname '*.pdf' 2>/dev/null | wc -l | tr -d ' ')"
echo "Existing knowledge PDFs: $PDF_COUNT"
[ "$PDF_COUNT" -gt 0 ] || { echo "STOP: No Partner-AI knowledge PDFs found."; false; }

echo
echo "Installing AI runtime dependencies..."
npm install --omit=dev --package-lock=false
npm run check:pbr

if [ -n "$(git status --porcelain)" ]; then
    echo "STOP: Partner-AI source changed during dependency install."
    git status --short
    false
fi

NODE_SOURCE="$(command -v node)"
install -m 0755 "$NODE_SOURCE" /usr/local/bin/pbr-node
/usr/local/bin/pbr-node --version

mkdir -p "$AI_CACHE_DIR"
chown www-data:www-data "$AI_CACHE_DIR"
chmod 750 "$AI_CACHE_DIR"

echo
echo "=== PRIVATE AI CREDENTIAL SETUP ==="
GEMINI_KEY=""
if [ -f "$AI_ENV" ]; then
    GEMINI_KEY="$(sed -n 's/^GEMINI_API_KEY=//p' "$AI_ENV" | head -n1)"
fi

if [ -z "$GEMINI_KEY" ]; then
    read -r -s -p "Paste the existing Gemini API key used for Partner AI (it will NOT be displayed): " GEMINI_KEY
    echo
fi

[ -n "$GEMINI_KEY" ] || { echo "Gemini API key cannot be empty."; false; }
PBR_SECRET="$(openssl rand -hex 32)"

umask 077
cat > "$AI_ENV" <<EOF
GEMINI_API_KEY=$GEMINI_KEY
PBR_INTERNAL_SECRET=$PBR_SECRET
PBR_INTERNAL_HOST=127.0.0.1
PBR_INTERNAL_PORT=3107
PBR_AI_MODEL=gemini-2.5-flash
PBR_EMBEDDING_MODEL=gemini-embedding-2
PBR_EMBEDDING_DIMENSIONS=768
PBR_EMBEDDING_BATCH_SIZE=16
PBR_VECTOR_CACHE_PATH=$AI_CACHE_DIR/pbr_vectordb_cache.json
PBR_DOC_THRESHOLD=0.45
PBR_MAX_HISTORY=20
PBR_MAX_CONTEXT_CHARS=60000
PBR_MAX_MESSAGE_CHARS=5000
EOF
chmod 600 "$AI_ENV"
unset GEMINI_KEY

echo
echo "=== INSTALL PRIVATE SYSTEMD SERVICE ==="
cat > "$AI_SERVICE" <<EOF
[Unit]
Description=PBR AI Advisor Private RAG Service
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$AI_DIR
Environment=NODE_ENV=production
EnvironmentFile=$AI_ENV
ExecStart=/usr/local/bin/pbr-node $AI_DIR/pbr-service.js
Restart=on-failure
RestartSec=5
TimeoutStopSec=20
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
PrivateDevices=true
ProtectHome=true
ProtectSystem=strict
ReadWritePaths=$AI_CACHE_DIR
RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6

[Install]
WantedBy=multi-user.target
EOF
chmod 644 "$AI_SERVICE"

systemctl daemon-reload
systemctl enable pbr-ai-advisor.service >/dev/null
systemctl restart pbr-ai-advisor.service

echo
echo "PBR RAG engine is starting. The first modern embedding cache build can take a few minutes."
AI_READY=0
for attempt in $(seq 1 180); do
    if curl -fsS --max-time 3 http://127.0.0.1:3107/health >/tmp/pbr-ai-health.json 2>/dev/null; then
        AI_READY=1
        break
    fi

    if ! systemctl is-active --quiet pbr-ai-advisor.service; then
        break
    fi

    if [ $((attempt % 12)) -eq 0 ]; then
        echo "Still preparing preserved PBR knowledge..."
    fi
    sleep 5
done

if [ "$AI_READY" -ne 1 ]; then
    echo "AI service did not become ready. Recent service log:"
    journalctl -u pbr-ai-advisor.service -n 60 --no-pager || true
    false
fi

cat /tmp/pbr-ai-health.json
grep -q '"knowledgeReady":true' /tmp/pbr-ai-health.json || {
    echo "STOP: AI service started but RAG knowledge is not ready."
    false
}
rm -f /tmp/pbr-ai-health.json

echo
echo "=== VERIFY ORIGINAL RAG DATA WAS NOT MODIFIED ==="
if [ -n "$LEGACY_CACHE_SHA_BEFORE" ]; then
    LEGACY_CACHE_SHA_AFTER="$(sha256sum vectordb_cache.json | awk '{print $1}')"
    [ "$LEGACY_CACHE_SHA_BEFORE" = "$LEGACY_CACHE_SHA_AFTER" ] || {
        echo "STOP: Legacy RAG cache checksum changed unexpectedly."
        false
    }
fi
[ -z "$(git status --porcelain)" ] || {
    echo "STOP: Partner-AI tracked files changed unexpectedly."
    git status --short
    false
}
echo "Original Partner-AI data: PRESERVED"

echo
echo "=== PRIVATE API SECURITY + AI SMOKE TEST ==="
UNAUTH_CODE="$(curl -s -o /dev/null -w '%{http_code}' \
    -X POST http://127.0.0.1:3107/internal/pbr/chat \
    -H 'Content-Type: application/json' \
    --data '{"message":"test"}')"
[ "$UNAUTH_CODE" = "403" ] || { echo "Private API auth check failed: $UNAUTH_CODE"; false; }

SMOKE_FILE="/tmp/pbr-ai-smoke-$$.txt"
curl -fsS -N --max-time 180 \
    -X POST http://127.0.0.1:3107/internal/pbr/chat \
    -H "X-PBR-Internal-Secret: $PBR_SECRET" \
    -H 'Content-Type: application/json' \
    --data '{"message":"Partnership Business မှာ capital contribution ကို ဘယ်လိုစနစ်တကျ သတ်မှတ်သင့်လဲ?","history":[],"workspaceContext":{"business":{"name":"PBR Integration Test","stage":"existing","currency":"THB"}}}' \
    > "$SMOKE_FILE"

grep -q '"type":"delta"' "$SMOKE_FILE" || { echo "AI smoke test returned no answer text."; false; }
grep -q '"type":"done"' "$SMOKE_FILE" || { echo "AI smoke test did not complete."; false; }
SMOKE_MODE="$(grep -o '"mode":"[^"]*"' "$SMOKE_FILE" | head -n1 || true)"
rm -f "$SMOKE_FILE"
echo "AI generation: PASS ${SMOKE_MODE}"

echo
echo "=== VALIDATE LARAVEL AI FEATURE OFF PRODUCTION ==="
cd "$PBR_DIR"
rm -rf "$VALIDATION_DIR"
git worktree prune
git worktree add --detach "$VALIDATION_DIR" "origin/$PBR_BRANCH"

cp -al "$PBR_DIR/vendor" "$VALIDATION_DIR/vendor" 2>/dev/null || cp -a "$PBR_DIR/vendor" "$VALIDATION_DIR/vendor"
cp "$PBR_DIR/.env" "$VALIDATION_DIR/.env"
chmod 600 "$VALIDATION_DIR/.env"
mkdir -p \
    "$VALIDATION_DIR/storage/framework/cache" \
    "$VALIDATION_DIR/storage/framework/sessions" \
    "$VALIDATION_DIR/storage/framework/views" \
    "$VALIDATION_DIR/storage/logs" \
    "$VALIDATION_DIR/bootstrap/cache"

cd "$VALIDATION_DIR"
php -l app/Http/Controllers/WorkspaceAiAdvisorController.php
php -l app/Services/Ai/PbrAiContextBuilder.php
php -l app/Models/AiConversation.php
php -l app/Models/AiMessage.php
php -l config/pbr_ai.php
php -l database/migrations/2026_08_12_100700_create_ai_advisor_tables.php
php -l routes/ai.php
/usr/local/bin/pbr-node --check public/js/pbr-ai-advisor.js

ALLOW_URL_FOPEN="$(php -r 'echo ini_get("allow_url_fopen") ? "1" : "0";')"
[ "$ALLOW_URL_FOPEN" = "1" ] || { echo "STOP: PHP allow_url_fopen is disabled."; false; }

php artisan route:list --name=workspaces.ai-advisor >/tmp/pbr-ai-routes.txt
grep -q 'workspaces.ai-advisor.index' /tmp/pbr-ai-routes.txt
grep -q 'workspaces.ai-advisor.chat' /tmp/pbr-ai-routes.txt
rm -f /tmp/pbr-ai-routes.txt
php artisan view:cache
git diff --check "$PBR_CURRENT"..HEAD
[ -z "$(git status --porcelain)" ] || {
    echo "STOP: Laravel validation worktree is not clean."
    git status --short
    false
}

echo "Laravel AI feature validation: PASS"
cd "$PBR_DIR"
git worktree remove --force "$VALIDATION_DIR"
git worktree prune

echo
echo "=== CREATE PRODUCTION ROLLBACK + BACKUPS ==="
STAMP="$(date +%Y%m%d_%H%M%S)"
ROLLBACK_BRANCH="backup/pre-pbr-ai-advisor-${STAMP}"
BACKUP_DIR="$BACKUP_ROOT/pre-pbr-ai-advisor-${STAMP}"
DB_BACKUP="$BACKUP_DIR/thepbr_laravel.sql"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

git branch "$ROLLBACK_BRANCH" "$PBR_CURRENT"
cp .env "$BACKUP_DIR/production.env"
chmod 600 "$BACKUP_DIR/production.env"

MYSQL_PWD="$(cat /root/.thepbr_db_password)" \
    mysqldump \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --no-tablespaces \
    -u thepbr_app \
    thepbr_laravel \
    > "$DB_BACKUP"
chmod 600 "$DB_BACKUP"

[ -s "$DB_BACKUP" ] && grep -q 'Dump completed' "$DB_BACKUP" || {
    echo "STOP: Database backup verification failed."
    false
}
echo "Rollback branch: $ROLLBACK_BRANCH"
echo "Verified DB backup: $DB_BACKUP"

echo
echo "=== DEPLOY PBR AI ADVISOR ==="
ENV_OWNER="$(stat -c '%u:%g' .env)"
ENV_MODE="$(stat -c '%a' .env)"

runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan down --retry=60 >/dev/null
MAINTENANCE_STARTED=1

git merge --ff-only "origin/$PBR_BRANCH"

sed -i \
    -e '/^PBR_AI_BASE_URL=/d' \
    -e '/^PBR_AI_INTERNAL_SECRET=/d' \
    -e '/^PBR_AI_TIMEOUT=/d' \
    -e '/^PBR_AI_CONNECT_TIMEOUT=/d' \
    -e '/^PBR_AI_HISTORY_MESSAGES=/d' \
    -e '/^PBR_AI_MAX_CONTEXT_CHARS=/d' \
    .env

cat >> .env <<EOF

PBR_AI_BASE_URL=http://127.0.0.1:3107
PBR_AI_INTERNAL_SECRET=$PBR_SECRET
PBR_AI_TIMEOUT=180
PBR_AI_CONNECT_TIMEOUT=5
PBR_AI_HISTORY_MESSAGES=20
PBR_AI_MAX_CONTEXT_CHARS=60000
EOF
chown "$ENV_OWNER" .env
chmod "$ENV_MODE" .env
unset PBR_SECRET

chown -R www-data:www-data storage bootstrap/cache

runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan optimize:clear
runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan migrate --force
runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan config:cache
runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan view:cache
runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan up
MAINTENANCE_STARTED=0

echo
echo "=== FINAL PRODUCTION VERIFY ==="
runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan route:list --name=workspaces.ai-advisor | head -20
runuser -u www-data -- env HOME=/tmp XDG_CONFIG_HOME=/tmp php artisan migrate:status | grep -E 'create_ai_advisor_tables|2026_08_12_100700'

for URL in \
    "https://thepbr.io/" \
    "https://thepbr.io/login" \
    "https://thepbr.io/articles" \
    "https://thepbr.io/classes"
do
    CODE="$(curl -L -s -o /dev/null -w '%{http_code}' "$URL")"
    echo "$CODE  $URL"
    [ "$CODE" = "200" ] || false
done

curl -fsS --max-time 5 http://127.0.0.1:3107/health

echo
echo "Production commit: $(git rev-parse --short HEAD)"
echo "Partner-AI commit: $(cd "$AI_DIR" && git rev-parse --short HEAD)"
echo "PBR working tree:"
git status --short

echo
echo "=== PBR AI ADVISOR DEPLOY COMPLETE ==="
echo "Original Partner-AI source/data is preserved."
echo "PBR AI service is private on 127.0.0.1:3107."
echo "Database backup: $DB_BACKUP"
echo "Rollback branch: $ROLLBACK_BRANCH"
echo "Next: browser-test the AI Advisor once."
