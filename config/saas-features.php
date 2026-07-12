<?php

return [

    /*
    | Каталог модулей Traklo Pro для platform admin и feature gating.
    | Ключи совпадают с middleware feature:* и settings.features tenant.
    */
    'catalog' => [
        'leads' => ['label' => 'Лиды', 'group' => 'core'],
        'orders' => ['label' => 'Заказы', 'group' => 'core'],
        'contractors' => ['label' => 'Контрагенты', 'group' => 'core'],
        'tasks' => ['label' => 'Задачи', 'group' => 'core'],
        'grid_views' => ['label' => 'Сохранённые виды таблиц', 'group' => 'core'],
        'documents' => ['label' => 'Документы', 'group' => 'documents'],
        'payment_schedules' => ['label' => 'График оплат', 'group' => 'documents'],
        'print' => ['label' => 'Печать документов', 'group' => 'documents'],
        'mail' => ['label' => 'Почта', 'group' => 'communications'],
        'sales_scripts' => ['label' => 'Скрипты продаж', 'group' => 'sales'],
        'sales_book' => ['label' => 'Книга продаж', 'group' => 'sales'],
        'sales_trainer' => ['label' => 'Тренажёр продаж', 'group' => 'sales'],
        'proposals_html' => ['label' => 'Коммерческие предложения', 'group' => 'sales'],
        'how_much_fits' => ['label' => 'Сколько влезет', 'group' => 'logistics'],
        'mcp_read' => ['label' => 'Текстовый помощник (чтение)', 'group' => 'integrations'],
        'mcp_write' => ['label' => 'Текстовый помощник (запись)', 'group' => 'integrations'],
        'fleet' => ['label' => 'Автопарк', 'group' => 'logistics'],
        'disposition' => ['label' => 'Диспозиция', 'group' => 'logistics'],
        'load_board' => ['label' => 'Биржа грузов', 'group' => 'logistics'],
        'import_cost' => ['label' => 'Импорт себестоимости', 'group' => 'finance'],
        'management_accounting' => ['label' => 'Управленческий учёт', 'group' => 'finance'],
        'budgeting' => ['label' => 'Бюджетирование', 'group' => 'finance'],
        'company_planning' => ['label' => 'Планирование компании', 'group' => 'finance'],
        'ai_analytics' => ['label' => 'Аналитика ИИ', 'group' => 'analytics'],
        'integrations' => ['label' => 'Внешние интеграции', 'group' => 'integrations'],
        'custom_domain' => ['label' => 'Свой домен', 'group' => 'platform'],
        'traklo_mobile' => ['label' => 'Мобильное приложение', 'group' => 'platform'],
    ],

    'groups' => [
        'core' => 'Ядро кабинета',
        'documents' => 'Документы и оплаты',
        'communications' => 'Коммуникации',
        'sales' => 'Продажи',
        'logistics' => 'Логистика',
        'finance' => 'Финансы и управление',
        'analytics' => 'Аналитика',
        'integrations' => 'Интеграции',
        'platform' => 'Платформа',
    ],

];
