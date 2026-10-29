# Documentation review and corrected dashboard scope

Reviewed: 19 October 2026. Analysis and implementation planning only; no application changes authorized by this review.

## Documents reviewed and authority

| File | Contents and use |
| --- | --- |
| `PTMLAUNCH_Document_12_USA_UK_Launch_Scope_Overlay.pdf` | All 13 pages reviewed. Launch scope, architecture obligations, four-week gates and acceptance. Refers to Documents 1–11 as authoritative. |
| `PTM_Document_13_Existing_Build_Reuse_Modification_Guide (4).pdf` | All 4 pages reviewed. Requires source audit and KEEP / KEEP + CONNECT / MODIFY / REPLACE / NEW / REMOVE / VERIFY classification before rebuilding. Does not override Documents 1–12. |
| `WhatsApp Image 2026-10-19 at 21.53.25.jpeg` | Visible email excerpt recommends reusing Parent/Teacher mobile screens for Staff/Admin where compatible. The remainder is obscured/cropped; no hidden text is inferred. |
| `current-state.md` | Legacy implementation inventory and October 7 fixes. Useful for code audit, not product scope authority. Earlier tables are historical and partly superseded by its own fix-status section. |
| `DEPLOYMENT.md` | Existing runtime/deployment instructions, providers and operational constraints. Does not demonstrate the Document 12 recovery/acceptance gates have passed. |
| `postman.json` | Parsed collection: 513 requests, comprising 187 mobile API and 326 web requests; 286 GET, 156 POST, 45 DELETE, 26 PUT. These are collection counts, not verified current registered-route counts. |
| `four-role-dashboard-plan.md` | Initial code-led proposal from this conversation. Requires correction against the PDFs; the corrections below take precedence. |
| `index.md` | Empty before this review. Populated with the documentation map. |

Documents 1–11 are absent from this folder. Their detailed requirements cannot be reconstructed from the overlay. Their absence prevents a complete requirement-by-requirement compliance statement and final estimates; useful preliminary source audit and planning can continue.

## Corrections to the initial dashboard plan

1. **Four onboarding entrances, one identity.** Parent, Teacher, Staff and Admin/District are onboarding doorways, not fixed backend user types or separate role passwords. After signup use universal sign-in; resolve effective roles, organizations, relationships, permissions and selected context server-side. Document 13, pages 2 and 4; Document 12, sections 4–5.
2. **Reuse the mobile shell.** The approved foundation is Home | Messages | Schedule | Events | Profile. Different roles get different Home cards, permissions and actions within this shared foundation. Do not assume four new apps or entirely separate navigation systems. Document 13, pages 2–4.
3. **Staff is permission-generated.** Essential staff cards are L/A. Specialized staff/admin mobile tooling is E. The proposed office/finance bundles, task system, document queue and attendance correction product were assumptions, not requirements established by the supplied specifications. Keep them VERIFY/proposals until mapped to Documents 1–11. Document 12, sections 4–5.
4. **School and District are distinct launch contexts.** Both essential web experiences are L. District administration cannot be silently postponed. Mobile Admin/District is an operational companion; heavy management remains web-first. Standalone Schools must work without a District. Document 12, sections 4, 6–7.
5. **Student is an entity, not a parent-owned object.** Student-Guardian linkage is verified, effective-dated and revocable. The parent journey requires school approval before the student appears. Existing child CRUD cannot be assumed compliant. Student login is F; preserve legacy data and assess compatibility without presenting Student login as launch scope. Document 12, sections 5, 7, 17; Document 13, pages 3–4.
6. **Student Updates are core academics.** Support institution-configured Student Update and/or Formal Grade modes. Parents see published records only; corrections preserve history. Legacy exams, assignments, attendance and school-fee modules need explicit mapping before being promoted into mandatory launch panels. Document 12, sections 5, 7, 16; Document 13, page 3.
7. **School suggestion is not organization creation.** Replace parent '+ADD School' behavior with Suggest School/request. Official organization creation is institution-controlled. Document 13, pages 3–4.
8. **Video and minutes must be first-class.** Scheduled and instant Agora calls, join-time authorization, reconnect/failure/history, institutional coverage, immutable minute ledger and entitlement/usage separation are launch scope. Recording stays OFF. Freeze billing unit/rounding policy before production. Document 12, section 8.
9. **Commercial scope is subscription/entitlement-led.** Stripe and PayPal, configurable plans/trials/minutes, institutional coverage and AD_FREE_MOBILE are L. The $55/year, 5-day trial, 5,000-minute example is not a hard-coded product rule. School tuition fee management is not established as a launch requirement by these documents. Document 12, sections 8, 13; Document 13, page 3.
10. **Use the approved launch integrations.** ZeptoMail is expected for transactional email; legacy SendGrid is current implementation evidence, not the final provider requirement. FCM/APNs, private scanned storage, monitoring, AdMob eligibility/kill switch and reliable lead capture have explicit scope. Document 12, sections 9–10.
11. **Include all web operator contexts.** Super Admin, School Admin, District Admin and basic scoped Sub-Super delegation are in the overlay. Sub-Super is not school staff. Permission changes cannot enable privilege escalation. Document 12, section 6.
12. **Four-week planning needs evidence, not a promise.** Every L/A item needs week, named owner, dependency, deliverable, demo, QA method and evidence. Do not invent owners, demo dates or claim the complete scope fits before auditing missing specs, client source and resources. Document 12, sections 15–20.

