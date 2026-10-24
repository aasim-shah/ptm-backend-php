# Launch verification — 20 October 2026

> Superseded for web role architecture on 21 October 2026: see [four-panel client feedback implementation](client-feedback-architecture.md). Parent/Teacher web access is removed; Staff requires explicit authorization. Earlier role/demo claims below are historical.
Status: **not ready for production deployment**.

## Verified locally

- Laravel 12.70.0, Sanctum 4, PHP 8.2.32.
- PHPUnit: 53 tests, 300 assertions passed, including fresh database migrations and real bearer-token flows.
- Composer locked dependency audit: no known security vulnerability advisories reported.
- Blade template compilation, route cache build/clear and `git diff --check` passed.
- Local development database migrated through token-expiry migration.

Implemented additions include role-scoped portals, MFA/recovery/session revocation, secure invitations, notification outbox, private quarantined attachments, dedicated job queue, runtime heartbeats and deployment readiness checks. Colourful shared styling retains the client's prohibition on decorative one-sided borders.

## Unverified or incomplete

Docker image and Compose runtime were not tested: the local Docker daemon is unavailable. Scanner success/infected-file acceptance, actual worker/scheduler deployment, provider delivery and restoration must be proved in staging.

Organization-authorized video journeys, billing reconciliation and minute ledger remain incomplete. Legacy integration code does not establish acceptance. Native Flutter source and Documents 1–11 are absent. Provider credentials/staging access, commercial rules and owner-reviewed acceptance evidence remain required. No production deployment or real provider transaction was performed.

Run `php artisan workspace:readiness --production --json` against the intended environment for individual blockers. Local production-mode inspection correctly reports `ready: false`; development configuration is not a production configuration.

## VPS review deployment — 20 October 2026

The upgraded application was subsequently deployed for review at http://2.25.103.51, as requested. Caddy routes the IP site to the isolated `ptm-candidate` stack on loopback port 8091. The original stack remains separate and retained for rollback.

Verification on the VPS:

- Docker image build and startup succeeded with Laravel 12.70.0.
- Initial database snapshot restored successfully; a fresh maintenance-mode snapshot was restored for the final cutover. The existing eight accounts and two schools were preserved.
- Real HTTP login and permitted-page checks passed for Parent, Teacher, Staff, School Admin, District Admin and Super Admin. Authenticator enrollment and recovery-code display passed for privileged test accounts. Temporary check accounts were removed after each test run.
- Actual scheduled and queued heartbeats passed. The worker, scheduler and application were healthy.
- Antivirus clean-file, harmless EICAR test-file detection and missing-file error checks passed with real ClamAV definitions.
- Public `/health/ready` returned 200; `.env` and the credential path returned 404. Shared colourful styles returned 200. Browser login checks found no console errors or horizontal overflow at 390px, and balanced card outlines.
- Other hosted services remained running. Backups, the original application and root-only deployment evidence were retained.

The earlier statements about Docker being unavailable and no deployment performed describe the preceding local verification phase. Live provider transactions, native app acceptance, commercial policies and owner-reviewed launch acceptance are still outstanding. Review deployment does not change the incomplete module scope or satisfy production launch gates. HTTP on the IP address is temporary; secure cookies must be restored when HTTPS is configured. Workspace email/push delivery remains disabled. The image uses the same no-gRPC configuration as the previous deployment, so legacy Firestore chat remains unverified/unavailable until that extension is enabled.

## Explicit review accounts

At the owner's request, five role demo accounts (`parent`, `teacher`, `staff`, `school_admin`, `district_admin` at `workspace.test`) were provisioned separately on the review VPS, with isolated fictional school/district data and seven-day memberships. They have no global Super Admin role. Administrator MFA remains enforced and is left unenrolled for the reviewer to configure. Credentials are kept only in the root-only server deployment record and supplied directly to the owner; they are not part of the application image, source or release archive. The local-only demo seeder remains excluded and its environment guard was not bypassed.

## Temporary authenticator setup skip

At the owner's request, the review VPS enables `REVIEW_ALLOW_MFA_SKIP=true`. The account security page shows a CSRF-protected “Skip to dashboard” action for accounts that have not enabled an authenticator. It grants only the current browser a twelve-hour enrollment deferral and records an audit event; active/verified account checks, role scope and session revocation still apply. Enabled authenticators and bearer-token MFA cannot be skipped. The default remains false in source configuration and `.env.example`; disabling the flag immediately removes the deferral. Security regression verification: 9 tests, 55 assertions passed.
