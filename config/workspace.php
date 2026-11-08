<?php

return [
    // QA D3 / Document 13 §3: the legacy admin panel stays closed unless explicitly reopened.
    'legacy_admin_enabled' => (bool) env('LEGACY_ADMIN_ENABLED', false),
];
