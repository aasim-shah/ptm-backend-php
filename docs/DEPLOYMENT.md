# Deployment Guide

Production deployment of the School System backend (Laravel 9 API for the Parent Teacher Mobile app + Blade admin panel).

## 1. Server requirements

| Item | Requirement |
|---|---|
| PHP | 8.1 or 8.2, with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`, `intl`, `bcmath`, `fileinfo`, `tokenizer`, `xml` |
| PHP `grpc` extension | Required for Firestore chat (`pecl install grpc`). Without it chat calls fail; everything else works. |
| MySQL | 8.0 |
| Web server | Apache or Nginx with the **document root set to `<app>/public`** |
| Build machine / CI | Composer 2, Node 18+, npm |

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
