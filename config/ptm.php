<?php

return [
    // When true, a teacher who picks a school gets a pending context that a school admin
    // must approve. When false (the default, matching current behaviour) the teacher is
    // active straight away.
    'teacher_requires_approval' => (bool) env('PTM_TEACHER_REQUIRES_APPROVAL', false),
];
