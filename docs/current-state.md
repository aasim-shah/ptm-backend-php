# Current State — School System (Parent Teacher Mobile)

_Last reviewed: 2026-09-28. Fixes applied: 2026-10-07 (see §0)._

## 0. Fix status (2026-10-07)

Sections 2–7 describe the code **as found on 2026-09-28**. This table shows what has changed since then. The deployment steps are in [DEPLOYMENT.md](DEPLOYMENT.md).

| Item | Status | What changed |
|---|---|---|
| S1 signup privilege escalation | ✅ Fixed | `type` must be `parent` or `teacher`, and the user and role are created in one transaction. The default `POST /login` and `/register` routes are removed. `/login-web` and the admin `Role` middleware check Spatie roles (`Super Admin` / `Principal`) instead of `users.type`. |
| S2 committed secrets | ⚠️ Code fixed, **keys must be rotated** | SendGrid keys removed from the code (`MAIL_PASSWORD` / `SENDGRID_API_KEY`). The Firebase key moved to `storage/app/firebase/service-account.json` (path set by `FIREBASE_CREDENTIALS`) and is git-ignored and excluded from releases. The old keys are still valid until revoked. |
| S3 no API role checks | ✅ Fixed | New `userType` middleware: `/api/teacher/*` → teacher or principal, `/api/parent/*` → parent, `/api/student/*` → student. |
| S4 IDOR / public data | ✅ Fixed for the listed endpoints | `student-attendance` needs auth and access to that student. Class ownership is checked on class delete, subject delete, report create/edit/delete/count, exam create/timetable, and attendance mark/edit/view. Other legacy eSchool teacher endpoints were not audited one by one. |
| S5 PayPal admin routes | ✅ Fixed | Plan tooling now requires a Super Admin login and returns JSON instead of `dd()`. Subscription cancel checks the owner. |
| S6 webhook signatures | ✅ Fixed | PayPal is verified through PayPal's API (`PAYPAL_WEBHOOK_ID`); Agora through HMAC (`AGORA_WEBHOOK_SECRET`). Unsigned requests get 401. |
| S7 test/debug routes | ✅ Removed | `test-notification`, `/clear`, `/sendMail`, `/chat2`, `/sendtest`. |
| S8 middleware crashes | ✅ Fixed | `CheckChild` is null-safe and returns 403; `CheckRole` now checks the role. |
| S9 env files / gitignore | ✅ Fixed | New `.gitignore` (ignores `.env*`, keys, logs, build output; `composer.lock` is now tracked) and `.env.example`. |
| Broken / stub routes (§5) | ✅ Fixed | Routes to missing or empty methods removed or restricted. `route:list` shows 0 broken handlers out of 520. |
| Production caches | ✅ Fixed | `config:cache` and `route:cache` now succeed: removed the installer config, fixed the duplicate `logout` route name, and moved `env()` calls in app code to `config/environment.php`. Before this, `env()` returned null in production for mail, Agora, PayPal and trial settings. |
| Admin panel crashes (§4.3) | ✅ Fixed | Super Admin gets all admin permissions except the teacher-scoped ones (migration). Principal-only pages redirect with a message instead of returning 500. Fixed the `search`-param crash on 166 list endpoints, the edit-profile crash and the favicon path. System update is off unless `SYSTEM_UPDATE_ENABLED=true`. |
| `deleteClass` bug | ✅ Fixed | Students were detached before being read, so parents were never notified and meetings were not removed. |
| TLS verification | ✅ Fixed | Outgoing curl calls (push notifications) had `CURLOPT_SSL_VERIFYPEER=false`; now on. |
| `exit()` / `die()` / `dd()` in request paths | ✅ Fixed | Stripe webhook, PayPal plan creation, curl helpers and SendGrid error paths. |
| Seeder | ✅ Fixed | `AddSuperAdminSeeder` no longer resets user #1 to `superadmin/superadmin`; credentials come from `SUPER_ADMIN_*` or are randomly generated. `InstallationSeeder` no longer aborts. |
| Dependencies | ⚠️ Partly | dompdf 2 → 3 (6 advisories fixed). `krlove/eloquent-model-generator` moved to dev. `axios` upgraded to 1.x and `webpack` pinned at `5.98.0`. **Laravel 9 still has 5 advisories (1 high); the fix is upgrading to Laravel 10+.** The remaining npm advisories are in build tools only (Laravel Mix and its dev server), not in shipped code. |
| Front-end build | ✅ | `npm run prod` → 1.1 MB versioned `app.js` (was a 7.9 MB dev build); no source maps in production. |
| Tests | ✅ Added | `tests/Feature/SecurityTest.php`: 10 tests covering S1, S3, S4, S5, S6, S7 and admin login. `phpunit`: 12 tests pass. |
| Deployment | ✅ | `scripts/build-release.sh` builds a clean release zip and `scripts/deploy.sh` runs the server steps. `azure-pipelines.yml` is rewritten to use them. Verified locally: the release boots in production mode with config and route caches, and `/.env` returns 404. |
| Unused files | ⏳ Your call | Listed in `scripts/cleanup-unused.sh` (old root `index.php`/`server.php`, the stale `assets/` copy, `error_log`, installer views, empty files). They are already left out of release zips; run the script to remove them from the repo. |
| Still open | — | Laravel 10+ upgrade; Firestore chat needs `ext-grpc` on the server; `.env.prod` has no PayPal/TRAIL/PREMIUM values (check the real server `.env`); v1/v2 duplicate endpoints; the three password-reset flows. |

