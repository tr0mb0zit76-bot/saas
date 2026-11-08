<?php

return [
    /*
    | local | tenant
    |
    | Traklo Pro SaaS uses TenantStorage when TenantContext is set
    | (TENANT_STORAGE_FOR_DOCUMENTS=true). Nextcloud is not used in this repo.
    */
    'driver' => env('DOCUMENT_STORAGE', 'local'),
];
