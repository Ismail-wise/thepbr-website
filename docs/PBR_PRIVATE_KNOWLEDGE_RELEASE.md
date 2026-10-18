# Owner-private Knowledge and Workspace PBR AI

The owner confirmed all live Owner Workspace, invited Partner and native Filament
publishing UAT passed on 19 October 2026 (Asia/Bangkok). That accepted foundation
is production `c086de86c7bc73094c2b0984ca66cd398c1e572f`. This next release is not
production until its separate installation receipt and signed-in UAT are supplied.

## User workflow

An active Student opens My Workspaces, chooses a business, and opens Knowledge.
They add a text note or a JPEG/PNG/WebP photo, PDF or UTF-8 text file, and explicitly
choose Draft, Current or Historical. Uploaded originals stay private. Photo/PDF
transcription runs through the existing Partner AI Gemini service; a failed read
keeps the original and permits retry or manual transcription. Partial extraction
is visibly flagged. The Owner checks names, amounts, dates, unreadable text and
table relationships, edits the text, and explicitly confirms AI use. Saving for
later removes that source from AI use until it is approved again.

Readiness and business status are independent. An approved draft is still a draft;
an approved historical record is still historical. AI proposals never write or
approve facts automatically. Knowledge is not published to the public website.

The Workspace PBR AI page names the selected business, shows its approved source
count and keeps its own chat history. It combines the original Partner AI shared
partnership expertise with selected approved excerpts from this Workspace. It
returns stable source references such as [S1], including across follow-up answers; the Owner can follow the authorized source
and download the original. The source list is labeled as material supplied to the
answer, not proof every listed source was actually cited or that the whole library
was audited. Changed revisions and removed sources are identified in old chats.

Changing approved Knowledge, deleting it, saving it for later, or changing the
business name/description makes an old conversation read-only for inference. Its
historical answers remain readable; the Owner starts a new conversation so removed
facts cannot leak back through old messages. The Owner can delete a conversation
and all its messages. Source approval/edit/delete and inference use one Workspace
lock; stale review forms are rejected. Legacy Workspace metadata writes use the
same lock. Legacy Workspace deletion requires removing private originals first.

## Privacy and processing

Every route checks active Student status and exact ownership before source/file,
retrieval or conversation access. The ordinary application Admin has no ownership
bypass. Being a Partner, having old broad membership permissions, or owning another
Workspace does not grant access. Personal portal AI storage and legacy AI storage
are separate from these new private conversation tables. Existing PartnerDynamics
scoring, invitation-only access and result-sharing restrictions remain.

The dedicated filesystem disk has `serve=false`, private visibility and no public
storage symlink. Random server filenames, MIME restrictions, integrity checks,
forced attachment delivery, no-store/no-referrer and nosniff protect originals.
Source and answer text render as escaped text; model output cannot create arbitrary
links, HTML or script. Only server-authorized source IDs produce citation links.

Private transcription and embedding are stateless endpoints in the original
`Ismail-wise/partner-ai` repository. They use inline processing, not a Files API or
shared knowledge ingestion. Per-source vectors live only in the scoped Laravel
MySQL tables. Queries use matching model/dimension/version keys; a model change
requires re-approval. Private chats have no Google Search or other external tools.
Provider errors are generic and do not echo private source or prompt content.
The UI explains that the configured AI provider processes submitted content.
Application privacy does not imply the server operator or the external AI provider
cannot process data, nor that their backups/retention policies have changed.

There is no invented account/Workspace count or subscription storage quota. The
initial per-request technical bounds are 10 MiB per upload (also subject to the
existing PHP/web-server limit), 60,000 reviewed characters, 1,800-character chunks
with overlap, a bounded 1,800-character retrieval query and up to 12 excerpts per
question. The complete user question still reaches the advice model. Long or unclear extraction must
be checked by the Owner. Retrieval is a bounded selection; the initial scoped
MySQL cursor implementation does not claim an exhaustive review of every source.

No automatic expiry or background purge is introduced. Owner source deletion
removes its original and indexed content from the active application. Old derived
answers require separate chat deletion. Verified deployment backups retain their
existing protected retention behavior. No new file-retention business policy is
invented. The historical test reset refuses any private Knowledge/chat data and
is never invoked by this deployment.

## Installation and evidence

Only four new tables are added: workspace_knowledge_sources,
workspace_knowledge_chunks, workspace_ai_conversations and workspace_ai_messages.
Original rows, columns, credentials, content, chats, account entitlements, .env and
shared Partner AI knowledge remain intact.

`scripts/pbr-knowledge-update.sh` targets the exact accepted Workspace baseline and
an exact reviewed original Partner AI commit. It runs isolated application tests
and the AI private contract, takes a fresh restored/verified Laravel backup and an
AI source/config/cache archive with an isolated extraction/compare check, rehearses
the additive migration using a restricted MySQL account, renders real forms as the
web user, updates both code repositories in maintenance and checks public/auth
routes before reopening. On the server it also makes real synthetic-only private
OCR/embedding, original personal advice and cited Workspace advice requests. It
never uses a real Owner's content for these generation tests.

Unchanged records allow code-only rollback of both repositories while retaining
the additive schema. Unexpected cutover writes remain intact with maintenance on
for review; no older database, cache or uploaded files are restored over new data.
Retry reuses the recorded migration. Deployment receipts contain no credentials.

Application tests exercise owner/Workspace/Admin/Partner boundaries, review and
business status, private originals, citations, stale history/forms, source deletion,
incomplete streaming, expiry, model mismatch and the old reset guard. The MySQL
release fixture tests real backup/restore, migration, all original records,
repeat installs with newly created private records, AI restart failure, public
probe rollback, safe retry and an unexpected binary mutation held for review.
Its AI process, generation and HTTPS calls are explicit synthetic doubles; real
provider requests are tested by the server installer. Partner AI's own contract
suite validates its actual private endpoint handlers. Signed-in desktop/mobile
and original-file review remain a live user acceptance check.
