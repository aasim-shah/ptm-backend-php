
# Backend Update: Roles, Organizations & Current App Compatibility

**For:** the PTM backend team
**Mobile build:** `parent_teacher_app` 1.0.1+44, plus the role changes on the `feedback-fixes` branch
**Related files:** [API_CONTRACT.md](./API_CONTRACT.md) (full endpoint contract), [API_APP_CHANGE_HISTORY.md](./API_APP_CHANGE_HISTORY.md)
**Specs:** Document 12 (USA/UK Launch Scope) §4–5 and §7, and Document 13 (Reuse Guide) §2–3

This document covers two things:

- **Part A:** what the backend must add so the new role features in the app work: Staff and Admin/District signup, role contexts, permissions, and role switching.
- **Part B:** changes the app already depends on, which the backend must match to work with the current build.

Part A is **backward compatible**. If the backend doesn't send `contexts` yet, the app behaves exactly as before, so the backend can ship Part A in stages.

---

## Part A: Roles and organizations

### A1. What changed in the app

| Area | App behavior now |
|---|---|
| Signup doorways | There are four: **Parent**, **Teacher**, **Staff**, and **School / District Admin**. The Admin doorway asks "I administer: A School / A School District". |
| Register `type` | Can now be `parent`, `teacher`, `staff`, `school_admin`, or `district_admin` |
| After signup | The school picker opens. For `district_admin` it lists districts (`organization_type=district`). |
| Picking an organization | Parent and Teacher call `POST /user/schools`, as before. Staff and Admins call the new `POST /auth/role-requests` and then see "Request Sent – awaiting approval". |
| Roles at login | The app reads `contexts[]` and `active_context_id` from the login, register, profile, and switch responses |
| Every request | Sends the header `X-PTM-Context: <active context id>` |
| Dashboard | Tabs and Home cards come from the active context's `permissions`, not from `user.type` |
| Profile | A "Roles & Organizations" list shows every context. Tapping another active one calls `POST /auth/context/switch`. |
| Pending access | A `pending` context grants nothing. The user sees only Home, with an approval message. |

One account can hold several roles. Doc 13 §3 says: *"Fixed user_type authorization: replace with User → Roles → Organizations → Permissions"* and *"Do not create separate passwords/accounts per role."*

### A2. Data model

These table and column names are suggestions; the shapes are what matter. Keep the existing `users.type` column populated for now, because older app builds still read it.

```text
organizations
  id               PK
  type             enum('school','district')
  name             string
  parent_id        FK organizations.id NULL   -- school -> its district (NULL = standalone school)
  country_id       FK countries.id            -- market (231 = US, 230 = UK)
  status           enum('active','suspended')
  timestamps

  -- Option: keep the existing `schools` table and add `organization_id` to it,
  -- or make `schools.id` == `organizations.id` for type=school. The app sends
  -- the id from GET /get/schools as `organization_id`, so for schools the id
  -- the app sees must resolve to the organization.

user_roles   (one row = one "role context" the app sees)
  id               PK                         -- sent to the app as context id
  user_id          FK users.id
  role             enum('parent','teacher','staff','school_admin','district_admin')
  organization_id  FK organizations.id
  status           enum('pending','active','rejected','suspended')
  requested_at     datetime
  decided_at       datetime NULL
  decided_by       FK users.id NULL           -- admin who approved/rejected
  effective_from   datetime NULL
  effective_to     datetime NULL              -- relationship ended (Doc 12 J5)
  timestamps
  UNIQUE (user_id, role, organization_id)

permissions
  key              PK string                  -- e.g. 'messages.use'
  description      string

role_permissions          -- defaults per role
  role             enum(...)
  permission_key   FK permissions.key

user_role_permissions     -- per-context grants/revocations (Staff are configured individually)
  user_role_id     FK user_roles.id
  permission_key   FK permissions.key
  granted          boolean                    -- true = add, false = remove from defaults

audit_log                 -- Doc 12 §11: approvals, switches, permission changes
  id, actor_user_id, action, subject_type, subject_id, context_id, payload json, created_at
```

**Effective permissions of a context:** start with `role_permissions` for its role, then apply that context's `user_role_permissions` changes. A context with `status != 'active'`, or an expired `effective_to`, has **no** permissions.

### A3. Permission keys the app understands

Send only these keys. The app ignores unknown keys, so new ones can be added later without breaking it.

| Key | App effect | Default for |
|---|---|---|
| `children.manage` | "My Kids" card, loads parent data | parent |
| `reports.view_children` | Reports tab (parent view) | parent |
| `classes.manage` | "My Classes" card, loads teacher data | teacher |
| `students.view_assigned` | Reserved: no app UI yet | teacher |
| `reports.manage` | Reports tab (teacher view) | teacher |
| `attendance.manage` | Reserved: no app UI yet | teacher |
| `messages.use` | Chat tab | parent, teacher, staff*, school_admin, district_admin |
| `schedule.use` | Calendar tab and Home meeting calendar | parent, teacher, staff*, school_admin, district_admin |
| `video.use` | Reserved for gating video calls | parent, teacher, staff*, school_admin, district_admin |
| `events.view` | "News & Events" card | parent, teacher, staff, school_admin, district_admin |

\* For Staff, Doc 12 §5 requires *"permission-generated essential cards; no title-based authorization."* Defaults are a starting point; each school decides per Staff member through `user_role_permissions`, in the Web Panel. A Teacher Assistant might get `messages.use` plus `students.view_assigned`, while Administrative Staff get `schedule.use` and `events.view`.

### A4. Response shape: add to every `LoginResponse`

The following endpoints return the `LoginResponse` shape:

- `POST /auth/login`
- `POST /auth/register`
- `GET /auth/details`
- `POST /auth/context/switch`
- `POST /update-remaining-time`

In each of them, add `contexts` and `active_context_id` next to the existing fields:

```json
{
  "status": 200,
  "message": "Login successful",
  "token_type": "Bearer",
  "token": "eyJ...",
  "has_school": true,
  "user": { "id": 31, "type": "teacher", "...": "existing User fields" },
  "active_context_id": 12,
  "contexts": [
    {
      "id": 12,
      "role": "teacher",
      "status": "active",
      "organization": { "id": 4, "type": "school", "name": "Oak Primary" },
      "permissions": ["classes.manage", "students.view_assigned", "reports.manage",
                      "attendance.manage", "messages.use", "schedule.use",
                      "video.use", "events.view"]
    },
    {
      "id": 19,
      "role": "staff",
      "status": "pending",
      "organization": { "id": 7, "type": "school", "name": "Elm Academy" },
      "permissions": []
    }
  ]
}
```

Rules:
- Send **all** of the user's contexts, including `pending`, `rejected`, and `suspended` ones, so the Profile list can show their status. Leave out contexts that ended (`effective_to` in the past).
- `permissions` is the **effective** list, after the per-context changes in A2. Send `[]` for any context that isn't active.
- `active_context_id` should be the context the server considers active for this session. On login, use the user's last-used active context, or the first active one. Send `null` if there is none.
- `id` can be an int or a string; the app stores it as a string.
- `has_school` must be `true` once the user has **any** context, including `pending`. If it is `false`, sign-in sends the user back to the school picker in a loop.
- `user.type` stays as it is for older app versions. Keep it as the user's original doorway role.
- Once you send `contexts`, send it **every time**, even as `[]` for a brand-new account. If it is missing, the app falls back to legacy mode.

### A5. New and changed endpoints

#### A5.1 `POST /auth/register`: changed
`type` now accepts `parent | teacher | staff | school_admin | district_admin`. Other fields are unchanged; see API_CONTRACT §1.1.
- Create the user only. Do **not** create any active role for `staff`, `school_admin`, or `district_admin`.
- Return `contexts: []` and `has_school: false`.
- `title` is now sent for every non-parent type.

#### A5.2 `GET /get/schools`: changed
New optional query param: `organization_type=district`.
- When it is absent or `school`, behavior is unchanged.
- When it is `district`, return districts in the **same** `School` object shape (API_CONTRACT "Shared object shapes"). Put the district name in `school_name`, and keep `created_at`, `updated_at`, and `country_id` populated, because the app requires them.
- `selected_schools` should list the organizations the user already has a context in.

#### A5.3 `POST /user/schools`: changed
Same request: `{ "school_ids": [4] }`. It must now also:
- Create (or reactivate) the caller's `user_roles` row with `role = users.type` (`parent` or `teacher`), `organization_id` set to that school, and `status = 'active'`. If schools must verify teachers first, use `pending` instead.
- Leave existing behavior in place, including the error message `"You have already connected a school"`, which the app checks for.

After this call the app requests `GET /auth/details`, so the new context must appear there.

#### A5.4 `POST /auth/role-requests`: **new**
Used by Staff, School Admin, and District Admin after picking an organization.

Request:
```json
{ "organization_id": 4, "organization_type": "school", "role": "staff" }
```

Validation:
- `role` must be one of `staff`, `school_admin`, or `district_admin`. Reject `parent` and `teacher` here; they use A5.3.
- If `role = district_admin`, the organization must have `organization_type = district`. If `role` is `staff` or `school_admin`, it must be `school`.
- The organization must exist and be active, and be in the user's market (`country_id`).
- If the same `(user, role, organization)` request already exists, return 2xx and leave it unchanged. The request must be idempotent.

Effects:
- Create a `user_roles` row with `status = 'pending'` and no permissions.
- Notify the organization's approvers. A Staff request goes to that school's School Admins. A School Admin request goes to the district's District Admins, or to PTM Super Admin for a standalone school. A District Admin request goes to PTM Super Admin.
- Write an audit entry.

Response: any 2xx JSON, for example `{ "status": 200, "message": "Request sent", "data": { "id": 19, "status": "pending" } }`. The app ignores the body and then calls `GET /auth/details`.

Errors: use the existing error shape (`{ "error": true, "message": "...", "code": ... }`). The app shows `message`.

#### A5.5 `POST /auth/context/switch`: **new**
Request: `{ "context_id": "12" }`

- The context must belong to the caller and have `status = 'active'`. Otherwise return 403 with a `message`.
- Record it as the session's active context: store it on the token/session, or as `users.last_context_id`.
- Write an audit entry.
- Response: the full `LoginResponse` (A4) with `active_context_id` set to the new context. `token` can be left out.

#### A5.6 Header `X-PTM-Context`: **new, applies to every authenticated endpoint**

The app sends this header on every request once `contexts` exists. On each request:
1. If the header is present, load that `user_roles` row. If it isn't the caller's own, or isn't `active`, return **403**. Never fall back silently to another role.
2. If the header is absent, the request comes from an older app or legacy mode. Fall back to the session's active context, or to the user's single active context.
3. Authorize the action using that context's **effective permissions and organization**. Data must be limited to that organization, and to district-wide scope for district admins (Doc 12 §11: tenant isolation).

Hidden UI is not security. Doc 12 §6 says *"All actions remain backend-authorized."* Every endpoint must re-check permissions even when the app hides the button.

### A6. Existing endpoints that Staff and Admin contexts now call

Chat, meetings, and scheduling have separate code paths for parents and teachers. The app sends **every non-parent role** (`teacher`, `staff`, `school_admin`, `district_admin`) down the **teacher** path. For a Staff or Admin context, these endpoints get called:

| Endpoint | Called for Staff/Admin | Required backend behavior |
|---|---|---|
| `GET /teacher/chat?to_user=` | Chat tab | Return conversations of the caller in the active organization. Return an empty list, not an error, if there are none. |
| `POST /chat/send`, `POST /chat/read` | Chat | Allow only participants the context is authorized to message (`messages.use` plus relationship rules) |
| `GET /teacher/classes/fetch`, `GET /teacher/class/students`, `GET /teacher/student/parents` | "Start new chat" and "Schedule meeting" pickers | Return only classes and students the context may see: assigned classes for staff with `students.view_assigned`, the whole school for school admins. Otherwise return an empty list. |
| `GET /get/meetings-v2` | Home and Calendar | Meetings where the caller is creator or participant, scoped to the organization |
| `POST /add/meetings-v2` | Schedule meeting | Same body as when a teacher creates a meeting: `teacher_id` and `student_id` are left out, and `student_parent_ids` is filled. The creator is the authenticated user. Require `schedule.use`. |
| `POST /update/meetings` | Accept or reject | The app matches the caller to `meeting_details[].teacher_id` for every non-parent role. Store Staff and Admin invitees in that **institution participant slot** until a proper participant model replaces it. |
| `GET /parent/school/teachers` | Parent picks whom to meet or message | Decide whether Staff and Admins with `messages.use`/`schedule.use` should appear here. If they do, return them in the same `User` shape. |
| `GET /auth/announcement` | News & Events | Return announcements targeted at the context's organization and role (Doc 12 §9). Require `events.view`. |

Endpoints for Teacher-only actions, such as creating classes, subjects, and reports, or marking attendance, must reject Staff and Admin contexts unless the context has the matching permission (`classes.manage`, `reports.manage`, `attendance.manage`).

### A7. Approval workflow (Web Panel)

Approving, rejecting, suspending, and configuring Staff permissions happen in the **Web Panels**. Doc 12 §5 describes mobile Admin as an "operational companion" and keeps heavy admin web-first. The backend needs these endpoints for the panels. The mobile app doesn't call them.

- List pending role requests for the organizations the approver administers.
- Approve or reject a request: set `status`, `decided_by`, `decided_at`, and `effective_from`, and write an audit entry.
- Suspend or end a context: set `status = 'suspended'` or `effective_to`. Access must stop **on the next request** (Doc 12 J5: revocation).
- Edit a Staff context's permission changes (`user_role_permissions`).

**Push notification on decision.** Use the existing FCM payload format (API_CONTRACT §12.3) with a new `type`:
```json
{
  "notification": { "title": "Access approved", "body": "You can now use PTM at Oak Primary." },
  "data": {
    "type": "ROLE_REQUEST_STATUS",
    "data": "{\"context_id\":\"19\",\"status\":\"active\"}"
  }
}
```
The app has no handler for this `type` yet, so tapping it only opens the app. It picks up the new status on its next `GET /auth/details`, which runs at every app launch.

### A8. Migrating existing data

Run this once when roles go live:
1. Create an `organizations` row for each existing school, with `type = 'school'`, the same name, and the same `country_id`.
2. For every user with `type = 'parent'` or `'teacher'`, create one **active** `user_roles` row per linked school (from the current user–school link table), using `role = users.type`.
3. For users with `type = 'Principal'`, decide the mapping, most likely `school_admin` for their school (`schools.principal_id`). Confirm with the product owner.
4. Seed `permissions` and `role_permissions` with the defaults in A3.
5. Parents and Teachers with **no** linked school get no context and `has_school: false`, as today.

Once migrated, existing Parents and Teachers get the same tabs and cards from the permissions as they had from `user.type`.

### A9. Acceptance checks for Part A

| # | Scenario | Expected |
|---|---|---|
| 1 | The current backend (no `contexts`) with this app build | Parent and Teacher work exactly as before |
| 2 | Register as Staff and pick a school | The pending context appears in `/auth/details`. The app shows "Request Sent", then only Home with an approval message. |
| 3 | Approve that request in the Web Panel and relaunch the app | The Staff context becomes `active` with permissions, and the tabs appear |
| 4 | A Parent who is also a Teacher | Profile lists both. Switching changes tabs and cards, and the backend returns data for the new context only. |
| 5 | Send `X-PTM-Context` with another user's context id | 403 |
| 6 | Send a pending context id | 403 |
| 7 | A Staff context without `schedule.use` calls `POST /add/meetings-v2` directly | 403, even though the app hides the Calendar |
| 8 | Suspend a context while the user is signed in | The next request with that context returns 403, and `/auth/details` shows `suspended` |
| 9 | Register as District Admin | `GET /get/schools?organization_type=district` lists districts, and `/auth/role-requests` with `organization_type=district` succeeds |
| 10 | Call `/auth/role-requests` twice for the same organization and role | One pending row; both calls return 2xx |

---

## Part B: App changes the backend must already match

These come from [API_APP_CHANGE_HISTORY.md](./API_APP_CHANGE_HISTORY.md), [API_CONTRACT.md](./API_CONTRACT.md) §15, and the current working copy. They apply whether or not Part A has shipped.

### B1. Changes recorded in the app's history

| Since | Endpoint | What the app sends or expects | Backend action |
|---|---|---|---|
| 2025-11-20 (`51fcd32`) | `POST /teacher/create/student-report`, `POST /teacher/update/student-report` | Each report item now includes `subject_id` | Accept and store `subject_id` on each report row |
| 2025-12-03 (`19234fa`) | `DELETE /teacher/delete/subject/{id}` | No body; `{id}` is the subject id | Implement it: delete the teacher's standalone subject and return 2xx. The app then removes it from its list. |
| 2025-12-03 (`19234fa`) | `GET /get/schools` | Expects `data.selected_schools` (array or null) and `data.other_schools` (paginator) | Return this shape. `selected_schools` = schools the user is already linked to. |
| 2025-12-03 (`19234fa`) | Subject object | Reads `teacher_id` and `principal_id` on `Subject` | Include both, or null |
| 2025-12-03 (`19234fa`) | Reports, News & Events | The app locks these behind a paid plan in the UI | Enforce plan entitlement on the server too, if it is a business rule. Doc 12 lists institutional Events/Announcements as Launch for all users, so confirm with the product owner. |
| 2025-12-16 (`8b0a8cc`) | `POST /update-remaining-time` | `{ "used_time": <seconds> }`. Expects a `LoginResponse` back. | Implement it and return `LoginResponse`. **Note:** the app measures the seconds itself. Doc 12 §8 requires a server-owned, immutable minute ledger, so validate this against server call records (join/leave times from `/join/call` and `/left/call`) instead of trusting the client value. |

### B2. Changes in the current working copy (not yet committed)

| File | Change | Backend impact |
|---|---|---|
| `lib/models/school.dart` | The school list also accepts `data` being a **flat paginator** when `other_schools` is missing. Missing `current_page` defaults to 1, and a missing list is treated as empty. | Either shape works now. Prefer the documented shape in B1. |
| `sign_in_screen.dart`, `splash_screen.dart`, `find_school_screen.dart` | The app **no longer sends unverified users to the email OTP screen** after sign-in, signup, or launch | If email verification is required, enforce it on the server. Doc 13 says to keep verification (MODIFY + CONNECT); this removal is expected to be reverted. |
| `lib/network/net_url.dart` | Base URL temporarily points to `http://2.25.103.51/api` (plain HTTP, bare IP) | Production must be HTTPS on the real domain (`https://www.schoolsnow.parentteachermobile.com/api`) before release |

### B3. Long-standing request quirks (API_CONTRACT §15)

The backend must tolerate these, because the current app sends them:

| # | Endpoint | Issue | Action |
|---|---|---|---|
| 1 | `POST /auth/update/profile` | Sends the field `fcm id`, with a space | Accept both `fcm id` and `fcm_id` |
| 2 | `POST /add/school` | Sends `email: "null"` when empty, and uses the camelCase key `webSite` | Treat `"null"` as empty and accept `webSite` |
| 3 | `GET /teacher/view-attendance/class` | Sends `subject_id=null` as a string | Treat `"null"` as absent |
| 4 | `GET /teacher/class/reports/count` | May send `page=null` | Default to page 1 |
| 5 | `POST /auth/register` | `fcm_id` is a hard-coded placeholder | Ignore it. The real token arrives through `/auth/fcm`. |
| 6 | `POST /teacher/subject`, `/teacher/update/subject` | `bg_color` is always `"red"` | For information only |
| 7 | Several `DELETE` endpoints | Send a JSON body (API_CONTRACT §4.3, 4.8, 5.4, 8.3) | Parse DELETE bodies |
| 8 | `POST /notifications-read` | Sends the id as a query param | Read it from the query string |
| 9 | `POST /teacher/student/accept`, `/parent/class/students/requests/cancel` | Ids are sent as strings | Cast them on the server |
| 10 | Reports | `points` is sent as an int, but read back as a string | Keep returning a string |
| 11 | `/sections`, `/quarters`, `/parent/children-list`, chat list | Do not use the `data` wrapper | Keep these exact top-level keys |
| 12 | `School.country` | Only `US`, `GB`, and `AW` parse | Adding any other country needs an app update |
| 13 | `/edit-attendance` | Defined in the app but never called | Optional |
| 14 | ID types | Strings: meeting, chat, notification, report, quarter, and plan `id`. Ints: everything else. | Keep these types stable |
| 15 | `POST /auth/register` | `type` now includes `staff`, `school_admin`, and `district_admin` | See A5.1 |
| 16 | All authenticated endpoints | `X-PTM-Context` header | See A5.6 |
| 17 | Teacher-side endpoints | Also called by Staff and Admin contexts | See A6 |
| 18 | `has_school` | `false` sends the user back to the school picker | See A4 |
| 19 | Email verification | The app does not enforce it at the moment | See B2 |

### B4. Parse-critical fields (the app crashes if these are wrong)

From API_CONTRACT "Shared object shapes". These matter for every endpoint above:
- `User.dob` and `User.email_verified_at` must be `null` or a string `DateTime.parse` accepts.
- `School.created_at` and `School.updated_at` are **required**. `School.country.code` must be `US`, `GB`, or `AW`.
- In `LoginResponse`, `status`, `message`, and `user` are **required**.
- New in Part A: each `contexts[]` item needs `id` and `role`. `status` defaults to `active` if it is missing. `organization` and `permissions` are optional.

---

## Rollout order

1. **Part B first.** The current app already needs these.
2. **A2 tables and A8 migration.** Start returning `contexts` (A4) and accepting `X-PTM-Context` (A5.6). Parents and Teachers see no difference.
3. **A5.1–A5.5.** This turns on the Staff and Admin doorways end to end.
4. **A6 scoping and A7 Web Panel approval.**
5. Run the A9 checks against Staging before Production. Doc 12 §2 requires QA evidence for every launch item.
Add this update on the backend site to make the backend and frontend uptodate, to sync the app and backend with each other.
Show less
10:28 PM
I'll start by surveying the backend so the changes match how it's built today.