_Method: static analysis of `routes/`, `app/Http/Controllers`, middleware, models, migrations and Blade views, plus a **runtime smoke test of the admin panel** on a fresh local database (see §4 and §9). The mobile API has **not** been exercised at runtime yet, so for API rows "✅" means "the route is wired to a real, non-empty controller method". Run `postman.json` against the local server to confirm API behaviour._

## 1. What this project is

- **Backend:** Laravel 9 (PHP ^8.0), MySQL, Sanctum tokens for mobile, Spatie roles/permissions.
- **Frontend:** Blade admin panel, jQuery + bootstrap-table, and a few Vue 2 components built with Laravel Mix.
- **Clients:** one mobile app with **Parent** and **Teacher** modes (the "PTM" core), plus a **Student** API. The Student and fees/exam parts come from the WRTeam "eSchool" template this project was built on.
- **Integrations:** Agora (video calls), Firebase (FCM push, Realtime DB chat), SendGrid (email), PayPal (subscriptions), Razorpay/Stripe (fee payments), Pusher/Laravel Echo, Maatwebsite Excel exports, DomPDF receipts.

## 2. Route inventory

| Area | Routes | Wired OK | Broken (method missing) | Stub (empty body) | Closure |
|---|---|---|---|---|---|
| Mobile API (`routes/api.php`) | 189 | 187 | 0 | 0 | 2 (`test-notification`, declared twice) |
| Admin panel (`routes/web.php`)* | 351 | 321 | 15 | 11 | 4 |
| **Total** | **540** | **508** | **15** | **11** | **6** |

\* Excludes 36 `Route::resource` `create`/`edit` routes with no method. The admin UI uses modals instead of those pages, so they are harmless 500s rather than missing features.

`postman.json` contains every one of the 540 requests. Broken and stub routes are prefixed `[BROKEN]` / `[STUB]`. Each request's description gives the handler `file:line`, auth type, middleware and validation rules.

## 3. Features — status

Legend: ✅ wired and implemented · ⚠️ works with caveats · ❌ broken or missing · 👻 code exists but no UI entry point

### 3.1 Mobile app — core PTM (Parent + Teacher)

