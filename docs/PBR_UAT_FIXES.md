# Partner Portal user testing fixes

Addresses the seven findings in GitHub issue #49. Installation starts from
website `0694d95e09f0f20218c762df2c47a1bcc2fa85c5` and original Partner AI
`908173283ff963fe6ee0dc6ff79933682afd74f1`. It does not migrate or reset data.

| Finding | Result |
| --- | --- |
| Auto reply language | Original Partner AI resolves English/Myanmar from the latest question, then places the specific language rule after multilingual evidence. Explicit language requests still take precedence. |
| Repeated list number 1 | Blank lines and indented descriptions keep one ordered list. Separate topics start a separate list. |
| Photo upload 413 | Inspect nginx's active configuration, plan and back up the exact app vhost changes, allow a 12 MiB request with a 10 MiB PHP file limit. Client and application enforce the configured Knowledge file limit. |
| Approval leaves the old form | Successful approval returns to the Knowledge list. Add Knowledge opens an empty form. The approved source stays Ready. |
| S1/S2 in answers | Strip machine citation labels from displayed, streamed, restored and copied answer text. Keep source metadata and readable source titles in the collapsed Sources panel. Stored original answers remain intact. |
| Sent question stays in composer | Clear on submission. Preserve a new draft while awaiting the response; restore a failed, unaccepted question for retry. |
| History cannot continue | Resume the same authorized thread. When Knowledge changes, start a fresh model context inside that thread; preserve visible old messages without re-sending their obsolete facts. Subsequent follow-ups use only messages after that reset boundary. |

## Validation

- DOM interaction checks cover loose lists, source label chunk boundaries,
  copying, immediate clearing, failure recovery, unsent drafts and History.
- PHP tests cover approval redirects, empty new forms, upload validation,
  context refresh boundaries, failed refresh recovery and private isolation.
- Original Partner AI tests cover Auto resolution after Burmese evidence.
- Release checks use synthetic English/Myanmar questions including language
  switches and preserved multilingual context. No live user data is used.
- Disposable MySQL deployment tests cover records, schema, file preservation,
  paired code rollback and scoped nginx configuration rollback.

## Deployment

Use the reviewed `scripts/pbr-chat-update.sh` pinned to the release commit.
It runs isolated tests and creates a fresh verified app/database/AI backup
before the switch. nginx configuration is held privately in the release
receipt, with concurrent-change checks and rollback. Only thepbr.io's own
PHP locations are changed. Other hosts and global PHP settings remain intact.
Custom PHP overrides, shared hostnames, inherited FastCGI parameter sets and
unrecognized configuration layouts stop for review before maintenance.

The 10 MiB multipart probe is unauthenticated and has no CSRF token. It must
reach the auth/CSRF boundary; it does not create an account, source or chat.
This verifies request admission, not signed-in photo extraction. After a
successful release, retest the actual receipt upload and review, language
switches, lists, source display, composer and History in the signed-in UI.

Old answers are preserved as historical messages. After Knowledge changes,
a vague question that depends only on an obsolete answer may need to be
restated; withdrawn source content is not replayed to the provider.
