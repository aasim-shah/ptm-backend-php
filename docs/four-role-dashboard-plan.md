# Four role dashboards: backend analysis and implementation blueprint

> Superseded for web role architecture on 21 October 2026: see [four-panel client feedback implementation](client-feedback-architecture.md). Parent/Teacher web access is removed; Staff requires explicit authorization. Earlier role/demo claims below are historical.
> **Superseded planning assumptions:** Read [document-review.md](document-review.md) first. The subsequent review of Documents 12–13 corrects universal sign-in, shared mobile navigation, launch District scope, verified Student-Guardian links, permission-generated Staff cards and reuse obligations. Unmapped panels in this initial proposal are proposals, not approved launch requirements.

Date: 19 October 2026. Status: proposed implementation, based on repository inspection. This document does not mean the dashboards have been implemented.

## 1. Outcome and scope

Build four genuinely different workspaces for Parent, Teacher, Staff Member, and School / District Administrator. Each workspace has its own navigation, summary metrics, work queues, forms, and authorized actions. The signed-in account determines access. The four buttons in the supplied reference choose a login experience; clicking one never grants a role.

Keep Super Admin as a separate platform operator workspace. A school administrator must not inherit platform-wide settings, payment gateway credentials, other schools' records, or permission to create Super Admins. Preserve the existing Student API; it is outside the four requested dashboards.

Recommended implementation: extend this Laravel application with separate Blade views and shared application services for the web and Sanctum APIs. No framework rewrite is needed for role separation. The repository contains the backend and web admin UI, but no complete parent/teacher mobile client source was identified. Native mobile screens would need their client repository.

Staff is assumed to mean non-teaching school employees. Initial proposed staff permission bundles are office operations, attendance support, and finance; assign only the bundles a person needs. District support is new work, not an existing backend feature.

## 2. What exists now

| Area | Verified code state | Consequence |
| --- | --- | --- |
| Application | Laravel 9, MySQL, Blade/jQuery, Vue 2 assets, Sanctum, Spatie permissions | Extend existing modules and contracts |
| Web login | `LoginController::loginWeb` permits only Spatie Super Admin / Principal roles | Parent, teacher, and staff web login must be added deliberately |
| Web route gate | `CheckRole` protects the current administrative route group | Do not simply open the entire group to four roles |
| Home dashboard | `HomeController::index` provides counts, teachers and announcements for Super Admin / Principal | There are not four role dashboards today |
| Parent | Child, attendance, report, assignment, timetable, exam, fee, meeting and chat API routes exist | Reuse after ownership and response checks; web panels still need building |
| Teacher | Classes, student requests, attendance, reports, assignments, lessons, marks and communication APIs exist | Reuse after assignment and school authorization checks |
| Staff | No dedicated staff role seed, membership workflow, API group or dashboard found | New backend and UI work |
| School administrator | Principal role and school-scoped queries exist | Evolve Principal into the school administrator experience |
| District administrator | No district membership model or district dashboard found | Add district scope and explicit school membership |
| Platform operator | Existing Super Admin panel | Keep separately authorized |
| Runtime baseline | Route enumeration and feature tests encounter MySQL connection refused | Static findings are confirmed; runtime workflows are unverified |

Existing `docs/current-state.md` contains useful history, but its older sections describe issues subsequently fixed. The source code and its October fix-status table take precedence over old inventory numbers.

## 3. Parent dashboard

Purpose: understand each child's progress and communicate with the school.

Navigation: Overview, My Children, Attendance, Learning, Progress Reports, Meetings, Messages, Fees, Notices, Profile.