| Feature | Status | Endpoints / notes |
|---|---|---|
| Register (email + OTP) | ⚠️ | `POST /api/auth/register`. The `type` field is trusted blindly; see Security S1. If `type` is not a real role, `assignRole` throws **after** the user row is saved, leaving an orphan user and returning 500. |
| Login / logout / profile | ✅ | `auth/login`, `auth/signout`, `logout`, `auth/details`, `auth/get/profile`, `auth/update`, `auth/update/profile`, `auth/fcm` |
| Email verification pincode | ✅ | `send/pincode`, `verify/pincode` |
| Forgot / reset password | ⚠️ | **Three separate flows:** `forgot/password` + `reset/password` (token-based), `forgot-password` → `verify-otp` → `set-password`, and `student/forgot-password`. The mobile team should agree on one. `forgotPassword1` calls `dd()` in a `catch`, so errors return an HTML dump instead of JSON. |
| Delete account, rate app | ✅ | `DELETE auth/delete`, `auth/rate` |
| Schools: add, search, link user | ✅ | `add/school`, `get/schools`, `get/schools-details`, `user/schools`, `get/teacher/schools`, `parent/schools` |
| Countries / states / cities | ✅ | `get/countries`, `states/cities/country/{id}` |
| Teacher: classes and sections | ✅ | `teacher/class/create`, `teacher/classes/fetch`, `teacher/school-sections`, `DELETE teacher/class/delete` |
| Teacher: subjects | ✅ | `teacher/subject`, `teacher/assign/subject`, `teacher/update/subject`, `DELETE teacher/subject/delete`, `subjects` |
| Parent: add/edit children | ✅ | `parent/student/create`, `parent/student/update`, `DELETE parent/student`, `parent/children-list` |
| Class join requests (parent → teacher) | ✅ | `student/assign/class`, `teacher/class/students/requests`, `teacher/student/accept`, `parent/class/students/requests/cancel`, `DELETE student/remove/class` |
| Attendance (v2, teacher-marked) | ✅ | `teacher/mark-attendance`, `teacher/edit-attendance`, `teacher/view-attendance/class`, `student-attendance` (public, see S4) |
| Student report cards (quarters) | ✅ | `teacher/create/student-report`, `update/student-report`, `DELETE delete/student-report/{id}`, `reports/count`, `class/reports/count`, `class/subject/reports/count`, `parent/student/report`, `quarters` |
| Meetings (book, list, accept/decline) | ⚠️ | v1 and v2 exist side by side: `add/meetings`, `add/meetings-v2`, `get/meetings`, `get/meetings-v2`, `update/meetings`, `search/meetings`, `get/status/meetings`. Remove v1 once the app only uses v2. |
| Video calls (Agora) | ✅ | `join/call`, `left/call`, `channel/token`. Call minutes are deducted through `webhook/agora`. |
| Chat (Firebase RTDB + FCM) | ⚠️ | `chat/send`, `chat/read`, `teacher/chat/{to_user?}`, `parent/chat/{to_user?}`. The Firebase service account is loaded from the relative path `'../new-ptm-app-firebase-adminsdk-….json'`, which only resolves when the CWD is `public/`. It breaks under queues, CLI and some FPM setups. |
| Notifications | ✅ | `notifications`, `notifications-read` |
| Announcements (v2) | ✅ | `auth/announcement`. The admin panel creates them. |
| Subscriptions (PayPal) | ⚠️ | `plans`, `plan/trail`, `active/subscription`, `plan/{id}/agreement/create`, `subscription-cancel/{id}`. See S5 and S6. Plan admin is `dd()`-based dev tooling. |

### 3.2 Mobile app — Student API and eSchool-template features

These routes are all wired. Many depend on data that can **only** be created from admin pages that are no longer in the sidebar (👻).

| Feature | Status | Notes |
|---|---|---|
| Student login (GR number + password) | ✅ | `student/login` |
| Timetable (student / parent / teacher) | 👻 | Read APIs exist. The only way to create a timetable is the hidden admin `/timetable` page; there is no teacher API. |
| Lessons & topics | ✅ | Teacher CRUD through the API, and student/parent read. |
| Assignments & submissions | ✅ | Full teacher CRUD, student submit/delete, parent read, reports. |
| Announcements (legacy) | ✅ | `teacher/*-announcement`, `student/announcements`, `parent/announcements` |
| Exams, marks, results | ⚠️ | Teachers can create exams and exam timetables (`teacher/create/exam*`) and submit marks. Grades are admin-only and hidden, and `show-grades`/`update-grades` are broken (see §5). |
| Online exams | 👻 | Students and parents can take exams and read results. Exams and questions can only be authored on the hidden admin pages. |
| Fees & fee payments | 👻 | Parent APIs for details, transactions, receipt PDF and Razorpay/Stripe payment exist. Fee types and class fees are set up on hidden admin pages. `fees/paid/store` and `fees/paid/update/{id}` are broken. |
| Holidays, sliders, session year, settings | ⚠️ | Public read APIs exist. Holidays and sliders can only be managed on hidden pages. |

### 3.3 Admin panel

See §4 for the full admin panel breakdown and runtime results.

## 4. Admin panel (web)

### 4.1 Overview

