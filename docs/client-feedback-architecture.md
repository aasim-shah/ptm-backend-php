# Client feedback: four administrative web panels

21 October 2026. This report supersedes the Parent/Teacher web-portal claims in earlier workspace notes. It describes source implementation; staging acceptance and production deployment are separate.

## Finding and correction

The five accounts previously sent were backed by functioning Parent, Teacher, Staff, School Admin and District Admin web dashboards. Parent and Teacher were **not merely backend test accounts**. The web implementation had incorrectly applied the shared mobile experience to browser access.

Web sign-in and every workspace route now require one of four administrative contexts: Super Admin, Sub-Super Admin, District Admin or School Admin. Staff may use existing operational tools only when an active school membership explicitly includes `web.access`; this does not grant other staff capabilities. Parent and Teacher contexts cannot be selected, even by a user who also has an administrative context. Parent/Teacher data and mobile API contexts remain available. Invitation acceptance supports mobile users without granting web dashboard access.

Super Admin reuses existing global management routes plus the shared administrative workspace. The old Principal management surface is closed; School Admins use the scoped workspace, avoiding a second school management path. No second backend or database was introduced. Legacy global roles and workspace memberships still need a migration audit before claiming all historical mobile records use one authorization model.

## Panel status

“Implemented” means executable source with backend authorization, not client acceptance. “Partial” means some functionality exists and the remaining scope is listed. Work is not labelled “in progress” without an active implementation; the remaining items below are not completed.

| Feature | Super Admin | Sub-Super Admin | District Admin | School Admin |
|---|---|---|---|---|
| Sign-in and actual-data overview | Implemented, platform scope | Implemented, assigned scope | Implemented, district scope | Implemented, school scope |
| Backend authorization | Global operator | Explicit permissions and assigned organizations; no pricing/settings access | Assigned district/schools only | Assigned school only |
| Organization management | Partial: existing global tools, organization list and school profiles | Assigned organization list | School list/profile edits | Own school profile edits |
| Administrator assignment | Sub-Super delegation/revocation, School Admin assignment; District provisioning via operator console | Cannot assign administrators or escalate privileges | School Admin assignment in district | Cannot assign administrators |
| People, invitations, guardian verification | Implemented within selected scope | Explicit `memberships.manage`; cannot grant capabilities beyond own delegation | Implemented in district schools | Implemented in own school |
| Academics | Class/teacher assignment, roster and versioned Student Updates | Roster/class management only with relevant grants | Same within district | Same within school |
| Communications/events | School-scoped notices/events and participant-private messages | Explicit publishing/event grants | Within assigned schools | Within own school |
| Scheduling | Scoped appointment list/create/cancel, participant accept/decline | Scoped operational list with event permission; participant workflows | District school appointments | School appointments |
| CSV exports | School/membership/student report | Explicit `reports.export` | District schools only | Own school only |
| Activity log | Global recent workspace audit | Explicit `audit.view`, school-scoped | Scoped audit | Scoped audit |
| Institutional ad-free requests | Review/approve/reject with audit trail | Read assigned school requests; cannot approve | Submit school/district requests | Submit own school requests |
| Browser data freshness | Backend reads and 30-second overview polling | Same | Same | Same |

## Not yet complete

- Agora: legacy Agora integration is retained, but organization-authorized join tokens, reconnect/failure handling, operational call reports and complete web/mobile acceptance are not implemented for these panels. Appointments are not video calls.
- Commercial: full subscription/revenue dashboards, refunds, configurable pricing, institutional billing, usage reports and immutable minute ledger are not connected to the new panels. Existing payment code is not evidence of completed workflows.
- Ad-free approval is a persisted administrative decision; mobile AdMob eligibility/entitlement enforcement is not connected. Approval must not be represented as activation of mobile ad-free service.
- Reports cover a real CSV school summary. Excel workbooks, comprehensive user/call/billing reports, custom analytics and complete export coverage are outstanding.
- Platform-wide targeted communication campaigns, notification-template administration, full organization verification/suspension, granular district settings and all historical audit coverage are incomplete.
- Native mobile write integration, source reconciliation with Documents 1–11, and all legacy endpoint organization-isolation audits remain outstanding. The Flutter source and Documents 1–11 are absent here.
- US/UK market configuration and IANA timezones exist. Currency/tax/legal/provider/localization acceptance for both markets has not been established.
- Existing shared visual assets and layouts are reused. A complete Preskool visual acceptance cannot be certified without the approved original template/reference files; the supplied images specify hierarchy and features but do not supply that source.
- Polling refreshes overview totals. Instant push updates and complete live refresh of every list are not implemented.