| Panel | Data / display | Controls and actions | Access boundary |
| --- | --- | --- | --- |
| Child switcher | Linked children, school, class, enrollment status | Select child; add child; request class enrollment; cancel pending request | Only authenticated parent's verified links |
| Today | Selected child's timetable, attendance state, upcoming deadlines | Open schedule, attendance detail, assignment | Selected child only |
| Attendance | Present/absent history and period totals | Filter date range; view detail; submit absence explanation if enabled | Parent cannot mark attendance |
| Learning | Published assignments, lessons, resources, due dates | View/download learning material and submission status | No grading or assignment editing |
| Progress | Published quarterly reports, subject results, teacher feedback | Select term; view/download published report | Draft reports never returned |
| Meetings | Pending requests, accepted appointments, local time | Request, reschedule/cancel subject to policy; accept/decline invitations; join authorized call | Participant-bound meeting and call token |
| Messages | Authorized teacher conversations and unread counts | Send/read messages; supported attachment upload | Child-linked teachers or school-approved contacts |
| Fees | Outstanding invoices, payment state, receipts | Initiate checkout, inspect confirmed transaction, download receipt | Own child's ledger; server-confirmed payment state |
| Notices | Relevant school/class announcements | Open notice; mark own notification read | Audience and school filters enforced |
| Profile | Own contact details and preferences | Update profile/password and notification preferences | No role, school or child-link privilege editing |

Parent landing priority: child selector, today's status, upcoming work, next meeting, unread notices. Missing enrollment should show enrollment steps, not zero-filled academic metrics. Payment failure shows a retry path without marking an invoice paid.

## 4. Teacher dashboard

Purpose: run assigned classes and complete teaching work.

Navigation: Overview, My Classes, Attendance, Assignments, Lessons, Reports & Marks, Enrollment Requests, Timetable, Meetings, Messages, Class Notices, Profile.

| Panel | Data / display | Controls and actions | Access boundary |
| --- | --- | --- | --- |
| Teaching day | Assigned periods, rooms, next class | Open class roster; view timetable | Assigned school/class/subject |
| Attendance queue | Assigned sections missing today's register | Mark present/absent; save; correct with reason | Class teacher or explicitly delegated attendance permission |
| Classes | Class/section rosters and subjects | View authorized students; open parent contacts | Class teacher and subject teacher rules are distinct |
| Enrollment requests | Pending requests to own classes | Inspect; accept/reject; record outcome | Validate requested section and student, not just teacher role |
| Assignments | Published/draft tasks, due dates, ungraded submissions | Create/edit/publish task; attach material; grade/return submission | Assigned subject and section |
| Lessons | Lessons, topics and resources | Create/edit/publish own teaching material | Assigned teaching context |
| Reports and marks | Draft reports, pending marks, published progress | Record marks; draft/submit report; publish only if permitted | Authorized subject/section/term |
| Meetings | Own parent appointments and invitations | Propose slots; accept/decline; reschedule; join call | Participant or authorized organizer |
| Messages | Authorized parent conversations | Send message; mark own messages read | Relevant family/class relationship |
| Notices | Own class/subject notices | Draft/publish/update within delegated rights | No school-wide broadcast by default |

Teacher landing priority: next class, attendance to complete, pending enrollment, ungraded submissions, reports due, today's meetings. No school-wide student exports, staff administration, finance management or platform configuration.

## 5. Staff dashboard

Purpose: complete assigned administrative work without teaching or administrator privileges.

Navigation: Overview, My Tasks, Student Services, Attendance Follow-up, Calendar, Communications, and Finance only when explicitly assigned.

| Panel | Data / display | Controls and actions | Required permission / scope |
| --- | --- | --- | --- |
| Work queue | Assigned tasks, deadlines, status, escalation | Claim assigned/unassigned eligible task; update status; add note | `tasks.view`, `tasks.update`; assigned school and department |
| Student services | Authorized enrollment/document requests | Review documents; request missing information; route for approval | `student-services.manage`; limited student fields |
| Attendance follow-up | Missing registers and absence follow-up list | Contact guardian; add follow-up note; flag issue | `attendance.follow-up`; no academic editing |
| Attendance correction | Correction requests where delegated | Propose or apply correction with reason and audit | Separate `attendance.correct`; school scope |
| Calendar and reception | School events and approved appointment information | Schedule authorized appointment; update logistics | `calendar.manage`; sensitive notes excluded |
| Communications | Approved recipients, templates, delivery state | Draft notice; send only with delegated broadcast permission | `communications.draft` / `communications.send` |
| Finance | Due invoices, verified collections, reconciliation items | Create invoice; reconcile verified transaction; issue receipt | `fees.view`, `fees.manage`; assigned schools |
| My profile | Own identity and preferences | Update profile, password, notification settings | Own account |

