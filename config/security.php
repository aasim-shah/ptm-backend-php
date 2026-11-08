<?php
return [
    'require_admin_mfa' => env('REQUIRE_ADMIN_MFA', env('APP_ENV') === 'production'),
    // QA B2 / Doc 12 §11: administrators cannot skip two-step sign-in while admin MFA is required.
    // The skip only exists for review environments that do not require admin MFA at all.
    'allow_review_skip' => env('REVIEW_ALLOW_MFA_SKIP', false) && !env('REQUIRE_ADMIN_MFA', env('APP_ENV') === 'production'),
    'mfa_lifetime_minutes' => 720,
    'enrollment_minutes' => 10,
];
