#!/usr/bin/env bash
# Integration test for disposable GitHub Actions runners only.
set -Eeuo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${CI:-}" == true && "$EUID" != 0 ]] || exit 2
umask 077
ROOT="$(pwd)"
FIXTURE="$(mktemp -d /tmp/pbr-backup-fixture-XXXXXXXX)"
ARTIFACTS="$(mktemp -d /tmp/pbr-backup-test-XXXXXXXX)"
FIXTURE_DB="pbr_backup_fixture_$(openssl rand -hex 8)"
ADMIN_CONFIG_CREATED=0
BACKUP_PATHS=()
run_backup() {
    sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0="$FIXTURE" \
        bash "$ROOT/scripts/pbr-portal-backup.sh" "$FIXTURE"
}

cleanup() {
    local result=$?
    trap - EXIT
    set +e
    if [[ "$ADMIN_CONFIG_CREATED" == 1 ]]; then
        printf 'DROP DATABASE IF EXISTS `%s`;\n' "$FIXTURE_DB" | sudo mysql
        sudo rm -f /root/.my.cnf
    fi
    for backup in "${BACKUP_PATHS[@]}"; do sudo rm -rf -- "$backup"; done
    git worktree remove --force "$FIXTURE" >/dev/null 2>&1 || true
    rm -rf -- "$FIXTURE"
    rm -rf -- "$ARTIFACTS"
    exit "$result"
}
trap cleanup EXIT

# Synthetic local test credential, never a production credential.
sudo test ! -e /root/.my.cnf
printf '[client]\nuser=root\npassword=root\n' | sudo install -m 600 /dev/stdin /root/.my.cnf
ADMIN_CONFIG_CREATED=1
sudo mysql --execute='SELECT 1;' >/dev/null
git worktree add --detach "$FIXTURE" b71122dee5f0585807223b94b33211e1399df4dd
cp -a vendor "$FIXTURE/vendor"
(cd "$FIXTURE" && composer dump-autoload --no-scripts --no-interaction --optimize >/dev/null)
cat >"$FIXTURE/.env" <<EOF
APP_NAME=PBRBackupFixture
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://thepbr.io
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$FIXTURE_DB
DB_USERNAME=root
DB_PASSWORD=root
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
LOG_CHANNEL=stderr
PBR_PARTNER_PORTAL_ENABLED=false
EOF
mkdir -p "$FIXTURE/storage/app/private" "$FIXTURE/storage/framework/views" "$FIXTURE/storage/logs"
printf 'private upload sentinel\n' >"$FIXTURE/storage/app/private/backup-sentinel.txt"
printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$FIXTURE_DB" | sudo mysql
sudo mysql --default-character-set=utf8mb4 --database="$FIXTURE_DB" <<'SQL'
CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(255), payload JSON) ENGINE=InnoDB;
CREATE TABLE student_enrollments (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED, FOREIGN KEY (user_id) REFERENCES users(id)) ENGINE=InnoDB;
CREATE TABLE partner_dynamics_assessments (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED) ENGINE=InnoDB;
CREATE TABLE ai_conversations (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED) ENGINE=InnoDB;
CREATE TABLE fixture_payloads (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, bytes LONGBLOB, text_value LONGTEXT, optional_value TEXT NULL) ENGINE=InnoDB AUTO_INCREMENT=12;
CREATE TABLE fixture_without_primary (message TEXT NOT NULL) ENGINE=InnoDB;
INSERT INTO users VALUES (1, 'fixture-only@example.invalid', JSON_OBJECT('name', 'မြန်မာ 🤝', 'active', true));
INSERT INTO student_enrollments VALUES (1, 1);
INSERT INTO partner_dynamics_assessments VALUES (1, 1);
INSERT INTO ai_conversations VALUES (1, 1);
INSERT INTO fixture_payloads VALUES (3, 0x000a0d5c22ff, 'မြန်မာ\nquote\' " \\ 🤝', NULL), (4, '', '', '');
INSERT INTO fixture_without_primary VALUES ('zebra'), ('alpha'), ('alpha');
SQL
sudo mysqldump --user=root --single-transaction --skip-comments --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 "$FIXTURE_DB" >"$ARTIFACTS/before.sql"

set +e
OUTPUT="$(run_backup)"
RESULT=$?
set -e
printf '%s\n' "$OUTPUT"
BACKUP="$(printf '%s\n' "$OUTPUT" | sed -n 's/^BACKUP_PATH=//p')"
BACKUP_PATHS+=("$BACKUP")
if [[ "$RESULT" != 0 ]]; then
    # This runner contains only synthetic fixture data and disposable secrets.
    if [[ -n "$BACKUP" ]]; then sudo tail -n 35 "$BACKUP/private.log"; fi
    exit "$RESULT"
fi
[[ "$OUTPUT" == *PBR_PORTAL_BACKUP=COMPLETE* && "$OUTPUT" == *DATABASE_ROUNDTRIP=OK* && "$OUTPUT" == *APPLICATION_RESTORE_CHECK=OK* ]]
[[ "$OUTPUT" != *fixture-only* && "$OUTPUT" != *DB_PASSWORD* && "$OUTPUT" != *APP_KEY* ]]
sudo test -s "$BACKUP/restore-verified.txt"
sudo test ! -e "$BACKUP/drill.cnf"
[[ "$(sudo stat -c '%a' "$BACKUP")" == 700 ]]
[[ "$(sudo stat -c '%a' "$BACKUP/database.sql")" == 600 ]]
sudo tar -xOf "$BACKUP/application.tar.gz" ./storage/app/private/backup-sentinel.txt | cmp -s - "$FIXTURE/storage/app/private/backup-sentinel.txt"
sudo mysqldump --user=root --single-transaction --skip-comments --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 "$FIXTURE_DB" >"$ARTIFACTS/after.sql"
cmp -s "$ARTIFACTS/before.sql" "$ARTIFACTS/after.sql"
[[ "$(sudo mysql -N --execute="SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'pbr_portal_drill_%';")" == 0 ]]
[[ "$(sudo mysql -N --execute="SELECT COUNT(*) FROM mysql.user WHERE user LIKE 'pbrpd_%';")" == 0 ]]
echo "Source data, account permissions, archive content and cleanup: PASS"

# A view could refer back to the live schema; reject it before creating a clone.
sudo mysql --database="$FIXTURE_DB" --execute='CREATE VIEW fixture_view AS SELECT id FROM users;'
set +e
OUTPUT="$(run_backup)"
RESULT=$?
set -e
BACKUP_PATHS+=("$(printf '%s\n' "$OUTPUT" | sed -n 's/^BACKUP_PATH=//p')")
[[ "$RESULT" != 0 && "$OUTPUT" == *CUSTOM_DATABASE_OBJECTS_NEED_REVIEW* && "$OUTPUT" == *PBR_PORTAL_BACKUP=INCOMPLETE* ]]
[[ "$(sudo mysql -N --execute="SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'pbr_portal_drill_%';")" == 0 ]]
echo "Unsupported database objects stop before restore: PASS"

git -C "$FIXTURE" checkout --detach "$(git rev-parse HEAD)"
set +e
OUTPUT="$(run_backup)"
RESULT=$?
set -e
[[ "$RESULT" != 0 && "$OUTPUT" == *LIVE_SOURCE_CHANGED_RECHECK_REQUIRED* && "$OUTPUT" != *STEP=database_backup* ]]
echo "Unexpected live source stops before any backup or restore: PASS"