## Correct four mobile Home experiences

All use the shared navigation, universal account and context switcher. Cards and actions depend on effective backend authorization and current relationships, not merely the selected onboarding label.

| Home | Essential panels supported by supplied documents | Main actions / boundaries |
| --- | --- | --- |
| Parent | Verified students; contextual messages; schedule; video/history; published academic updates; events and announcements; applicable plan, entitlement and minutes | Request verified student relationship; contact permitted participants; request/reschedule/cancel meeting; join authorized call; view published updates; inspect plan/minutes. No automatic student ownership or official-school creation. |
| Teacher | Assigned classes/students; messages; schedule; video; academic reporting; events/announcements | Work within active assignments; draft/submit/publish Student Updates as permitted; manage availability/appointments; communicate and call authorized participants. Recheck relationship at action time. |
| Staff | Essential cards generated from organization permissions using reusable components | Only delegated communications, scheduling, event and operational actions that source specifications authorize. No inferred authority from job title; specialized tooling is E unless separately required. |
| School / District Admin | Contextual operational summaries; communications; schedule/events; usage and authorized operational shortcuts | Switch authorized organizations/schools, act within delegation, use web panel for heavy management. School and District contexts resolve different scope. |

Profile must include Roles & Organizations, actual Security controls, notification settings, applicable Subscription/Minutes and Support/Privacy. Privacy Policy alone is not an account Security screen. Events and Announcements remain separate domain concepts; News need not be a separate backend.

## Correct web panel families

| Panel | Launch controls from Document 12 section 6 |
| --- | --- |
| Super Admin | People/organizations, video/usage, communications, imports/exports, billing, support, integration health, audit, settings and monetization basics |
| School Admin | School dashboard, people, academic structure/reporting, communications, imports/invitations, usage, support and school settings |
| District Admin | District dashboard, schools/admins, people, academic structure, communications, imports/invitations, usage, support and scoped settings |
| Sub-Super | Explicit scoped PTM delegation without privilege escalation |

Keep the compatible existing web visual shell. Source audit decides what to retain, connect, modify or replace. Parent/Teacher/Staff mobile Home requirements alone do not establish a requirement for three additional full web admin suites.

## Preliminary reuse register

These classifications are provisional. The retained Flutter source must be inspected before classifying its screens as reusable or absent.

