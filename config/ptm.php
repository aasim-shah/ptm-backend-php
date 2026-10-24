<?php

return [
    // When true, a teacher who picks a school gets a pending context that a school admin
    // must approve. When false (the default, matching current behaviour) the teacher is
    // active straight away.
    'teacher_requires_approval' => (bool) env('PTM_TEACHER_REQUIRES_APPROVAL', false),

    // When true (the default), login gives no token until the email is verified with the OTP.
    // Set to false on staging only, to test the app without the OTP screen. Users stay
    // unverified (email_verified_at is never set here), so turning it back on makes them verify.
    'require_email_verification' => (bool) env('AUTH_REQUIRE_EMAIL_VERIFICATION', true),
];
