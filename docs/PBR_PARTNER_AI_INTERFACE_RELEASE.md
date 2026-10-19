# Partner AI interface and bilingual replies

The accepted private Knowledge release is live at website
`2567dc48a3d6d83565a7e078d1274cbf810a276d`, paired with original Partner AI
`a0701941890e57267d2c4ab3ad39e1f0f284ae84`. The Owner reported its signed-in
workflow correct before requesting this interface refinement on 19 October 2026
(Asia/Bangkok).

## Interface

The original `Ismail-wise/partner-ai/index.html` is the visual reference: compact
forest-green header, white conversation surface, rounded green user messages,
subtle gold separator and bottom composer. The portal retains its own signed-in
account and Workspace URLs. It does not embed the standalone application's
separate login, subscription counters, upgrade offers, Admin-key flow or storage.

Remove repeated slogans, eyebrow headings, welcome paragraphs and redundant
instructions from the launcher, Workspace pages, Knowledge and chat. Keep field
labels, source status, review consent and actionable errors. Privacy/provider
processing, source deletion and invitation explanations remain in compact,
keyboard-accessible details. Public content and the native Filament UI stay intact.

- Personal and Workspace chat use the same compact shell with accessible History,
  New chat, Delete and Copy controls, including immediately after a first answer.
- Source links remain server-authorized. Chat text is formatted with DOM-created
  paragraphs, lists, emphasis and code; model HTML and URLs are never interpreted.
- Auto-growing input, IME-safe Enter, manual-scroll preservation, responsive
  heights, reduced motion and optional light/dark theme are supported.
- The browser stores only theme and reply-language preferences, never messages,
  uploaded text, Owner identifiers, private sources or credentials.

## Languages

The composer accepts both Burmese and English. `Auto` follows the latest question,
so the same conversation can switch languages. `my` and `en` select a preferred
reply language. An explicit request to use a different language in the latest
question takes precedence. Answers are concise by default and expand when asked.
This is a reply-language control, not a claim that every portal page is translated.

Laravel validates `reply_language` against `auto,my,en`, forwards only the
validated preference and builds all history/Workspace context on the server.
Workspace chat validation/authentication errors return JSON for the streaming
client. Saved questions and private retrieval queries retain the exact user text.

Original AI retry runtime: `908173283ff963fe6ee0dc6ff79933682afd74f1`.
Health advertises `chatInterfaceProtocol: 1`; an older service fails closed for
chat. The private Knowledge protocol, original partnership knowledge, embeddings,
OCR, source permissions and absence of external tools for private chat remain.

## Deployment and checks

`scripts/pbr-chat-update.sh REVIEWED_WEBSITE_SHA` accepts only the confirmed live
website/AI baselines or its already-installed exact pair. It allows the reviewed
UI/language files only, runs isolated server tests, verifies a fresh database and
application backup by restore, and separately archives/verifies the AI source,
shared cache and configuration.

There are no migrations, account updates, environment changes, business quotas or
resets. All existing rows (including private sources/vectors/chats and binary/JSON
values), schema and private original files must match before reopening. Live
source and cached/environment configuration must match. Visitors stay in
maintenance until native forms render, the original AI passes synthetic OCR and
cited generation, four real synthetic-only language checks pass, and HTTPS page,
authentication and exact CSS/JS asset-byte probes pass. No Owner content is sent
for testing. A failure permits paired code rollback only while the record snapshot
still matches; unexpected writes are retained under maintenance. No database or
uploads are restored over new data.

The disposable Actions fixture uses real MySQL backup/restore and record/file
checks. Its AI process/provider and front-door HTTPS responses are explicit
synthetic doubles; the server installer performs real provider/HTTPS checks.
The fixture covers retained ready Knowledge and originals, repeat installation,
AI restart failure, incorrect CDN assets, failed public probes, safe retry and
unexpected cutover writes.

