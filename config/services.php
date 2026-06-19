<?php

return [


    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shopify' => [
        'key'         => env('SHOPIFY_API_KEY'),
        'secret'      => env('SHOPIFY_API_SECRET'),
        'scopes'      => env('SHOPIFY_SCOPES', 'read_orders,read_products,read_customers,read_inventory,write_orders,read_fulfillments'),
        'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),
    ],

    'woo' => [
        'app_name' => env('WOO_APP_NAME', 'heyd2c D2C Ops'),
        'scope'    => env('WOO_SCOPE', 'read_write'),
    ],

    'do_ai' => [
        'base_url'    => env('DO_AI_BASE_URL', 'https://inference.do-ai.run/v1'),
        'light_key'   => env('DO_AI_LIGHT_KEY'),
        'light_model'  => env('DO_AI_LIGHT_MODEL', 'deepseek-ai/DeepSeek-V4-Flash'),
        'heavy_key'   => env('DO_AI_HEAVY_KEY'),
        'heavy_model'  => env('DO_AI_HEAVY_MODEL', 'nvidia/Nemotron-3-Super-120B'),
        'vision_key'    => env('DO_AI_VISION_KEY'),
        'vision_model'  => env('DO_AI_VISION_MODEL', 'nemotron-nano-12b-v2-vl'),
    ],

    'bunny' => [
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'api_key'      => env('BUNNY_API_KEY'),
        'cdn_url'      => env('BUNNY_CDN_URL'),
        'region'       => env('BUNNY_REGION', ''),  // sg, ny, la, syd, br, jh, se — empty for Falkenstein
    ],

    'meta' => [
        'app_id'     => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'scopes'     => env('META_SCOPES', 'ads_read,ads_management,read_insights'),
    ],

    'google_ads' => [
        'client_id'       => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret'   => env('GOOGLE_ADS_CLIENT_SECRET'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    ],

    'msg91' => [
        'auth_key'        => env('MSG91_AUTH_KEY'),
        'otp_template_id' => env('MSG91_OTP_TEMPLATE_ID'),
    ],
    

];
