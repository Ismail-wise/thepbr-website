# PBR Partnership Business Operating System V1

## Product goal

PBR Chapters 1–10 are one connected operating system, not a collection of unrelated calculators.

Flow:

`Capital → Ownership → Contribution → Distribution → Financial Controls → Governance → Exit → Continuity → Share Transfer → Dispute Resolution → PBR AI Advisor`

The system is Burmese-first, workspace-scoped, revisioned and permission-aware.

## Tool library

The course catalog contains 64 tools across 10 chapters. A workspace only displays tools that match its business stage (`new` or `existing`). Chapter 1 retains its specialized capital builders; Chapters 2–10 use the universal operating-tool engine and interface.

## Canonical data layers

### 1. Tool scenarios

`tool_sessions`

Private working scenarios owned by the user who created them. These are not business agreements.

### 2. Tool outputs

`workspace_tool_outputs`

Revisioned output per workspace/tool. Status can be:

- `draft` — working output, owner/admin only
- `agreed` — approved business rule that accepted partners may read

### 3. Chapter/domain snapshots

`workspace_operating_snapshots`

Revisioned connected state for these domains:

1. `capital`
2. `ownership`
3. `contribution`
4. `distribution`
5. `financial_controls`
6. `governance`
7. `exit`
8. `continuity`
9. `share_transfer`
10. `dispute_resolution`

An agreed snapshot never reads draft outputs. A working snapshot uses the latest authorized output for each tool, so a new draft may coexist with previously agreed tools without erasing them from the owner working view.

### 4. Operating records

`workspace_operating_records`

Append-style operational history for tools such as meeting decisions, transfers, dispute records and resolution actions.

### 5. Partner roster

`workspace_partner_profiles`

One shared partner identity layer for a business. It syncs the workspace owner and accepted members and also supports planned partners before invitation. It is a PBR planning identity layer, not a statutory ownership register.

## Permission model

### Owner / Admin

- Calculate
- Create and edit scenarios
- Save drafts
- Create draft workspace outputs
- Approve agreed business rules
- Manage planned Partner Roster entries
- Read working + agreed operating data
- AI may use authorized working business data

### Accepted Partner

- Read workspace
- Read latest agreed tool output only
- Read latest agreed operating snapshot only
- Cannot calculate, save, rename, duplicate, delete or approve scenarios
- Cannot access owner/admin Partner Dynamics report
- AI receives agreed tool outputs only; draft/private outputs are excluded

## Business-rule lifecycle

`Enter Data → Calculate / Review → Save Draft → Draft Output → Approve → Agreed Business Rule`

A calculation is never automatically treated as an agreement.

## Cross-chapter prefill

Prefills only use agreed operating snapshots plus authorized business sources.

Examples:

- Chapter 1 partner capital → Chapter 2 equity planning
- Valuation Base Estimate → Chapter 2 share/unit value
- Agreed ownership → Chapter 4 profit/loss starting scenarios
- Chapter 1 operating cost → Chapter 5 reserve/cash-flow planning
- Agreed ownership/voting → Chapter 6 voting simulation
- Valuation → Chapter 7 buyout and exit-value scenarios
- Valuation → Chapter 8 ownership-transition planning
- Ownership + valuation → Chapter 9 transfer simulation/value

Prefill is a convenience. The user can modify the scenario before saving/approving it.

## Logic guardrails

- Contribution share is not automatically ownership share.
- Ownership is not salary, profit share or work compensation.
- Equity Split Simulator produces a negotiation reference, not a mathematically “correct” ownership percentage.
- Share/unit value is indicative and not a legal share price or guaranteed transaction price.
- Buyout and transfer values are planning estimates.
- Voting tools apply user-configured rules and do not determine legal validity.
- Deadlock tools produce workflow signals, not legal findings.
- Issue Priority Matrix is a PBR triage aid, not an ISO-certified risk score.
- Death, disability, spouse, inheritance, insurance, tax and transfer rights vary by jurisdiction and require professional review where appropriate.
- Dispute tools structure facts and escalation; PBR does not decide who is legally right.

## AI integration

`PbrAiContextBuilder` receives:

- workspace context
- latest feasibility result
- latest valuation result
- authorized Partner Dynamics context
- authorized tool outputs
- connected operating-system snapshots
- existing Partner-AI RAG knowledge through the private AI service

Accepted partners are explicitly restricted to agreed tool outputs and agreed operating snapshots.

## UI architecture

The 10-chapter dashboard shows:

- available tool count for the current business stage
- agreed-rule completion
- connected domain status
- Chapter 1 capital summary
- Partner Roster shortcut for managers
- chapter progress
- tool cards
- AI Advisor bridge

Chapters 2–10 share a consistent interface:

- Business context
- Dynamic form / repeaters / checklists
- Calculate / Review
- Metrics and detailed tables
- Warnings and business notes
- Saved scenarios
- Revision history
- Draft Output
- Approve as Agreed Business Rule
- Partner read-only agreed view

## Production deployment rule

Never deploy this branch directly without the one-pass validation/deployment script. Pre-deployment validation must pass using an isolated SQLite testing database before production code or database is changed. Production deployment must create a verified database backup and rollback branch first, and maintenance mode must always be released by the failure trap.