| Item | Detail |
|---|---|
| URL (local) | `http://127.0.0.1:8000` → login page; after login `/home` |
| Login | `POST /login-web`. Only users with `users.type = 'admin'` or `'Principal'` are admitted. The default Laravel `POST /login` is **also** active and skips this check (Security S1). |
| Seeded account | `superadmin@gmail.com` / `superadmin` (from `AddSuperAdminSeeder`). **Change before any deploy.** |
| Roles | `Super Admin`, `Principal`, `Teacher`, `Parent`, `Student` (Spatie). Only Super Admin and Principal use the panel. |
| Permissions | 124 seeded. Fresh-seed grants: Super Admin 89, Principal 99, Teacher 33, Parent 0, Student 0. |
| Stack | Blade (149 views, ~18.4k lines), jQuery, bootstrap-table (server-side JSON lists), a few Vue 2 components, Laravel Mix. |
| Language | Translation file `resources/lang/en.json` (English only). |
| Assets | `mix('js/app.js')` / `mix('css/app.css')`; `npm run hot` serves them with HMR on `localhost:8082`. |

### 4.2 Two personas, one panel

The panel is shared by **Super Admin** (platform owner, sees all schools) and **Principal** (sees only the school where `schools.principal_id` = their user id). Several controllers assume a Principal: they look up "my school" with `Schools::where('principal_id', auth id)->first()->id` and crash when the user has no school. As a result, **Super Admin gets HTTP 500 on Classes, Subjects, Calendar and Meeting list**. This is the main functional gap in the panel: either give Super Admin a school picker or guard those lookups.

### 4.3 Sidebar pages — runtime result (logged in as Super Admin, empty DB)

