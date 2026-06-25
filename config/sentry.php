<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('APP_ENV', 'production'),

    'send_default_pii' => false,

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ],

    'ignore_transactions' => [
        '/up',
        '/health',
    ],

    'breadcrumbs' => [
        'logs'             => true,
        'cache'            => true,
        'sql_queries'      => true,
        'sql_bindings'     => false,
        'queue_info'       => true,
        'command_info'     => true,
    ],

    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs'             => true,
        'sql_queries'            => true,
        'http_client_requests'   => true,
        'requests'               => true,
    ],
];
