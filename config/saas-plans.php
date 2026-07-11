<?php

return [

    'plans' => [
        'start' => [
            'label' => 'Start',
            'features' => [
                'leads',
                'orders',
                'contractors',
                'tasks',
                'grid_views',
            ],
            'limits' => [
                'users' => 5,
                'orders_per_month' => 200,
                'storage_mb' => 2048,
            ],
        ],
        'pro' => [
            'label' => 'Pro',
            'features' => [
                'leads',
                'orders',
                'contractors',
                'tasks',
                'grid_views',
                'documents',
                'payment_schedules',
                'print',
                'mail',
                'sales_scripts',
                'sales_book',
                'sales_trainer',
                'proposals_html',
                'mcp_read',
            ],
            'limits' => [
                'users' => 25,
                'orders_per_month' => 2000,
                'storage_mb' => 20480,
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'features' => [
                'leads',
                'orders',
                'contractors',
                'tasks',
                'grid_views',
                'documents',
                'payment_schedules',
                'print',
                'mail',
                'sales_scripts',
                'sales_book',
                'sales_trainer',
                'proposals_html',
                'mcp_read',
                'mcp_write',
                'fleet',
                'disposition',
                'management_accounting',
                'load_board',
                'import_cost',
                'integrations',
                'custom_domain',
                'traklo_mobile',
            ],
            'limits' => [
                'users' => null,
                'orders_per_month' => null,
                'storage_mb' => null,
            ],
        ],
    ],

];