Bash Survey project layout
IN
ls && cat composer.json | head -40 && ls app app/Http/Controllers app/Http/Controllers/* app/Models database/migrations | head -300 && wc -l routes/api.php

OUT
README.md
app
artisan
assets
azure-pipelines.yml
bootstrap
cmd.sh
composer.json
composer.lock
config
database
database.rules.json
docker
docker-compose.prod.yml
docs
favicon.ico
index.php
lang
nuxt.config.js
package-lock.json
package.json
phpunit.xml
public
resources
robots.txt
routes
scripts
server.php
test
test.php
tests
vite.config.js
webpack.mix.js
{
    "name": "laravel/laravel",
    "type": "project",
    "description": "The Laravel Framework.",
    "keywords": [
        "framework",
        "laravel"
    ],
    "license": "MIT",
    "require": {
        "php": "^8.0",
        "ext-curl": "*",
        "ext-zip": "*",
        "awobaz/compoships": "^2.1",
        "barryvdh/laravel-dompdf": "^3.0",
        "code-lts/laravel-fcm": "^1.8",
        "doctrine/dbal": "^3.3",
        "fruitcake/laravel-cors": "^2.0.5",
        "google/cloud-firestore": "^1.37.7",
        "grpc/grpc": "^v1.44.0",
        "guzzlehttp/guzzle": "^7.2",
        "intervention/image": "^2.7",
        "kreait/laravel-firebase": "^4.0.0",
        "laravel/framework": "^9.0",
        "laravel/sanctum": "^2.14",
        "laravel/tinker": "^2.7",
        "laravel/ui": "^3.4",
        "laravelcollective/html": "^6.3",
        "maatwebsite/excel": "^3.1",
        "psr/simple-cache": "^2.0",
        "pusher/pusher-php-server": "^7.2",
        "razorpay/razorpay": "2.*",
        "sendgrid/sendgrid": "^8.0.0",
        "spatie/laravel-permission": "^5.5",
        "stripe/stripe-php": "^10.0",
        "webpatser/laravel-uuid": "^4.0"
    },
    "require-dev": {
        "barryvdh/laravel-debugbar": "^3.6",
        "fakerphp/faker": "^1.9.1",
app/Http/Controllers/AnnouncementController.php
app/Http/Controllers/AssignmentController.php
app/Http/Controllers/AttendanceController.php
app/Http/Controllers/CategoryController.php
app/Http/Controllers/ClassSchoolController.php
app/Http/Controllers/ClassTeacherController.php
app/Http/Controllers/Controller.php
app/Http/Controllers/ExamController.php
app/Http/Controllers/ExamTimetableController.php
app/Http/Controllers/FcmController.php
app/Http/Controllers/FeesTypeController.php
app/Http/Controllers/HolidayController.php
app/Http/Controllers/HomeController.php
app/Http/Controllers/LanguageController.php
app/Http/Controllers/LessonController.php
app/Http/Controllers/LessonTopicController.php
app/Http/Controllers/MediumController.php
app/Http/Controllers/MeetingController.php
app/Http/Controllers/OnlineExamController.php
app/Http/Controllers/OnlineExamQuestionController.php
app/Http/Controllers/ParentsController.php
app/Http/Controllers/RoleController.php
app/Http/Controllers/SchoolsController.php
app/Http/Controllers/SectionController.php
app/Http/Controllers/SessionYearController.php
app/Http/Controllers/SettingController.php
app/Http/Controllers/SliderController.php
app/Http/Controllers/StudentController.php
app/Http/Controllers/StudentSessionController.php
app/Http/Controllers/SubjectController.php
app/Http/Controllers/SubjectTeacherController.php
app/Http/Controllers/SystemUpdateController.php
app/Http/Controllers/TeacherController.php
app/Http/Controllers/TimetableController.php
app/Http/Controllers/UserController.php
app/Http/Controllers/WebhookController.php

app:
Class
Common
Console
Events
Exceptions
Exports
Helpers
Http
Imports
Jobs
Mail
Models
PayPalSDK
Paypal
Providers
Repositories
Rules
Services
Support

app/Http/Controllers:
AnnouncementController.php
Api
AssignmentController.php
AttendanceController.php
Auth
CategoryController.php
ClassSchoolController.php
ClassTeacherController.php
Controller.php
ExamController.php
ExamTimetableController.php
FcmController.php
FeesTypeController.php
HolidayController.php
HomeController.php
LanguageController.php
LessonController.php
LessonTopicController.php
MediumController.php
MeetingController.php
OnlineExamController.php
OnlineExamQuestionController.php
ParentsController.php
Payment
RoleController.php
SchoolsController.php
SectionController.php
SessionYearController.php
SettingController.php
SliderController.php
StudentController.php
StudentSessionController.php
SubjectController.php
SubjectTeacherController.php
SystemUpdateController.php
TeacherController.php
TimetableController.php
UserController.php
V2
WebhookController.php

app/Http/Controllers/Api:
ApiController.php
AuthController.php
ParentApiController.php
StudentApiController.php
TeacherApiController.php
v2

app/Http/Controllers/Auth:
ConfirmPasswordController.php
ForgotPasswordController.php
LoginController.php
RegisterController.php
ResetPasswordController.php
VerificationController.php

app/Http/Controllers/Payment:
SubscriptionController.php

app/Http/Controllers/V2:
ExcelController.php
ForgetPasswordController.php
PrincipalController.php
PushNotificationController.php
StudentController.php

app/Models:
Announcement.php
ApplicationRating.php
Assignment.php
AssignmentSubmission.php
Attendance.php
CallDetails.php
Category.php
Chat.php
City.php
ClassQuarter.php
ClassSchool.php
ClassSection.php
ClassSubject.php
Country.php
CustomResetPassword.php
ElectiveSubjectGroup.php
Exam.php
ExamClass.php
ExamMarks.php
ExamResult.php
ExamTimetable.php
FeesChoiceable.php
FeesClass.php
FeesPaid.php
FeesType.php
File.php
Grade.php
Holiday.php
InstallmentFee.php
Language.php
Lesson.php
LessonTopic.php
MainMeeting.php
Mediums.php
MeetingEntry.php
Meetings.php
NotificationLogs.php
OnlineExam.php
OnlineExamQuestion.php
OnlineExamQuestionAnswer.php
OnlineExamQuestionChoice.php
OnlineExamQuestionOption.php
OnlineExamStudentAnswer.php
PaidInstallmentFee.php
Parents.php
PasswordReset.php
PaymentTransaction.php
Plan.php
Principal.php
PromoteStudents.php
Quarters.php
SchoolSubjects.php
Schools.php
Section.php
SessionYear.php
Settings.php
Slider.php
State.php
StudentOnlineExamStatus.php
StudentQuarter.php
StudentReportCards.php
StudentSessions.php
StudentSubject.php
Students.php
Subject.php
SubjectTeacher.php
Teacher.php
Timetable.php
User.php
UserCallRoom.php
UserEcToken.php
UserMeeting.php
UserSchools.php
UserSubscription.php

database/migrations:
2014_10_12_000000_create_users_table.php
2014_10_12_100000_create_password_resets_table.php
2017_05_06_172817_create_cities_table.php
2017_05_06_173711_create_states_table.php
2017_05_06_173745_create_countries_table.php
2019_08_19_000000_create_failed_jobs_table.php
2019_12_14_000001_create_personal_access_tokens_table.php
2022_04_01_044234_create_settings_table.php
2022_04_01_091033_create_permission_tables.php
2022_04_01_105826_all_tables.php
2022_04_27_072441_parent_changes.php
2022_04_28_105419_add_day_name_to_timetables_table.php
2022_04_29_164836_add_class_section_id_to_timetables.php
2022_05_03_053843_add_lesson_files.php
2022_05_06_071034_create_holidays_table.php
2022_05_11_063841_add_sliders.php
2022_05_13_041458_add_date_to_session_years_table.php
2022_05_16_045021_add_class_secion_id_to_attendances.php
2022_05_19_053446_add_fcm_id_to_users.php
2022_05_31_133456_add_reset_request_to_users.php
2022_06_03_060653_create_student_sessions_table.php
2022_06_07_065946_create_languages_table.php
2022_07_18_044243_is_rtl_in_language.php
2022_07_25_103347_create_exams_table.php
2022_11_11_065720_fees_module.php
2022_12_08_044452_generate_roll_number.php
2022_12_12_033204_online_exam_module.php
2023_02_14_164618_update_online_exam_to_class_section.php
2023_06_02_100137_change_fee_choiceable_to_class.php
2023_06_02_100328_create_installment_fees_table.php
2023_06_05_104000_create_paid_installment_fees_table.php
2023_07_25_122427_create_schools_table.php
2023_07_25_214652_create_meetings_table.php
2023_08_08_094155_create_user_schools_table.php
2023_08_09_080524_add_school_id_into_class_table.php
2023_08_13_131750_create_principal_table.php
2023_08_14_054410_add_school_id_to_section_table.php
2023_08_14_172303_add_user_id_to_principal_table.php
2023_08_14_181609_add_school_id_to_medium_table.php
2023_08_15_064750_make_students_fields_nullable.php
2023_08_15_065510_make_user_fields_nullable.php
2023_08_15_074728_make_class_school_nullable.php
2023_08_15_082234_add_grade_to_classess_table.php
2023_08_15_175555_add_relationship_to_students_table.php
2023_08_15_175811_set_title_as_nullable_in_users_table.php
2023_08_18_083308_add_student_id_to_meetings_table.php
2023_08_18_084237_add_otp_column_to_password_resets_table.php
2023_08_21_052517_create_ratings_table.php
2023_08_21_063639_create_quaters_table.php
2023_08_21_063849_create_student_report_cards_table.php
2023_08_21_081355_create_student_quarter_table.php
2023_08_21_115213_set_email_as_ununique_in_users_table.php
2023_08_22_062653_add_grade_to_student_report_cards_table.php
2023_08_22_101853_add_extras_to_announcements_table.php
2023_08_24_051439_medium_id_nullable_in_subjects_table.php
2023_08_24_051648_image_nullable_in_subjects_table.php
2023_08_29_031117_change_school_principal_id_schools_table.php
2023_09_04_103614_add_country_to_users_table.php
2023_09_07_134716_create_user_meetings_table.php
2023_09_07_134733_create_meeting_entries_table.php
2023_09_19_080042_create_chat_table.php
2023_09_20_074650_add_attributes_to_meetings_table.php
2023_09_25_114404_add_hash_into_meetings_table.php
2023_09_26_041053_add_to_user_id_to_chat_table.php
2023_10_03_072634_add_principal_id_to_meetings_table.php
2023_10_05_034702_add_meeting_created_user_type_to_meetings_table.php
2023_10_23_061027_create_notification_logs_table.php
2023_10_26_053612_add_state_to_schools_table.php
2023_11_01_031702_add_new_message_to_chat_table.php
2023_12_13_072737_create_class_quarter_table.php
2023_12_14_032347_add_session_year_id_to_student_report_cards_table.php
2023_12_19_041837_add_subject_id_to_attendance_table.php
2023_12_19_102344_create_call_details_table.php
2023_12_19_104303_set_class_id_nullable_to_class_subjects_table.php
2023_12_20_032515_add_event_type_to_call_details_table.php
2023_12_27_030701_create_user_subscription_table.php
2023_12_27_103250_create_plan_table.php
2023_12_28_065709_create_user_ec_token_table.php
2024_01_02_085736_add_fields_to_user_table.php
2024_01_02_095819_add_name_to_users_table.php
2024_01_03_022228_add_remaining_time_to_user_subscription_table.php
2024_01_04_020506_create_user_call_room_table.php
2024_01_08_111104_add_type_to_notification_logs_table.php
2024_01_11_060229_add_call_count_to_user_subscription_table.php
2024_01_11_103604_add_enable_notification_to_users_table.php
2024_01_12_040415_create_main_meeting_table.php
2024_01_12_105349_add_class_id_to_student_quarter_table.php
2024_01_13_084234_create_school_subjects_table.php
2024_01_14_012134_add_session_year_to_student_quarter_table.php
2024_01_14_055934_add_session_year_id_to_class_quarter_table.php
2024_01_16_060301_add_title_to_notfication_logs.php
2024_01_18_055424_add_new_message_count_to_chat_table.php
2024_01_18_092109_add_missing_cause_to_student_report_cards_table.php
2024_01_23_042716_add_class_status_to_students_table.php
2024_01_23_061331_add_subject_created_user_to_subjects_table.php
2024_01_26_085241_make_image_varchar.php
2024_01_29_045557_add_principal_id.php
     328 routes/api.php
Bash Read routes, kernel, docs, tests
IN
ls database/migrations | tail -n +130; ls docs app/Http/Middleware app/Http/Controllers/Api/v2 tests tests/*; cat routes/api.php; cat app/Http/Kernel.php | sed -n 1,200p

OUT
tests/CreatesApplication.php
tests/TestCase.php

app/Http/Controllers/Api/v2:
AnnouncementController.php
AttendanceController.php
ChatController.php
ClassController.php
CountryController.php
NotificationController.php
SchoolController.php
StudentController.php
SubjectController.php
TeacherController.php

app/Http/Middleware:
Authenticate.php
CheckChild.php
CheckRole.php
CheckStudent.php
DemoMiddleware.php
EncryptCookies.php
EnsureSystemUpdateEnabled.php
EnsureUserType.php
HandleSanctumUnauthenticated.php
HandleSanctumUnauthenticatedApi.php
LanguageManager.php
PreventRequestsDuringMaintenance.php
RedirectIfAuthenticated.php
TrimStrings.php
TrustHosts.php
TrustProxies.php
ValidateSignature.php
VerifyCsrfToken.php

docs:
DEPLOYMENT.md
current-state.md
postman.json

tests:
CreatesApplication.php
Feature
TestCase.php
Unit

tests/Feature:
ExampleTest.php
SecurityTest.php

tests/Unit:
ExampleTest.php
<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ParentApiController;
use App\Http\Controllers\Api\StudentApiController;
use App\Http\Controllers\Api\TeacherApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('logout', [ApiController::class, 'logout']);
});


Route::any('/auth/register', [AuthController::class, 'createUser'])->middleware('throttle:10,1');
Route::any('/auth/signout', [AuthController::class, 'signout'])->middleware('auth:sanctum');
Route::any('/auth/fcm', [AuthController::class, 'fcm'])->middleware('auth:sanctum');
Route::any('/auth/details', [AuthController::class, 'details'])->middleware('auth:sanctum');
Route::any('/auth/update', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
Route::any('/auth/settings', [AuthController::class, 'settings'])->middleware('auth:sanctum');
Route::any('/auth/update/profile', [AuthController::class, 'updateUser'])->middleware('auth:sanctum');
Route::any('/auth/get/profile', [AuthController::class, 'getProfile'])->middleware('auth:sanctum');
Route::any('/auth/login', [AuthController::class, 'loginUser'])->middleware('throttle:10,1');
Route::any('add/school', [ApiController::class, 'postSchool'])->middleware('auth:sanctum');
Route::get('get/teachers', [ApiController::class, 'getTeachers'])->middleware('auth:sanctum');
Route::any('search/user', [ApiController::class, 'searchjParentTeacher'])->middleware('auth:sanctum');
Route::get('get/parents', [ApiController::class, 'getParents'])->middleware('auth:sanctum');
Route::any('add/meetings', [ApiController::class, 'postMeeting'])->middleware('auth:sanctum');
Route::any('get/meetings', [ApiController::class, 'getMeetings'])->middleware('auth:sanctum');
Route::any('add/meetings-v2', [ApiController::class, 'postMeetingV2'])->middleware('auth:sanctum');
Route::any('get/meetings-v2', [ApiController::class, 'getMeetingsV2'])->middleware('auth:sanctum');
Route::any('send/pincode', [ApiController::class, 'sendPincode'])->middleware('auth:sanctum');
Route::any('forgot/password', [ApiController::class, 'forgotPassword1'])->middleware('throttle:5,1');
Route::any('reset/password', [ApiController::class, 'resetpassword'])->middleware('auth:sanctum');
Route::any('verify/pincode', [ApiController::class, 'verifyPincode'])->middleware('auth:sanctum');
Route::any('get/status/meetings', [ApiController::class, 'getMeeting'])->middleware('auth:sanctum');
Route::any('search/meetings', [ApiController::class, 'searchMeetings'])->middleware('auth:sanctum');
Route::any('update/meetings', [ApiController::class, 'updateMeeting'])->middleware('auth:sanctum');
Route::any('get/schools', [ApiController::class, 'getSchools'])->middleware('auth:sanctum');
Route::any('get/countries', [ApiController::class, 'getCountries'])->middleware('auth:sanctum');

Route::post('user/schools', [AuthController::class, 'userSchools'])->middleware('auth:sanctum');
Route::get('mediums', [ApiController::class, 'medium']);
Route::get('sections', [ApiController::class, 'sections'])->middleware('auth:sanctum');
Route::get('states/cities/country/{id}', [\App\Http\Controllers\Api\v2\CountryController::class, 'statesAndCities']);
Route::get('get/teacher/schools', [ApiController::class, 'getTeacherSchools'])->middleware('auth:sanctum');
Route::get('subjects', [\App\Http\Controllers\Api\v2\SubjectController::class, 'subjects'])->middleware('auth:sanctum');

Route::post('student/assign/class', [\App\Http\Controllers\Api\v2\StudentController::class, 'assignStudentToClassSection'])->middleware('auth:sanctum');
Route::delete('student/remove/class', [\App\Http\Controllers\Api\v2\StudentController::class, 'removeStudentFromClassSection'])->middleware('auth:sanctum');
Route::get('students', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentList'])->middleware('auth:sanctum');

Route::delete('auth/delete', [\App\Http\Controllers\Api\AuthController::class, 'deleteUser'])->middleware('auth:sanctum');
Route::post('auth/rate', [\App\Http\Controllers\Api\AuthController::class, 'store'])->middleware('auth:sanctum');
Route::get('auth/announcement', [\App\Http\Controllers\Api\v2\AnnouncementController::class, 'announcement'])->middleware('auth:sanctum');

Route::get('get/schools-details', [\App\Http\Controllers\Api\ApiController::class, 'getSchool'])->middleware('auth:sanctum');


Route::get('students/find', [\App\Http\Controllers\Api\v2\StudentController::class, 'findStudent'])->middleware('auth:sanctum');
Route::delete('parent/student', [\App\Http\Controllers\Api\v2\StudentController::class, 'deleteStudent'])->middleware('auth:sanctum');

Route::get('notifications', [\App\Http\Controllers\Api\v2\NotificationController::class, 'notificationLogs'])->middleware('auth:sanctum');
Route::post('notifications-read', [\App\Http\Controllers\Api\v2\NotificationController::class, 'notificationRead'])->middleware('auth:sanctum');
Route::post('chat/send', [\App\Http\Controllers\Api\v2\ChatController::class, 'createChat'])->middleware('auth:sanctum');
Route::post('chat/read', [\App\Http\Controllers\Api\v2\ChatController::class, 'chatRead'])->middleware('auth:sanctum');

Route::get('student-attendance', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentAttendance'])->middleware('auth:sanctum');


Route::post('plan/{id}/agreement/create', [\App\Http\Controllers\Payment\SubscriptionController::class, 'createAgreement'])->middleware('auth:sanctum');
Route::post('subscription-cancel/{subscription_id}', [\App\Http\Controllers\Payment\SubscriptionController::class, 'cancelSubscription'])->middleware('auth:sanctum');
Route::get('plans', [\App\Http\Controllers\Payment\SubscriptionController::class, 'listPlan']);
Route::get('active/subscription', [\App\Http\Controllers\Payment\SubscriptionController::class, 'activeSubscription'])->middleware('auth:sanctum');
Route::post('plan/trail', [\App\Http\Controllers\Payment\SubscriptionController::class, 'activateTrail'])->middleware('auth:sanctum');


Route::post('join/call', [\App\Http\Controllers\MeetingController::class, 'makeCallRoom'])->middleware('auth:sanctum');
Route::post('left/call', [\App\Http\Controllers\MeetingController::class, 'leftFormCallRoom'])->middleware('auth:sanctum');
Route::post('channel/token', [\App\Http\Controllers\MeetingController::class, 'channelToken'])->middleware('auth:sanctum');



/**
 * STUDENT APIs
 **/
