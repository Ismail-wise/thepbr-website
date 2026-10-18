<?php

return [
    // Per-request processing bounds, not account, Workspace or storage quotas.
    'upload_kb' => (int) env('PBR_KNOWLEDGE_UPLOAD_KB', 10240),
    'text_chars' => 60000,
    'chunk_chars' => 1800,
    'chunk_overlap' => 200,
    'retrieval_chunks' => 12,
];
