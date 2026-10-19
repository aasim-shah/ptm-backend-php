# Roles, Organizations & App Sync — Backend Implementation

_Implemented 2026-10-19 against the "Backend Update: Roles, Organizations & Current App Compatibility" spec (app build 1.0.1+44 plus the `feedback-fixes` role changes)._

This page lists what the backend now does, where the code is, and where it differs from the spec's suggestions.

## Deploying

1. `php artisan migrate`. This runs three new migrations:
   - `2026_10_19_000001_create_role_context_tables`: tables, plus the default permissions from spec A3.
   - `2026_10_19_000002_backfill_role_contexts`: the one-time data migration from spec A8. Safe to re-run.
   - `2026_10_19_000003_add_call_usage_ledger`: call minute ledger.
2. Optional: set `PTM_TEACHER_REQUIRES_APPROVAL=true` to make teachers who pick a school wait for school-admin approval. It defaults to `false`, which is today's behaviour.
3. `php artisan config:cache && php artisan route:cache`. Both were verified to succeed.

**Product decision needed (A8.3):** the backfill maps every principal (`schools.principal_id`) to an active `school_admin` context for that school. That is the spec's "most likely" mapping. Confirm it with the product owner. To undo it, delete the `school_admin` rows with `source = 'legacy_link'`.

## Data model

| Table | Purpose |
|---|---|
| `districts` | School districts (`name`, `country_id`, `status`) |
| `schools.district_id`, `schools.status` | A school's district (NULL = standalone) and whether it accepts requests |
| `user_roles` | One row per role context. Its `id` is the context id the app sends in `X-PTM-Context`. `source` is `legacy_link` (from a school link), `request` (from `/auth/role-requests`) or `admin`. |
| `context_permissions`, `context_role_permissions` | Permission keys and each role's defaults (A3) |
| `user_role_permissions` | Per-context grants and revocations |
| `audit_logs` | Requests, approvals, suspensions, ends, switches, permission changes |
| `users.last_context_id` | The session's active context, used when the header is absent |
| `call_usage_ledger`, `user_call_room.joined_at/left_at/charged_seconds` | Server-owned call minutes |

**How this differs from the spec's model:**
- **No single `organizations` table.** A context points at `organization_type` + `organization_id`. A school's `organization_id` is the same `schools.id` the app already sees, so nothing needs mapping.
- **Different permission table names.** They carry a `context_` prefix because Spatie already uses `permissions` and `role_has_permissions` for the admin panel.

All of the logic lives in `app/Services/RoleContextService.php`.

## Mobile API

