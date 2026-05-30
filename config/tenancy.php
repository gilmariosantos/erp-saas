<?php

declare(strict_types=1);

return [

    'tenant_model' => \App\Models\Tenant::class,

    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => \Stancl\Tenancy\Database\Models\Domain::class,

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    | Domínios que NÃO são tenant (landing page, painel admin).
    */
    'central_domains' => [
        env('CENTRAL_DOMAIN', 'localhost'),
        'www.' . env('CENTRAL_DOMAIN', 'localhost'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy Bootstrappers
    |--------------------------------------------------------------------------
    */
    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'central_connection'    => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null,

        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'suffix' => '',

        'managers' => [
            'mysql'  => \Stancl\Tenancy\Database\Managers\MySQLDatabaseManager::class,
            'sqlite' => \Stancl\Tenancy\Database\Managers\SQLiteDatabaseManager::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'tag_base' => 'tenant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem
    |--------------------------------------------------------------------------
    */
    'filesystem' => [
        'suffix_base'  => 'tenant_',
        'disks'        => ['local', 'public'],
        'root_override' => [],
        'override_disks' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'prefix_base'   => 'tenant_',
        'prefixed_connections' => ['default', 'cache'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        \Stancl\Tenancy\Features\UserImpersonation::class,
        \Stancl\Tenancy\Features\TelescopeTags::class,
        \Stancl\Tenancy\Features\UniversalRoutes::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration parameters
    |--------------------------------------------------------------------------
    */
    'migration_parameters' => [
        '--force'   => true,
        '--path'    => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'TenantDatabaseSeeder',
    ],
];