Route::group(['prefix' => 'student'], function () {

    //Non Authenticated APIs
    Route::post('login', [StudentApiController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [StudentApiController::class, 'forgotPassword'])->middleware('throttle:5,1');

    //Authenticated APIs
    Route::group(['middleware' => ['auth:sanctum', 'userType:student']], function () {
        Route::get('subjects', [StudentApiController::class, 'subjects']);
        Route::get('class-subjects', [StudentApiController::class, 'classSubjects']);
        Route::post('select-subjects', [StudentApiController::class, 'selectSubjects']);
        Route::get('parent-details', [StudentApiController::class, 'getParentDetails']);
        Route::get('timetable', [StudentApiController::class, 'getTimetable']);
        Route::get('lessons', [StudentApiController::class, 'getLessons']);
        Route::get('lesson-topics', [StudentApiController::class, 'getLessonTopics']);
        Route::get('assignments', [StudentApiController::class, 'getAssignments']);
        Route::post('submit-assignment', [StudentApiController::class, 'submitAssignment']);
        Route::post('delete-assignment-submission', [StudentApiController::class, 'deleteAssignmentSubmission']);
        Route::get('attendance', [StudentApiController::class, 'getAttendance']);
        Route::get('announcements', [StudentApiController::class, 'getAnnouncements']);
        Route::get('get-exam-list', [StudentApiController::class, 'getExamList']); // Exam list Route
        Route::get('get-exam-details', [StudentApiController::class, 'getExamDetails']); // Exam Details Route
        Route::get('exam-marks', [StudentApiController::class, 'getExamMarks']); // Exam Details Route

        // online exam routes
        Route::get('get-online-exam-list', [StudentApiController::class, 'getOnlineExamList']); // Get Online Exam List Route
        Route::get('get-online-exam-questions', [StudentApiController::class, 'getOnlineExamQuestions']); // Get Online Exam Questions Route
        Route::post('submit-online-exam-answers', [StudentApiController::class, 'submitOnlineExamAnswers']); // Submit Online Exam Answers Details Route
        Route::get('get-online-exam-result-list', [StudentApiController::class, 'getOnlineExamResultList']); // Online exam result list Route
        Route::get('get-online-exam-result', [StudentApiController::class, 'getOnlineExamResult']); // Online exam result  Route

        //reports
        Route::get('get-online-exam-report', [StudentApiController::class, 'getOnlineExamReport']); // Online Exam Report Route
        Route::get('get-assignments-report', [StudentApiController::class, 'getAssignmentReport']); // Assignment Report Route

        // profile data
        Route::get('get-profile-data', [StudentApiController::class, 'getProfileDetails']); // Get Profile Data

    });
});

/**
 * PARENT APIs
 **/
Route::group(['prefix' => 'parent'], function () {
    //Non Authenticated APIs
    Route::post('login', [ParentApiController::class, 'login'])->middleware('throttle:10,1');
    //Authenticated APIs
    Route::group(['middleware' => ['auth:sanctum', 'userType:parent']], function () {

        //APIS Without Child ID
        Route::get('announcements', [ParentApiController::class, 'getAnnouncements']); //Get Announcementes
        Route::get('fees-paid-receipt-pdf', [ParentApiController::class, 'feesPaidReceiptPDF']); //Fees Receipt
        Route::get('fees-transactions-list', [ParentApiController::class, 'getFeesPaymentTransactions']); //Fees Payment Transaction Details
        Route::get('get-profile-data', [ParentApiController::class, 'getProfileDetails']); // Get Profile Data
        Route::post('fail-payment-transaction', [ParentApiController::class, 'failPaymentTransactionStatus']); // Make Payment Transaction Fail API
        Route::post('student/create', [\App\Http\Controllers\Api\v2\StudentController::class, 'createStudent']);
        Route::post('student/update', [\App\Http\Controllers\Api\v2\StudentController::class, 'updateStudent']);
        Route::get('schools', [\App\Http\Controllers\Api\v2\StudentController::class, 'parentSchools']);

        
        Route::get('children-list', [ParentApiController::class, 'noOfChildren']);

        Route::get('school/teachers', [\App\Http\Controllers\Api\v2\StudentController::class, 'schoolTeacher']);
        Route::get('classes/teacher/fetch', [\App\Http\Controllers\Api\v2\ClassController::class, 'getTeacherClasses']);
        Route::get('student/report', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentReport']);
        Route::get('student/teachers', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentTeachers']);

        Route::get('class/subjects', [\App\Http\Controllers\Api\v2\ClassController::class, 'subjects2']);


        Route::group(['middleware' => ['auth:sanctum', 'checkChild']], function () {

            Route::get('subjects', [ParentApiController::class, 'subjects']);
            Route::get('class-subjects', [ParentApiController::class, 'classSubjects']);
            Route::get('timetable', [ParentApiController::class, 'getTimetable']);
            Route::get('lessons', [ParentApiController::class, 'getLessons']);
            Route::get('lesson-topics', [ParentApiController::class, 'getLessonTopics']);
            Route::get('assignments', [ParentApiController::class, 'getAssignments']);
            Route::get('attendance', [ParentApiController::class, 'getAttendance']);
            // Route::get('announcements', [ParentApiController::class, 'getAnnouncements']);
            Route::get('teachers', [ParentApiController::class, 'getTeachers']);
            Route::get('get-exam-list', [ParentApiController::class, 'getExamList']); // Exam list Route
            Route::get('get-exam-details', [ParentApiController::class, 'getExamDetails']); // Exam Details Route
            Route::get('exam-marks', [ParentApiController::class, 'getExamMarks']); //Exam Marks

            //fees
            Route::get('fees-details', [ParentApiController::class, 'getFeesDetails']); //Fees Details
            Route::post('add-fees-transaction', [ParentApiController::class, 'storeFeesTransaction']); //Fees Details
            Route::post('store-fees', [ParentApiController::class, 'storeFees']); //Store Fees
            Route::get('fees-paid-list', [ParentApiController::class, 'feesPaidList']); //Fees Details

            // online exam routes
            Route::get('get-online-exam-list', [ParentApiController::class, 'getOnlineExamList']); // Get Online Exam List Route
            Route::get('get-online-exam-result-list', [ParentApiController::class, 'getOnlineExamResultList']); // Online exam result list Route
            Route::get('get-online-exam-result', [ParentApiController::class, 'getOnlineExamResult']); // Online exam result  Route

            //reports
            Route::get('get-online-exam-report', [ParentApiController::class, 'getOnlineExamReport']); // Online Exam Report Route
            Route::get('get-assignments-report', [ParentApiController::class, 'getAssignmentReport']); // Assignment Report Route

        });
        Route::get('chat/{to_user?}', [\App\Http\Controllers\Api\v2\ChatController::class, 'teacherChat']);
        Route::post('class/students/requests/cancel', [\App\Http\Controllers\Api\v2\StudentController::class, 'classSectionStudentsRequestsCancel']);

    });
});

/**
 * TEACHER APIs
 **/
Route::group(['prefix' => 'teacher'], function () {
    //Non Authenticated APIs
    Route::post('login', [TeacherApiController::class, 'login'])->middleware('throttle:10,1');
    //Authenticated APIs
    Route::group(['middleware' => ['auth:sanctum', 'userType:teacher,principal']], function () {
        Route::get('classes', [TeacherApiController::class, 'classes']);

        Route::get('class/subjects', [\App\Http\Controllers\Api\v2\ClassController::class, 'subjects']);

        //Assignment
        Route::get('get-assignment', [TeacherApiController::class, 'getAssignment']);
        Route::post('create-assignment', [TeacherApiController::class, 'createAssignment']);
        Route::post('update-assignment', [TeacherApiController::class, 'updateAssignment']);
        Route::post('delete-assignment', [TeacherApiController::class, 'deleteAssignment']);

        //Assignment Submission
        Route::get('get-assignment-submission', [TeacherApiController::class, 'getAssignmentSubmission']);
        Route::post('update-assignment-submission', [TeacherApiController::class, 'updateAssignmentSubmission']);

        //File
        Route::post('delete-file', [TeacherApiController::class, 'deleteFile']);
        Route::post('update-file', [TeacherApiController::class, 'updateFile']);

        //Lesson
        Route::get('get-lesson', [TeacherApiController::class, 'getLesson']);
        Route::post('create-lesson', [TeacherApiController::class, 'createLesson']);
        Route::post('update-lesson', [TeacherApiController::class, 'updateLesson']);
        Route::post('delete-lesson', [TeacherApiController::class, 'deleteLesson']);

        //Topic
        Route::get('get-topic', [TeacherApiController::class, 'getTopic']);
        Route::post('create-topic', [TeacherApiController::class, 'createTopic']);
        Route::post('update-topic', [TeacherApiController::class, 'updateTopic']);
        Route::post('delete-topic', [TeacherApiController::class, 'deleteTopic']);

        //Announcement
        Route::get('get-announcement', [TeacherApiController::class, 'getAnnouncement']);
        Route::post('send-announcement', [TeacherApiController::class, 'sendAnnouncement']);
        Route::post('update-announcement', [TeacherApiController::class, 'updateAnnouncement']);
        Route::post('delete-announcement', [TeacherApiController::class, 'deleteAnnouncement']);

        Route::get('get-attendance', [TeacherApiController::class, 'getAttendance']);
        Route::post('submit-attendance', [TeacherApiController::class, 'submitAttendance']);


        //Exam
        Route::get('get-exam-list', [TeacherApiController::class, 'getExamList']); // Exam list Route
        Route::get('get-exam-details', [TeacherApiController::class, 'getExamDetails']); // Exam Details Route
        Route::post('submit-exam-marks/subject', [TeacherApiController::class, 'submitExamMarksBySubjects']); // Submit Exam Marks By Subjects Route
        Route::post('submit-exam-marks/student', [TeacherApiController::class, 'submitExamMarksByStudent']); // Submit Exam Marks By Students Route

        Route::group(['middleware' => ['auth:sanctum', 'checkStudent']], function () {
            Route::get('get-student-result', [TeacherApiController::class, 'GetStudentExamResult']); // Student Exam Result
            Route::get('get-student-marks', [TeacherApiController::class, 'GetStudentExamMarks']); // Student Exam Marks
        });

        //Student List
        Route::get('student-list', [TeacherApiController::class, 'getStudentList']);
        Route::get('student-details', [TeacherApiController::class, 'getStudentDetails']);

        //Schedule List
        Route::get('teacher_timetable', [TeacherApiController::class, 'getTeacherTimetable']);

        //Profile Detials
        Route::get('get-profile-details', [TeacherApiController::class, 'getProfileDetails']);

        Route::post('class/create', [ApiController::class, 'createClass']);
        Route::get('class/students', [\App\Http\Controllers\Api\v2\StudentController::class, 'classSectionStudents']);
        Route::post('school-sections', [\App\Http\Controllers\Api\v2\SchoolController::class, 'schoolSection']);
        Route::get('classes/fetch', [\App\Http\Controllers\Api\v2\ClassController::class, 'getTeacherClassList']);
        Route::post('subject', [\App\Http\Controllers\Api\v2\SubjectController::class,'create']);
        Route::post('assign/subject', [\App\Http\Controllers\Api\v2\SubjectController::class,'assign']);
        Route::post('update/subject', [\App\Http\Controllers\Api\v2\SubjectController::class,'update']);
        Route::post('mark-attendance', [\App\Http\Controllers\Api\v2\AttendanceController::class,'attendance']);
        Route::post('edit-attendance', [\App\Http\Controllers\Api\v2\AttendanceController::class,'editAttendance']);
        Route::get('view-attendance/class', [\App\Http\Controllers\Api\v2\AttendanceController::class,'viewAttendance']);

        Route::post('create/exam', [\App\Http\Controllers\Api\v2\TeacherController::class,'createExam']);
        Route::post('create/exam/timetable', [\App\Http\Controllers\Api\v2\TeacherController::class,'timetable']);
        Route::post('create/student-report', [\App\Http\Controllers\Api\v2\TeacherController::class,'addStudentReport']);
        Route::delete('delete/student-report/{id}', [\App\Http\Controllers\Api\v2\TeacherController::class,'deleteStudentReport']);
        Route::post('update/student-report', [\App\Http\Controllers\Api\v2\TeacherController::class,'editStudentReport']);


        Route::get('fetch-student-class', [TeacherApiController::class, 'getStudentsListForClass']);
        Route::get('student/parents', [\App\Http\Controllers\Api\v2\StudentController::class, 'studentParents']);
        Route::get('class/parents', [\App\Http\Controllers\Api\ParentApiController::class, 'classParents']);
        Route::get('reports/count', [\App\Http\Controllers\Api\v2\TeacherController::class, 'reportCount']);
        Route::get('class/reports/count', [\App\Http\Controllers\Api\v2\TeacherController::class, 'ClassReportCount']);
        Route::get('class/subject/reports/count', [\App\Http\Controllers\Api\v2\TeacherController::class, 'ClassSubjectReportCount']);


        Route::get('chat/{to_user?}', [\App\Http\Controllers\Api\v2\ChatController::class, 'parentChat']);

        Route::get('class/students/requests', [\App\Http\Controllers\Api\v2\StudentController::class, 'classSectionStudentsRequests']);
        Route::post('student/accept', [\App\Http\Controllers\Api\v2\StudentController::class, 'acceptOrRejectStudentFromClassSection'])->middleware('auth:sanctum');
        Route::delete('class/delete', [\App\Http\Controllers\Api\ApiController::class, 'deleteClass'])->middleware('auth:sanctum');
        Route::delete('subject/delete', [\App\Http\Controllers\Api\ApiController::class, 'subjectDelete'])->middleware('auth:sanctum');

    });

});

/**
 * GENERAL APIs
 **/
Route::get('holidays', [ApiController::class, 'getHolidays']);
Route::get('quarters', [\App\Http\Controllers\Api\v2\TeacherController::class, 'quarters']);
Route::get('sliders', [ApiController::class, 'getSliders']);
Route::get('current-session-year', [ApiController::class, 'getSessionYear']);
Route::get('settings', [ApiController::class, 'getSettings']);
Route::post('forgot-password', [ApiController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('verify-otp', [ApiController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('set-password', [ApiController::class, 'changePasswordAfterOtp'])->middleware('throttle:10,1');

Route::group(['middleware' => ['auth:sanctum',]], function () {
    Route::post('change-password', [ApiController::class, 'changePassword']);
});
<?php

namespace App\Http;

use App\Http\Middleware\DemoMiddleware;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
    * The application's global HTTP middleware stack.
    *
    * These middleware are run during every request to your application.
    *
    * @var array<int, class-string|string>
    */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Fruitcake\Cors\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        DemoMiddleware::class,
        \App\Http\Middleware\HandleSanctumUnauthenticated::class,
        // \App\Http\Middleware\LanguageManager::class,
    ];

    /**
    * The application's route middleware groups.
    *
    * @var array<string, array<int, class-string|string>>
    */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
             \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'handle.sanctum.unauthenticated.api', // Use the custom middleware instead of EnsureFrontendRequestsAreStateful
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \App\Http\Middleware\CheckChild::class,
        ],
    ];

    /**
    * The application's route middleware.
    *
    * These middleware may be assigned to groups or used individually.
    *
    * @var array<string, class-string|string>
    */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,
        'Role'=>\App\Http\Middleware\CheckRole::class,
        'checkChild'=>\App\Http\Middleware\CheckChild::class,
        'language' => \App\Http\Middleware\LanguageManager::class,
        'checkStudent'=>\App\Http\Middleware\CheckStudent::class,
        'userType' => \App\Http\Middleware\EnsureUserType::class,
        'systemUpdate' => \App\Http\Middleware\EnsureSystemUpdateEnabled::class,
        'handle.sanctum.unauthenticated.api' => \App\Http\Middleware\HandleSanctumUnauthenticatedApi::class,
    ];
}
Bash List migrations, read state doc and middleware
IN
ls database/migrations | wc -l; ls database/migrations | tail -30; cat docs/current-state.md; cat app/Http/Middleware/EnsureUserType.php; wc -l app/Http/Controllers/Api/*.php app/Http/Controllers/Api/v2/*.php

OUT
<persisted-output>
Output too large (35.8KB). Full output saved to: /Users/apple/.claude/projects/-Users-apple-StudioProjects-ptm-backend-php/e0611edb-268c-4dda-88a7-14aafe54fca7/tool-results/bongdz03c.txt

Preview (first 2KB):
     102
2023_12_19_102344_create_call_details_table.php
2023_12_19_104303_set_class_id_nullable_to_class_subjects_table.php
2023_12_20_032515_add_event_type_to_call_details_table.php
2023_12_27_030701_create_user_subscription_table.php
2023_12_27_103250_create_plan_table.php
2023_12_28_065709_create_user_ec_token_table.php
2024_01_02_085736_add_fields_to_user_table.php
2024_01_02_095819_add_name_to_users_table.php
2024_01_03_022228_add_remaining_time_to_user_subscription_table.php
2024_01_04_020506_create_user_call_room_table.php
2024_01_08_111104_add_type_to_notification_logs_table.php
2024_01_11_060229_add_call_count_to_user_subscription_table.php
2024_01_11_103604_add_enable_notification_to_users_table.php
2024_01_12_040415_create_main_meeting_table.php
2024_01_12_105349_add_class_id_to_student_quarter_table.php
2024_01_13_084234_create_school_subjects_table.php
2024_01_14_012134_add_session_year_to_student_quarter_table.php
2024_01_14_055934_add_session_year_id_to_class_quarter_table.php
2024_01_16_060301_add_title_to_notfication_logs.php
2024_01_18_055424_add_new_message_count_to_chat_table.php
2024_01_18_092109_add_missing_cause_to_student_report_cards_table.php
2024_01_23_042716_add_class_status_to_students_table.php
2024_01_23_061331_add_subject_created_user_to_subjects_table.php
2024_01_26_085241_make_image_varchar.php
2024_01_29_045557_add_principal_id.php
2024_01_29_070815_make_bg_color_nullable.php
2024_01_30_032903_add_principal_id_to_subjects_table.php
2024_02_15_140438_make_post_town_nullable.php
2024_03_25_130950_add_timezone_to_user_table.php
2026_10_07_000000_grant_admin_panel_roles.php
# Current State — School System (Parent Teacher Mobile)

_Last reviewed: 2026-09-28. Fixes applied: 2026-10-07 (see §0)._

## 0. Fix status (2026-10-07)

Sections 2–7 describe the code **as found on 2026-09-28**. This table shows what has changed since then. The deployment steps are in [DEPLOYMENT.md](DEPLOYMENT.md).

| Item | Status | What changed |
|---|---|---|
...
</persisted-output>
Read bongdz03c.txt
Read AuthController.php
Bash Find missing routes, read models and tests
IN
grep -rn "remaining-time\|remaining_time\|delete/subject\|subject_id" routes/ app/Http/Controllers/Api/v2/TeacherController.php | head -40; cat app/Models/User.php app/Models/UserSchools.php app/Models/Schools.php; cat tests/Feature/SecurityTest.php | head -80; cat phpunit.xml | grep -i db

OUT
routes/web.php:112:        Route::delete('class/subject/{class_subject_id}', [ClassSchoolController::class, 'subject_destroy'])->name('class.subject.delete');
app/Http/Controllers/Api/v2/TeacherController.php:187:        $subject_id = $request->subject_id;
app/Http/Controllers/Api/v2/TeacherController.php:194:                ->where('subject_id',$subject_id)
app/Http/Controllers/Api/v2/TeacherController.php:202:                ->groupBy('quarter_id','student_id','subject_id','session_year')->paginate(10);
app/Http/Controllers/Api/v2/TeacherController.php:205:                ->where('subject_id', $subject_id)
app/Http/Controllers/Api/v2/TeacherController.php:210:                ->groupBy('quarter_id', 'student_id', 'subject_id', 'session_year')->paginate(10);
app/Http/Controllers/Api/v2/TeacherController.php:318:                    'subject_id' => $timetable['subject_id'],
app/Http/Controllers/Api/v2/TeacherController.php:354:            'subject_id' => 'required|numeric',
app/Http/Controllers/Api/v2/TeacherController.php:370:            $exam_timetable = ExamTimetable::where(['exam_id' => $request->exam_id, 'class_id' => $class_id])->where('subject_id', $request->subject_id)->firstOrFail();
app/Http/Controllers/Api/v2/TeacherController.php:392:                    ['exam_timetable_id' => $exam_timetable->id, 'student_id' => $exam_marks['student_id'], 'subject_id' => $request->subject_id, 'obtained_marks' => $exam_marks['obtained_marks'], 'passing_status' => $status, 'session_year_id' => $exam_timetable->session_year_id, 'grade' => $exam_grade,]
app/Http/Controllers/Api/v2/TeacherController.php:442:            'subject_id' => 'required|numeric',
app/Http/Controllers/Api/v2/TeacherController.php:460:        $isSubject = Subject::where('id', $request->subject_id)->first();
app/Http/Controllers/Api/v2/TeacherController.php:519:                    ->where('subject_id', $request->subject_id)
app/Http/Controllers/Api/v2/TeacherController.php:531:                    "subject_id" => $request->subject_id,
app/Http/Controllers/Api/v2/TeacherController.php:562:            $classSubjects = $classSubjectsSql->pluck('subject_id')->toArray();
app/Http/Controllers/Api/v2/TeacherController.php:568:                    ->where('subject_id',$classSubject)
app/Http/Controllers/Api/v2/TeacherController.php:726:            'subject_id' => 'required|numeric',
app/Http/Controllers/Api/v2/TeacherController.php:746:        $isSubject = Subject::where('id', $request->subject_id)->first();
app/Http/Controllers/Api/v2/TeacherController.php:789:                    ->where('subject_id', $request->subject_id)
app/Http/Controllers/Api/v2/TeacherController.php:801:                    "subject_id" => $request->subject_id,
app/Http/Controllers/Api/v2/TeacherController.php:836:            $classSubjects = $classSubjectsSql->pluck('subject_id')->toArray();
app/Http/Controllers/Api/v2/TeacherController.php:842:                    ->where('subject_id',$classSubject)
<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'title',
        'gender',
        'email',
        'country_id',
        'fcm_id',
        'email_verified_at',
        'password',
        'mobile',
        'job_title',
        'industry',
        'image',
        'hear_about_us',
        'language',
        'dob',
        'current_address',
        'type',
        'pincode',
        'permanent_address',
        'status',
        'reset_request',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'pivot',
        'password',
        'remember_token',
        "deleted_at",
        "created_at",
        "updated_at"
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function student() {
        return $this->hasOne(Students::class, 'user_id', 'id');
    }
    public function students() {
        return $this->hasMany(Students::class, 'user_id', 'id');
    }
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
    public function parent() {
        return $this->hasOne(Parents::class, 'user_id', 'id');
    }
    public function principal() {
        return $this->hasOne(Principal::class, 'user_id', 'id');
    }

    public function teacher() {
        return $this->hasOne(Teacher::class, 'user_id', 'id');
    }

    public function rate() {
        return $this->hasOne(ApplicationRating::class, 'user_id', 'id');
    }
    public function userSchool() {
        return $this->hasMany(UserSchools::class, 'user_id');
    }



    //Getter Attributes
    public function getImageAttribute($value) {
        if(!$value){
            return null;
        }
        return url(Storage::url($value));
    }

    public function getUserMeetingInfo(){
        return $this->hasOne(UserMeeting::class,'user_id','id');
    }
    public function chat(){
        return $this->hasMany(Chat::class,'user_id','id');
    }
    public function toChatUser(){
        return $this->hasMany(Chat::class,'to_user_id','id');
    }
    public function sendPasswordResetNotification($token)
    {
        try {
            $this->notify(new CustomResetPassword($token));
            return true;
        }catch (\Exception $e){
            return true;
        }
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $user_id
 * @property integer $school_id
 * @property string $created_at
 * @property string $updated_at
 * @property Schools $school
 * @property User $user
 */
class UserSchools extends Model
{
    /**
     * @var array
     */
    protected $fillable = ['user_id', 'school_id', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\Schools');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }


}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property integer $id
 * @property string $principal_id
 * @property integer $user_id
 * @property integer $country_id
 * @property string $school_name
 * @property string $address
 * @property string $locality
 * @property string $post_town
 * @property string $post_code
 * @property string $image
 * @property string $email
 * @property string $phone
 * @property string $website
 * @property string $created_at
 * @property string $updated_at
 * @property Announcement[] $announcements
 * @property ClassSchool[] $classes
 * @property Mediums[] $mediums
 * @property Principal $principal
 * @property Section[] $sections
 * @property UserSchools[] $userSchools
 * @property SchoolSubjects[] $schoolSubjects
 */
class
Schools extends Model
{

    use HasFactory;

    /**
     * @var array
     */
    protected $fillable = ['principal_id', 'user_id', 'country_id', 'school_name', 'address', 'locality', 'post_town', 'post_code', 'image', 'email', 'phone', 'website', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function announcements()
    {
        return $this->hasMany('App\Models\Announcement');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classes()
    {
        return $this->hasMany('App\Models\ClassSchool');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mediums()
    {
        return $this->hasMany('App\Models\Mediums');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function principal()
    {
        return $this->belongsTo('App\Models\Principal','principal_id','user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sections()
    {
        return $this->hasMany('App\Models\Section');
    }

    public function country()
    {

        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userSchools()
    {
        return $this->hasMany('App\Models\UserSchools','school_id');
    }

    public function school(){
        return $this->hasMany(User::class, 'user_id');
    }
    public function getImageAttribute($value) {
        if(!$value){
            return null;
        }
        return url(Storage::url($value));
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function schoolSubjects()
    {
        return $this->hasMany('App\Models\SchoolSubjects');
    }
}
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['Super Admin', 'Principal', 'Teacher', 'Parent', 'Student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeUser(string $type, ?string $role = null, string $email = null): User
    {
        $user = User::forceCreate([
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'email' => $email ?? $type . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'type' => $type,
            'email_verified_at' => now(),
        ]);
        if ($role) {
            $user->assignRole($role);
        }
        return $user;
    }

    public function test_register_rejects_privileged_types(): void
    {
        foreach (['Super Admin', 'admin', 'Principal', 'student'] as $type) {
            $response = $this->postJson('/api/auth/register', [
                'first_name' => 'Eve',
                'last_name' => 'Attacker',
                'email' => 'eve' . md5($type) . '@example.com',
                'password' => 'secret123',
                'fcm_id' => 'x',
                'type' => $type,
            ]);

            $this->assertNotEquals(200, $response->status(), "type=$type must be rejected");
            $this->assertDatabaseMissing('users', ['email' => 'eve' . md5($type) . '@example.com']);
        }
    }

    public function test_default_login_and_register_routes_are_gone(): void
    {
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'x'])->assertStatus(405);
        $this->post('/register', [])->assertNotFound();
    }

    public function test_admin_panel_requires_admin_role_not_type_column(): void
    {
        $this->makeUser('admin', null, 'fake-admin@example.com');

        $this->post('/login-web', ['email' => 'fake-admin@example.com', 'password' => 'secret123'])
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_super_admin_can_open_dashboard(): void
    {
        $this->makeUser('admin', 'Super Admin', 'boss@example.com');

        $this->post('/login-web', ['email' => 'boss@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('home'));
        $this->get('/home')->assertOk();
    }
        <!-- <env name="DB_CONNECTION" value="sqlite"/> -->
        <env name="DB_DATABASE" value="school_test"/>
10:29 PM
This is a large change set. Let me check that I can run the test suite locally before going further.



Bash Check PHP, MySQL and test DB setup
IN
ls ~/devtools/bin 2>/dev/null; which php; grep -E "^DB_" .env 2>/dev/null; grep -n "DB_\|env name" phpunit.xml; (~/devtools/mysql-8.0.43-macos15-x86_64/bin/mysqladmin --socket=$HOME/devtools/mysql.sock -uroot status 2>&1 | head -2); docker ps 2>&1 | head -3

OUT
php not found
21:        <env name="APP_ENV" value="testing"/>
22:        <env name="BCRYPT_ROUNDS" value="4"/>
23:        <env name="CACHE_DRIVER" value="array"/>
24:        <!-- <env name="DB_CONNECTION" value="sqlite"/> -->
25:        <env name="DB_DATABASE" value="school_test"/>
26:        <env name="MAIL_MAILER" value="array"/>
27:        <env name="QUEUE_CONNECTION" value="sync"/>
28:        <env name="SESSION_DRIVER" value="array"/>
29:        <env name="TELESCOPE_ENABLED" value="false"/>
(eval):1: no such file or directory: /Users/apple/devtools/mysql-8.0.43-macos15-x86_64/bin/mysqladmin
(eval):1: command not found: docker
Bash Look for any PHP/MySQL install
IN
ls ~/devtools 2>&1; ls /usr/local/bin | grep -i -E "php|mysql|docker" ; ls /opt/homebrew/bin 2>/dev/null | grep -i -E "php|mysql"; ls /Applications | grep -i -E "docker|herd|mamp|orb"; ls ~/Library/Application\ Support/Herd/bin 2>/dev/null; ls /Users/apple/StudioProjects/ptm-backend-php/vendor | head -3

OUT
ls: /Users/apple/devtools: No such file or directory
ls: /Users/apple/StudioProjects/ptm-backend-php/vendor: No such file or directory
This machine lacks PHP, Composer, MySQL, and Docker, so I can't execute anything locally — I'll write the code and feature tests carefully and note they still need to be run. Next, I'll review the endpoints affected by this update.
Bash List controller methods
IN
cd /Users/apple/StudioProjects/ptm-backend-php/app/Http/Controllers && grep -n "public function" Api/ApiController.php Api/v2/*.php MeetingController.php | head -150

OUT
Api/v2/AnnouncementController.php:12:    public function __construct()
Api/v2/AnnouncementController.php:16:    public function announcement(Request $request){
Api/ApiController.php:55:    public function __construct(NotificationSender $notificationSender)
Api/ApiController.php:61:    public function getMeeting(Request $request)
Api/ApiController.php:98:    public function searchMeetings(Request $request)
Api/ApiController.php:138:    public function searchjParentTeacher(Request $request)
Api/ApiController.php:176:    public function verifyPincode(Request $request)
Api/ApiController.php:223:    public function sendPincode(Request $request)
Api/ApiController.php:266:    public function forgotPassword1(Request $request)
Api/ApiController.php:334:    public function resetpassword(Request $request)
Api/ApiController.php:373:    public function getMeetings(Request $request)
Api/ApiController.php:453:    public function getMeetingsV2(Request $request)
Api/ApiController.php:498:    public function getTeachers(Request $request)
Api/ApiController.php:523:    public function getParents(Request $request)
Api/ApiController.php:548:    public function updateMeeting(Request $request)
Api/ApiController.php:654:    public function postMeeting(Request $request)
Api/ApiController.php:885:    public function postMeetingV2(Request $request)
Api/ApiController.php:1151:    public function getSchool(Request $request)
Api/ApiController.php:1186:    public function postSchool(Request $request)
Api/ApiController.php:1265:    public function getCountries(Request $request)
Api/ApiController.php:1294:    public function getSchools(Request $request)
Api/ApiController.php:1324:    public function logout(Request $request)
Api/ApiController.php:1347:    public function getHolidays(Request $request)
Api/ApiController.php:1380:    public function getSliders(Request $request)
Api/ApiController.php:1400:    public function getSessionYear(Request $request)
Api/ApiController.php:1423:    public function getSettings(Request $request)
Api/ApiController.php:1665:    public function medium()
Api/ApiController.php:1677:    public function sections(Request $request)
Api/ApiController.php:1692:    public function getTeacherSchools(Request $request)
Api/ApiController.php:1733:    public function createClass(Request $request)
Api/ApiController.php:1866:    public function deleteClass(Request $request)
Api/ApiController.php:1934:    public function subjectDelete(Request $request){
Api/v2/AttendanceController.php:26:    public function __construct(NotificationSender $notificationSender)
Api/v2/AttendanceController.php:31:    public function attendance(Request $request)
Api/v2/AttendanceController.php:190:    public function editAttendance(Request $request)
Api/v2/AttendanceController.php:288:    public function viewAttendance(Request $request)
Api/v2/ChatController.php:30:    public function __construct(NotificationSender $notificationSender)
Api/v2/ChatController.php:35:    public function teacherChat()
Api/v2/ChatController.php:127:    public function parentChat()
Api/v2/ChatController.php:211:    public function createChat(Request $request){
Api/v2/ChatController.php:293:    public function chatRead(Request $request){
Api/v2/ChatController.php:350:    public function sendMessage($request,$destinationPath,$fileName,$chatId,$chatJson)
Api/v2/CountryController.php:13:    public function __construct(CountryService $countryService)
Api/v2/CountryController.php:18:    public function statesAndCities($id){
Api/v2/ClassController.php:14:    public function __construct()
Api/v2/ClassController.php:18:    public function getTeacherClassList(Request $request){
Api/v2/ClassController.php:38:    public function getTeacherClasses(Request $request){
Api/v2/ClassController.php:53:    public function subjects(Request $request)
Api/v2/ClassController.php:84:    public function subjects2(Request $request)
Api/v2/NotificationController.php:12:    public function __construct()
Api/v2/NotificationController.php:16:    public function notificationLogs(Request $request)
Api/v2/NotificationController.php:32:    public function notificationRead(Request $request)
Api/v2/SubjectController.php:20:    public function create(Request $request)
Api/v2/SubjectController.php:131:    public function update(Request $request)
Api/v2/SubjectController.php:213:    public function subjects(){
Api/v2/SubjectController.php:230:    public function assign(Request $request){
Api/v2/SchoolController.php:13:    public function __construct()
Api/v2/SchoolController.php:17:    public function schoolSection(Request $request){
Api/v2/StudentController.php:42:    public function __construct(NotificationSender $notificationSender)
Api/v2/StudentController.php:46:    public function classSectionStudents(Request $request)
Api/v2/StudentController.php:87:    public function classSectionStudentsRequests(Request $request)
Api/v2/StudentController.php:129:    public function createStudent(Request $request)
Api/v2/StudentController.php:210:    public function updateStudent(Request $request)
Api/v2/StudentController.php:292:    public function assignStudentToClassSection(Request $request)
Api/v2/StudentController.php:331:    public function removeStudentFromClassSection(Request $request)
Api/v2/StudentController.php:398:    public function acceptOrRejectStudentFromClassSection(Request $request){
Api/v2/StudentController.php:505:    public function studentList(Request $request)
Api/v2/StudentController.php:584:    public function findStudent(Request $request)
Api/v2/StudentController.php:597:    public function deleteStudent(Request $request)
Api/v2/StudentController.php:668:    public function parentSchools(Request $request)
Api/v2/StudentController.php:689:    public function schoolTeacher(Request $request)
Api/v2/StudentController.php:719:    public function studentReport(Request $request){
Api/v2/StudentController.php:791:    public function studentTeachers(Request $request){
Api/v2/StudentController.php:830:    public function studentParents(Request $request){
Api/v2/StudentController.php:866:    public function studentAttendance(Request $request){
Api/v2/StudentController.php:905:    public function classSectionStudentsRequestsCancel(Request $request){
Api/v2/TeacherController.php:38:    public function __construct(NotificationSender $notificationSender)
Api/v2/TeacherController.php:43:    public function reportCount(Request $request)
Api/v2/TeacherController.php:98:    public function ClassReportCount(Request $request)
Api/v2/TeacherController.php:174:    public function ClassSubjectReportCount(Request $request)
Api/v2/TeacherController.php:222:    public function createExam(Request $request)
Api/v2/TeacherController.php:279:    public function timetable(Request $request)
Api/v2/TeacherController.php:342:    public function submitMarks(Request $request)
Api/v2/TeacherController.php:409:    public function quarters()
Api/v2/TeacherController.php:421:    public function addStudentReport(Request $request)
Api/v2/TeacherController.php:664:    public function deleteStudentReport(Request $request, $id){
Api/v2/TeacherController.php:708:    public function editStudentReport(Request $request)
MeetingController.php:37:    public function __construct(NotificationSender $notificationSender)
MeetingController.php:42:    public function index(Request $request)
MeetingController.php:50:    public function calendarIndex(Request $request)
MeetingController.php:68:    public function getMeetings($status = null)
MeetingController.php:146:    public function teacherChat($to_user = null)
MeetingController.php:217:    public function parentChat($to_user = null)
MeetingController.php:285:    public function createChat($user,$chatId,$to_user_id){
MeetingController.php:296:    public function token(Request $request)
MeetingController.php:313:    public function callUser(Request $request)
MeetingController.php:359:    public function createMeeting(Request $request)
MeetingController.php:594:    public function meetingListView()
MeetingController.php:605:    public function meetingList(Request $request)
MeetingController.php:709:    public function updateMeeting(Request $request)
MeetingController.php:728:    public function meetingDetails($meetingHash)
MeetingController.php:752:    public function makeCallRoom(Request $request){
MeetingController.php:790:    public function leftFormCallRoom(Request $request){
MeetingController.php:813:    public function channelToken(Request $request){
Read ApiController.php (lines 441-690)
Read ApiController.php (lines 886-1325)
Bash Read chat controller
IN
cd /Users/apple/StudioProjects/ptm-backend-php/app/Http/Controllers/Api/v2 && sed -n 1,350p ChatController.php

OUT
<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Schools;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Services\PushNotificationSender;
use Carbon\Carbon;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;
use LaravelFCM\Facades\FCM;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Message\Topics;

class ChatController extends Controller
{
    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }

    public function teacherChat()
    {
        $to_user = request()->to_user;

        if (Auth::user()->type != 'parent') {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }
        $sender_id = Auth::user()->id;
        $school = UserSchools::where('user_id', $sender_id)->first();

        $schoolIds = UserSchools::where('user_id', $sender_id)->pluck('school_id')->toArray();
        $schoolsPrincipals = Schools::whereIn('id', $schoolIds)->pluck('principal_id')->toArray();

        if (!$school) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_school_assigned')
            ], 400);
        }
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'teacher');
        })->whereIn('school_id', $schoolIds)->pluck('user_id')->toArray();
        $userIds = array_merge($userIds,$schoolsPrincipals);

//        $school = Schools::where('id',$school->school_id)->first();

//        if($school->principal_id){
//            array_push($userIds,$school->principal_id);
//        }



        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        if ($to_user_id) {

            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id);
                    $query->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id);
                    $query->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if($chats){
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatExists = Chat::where(function ($query) use ($sender_id, $to_user_id, $userIds) {
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->whereIn('user_id', $userIds);
                $query->orWhereIn('to_user_id', $userIds);
            });
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->where('user_id', $sender_id);
                $query->orWhere('to_user_id', $sender_id);
            });
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'teacher');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'teacher');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        $response = [
            'chat_users' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats
        ];
        return response()->json($response);

    }

    public function parentChat()
    {
        $to_user = request()->to_user;
        if (Auth::user()->type != 'teacher') {
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }
        $sender_id = Auth::user()->id;
        $school = UserSchools::where('user_id', $sender_id)->first();
        if (!$school) {
            return response()->json([
                'status' => 400,
                'message' => trans('no_school_assigned')
            ], 400);
        }
        $userIds = UserSchools::whereHas('user', function ($query) {
            $query->where('type', 'parent');
        })->where('school_id', $school->school_id)->pluck('user_id')->toArray();

        $school = Schools::where('id',$school->school_id)->first();

        if($school->principal_id){
            array_push($userIds,$school->principal_id);
        }

        $to_user_id = $to_user;
        $chats = null;
        $to_user = null;
        if ($to_user_id) {

            $chats = Chat::with('user')
                ->where(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('user_id', $sender_id);
                    $query->where('to_user_id', $to_user_id);
                })
                ->orWhere(function ($query) use ($sender_id, $to_user_id) {
                    $query->where('to_user_id', $sender_id);
                    $query->where('user_id', $to_user_id);
                })->first();
            $to_user = User::where('id', $to_user_id)->first();
            if($chats){
                $chats->new_message = false;
                $chats->save();
            }
        }

        $chatExists = Chat::where(function ($query) use ($sender_id, $to_user_id, $userIds) {
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->whereIn('user_id', $userIds);
                $query->orWhereIn('to_user_id', $userIds);
            });
            $query->where(function ($query) use ($userIds, $sender_id) {
                $query->where('user_id', $sender_id);
                $query->orWhere('to_user_id', $sender_id);
            });
        })->with(['user' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'parent');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])->with(['receiver' => function ($query) use ($sender_id, $userIds) {
            $query->where('id', '!=', $sender_id);
            $query->where(function ($query){
                $query->where('type', 'parent');
                $query->orWhere('type', 'Principal');
            });
            $query->whereIn('id', $userIds);
        }])
            ->orderBy('updated_at', 'DESC')->get();

        $response = [
            'chat_users' => $chatExists,
            'to_user_id' => $to_user->id ?? null,
            'to_user_name' => $to_user ? $to_user->first_name . " " . $to_user->last_name : null,
            'chat' => $chats
        ];

        return response()->json($response);
    }

    public function createChat(Request $request){
        if(!$request->to_user_id){
            return response()->json([
                'status' => 400,
                'message' => trans('error_occurred')
            ], 400);
        }

        $user = Auth::user();
        $to_user_id = $request->to_user_id;
        $receiver = User::where('id',$to_user_id)->first();
        if(!$receiver){
            return response()->json([
                'status' => 400,
                'message' => "Invalid user detected",
                'data' => [
                    "to_user_id" => $to_user_id
                ]
            ], 400);
        }
        $file_name = null;
        $file = $request->file;
        $destinationPath = null;
        $file_extension = null;
        if($file){
            $file_extension = $request->file_extension;
            $file_name = Utility::getUUID() . "." . $file_extension;
            $destinationPath = storage_path('app/public/chat/files');
            $file->move($destinationPath, $file_name);
        }

        $chatExists = Chat::where(function ($query) use ($user,$receiver){
            $query->where('user_id',$user->id);
            $query->where('to_user_id',$receiver->id);
        })->orWhere(function ($query) use ($user,$receiver){
            $query->where('user_id',$receiver->id);
            $query->where('to_user_id',$user->id);
        })->first();

        $is_image = false;
        $ext = explode('.',$file_name);
        if(sizeof($ext) > 1 && in_array($ext[1],['png','jpg','jpeg','svg','webp','mp4','gif'])){
            $is_image = true;
        }

        $chatJson =
            [
                'message' => $request->message,
                'sender_id' => $user->id,
                'file' => $file_name,
                'file_extension' => $file_extension,
                'is_image' => $is_image,
                'sender_name' => $user->first_name,
                'receiver_name' => $receiver->first_name,
                'created_at' => Carbon::now()
            ];

        $chatId = Utility::getUUID();
        if(!$chatExists){
            $chat = new Chat();
            $chat->id = $chatId;
            $chat->user_id = $user->id;
            $chat->to_user_id = $to_user_id;
            $chat->new_message = true;
            $chat->message = json_encode($chatJson,true);
            $chat->save();
        }else{
            $chatId = $chatExists->id;
            if($chatExists->message['sender_id'] == $user->id){
                $chatExists->new_message_count = $chatExists->new_message_count+1;
            }else{
                $chatExists->new_message_count = 1;
            }
            $chatExists->new_message = true;
            $chatExists->message = $chatJson;
            $chatExists->save();
        }
        $this->brodCastMessage($user,$request->message,$to_user_id,$chatJson);
        $this->sendMessage($request,$destinationPath,$file_name,$chatId,$chatJson);
        return response()->json($chatJson);
    }

    public function chatRead(Request $request){
        $chat_id = $request->id;
        $user = Auth::user();

        $isChat = Chat::where('id',$chat_id)
            ->where(function ($query) use ($user){
                $query->where('user_id',$user->id);
                $query->orWhere('to_user_id',$user->id);
            })
            ->first();
        if(!$isChat){
            return response()->json([
                'status' => 400,
                'message' => trans('no_permission_message')
            ], 400);
        }

        if($isChat->message['sender_id'] != $user->id){
            $isChat->new_message_count = 0;
            $isChat->save();
        }
        return response()->json($isChat->refresh());

    }
    private function brodCastMessage($user,$message,$to_user_id,$chatJson){

        $message =  $message ?? "New File";
        $senderName = $user->first_name.' '.$user->last_name;
        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60*20);
        $notificationBuilder = new PayloadNotificationBuilder('New Message From : '.$senderName);
        $notificationBuilder->setBody($message?? 'New File')
            ->setSound('default')
            ->setClickAction(config('environment.APP_SITE'));
        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(
            [
                'sender_name' => $senderName,
                'message' => $message,
                'body' => $chatJson
            ]);

        $tokenUser = User::where('id',$to_user_id)->where('enable_notification',1)->first();

        if($tokenUser){
            $topic = new Topics();
            $topic->topic($tokenUser->fcm_id)->andTopic(function($condition) use ($tokenUser){
                $condition->topic($tokenUser->fcm_id)->orTopic('cultural');
            });
            $data = $dataBuilder->build();
            PushNotificationSender::sendMobileMessage('New Message From : '.$senderName, $message, $tokenUser->fcm_id, "CHAT", $data);
            $this->notificationSender->saveLogs($to_user_id,$user->id,$message,'CHAT','New Message From : '.$senderName);

        }

    }

    public function sendMessage($request,$destinationPath,$fileName,$chatId,$chatJson)
Bash Read class, announcement, notification and student endpoints
IN
cd /Users/apple/StudioProjects/ptm-backend-php/app/Http/Controllers/Api/v2 && cat ClassController.php AnnouncementController.php NotificationController.php && sed -n 1,90p StudentController.php && sed -n 660,720p StudentController.php && sed -n 825,870p StudentController.php

OUT
<?php

namespace App\Http\Controllers\Api\v2;

use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassController
{
    public function __construct()
    {
    }

    public function getTeacherClassList(Request $request){
        $user = $request->user();
        $class = ClassSchool::whereHas('sections',function ($query) use ($user){
            $query->where('class_teacher_id',$user->id);
        })->with('allSubjects.subject')
            ->with(['class_section' => function($query) use($user){
                $query->withCount(['students'=>function($query){
                    $query->where('class_status','ACCEPTED');
                    $query->whereHas('user');
                }]);
            }])->get();
        $response = array(
            'error' => false,
            'message' => 'Teacher Classes Fetched Successfully.',
            'data' => $class,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function getTeacherClasses(Request $request){
        $user = $request->user();
        $teacher_id = $request->teacher_id;
        $class = ClassSchool::whereHas('sections',function ($query) use ($teacher_id){
            $query->where('class_teacher_id',$teacher_id);
        })->get();
        $response = array(
            'error' => false,
            'message' => 'Teacher Classes Fetched Successfully.',
            'data' => $class,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function subjects(Request $request)
    {

        try {
            $user = $request->user();
            $class_id = $request->class_id;
            $classSection =  ClassSection::where('class_id',$class_id)->first();
            $subjects = null;
            if ($classSection) {
                $subjects = Subject::whereHas('subject_teacher',function ($query) use ($user,$classSection){
                    $query->where('teacher_id',$user->id);
                    $query->where('class_section_id',$classSection->id);
                })->get();
            }

            $response = array(
                'error' => false,
                'message' => 'Teacher Subject Fetched Successfully.',
                'data' => $subjects,
                'code' => 200,
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response, 200);
        }
    }
    public function subjects2(Request $request)
    {

        try {
            $user = $request->user();
            $class_id = $request->class_id;
            $classSection =  ClassSection::where('class_id',$class_id)->first();
            $subjects = null;
            if ($classSection) {
                $subjects = ClassSubject::where('class_id',$class_id)->with('subject')->get();
            }

            $response = array(
                'error' => false,
                'message' => 'Subject Fetched Successfully.',
                'data' => $subjects,
                'code' => 200,
            );
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'code' => 103,
            );
            return response()->json($response, 200);
        }
    }
}<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\UserSchools;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct()
    {
    }

    public function announcement(Request $request){
        $user = $request->user();
        $search_param = $request->search_param ?? null;
        $userSchool = UserSchools::where('user_id',$user->id)->pluck('school_id')->toArray();
        $announcement = Announcement::where('created_at','>',$user->created_at)
        ->with('file')->where(function ($query) use ($user,$userSchool,$search_param){
            if($search_param){
                $query->where('title', 'LIKE', '%' . $search_param . '%');
            }
            if($user->type == 'parent'){
                $query->where(function ($query) use ($userSchool){
                    $query->where('to_user','parent');
                    $query->orWhere('to_user',null);
                });
                $query->where(function ($query) use ($userSchool){
                    $query->whereIn('school_id',$userSchool);
                    $query->orWhere('school_id',null);
                });
            }
            if($user->type == 'teacher'){
                $query->where(function ($query) use ($userSchool){
                    $query->where('to_user','teacher');
                    $query->orWhere('to_user',null);
                });
                $query->where(function ($query) use ($userSchool){
                    $query->whereIn('school_id',$userSchool);
                    $query->orWhere('school_id',null);
                });
            }

        })->with('school')->orderBy('created_at','DESC')->paginate(10);

        $response = array(
            'error' => false,
            'message' => 'Announcement fetched Successfully.',
            'data' => $announcement,
            'code' => 200,
        );
        return response()->json($response);
    }

}<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogs;
use Illuminate\Http\Request;

class NotificationController extends Controller
{

    public function __construct()
    {
    }

    public function notificationLogs(Request $request)
    {
        $user = $request->user();
        $logs = NotificationLogs::where('user_id', $user->id)
            ->with('fromUser')
            ->orderBy('created_at', 'DESC')->get();

        $response = array(
            'error' => false,
            'message' => 'User notifications successfully fetched',
            'data' => $logs,
            'code' => 200,
        );
        return response()->json($response);
    }

    public function notificationRead(Request $request)
    {
        $user = $request->user();
        $logs = NotificationLogs::where('id', $request->notification_id)
            ->where('user_id', $user->id)->update([
                'read' => 1
            ]);

        if (!$logs) {
            $response = array(
                'error' => true,
                'message' => 'User notifications failed to update',
                'data' => $logs,
                'code' => 400
            );
        } else {
            $response = array(
                'error' => false,
                'message' => 'User notifications successfully updated',
                'data' => $logs,
                'code' => 200
            );
        }
        return response()->json($response);

    }

}<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Models\Attendance;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\MainMeeting;
use App\Models\Meetings;
use App\Models\Parents;
use App\Models\Schools;
use App\Models\Settings;
use App\Models\StudentQuarter;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\StudentSubject;
use App\Models\User;
use App\Models\UserSchools;
use App\Services\NotificationSender;
use App\Support\Access;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Exception;
use Spatie\Permission\Models\Role;
use stdClass;
use function Termwind\style;


class StudentController
{

    private NotificationSender $notificationSender;

    public function __construct(NotificationSender $notificationSender)
    {
        $this->notificationSender = $notificationSender;
    }
    public function classSectionStudents(Request $request)
    {
        $name = $request->get('name');
        $userID = $request->user()->id;
        $class_id = $request->class_id;

        $students = User::where(function ($query) use ($name) {
            if($name){
                $query->where('name', 'LIKE', '%'.$name.'%');

            }
        })
            ->where('type', 'student')
            ->with('student.class_section.class')
            ->with('student.father')
            ->with('student.mother')
            ->with('userSchool.school')
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->where('class_status','ACCEPTED');
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->orderBy('first_name', 'ASC')->get();

        if ($students) {
            $response = array(
                'error' => false,
                'message' => trans('data_fetch_successfully'),
                'data' => $students
            );
        } else {
            $response = array(
                'error' => true,
                'message' => trans('no_data_found'),
                'data' => $students
            );
        }

        return response()->json($response);
    }
    public function classSectionStudentsRequests(Request $request)
    {
        $name = $request->get('name');
        $userID = $request->user()->id;
            'error' => false,
            'message' => 'Student deleted Successfully.',
            'data' => $user,
            'code' => 200
        );
        return response()->json($response);
    }

    public function parentSchools(Request $request)
    {
        $userId = $request->user()->id;
        $schoolName = $request->get('name');

        $schools = Schools::where(function ($query) use ($schoolName) {
            if ($schoolName) {
                $query->where('school_name', 'LIKE', "%{$schoolName}%");
            }
        })->whereHas('userSchools', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();
        $response = array(
            'error' => false,
            'message' => 'Schools fetched Successfully.',
            'data' => $schools,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function schoolTeacher(Request $request)
    {
        $userId = $request->user()->id;
        $school_id  = $request->school_id;
        if($request->user()->type == 'teacher'){
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        if(!$school_id){
            $schoolIds = UserSchools::where('user_id', $userId)->pluck('school_id')->toArray();
            $userIds = UserSchools::whereIn('school_id', $schoolIds)->pluck('user_id')->toArray();
            $teachers = User::whereHas('teacher')->with('userSchool.school')->whereIn('id', $userIds)->get();
        }else{
            $userIds = UserSchools::where('school_id', $school_id)->pluck('user_id')->toArray();
            $teachers = User::whereHas('teacher')->with('userSchool.school')->whereIn('id', $userIds)->get();
        }

        $response = array(
            'error' => false,
            'message' => 'School teachers fetched Successfully.',
            'data' => $teachers,
            'code' => 200,
        );
        return response()->json($response, 200);
    }

    public function studentReport(Request $request){
        if ($request->student_id && !Access::canViewStudent($request->user(), $request->student_id)) {
            'data' => $teachers,
            'code' => 200,
        );
        return response()->json($response, 200);
    }
    public function studentParents(Request $request){
        $user = $request->user();
        if ($user && $user->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null,
                'code' => 400,
            );
            return response()->json($response);
        }
        $request->validate([
            'student_id' => 'required'
        ]);

        $student_id = $request->student_id;

        $mothers = Students::where('user_id',$student_id)->pluck('mother_id')->toArray();
        $fathers = Students::where('user_id',$student_id)->pluck('father_id')->toArray();

        $parents = User::where('type','parent')
            ->where(function ($query) use ($mothers,$fathers){
                $query->whereIn('id',$mothers);
                $query->orWhere('id',$fathers);
            })
            ->orderBy('first_name','ASC')
            ->get();

        $response = array(
            'error' => false,
            'message' => 'Student\'s parents fetched Successfully.',
            'data' => $parents,
            'code' => 200,
        );
        return response()->json($response, 200);
    }
    public function studentAttendance(Request $request){

        $request->validate([
            'student_id' => 'required'
        ]);
Bash Read Access helper, subject delete and report create
IN
cd /Users/apple/StudioProjects/ptm-backend-php/app && ls Support Services Helpers; cat Support/*.php; sed -n 1930,2010p Http/Controllers/Api/ApiController.php; sed -n 415,560p Http/Controllers/Api/v2/TeacherController.php

OUT
Helpers:
Utility.php
notification_helper.php
settings_helper.php

Services:
CountryService.php
NotificationSender.php
PushNotificationSender.php

Support:
Access.php
<?php

namespace App\Support;

use App\Models\ClassSection;
use App\Models\Students;
use App\Models\User;

/**
 * Ownership checks shared by the mobile API controllers.
 */
class Access
{
    public static function isPrincipal(User $user): bool
    {
        return $user->type === 'Principal' || $user->hasRole('Principal');
    }

    /**
     * True when the user is the class teacher of any section of the class.
     */
    public static function teacherOwnsClass(User $user, $classId): bool
    {
        return ClassSection::where('class_id', $classId)
            ->where('class_teacher_id', $user->id)
            ->exists();
    }

    /**
     * Whether the user may read data about a student.
     * $studentUserId is the student's users.id (what attendance and reports store).
     */
    public static function canViewStudent(User $user, $studentUserId): bool
    {
        if (self::isPrincipal($user)) {
            return true;
        }

        $student = Students::where('user_id', $studentUserId)->first();
        if (!$student) {
            return false;
        }

        if ((int) $student->user_id === (int) $user->id) {
            return true;
        }

        if (in_array((int) $user->id, array_map('intval', array_filter([
            $student->father_id, $student->mother_id, $student->guardian_id,
        ])), true)) {
            return true;
        }

        return $student->class_section_id
            && ClassSection::where('id', $student->class_section_id)
                ->where('class_teacher_id', $user->id)
                ->exists();
    }

    public static function forbidden()
    {
        return response()->json([
            'error' => true,
            'message' => trans('no_permission_message'),
            'code' => 403,
        ], 403);
    }
}
            'data' => []
        ]);
    }

    public function subjectDelete(Request $request){
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response,400);
        }

        $validator = Validator::make($request->all(), [
            'subject_id' => 'required',
            'class_id' => 'required'
        ]);
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first(),
                'code' => 102,
            );
            return response()->json($response,400);
        }

        $teachesSubject = SubjectTeacher::where('subject_id', $request->subject_id)
            ->where('teacher_id', $user->id)
            ->whereIn('class_section_id', ClassSection::where('class_id', $request->class_id)->pluck('id'))
            ->exists();
        if (!$teachesSubject && !Access::teacherOwnsClass($user, $request->class_id)) {
            return Access::forbidden();
        }
        $subject_id = $request->subject_id;

        $canDelete = Subject::where('id',$subject_id)->first();
//        if(!$canDelete){
//            $response = array(
//                'message' => trans('no_permission_message')
//            );
//            return response()->json($response,400);
//        }
        $classSection = ClassSection::where('class_id', $request->class_id)->first();

//        StudentReportCards::where('subject_id',$subject_id)->where('class_id',$request->class_id)->delete();
        SubjectTeacher::where('subject_id',$subject_id)
            ->where('class_section_id',$classSection->id)
            ->where('teacher_id',$user->id)->delete();

        $subjectStudents = StudentSubject::where('subject_id',$subject_id)->where('class_section_id',$classSection->id)
            ->pluck('student_id')->toArray();

        foreach ($subjectStudents as $subjectStudent){
            $student = Students::where('user_id',$subjectStudent)->first();
            if($student){

                $message = "Subject ".$canDelete->name ." has been removed by the teacher";
                $this->notificationSender->sendNotification(
                    "Subject Removed",
                    $message,
                    [$student->mother_id,$student->father_id], "SUBJECT_REMOVED", $canDelete);
                $this->notificationSender->saveLogs($student->mother_id ?? $student->father_id,$user->id,$message,'SUBJECT_REMOVED','Subject removed by the teacher');

            }
        }

        StudentSubject::where('subject_id',$subject_id)
            ->where('class_section_id',$classSection->id)->delete();

//        Attendance::where('class_section_id',$classSection->id)->where('subject_id',$subject_id)->delete();

        ClassSubject::where('subject_id',$subject_id)->where('class_id',$request->class_id)->delete();
//        Subject::where('id',$subject_id)->delete();
        return response()->json([
            'error' => false,
            'status' => 200,
            'message' => 'Subject deleted successfully.',
            'data' => []
        ]);
    }
}
                'quarters' => Quarters::orderBy('name', 'ASC')->get()
            )
        );

    }

    public function addStudentReport(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        Log::info($request);
        if ($request->user()->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        $user = $request->user();
        $userID = $user->id;
        $validator = Validator::make($request->all(), [
            'quarter_id' => 'required',
            'reports.*.grade' => 'nullable',
            'class_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'session_year_id' => 'required|numeric',
            'reports.*.student_id' => 'required',
            'reports.*.points' => 'nullable|numeric',
            'reports.*.review' => 'nullable',
            'reports.*.missing_cause' => 'nullable'
        ]);
        $class_id = $request->class_id;

        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if (!$classSection) {
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }
        $isSubject = Subject::where('id', $request->subject_id)->first();
        if (!$isSubject) {
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => null
            );
            return response()->json($response);
        }
        $studentIds = array_column($request->reports, 'student_id');
        $isClassStudents = User::where('type', 'student')->whereIn('id', $studentIds)
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->count();

        if (sizeof($studentIds) != $isClassStudents) {
            $response = array(
                'error' => true,
                'message' => "Some of provided students are invalid."
            );
            return response()->json($response);
        }

        $sessionYear = SessionYear::where('id', $request->session_year_id)->first();
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $array = array();
        $allStudents = [];
        try {
            DB::beginTransaction();

            $classQuarter = ClassQuarter::where('quarter_id',$request->quarter_id)
                ->where('session_year_id', $sessionYear->id)
                ->where('class_id', $class_id)
                ->first();
            if(!$classQuarter){
                $classQuarter = new ClassQuarter();
                $classQuarter->id = Utility::getUUID();
                $classQuarter->quarter_id = $request->quarter_id;
                $classQuarter->class_id = $request->class_id;
                $classQuarter->status = "PENDING";
                $classQuarter->session_year = $sessionYear->name;
                $classQuarter->session_year_id = $sessionYear->id;
                $classQuarter->save();
                $classQuarter = $classQuarter->refresh();
            }

            $canPublish = false;
            foreach ($request->reports as $report) {
                $deleted =  StudentReportCards::where('class_id', $class_id)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('subject_id', $request->subject_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('student_id', $report['student_id'])
                    ->delete();

                $array [] = [
                    "id" => Utility::getUUID(),
                    "quarter_id" => $request->quarter_id,
                    "class_id" => $class_id,
                    "grade" => $report['grade'],
                    "session_year" => $sessionYear->name,
                    "session_year_id" => $request->session_year_id,
                    "subject_id" => $request->subject_id,
                    "student_id" => $report['student_id'],
                    "points" => $report['points'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    "review" => $report['review'] ?? null,
                    "missing_cause" => $report['missing_cause'] ?? null
                ];
            }

            $result = StudentReportCards::insert($array);

            $allStudents = User::where('type', 'student')
                ->whereHas('student', function ($query) use ($userID, $class_id) {
                    $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                        $query->where('class_id', $class_id);
                    });
                })->pluck('id')->toArray();

            $publishedStudents = StudentQuarter::where('quarter_id', $request->quarter_id)
                ->where('class_id',$class_id)
                ->where('session_year_id', $request->session_year_id)
                ->pluck('student_id')->toArray();
            $unPublishedStudents = array_diff($allStudents, $publishedStudents);
            $newStudentQuarter = array();

            $notificationUsers =[];
            $quarters = Quarters::where('id',$request->quarter_id)->first();

            $classSubjectsSql = ClassSubject::where('class_id', $request->class_id);
Bash Read rest of report create/edit
IN
sed -n 560,925p Http/Controllers/Api/v2/TeacherController.php

OUT
            $classSubjectsSql = ClassSubject::where('class_id', $request->class_id);
            $classSubjectsCount = $classSubjectsSql->count();
            $classSubjects = $classSubjectsSql->pluck('subject_id')->toArray();

            $missing_cause_count = 0;
            foreach ($classSubjects as $classSubject){
                $subjectNotEmptyReports = StudentReportCards::where('session_year_id',$request->session_year_id)
                    ->where('quarter_id',$request->quarter_id)
                    ->where('subject_id',$classSubject)
                    ->where('class_id',$request->class_id)
                    ->where('missing_cause',null)
                    ->where('points',null)
                    ->first();

                if(!$subjectNotEmptyReports){
                    $missing_cause_count = $missing_cause_count+1;
                }
            }

            if($missing_cause_count == $classSubjectsCount){
                $canPublish = true;
            }
            foreach ($unPublishedStudents as $unPublishedStudent) {
                $existingReportCardCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('class_id', $class_id)
                    ->count();

                $missingReasonsCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('missing_cause', '!=',null)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('class_id', $class_id)
                    ->count();


                if ($existingReportCardCount == $classSubjectsCount &&  $canPublish) {

                    $newStudentQuarter [] = [
                        'id' => Utility::getUUID(),
                        'quarter_id' => $request->quarter_id,
                        'student_id' => $unPublishedStudent,
                        'class_id' => $class_id,
                        'session_year_id' => $request->session_year_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'status' => 'PUBLISHED',
                    ];

                    $student = Students::with('user')->where('user_id',$unPublishedStudent)->first();

//                    $studentUser [] = User::where('id',$unPublishedStudent)
//                        ->with('student.class_section.class.school')
//                        ->with(['student' => function($query){
//                            $query->withCount('student_subjects');
//                        }])
//                        ->first();

                    $logOwnId = $student->mother_id ?? $student->father_id;
                    if($missingReasonsCount!=$classSubjectsCount){
                        $notificationUsers[] = $logOwnId;
                    }
                    $classQuarter->status = 'PUBLISHED';
                    $classQuarter->save();
                }
            }
            if(sizeof($notificationUsers)>0){
                $message = "A new report has been published for ".$quarters->name;

                $notificationUsers = array_unique($notificationUsers);

                $this->notificationSender->sendNotification(
                    "Report Published",
                    $message,
                    $notificationUsers, "REPORT");
                foreach ($notificationUsers as $notificationUser){
                    $this->notificationSender->saveLogs($notificationUser,$user->id,$message,'REPORT','Report Published');
                }
                $notificationUsers = [];
            }
            StudentQuarter::insert($newStudentQuarter);

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
                'report' => $result
            );
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'report' => null
            );
        }

        return response()->json(
            $response
        );
    }

    public function deleteStudentReport(Request $request, $id){

        $classQuarter = ClassQuarter::where('id',$id)->first();
        if ($classQuarter && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $classQuarter->class_id)) {
            return Access::forbidden();
        }
        if($classQuarter){
            $sessionYear = SessionYear::where('name', $classQuarter->session_year)->first();

            StudentQuarter::where('quarter_id',$classQuarter->quarter_id)
                ->where('class_id',$classQuarter->class_id)
                ->where('session_year_id',$sessionYear->id)
                ->delete();

            StudentReportCards::where('class_id', $classQuarter->class_id)
                ->where('quarter_id', $classQuarter->quarter_id)
                ->where('session_year_id', $sessionYear->id)
                ->delete();

            $deleted = ClassQuarter::where('id',$id)->delete();
            if($deleted){
                $response = array(
                    'error' => false,
                    'message' => "Class quarter successfully deleted",
                    'data' => $deleted
                );
            }else{
                $response = array(
                    'error' => true,
                    'message' => "Failed to delete class quarter!",
                    'data' => $deleted
                );
            }
            return response()->json($response);
        }
        $response = array(
            'error' => true,
            'message' => "Failed to delete class quarter!",
            'data' => null
        );
        return response()->json($response);
    }

    public function editStudentReport(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        Log::info($request);
        if ($request->user()->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
            return response()->json($response);
        }
        $validator = Validator::make($request->all(), [
            'quarter_id' => 'required',
            'class_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'session_year_id' => 'required|numeric',
            'reports.*.student_id' => 'required',
            'reports.*.points' => 'nullable|numeric',
            'reports.*.review' => 'nullable',
            'reports.*.missing_cause' => 'nullable',
            'reports.*.grade' => 'nullable'
        ]);
        $class_id = $request->class_id;
        $userID = $request->user()->id;

        $classSection = ClassSection::where('class_id', $class_id)->first();
        if (!$classSection) {
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }
        $isSubject = Subject::where('id', $request->subject_id)->first();
        if (!$isSubject) {
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => null
            );
            return response()->json($response);
        }

        $studentIds = array_column($request->reports, 'student_id');
        $isClassStudents = User::where('type', 'student')->whereIn('id', $studentIds)
            ->whereHas('student', function ($query) use ($userID, $class_id) {
                $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                    $query->where('class_teacher_id', $userID);
                    $query->where('class_id', $class_id);
                });
            })->count();

        if (sizeof($studentIds) != $isClassStudents) {
            $response = array(
                'error' => true,
                'message' => "Some of provided students are invalid."
            );
            return response()->json($response);
        }

        $sessionYear = SessionYear::where('id', $request->session_year_id)->first();
        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $array = array();
        try {
            DB::beginTransaction();
            $canPublish = false;

            foreach ($request->reports as $report) {
               $deleted = StudentReportCards::where('class_id', $request->class_id)
                    ->where('quarter_id', $request->quarter_id)
                    ->where('subject_id', $request->subject_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('student_id', $report['student_id'])
                    ->delete();

                $array [] = [
                    "id" => Utility::getUUID(),
                    "quarter_id" => $request->quarter_id,
                    "class_id" => $request->class_id,
                    "grade" => $report['grade'],
                    "session_year" => $sessionYear->name,
                    "session_year_id" => $request->session_year_id,
                    "subject_id" => $request->subject_id,
                    "student_id" => $report['student_id'],
                    "points" => $report['points'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    "review" => $report['review'] ?? null,
                    "missing_cause" => $report['missing_cause'] ?? null
                ];

            }
            $result = StudentReportCards::insert($array);

            $allStudents = User::where('type', 'student')
                ->whereHas('student', function ($query) use ($userID, $class_id) {
                    $query->whereHas('class_section', function ($query) use ($userID, $class_id) {
                        $query->where('class_id', $class_id);
                    });
                })->pluck('id')->toArray();

            $publishedStudents = StudentQuarter::where('quarter_id', $request->quarter_id)
                ->where('class_id',$class_id)
                ->where('session_year_id', $request->session_year_id)
                ->pluck('student_id')->toArray();

            $unPublishedStudents = array_diff($allStudents, $publishedStudents);
            $newStudentQuarter = array();

            $classQuarter = ClassQuarter::where('quarter_id',$request->quarter_id)
                ->where('session_year_id', $sessionYear->id)
                ->where('class_id', $class_id)
                ->first();
            $notificationUsers = [];
            $quarters = Quarters::where('id',$request->quarter_id)->first();
            $classSubjectsSql = ClassSubject::where('class_id', $request->class_id);
            $classSubjectsCount = $classSubjectsSql->count();
            $classSubjects = $classSubjectsSql->pluck('subject_id')->toArray();

            $missing_cause_count = 0;
            foreach ($classSubjects as $classSubject){
                $subjectNotEmptyReports = StudentReportCards::where('session_year_id',$request->session_year_id)
                    ->where('quarter_id',$request->quarter_id)
                    ->where('subject_id',$classSubject)
                    ->where('class_id',$request->class_id)
                     ->where('missing_cause',null)
                     ->where('points',null)
                    ->first();
                if(!$subjectNotEmptyReports){
                    $missing_cause_count = $missing_cause_count+1;
                }
            }

            if($missing_cause_count == $classSubjectsCount){
                $canPublish = true;
            }

            foreach ($unPublishedStudents as $unPublishedStudent) {
                $existingReportCardCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('class_id',$class_id)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('quarter_id', $request->quarter_id)->count();

                $missingReasonsCount = StudentReportCards::where('student_id', $unPublishedStudent)
                    ->where('class_id',$class_id)
                    ->where('missing_cause', '!=',null)
                    ->where('session_year_id', $request->session_year_id)
                    ->where('quarter_id', $request->quarter_id)->count();


                if ($existingReportCardCount == $classSubjectsCount && $canPublish) {
                    $newStudentQuarter [] = [
                        'id' => Utility::getUUID(),
                        'quarter_id' => $request->quarter_id,
                        'student_id' => $unPublishedStudent,
                        'created_at' => Carbon::now(),
                        'class_id' => $class_id,
                        'session_year_id' => $request->session_year_id,
                        'updated_at' => Carbon::now(),
                        'status' => 'PUBLISHED',
                    ];
                    $student = Students::with('user')->where('user_id',$unPublishedStudent)->first();
                    $message = "A new report has been published for ".$quarters->name;

                    $logOwnId = $student->mother_id ?? $student->father_id;
                    if($missingReasonsCount!=$classSubjectsCount){
                        $notificationUsers[] = $logOwnId;
                    }
                    $classQuarter->status = 'PUBLISHED';
                    $classQuarter->save();
                }
            }
            if(sizeof($notificationUsers)>0){
                $message = "A new report has been published for ".$quarters->name;

                $notificationUsers = array_unique($notificationUsers);
                $this->notificationSender->sendNotification(
                    "Report Published",
                    $message,
                    $notificationUsers, "REPORT");
                foreach ($notificationUsers as $notificationUser){
                    $this->notificationSender->saveLogs($notificationUser,$userID,$message,'REPORT','Report Published');
                }
                $notificationUsers = [];
            }
            StudentQuarter::insert($newStudentQuarter);

            $response = array(
                'error' => false,
                'message' => trans('data_store_successfully'),
                'report' => $result
            );
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'report' => null
            );
        }

        return response()->json(
            $response
        );
    }