Finance staff must not gain access to grading, private teacher notes or unrestricted parent conversations. Office staff must not gain payment gateway settings. Refunds and fee waivers require separate permissions and configured approval rules.

New backend work: staff invitations/memberships, task queue, document requests, attendance follow-up/correction records and permission bundles. Do not label these working features until implemented and exercised.

## 6. School / district administrator dashboard

Purpose: manage school operations and resolve exceptions. School and district administration share a workspace family with different scope.

Navigation: Overview, Schools (district only), People, Academics, Attendance, Approvals, Communications, Calendar & Meetings, Finance, Reports, Staff Access, School Settings.

| Panel | Data / display | Controls and actions | Boundary |
| --- | --- | --- | --- |
| Scope selector | Assigned school, or authorized district schools | Switch authorized school; district aggregate view | Validate each requested school server-side |
| Operational summary | Active enrollment, staff/teacher counts, attendance coverage, unresolved approvals | Drill into filtered underlying records | Scope/date/term retained on every drill-down |
| Approvals | Enrollment, corrections, report publication, fee exceptions as enabled | Approve/reject with reason; bulk action only when safe | Separate permission per approval type |
| People | School students, guardians, teachers, staff | Invite; assign; suspend school membership; manage enrollment | Cannot grant platform privileges |
| Academics | Classes, sections, subjects, assignments, timetable, term configuration | Assign teachers; manage class structure and schedule | Assigned school; validate cross-linked records |
| Attendance | Daily coverage, absences, missing registers, correction requests | Follow up; approve corrections; export permitted reports | School-scoped; correction history retained |
| Communication | School notices, targeting, delivery outcomes | Publish notice; approve staff drafts; send authorized broadcast | Recipient audience must be inside authorized scope |
| Meetings | Scheduling overview and school events | Manage logistics; resolve scheduling conflicts | No automatic access to private message/call content |
| Finance | Outstanding invoices, confirmed collections, exceptions | Configure fees; approve adjustments; authorized finance export | School finance permission; no platform gateway secrets |
| Reports | Enrollment, attendance, learning and finance summaries | Filter; compare authorized schools; export | Minimum necessary fields, audited export |
| Staff access | Members and school-specific permission bundles | Invite/revoke staff; assign bounded permissions | Grantable permissions limited to actor's authority |
| School settings | School profile, term, timezone, working days | Update school configuration | No system updater, global integrations or Super Admin management |

District mode additionally supports authorized multi-school comparisons, per-school drill-down, district notices and school-leader membership management. District role alone grants no access to schools outside the district assignment. Add explicit district entities/memberships; do not reinterpret Super Admin as district administrator.

## 7. Permission matrix

All entries are additionally constrained by school membership, resource ownership, published state and active account status.

| Capability | Parent | Teacher | Staff | School admin | District admin |
| --- | --- | --- | --- | --- | --- |
| View student academic record | Linked child, published | Assigned teaching context | Only separately delegated fields | Own school | Assigned district schools |
| Mark attendance | No | Assigned register | Explicit delegation | Explicit permission | Explicit permission within scope |
| Edit marks / reports | No | Assigned subjects | No | Explicit academic permission | Explicit academic permission |
| Enrollment | Request for own child | Decide assigned class requests | Prepare/review if delegated | School approval | Authorized school approval |
| Send messages | Authorized contacts | Authorized contacts | Approved operational contacts | Own authorized conversations | Own authorized conversations |
| View private chats/calls | Participant | Participant | Participant | Participant | Participant |
| Pay fees | Own child's invoices | No by default | No by default | No by default | No by default |
| Manage fees | No | No by default | Finance permission | Finance permission | Finance permission within scope |
| School broadcast | No | No by default | Explicit permission | Own school | Authorized schools |
| Manage staff access | No | No | No | Bounded school grants | Bounded district grants |
| Global credentials / system updates | No | No | No | No | No |

