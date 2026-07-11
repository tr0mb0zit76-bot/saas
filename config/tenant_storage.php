<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant file storage (S3-first)
    |--------------------------------------------------------------------------
    |
    | Lab: tenant_local → storage/app/tenants (same key layout as S3).
    | Prod: tenant_s3 → Yandex Object Storage / AWS S3 / MinIO (S3 API).
    |
    | Nextcloud WebDAV is NOT used on Traklo Pro SaaS path (v5 AA legacy only).
    |
    */

    'disk' => env('TENANT_STORAGE_DISK', 'tenant_local'),

    // Route DocumentStorageService through TenantStorage when tenant context is set.
    'use_for_documents' => env('TENANT_STORAGE_FOR_DOCUMENTS', true),

    // Optional bucket-level prefix, e.g. traklo-pro-prod
    'root_prefix' => env('TENANT_STORAGE_ROOT_PREFIX', ''),

    'disks' => [
        'tenant_local' => [
            'driver' => 'local',
            'root' => storage_path('app/tenants'),
            'throw' => true,
        ],
        'tenant_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'ru-central1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => true,
        ],
    ],

];