## Testing access

A local preview is running at http://127.0.0.1:8000/login, backed by the isolated `school_review` database in the `ptm-review-db` Docker container. All four administrative fixture logins were checked in the browser.

Local-only fixtures can be created with `php artisan migrate` and `php artisan db:seed --class=WorkspaceDemoSeeder`. Use `/login`.

| Administrative panel | Fixture email |
|---|---|
| Super Admin | `super_admin@workspace.test` |
| Sub-Super Admin | `sub_super_admin@workspace.test` |
| District Admin | `district_admin@workspace.test` |
| School Admin | `school_admin@workspace.test` |

New local fixture accounts use `Workspace-demo-2026!`. The seeder preserves existing passwords and existing delegations. It does not change the password of any previously shared account, including those in the client's screenshot. The Sub-Super fixture is restricted to the Riverside demo school. Parent/Teacher/Staff fixtures remain for mobile/backend testing; Staff is not granted web access by default.

The seeder refuses production, is not part of deployment, and must not be used to provision client production access. The local fixture instructions above are separate from the hosted review-account provisioning recorded below. Use named individual accounts and approved operator provisioning on staging.

## Rollout

Back up the target database, apply the normal release process and the new `2026_10_21_000000_create_administrative_requests` migration. Review existing staff memberships: only explicitly approved memberships should receive `web.access`. Do not bulk grant this permission. Existing Principal accounts require approved School Admin memberships (the original workspace migration backfills principals at migration time; subsequent users require provisioning). Revoke obsolete browser sessions and replace the five-account client handoff with the four administrative test accounts after staging verification.

## Verification — 21 October 2026

- PHPUnit: **61 tests, 344 assertions passed**, using the dedicated `school_test` database in an isolated MySQL container. Existing tests were updated for the approved web/mobile boundary; mobile relationship visibility, scoped operations, attachments, delivery and account-security coverage remain.
- New architecture tests cover mobile-role login/deep-link denial with retained API access, explicit/revocable staff web access, Sub-Super permissions/scope and escalation denial, delegation revocation, district export/assignment isolation and Super-only ad-free decisions.
- PHP syntax checks, Blade compilation, route cache build/clear and whitespace checks passed.
- Browser: Super Admin and Sub-Super Admin dashboard/administration, District Admin dashboard/administration, School Admin dashboard/people/administration, Parent login denial. The 390px School Admin administration page had no document-level horizontal overflow.
- Local migration and demo provisioning succeeded. These were local verification results before the redeployment recorded below. Agora/provider transactions, native mobile runtime, production deployment and client acceptance remain unverified.

## Hosted redeployment — 21 October 2026

The prepared release was deployed to http://2.25.103.51/login on the existing `ptm-candidate` stack. The application, queue worker and scheduler are healthy; the public readiness endpoint returns `ready`. Database, uploads, environment configuration and existing review passwords were preserved. The new administrative-request migration was applied.

Deployment checks passed for all four administrative roles, including MFA enrollment on temporary test users and their permitted dashboard, administration, reports and operational routes. Parent/Teacher/unauthorized Staff web sign-in was denied while their mobile workspace API remained available. Explicitly authorized Staff web access passed. Temporary verification users were removed. Public login returned HTTP 200 and `/.env` returned HTTP 404; the public browser login displays the four approved roles and mobile-access guidance.

New hosted Super Admin and school-scoped Sub-Super Admin review accounts were provisioned and their credentials verified. Existing accounts and passwords were preserved. Hosted credentials are stored separately in a private, ignored testing-access file, not in this documentation or application source. The Sub-Super review-school delegation expires after seven days. MFA setup remains available to the reviewer on first sign-in.

Release archive SHA-256: `7bee8fff2ce47d8cce89d87237a4e83215af8320b06b13fa5b4adae54757a142`.
Deployed image: `sha256:ea36b4785b215e853ea9c1cd528536e07eba5288062ed1c7f3ea967fd6e9d982`.
Final rollback snapshot: `/root/ptm-deploy-backups/20261021T055943Z-four-panels`.

An initial promotion rolled back automatically because the deployment test supplied an incorrect referrer for a deliberately rejected login. After correcting the test client, the repeated deployment and all checks passed. The prior database and uploads backups and application image remain available. This deployment does not complete the outstanding provider/native-app/launch-acceptance scope listed above.