## 8. Authentication and routing design

1. Reference screen: choose Parent, Teacher, Staff Member, or School / District Administrator, then enter credentials. Include account recovery. Administrator and staff accounts are invitation-managed; public signup remains restricted to supported public roles.
2. Authenticate and verify account state; load authoritative roles and approved memberships. Do not accept role or school grants from login/profile payloads.
3. Resolve allowed workspaces on the server. A user with one role lands directly in its dashboard; a user with multiple roles chooses among existing authorized memberships. Keep selected workspace and school as context, not as proof of authorization.
4. Require explicit membership approval for school-controlled staff/teacher powers. Self-registering as teacher must not grant school access.
5. Reject inactive accounts, revoked memberships, unsupported contexts and unauthorized role selections. Rotate the web session at login; invalidate session/token access appropriately on revocation.
6. Separate authenticated dashboard routes from administrative management routes. Parent/teacher/staff access must never be implemented by merely widening `CheckRole`.
7. Preserve existing `/home` behavior for current administrators during migration; route other authorized users to their own workspace after login.

Proposed web entry points: `/dashboard/parent`, `/dashboard/teacher`, `/dashboard/staff`, `/dashboard/administrator`; existing platform `/home` remains separately protected. Shared profile/logout routes belong to a normal authenticated group rather than an admin-only group.

Proposed additive API contracts:

- `GET /api/me/workspaces`: allowed role contexts, memberships, permissions and authorized schools.
- `GET /api/dashboard`: role-specific summaries for the validated workspace; optional school/date/term/child/section filters.
- `POST /api/workspace/select`: validate the chosen membership/context; no role assignment.
- Module-specific action routes invoke policies and application services; dashboard links are not authorization.

Responses should contain `context`, `summary`, authorized `panels`, authorized `actions`, pagination metadata where applicable and an `as_of` timestamp. Omit forbidden payload fields entirely. Use bounded recent lists and drill-down pagination; do not return every teacher/student in a dashboard response.

For new endpoints use consistent 401/403/404/422 responses. Avoid changing old mobile response envelopes until the mobile client is checked and migrated.

## 9. Backend findings to resolve before rollout

These are code findings, not claims that live exploitation was tested.

| Finding | Source | Required change |
| --- | --- | --- |
| Principal bypasses school ownership in shared student checks | `app/Support/Access.php::canViewStudent` returns true for any Principal | Resolve student school and require authorized school membership |
| Principal attendance bypass | `Api/v2/AttendanceController` skips class ownership for any Principal | Apply class-section and school policy to every caller |
| Enrollment acceptance lacks an explicit assigned-class check in inspected method | `Api/v2/StudentController::acceptOrRejectStudentFromClassSection` | Resolve pending request within actor's authorized section; validate status enum; transaction and concurrency checks |
| Attendance resolves the first section of a class | `Api/v2/AttendanceController` queries `ClassSection::where('class_id', ...)->first()` | Require an explicit authorized section and validate every submitted student belongs to it |
| Teacher ownership helper covers class teachers only | `Access::teacherOwnsClass` versus subject assignments in `SubjectTeacher` | Define separate attendance, subject teaching and roster permissions |
| Parent link identifiers are inconsistent | `Parents` relations use default parent model key; `Students::father/mother` use `user_id`; guardian uses default key | Audit stored links; normalize one key contract and migrate carefully before changing relations |
| Announcement writes rely on permission without explicit school ownership in inspected methods | `AnnouncementController::store/update/destroy` | Authorize target school and loaded announcement on every write |
| Web profile update trusts submitted account ID | `HomeController::updateProfile` uses `User::find($request->id)` | Own profile uses authenticated user; management updates use a separate policy-protected endpoint |
| API profile update assigns password directly | `Api/AuthController::updateUser` assigns request password; no password mutator found on User | Use validated dedicated password flow and hashing; test stored password and login behavior |
| API type and Spatie role can independently authorize | `EnsureUserType` accepts either a matching `users.type` or role | Introduce authoritative role/membership resolution; migrate mismatches without breaking existing clients |
| Login does not explicitly check active status in inspected handlers | Web `loginWeb`, general API `loginUser` | Enforce account status consistently at login and authenticated access |
| No staff/district authorization boundary | Seeders, routes and membership models inspected | Add membership model, policies and constrained provisioning |

