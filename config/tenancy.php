<?php

declare(strict_types=1);

use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager;

return [
    'tenant_model' => \App\Models\Company::class,

    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => null,

    'central_domains' => [],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'pgsql'),
        'template_tenant_connection' => null,
        'prefix' => 'tenant_',
        'suffix' => '',
        'managers' => [
            'pgsql' => PostgreSQLSchemaManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public', 's3'],
        'root_override' => [
            'local'  => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => ['default', 'cache'],
    ],

    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        // FilesystemTenancyBootstrapper EXCLUDED — it rewrites asset URLs for domain-based tenancy, breaks path-based
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'features' => [
        // Add features here as needed (Octane, Telescope, etc.)
    ],

    'migration_parameters' => [
        '--force'    => true,
        '--path'     => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => \Database\Seeders\Tenant\TenantDatabaseSeeder::class,
    ],
];
