<?php
return [
    'require_admin_mfa' => env('REQUIRE_ADMIN_MFA', env('APP_ENV') === 'production'),
    'allow_review_skip' => env('REVIEW_ALLOW_MFA_SKIP', false),
    'mfa_lifetime_minutes' => 720,
    'enrollment_minutes' => 10,
];
