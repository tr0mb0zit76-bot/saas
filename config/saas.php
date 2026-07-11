<?php

return [
    'default_tenant_slug' => env('SAAS_DEFAULT_TENANT_SLUG', 'demo'),
    'mobile_app_name' => env('SAAS_MOBILE_APP_NAME', 'Traklo Pro'),

    /**
     * Platform operators (comma-separated emails). Can access /platform/tenants.
     */
    'platform_admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('SAAS_PLATFORM_ADMIN_EMAILS', 'admin@saas.local')),
    ))),

    'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),
];