School context should be derived consistently from the actual class/school relationship. The repository currently uses both class and section relationships; identify and repair inconsistent data before choosing a canonical path. A user-school link by itself must not grant every role in that school.

## 10. Data and service design

Retain current academic tables and existing role records. Add only the structures required for missing behavior:

| Structure | Minimum responsibility |
| --- | --- |
| School memberships | User, school, role, active/pending/revoked state, approved by/at; school-bound permission assignments |
| Districts and district memberships | District identity, authorized administrators; schools linked to districts |
| Staff profiles | Employee identity/department attached to user; access remains in memberships |
| Staff tasks | School, assignee, kind, due date, status, reference resource and notes |
| Enrollment requests | Explicit student/section/requester/state and decision history if current state fields are insufficient |
| Attendance corrections | School/section/student/date, old/new values, reason, requester, approver, outcome |
| Document requests | School, student, request kind, review state, private attachment references |
| Activity audit | Actor, context/school, action, subject, timestamp, approved before/after metadata; exclude secrets and private message bodies |

Do not extend the global Spatie roles into school-specific grants without a clear scoping strategy. A user may be teacher in school A and parent in school B. Use membership policies and scoped capability resolution; global role checks cannot safely express this alone.

Recommended application boundaries: WorkspaceResolver, SchoolAccess, ParentDashboardService, TeacherDashboardService, StaffDashboardService, AdministratorDashboardService, and policies for student/class/assignment/attendance/report/meeting/announcement/fee/membership resources. Share rules between web and API handlers.

Transactions must cover enrollment decisions, attendance replacement, fee adjustments and permission changes. Queue notifications after commit and make delivery retries idempotent. Validate uploads by type/size, store student documents privately, and authorize downloads.

Indexes should follow measured queries: active memberships by user/school/role, students by section/status, assignments by section/subject/due date, and tasks by school/assignee/status/due date. Inspect existing duplicates and actual column types before adding uniqueness constraints.

## 11. Metric and UI rules

- Label every metric with school/child/section, period and relevant timezone. Use school timezone for school-day boundaries and user timezone for appointment display.
- Attendance coverage is completed required registers divided by scheduled registers. Student attendance rate is present divided by recorded applicable sessions; show missing records separately. Do not count unrecorded attendance as absence.
- Ungraded submissions count submitted work awaiting review; exclude drafts and withdrawn submissions.
- Fee collections come from server-confirmed successful transactions; distinguish outstanding invoices, partial payments, refunds and failed attempts. Do not sum different currencies without a defined conversion policy.
- District totals must deduplicate students/teachers as appropriate and explain whether figures count people or memberships.
- Show genuine empty, loading, validation, forbidden, offline/service-error and success states. An unavailable integration is not a zero count.
- Use distinct role navigation and page composition. Parent is child-centered; teacher is class/task-centered; staff is queue-centered; administrator is school/exception-centered.
- On phones use a compact role-specific primary navigation and a More menu. On desktop use the role sidebar. Keep visible context selectors and accessible labels, keyboard focus, contrast and form errors.
- Retain the reference's friendly blue/yellow identity where appropriate. The provided image specifies role entry, not the detailed dashboard layout.

## 12. Implementation sequence and completion gates

### Phase 1 — Baseline and access foundation

Restore a local test database; record current tests; validate schema; inventory role/type mismatches and parent links. Introduce membership scope and resource policies. Fix the concrete ownership/profile issues above. Add cross-school, cross-role and direct-URL tests before exposing new roles.

Gate: Parent cannot read another child; teachers cannot mutate another section; principals cannot access another school; staff without a permission are denied even if they know a route or record ID.