10:31 PM
Now the call/subscription code, to see how update-remaining-time can be checked against server-side call records.



Bash Read call room and subscription logic
IN
sed -n 745,830p Http/Controllers/MeetingController.php; cat Models/UserCallRoom.php Models/CallDetails.php Models/UserSubscription.php | grep -v "^\s*\*\|/\*\*" ; grep -n "remaining_time" -r Http | head -20; cat ../database/migrations/2024_01_04_020506_create_user_call_room_table.php ../database/migrations/2023_12_19_102344_create_call_details_table.php | grep "\$table"

OUT
        }
        return view('eventDetails')->with([
            'meetingDetails' => $meetingDetails,
            'user'=>$user
        ]);
    }

    public function makeCallRoom(Request $request){
        $channel_name = $request->channel_name;
        $user_id = Auth::user()->id;

        $isActive = UserSubscription::where('user_id',$user_id)
            ->where('status',"ACTIVE")->where('remaining_time','!=','0')->first();

        if(!$isActive){
            $response = [
                'error' => true,
                'message' => "No Active Subscription",
                'data' => UserSubscription::where('user_id',$user_id)
                    ->where('status',"ACTIVE")->first()
            ];
            return response()->json($response,400);
        }


        $isRoom = UserCallRoom::where('user_id',$user_id)->where('chanel_name',$channel_name)->first();
        if(!$isRoom){
            $isRoom = new UserCallRoom();
            $isRoom->id = Utility::getUUID();
            $isRoom->user_id = $user_id;
            $isRoom->chanel_name = $channel_name;
            $isRoom->status = 'JOINED';
        }else{
            $isRoom->status = 'JOINED';
            $isRoom->updated_at = Carbon::now();
        }
        $isRoom->save();
        $response = [
            'error' => false,
            'message' => "Room Updated",
            'data' => $isRoom,
        ];
        return response()->json($response);
    }

    public function leftFormCallRoom(Request $request){
        $channel_name = $request->channel_name;
        $user_id = Auth::user()->id;

        $isRoom = UserCallRoom::where('user_id',$user_id)
            ->where('chanel_name',$channel_name)
            ->where('status','JOINED')
            ->first();
        if($isRoom){
            $isRoom->status = 'LEFT';
            $isRoom->updated_at = Carbon::now();
            $isRoom->save();
        }

        $response = [
            'error' => false,
            'message' => "Room Updated",
            'data' => $isRoom,
        ];
        return response()->json($response);

    }

    public function channelToken(Request $request){
        $isActive = UserSubscription::where('user_id',Auth::user()->id)
            ->where('status','ACTIVE')->where('remaining_time','!=','0')->first();
        if(!$isActive){
            $response = [
                'error' => true,
                'message' => "No Active Subscription",
                'data' => $isActive,
            ];
            return response()->json($response,400);
        }

        $channelName = $request->channel_name;
        $appID = config('environment.AGORA_APP_ID');
        $appCertificate = config('environment.AGORA_APP_CERTIFICATE');
        $role = RtcTokenBuilder::RoleAttendee;
        $expireTimeInSeconds = 660;

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCallRoom extends Model
{
    protected $table = 'user_call_room';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'chanel_name','duration','sid', 'status', 'created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallDetails extends Model
{
    protected $table = 'call_details';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['host_user_id', 'end_user_id', 'sid', 'channel_name', 'duration', 'created_at', 'updated_at'];

    public function host_user()
    {
        return $this->belongsTo('App\Models\User', 'end_user_id');
    }

    public function end_user()
    {
        return $this->belongsTo('App\Models\User', 'host_user_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $table = 'user_subscription';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id','email', 'subscription_id','plan_id', 'type', 'status', 'created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
Http/Controllers/MeetingController.php:757:            ->where('status',"ACTIVE")->where('remaining_time','!=','0')->first();
Http/Controllers/MeetingController.php:815:            ->where('status','ACTIVE')->where('remaining_time','!=','0')->first();
Http/Controllers/WebhookController.php:432:            ->where('status','ACTIVE')->where('remaining_time','!=',0)->first();
Http/Controllers/WebhookController.php:434:            $remainingTime = $isActive->remaining_time - $duration;
Http/Controllers/WebhookController.php:435:            $isActive->remaining_time = max($remainingTime, 0);
Http/Controllers/WebhookController.php:437:                Log::info("reduce call remaining_time by $duration s for user $user_id");
Http/Controllers/Payment/SubscriptionController.php:47:            ->where('status','ACTIVE')->where('remaining_time','!=',0)->first();
Http/Controllers/Payment/SubscriptionController.php:190:        $subscription->remaining_time = config('environment.TRAIL')*60;
Http/Controllers/Api/ApiController.php:891:            ->where('status',"ACTIVE")->where('remaining_time','!=','0')->first();
Http/Controllers/Api/AuthController.php:220:        $subscription->remaining_time = config('environment.TRAIL')*60;
Bash Read Agora webhook minute deduction
IN
cd /Users/apple/StudioProjects/ptm-backend-php && sed -n 370,470p app/Http/Controllers/WebhookController.php; grep -h "\$table" database/migrations/*call_room* database/migrations/*call_details* database/migrations/*event_type_to_call*

OUT
                Log::error($event->type);
                // Unexpected event type
                Log::error('Received unknown event type');
        }
    }

    public function agora(Request $request){
        // Agora NCS signs the raw body with HMAC-SHA256 (header Agora-Signature-V2).
        $secret = config('environment.AGORA_WEBHOOK_SECRET');
        $signature = (string) $request->header('Agora-Signature-V2');
        if (!$secret || !hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            Log::warning('Agora webhook rejected: invalid signature or AGORA_WEBHOOK_SECRET not configured.');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        Log::info($request);
        $eventType = $request->eventType;
        $sid = $request->sid;
        $payload = $request->payload;
        Log::info($payload['uid']);


        if($eventType == 103){
            $user_id = $payload['account'] ?? $payload['uid'];
            $isRoom = UserCallRoom::where('chanel_name',$payload['channelName'])
                ->where('user_id',$user_id)->first();
            if(!$isRoom){
                $isRoom = new UserCallRoom();
                $isRoom->id = Utility::getUUID();
                $isRoom->chanel_name = $payload['channelName'];
                $isRoom->user_id = $user_id;
                $isRoom->sid = $sid;
                $isRoom->status = 'JOINED';
            }elseif ($isRoom->status == 'JOINED'){
                $isRoom->updated_at = Carbon::now();
            }elseif ($isRoom->status == 'LEFT'){
                $isRoom->updated_at = Carbon::now();
                $isRoom->status = 'JOINED';
            }
            $isRoom->save();
        }

        if($eventType == 104 || $eventType == 108){
            $duration = $payload['duration'];
            $user_id = $payload['account'];
            $isRoom = UserCallRoom::where('chanel_name',$payload['channelName'])
                ->where('user_id',$user_id)->first();
            if($isRoom){
                if($isRoom->status == 'JOINED'){
                    $isRoom->updated_at = Carbon::now();
                    $isRoom->status = 'LEFT';
                    $isRoom->sid = $sid;
                    $isRoom->duration = $isRoom->duration+$duration;
                    $this->inactivateSubscription($user_id,$duration);
                }
                $isRoom->save();
            }
        }
    }

    private function inactivateSubscription($user_id,$duration = 0){
        $isActive = UserSubscription::where('user_id',$user_id)
            ->where('status','ACTIVE')->where('remaining_time','!=',0)->first();
        if($isActive){
            $remainingTime = $isActive->remaining_time - $duration;
            $isActive->remaining_time = max($remainingTime, 0);
            if($remainingTime <= 0){
                Log::info("reduce call remaining_time by $duration s for user $user_id");
                $isActive->status = 'INACTIVE';
                $isActive->call_count = 0;

            }
            $isActive->save();
            $isActive = $isActive->refresh();

            if($isActive->status == 'INACTIVE' && $isActive->subscription_id){
                $subscriptionPlan = new SubscriptionPlan();
                Log::info("inactivating paypal subscription for subscription_id ". $isActive->subscription_id);
                $subscriptionPlan->cancelSubscription($isActive->subscription_id,true, [
                    "reason" => "Inactivate automatically due to 0 remaining time"
                ]);
            }
        }
    }

    public function subscriptionActivated(Request $request){
        if (!(new WebhookVerifier())->verify($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }
        Log::info($request);
        $paypalAgreement = new PayPalAgreement();

        if($request->event_type == 'BILLING.SUBSCRIPTION.EXPIRED' ||
            $request->event_type == 'BILLING.SUBSCRIPTION.SUSPENDED' ||
            $request->event_type == 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' ||
            $request->event_type == 'BILLING.SUBSCRIPTION.CANCELLED'){
            $subscription_id = $request->resource['id'];
            $paypalAgreement->inactivateActiveSubscriptionBySubscriptionId($subscription_id);
        }

        if($request->event_type == 'PAYMENT.SALE.COMPLETED'){
Bash Read subject controller and model
IN
cd /Users/apple/StudioProjects/ptm-backend-php && sed -n 1,135p app/Http/Controllers/Api/v2/SubjectController.php; sed -n 210,323p app/Http/Controllers/Api/v2/SubjectController.php; grep -v "^\s*\*" app/Models/Subject.php | head -60; grep -h "\$table" database/migrations/*subject_created_user* database/migrations/2024_01_30*

OUT
<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Utility;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\SchoolSubjects;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\UserSchools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubjectController
{
    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'bg_color' => 'required|not_in:transparent',
            'image' => 'max:2048',
            'class_id' => 'nullable',
        ])->setAttributeNames(
            ['bg_color' => 'Background Color'],
        );
        $classSection = ClassSection::where('class_id', $request->class_id)->first();

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response,400);
        }

        $isSectionClass = null;
        if($request->class_id){
            $isSectionClass = ClassSchool::where('id',$request->class_id)->whereHas('sections',function ($query) use ($user){
                $query->where('class_teacher_id',$user->id);
            })->first();
        }
        $userSchools = UserSchools::where('user_id',$user->id)->first();
        try {
            $subject = Subject::where(
                ['name' => $request->name])
                ->whereHas('schoolSubjects',function ($query) use ($userSchools){
                    $query->where('school_id',$userSchools->school_id);
                })
                ->count();
            if ($subject) {
                $response = array(
                    'error' => true,
                    'message' => trans('subject_already_exists')
                );
                return response()->json($response,400);
            } else {

                $image = $request->file('image');
                $file_path = null;
                if($image){
                    // made file name with combination of current time
                    $file_name = Utility::getUUID() . "." . explode("/", $image->getClientMimeType())[1];
                    //made file path to store in database
                    $file_path = 'subjects/' . $file_name;
                    if($image->getClientMimeType() != 'image/svg+xml' && $image->getClientMimeType() != 'image/svg'){
                        //resized image
                        resizeImage($image);
                    }
                    //stored image to storage/public/subjects folder
                    $destinationPath = storage_path('app/public/subjects');
                    $image->move($destinationPath, $file_name);
                }

                $subject = new Subject();
                $subject->medium_id = $request->medium_id ?? null;
                $subject->name = $request->name;
                $subject->bg_color = $request->bg_color;
                $subject->teacher_id = $user->id;
                $subject->image = $file_path;
                $subject->save();
                $subject = $subject->refresh();

                if($classSection){
                    $subjectTeacher = new SubjectTeacher();
                    $subjectTeacher->class_section_id = $classSection->id;
                    $subjectTeacher->subject_id = $subject->id;
                    $subjectTeacher->teacher_id = $user->id;
                    $subjectTeacher->save();
                    $subjectTeacher = $subjectTeacher->refresh();

                    $classSubject = new ClassSubject();
                    $classSubject->subject_id = $subject->id;
                    $classSubject->class_id = $request->class_id ?? null;
                    $classSubject->type = "Compulsory";
                    $classSubject->save();
                }


                $isSchoolSubject = SchoolSubjects::where('school_id',$userSchools->school_id)->where('subject_id',$subject->id)->first();

                if(!$isSchoolSubject){
                    $isSchoolSubject = new SchoolSubjects();
                    $isSchoolSubject->id = Utility::getUUID();
                    $isSchoolSubject->school_id = $userSchools->school_id;
                    $isSchoolSubject->subject_id = $subject->id;
                    $isSchoolSubject->save();
                }
                $response = array(
                    'error' => false,
                    'message' => trans('data_store_successfully'),
                    'data' => $subject
                );
                return response()->json($response);

            }
        } catch (\Throwable $e) {
            Log::error($e);
            $response = array(
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
            return response()->json($response,400);
        }
    }
    public function update(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        }
    }

    public function subjects(){

        $user = Auth::user();
        $userSchools = UserSchools::where('user_id',$user->id)->first();
        Log::info($userSchools);

        $subject = Subject::whereHas('schoolSubjects',function ($query) use ($userSchools){
                $query->where('school_id',$userSchools->school_id);
            })->orderBy('name','ASC')->get();
        $response = array(
            'error' => false,
            'message' => "Subject fetched successfully.",
            'data' => $subject
        );
        return response()->json($response);

    }
    public function assign(Request $request){
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'subject_id' => 'required',
            'class_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response = array(
                'error' => true,
                'message' => $validator->errors()->first()
            );
            return response()->json($response);
        }
        $classSection = ClassSection::where('class_id', $request->class_id)->first();
        if(!$classSection){
            $response = array(
                'error' => true,
                'message' => "Invalid class detected or class is not associate with a section",
                'data' => null
            );
            return response()->json($response);
        }
        $isSectionClass = ClassSchool::where('id',$request->class_id)->whereHas('sections',function ($query) use ($user){
            $query->where('class_teacher_id',$user->id);
        })->first();

        if(!$isSectionClass){
            $response = array(
                'error' => true,
                'message' => "This class is not associate with your school sections or you",
                'data' => [
                    'class_id' => $request->class_id
                ]
            );
            return response()->json($response);
        }
        $subject_id = $request->subject_id;
        $user_id = $user->id;

        $isSubject = Subject::whereIn('id',$subject_id)->first();
        if(!$isSubject){
            $response = array(
                'error' => true,
                'message' => "Invalid subject detected.",
                'data' => $isSubject
            );
            return response()->json($response);
        }

        foreach($subject_id as $subject){
            $isExists = ClassSubject::where('class_id',$classSection->class_id)->where('subject_id',$subject)->first();
            if(!$isExists){
                $classSubject = new ClassSubject();
                $classSubject->subject_id = $subject;
                $classSubject->class_id = $classSection->class_id;
                $classSubject->type = "Compulsory";
                $classSubject->save();
            }
            $classAssigned = SubjectTeacher::where('class_section_id',$classSection->id)
                ->where('subject_id',$subject)->first();
            if(!$classAssigned){
                $subjectTeacher = new SubjectTeacher();
                $subjectTeacher->class_section_id = $classSection->id;
                $subjectTeacher->subject_id = $subject;
                $subjectTeacher->teacher_id = $user_id;
                $subjectTeacher->save();
                $subjectTeacher = $subjectTeacher->refresh();
            }else{
                $hasAssigned = SubjectTeacher::where('subject_id',$subject)
                    ->where('class_section_id',$classSection->id)
                    ->where('teacher_id',$user_id)->first();
                if(!$hasAssigned){
                    $subjectTeacher = new SubjectTeacher();
                    $subjectTeacher->class_section_id = $classSection->id;
                    $subjectTeacher->subject_id = $subject;
                    $subjectTeacher->teacher_id = $user_id;
                    $subjectTeacher->save();
                    $subjectTeacher = $subjectTeacher->refresh();
                }
            }

        }

        $response = array(
            'error' => false,
            'message' => trans('data_store_successfully'),
            'data' => null
        );
        return response()->json($response);

    }

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
class Subject extends Model
{
    use SoftDeletes;
    use HasFactory;

    /**
    protected $fillable = ['name', 'code','teacher_id', 'bg_color', 'image', 'medium_id', 'type', 'created_at', 'updated_at', 'deleted_at'];

    /**
    public function onlineExams()
    {
        return $this->hasMany('App\Models\OnlineExam');
    }

    /**
    public function studentReportCards()
    {
        return $this->hasMany('App\Models\StudentReportCards');
    }

    public function medium() {
        return $this->belongsTo(Mediums::class)->withTrashed();
    }
    public function subject_teacher() {
        return $this->hasMany(SubjectTeacher::class,'subject_id');
    }

    public function scopeSubjectTeacher($query) {
        $user = Auth::user();
        if ($user->hasRole('Teacher')) {
            $subjects_ids = $user->teacher->subjects()->pluck('subject_id');
            return $query->whereIn('id', $subjects_ids);
        }
        return $query;
    }

//Getter Attributes
    public function getImageAttribute($value) {
        if(!$value){
            return null;
        }
        return url(Storage::url($value));
    }
    /**
    public function schoolSubjects()
    {
        return $this->hasMany('App\Models\SchoolSubjects','');
    }
    public function class_subject() {
Bash Check subject fields, quirk endpoints, school link sites
IN
cd /Users/apple/StudioProjects/ptm-backend-php && grep -n "hidden\|appends" app/Models/Subject.php; cat database/migrations/2024_01_30_032903_add_principal_id_to_subjects_table.php | grep table; sed -n 280,300p app/Http/Controllers/Api/v2/AttendanceController.php; grep -n "page\b\|->page\|'page'" app/Http/Controllers/Api/v2/TeacherController.php | head; sed -n 98,130p app/Http/Controllers/Api/v2/TeacherController.php; grep -rn "UserSchools::create\|UserSchools::insert\|new UserSchools" app | grep -v "Api/AuthController"

OUT
        Schema::table('subjects', function (Blueprint $table) {
            $table->integer('principal_id')->nullable();
        Schema::table('subjects', function (Blueprint $table) {
            $table->integer('principal_id');
                'error' => true,
                'message' => trans('error_occurred'),
                'data' => $e
            );
        }
        return response()->json($response);
    }

    public function viewAttendance(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        $user = $request->user();
        if ($user->type != 'teacher') {
            $response = array(
                'error' => true,
                'message' => trans('no_permission_message'),
                'data' => null
            );
    public function ClassReportCount(Request $request)
    {
        if ($request->class_id && !Access::isPrincipal($request->user())
            && !Access::teacherOwnsClass($request->user(), $request->class_id)) {
            return Access::forbidden();
        }
        if ($request->user()->type != 'teacher') {
            $response = array(
                'message' => trans('no_permission_message')
            );
            return response()->json($response, 401);
        }
        $class_id = $request->class_id;
        $quarter_id = $request->quarter_id;
        $session_year_id = $request->session_year_id;
        $status = $request->status;

        $classQuarters = ClassQuarter::where('class_id',$class_id)
            ->whereHas('quarter',function ($query) use($quarter_id){
                if($quarter_id){
                    $query->where('id',$quarter_id);
                }
            })
            ->where(function ($query) use ($session_year_id,$status){
                if($session_year_id){
                    $query->where('session_year_id',$session_year_id);
                }
                if($status && $status != "ALL"){
                    $query->where('status',$status);
                }
            })
            ->whereHas('quarter.studentReportCards.user')
            ->with('quarter')->paginate(10);
app/Http/Controllers/Api/v2/StudentController.php:453:                $userSchool = new UserSchools();
10:31 PM
Now the admin panel side, to decide how the approval workflow (A7) should plug in.



Bash Read web routes and views layout
IN
sed -n 1,110p routes/web.php; ls resources/views; ls resources/views/principal 2>/dev/null; cat app/Http/Middleware/CheckRole.php | sed -n 1,60p

OUT
<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\V2\PrincipalController;
use App\Models\Grade;
use App\Models\ExamTimetable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MediumController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ParentsController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FeesTypeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassSchoolController;
use App\Http\Controllers\LessonTopicController;
use App\Http\Controllers\SessionYearController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ClassTeacherController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\ExamTimetableController;
use App\Http\Controllers\OnlineExamController;
use App\Http\Controllers\OnlineExamQuestionController;
use App\Http\Controllers\StudentSessionController;
use App\Http\Controllers\SubjectTeacherController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Admin panel authentication. Only /login-web may sign users in: it checks the
// user's role. Laravel's default POST /login and /register are intentionally not registered.
Route::get('login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::namespace('App\Http\Controllers')->group(function () {
    Route::resetPassword();
});
Route::get('/', [HomeController::class, 'login']);
Route::post('/login-web', [\App\Http\Controllers\Auth\LoginController::class, 'loginWeb'])->name('loginWeb')->middleware('throttle:10,1');

Route::group(['middleware' => ['Role', 'auth']], function () {
    Route::get('/agora-chat', 'App\Http\Controllers\MeetingController@index');
    Route::get('/chat-teacher/{to_user?}', 'App\Http\Controllers\MeetingController@teacherChat')->name("t_chat");
    Route::get('/chat-parent/{to_user?}', 'App\Http\Controllers\MeetingController@parentChat')->name("p_chat");
    Route::get('/calendar/{id?}', 'App\Http\Controllers\MeetingController@calendarIndex')->name("calendar");
    Route::post('/agora/token', 'App\Http\Controllers\MeetingController@token');
    Route::post('/agora/call-user', 'App\Http\Controllers\MeetingController@callUser');
    Route::any('get/meetings/{status?}', [\App\Http\Controllers\MeetingController::class, 'getMeetings'])->name('meetings');
    Route::post('add/meeting', [\App\Http\Controllers\MeetingController::class, 'createMeeting'])->name('create_meeting');
    Route::get('teacher/{id}/classes', [\App\Http\Controllers\StudentController::class, 'teacherClasses'])->name('teacher_classes');
    Route::get('class/{id}/parents', [\App\Http\Controllers\StudentController::class, 'classParents'])->name('class_parents');
    Route::get('class/{id}/students', [\App\Http\Controllers\StudentController::class, 'classStudents'])->name('class_students');
    Route::get('meeting-list/view', [\App\Http\Controllers\MeetingController::class, 'meetingListView'])->name('meeting_list_view');
    Route::get('meeting-list', [\App\Http\Controllers\MeetingController::class, 'meetingList'])->name('meeting_list');
    Route::any('update/meetings', [\App\Http\Controllers\MeetingController::class, 'updateMeeting'])->name('update_meeting');
    Route::get('meetingDetails/{id}', [\App\Http\Controllers\MeetingController::class, 'meetingDetails'])->name('meeting_details');


    Route::group(['middleware' => 'language'], function () {
        Route::get('/', [HomeController::class, 'index']);
        Route::get('home', [HomeController::class, 'index'])->name('home');
        Route::get('/logout', [HomeController::class, 'logout'])->name('logout');
        Route::post('/logout', [HomeController::class, 'logout']);
        Route::get('subject-by-class-section', [HomeController::class, 'getSubjectByClassSection'])->name('class-section.by.subject');
        Route::get('teacher-by-class-subject', [HomeController::class, 'getTeacherByClassSubject'])->name('teacher.by.class.subject');

        Route::get('home/reset_password', [HomeController::class, 'resetPasswordView']);


        Route::resource('roles', RoleController::class);
        Route::resource('users', UserController::class);

        Route::get('settings', [SettingController::class, 'index']);
        Route::post('settings', [SettingController::class, 'update']);

        Route::get('fcm-settings', [SettingController::class, 'fcm_index']);

        Route::resource('medium', MediumController::class)->except(['create', 'edit']);
        Route::get('medium_list', [MediumController::class, 'show']);

        Route::resource('teachers', TeacherController::class)->except(['create']);
        Route::get('teacher_list/{school_id?}', [TeacherController::class, 'show'])->name('teacher_list');

        Route::resource('section', SectionController::class)->except(['create', 'edit']);
        Route::get('section_list', [SectionController::class, 'show']);

        Route::get('class/subject', [ClassSchoolController::class, 'subject'])->name('class.subject');
after-update-note-modal.blade.php
agora-chat.blade.php
announcement
assignment
attendance
auth
calendar.blade.php
category
chat-demo.blade.php
chat.blade.php
chat2.blade.php
class
createMeeting.blade.php
dashboard.blade.php
emails
errors
eventDetails.blade.php
exams
excel
fees
holiday
home.blade.php
index.html
layouts
lessons
mail.blade.php
medium
meeting
online_exam
parents
payment-failed.blade.php
payment-success.blade.php
principal
promote_student
push-notification.blade.php
roles
schools
section
session_years
settings
sliders
students
subject
system-update
teacher
timetable
users
vendor
vendor_overrided_email.blade.php
welcome.blade.php
chat.blade.php
index.blade.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin panel gate: only Super Admin and Principal users may use the web panel.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasAnyRole(['Super Admin', 'Principal'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['message' => trans('no_permission_message')]);
        }

        return $next($request);
    }
}
Bash Read sample admin view and sidebar
IN
sed -n 1,80p resources/views/principal/index.blade.php; ls resources/views/layouts; grep -n "announcement\|push-notifications" resources/views/layouts/sidebar.blade.php | head

OUT
@extends('layouts.master')

@section('title')
{{ __('principal') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage') . ' ' . __('principal') }}
        </h3>
    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        {{ __('new_principal') }}
                    </h4>
                    <form class="create-form pt-3" id="formdata" action="{{ route('principal.store') }}"
                          enctype="multipart/form-data" method="POST" novalidate="novalidate">
                        @csrf
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('first_name') }} <span class="text-danger">*</span></label>
                                {!! Form::text('first_name', null, ['required', 'placeholder' => __('first_name'), 'class' => 'form-control']) !!}

                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('last_name') }} <span class="text-danger">*</span></label>
                                {!! Form::text('last_name', null, ['required', 'placeholder' => __('last_name'), 'class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('password') }} <span class="text-danger">*</span></label>
                                {!! Form::text('password', null, ['required', 'placeholder' => __('password'), 'class' => 'form-control']) !!}

                            </div>

                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('gender') }} <span class="text-danger">*</span></label><br>
                                <div class="d-flex">
                                    <div class="form-check form-check-inline">
                                        <label class="form-check-label">
                                            {!! Form::radio('gender', 'male') !!}
                                            {{ __('male') }}
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <label class="form-check-label">
                                            {!! Form::radio('gender', 'female') !!}
                                            {{ __('female') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('email') }} <span class="text-danger">*</span></label>
                                {!! Form::text('email', null, ['required', 'placeholder' => __('email'), 'class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('mobile') }} <span class="text-danger">*</span></label>
                                {!! Form::number('mobile', null, ['required', 'placeholder' => __('mobile'), 'class' => 'form-control']) !!}

                            </div>
                            <div class="form-group col-sm-12 col-md-6">

                                <label>{{ __('image') }}</label>
                                <input type="file" name="image" class="file-upload-default" accept="image/png,image/jpeg,image/jpg" />
                                <div class="input-group col-xs-12">
                                    <input type="text" class="form-control file-upload-info" disabled=""
                                           placeholder="{{ __('image') }}" />
                                    <span class="input-group-append">
app.blade.php
footer.blade.php
footer_js.blade.php
header.blade.php
include.blade.php
master.blade.php
sidebar.blade.php
66:            <a class="nav-link" href="{{ route('push-notifications') }}"> <span
159:        @can('announcement-create')
161:                <a class="nav-link" href="{{ route('announcement.index') }}">
Bash Sidebar entries and notification sender
IN
sed -n 55,80p resources/views/layouts/sidebar.blade.php; grep -n "push-notifications\|schools-view\|get-principal" routes/web.php; cat app/Services/NotificationSender.php | sed -n 1,80p

OUT
        {{-- principal --}}
        @canany(['create-principal','principal-list'])
        <li class="nav-item">
            <a href="{{ route('principal.index') }}" class="nav-link"> <span
                        class="menu-title">Principals</span> <i class="fa fa-user-circle menu-icon"></i> </a>
        </li>
        @endcan

        @hasrole(['Super Admin','Principal'])
        <li class="nav-item">
            <a class="nav-link" href="{{ route('push-notifications') }}"> <span
                        class="menu-title">Push Notifications</span> <i class="fa fa-bell menu-icon"></i> </a>
        </li>
        @endrole
        @hasrole(['Principal'])

        <li class="nav-item">
            <a class="nav-link" href="{{ route('class.index') }}"> <span
                        class="menu-title">Manage Class</span> <i class="fa fa-users menu-icon"></i> </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('subject.index') }}"> <span
                        class="menu-title">Manage Subject</span> <i class="fa fa-book menu-icon"></i> </a>
        </li>
321:        Route::get('get-principal',[\App\Http\Controllers\V2\PrincipalController::class, 'index'])->name('principal.index');
327:        Route::get('schools-view', [\App\Http\Controllers\SchoolsController::class, 'schoolsView'])->name('school.index');
340:        Route::get('push-notifications', [\App\Http\Controllers\V2\PushNotificationController::class, 'pushNotificationView'])->name('push-notifications');
<?php

namespace App\Services;

use App\Events\WebNotificationEvent;
use App\Helpers\Utility;
use App\Models\NotificationLogs;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationSender
{

    function __construct()
    {
    }

    public function sendNotification($title, $message,$user_ids, $type = null, $data = [],$web = false)
    {
        $environment = config('environment.APP_ENV');

        try {
            if (config('environment.BROADCAST_DRIVER') == "pusher") {
                foreach ($user_ids as $topic) {
                    event(new WebNotificationEvent($title, $message, $topic, $type, $data));
                }
            } else {
                Log::info("user ids for push notifications");
                Log::info($user_ids);
                $tokens = User::whereIn('id',$user_ids)
                    ->where('enable_notification',1)->pluck('fcm_id')->toArray() ?? [];
                foreach ($tokens as $topic) {
                    PushNotificationSender::sendMobileMessage($title, $message, $topic, $type, $data);
                    if($web){
                        PushNotificationSender::sendWebMessage($title, $message, $topic, $type, $data);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
    }

    public function oneTimeNotificationSend($title,$message,$topic, $type, $data )
    {
        return PushNotificationSender::sendMobileMessage($title,$message,$topic, $type, $data );
    }

    public function saveLogs($user_id,$from_user_id,$message,$type,$title = null){

        $logs = new NotificationLogs();
        $logs->id = Utility::getUUID();
        $logs->user_id = $user_id;
        $logs->from_user_id = $from_user_id;
        $logs->message = $message;
        $logs->data = null;
        $logs->title = $title;
        $logs->type = $type;
        $logs->save();
        return $logs->refresh();
    }
    public function insertLogs($data){
        return NotificationLogs::insert($data);
    }


}
Bash Read push payload builder and admin routes
IN
sed -n 1,80p app/Services/PushNotificationSender.php; sed -n 310,350p routes/web.php

OUT
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PushNotificationSender
{
    private static string $url = "https://fcm.googleapis.com/fcm/send";
    private static $oneSignalUrl = "https://onesignal.com/api/v1/notifications";

    public static function sendWebMessage($title, $message, $topic, $type, $data = null)
    {
        Log::info('sending firebase notification', ['topic' => $topic, 'title' => $title, 'message' => $message]);
        $key = config('environment.PUSH_KEY');
        $fields = array(
            'to' => $topic,
            'priority' => "high",
            'data' => [
                'type' => $type,
                'click_action' => config('environment.APP_DASHBOARD'),
                'data' => $data
            ],
            'notification' => array("title" => $title, "body" => $message,"click_action"=>config('environment.APP_DASHBOARD'))
        );

        $headers = array(
            self::$url,
            'Content-Type: application/json',
            'Authorization: key=' . $key
        );


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        if ($result === FALSE) {
            return false;
        }

        curl_close($ch);
        return $result;

    }
    public static function sendMobileMessage($title, $message, $topic, $type, $data = null)
    {
        Log::info('sending firebase notification', ['topic' => $topic, 'title' => $title, 'message' => $message]);
        $key = config('environment.PUSH_KEY');

        $fields = array(
            'to' => "/topics/" .$topic,
            'priority' => "high",
            'data' => ['type' => $type,
                'click_action' => "FLUTTER_NOTIFICATION_CLICK",
                'data' => $data
            ],
            'notification' => array("title" => $title, "body" => $message)
        );

        $headers = array(
            self::$url,
            'Content-Type: application/json',
            'Authorization: key=' . $key
        );


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        // End Online Exam Routes

        Route::get('app-settings', [SettingController::class, 'app_index']);
        Route::post('app-settings', [SettingController::class, 'app_update']);
        // Uploading a zip that overwrites application code is disabled unless explicitly enabled.
        Route::group(['middleware' => 'systemUpdate'], function () {
            Route::get('system-update', [SystemUpdateController::class, 'index'])->name('system-update.index');
            Route::post('system-update', [SystemUpdateController::class, 'update'])->name('system-update.update');
        });

        Route::get('update-warning-modal',[HomeController::class, 'updateWarningModal'])->name('update-warning-modal');
        Route::get('get-principal',[\App\Http\Controllers\V2\PrincipalController::class, 'index'])->name('principal.index');
        Route::post('principal/store', [PrincipalController::class, 'store'])->name('principal.store');
        Route::delete('principal-unlink/{user_id}/school/{school_id?}', [PrincipalController::class, 'unlink'])->name('principal.unlink');
        Route::delete('principal/{user_id}', [PrincipalController::class, 'remove'])->name('principal.remove');
        Route::get('principals', [PrincipalController::class, 'get'])->name('principals');
        Route::get('class-sections/school/{school_id}/{type?}', [ClassSchoolController::class, 'classSectionsBySchool'])->name('class-section-school');
        Route::get('schools-view', [\App\Http\Controllers\SchoolsController::class, 'schoolsView'])->name('school.index');
        Route::post('schools', [\App\Http\Controllers\SchoolsController::class, 'add'])->name('create-school');
        Route::get('schools-list', [\App\Http\Controllers\SchoolsController::class, 'schoolsList'])->name('schools');
        Route::put('update-schools/{id?}', [\App\Http\Controllers\SchoolsController::class, 'update'])->name('update-school');

        Route::post('save-token', [\App\Http\Controllers\FcmController::class, 'saveToken'])->name('save-token');
        Route::post('chat', [\App\Http\Controllers\FcmController::class, 'createChat'])->name('create-chat');
        Route::get('excel/teachers', [\App\Http\Controllers\V2\ExcelController::class, 'exportTeachers'])->name('export_teachers');
        Route::get('excel/parents', [\App\Http\Controllers\V2\ExcelController::class, 'exportParents'])->name('export_parents');
        Route::get('excel/students', [\App\Http\Controllers\V2\ExcelController::class, 'exportStudents'])->name('export_students');
        Route::get('excel/announcements', [\App\Http\Controllers\V2\ExcelController::class, 'exportAnnouncement'])->name('export_announcements');
        Route::get('excel/schools', [\App\Http\Controllers\V2\ExcelController::class, 'exportSchools'])->name('export_schools');
        Route::get('excel/principals', [\App\Http\Controllers\V2\ExcelController::class, 'exportPrincipals'])->name('export_principals');
        Route::get('push-notifications', [\App\Http\Controllers\V2\PushNotificationController::class, 'pushNotificationView'])->name('push-notifications');
        Route::post('send-notification', [\App\Http\Controllers\V2\PushNotificationController::class, 'sendNotification'])->name('send-notification');
        Route::get('class-users/{class_id}/{type}/{school_id}', [\App\Http\Controllers\V2\PushNotificationController::class, 'classUsers'])->name('class-users');
        Route::post('send-meeting-notification', [\App\Http\Controllers\V2\PushNotificationController::class, 'sendMeetingNotification'])->name('send-meeting-notification');

    });
});

// webhooks
Route::post('webhook/razorpay', [WebhookController::class, 'razorpay']);
Route::post('webhook/stripe', [WebhookController::class, 'stripe']);
Bash Check schema for users, schools, links
IN
grep -rn "'type'" database/migrations/2014_10_12_000000_create_users_table.php database/migrations/2022_04_01_105826_all_tables.php | head -5; grep -rn "type" database/migrations/2023_08_15_065510_make_user_fields_nullable.php; grep -h "\$table" database/migrations/2023_07_25_122427_create_schools_table.php database/migrations/2023_08_08_094155_create_user_schools_table.php database/migrations/2023_10_23_061027_create_notification_logs_table.php; grep -n "to_user\|school_id" database/migrations/2023_08_22_101853_add_extras_to_announcements_table.php

OUT
database/migrations/2014_10_12_000000_create_users_table.php:20:            $table->string('type', 512);
database/migrations/2022_04_01_105826_all_tables.php:110:            $table->string('type', 64)->nullable();
database/migrations/2022_04_01_105826_all_tables.php:118:            $table->string('type', 32)->comment('Compulsory / Elective');
database/migrations/2022_04_01_105826_all_tables.php:330:            $table->tinyInteger('type')->comment('0=Absent, 1=Present');
17:            $table->bigInteger('school_id')->unsigned()->nullable();
18:            $table->string('to_user')->nullable();
19:            $table->foreign('school_id')->references('id')->on('schools');
32:            $table->dropColumn('school_id');
33:            $table->dropColumn('to_user');
Bash Read schools and user_schools migrations
IN
sed -n 10,40p database/migrations/2023_07_25_122427_create_schools_table.php; sed -n 10,30p database/migrations/2023_08_08_094155_create_user_schools_table.php; sed -n 1,60p database/migrations/2026_10_07_000000_grant_admin_panel_roles.php

OUT
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('country_id');
            $table->string('school_name');
            $table->string('address');
            $table->string('locality');
            $table->string('post_town');
            $table->string('image');
            $table->string('post_code');
            $table->string('email');
            $table->string('phone');
            $table->string('website');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('schools');
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_schools', function (Blueprint $table) {
            $table->uuid('id');
            $table->bigInteger('user_id')->unsigned()->index();
            $table->bigInteger('school_id')->unsigned()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('school_id')->references('id')->on('schools');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin panel access is now decided by Spatie roles (Super Admin, Principal)
 * instead of the user-editable users.type column.
 *
 * - Super Admin gets every permission except the teacher-scoped ones (those pages
 *   assume the logged-in user is a class teacher and crash otherwise).
 * - Users linked to a school as principal get the Principal role if missing.
 */
return new class extends Migration
{
    public const TEACHER_ONLY = [
        'class-teacher', 'manage-online-exam', 'exam-result', 'exam-upload-marks',
        'assignment-list', 'assignment-create', 'assignment-edit', 'assignment-delete',
        'attendance-create', 'attendance-edit', 'attendance-delete',
        'lesson-list', 'lesson-create', 'lesson-edit', 'lesson-delete',
        'topic-list', 'topic-create', 'topic-edit', 'topic-delete',
    ];

    public function up()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(
                Permission::where('guard_name', 'web')->whereNotIn('name', self::TEACHER_ONLY)->get()
            );
        }

        $principal = Role::where('name', 'Principal')->where('guard_name', 'web')->first();
        if ($principal) {
            $principalIds = DB::table('schools')->whereNotNull('principal_id')->pluck('principal_id')->unique();
            foreach ($principalIds as $userId) {
                $user = \App\Models\User::find($userId);
                if ($user && !$user->hasRole('Principal')) {
                    $user->assignRole($principal);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down()
    {
        // Permission grants are not reverted automatically.
    }
};
10:32 PM
I've finished reading the relevant code. Next I'm writing the role-context schema, the service and middleware, and then the endpoint changes.



Write 2026_10_19_000001_create_role_context_tables.php
150 lines
Write 2026_10_19_000002_backfill_role_contexts.php
69 lines
Write 2026_10_19_000003_add_call_usage_ledger.php
45 lines
Bash Check call room id type and country model
IN
grep -h "\$table" database/migrations/2024_01_04_020506_create_user_call_room_table.php; grep -v "^\s*\*" app/Models/Country.php | head -30; grep -n "hidden\|appends" app/Models/Schools.php app/Models/Country.php

OUT
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
class Country extends Model
{
    /**
    protected $fillable = ['code', 'name', 'phonecode'];
}
10:33 PM
Now the models.



Bash Create new Eloquent models
IN
cat > app/Models/District.php <<'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A school district: an organization that groups schools (schools.district_id).
 *
 * @property integer $id
 * @property string $name
 * @property integer $country_id
 * @property string $status
 */
class District extends Model
{
    protected $fillable = ['name', 'country_id', 'status'];

    public function schools()
    {
        return $this->hasMany(Schools::class, 'district_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * The district in the School object shape the app parses (GET /get/schools?organization_type=district).
     */
    public function toSchoolShape(): array
    {
        return [
            'id' => $this->id,
            'organization_type' => 'district',
            'school_name' => $this->name,
            'country_id' => $this->country_id,
            'district_id' => null,
            'principal_id' => null,
            'user_id' => null,
            'address' => null,
            'locality' => null,
            'post_town' => null,
            'post_code' => null,
            'image' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'country' => $this->country,
        ];
    }
}
EOF
cat > app/Models/UserRole.php <<'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One role context: a user holding a role in an organization (a school or a district).
 * Its id is the context id the app sends back in X-PTM-Context.
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $role
 * @property string $organization_type
 * @property integer $organization_id
 * @property string $status
 * @property string $source
 */
class UserRole extends Model
{
    public const ROLES = ['parent', 'teacher', 'staff', 'school_admin', 'district_admin'];
    public const INSTITUTION_ROLES = ['teacher', 'staff', 'school_admin', 'district_admin'];
    public const REQUESTABLE_ROLES = ['staff', 'school_admin', 'district_admin'];
    public const STATUSES = ['pending', 'active', 'rejected', 'suspended'];

    protected $fillable = [
        'user_id', 'role', 'organization_type', 'organization_id', 'status', 'source',
        'requested_at', 'decided_at', 'decided_by', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function permissionOverrides()
    {
        return $this->hasMany(UserRolePermission::class, 'user_role_id');
    }

    /** Contexts whose relationship has not ended. */
    public function scopeCurrent($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
        });
    }

    public function hasEnded(): bool
    {
        return $this->effective_to !== null && $this->effective_to->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && !$this->hasEnded();
    }

    public function isSchool(): bool
    {
        return $this->organization_type === 'school';
    }

    public function isDistrict(): bool
    {
        return $this->organization_type === 'district';
    }

    public function organization()
    {
        return $this->isDistrict()
            ? District::find($this->organization_id)
            : Schools::find($this->organization_id);
    }
}
EOF
cat > app/Models/UserRolePermission.php <<'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-context change to the role's default permissions (granted = add, false = remove).
 */
class UserRolePermission extends Model
{
    protected $fillable = ['user_role_id', 'permission_key', 'granted'];

    protected $casts = ['granted' => 'boolean'];
}
EOF
cat > app/Models/AuditLog.php <<'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail: role requests, approvals, context switches, permission changes.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_user_id', 'action', 'subject_type', 'subject_id', 'context_id', 'payload'];

    protected $casts = ['payload' => 'array'];

    public static function record(?int $actorId, string $action, $subject = null, ?int $contextId = null, array $payload = []): self
    {
        return self::create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'context_id' => $contextId,
            'payload' => $payload ?: null,
        ]);
    }
}
EOF
cat > app/Models/CallUsageLedger.php <<'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only record of call seconds charged to a user's subscription.
 */
class CallUsageLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'call_usage_ledger';

    protected $fillable = ['user_id', 'user_call_room_id', 'channel_name', 'seconds', 'client_reported_seconds', 'source'];
}
EOF

OUT
(Bash completed with no output)
Models are set. Now I'll fix the ledger migration to use a charged-seconds counter instead of a timestamp, preventing double-charging between the webhook and app report, then move on to the core service for contexts, permissions, scoping, and approvals.
Bash Switch ledger to charged_seconds
IN
python3 - <<'EOF'
p='database/migrations/2026_10_19_000003_add_call_usage_ledger.php'
s=open(p).read()
s=s.replace(""" * user_call_room now records when the server saw the join and the leave, and when that
 * call session was charged. A session is charged once, by whichever arrives first: the
 * Agora webhook (leave event) or the app's POST /update-remaining-time.
 * call_usage_ledger is insert-only: one row per charge.""",""" * user_call_room now records when the server saw the join and the leave, and how many
 * seconds of the current call session were already charged. Both the Agora webhook (leave
 * event) and the app's POST /update-remaining-time charge only the part not yet charged,
 * so a session is never billed twice. call_usage_ledger is insert-only: one row per charge.""")
s=s.replace("$table->dateTime('charged_at')->nullable();","$table->unsignedInteger('charged_seconds')->default(0);")
s=s.replace("'charged_at']","'charged_seconds']")
open(p,'w').write(s)
EOF
grep -n "charged" database/migrations/2026_10_19_000003_add_call_usage_ledger.php

OUT
11: * seconds of the current call session were already charged. Both the Agora webhook (leave
12: * event) and the app's POST /update-remaining-time charge only the part not yet charged,
22:            $table->unsignedInteger('charged_seconds')->default(0);
41:            $table->dropColumn(['joined_at', 'left_at', 'charged_seconds']);
Write RoleContextService.php
589 lines
Bash Fix id comparison type
IN
python3 - <<'EOF'
p='app/Services/RoleContextService.php'
s=open(p).read()
s=s.replace("if ($context->user_id === $actor->id || $context->isDistrict()","if ((int) $context->user_id === (int) $actor->id || $context->isDistrict()")
open(p,'w').write(s)
EOF
grep -n "(int) \$context->user_id" app/Services/RoleContextService.php

OUT
(Bash completed with no output)
Bash Verify the replacement applied
IN
grep -n "user_id === \|user_id ===" app/Services/RoleContextService.php; grep -n "isDistrict() || \$context->role === 'district_admin'" app/Services/RoleContextService.php

OUT
443:        if ((int) $context->user_id === (int) $actor->id || $context->isDistrict() || $context->role === 'district_admin') {
10:35 PM
Now the request-level pieces: helpers on Access, plus the middleware that reads X-PTM-Context and checks roles and permissions.



Bash Add Access helpers and context middleware
IN
python3 - <<'EOF'
p='app/Support/Access.php'
s=open(p).read()
s=s.replace("""use App\\Models\\ClassSection;
use App\\Models\\Students;
use App\\Models\\User;
""","""use App\\Models\\ClassSection;
use App\\Models\\Students;
use App\\Models\\Teacher;
use App\\Models\\User;
use App\\Models\\UserRole;
use App\\Services\\RoleContextService;
""")
s=s.replace("""    public static function forbidden()""","""    /**
     * The role context of the current API request (set by ResolvePtmContext), or null
     * for users that have none yet (legacy mode).
     */
    public static function context(): ?UserRole
    {
        $request = request();
        return $request ? $request->attributes->get(RoleContextService::REQUEST_ATTRIBUTE) : null;
    }

    /**
     * The role the user is acting as: the active context's role, else the legacy users.type
     * ('principal' for principals).
     */
    public static function role(User $user): string
    {
        $context = self::context();
        if ($context && (int) $context->user_id === (int) $user->id) {
            return $context->role;
        }
        return self::isPrincipal($user) ? 'principal' : strtolower((string) $user->type);
    }

    /** Parent path or institution path (teacher, staff, admins, principal). */
    public static function actsAsParent(User $user): bool
    {
        return self::role($user) === 'parent';
    }

    /**
     * Whether the current request may use a permission. With a context this is the
     * context's effective permissions; without one only legacy parent/teacher/principal
     * accounts pass, and the route's role middleware still applies.
     */
    public static function can(User $user, string $permission): bool
    {
        $context = self::context();
        if ($context && (int) $context->user_id === (int) $user->id) {
            return app(RoleContextService::class)->contextCan($context, $permission);
        }
        return in_array(self::role($user), RoleContextService::LEGACY_TYPES, true);
    }

    /** School ids the current request is limited to (null = legacy mode: the user's linked schools). */
    public static function schoolIds(User $user): ?array
    {
        $context = self::context();
        if ($context && (int) $context->user_id === (int) $user->id) {
            return app(RoleContextService::class)->schoolIds($context);
        }
        return null;
    }

    /**
     * Whether a user can sit in the institution side of a meeting: a teacher, or anyone
     * with an active teacher/staff/admin context.
     */
    public static function isInstitutionUser($userId): bool
    {
        return Teacher::where('user_id', $userId)->exists()
            || UserRole::where('user_id', $userId)
                ->whereIn('role', UserRole::INSTITUTION_ROLES)
                ->where('status', 'active')->current()->exists();
    }

    public static function forbidden()""")
open(p,'w').write(s)
EOF
mkdir -p app/Http/Middleware && cat > app/Http/Middleware/ResolvePtmContext.php <<'EOF'
<?php

namespace App\Http\Middleware;

use App\Services\RoleContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the role context of every authenticated API request (X-PTM-Context, Part A5.6).
 *
 * - Header present: it must be one of the caller's own, active contexts, otherwise 403.
 *   There is no silent fallback to another role.
 * - Header absent (older app builds): the user's default active context, if any.
 *
 * The resolved context is stored on the request (Access::context()).
 * A few account-level endpoints ignore a bad header instead of rejecting it, so a user
 * whose context was suspended can still load /auth/details, switch, or sign out.
 */
class ResolvePtmContext
{
    private const LENIENT_PATHS = [
        'api/auth/details', 'api/auth/context/switch', 'api/auth/signout', 'api/logout',
        'api/auth/login', 'api/auth/register', 'api/auth/fcm',
    ];

    public function __construct(private RoleContextService $contexts)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->bearerToken() ? Auth::guard('sanctum')->user() : null;
        if (!$user) {
            return $next($request);
        }

        [$context, $error] = $this->contexts->resolve($request, $user);
        if ($error) {
            if (!in_array(trim($request->path(), '/'), self::LENIENT_PATHS, true)) {
                return response()->json(['error' => true, 'message' => $error, 'code' => 403], 403);
            }
            $context = $this->contexts->defaultContext($user);
        }

        $request->attributes->set(RoleContextService::REQUEST_ATTRIBUTE, $context);
        return $next($request);
    }
}
EOF
cat > app/Http/Middleware/RequireContextRole.php <<'EOF'
<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;

/**
 * Restricts a route to the roles the request is acting as.
 *
 * Usage: ->middleware('ptm.role:teacher,principal')
 * With a role context the context's role must be listed ('principal' also accepts the
 * school_admin context of a legacy principal). Without one, users.type / the Spatie role
 * decides, exactly as the old userType middleware did.
 */
class RequireContextRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.', 'code' => 401], 401);
        }

        $allowed = array_map('strtolower', $roles);
        $context = Access::context();
        if ($context) {
            $role = $context->role;
            if (in_array($role, $allowed, true)
                || ($role === 'school_admin' && in_array('principal', $allowed, true) && Access::isPrincipal($user))) {
                return $next($request);
            }
            return Access::forbidden();
        }

        if (in_array(strtolower((string) $user->type), $allowed, true)) {
            return $next($request);
        }
        foreach ($allowed as $role) {
            if (in_array($role, ['parent', 'teacher', 'principal', 'student'], true) && $user->hasRole(ucfirst($role))) {
                return $next($request);
            }
        }
        return Access::forbidden();
    }
}
EOF
cat > app/Http/Middleware/RequireContextPermission.php <<'EOF'
<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;

/**
 * Requires one of the given context permissions, e.g. ->middleware('ptm.can:schedule.use').
 * Hidden UI is not security: every endpoint re-checks (Doc 12 §6).
 */
class RequireContextPermission
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.', 'code' => 401], 401);
        }
        foreach ($permissions as $permission) {
            if (Access::can($user, $permission)) {
                return $next($request);
            }
        }
        return Access::forbidden();
    }
}
EOF
cat > app/Http/Middleware/NormalizeAppRequest.php <<'EOF'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Smooths over request quirks the current mobile build sends (API_CONTRACT §15, Part B3).
 *
 * - Query parameters sent as the strings "null" / "undefined" are treated as absent
 *   (subject_id=null on view-attendance, page=null on class reports count, ...).
 * - "fcm id" (with a space, JSON bodies) is copied to fcm_id.
 * - POST /add/school: webSite -> website, email "null" -> empty.
 */
