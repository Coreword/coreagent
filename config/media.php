<?php

return [
    // Endpoint paths for third-party video generation vary by account type
    // (direct vendor API vs. a reseller gateway like fal.ai/aimlapi), so these
    // are fully config-driven. Verify base_url/paths against your actual
    // provider account's docs before relying on this in production — see
    // multimedia-ai-setup.md section 3 for background on the vendor landscape.
    'seedance' => [
        'api_key' => env('SEEDANCE_API_KEY'),
        'base_url' => env('SEEDANCE_BASE_URL'),
        'model' => env('SEEDANCE_MODEL', 'seedance-1.0-lite'),
        'submit_path' => env('SEEDANCE_SUBMIT_PATH', '/v1/video/generations'),
        'poll_path' => env('SEEDANCE_POLL_PATH', '/v1/video/generations/{job_id}'),
    ],

    'minimax' => [
        'api_key' => env('MINIMAX_API_KEY'),
        'base_url' => env('MINIMAX_BASE_URL', 'https://api.minimax.io'),
        'model' => env('MINIMAX_MODEL', 'minimax-h3'),
        'submit_path' => env('MINIMAX_SUBMIT_PATH', '/v1/video_generation'),
        'poll_path' => env('MINIMAX_POLL_PATH', '/v1/query/video_generation'),
    ],

    'poll_interval_seconds' => (int) env('VIDEO_POLL_INTERVAL_SECONDS', 15),
    'poll_max_attempts' => (int) env('VIDEO_POLL_MAX_ATTEMPTS', 40),
];
