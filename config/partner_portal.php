<?php

return [
    // Enable only after migrations and the private partner-ai health check pass.
    'enabled' => (bool) env('PBR_PARTNER_PORTAL_ENABLED', false),
];
