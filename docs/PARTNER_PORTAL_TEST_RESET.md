# Authorized test-data reset

The owner confirmed that every workspace is test data and only Admin accounts
are needed. This release also simplifies the original Filament dashboard and
navigation. Articles, Videos and Class Sessions remain editable in Filament and
continue to supply the public website. Partner AI and PartnerDynamics code do
not change.

`scripts/pbr-portal-reset.sh REVIEWED_COMMIT [APP_ROOT]` accepts only the deployed
Filament baseline `ca1595f8f758d4e1d3376b19a3639b3c0629197f` and the exact reviewed
target. It requires root on the production server, PHP 8.4 and local MySQL.

The script runs isolated application tests, enables maintenance, records the
exact reset scope, creates a fresh database/application backup and verifies its
restore. It repeats the exact deletion on an isolated MySQL restore using a
database account that cannot access production. Only then does it install the
reviewed code and apply the cleanup in one transaction, with foreign keys on.
The final database fingerprint must match the rehearsal exactly.

Removed: every workspace, associated workspace records, all non-admin accounts
and their personal chats, saved tools, assessments, access records, sessions and
reset tokens. Admin-owned test workspaces and their tool sessions are included.
All identities marked `is_admin` or `role=admin` are retained, including inactive
admins; at least one active Filament administrator must remain. Credentials,
names, roles and statuses are not changed. Admin personal records outside
workspaces are retained. Public content, class metadata, chapter/tool catalog,
migrations, schema, application environment and identity sequences are retained.

No seeders, truncation, schema resets, password resets or AI reindexing run.
Unknown dependent tables, pending jobs, unsupported database objects or a changed
dataset stop the reset. Receipts/plans/backups are root-private. Normal output
contains counts and checks, not credentials or user identities.

A persistent journal prevents a repeated command from deleting accounts created
after the initial reset. If the target and committed journal already exist, a
repeat performs runtime/public checks only. Do not delete or reuse this journal.

Before a database commit, a verified transaction failure restores the previous
code and reopens the site. An uncertain database outcome keeps maintenance on
for review. After a commit the script never restores an old database: that could
erase new real records. A failed public probe after reopening leaves the target
running and can be retried safely. A renderer failure while still in maintenance
requires review of the private receipt before reopening. Full backup paths are
printed in the receipt; do not paste backup contents or private logs into chat.

Validation covers SQLite account/signup/admin/publishing regressions and a real
disposable MySQL rehearsal, failed-postcondition rollback, public-probe failure
after commit, and repetition after creating a new real member. Public HTTPS is
a synthetic front door in CI; live probes run on the actual server. Signed-in
Admin login, content publishing and a new signup/access grant still need human
verification after deployment.
