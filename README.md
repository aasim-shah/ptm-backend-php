# School System — Parent Teacher Mobile backend

Laravel 9 backend for the Parent Teacher Mobile app (parent, teacher and student modes) and the web admin panel used by Super Admins and Principals.

- **API** (`routes/api.php`): Sanctum Bearer tokens. `/api/teacher/*`, `/api/parent/*` and `/api/student/*` are restricted to the matching user type.
- **Admin panel** (`routes/web.php`): Blade + jQuery. Sign-in is through `/login-web`, and only users with the `Super Admin` or `Principal` role are admitted.
- **Integrations:** Agora (calls), Firebase (push, chat), SendGrid (email), PayPal (subscriptions), Razorpay/Stripe (fees).

## Local development

```bash
cp .env.example .env            # set DB_* for your local MySQL, APP_ENV=local, APP_DEBUG=true
composer install --ignore-platform-req=ext-grpc
php artisan key:generate
php artisan migrate
php artisan db:seed --class="Database\Seeders\InstallationSeeder"
php artisan db:seed --class="Database\Seeders\AddSuperAdminSeeder"   # prints the admin password
php artisan db:seed
npm ci && npm run dev            # or: npm run hot  (HMR on localhost:8082)
php artisan serve
```

Run the tests (they use a MySQL database named `school_test`):

```bash
vendor/bin/phpunit
```

## Deployment

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). In short: point the document root at `public/`, keep `.env` and the Firebase key on the server only, then run `scripts/build-release.sh` followed by `scripts/deploy.sh` (Azure Pipelines does both).

## Docs

- [docs/current-state.md](docs/current-state.md): feature inventory, known gaps, security review and platform recommendation.
- [docs/postman.json](docs/postman.json): every route as a Postman collection. Regenerate it with:
  `php scripts/export-routes.php > build/routes.json && python3 scripts/make-postman.py build/routes.json docs/postman.json`