class NormalizeAppRequest
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->query->all() as $key => $value) {
            if (is_string($value) && in_array(strtolower($value), ['null', 'undefined'], true)) {
                $request->query->remove($key);
            }
        }

        $input = $request->all();
        $changes = [];
        if (array_key_exists('fcm id', $input) && !$request->filled('fcm_id')) {
            $changes['fcm_id'] = $input['fcm id'];
        }
        if ($request->is('api/add/school')) {
            if (array_key_exists('webSite', $input) && !$request->filled('website')) {
                $changes['website'] = $input['webSite'];
            }
            if (is_string($input['email'] ?? null) && strtolower($input['email']) === 'null') {
                $changes['email'] = null;
            }
        }
        if ($changes) {
            $request->merge($changes);
        }

        return $next($request);
    }
}
EOF

OUT
(Bash completed with no output)
Edit RequireContextRole.php
Added 3 lines
10:36 PM
Now registering the middleware in the HTTP kernel.



Bash Register middleware in kernel
IN
python3 - <<'EOF'
p='app/Http/Kernel.php'
s=open(p).read()
s=s.replace("""            'throttle:api',
            \\Illuminate\\Routing\\Middleware\\SubstituteBindings::class,""","""            'throttle:api',
            \\App\\Http\\Middleware\\NormalizeAppRequest::class,
            \\App\\Http\\Middleware\\ResolvePtmContext::class,
            \\Illuminate\\Routing\\Middleware\\SubstituteBindings::class,""")
