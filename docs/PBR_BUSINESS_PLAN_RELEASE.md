# Owner-private Business Plan — first workflow App

Implements the first testable slice of issue #50 on top of portal
`369a1ec3bb2e947cfc0c18482b23ff96cf78fbcd`. This document describes the code;
production installation and signed-in acceptance require a separate receipt.

## Owner workflow

1. Apps → My Workspaces → select a Workspace → Business Plan.
2. Enter business name/stage and the plan. Optional sections cover the nine
   Business Model Canvas topics, demand evidence, growth and break-even notes.
3. **Save Draft** keeps incomplete work. **သိမ်းပြီး ပြန်စစ်မယ်** saves the actual
   form contents before opening Review, so newly typed text is included.
4. Confirmation requires the three marked basics: business name, value
   proposition and target customers, plus the Owner's review checkbox. These
   are form-completeness requirements, not an instructor-approved feasibility
   score. Optional fields remain optional.
5. **Confirm** creates a numbered, immutable content snapshot. Editing starts
   or updates a separate Draft; the previous confirmed content stays visible
   in History. An older snapshot can explicitly replace the working Draft,
   preserving its revision provenance and every confirmed snapshot.
6. From a confirmed snapshot, **AI အတွက် ပြင်ဆင်မယ်** creates a private text
   Knowledge source and opens the existing review screen. It does not approve,
   embed or transmit the content. The Owner reviews text/business status and
   separately approves it using the existing Knowledge workflow.

Opening an already linked Knowledge source never overwrites the Owner's text
or resets its approval. Deleting that source preserves the confirmed plan and
permits an explicit fresh export/review. Old plan snapshots export as
Historical; the latest exports as Current with AI readiness still Needs Review.
Later plan edits do not change existing Knowledge. The Owner manages whether
older plan Knowledge should remain Current, become Historical, or be removed.
The source contains its original plan version and confirmation date.

## Scope

- Multiple Owner Workspaces remain separate, including against invitation-only
  Partners and ordinary application Admins. Current Student expiry/account
  status rules and legacy-route boundaries remain authoritative.
- All plan pages are private/no-store and escape entered text. Knowledge is
  still isolated from public publishing and shared Partner AI ingestion.
- The Workspace overview shows an honest New/Draft/Confirmed state and links
  only to implemented actions. It does not label the whole business ready or
  force a sequential journey through transfer, disputes, exit or closure.
- Demand, scalability and break-even fields store Owner-entered evidence and
  notes. This slice does not invent demand forecasts, feasibility scores,
  universal BEP formulas or valuation methods.
- Valuation, workspace contributions and ownership records are subsequent
  slices. Existing personal saved calculator results keep their original scope.
- Original AI chat/language behavior, PartnerDynamics scoring and Filament
  Article/Video/Class Session publishing remain unchanged.

## Concurrency and storage

One additive migration creates `workspace_business_plans` and
`workspace_business_plan_revisions`. No existing table is altered. Draft saves,
confirmation and history-to-Draft copies use a version token, the existing
private Workspace lock and a transactional Workspace row lock. A stale form
returns HTTP 409 with its submitted text still visible for recovery, rather
than replacing the newer Draft. Confirmation creates no Knowledge source.

The revision's content/provenance is immutable through these routes. A nullable
Knowledge-source reference can be attached or cleared on source deletion.
Requests cannot choose another Workspace, confirmation actor, snapshot content,
source ID or internal version counter. Each revision/source lookup is scoped
again on the server. No private business data is logged by this feature.

## Acceptance

Automated feature coverage checks draft/review/confirmation, provenance,
retained history, stale and duplicate submissions, recovery text, cross-Owner
and same-Owner Workspace isolation, Admin/Partner/expiry denials, escaped/private
rendering, preserved old records and the real existing Knowledge approval path
with a synthetic provider double. Provider doubles do not establish live AI
behavior; the original AI service itself is not changed by this release.

Signed-in acceptance must check desktop/mobile typing, collapsed sections,
draft reopening, two confirmed versions, History, stale-tab recovery and the
explicit Knowledge review/approval path before marking the release accepted.

## Guarded installation

`scripts/pbr-business-plan-update.sh TARGET [APP_ROOT] [AI_ROOT]` accepts the exact
installed baseline or the same reviewed target for an idempotent retry. It
checks cached/uncached production configuration, the original local AI source,
and schema consistency before maintenance. The target diff must stay inside an
explicit Business Plan allowlist and contain only this one additive migration.

The installer runs the full isolated SQLite suite and synthetic original-AI
OCR/embedding/generation checks before maintenance. It then verifies a fresh
application/database backup through an isolated restore, rehearses the migration
on that restored MySQL database using an account with no access to production,
and verifies existing row/schema fingerprints before switching code. Live
render probes use unsaved synthetic models and do not create user records.

All original business/private tables, account/access records, old schema and
private original files are checked again before reopening. Public/Admin HTTPS,
anonymous Business Plan authentication and exact served CSS bytes are checked
through the temporary maintenance bypass. The AI repository is never switched,
its service is not restarted, `.env` is unchanged and no data reset is run.

On a failed cutover, only code/caches are restored; additive tables and existing
records remain. Unexpected record/private-file changes keep the current code
under maintenance for review. An old database backup is never restored over
new data. The historical test-data cleanup also refuses to run when private
Business Plan records are present.

The disposable Actions smoke test exercises successful MySQL migration/render,
repeat installation with new plan data, bad-asset rollback, retry after rollback
and preservation/maintenance on unexpected private-file or new-plan writes.
Its HTTPS and AI services are explicit doubles; production executes the actual
checks. This does not replace the signed-in acceptance checklist above.
