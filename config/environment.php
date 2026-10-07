<?php

/*
| Environment values read by application code.
|
| Application code must not call env() directly: once `php artisan config:cache`
| runs (production), env() returns null outside config files. Read these via
| config('environment.KEY') instead.
*/

return [
    'AGORA_APP_CERTIFICATE' => env('AGORA_APP_CERTIFICATE'),
    'AGORA_APP_ID' => env('AGORA_APP_ID'),
    'AGORA_WEBHOOK_SECRET' => env('AGORA_WEBHOOK_SECRET'),
    'APP_DASHBOARD' => env('APP_DASHBOARD'),
    'APP_ENV' => env('APP_ENV'),
    'APP_NAME' => env('APP_NAME'),
    'APP_SITE' => env('APP_SITE'),
    'APP_URL' => env('APP_URL'),
    'BROADCAST_DRIVER' => env('BROADCAST_DRIVER'),
    'DEMO_MODE' => env('DEMO_MODE'),
    'FAVICON' => env('FAVICON'),
    'FIREBASE_DB_URL' => env('FIREBASE_DB_URL'),
    'FIREBASE_PROJECT_ID' => env('FIREBASE_PROJECT_ID'),
    'LOGO1' => env('LOGO1'),
    'LOGO2' => env('LOGO2'),
    'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
    'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
    'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
    'MAIL_HOST' => env('MAIL_HOST'),
    'MAIL_MAILER' => env('MAIL_MAILER'),
    'MAIL_PASSWORD' => env('MAIL_PASSWORD'),
    'MAIL_PORT' => env('MAIL_PORT'),
    'MAIL_USERNAME' => env('MAIL_USERNAME'),
    'ONESIGNAL_APP_ID' => env('ONESIGNAL_APP_ID'),
    'ONESIGNAL_REST_API_KEY' => env('ONESIGNAL_REST_API_KEY'),
    'OTP_ENABLED' => env('OTP_ENABLED'),
    'PAYPAL_LIVE_ID' => env('PAYPAL_LIVE_ID'),
    'PAYPAL_LIVE_SECRET' => env('PAYPAL_LIVE_SECRET'),
    'PAYPAL_SANDBOX_ID' => env('PAYPAL_SANDBOX_ID'),
    'PAYPAL_SANDBOX_SECRET' => env('PAYPAL_SANDBOX_SECRET'),
    'PAYPAL_WEBHOOK_ID' => env('PAYPAL_WEBHOOK_ID'),
    'PREMIUM' => env('PREMIUM'),
    'PREMIUM_CALL_COUNT' => env('PREMIUM_CALL_COUNT'),
    'PREMIUM_PRICE' => env('PREMIUM_PRICE'),
    'PUSH_KEY' => env('PUSH_KEY'),
    'RAZORPAY_API_KEY' => env('RAZORPAY_API_KEY'),
    'RAZORPAY_SECRET_KEY' => env('RAZORPAY_SECRET_KEY'),
    'RAZORPAY_WEBHOOK_SECRET' => env('RAZORPAY_WEBHOOK_SECRET'),
    'SANDBOX' => env('SANDBOX'),
    'SENDGRID_API_KEY' => env('SENDGRID_API_KEY'),
    'STRIPE_SECRET_KEY' => env('STRIPE_SECRET_KEY'),
    'STRIPE_WEBHOOK_SECRET' => env('STRIPE_WEBHOOK_SECRET'),
    'TRAIL' => env('TRAIL'),
    'TRAIL_CALL_COUNT' => env('TRAIL_CALL_COUNT'),
    'TRAIL_DAYS' => env('TRAIL_DAYS'),
    'TRUSTED_PROXIES' => env('TRUSTED_PROXIES'),
];
