# Deployment Guide

Production deployment of the School System backend (Laravel 12 API for the Parent Teacher Mobile app + Blade admin panel).

## 1. Server requirements

| Item | Requirement |
|---|---|
| PHP | 8.2 or newer, with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`, `intl`, `bcmath`, `fileinfo`, `tokenizer`, `xml` |
| PHP `grpc` extension | Required for Firestore chat (`pecl install grpc`). Without it chat calls fail; everything else works. |
| MySQL | 8.0 |
| Web server | Apache or Nginx with the **document root set to `<app>/public`** |
| Build machine / CI | Composer 2, Node 22+, npm |

> **Document root must be `public/`.** If the domain points at the project root, `.env`, logs and the Firebase key become downloadable.

## 2. One-time server setup

1. **Point the domain** at `<app>/public`. In cPanel: *Domains → Manage → Document Root*.
2. **Create `.env`** in `<app>` from `.env.example` and fill in real values. At minimum:
   - `APP_KEY`: generate with `php artisan key:generate --show`.
   - `APP_URL`, `DB_*`, `MAIL_*`.
   - `TIMEZONE`.
   - `AGORA_*`, `FIREBASE_*`, PayPal keys, and the `TRAIL*`/`PREMIUM*` limits.
3. **Firebase service account:** upload the JSON key to `<app>/storage/app/firebase/service-account.json` with `chmod 600`, or set `FIREBASE_CREDENTIALS` to its absolute path.
4. **Webhook secrets** (webhooks are rejected without them):
   - `AGORA_WEBHOOK_SECRET`: the secret configured in Agora Console → Notification Center.
   - `PAYPAL_WEBHOOK_ID`: the webhook ID shown in the PayPal developer dashboard.
   - `RAZORPAY_WEBHOOK_SECRET`, `STRIPE_WEBHOOK_SECRET`: if those gateways are used.
5. **Rotate leaked credentials.** These were committed in source code before 2026-10-07 and must be treated as public:
   - SendGrid API keys `SG.5zyx…` and `SG.kEZ_…`: revoke both in SendGrid, then put the new key in `MAIL_PASSWORD` (and optionally `SENDGRID_API_KEY`).
   - Firebase Admin SDK key `new-ptm-app-firebase-adminsdk-81q2l` (key id `f2926e2f66…`): in Google Cloud → IAM → Service Accounts, delete that key and create a new one.
   - Change the `superadmin@gmail.com` password if that account exists in production.
6. **First database setup** (new servers only):
   ```bash
   php artisan migrate --force
   php artisan db:seed --class="Database\Seeders\InstallationSeeder" --force
   SUPER_ADMIN_EMAIL=you@example.com SUPER_ADMIN_PASSWORD='strong-password' \
     php artisan db:seed --class="Database\Seeders\AddSuperAdminSeeder" --force
   php artisan db:seed --force   # countries, states, cities, quarters
   ```

## 3. Each deploy

### Option A: Azure Pipelines (existing setup)

Pushing to `development` runs `azure-pipelines.yml`:
1. `scripts/build-release.sh` builds assets (`npm run prod`), installs PHP dependencies without dev packages, and zips only the files listed as needed (excluded files are in `scripts/release-exclude.txt`).
2. The zip is uploaded over FTP and extracted over the app directory. The server's `.env` and Firebase key are not in the zip, so they are kept.
3. `scripts/deploy.sh` runs on the server: maintenance mode on, `migrate --force`, `storage:link`, `optimize` (config + route cache), `view:cache`, then maintenance mode off.

### Option B: Manual

```bash
# on a build machine
bash scripts/build-release.sh          # -> build/release.zip
# copy build/release.zip to the server, then:
unzip -oq release.zip -d /path/to/app
cd /path/to/app && bash scripts/deploy.sh
```

## 4. After deploying a version with the 2026-10-07 fixes

- The migration `2026_10_07_000000_grant_admin_panel_roles` runs automatically. It gives Super Admin all admin permissions, and gives the Principal role to every user linked to a school as principal.
- **Admin login now checks roles, not `users.type`.** Any admin who cannot log in afterwards needs the `Super Admin` or `Principal` role (Roles page, or `php artisan tinker` → `User::find(ID)->assignRole('Principal')`).
- **Mobile app impact:**
  - `/api/teacher/*` now needs a teacher or principal token, `/api/parent/*` a parent token, and `/api/student/*` a student token. Other tokens get HTTP 403.
  - `/api/student-attendance` now needs a Bearer token.
  - Registration only accepts `type=parent` or `type=teacher`.
  - Login, registration and OTP endpoints are rate-limited (10/min; 5/min for forgot-password).

## 5. Smoke test after deploy

```bash
curl -I https://<domain>/login              # 200
curl -I https://<domain>/.env               # 404 (must NOT be 200)
curl -s -X POST https://<domain>/api/auth/login -H 'Accept: application/json' \
     -d 'email=x@y.z&password=wrong'        # JSON error, not HTML
```
Then log in to the admin panel as a Principal and open Dashboard, Classes, Calendar and Chat. For a full API check, import `docs/postman.json` and run the Collection Runner.

## 6. Settings you should know about

| Variable | Default | Meaning |
|---|---|---|
| `SYSTEM_UPDATE_ENABLED` | `false` | Shows the admin "System update" page, which extracts an uploaded zip over the code. Leave it off. |
| `LOG_LEVEL` | `warning` | Use `debug` only while troubleshooting. |
| `SANDBOX` | `false` | `true` makes PayPal use the sandbox credentials. |

Admin pages that edit `.env` (email, fees configuration) rebuild the config cache automatically after saving.

## 7. VPS deployment (Docker): current production setup

The app runs on the VPS `2.25.103.51` as its own Docker Compose stack. It shares the host with other services (Postiz/Temporal in Docker, Caddy on ports 80/443) and does not touch them.

| Item | Value |
|---|---|
| Code | `/root/ptm-backend` (git clone of this repo) |
| Stack | `docker-compose.prod.yml`: `ptm-app` (PHP 8.2 + Apache) and `ptm-mysql` (MySQL 8, private network `ptm-network`) |
| App port | `127.0.0.1:8090` (not exposed publicly) |
| Public access | Caddy block `http://2.25.103.51` in `/etc/caddy/Caddyfile` → `127.0.0.1:8090` (backup: `/etc/caddy/Caddyfile.bak-2026-10-07`) |
| Secrets (root only) | `.env.production` (mounted as the app's `.env`), `.env.compose` (DB passwords for compose), `secrets/firebase-service-account.json`, `secrets/admin-credentials.txt` |
| Data | Docker volumes `ptm_ptm-mysql` (database), `ptm_ptm-storage` (uploads), `ptm_ptm-logs` |

**Update to the latest code** (about 2 minutes, a few seconds of downtime while the container restarts):

```bash
cd /root/ptm-backend
git pull origin main
WITH_GRPC=0 docker compose -f docker-compose.prod.yml --env-file .env.compose build
docker compose -f docker-compose.prod.yml --env-file .env.compose up -d
```
The container runs migrations and rebuilds the caches on every start.

**Firestore chat** needs the `grpc` extension. The current image is built with `WITH_GRPC=0` because compiling grpc takes over an hour on this 2-CPU server. To enable chat, run the same build with `WITH_GRPC=1` (it keeps the running app up while it compiles), then run `up -d`.

**Useful commands**
```bash
docker logs -f ptm-app                                  # app + Apache log
docker exec -u www-data ptm-app php artisan tinker      # console
docker exec ptm-app tail -f storage/logs/laravel-$(date +%F).log
docker exec ptm-mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" ptm' > ptm-$(date +%F).sql   # backup
```

**Moving to a domain with HTTPS:** point the domain's DNS at the server, then in the Caddyfile replace `http://2.25.103.51` with the domain name (Caddy issues the certificate automatically). Set `APP_URL=https://<domain>` in `.env.production`, run `caddy validate --config /etc/caddy/Caddyfile` and `systemctl reload caddy`, then restart the app with `up -d`.


## 8. Workspace upgrade deployment — 20 October 2026

The upgraded stack is staged at `/root/ptm-candidate`, using Compose project `ptm-candidate`, image `ptm-backend:candidate` and loopback port 8091. The cutover switches only the existing IP site's Caddy upstream from port 8090 to 8091. Other hosted services remain outside this deployment. The original `/root/ptm-backend` stack and its original database remain separate for rollback; do not run the old `git pull` instructions expecting them to update the upgraded stack.

The upgraded stack uses separate MySQL, application storage, logs and antivirus-definition volumes. It preserves the source database/accounts and application encryption key. Real provider transactions are not part of deployment testing. Workspace email/push sends stay disabled until staging provider acceptance. The image retains `WITH_GRPC=0`, matching the previous host build; legacy Firestore chat still needs the gRPC extension before acceptance.

The IP address remains HTTP at the owner's request. Its deployment override sets `SESSION_SECURE_COOKIE=false` so browsers can sign in, while HttpOnly, SameSite, encrypted sessions and administrator MFA remain enabled. Restore secure cookies when TLS is configured. This is a temporary review deployment and does not satisfy production launch gates.

Useful commands on the server:

```bash
cd /root/ptm-candidate
docker compose -f docker-compose.prod.yml --env-file .env.compose ps
docker compose -f docker-compose.prod.yml --env-file .env.compose exec -T app php artisan workspace:health
docker compose -f docker-compose.prod.yml --env-file .env.compose exec -T app php artisan workspace:readiness --production --json
```

Root-only backups and deployment checks are under `/root/ptm-deploy-backups`. `active-release-backup` in the upgraded deployment identifies the final cutover snapshot after successful promotion. Snapshot artifacts contain sensitive application data and must not be published or committed.

For a rollback after users have started writing to the upgraded site, first preserve its database/uploads and reconcile those writes. Do not blindly redirect to the original database and lose new activity. During the initial cutover, the deployment script automatically restores the original route and releases original maintenance mode if its checks fail.

## 9. Four administrative panels redeployment — 21 October 2026

The active review portal is http://2.25.103.51/login on the existing `/root/ptm-candidate` stack and loopback port 8091. The current `ptm-backend:candidate` image includes Super Admin, Sub-Super Admin, District Admin and School Admin contexts. Parent/Teacher browser workspaces are denied; Staff needs an explicit `web.access` membership permission. School administration uses the scoped workspace instead of the legacy Principal management surface.

The final deployment backup is `/root/ptm-deploy-backups/20261021T055943Z-four-panels`; `active-release-backup` points to it. It contains the pre-update database, uploads, environment/Compose configuration, source archive and rollback-image reference. The new administrative-request migration is additive. Do not restore an old database over later user activity during an application rollback.

Deployment smoke checks passed for all four roles and MFA, authorized/unauthorized Staff, mobile API preservation and Parent/Teacher browser denial. App, worker and scheduler are healthy. Public login is HTTP 200; public `/.env` is HTTP 404. Root-only verification logs and review-account credentials remain under `/root/ptm-deploy-backups`; no development seeder was run on the server.

See [feature status and deployment evidence](client-feedback-architecture.md) for remaining integration and acceptance gaps. The existing temporary review settings and provider configuration were preserved.

## 10. Pulled backend update — 21 October 2026

Pulled `origin/main` commit `7db0c64` and preserved the existing four-panel workspace/security implementation. The pre-pull local work is also retained in the Git stash named `Preserve deployed four-panel work before pulling backend 7db0c64`. One AuthController merge conflict was resolved by retaining inactive-account rejection alongside the new login response. Registration's trial-day configuration is cast to an integer for Carbon 3 compatibility. The upstream role-context test fixture explicitly marks its account active.

Combined verification: **81 tests, 454 assertions passed**; route-cache build/clear, Blade compilation, merged PHP syntax and whitespace checks passed. The role-context tables, legacy-link backfill and call-usage-ledger migrations ran on the hosted database. The deployed context-switch route is registered and role-request APIs require authentication.

The active `/root/ptm-candidate` stack was updated with image `sha256:0744aa8c2912cbfb667e482f039d8fada87fa0a1febeec1a5986ae85c420a396`. Final database/uploads/configuration/source backup: `/root/ptm-deploy-backups/20261021T073113Z-four-panels`. Promotion log: `/root/ptm-deploy-backups/backend-7db0c64-promote.log`.

Hosted smoke checks passed for all four admin roles and MFA, explicitly authorized Staff web access, Parent/Teacher/unauthorized Staff web denial, and retained mobile workspace API access. Existing hosted admin-review credentials were rechecked and preserved. App, queue worker and scheduler are healthy; public readiness returns `ready`. Temporary smoke users were removed. No provider financial transaction or live video call was performed.

## 11. Backend pull and redeploy — 24 October 2026

Pulled `origin/main` to commit `ddf0618` (merge of the workspace-portals work with the upstream `f8c24fb` verification fixes) and redeployed the active `/root/ptm-candidate` stack on loopback port 8091.

Only two source files differed from the already-deployed four-panel build: `app/Http/Controllers/Api/AuthController.php` and `config/ptm.php`. The change adds a `require_email_verification` config flag (env `AUTH_REQUIRE_EMAIL_VERIFICATION`, default true) and gates the login OTP step behind it; with the default, login behaviour is unchanged. The server-specific `docker-compose.prod.yml` (candidate project name, port 8091, HTTP session cookies) was deliberately not overwritten.

Before the change, a timestamped backup was written to `/root/ptm-deploy-backups/20261024T184828Z-ddf0618-manual`: the `ptm` database dump, the environment and Compose files, the candidate source tarball, and the rollback image id `sha256:0744aa8c…`. The candidate image was rebuilt with `WITH_GRPC=0` (new id `sha256:8be7b83c…`) and the containers recreated with `up -d`; the app, worker and scheduler report healthy.

Post-deploy checks: public `GET /login` is HTTP 200 and `GET /.env` is HTTP 404; Super Admin and Sub-Super Admin review logins are accepted and routed to the MFA step. The other Caddy-fronted services (Postiz on `socialfeed.focusquota.com`) were untouched and still respond. The `workspace:readiness --production` gates that remain false are the documented temporary-review states from §8 (HTTP instead of HTTPS, non-secure session cookies, disabled email/push, and unaccepted Agora/Stripe/PayPal providers and their modules); they are intentional and not deployment failures.