s=s.replace("""        'userType' => \\App\\Http\\Middleware\\EnsureUserType::class,""","""        'userType' => \\App\\Http\\Middleware\\EnsureUserType::class,
        'ptm.role' => \\App\\Http\\Middleware\\RequireContextRole::class,
        'ptm.can' => \\App\\Http\\Middleware\\RequireContextPermission::class,""")
open(p,'w').write(s)
EOF
grep -n "Ptm\|ptm\|Normalize" app/Http/Kernel.php

OUT
51:            \App\Http\Middleware\NormalizeAppRequest::class,
52:            \App\Http\Middleware\ResolvePtmContext::class,
83:        'ptm.role' => \App\Http\Middleware\RequireContextRole::class,
84:        'ptm.can' => \App\Http\Middleware\RequireContextPermission::class,
Bash Add relations to User and Schools models
IN
python3 - <<'EOF'
p='app/Models/User.php'
s=open(p).read()
s=s.replace("""        'remember_token',
    ];""","""        'remember_token',
        'last_context_id',
    ];""",1)
s=s.replace("""    public function userSchool() {
        return $this->hasMany(UserSchools::class, 'user_id');
    }
""","""    public function userSchool() {
        return $this->hasMany(UserSchools::class, 'user_id');
    }
    public function roleContexts() {
        return $this->hasMany(UserRole::class, 'user_id');
    }
""")
open(p,'w').write(s)
p='app/Models/Schools.php'
s=open(p).read()
s=s.replace("""protected $fillable = ['principal_id', 'user_id', 'country_id',""","""protected $fillable = ['principal_id', 'user_id', 'country_id', 'district_id', 'status',""")
s=s.replace("""    public function country()
    {
""","""    public function district()
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function country()
    {
""",1)
open(p,'w').write(s)
EOF
git diff --stat

