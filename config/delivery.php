<?php
return [
    'email_enabled' => env('WORKSPACE_EMAIL_ENABLED', false),
    'push_enabled' => env('WORKSPACE_PUSH_ENABLED', false),
    // Nonproduction SMTP/FCM delivery is disabled unless an explicit test recipient
    // or test token hash is configured. In-app notifications always work.
    'test_email' => env('DELIVERY_TEST_EMAIL'),
    'test_token_hash' => env('DELIVERY_TEST_TOKEN_HASH'),
];