### Phase 2 — Login and workspace shell

Implement role entry, verified server-side workspace resolution, role navigation, school/child/class selectors and separate web route groups. Reuse existing visual theme files without overwriting local user changes. Establish additive mobile dashboard API contracts.

Gate: All four roles authenticate and land in the correct workspace. Unsupported role choice fails. Role selection cannot create privileges. Super Admin retains its platform workspace and Student API remains intact.

### Phase 3 — Parent and teacher workflows

Build child/class views, attendance, learning resources, published reports, enrollment requests, meetings, notices and messages on authorized application services. Exercise existing integrations and provide service-error states. Enable fee panels only after confirming transaction integrity and scoped receipts.

Gate: Parent request → authorized teacher decision → roster update → attendance → parent view works end to end; no other family/section can access the chain.

### Phase 4 — Staff operations

Build invitation and membership management, staff permissions, tasks, student service/document requests and attendance follow-up. Add finance only for assigned finance staff and verified payment workflows.

Gate: Staff complete assigned work with an audit trail; unrelated modules and confidential academic data remain inaccessible.

### Phase 5 — Administrator and district workflows

Build school operations, bounded staff management, approvals, scoped reports and school communications. Add district data and aggregate views, authorized multi-school selectors and district notices.

Gate: School admin accesses only its school; district admin accesses only assigned district schools; neither can grant platform roles. Aggregate and export scope match the visible context.

### Phase 6 — Release readiness

Verify responsive screens, accessibility, empty/error states, API compatibility, notification retries, authorized downloads, query performance and cache isolation. Back up before migrations; rehearse data migration and rollback; deploy additively with feature flags per workspace/module.

Gate: All acceptance scenarios below pass against representative data and configured integrations. Document unsupported modules honestly rather than shipping inactive buttons.

## 13. Required acceptance scenarios

1. Correct and incorrect role selections; multiple roles across different schools; disabled account; revoked membership; unverified email; expired session.
2. Parent with multiple children across schools; guardian-only child; no child; pending enrollment; forged child ID and receipt ID.
3. Class teacher versus subject teacher; multiple sections of the same class; substitute/delegated attendance; forged section and student IDs; invalid marks.
4. Enrollment acceptance/rejection is authorized, atomic and safe under repeat/concurrent requests.
5. Attendance does not erase another section's records and cannot include students outside its roster; corrections retain reason and actor.
6. Staff bundle combinations; missing permission; revoked access; attempted academic edit; school admin attempts to grant Super Admin.
7. School admin and district admin direct API requests for unauthorized schools; district aggregate/export context consistency.
8. Draft versus published reports; announcement targeting; private attachment downloads; pagination and empty states.
9. Meeting participant checks, scheduling conflicts/timezones, call token ownership, unauthorized conversation access and integration outage.
10. Successful, failed, pending, duplicate and refunded payment events; no client-controlled paid state; own-child receipt access.
11. Existing mobile login, parent, teacher and student contracts remain compatible; current Super Admin routes remain functional.
12. Permission changes and sensitive exports generate an appropriate audit trail; cached data never crosses user/school boundaries.

## 14. Verification performed for this analysis

Inspected web/API route definitions, login controllers, HomeController, user/parent/student/class/teacher/school models, access/type/child middleware, seeders, relevant attendance/enrollment/announcement handlers, current Blade dashboard/layouts and existing security tests.

Attempted `php artisan route:list --json`: blocked during application boot by MySQL connection refused; no verified runtime route count is claimed.

Attempted existing PHPUnit suite using `/Users/gamehive/devtools/bin/php vendor/bin/phpunit`: 12 tests attempted, 10 errors and 1 failure, with MySQL connection refused in the reported database-dependent failures. Runtime dashboard rendering and integration behavior remain unverified. No production data or application code was changed for this analysis.

Open implementation choices: confirm staff departments and permission bundles; whether district administration is required in the first release; which optional fees/exams modules are actively used; and whether the deliverable is web only or also a separate mobile client. These do not prevent starting the access foundation.