| Section | Page | Route | Result | Notes |
|---|---|---|---|---|
| Dashboard | Dashboard | `/home` | ✅ 200 | Counters for schools, teachers, students, parents, boys/girls, recent announcements. All 0 on empty DB. |
| People | Parents | `/parents` | ✅ 200 | List + details view + Excel export. List JSON `/parents_list` ✅. **No create/delete** (`store`/`destroy` missing). |
| | Teachers | `/teachers` | ✅ 200 | CRUD, details view, Excel export. List JSON `/teacher_list` ✅. |
| | Students | `/students` | ✅ 200 | CRUD, Excel export. List JSON `/students-list` ✅. |
| | Principals | `/get-principal` | ✅ 200 | Create, link/unlink to school, remove. List JSON `/principals` returns **500 if `search` param is absent** (`Undefined array key "search"`); works when bootstrap-table sends it. |
| Schools | Schools | `/schools-view` | ✅ 200 | Create/update schools, Excel export. |
| Academics | Classes | `/class` | ⚠️ 302 → home | Super Admin lacks the `class-list` permission; `/class-list` JSON is 500 for users without a school. |
| | Subjects | `/subject` | ⚠️ 200 | Page loads, but `/subject-list` JSON is **500** for users without a school. |
| Communication | Push notifications | `/push-notifications` | ✅ 200 | Send FCM push to class users; meeting notifications. |
| | Teacher chat / Parent chat | `/chat-teacher`, `/chat-parent` | ❌ 400 | Needs a school and chat users; Firestore needs `ext-grpc` locally. |
| | Calendar | `/calendar` | ❌ 500 | `Attempt to read property "id" on null` (no school). |
| | Meeting list | `/meeting-list/view` | ❌ 500 | Same cause as Calendar. |
| | Announcements | `/announcement` | ✅ 200 | CRUD + Excel export. |
| Attendance | View attendance | `/view-attendance` | ✅ 200 | "Add attendance" link is commented out in the sidebar, though `/attendance` loads (200). |
| Settings | App / General / Language / FCM / Email | `/app-settings`, `/settings`, `/language`, `/fcm-settings`, `/email-settings` | ✅ 200 | FCM form posts to `/setting-update` (works); `POST /fcm-settings` itself is broken. |
| | Fees configuration | `/fees-config` | ✅ 200 | Payment gateway keys (Razorpay/Stripe), currency. |
| | Privacy / Terms / About / Contact | `/privacy-policy`, `/terms-condition`, `/about-us`, `/contact-us` | ✅ 200 | Rich-text content served to the apps via `/api/settings`. |
| | Roles & permissions | `/roles` | ❌ 403 | Super Admin lacks `role-list`/`role-create` in the seed. |
| | System update | `/system-update` | ✅ 200 | Uploads a zip and overwrites app files — **dangerous in production**; restrict or remove. |
| Profile | Edit profile | `/edit-profile` | ❌ 500 | `Undefined array key 1` in `settings/update_profile.blade.php` (splits the user's name; seeded admin has one word). |
| | Change password | `/resetpassword` | ✅ 200 | |

### 4.4 Hidden pages (routes work, no sidebar link) — runtime result

| Result | Pages |
|---|---|
| ✅ 200 | Medium, Section, Session years, Category, Assign class teacher, Subject teachers, Timetable, Class timetable, Teacher timetable, Assignment submissions, Exams, Grades, Exam timetable, Holidays, Holiday view, Sliders, Fee types, Class fees, Fees paid, Fee transaction logs, Promote students, Roll numbers, Bulk student import, Assign class, Student reset password, Users, Excel exports |
| ⚠️ 302 → home (missing permission for Super Admin) | Lessons, Lesson topics, Assignments, Upload exam marks, Exam results, Online exams, Online exam terms |

These are eSchool-template features the mobile apps still read from (timetable, exams, online exams, fees, holidays, sliders). Decide per feature: restore the sidebar link or delete the feature end-to-end.

### 4.5 Admin panel fixes (quick wins)

1. Seed Super Admin with **all** permissions (`Role::findByName('Super Admin')->syncPermissions(Permission::all())`).
2. Replace every `Schools::where('principal_id', …)->first()->id` with a null-safe helper that returns 403/redirect, or add a school switcher for Super Admin.
3. Fix `/principals` (`$request['search'] ?? null`) and `update_profile.blade.php` (name split).
4. Remove or lock down **System update** (arbitrary zip upload = remote code execution if an admin account is compromised).
5. Restore "Add attendance" in the sidebar or delete the route.

## 5. Broken routes (route → missing method)

These return HTTP 500 (`BadMethodCallException`). None of them is linked from the current UI.

| Route | Controller@method |
|---|---|
| `POST /fcm-settings` | `SettingController@fcm_update` (the FCM page actually posts to `/setting-update`, which works) |
| `POST /parents`, `DELETE /parents/{id}` | `ParentsController@store`, `@destroy` |
| `GET /timetable/{id}`, `PUT /timetable/{id}`, `GET /timetable-list` | `TimetableController@show`, `@update` |
| `PUT /attendance/{id}`, `DELETE /attendance/{id}` | `AttendanceController@update`, `@destroy` |
| `GET /show-grades`, `PUT /update-grades/{grade_id}` | `ExamController@showGrades`, `@updateGrades`. **Grades cannot be listed or edited.** |
| `PUT /promote-student/{id}`, `DELETE /promote-student/{id}` | `StudentSessionController@update`, `@destroy` |
| `GET /sendtest` | `SettingController@test_mail` |
| `POST /fees/paid/store`, `PUT /fees/paid/update/{id}` | `FeesTypeController@feesPaidStore`, `@feesPaidUpdate` |

**Empty stubs (return blank 200):** `GET /sendMail`, `GET /chat2`, `GET /exam-timetable/create`, `GET /exam-timetable/{id}/edit`, **`PUT /exam-timetable/{id}` (updates silently do nothing)**, `GET /fees-type/create`, `GET /fees-type/{id}/edit`, `GET /online-exam/create`, `GET /online-exam/{id}/edit`, `GET /online-exam-question/create`, `GET /online-exam-question/{id}/edit`.

## 6. Security issues (fix before any production deploy)

| # | Severity | Issue | Where |
|---|---|---|---|
| S1 | **Critical** | **Privilege escalation at signup.** `POST /api/auth/register` passes the user-supplied `type` straight to `assignRole()`. Sending `type=Super Admin` creates a Super Admin. The default Laravel `POST /login` from `Auth::routes()` is still active and has no `type` check (unlike `/login-web`), so that account can log in to the admin panel. Sending `type=admin` also creates a user who passes `/login-web`'s `type == 'admin'` check. | `Api/AuthController.php:111`, `routes/web.php:56`, `Auth/LoginController.php:46` |
| S2 | **Critical** | **Secrets committed to the repo:** Firebase Admin SDK private key (`new-ptm-app-firebase-adminsdk-81q2l-….json`) and SendGrid API keys hardcoded in 4 places. Rotate all of them and move them to `.env`. | project root; `routes/api.php:349`; `Api/ApiController.php:243,299`; `Http/Libraries/Helpers.php:39` |
| S3 | High | **No role checks on the mobile API.** Only Laravel `auth:sanctum` is enforced. A parent token can call every `/api/teacher/*` endpoint. The `v2` controllers and `ParentApiController` contain no role checks at all. | `routes/api.php` teacher group |
| S4 | High | **IDOR and unauthenticated data access.** `GET /api/student-attendance?student_id=` is public and returns any child's attendance. `DELETE /api/teacher/delete/student-report/{id}` deletes any class's report cards with no ownership check. `DELETE teacher/class/delete` checks the role but not ownership. | `Api/v2/StudentController.php:859`, `Api/v2/TeacherController.php:639`, `Api/ApiController.php:1865` |
| S5 | High | **Unauthenticated PayPal admin routes:** `GET /plan/create`, `/plan/{id}/activate`, `/plan/{id}`, `/subscription-list` create and activate live PayPal plans and `dd()` the output. `POST /api/subscription-cancel/{id}` cancels any subscription ID without checking the owner. | `routes/web.php` bottom, `Payment/SubscriptionController.php` |
| S6 | Medium | PayPal and Agora webhooks do not verify signatures, so anyone can POST fake subscription or call events. Razorpay and Stripe do verify. | `WebhookController.php:377,449` |
| S7 | Medium | `POST /api/test-notification` is a public open email relay using the hardcoded SendGrid key, and it is declared twice. `GET /clear` publicly clears caches. | `routes/api.php:332-359`, `routes/web.php:373` |
| S8 | Medium | `CheckChild` middleware calls `$user->parent->children()` without a null check. A non-parent token gets a 500 instead of a 403. `CheckRole` (`Role`) only checks "is logged in", despite its name. | `Http/Middleware/CheckChild.php`, `CheckRole.php` |
| S9 | Low | `.env.dev` / `.env.prod` are in the project folder. There is no git repo, so no `.gitignore` protection, and the file named `gitignore` is missing its leading dot. | root |

## 7. Code-quality and setup gaps

- **No `composer.lock`.** Every `composer install` resolves fresh versions, so builds are not reproducible. Commit a lock file.
- **Not under version control.** Run `git init`, rename `gitignore` → `.gitignore`, and add `.env*` and `*firebase-adminsdk*.json` to it.
- **Stray files:** the root `index.php` and `server.php` point at `../vendor`, which is wrong from the root. Also stray: `test.php`, `error_log`, `cmd.sh`, `nuxt.config.js`, `vite.config.js` (the project uses Mix, not Vite), and bundled Agora SDKs checked into `resources/js` (`AgoraRTCSDK-2.4.0.js`, `AgoraRTC_N-4.7.3.js`), which makes `app.js` 7.5 MB.
- **Legacy code:** duplicate v1/v2 endpoints (meetings, subjects, students); `agora-rtc-sdk` v3 is deprecated but still a dependency next to `agora-rtc-sdk-ng`; Vue 2 is EOL.
- **Inconsistent API responses:** some endpoints return `{status, message}` with HTTP 401/500, others `{error, message, code}` with HTTP 200 or 400. Validation failures return **401** in `AuthController`. The mobile app has to handle both shapes.
- **Many `Route::any`:** 24 API routes accept every HTTP verb.
- **Fresh install could not migrate (fixed 2026-09-28):** `2023_09_20_074650_add_attributes_to_meetings_table.php` changed the foreign keys `meetings.teacher_id` / `parent_id` to `TIME`. MySQL 8 rejects this, so `php artisan migrate` failed on any new database. Changed to `unsignedBigInteger()->nullable()->change()`. Production was probably migrated on an older MySQL or by hand, so **production schema may differ from migrations** — compare before the next deploy.
- **`InstallationSeeder` aborts at line 486** (`RuntimeException: View path not found` while clearing views). Roles and permissions are created before that point, so the panel still works.
- **Dependencies with known vulnerabilities:** current Composer refuses to resolve `composer.json` because `dompdf/dompdf` 2.x (via `barryvdh/laravel-dompdf ^2.0`) has 10 security advisories. Local install used `COMPOSER_NO_SECURITY_BLOCKING=1`. Upgrade to `laravel-dompdf ^3` and run `composer audit`. `npm audit` reports 21 JS vulnerabilities (2 high).
- **`google/cloud-firestore` requires `ext-grpc`.** Without it Composer refuses to install; locally we used `--ignore-platform-req=ext-grpc`, so Firestore features won't work locally.
- **`config('app.timezone')` reads `env('TIMEZONE')`** with no default; if unset, Laravel emits deprecation notices. Add `TIMEZONE=UTC` to `.env` files.
- **Missing `storage/framework/{sessions,views,cache}` folders** in the project copy; every request 500s until they exist.
- **`webpack` pin:** Laravel Mix 6 breaks with webpack ≥ 5.100 (`Cannot find module 'webpack/lib/SizeFormatHelpers'`). It is pinned to `5.98.0` in `package.json`.

## 8. Tests

Only Laravel's two example tests exist. There is no CI test run.

## 9. Running locally (as set up on 2026-09-28)

Homebrew could not build PHP/MySQL from source on this Intel Mac (Tier 3, no bottles) and Docker Desktop was unresponsive, so portable binaries live in `~/devtools` (nothing installed system-wide).

| Service | Command | Port |
|---|---|---|
| MySQL 8.0.43 | `~/devtools/mysql-8.0.43-macos15-x86_64/bin/mysqld --basedir=… --datadir=~/devtools/data --port=3307 --socket=~/devtools/mysql.sock --mysqlx=OFF` | 3307 (root, no password, DB `school_local`) |
| Laravel | `~/devtools/bin/php artisan serve --port=8000` (static PHP 8.2.32) | 8000 |
| Assets (HMR) | `npm run hot` | 8082 (8080/8081 used by other projects) |
| Composer | `COMPOSER_NO_SECURITY_BLOCKING=1 ~/devtools/bin/php -d disable_functions=curl_multi_exec,curl_multi_init ~/devtools/bin/composer.phar install --ignore-platform-req=ext-grpc` | — |

First-time DB setup: `php artisan key:generate`, `php artisan migrate`, then seed `InstallationSeeder`, `AddSuperAdminSeeder`, `DatabaseSeeder` (countries/states/cities/quarters).

## 10. Should we migrate Laravel → Node.js (Fastify)?

### 10.1 Size of what would be rewritten

| Asset | Size |
|---|---|
| Controllers | 63 files, ~30k lines |
| Blade views (admin panel) | 149 files, ~18.4k lines |
| Models / migrations | 74 models, 101 migrations, 78 tables |
| HTTP routes | 540 (189 mobile API, 351 admin) |
| Integrations | Agora tokens + webhook, Firebase (FCM + Firestore/RTDB), SendGrid, PayPal subscriptions (bundled SDK), Razorpay, Stripe, Excel export/import, PDF receipts, Spatie roles/permissions |

A rewrite is not just the API: **the admin panel is server-rendered Blade**, so moving to Node also means rebuilding ~150 admin screens as a separate SPA (e.g. React/Next.js) or with an admin framework (Refine, AdminJS).

### 10.2 Options

| | A. Keep Laravel, upgrade & harden | B. Big-bang rewrite to Fastify | C. Incremental ("strangler") move to Fastify |
|---|---|---|---|
| What | Fix security, delete template leftovers, upgrade Laravel 9 → 12, add tests | New Fastify + TypeScript codebase, switch over on one date | Fastify service sits in front of / beside Laravel on the same DB; move one endpoint group at a time |
| Time to first value | Days (security fixes) | Months before anything ships | Weeks per module |
| Risk | Low | High: parity bugs, mobile app regressions, frozen feature work | Medium: two stacks for a while |
| Mobile app impact | None | Must match every response quirk or ship a new app | None if paths/shapes are kept |
| Admin panel | Keep Blade, improve gradually | Rebuild entirely | Keep Blade until last; rebuild as SPA at the end |
| Best when | Team can maintain PHP; product = current scope | Almost never at this size | Team is Node-first and plans to shrink scope to the PTM core |

### 10.3 Pros of moving to Node.js / Fastify

- **One language across the stack** if the mobile app (React Native?) and future web frontends are JS/TS — shared types, validation schemas (zod) and hiring pool.
- **TypeScript end-to-end** gives compile-time checks the current code lacks (untyped `$request->x` everywhere).
- **Fastify performance** and low overhead; good for realtime/websocket features (chat, call signalling) without Pusher.
- **Clean slate**: consistent response envelope, proper auth/role guards, OpenAPI generated from schemas (`@fastify/swagger`), no eSchool dead code.
- **Modern ecosystem** for queues (BullMQ), ORMs (Prisma/Drizzle), testing (Vitest), and first-class SDKs for Agora, Firebase, Stripe.

### 10.4 Cons of moving to Node.js / Fastify

- **Cost**: ~30k lines of controller logic + ~150 admin screens + 78-table schema must be re-implemented and re-tested. Months of work with no new features.
- **Laravel gives a lot for free** that Fastify does not: auth/sessions/CSRF, Sanctum tokens, Spatie roles & permissions, Eloquent relations, migrations, queues, mail, validation, Excel (Maatwebsite), PDF (DomPDF), scheduler. Each needs choosing, wiring and securing.
- **Parity risk**: the mobile app depends on today's inconsistent response shapes and `Route::any` verbs; every mismatch is a production bug.
- **The current problems are not Laravel's fault**: trusting `type` on signup, missing role checks, committed secrets and IDORs would be repeated in any framework without tests and review.
- **Business logic knowledge is in the code**, not in docs; a rewrite re-discovers it by trial and error.
- **Two stacks during transition** (option C) means duplicated deploys, monitoring and auth token sharing.

### 10.5 Pros and cons of staying on Laravel

| Pros | Cons |
|---|---|
| Working product today (508/540 routes wired; most admin pages load) | Laravel 9 is out of security support — upgrade is mandatory (9 → 10 → 11 → 12) |
| Security issues are fixable in days | PHP skills needed on the team |
| Upgrade path is incremental and well documented (Laravel Shift can automate much of it) | Legacy template code must be pruned by hand |
| Admin panel keeps working | Blade/jQuery admin UI looks dated; Vue 2 is EOL |
| Batteries included: auth, roles, queues, mail, Excel, PDF | Weak typing unless you add PHPStan/Larastan |

### 10.6 Database choice

| Option | Verdict | Why |
|---|---|---|
| **MySQL (current)** | Keep for now | Works; all 101 migrations target it; no feature needs something else. |
| **PostgreSQL** | Good optional move | Stricter types, JSONB, better constraints and full-text search. Eloquent (and Prisma/Drizzle if Node) support it; move during the Laravel upgrade or rewrite, not as a separate project. |
| **MongoDB** | Not recommended | Data is strongly relational (school → class → section → student → attendance/reports/fees/exams; many-to-many teacher↔class, parent↔child). Mongo would push joins and integrity into application code. Chat is the only document-shaped data, and it already lives in Firebase. |

### 10.7 Recommendation

**Option A** by default: fix security (§6), prune unused template features, upgrade Laravel to 12, add feature tests, and optionally move to PostgreSQL during the upgrade.

Choose **Option C** only if (1) the backend team is Node-only **and** (2) the product scope is the parent–teacher core (auth, schools/classes, join requests, meetings, calls, chat, attendance, reports, subscriptions — ~70 endpoints). Suggested Node stack in that case: Fastify + TypeScript, zod schemas, Prisma or Drizzle on PostgreSQL, BullMQ + Redis for jobs, `@fastify/swagger` for docs, and a React (Next.js or Refine) admin to replace Blade last. Keep the existing `/api/...` paths and response shapes so the mobile app needs no change, and use `postman.json` as the regression suite for each migrated module.

Avoid **Option B**.

## 11. Suggested next steps (priority order)

1. Fix **S1–S2**: whitelist `type` to `parent|teacher` on register, disable `Auth::routes()` login/register (keep `/login-web`), rotate the Firebase and SendGrid keys, and move them to `.env`.
2. Add a role middleware to `/api/teacher/*` and `/api/parent/*`, and add ownership checks to the delete/update endpoints (S3–S4).
3. Put the PayPal plan tooling behind admin auth or remove it, and delete `test-notification` and `/clear` (S5, S7).
4. Decide product scope for the eSchool features (timetable, online exams, fees, holidays). Either restore their admin sidebar links and fix the broken routes in §5, or remove them from the API.
5. Apply the admin panel quick wins (§4.5), then run the Postman collection against the local server (§9) and record real API pass/fail counts here.
6. `git init`, commit `composer.lock`, and add feature tests for auth, meetings, class requests and attendance.
7. Make the platform decision in §10 (keep Laravel vs. incremental Fastify) before starting large new features.