OUT
 app/Http/Kernel.php    |  4 +++
 app/Models/Schools.php |  7 +++++-
 app/Models/User.php    |  4 +++
 app/Support/Access.php | 68 ++++++++++++++++++++++++++++++++++++++++++++++++++
 4 files changed, 82 insertions(+), 1 deletion(-)
10:36 PM
Now the auth endpoints. A shared LoginResponse builder first, so all five endpoints return the same shape.



Write LoginResponse.php
28 lines
Bash Update register, login and details responses
IN
python3 - <<'EOF'
p='app/Http/Controllers/Api/AuthController.php'
s=open(p).read()
def rep(old,new,count=1):
    global s
    assert s.count(old)>=1, old[:80]
    s=s.replace(old,new,count)

rep("""use App\\Models\\UserSubscription;
use Carbon\\Carbon;""","""use App\\Models\\UserSubscription;
use App\\Services\\RoleContextService;
use App\\Support\\LoginResponse;
use Carbon\\Carbon;""")

rep("""            // Only parent and teacher accounts can self-register. Admin and principal
            // accounts are created from the admin panel.
            $request->merge(['type' => strtolower((string) $request->input('type'))]);
            $validateUser = Validator::make($request->all(),
                [
                    'type' => 'required|in:parent,teacher',""","""            // Self-registration doorways: parent, teacher, staff, school admin, district admin.
            // Staff and admin accounts get no access until an organization approves their
            // role request (POST /auth/role-requests). Super Admin and Principal accounts
            // are still created from the admin panel only.
            $request->merge(['type' => strtolower((string) $request->input('type'))]);
            $validateUser = Validator::make($request->all(),
                [
                    'type' => 'required|in:parent,teacher,staff,school_admin,district_admin',""")

