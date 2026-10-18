# Accepted next feature scope: private business knowledge

The owner agreed the following functional design on 18 October 2026. It is not
yet activated by the presentation-only release.

- Public website, shared signup/login and Admin-approved Student duration remain.
- An active Student may create multiple Workspaces. Workspace name is required;
  a short business description is optional. Creation establishes its Owner.
- Each Workspace has Overview, Knowledge, PBR AI and Partners sections.
- Text notes, photos and PDFs are privately ingested, extracted and reviewed by
  the Owner before becoming Workspace knowledge. Readiness is separate from a
  document's business status (draft/current/historical).
- Existing partner-ai core knowledge remains shared background expertise;
  per-Workspace source content, retrieval, derived answers and chat histories are
  Owner-only, including against ordinary application Admin and invited partners.
- The selected Workspace is explicit in chat. Different Workspaces stay separate,
  including Workspaces owned by the same person. Owner source citations link to
  authorized private originals. AI proposals do not silently become current facts.
- Site Admin manages account access, duration and usage; account-management rights
  do not automatically grant access to private Workspace knowledge.
- Owner invitations live in Workspace > Partners, with pending/accepted/revoke
  and removal controls. A valid invited Partner signs in/registers and accepts
  without waiting for Admin Student approval.
- Invitation-only Partners may use PartnerDynamics only. They receive no Student
  tool access, general/workspace AI access, private knowledge access or Workspace
  creation rights. Existing Student entitlements survive invitation acceptance;
  becoming a Student later requires the normal Admin access grant.

Before functional implementation, map these rules across existing legacy routes,
membership checks, invite callbacks, inference requests, file delivery, retrieval
and chat storage. Preserve original PartnerDynamics scoring. Define the precise
scope/consent of PartnerDynamics result sharing, file retention and operational
quotas without inventing numerical limits or granting broad Admin bypasses.