| Endpoint | What changed |
|---|---|
| `POST /auth/login`, `/auth/register`, `GET /auth/details`, `POST /auth/context/switch`, `POST /update-remaining-time` | All return the LoginResponse with `has_school`, `active_context_id` and `contexts` (built in `app/Support/LoginResponse.php`). `contexts` is always present, even as `[]`. |
| `POST /auth/register` | Accepts `staff`, `school_admin` and `district_admin`. These accounts get no role and no Spatie role until an organization approves them. |
| `GET /get/schools` | Returns `data.selected_schools` (an array) and `data.other_schools` (a paginator). With `?organization_type=district` it lists districts in the School shape. |
| `POST /user/schools` | Also creates the parent or teacher context. Staff and admin accounts get a 400 telling them to use a role request. The `"You have already connected a school"` error is kept. |
| `POST /auth/role-requests` | **New.** Creates a pending context and is idempotent. Notifies approvers (FCM `ROLE_REQUEST`) and writes an audit entry. |
| `POST /auth/context/switch` | **New.** Switches to one of the caller's own active contexts; anything else returns 403. Saves `users.last_context_id` and writes an audit entry. |
| `POST /update-remaining-time` | **New.** See "Call minutes" below. |
| `DELETE /teacher/delete/subject/{id}` | **New.** Deletes a subject the teacher created (403 for anyone else's). |
| `POST /teacher/create/student-report`, `/update/student-report` | Each report item's `subject_id` is stored. The top-level `subject_id` is optional now. |

### `X-PTM-Context` (`app/Http/Middleware/ResolvePtmContext.php`)

- **Header present:** it must name the caller's own context, and that context must be active and not ended. Otherwise the response is 403 and nothing falls back.
- **Header absent:** the server uses the last switched-to active context. If there is none, it uses the active context matching the signup type, then the first active one.
- **Exceptions:** `/auth/details`, `/auth/context/switch`, `/auth/login`, `/auth/register`, `/auth/signout`, `/auth/fcm` and `/logout` ignore a bad header instead of returning 403. Without this, a suspended user could never load their new status or switch away from the suspended context.
- **No context at all:** accounts with no context keep the old `users.type` behaviour. A staff or admin account with no active context gets nothing.

### Roles and permissions on routes

- **`ptm.role:...`** replaces `userType` on the parent and teacher groups and checks the role the request is acting as.
- **`ptm.can:...`** checks effective permissions:
  - `messages.use`: chat
  - `schedule.use`: meetings
  - `video.use`: `join/call`, `channel/token`
  - `events.view`: announcements
  - `reports.view_children`: parent reports
  - `classes.manage`, `reports.manage`, `attendance.manage`: teacher actions
- **Teacher-only endpoints:** checks that used `users.type == 'teacher'/'parent'` now use the acting role (`Access::role()`). A parent account with a teacher context therefore works on the teacher path.

### What Staff and Admin contexts can call (spec A6)

| Endpoint | Behaviour |
|---|---|
| `GET /teacher/chat` | Staff and admins get their conversations with people in the active organization, or an empty list. |
| `POST /chat/send` | With a context, the receiver must belong to the context's organization. A parent may only message the institution side (teachers, principals, staff, admins). |
| `GET /teacher/classes/fetch`, `/teacher/class/students`, `/teacher/student/parents` | **School and district admins:** every class in their school or district. **Staff with `students.view_assigned`:** the classes they are class teacher of; there is no separate staff-assignment model yet. **Other staff:** an empty list. |
| `GET /get/meetings-v2` | With a context, only that role's side of each meeting counts: the parent slot for parent contexts, the teacher (institution) slot for the rest. |
| `POST /add/meetings-v2` | Requires `schedule.use`. Staff and admins create meetings through the teacher slot and can be the invited "teacher". |
| `POST /update/meetings` | Only an invitee in the caller's slot can accept or reject. Before this, any user could update any meeting. |
| `GET /parent/school/teachers` | Also lists staff and admins of the parent's schools whose context has `messages.use` or `schedule.use`, in the same User shape. |
| `GET /auth/announcement` | Limited to the context's schools. Staff and admins see announcements sent to everyone (`to_user` NULL) or to their role name. |

## Web Panel approval (spec A7)

- **Admin panel:** a new "Role Requests" sidebar page (`/role-requests`) for Super Admin and Principal. It can filter by status, approve, reject, suspend, reactivate, end, and edit a context's permissions.
- **JSON API for other panels** (Sanctum token):
  - `GET /api/admin/role-requests?status=`
  - `POST /api/admin/role-requests/{id}/{approve|reject|suspend|reactivate|end}`
  - `PUT /api/admin/role-requests/{id}/permissions`
  - `GET /api/admin/permissions`
- **Who approves what:**
  - Staff, teachers and parents: their school's admins, or the district's admins.
  - School admins: the district's admins. For a standalone school, Super Admin.
  - District admins: Super Admin.
  - Super Admin can do everything.
  - If an organization has no approver, the request notification goes up a level.
- **Decisions** send FCM `ROLE_REQUEST_STATUS` with `data` = `{"context_id":"19","status":"active"}` and are written to `audit_logs`.
- **Suspending or ending** a context takes effect on the user's next request.

## Call minutes (B1, Doc 12 §8)

- `/join/call` and `/left/call` now record the server's join and leave times.
- `POST /update-remaining-time` charges `min(used_time, server-observed time not yet charged)`. It never charges more than the server saw.
- The Agora webhook charges whatever is left of Agora's duration for the session. Before this, a leave event that arrived after `/left/call` was never billed.
- Every charge is a row in `call_usage_ledger`, which is insert-only.

## App quirks tolerated (B3, `app/Http/Middleware/NormalizeAppRequest.php`)

- `"null"` / `"undefined"` query values count as absent. This covers `subject_id=null` and `page=null`.
- `fcm id` is copied to `fcm_id`.
- On `add/school`: `webSite` is copied to `website`, and `email: "null"` counts as empty.
- Already fine without changes: DELETE JSON bodies, `notifications-read` reading the id from the query string, and string ids.

## Not covered here

- **Email verification:** login already returns no token for unverified accounts. The app's removal of the OTP screen will strand those users until it is reverted (B2).
- **Plan gating for Reports and News & Events:** waiting for a product decision (B1).
- **HTTPS on the production domain:** a deploy task (B2).
- **Meeting scoping by organization:** meetings have no organization column, so `get/meetings-v2` filters by role slot instead.
- **Legacy eSchool teacher endpoints** (lessons, assignments, exams, timetable) are still teacher and principal only. Their data is not scoped per context.

## Tests

`tests/Feature/RoleContextTest.php` has 20 tests:
- the A9 acceptance checks #2–#10;
- the backfill;
- the admin panel page;
- `get/schools`, `user/schools`, call minutes and query quirks.

Check #1 (the old app against this backend) is covered by the legacy paths and the existing `SecurityTest`. The full suite (32 tests) passes on MySQL 8.0.