Local checks: frontend build, JS syntax, CSS parse (222 chat rules), 11 existing
streaming-client checks and 14 new DOM interaction checks passed. The latter cover
language switching, source isolation, safe formatting/copy, immediate deletion,
stale chats, Burmese IME mobile keyboard resizing and manual scrolling. Original AI's 9 contract checks pass.
PHP 8.4, compiled Blade, the full application suite and deployment rehearsal run in
Actions. Signed-in desktop/mobile appearance and both reply languages need Owner
acceptance after the production receipt. Browser screenshots were not available
in this environment; DOM checks do not establish visual acceptance.


## Reviewed production package

- Website: `0af5c5902eb5cafc5c14c6a43d28b2edd93ce812`.
- Original AI: `48d978daeebb996f729cbf559914adc4c204381b`.
- Installer SHA-256:
  `efb6b5abf8c97638e1822a772066d9c9b19327c01b4f9ac2f10747b9c4ad556c`.
- Push CI passed: https://github.com/Ismail-wise/thepbr-website/actions/runs/39190998914
- PR CI passed: https://github.com/Ismail-wise/thepbr-website/actions/runs/39191003155
- PHP 8.4: **428 passed, 1 pre-existing skipped, 5,795 assertions**. PHP parse,
  compiled Blade, all deployment regressions and frontend build passed.
- Shared/chat CSS parsed: 564 rules, including 222 in the new chat stylesheet.
- The 14 DOM interaction checks also passed in Actions. Application dependencies
  remain unchanged; the DOM runner is temporary and pinned to jsdom 26.1.0.
- The MySQL chat release rehearsal passed successful/repeated installation,
  preservation of existing ready Knowledge, private originals and chats, paired AI
restart rollback, asset mismatch rollback, public-probe rollback, safe retry and
  retention of unexpected cutover writes under maintenance. Historical deployment
  regressions passed too; these ran only against disposable synthetic fixtures.

The original follow-up recorded this package's CI evidence. Its production attempt
on 19 October 2026 did not complete: the real provider language check returned
`CHAT_CHECK_FAILED=REPLY_LANGUAGE_MISMATCH`. The receipt reports successful paired
code rollback and retention of all portal records. A read-only server diagnosis
confirmed website `2567dc48a3d6d83565a7e078d1274cbf810a276d` and AI
`a0701941890e57267d2c4ab3ad39e1f0f284ae84` remain the live pair. The original
checker did not record which case failed, so its output cannot distinguish an
actual wrong-language explanation from a script-detection false positive.

- Failed-attempt receipt:
  `/root/thepbr-deploy-backups/chat-ui-release-20261019T025954Z-05320d49`.
- Verified backup from that attempt:
  `/root/thepbr-deploy-backups/pre-filament-admin-20261019T030125Z-923637c0`.

## Language-check retry

The original AI now puts the current turn's language rule after all multilingual
Knowledge and Workspace evidence. Selected English and Burmese explicitly apply
when the question, earlier turns or evidence use the other language. Citations and
business terms stay intact. Twelve local Node contract tests pass, including long
opposite-language evidence and repeated preference changes; these are not live
provider tests.

The release probe now counts Myanmar letters rather than all code points in the
Myanmar blocks. Previously an English answer containing the native amount `၄၂`
could falsely fail. Myanmar numbers and punctuation alone no longer count as
Burmese prose. Wrong-language prose, missing/failed streams, empty answers, wrong
Workspace amounts and missing exact source citations still fail closed. Focused
PHP regressions exercise those cases and a Unicode SSE body across read boundaries.

Each real synthetic provider case prints a fixed case identifier, expected
language, letter counts and success/failure code. No prompts, generated answers or
Owner content are printed. The installer exposes failures in the normal receipt
and identifies the language-probe stage. The disposable MySQL rehearsal tests this
failure specifically, including paired rollback, reopening and unchanged records.
It exercises the exact current checkout rather than the superseded package above.

The retry retains the same live baselines, fresh verified backup/restore drill,
four real synthetic language cases, OCR/embedding checks, source citations,
private-record verification and HTTPS/asset probes. No checks are bypassed. Retry
CI, real server execution and signed-in visual/bilingual desktop/mobile acceptance
must pass before this replacement can be treated as live and accepted.
