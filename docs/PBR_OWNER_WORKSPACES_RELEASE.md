# Owner Workspace and invitation foundation

This is the first functional release after the accepted premium UI. The owner
confirmed that UI on 18 October 2026. Owner-private knowledge ingestion and
Workspace-context AI follow this access foundation; they are not enabled or
advertised by this release.

An active Student creates multiple Workspaces with a required name and optional
short description. A nullable description column is the only additive schema
change. Business stage/currency are not invented. Creation sets the authenticated
creator as Owner atomically with its membership. The simple Overview and Partners
pages use the approved premium portal shell. Pagination is not a Workspace quota.

The Owner creates an existing email-bound, seven-day, single-use invitation link,
then copies and sends it personally. PBR does not claim to send email. Invitations
store token fingerprints, never raw tokens. Login and signup preserve the intended
acceptance page. The targeted account accepts without Student/Admin approval.
Neither role, is_admin, Student enrollment nor expiry is changed by acceptance.

Invitation-only Partners see only PartnerDynamics on their launcher and can take,
retake and view their own original assessment. They cannot create Workspaces, use
AI/calculators/library or read Owner content through new or legacy Workspace URLs.
Their permission metadata is partner_dynamics_only. Legacy broad permissions do
not bypass this restriction. Revoke/remove applies immediately; multiple accepted
memberships and independent Student entitlements remain independent. Student
expiry removes Student tools; a separate accepted invitation may still permit
personal PartnerDynamics. No other participant's assessment is exposed by an
invitation. Legacy Workspace team report/profile endpoints are unavailable in the new portal
until explicit result-sharing consent/scope is designed. Personal scoring stays
unchanged.

Application Admin continues original Filament account management and Article,
Video and Class Session publishing. Admin privileges do not bypass the private
Workspace Owner boundary. The existing personal Admin portal preview behavior
outside Workspaces is retained. This release does not change the original AI
service or PartnerDynamics scoring. Legacy mode (partner_portal.enabled=false)
retains its existing workflow.

The production baseline is premium release
2ef296b102c9dc6011ec319935a4db8cf6954fe2. Deployment must verify a fresh backup
and restore, rehearse the one additive migration on isolated MySQL, and compare
all pre-existing columns/rows before and after migration. Public/authentication probes use a temporary Laravel maintenance bypass cookie;
real visitors stay in maintenance until all checks pass. On failure, unchanged
records permit a code-only rollback while retaining the nullable additive column.
Unexpected cutover writes keep the new code in maintenance for review, protecting
the new access boundary and all data. The script never restores an older database
or runs the previous test reset. Signed-in live UAT remains separate
from automated application and disposable MySQL checks.
