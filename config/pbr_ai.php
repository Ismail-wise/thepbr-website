<?php

return [
    'base_url' => env('PBR_AI_BASE_URL', 'http://127.0.0.1:3107'),
    'internal_secret' => env('PBR_AI_INTERNAL_SECRET'),
    'timeout' => (int) env('PBR_AI_TIMEOUT', 180),
    'connect_timeout' => (int) env('PBR_AI_CONNECT_TIMEOUT', 5),
    'history_messages' => (int) env('PBR_AI_HISTORY_MESSAGES', 20),
    'max_context_chars' => (int) env('PBR_AI_MAX_CONTEXT_CHARS', 60000),
];