| Existing area | Classification | Reason / next evidence |
| --- | --- | --- |
| Blade dashboard, header, sidebar, tables/forms | VERIFY / MODIFY | Existing source exists; reuse visual primitives but scope content/actions correctly. Preserve current uncommitted UI changes. |
| Parent/Teacher Flutter Home and shared navigation | VERIFY | Flutter source was not identified in this workspace; cannot conclude screens are absent. |
| Login UI | MODIFY + CONNECT | Reuse compatible visuals; replace fixed-role assumptions with universal identity/context resolution. |
| `users.type` authorization | REPLACE | Contradicts role/organization/permission architecture. Migrate existing accounts safely. |
| Principal / school relationships | MODIFY | Useful starting data; add effective relationship/context authorization and proper district/standalone models. |
| Parent/child links | REPLACE conflicting ownership / MODIFY presentation | Verified Student-Guardian entity model required; preserve history and reconcile identifier inconsistencies. |
| Report cards | MODIFY | Add configured modes, publishing/approval and correction history. |
| Meetings/chat/Agora UI | VERIFY / MODIFY + CONNECT | Reuse client screens if compatible; reauthorize participants/context, add full approved state handling. |
| Subscription/minutes | MODIFY + CONNECT | Configuration, institutional entitlements and immutable usage ledger required. |
| Parent school creation | MODIFY / REMOVE old behavior | Suggestion/request replaces official organization creation from parent UI. |
| Security/profile | VERIFY / MODIFY | Actual MFA/admin, sessions/devices and password controls required. |
| Staff/district/delegation backend | NEW where audit confirms absent | Do not duplicate compatible existing UI; implement missing organization and permission capabilities. |
| Legacy student login/exams/tuition fees | VERIFY launch mapping | Student login F; other legacy modules require authoritative source mapping. No destructive removal during planning. |

## Four-week planning alignment

This is a proposed dependency sequence, not a delivery commitment or completed tracker. Named engineering/QA owners, calendar demo dates, capacity and exact Documents 1–11 references remain to be supplied.

| Week | Required gate | Dashboard and backend planning focus | Evidence required |
| --- | --- | --- | --- |
| 1 | Foundation | Source/reuse audit; universal identity/MFA/sessions; organizations/roles/permissions; standalone School/District; markets; migrations; isolated environments; release/backup foundations | Reuse register; schema/API decisions; automated authorization tests; environment/repository/release evidence |
| 2 | Core Product | Verified guardians; Student Updates/configured academics; messaging/scheduling/notifications; shared mobile contexts; School/District core panels; invitations/imports; USA/UK configuration | Parent/teacher/admin journeys; negative relationship tests; import validation/preview/results |
| 3 | Commercial & Integration | Agora/history; ledger/entitlements; Stripe/PayPal; private files/scanning; exports; ad-free/AdMob; email/push; Super Admin/Sub-Super | Provider success/failure/replay tests; reconciled ledger; export/file authorization; scoped delegation evidence |
| 4 | Production Acceptance | Website/leads/resources/affiliates; support/audit; regression; isolation/accessibility/performance/outage tests; restore/handoff | Passed acceptance journeys, monitored restore evidence, deployment/runbooks and Planned vs Actual register |

QA runs every week. The complete launch is materially broader than four dashboards. B2B website, resources/affiliates, support, integrations and recovery must remain in the launch register even if implemented as separate workstreams. Any proposed deferment of L/A needs explicit product-owner approval under Document 12; no deferment has been approved or made here.

## Gaps and contradictions to track

- Documents 1–11 are missing; exact permission chains, academic schema/workflow, retention and full acceptance requirements remain unavailable.
- Retained Flutter source is not present in the inspected workspace; Document 13 requires checking it before replacing client screens.
- Document 13 says previous backend source was lost, but this workspace contains a substantial Laravel backend. Treat its provenance and compatibility as VERIFY; neither automatic wholesale replacement nor automatic retention follows from this discrepancy.
- `current-state.md` includes historical route/security/test statements superseded by its October fix table. Do not use old counts or issues as current verification.
- Postman tests only assert no 5xx/no 404 and capture login tokens. A wrong 200, unauthorized data disclosure, missing tenant scope or payment/ledger defect can pass those assertions. It is a route collection, not launch acceptance evidence.
- Deployment notes describe existing production configuration, including chat built without grpc at that recorded point. Current live state was not checked. The notes do not establish isolated environments, MFA, restore tests or required provider readiness.
- Initial runtime checks failed due to local MySQL connection refusal. This does not prove production failure; it limits local verification.
- Owners, QA assignments, start date, provider readiness, legal review, rounding policy and recovery targets are not supplied. Record these dependencies rather than inventing decisions.

## Next planning output

Use this review as the baseline, then complete a source-to-requirement reuse/gap matrix and the Document 12 tracking register. Each row needs source document/section, L/A/E/F, role/context, existing component/API, reuse status, required change, dependency, week, owner, demo, QA, evidence, implementation status and product acceptance. Keep code findings separate from approved product requirements and proposals.
