<?php

return [
    'base_url' => env('DO_AI_BASE_URL', 'https://inference.do-ai.run/v1'),

    // Light model — cheap, for extraction + categorization
    'light' => [
        'key'   => env('DO_AI_LIGHT_KEY'),
        'model' => env('DO_AI_LIGHT_MODEL', 'deepseek-ai/DeepSeek-V4-Flash'),
    ],

    // Heavy model — smarter, for insights + analysis
    'heavy' => [
        'key'   => env('DO_AI_HEAVY_KEY'),
        'model' => env('DO_AI_HEAVY_MODEL', 'nvidia/Nemotron-3-Super-120B'),
    ],

    // Token limits per task (to control costs)
    'limits' => [
        'invoice_extract'       => 1500,  // max output tokens for invoice parsing
        'bank_categorize_batch' => 800,   // max output for categorizing 50 transactions
        'insights'              => 2000,  // max output for analysis/insights
    ],
];
