<?php

/**
 * ADR-022 / ADR-026: allowlisted integration adapters + cutover templates.
 * Secrets never live here — only non-secret endpoints/mapping schema / catalog meta.
 */
return [

    'adapters' => [
        'one_c_bp' => [
            'label' => '1С (стандартная конфигурация)',
            'description' => 'OData-публикация типовой ИБ: контрагенты, реализации, банк',
            'vendor' => '1C',
            'category' => 'accounting',
            'tenant_self_serve' => true,
            'runtime_status' => 'stub',
            'auth_model' => 'basic_odata',
            'docs_open' => true,
            'docs_url' => null,
            'catalog_slug' => '1c_standard',
        ],
        't_bank' => [
            'label' => 'Т‑Банк (T-API)',
            'description' => 'Счета и выписки через публичный T-API (Bearer token)',
            'vendor' => 'T-Bank',
            'category' => 'bank',
            'tenant_self_serve' => true,
            'runtime_status' => 'stub',
            'auth_model' => 'bearer_token',
            'docs_open' => true,
            'docs_url' => 'https://developer.tbank.ru',
            'catalog_slug' => 't_bank',
        ],
        'tochka' => [
            'label' => 'Точка',
            'description' => 'Счета и выписки через Точка.API (OAuth2 / JWT, есть песочница)',
            'vendor' => 'Tochka',
            'category' => 'bank',
            'tenant_self_serve' => true,
            'runtime_status' => 'stub',
            'auth_model' => 'oauth2_jwt',
            'docs_open' => true,
            'docs_url' => 'https://developers.tochka.com',
            'catalog_slug' => 'tochka',
        ],
        'sber' => [
            'label' => 'Сбер API',
            'description' => 'Выписки и счета через Sber API (OAuth2)',
            'vendor' => 'Sber',
            'category' => 'bank',
            'tenant_self_serve' => true,
            'runtime_status' => 'stub',
            'auth_model' => 'oauth2',
            'docs_open' => true,
            'docs_url' => 'https://developers.sber.ru',
            'catalog_slug' => 'sber',
        ],
        'alfa' => [
            'label' => 'Альфа API',
            'description' => 'Выписки ЮЛ через Alfa API (Bearer / ApiKey)',
            'vendor' => 'Alfa-Bank',
            'category' => 'bank',
            'tenant_self_serve' => true,
            'runtime_status' => 'stub',
            'auth_model' => 'oauth2_or_apikey',
            'docs_open' => true,
            'docs_url' => 'https://developers.alfabank.ru',
            'catalog_slug' => 'alfa',
        ],
        'vtb_bank' => [
            'label' => 'ВТБ ИБК (Host-to-Host)',
            'description' => 'Интеграционный Банк-Клиент: SOAP + ГОСТ TLS + CryptoPro УНЭП (спеки после подключения ИБК)',
            'vendor' => 'VTB',
            'category' => 'bank',
            'tenant_self_serve' => true,
            'runtime_status' => 'stub',
            'auth_model' => 'ibk_soap_gost',
            'docs_open' => false,
            'docs_url' => 'https://www.vtb.ru/malyj-biznes/otkryt-schet/distancionnoe-obsluzhivanie/integracionniy-bank-klient/',
            'catalog_slug' => 'vtb_bank',
            'onboarding_checklist' => [
                'settlement_account_opened',
                'dbo_vtb_business_access',
                'ibk_service_enabled',
                'cryptopro_unep_cert',
                'gost_tls_ready',
            ],
        ],
    ],

    /**
     * AA → Orientum clone template (schema + public publication endpoints).
     * Credentials are filled at clone time from env into encrypted tenant secrets.
     * Ops-only — not exposed in tenant self-serve catalog (ADR-026).
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
