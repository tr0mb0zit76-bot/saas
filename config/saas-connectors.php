<?php

/**
 * ADR-022: allowlisted integration adapters + cutover templates.
 * Secrets never live here — only non-secret endpoints/mapping schema.
 */
return [

    'adapters' => [
        'one_c_bp' => [
            'label' => '1С БП (OData)',
            'description' => 'Публикации ИБ, номенклатура услуг, банк/ЭПД mapping',
        ],
    ],

    /**
     * AA → Orientum clone template (schema + public publication endpoints).
     * Credentials are filled at clone time from env into encrypted tenant secrets.
     */
    'templates' => [
        'aa_one_c_bp' => [
            'adapter_key' => 'one_c_bp',
            'endpoints' => [
                'default_publication' => 'autalliance',
                'publications' => [
                    'autalliance' => [
                        'label' => 'Автоальянс-Смоленск',
                        'base_url' => '',
                        'organization_inn' => '6732110940',
                        'date_filter_mode' => 'odata',
                        'enabled' => true,
                        'include_in_sync' => true,
                    ],
                    'gross' => [
                        'label' => 'Гросс',
                        'base_url' => '',
                        'organization_inn' => '6345031755',
                        'date_filter_mode' => 'client',
                        'enabled' => true,
                        'include_in_sync' => true,
                    ],
                    'profsfera' => [
                        'label' => 'Профсфера',
                        'base_url' => '',
                        'organization_inn' => '6321213940',
                        'date_filter_mode' => 'client',
                        'enabled' => true,
                        'include_in_sync' => true,
                    ],
                ],
            ],
            'mapping' => [
                'odata' => [
                    'realization_path' => '/odata/standard.odata/Document_РеализацияТоваровУслуг',
                    'counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
                    'organization_path' => '/odata/standard.odata/Catalog_Организации',
                    'bank_incoming_path' => '/odata/standard.odata/Document_ПоступлениеНаРасчетныйСчет',
                    'bank_outgoing_path' => '/odata/standard.odata/Document_СписаниеСРасчетногоСчета',
                ],
                'service_content_template' => 'Транспортные услуги по заказу {order_number}',
            ],
            'secret_env_keys' => [
                'username' => 'ONE_C_USERNAME',
                'password' => 'ONE_C_PASSWORD',
            ],
            'endpoint_env_overrides' => [
                'publications.autalliance.base_url' => 'ONE_C_AUTALLIANCE_BASE_URL',
                'publications.gross.base_url' => 'ONE_C_GROSS_BASE_URL',
                'publications.profsfera.base_url' => 'ONE_C_PROFSFERA_BASE_URL',
            ],
        ],
    ],

];