rep("""            $user->assignRole(ucfirst($request->type));
            if ($request->type == 'teacher') {""","""            if (in_array($request->type, ['parent', 'teacher'], true)) {
                $user->assignRole(ucfirst($request->type));
            }
            if ($request->type == 'teacher') {""")

rep("""            return response()->json([
                'status' => 200,
                'message' => 'User Created Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'user' => $user,
            ], 200);""","""            return LoginResponse::make($user, 'User Created Successfully', [
                'token_type' => 'Bearer',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
            ]);""")

# login
rep("""            $user = User::where('email', $request->email)->with('rate')->first();
            $hasSchool = UserSchools::where('user_id',$user->id)->first();
            $user->has_schools = (bool)$hasSchool;
""","""            $user = User::where('email', $request->email)->with('rate')->first();
""")
rep("""                return response()->json([
                    'status' => 200,
                    'message' => 'Your email is not verified.Please check your email inbox',
                    'user' => $user,
                    'has_school' => (bool)$hasSchool
                ], 200);""","""                return LoginResponse::make($user, 'Your email is not verified.Please check your email inbox');""")
rep("""            return response()->json([
                'status' => 200,
                'message' => 'User Logged In Successfully',
                'token_type' => 'Bearer',
                'user' => $user->refresh(),
                'has_school' => (bool)$hasSchool,
                'token' => $user->createToken("API TOKEN")->plainTextToken
            ], 200);""","""            return LoginResponse::make($user->refresh()->load('rate'), 'User Logged In Successfully', [
                'token_type' => 'Bearer',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
            ]);""")

# details
rep("""        $hasSchool = false;
        if($user){
            $hasSchool = UserSchools::where('user_id',$user->id)->first();
            $user->has_schools = (bool)$hasSchool;
        }

        return response()->json([
            'status' => 200,
            'message' => 'User fetched Successfully',
            'user' => $user,
            'has_school' => (bool)$hasSchool
        ], 200);""","""        return LoginResponse::make($user, 'User fetched Successfully');""")
open(p,'w').write(s)
EOF

OUT
(Bash completed with no output)
10:37 PM
Now POST /user/schools, which must also create the role context.



Bash Make user/schools create role contexts
IN
python3 - <<'EOF'
p='app/Http/Controllers/Api/AuthController.php'
s=open(p).read()
old="""        $userSchools = UserSchools::insert($userSchools);

        return response()->json(["""
new="""        $role = strtolower((string) $user->type);
        $status = ($role === 'teacher' && config('ptm.teacher_requires_approval')) ? 'pending' : 'active';
        $schoolIds = array_map('intval', $data['schools']);
        $validSchools = Schools::whereIn('id', $schoolIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count(array_unique($schoolIds)) !== count($validSchools)) {
            return response()->json([
                'status' => 400,
                'message' => "Some of the provided schools do not exist.",
            ], 400);
        }

        $userSchools = DB::transaction(function () use ($user, $role, $status, $schoolIds, $userSchools) {
            // Role context first, so a context sync running in between can't create it as active.
            foreach ($schoolIds as $schoolId) {
                app(RoleContextService::class)->linkSchool($user, $role, $schoolId, $status);
            }
            return UserSchools::insert($userSchools);
        });

        return response()->json(["""
assert old in s
s=s.replace(old,new)
old="""        if($user->type == 'teacher'){
            $teacherSchool = UserSchools::with('school')"""
new="""        // Parents and teachers join a school here. Staff and admins ask for access through
        // POST /auth/role-requests instead.
        if (!in_array(strtolower((string) $user->type), ['parent', 'teacher'], true)) {
            return response()->json([
                'status' => 400,
                'message' => "Use a role request to join an organization with this account type.",
            ], 400);
        }
        if($user->type == 'teacher'){
            $teacherSchool = UserSchools::with('school')"""
assert old in s
s=s.replace(old,new)
s=s.replace("""use App\\Models\\Plan;
""","""use App\\Models\\Plan;
use App\\Models\\Schools;
""")
open(p,'w').write(s)
EOF
mkdir -p config && cat > config/ptm.php <<'EOF'
<?php

return [
    // When true, a teacher who picks a school gets a pending context that a school admin
    // must approve. When false (the default, matching current behaviour) the teacher is
    // active straight away.
    'teacher_requires_approval' => (bool) env('PTM_TEACHER_REQUIRES_APPROVAL', false),
];
EOF

OUT
(Bash completed with no output)
10:37 PM
Next, the new POST /auth/role-requests and POST /auth/context/switch endpoints.



Write RoleContextController.php
97 lines
10:37 PM
Now GET /get/schools: the new selected_schools/other_schools shape and district mode.



Bash Rewrite getSchools with new shape
IN
python3 - <<'EOF'
p='app/Http/Controllers/Api/ApiController.php'
s=open(p).read()
old=s[s.index("    public function getSchools(Request $request)"):s.index("    public function logout(Request $request)")]
new='''    /**
     * School picker. data.selected_schools = organizations the user is already linked to or
     * has a role context in (any status); data.other_schools = the rest, paginated.
     * ?organization_type=district lists districts in the same School shape.
     */
    public function getSchools(Request $request)
    {
        $name = $request->get('name');
        $user = $request->user();
        try {
            if ($request->get('organization_type') === 'district') {
                $selectedIds = UserRole::where('user_id', $user->id)->current()
                    ->where('organization_type', 'district')->pluck('organization_id')->all();
                $scope = function ($query) use ($user) {
                    $query->where('status', 'active');
                    if ($user->country_id) {
                        $query->where('country_id', $user->country_id);
                    }
                };
                $selected = District::with('country')->whereIn('id', $selectedIds)->orderBy('name')->get()
                    ->map->toSchoolShape()->values();
                $others = District::with('country')->where($scope)
                    ->when($name, fn ($query) => $query->where('name', 'LIKE', "%{$name}%"))
                    ->whereNotIn('id', $selectedIds ?: [0])
                    ->orderBy('name', 'ASC')->paginate(10);
                $others->setCollection($others->getCollection()->map->toSchoolShape());
            } else {
                $selectedIds = UserSchools::where('user_id', $user->id)->pluck('school_id')
                    ->merge(UserRole::where('user_id', $user->id)->current()
                        ->where('organization_type', 'school')->pluck('organization_id'))
                    ->unique()->values()->all();
                $selected = Schools::with('country')->whereIn('id', $selectedIds)->orderBy('school_name')->get();
                $others = Schools::where(function ($query) use ($name, $user) {
                    if ($name) {
                        $query->where('school_name', 'LIKE', "%{$name}%");
                    }
                    if ($user->country_id) {
                        $query->where('country_id', $user->country_id);
                    }
                })->where('status', 'active')
                    ->whereNotIn('id', $selectedIds ?: [0])
                    ->orderBy('school_name', 'ASC')->with('country')->paginate(10);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Schools fetched successfully.',
                'data' => [
                    'selected_schools' => $selected,
                    'other_schools' => $others,
                ],
            ], 200);

        } catch (\\Throwable $th) {
            return response()->json([
                'status' => 400,
                'message' => $th->getMessage()
            ], 500);
        }


    }

'''
s=s.replace(old,new)
open(p,'w').write(s)
EOF
sed -n 1,55p app/Http/Controllers/Api/ApiController.php | grep "^use"

OUT
use App\Helpers\Utility;
use App\Models\Attendance;
use App\Models\CallDetails;
use App\Models\ClassQuarter;
use App\Models\ClassSchool;
use App\Models\ClassSection;
use App\Models\ClassSubject;
use App\Models\Country;
use App\Models\MainMeeting;
use App\Models\Mediums;
use App\Models\Meetings;
use App\Models\Parents;
use App\Models\PasswordReset;
use App\Models\Schools;
use App\Models\SchoolSubjects;
use App\Models\Section;
use App\Models\Settings;
use App\Models\StudentQuarter;
use App\Models\StudentReportCards;
use App\Models\Students;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserSchools;
use App\Models\UserSubscription;
use App\Services\NotificationSender;
use Carbon\Carbon;
use App\Support\Access;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Holiday;
use App\Models\SessionYear;
use App\Models\Slider;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
Bash Make meetings endpoints context-aware
IN
python3 - <<'EOF'
p='app/Http/Controllers/Api/ApiController.php'
s=open(p).read()
def rep(old,new,count=1):
    global s
    assert s.count(old)>=count, old[:100]
    s=s.replace(old,new,count)
rep("""use App\\Models\\Country;
""","""use App\\Models\\Country;
use App\\Models\\District;
""")
rep("""use App\\Models\\UserSchools;
use App\\Models\\UserSubscription;""","""use App\\Models\\UserRole;
use App\\Models\\UserSchools;
use App\\Models\\UserSubscription;""")

# getMeetingsV2
rep("""        $user = Auth::user();
        $user_id = $user->id;
        $user_type = $user->type;
        $meetings = MainMeeting::whereHas('meeting_details',function ($query) use ($user_id,$type,$date,$status,$user,$user_type){
            $query->select('id', 'title', 'status', 'user_id', 'meeting_created_user_type', 'teacher_id', 'parent_id', 'meeting_date',
                'meeting_time', 'meeting_end_time', 'description', 'student_id', 'meeting_hash');
            $query->where(function ($query) use ($user_id){
                $query->where('parent_id', $user_id);
                $query->orWhere('teacher_id', $user_id);
            });""","""        $user = Auth::user();
        $user_id = $user->id;
        // Parent path vs institution path (teacher, staff, admins all use the teacher slot).
        $user_type = Access::actsAsParent($user) ? 'parent' : 'teacher';
        $hasContext = (bool) Access::context();
        $meetings = MainMeeting::whereHas('meeting_details',function ($query) use ($user_id,$type,$date,$status,$user,$user_type,$hasContext){
            $query->select('id', 'title', 'status', 'user_id', 'meeting_created_user_type', 'teacher_id', 'parent_id', 'meeting_date',
                'meeting_time', 'meeting_end_time', 'description', 'student_id', 'meeting_hash');
            $query->where(function ($query) use ($user_id, $user_type, $hasContext){
                // With a role context only that role's side of the meeting counts, so a
                // parent who is also a teacher sees the meetings of the active role only.
                if (!$hasContext || $user_type == 'parent') {
                    $query->where('parent_id', $user_id);
                }
                if (!$hasContext || $user_type == 'teacher') {
                    $query->orWhere('teacher_id', $user_id);
                }
            });""")

# updateMeeting
rep("""            $meeting = Meetings::where('id',$request->meeting_id)->first();
            if ($meeting->user_id == $user->id) {""","""            $meeting = Meetings::where('id',$request->meeting_id)->first();
            if (!$meeting) {
                return response()->json([
                    'status' => 400,
                    'message' => "Meeting not found.",
                ], 400);
            }
            // Only an invitee can accept or reject: the parent slot, or the institution slot
            // (teacher_id, also used for staff and admins) / principal_id.
            $slot = Access::actsAsParent($user) ? [$meeting->parent_id] : [$meeting->teacher_id, $meeting->principal_id];
            if (!in_array((int) $user->id, array_map('intval', array_filter($slot)), true)) {
                return Access::forbidden();
            }
            if ($meeting->user_id == $user->id) {""")

# postMeetingV2 (only inside that method)
start=s.index("    public function postMeetingV2(Request $request)")
end=s.index("    public function getSchool(Request $request)")
m=s[start:end]
def mrep(old,new):
    global m
    assert old in m, old[:100]
    m=m.replace(old,new)
mrep("""            $meeting_created_user_type = "teacher";
            if ($user->type == 'parent') {
                $meeting_created_user_type = 'parent';
            }
            if ($user->type == 'Principal') {
                $meeting_created_user_type = 'Principal';
            }""","""            // Staff and admin contexts create meetings through the teacher (institution) slot.
            $actingRole = Access::role($user);
            $meeting_created_user_type = "teacher";
            if ($actingRole == 'parent') {
                $meeting_created_user_type = 'parent';
            }
            if ($actingRole == 'principal') {
                $meeting_created_user_type = 'Principal';
            }""")
mrep("""                $userSchool = UserSchools::where('user_id', $user->id)->first();
                $school = Schools::where('id', $userSchool->id)->first();
                if (!$school->principal_id) {""","""                $userSchool = UserSchools::where('user_id', $user->id)->first();
                $school = $userSchool ? Schools::where('id', $userSchool->school_id)->first() : null;
                if (!$school || !$school->principal_id) {""")
mrep("""            if ($request->student_parent_ids && $user->type != 'teacher') {""","""            if ($request->student_parent_ids && $actingRole == 'parent') {""")
mrep("""            if ($request->teacher_id && $user->type != 'parent') {""","""            if ($request->teacher_id && $actingRole != 'parent') {""")
mrep("""            $student_parent_ids = $request->student_parent_ids;
            if (sizeof($student_parent_ids) == 0) {""","""            $student_parent_ids = $request->student_parent_ids ?? [];
            if (sizeof($student_parent_ids) == 0) {""")
mrep("""            if($user->type == 'teacher' && $isActive->call_count <= sizeof($student_parent_ids)){""","""            if(in_array($actingRole, UserRole::INSTITUTION_ROLES, true) && $isActive->call_count <= sizeof($student_parent_ids)){""")
assert m.count("""                        $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();""")==1
m=m.replace("""                        $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();""","""                        $isTeacher = Access::isInstitutionUser($request->teacher_id);""")
assert m.count("""                    $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();""")==1
m=m.replace("""                    $isTeacher = Teacher::where('user_id', $request->teacher_id)->first();""","""                    $isTeacher = Access::isInstitutionUser($request->teacher_id);""")
s=s[:start]+m+s[end:]
open(p,'w').write(s)
EOF

OUT
(Bash completed with no output)
