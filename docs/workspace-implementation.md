# Role workspace implementation

> Superseded for web role architecture on 21 October 2026: see [four-panel client feedback implementation](client-feedback-architecture.md). Parent/Teacher web access is removed; Staff requires explicit authorization. Earlier role/demo claims below are historical.
Implemented locally on 19 October 2026. This is the web dashboard increment, not completion or production acceptance of Document 12's full USA/UK launch.

## Preview

Open http://127.0.0.1:8000/login. Universal sign-in resolves approved memberships; there is no role selector that grants access. The development server was started on port 8000 during implementation.

Optional local fixtures:

```bash
php artisan migrate
php artisan db:seed --class=WorkspaceDemoSeeder
php artisan serve --host=127.0.0.1 --port=8000
```

The seeder refuses environments other than local/testing, is not called by DatabaseSeeder/deployment, and is excluded from production release archives. It creates named demo records without resetting existing accounts. Re-running preserves the demo school's existing fixture data.

| Role | Local fixture account |
| --- | --- |
| Parent | `parent@workspace.test` |
| Teacher | `teacher@workspace.test` |
| Staff | `staff@workspace.test` |
| School Administrator | `school_admin@workspace.test` |
| District Administrator | `district_admin@workspace.test` |

Local fixture password: `Workspace-demo-2026!`. These are development examples, not production accounts.

## Working dashboard behavior

- Shared Home, Messages, Schedule, Events and Profile navigation with role-dependent additional panels and controls; responsive desktop and phone layouts using existing Lumen visual tokens.
- Parent: verified connected students, published Student Updates, school announcements/events, permitted teacher/admin contacts, private messages and appointments.
- Teacher: assigned class sections and students, Student Update drafts/publication and corrections, permitted parent/colleague contacts, private messages and appointments.
- Staff: essential cards based on delegated capabilities. Available optional permissions are `students.view`, `notices.publish` and `events.manage`. Roster permission does not grant academic update access.
- School Admin: scoped students, academic class/section creation and class-teacher assignment, updates, announcements/events, connect/revoke existing account memberships and verify/reject/revoke guardian relationships.
- District Admin: assigned school aggregate and per-school contexts, with the same scoped operational controls. Standalone school contexts do not require a district.
- Contextual announcements and distinct events with audience filtering.
- Private, text-only messaging with read state; current school membership and applicable student relationship are rechecked when reading/sending. School administration alone does not expose private conversations.
- Appointment request, participant accept/decline and cancellation, future-time validation and overlap checks. Account locks serialize conflicting booking attempts. Cancellation followed by a new request is available; no dedicated reschedule workflow is claimed.
- Profile name/password updates affect the authenticated account only. Password changes require the current password, confirmation and at least 12 characters; revoke the user's Sanctum tokens and rotate remember token/session ID.
- User-selectable IANA timezone; form times are normalized to UTC for storage and rendered in the saved timezone.
- Student Updates retain immutable versions; published corrections cannot silently become drafts. Optimistic version checks reject stale edits. Only the author can revise an update.
- Audit entries for membership, guardian, academic assignment, update, post and appointment mutations. Message bodies are not copied into audit entries.

## Backend boundary

New routes are in `routes/workspaces.php`, outside the legacy administrative `Role` group. That old management surface remains restricted to Super Admin/Principal and now also checks active/verified account state.

`WorkspaceAccess` re-resolves effective school/district memberships on each request. It ignores `users.type` for workspace authority. It checks school scope, active dates and applicable student/guardian or class-teaching relationships. Session context IDs are selectors, not authority. Revocation tests cover the new workspace routes, including stale session/deep-link requests.

New `workspace_*` tables isolate explicit organization and publication state from ambiguous legacy data. The migration connects existing school-appointed Principals as School Admin memberships. It deliberately does not approve legacy parent/teacher links, copy private chats into an organization, or publish historical report cards without verified provenance. Those migrations require source/data review. New membership provisioning and guardian verification make records available in these dashboards without deleting legacy records.

Additional fixes: Principal checks in the inspected legacy student/attendance/report helpers are school-bound; the legacy web profile handler uses the authenticated user rather than a submitted ID; the general API profile password change hashes the password and requires the current password; inactive general-API login is rejected.

These changes do not certify every legacy mobile endpoint against the new membership model. Existing mobile APIs and the Student API remain for compatibility. Their complete migration, revocation behavior and isolation audit are outstanding launch work.

## Routes and API

Web entry: `/dashboard`; pages: `/dashboard/{home|classes|students|updates|messages|schedule|events|notices|people|profile}`. Pages and actions enforce role/context checks server-side, including JSON requests to protected web pages. Write forms use CSRF-protected POST routes under `/workspace/*`. Unauthorized record/context requests return 403/404; stale changes/conflicting meetings return 409; invalid input returns 422 for JSON or normal validation feedback for forms.

Additive Sanctum read contracts:

- `GET /api/me/workspaces`: effective contexts for the current user.
- `GET /api/dashboard?context=m123`: scoped summary, capabilities, bounded recent lists, context, timezone and `as_of` timestamp.

API students use a restricted projection rather than exposing legacy health/guardian fields. The new mobile write contracts and native Flutter integration have not been implemented. The web actions work through authenticated sessions; do not treat their URLs as bearer-token APIs.

## Real account setup

Run migrations on the target environment using the normal release procedure. No production deployment was performed during this implementation.

A named, active, verified Super Admin is required for console provisioning; `--by` is the platform operator's existing user ID for the audit trail. Target accounts must already exist and be active/verified. Neither command sets passwords or grants global Spatie roles.

```bash
php artisan workspace:connect teacher@example.com teacher --school=12 --by=1
php artisan workspace:connect office@example.com staff --school=12 --permission=notices.publish --permission=events.manage --by=1
php artisan workspace:connect leader@example.com school_admin --school=12 --by=1
php artisan workspace:district 'Example District' UK --school=12 --school=13 --by=1
php artisan workspace:connect district@example.com district_admin --district=1 --by=1
```

Use actual IDs; the numbers above are examples. A school cannot silently be moved from another district by the district command. Activated market codes come from `config/workspaces.php` (US and UK initially).

School/District admins can then use People & access to connect existing Parent/Teacher/Staff accounts. Privileged admin role provisioning stays outside that form. Give teachers class assignments through Academic structure. Guardians must request the school-supplied student record reference and be verified against school records before students appear. Legacy guardian identifiers are not silently considered verification.

Super Admin retains `/home`; existing Principals can open the legacy admin panel from their workspace. A role-workspaces entry was added to the existing admin profile menu. Legacy Super Admins without memberships return to their platform dashboard.

## Verification evidence

- Baseline before implementation: 12 tests, 28 assertions passed after starting local MySQL.
- Final regression: **37 tests, 202 assertions passed**. PHP syntax checks passed for 21 changed/new PHP files; Blade compilation, route cache build and `git diff --check` passed.
- New workspace tests cover role-specific rendering, real Sanctum tokens, universal login, forged context/school IDs, inactive/expired/revoked access, student/guardian isolation, staff capability enforcement, academic privacy, scoped delegation, district selection, messages, appointment conflicts/state transitions, timezone conversion, immutable update history, console provisioning and legacy Principal school scope.
- Browser checks: Parent, Teacher, Staff, School Admin and District Admin sign-in and rendered dashboards; School Admin People & access; district aggregate/per-school selector. Phone checks at 390px found no document-level horizontal overflow on the checked Parent, district and People screens. Teacher/Staff/School/District desktop captures are in local ignored `.playwright-mcp/` artifacts.
- PHP syntax, Blade compilation, route cache build and whitespace checks are part of final verification. Local debugbar is development tooling, not part of the production dashboard.

Run `php vendor/bin/phpunit` against the configured `school_test` database. The suite uses RefreshDatabase, so use only the dedicated test database.

## Remaining launch scope

This increment does not implement native Flutter screens, provider-backed video/call authorization/reconnect/history, entitlements/minute ledger, Stripe/PayPal reconciliation, organization/market legal configuration, formal grading and school-configured academic approval workflows, production imports/exports, institutional billing, AdMob/ad-free, support/advanced audit UI, Sub-Super delegation, B2B leads/resources/affiliates or recovery certification. Existing integrations are not presented as newly accepted simply because legacy code exists.

New messaging is text-only and separate from legacy Firebase conversations. Existing legacy academic reports are not automatically published into Student Updates. Class management currently covers class/section and class-teacher assignments; full subject/term/enrollment management remains to be mapped and migrated.

Documents 1–11 and retained Flutter source are still absent from this workspace. No L/A requirement is marked Product Accepted or approved deferred here. Use `document-review.md` and Document 12's tracker to complete that broader launch work.

## Client colour reference update

The role portals, sign-in and legacy admin panel now share `public/assets/css/colourful-portals.css`: navy sidebar, pink/blue/purple/green overview cards, six-colour action tiles, tinted notices and tables, and the reused school illustration in a pastel welcome banner. Buttons use darker saturated gradients to keep white labels readable. The shared illustration is in `resources/views/workspace/hero-art.blade.php`. Desktop (1440px) and phone (390px) previews were checked; the existing 37-test/202-assertion suite still passes.

Design preference: do not add decorative one-sided accent borders to sign-in cards, portal panels, headings or notices. Use colour fills, icons and balanced full outlines instead.


## Launch hardening update — 20 October 2026

The runtime is upgraded to Laravel 12, Sanctum 4 and PHP 8.2+. The historical permission migration and token expiry schema are compatible with fresh installations. The retained Blade form helpers use an escaped local adapter instead of the abandoned Collective package.

Implemented web capabilities now also include authenticator MFA with recovery codes, session revocation, signed single-use school invitations, in-app notifications, durable email/push delivery, and private attachments quarantined until an antivirus scan succeeds. Provider delivery remains disabled until explicitly configured and validated. Native mobile write flows and provider-backed video/billing acceptance remain outstanding.

Production operations require all services in `docker-compose.prod.yml`: application, workspace queue worker, scheduler, database and antivirus definitions updater. After backing up and rehearsing restoration, run migrations using the release procedure. Workspace jobs use the dedicated `workspace` database connection and queue; the legacy default queue setting does not run these jobs inline. Configure SMTP/Firebase and the `WORKSPACE_EMAIL_ENABLED` / `WORKSPACE_PUSH_ENABLED` flags only after staging delivery tests. Nonproduction sends require explicit test sinks. Never package `storage/app`, credentials, database backups or local demo accounts into a release.

Check `php artisan workspace:health` and `/health/ready` after startup. Run `php artisan workspace:readiness --production --json` for explicit blockers. This command intentionally reports incomplete video, billing, usage-ledger and owner acceptance requirements; a successful build does not constitute launch acceptance. No production deployment, Docker runtime acceptance, live provider acceptance, backup restoration or native app acceptance has been performed here.

The earlier 37-test figures describe the dashboard-only increment. Use the latest launch verification report for the expanded suite.
