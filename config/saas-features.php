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
        'print' => ['label' => 'Печать DOCX/PDF', 'group' => 'documents'],
        'mail' => ['label' => 'Почта', 'group' => 'communications'],
        'sales_scripts' => ['label' => 'Скрипты продаж', 'group' => 'sales'],
        'sales_book' => ['label' => 'Книга продаж', 'group' => 'sales'],
        'sales_trainer' => ['label' => 'Тренажёр продаж', 'group' => 'sales'],
        'proposals_html' => ['label' => 'HTML-предложения', 'group' => 'sales'],
        'mcp_read' => ['label' => 'MCP (чтение)', 'group' => 'integrations'],
        'mcp_write' => ['label' => 'MCP (запись)', 'group' => 'integrations'],
        'fleet' => ['label' => 'Автопарк', 'group' => 'logistics'],
        'disposition' => ['label' => 'Диспозиция', 'group' => 'logistics'],
        'load_board' => ['label' => 'Биржа грузов', 'group' => 'logistics'],
        'import_cost' => ['label' => 'Импорт себестоимости', 'group' => 'finance'],
        'management_accounting' => ['label' => 'Управленческий учёт', 'group' => 'finance'],
        'integrations' => ['label' => 'Внешние интеграции', 'group' => 'integrations'],
        'custom_domain' => ['label' => 'Свой домен', 'group' => 'platform'],
        'traklo_mobile' => ['label' => 'Traklo Mobile', 'group' => 'platform'],
    ],

    'groups' => [
        'core' => 'Ядро CRM',
        'documents' => 'Документы и оплаты',
        'communications' => 'Коммуникации',
        'sales' => 'Продажи',
        'logistics' => 'Логистика',
        'finance' => 'Финансы',
        'integrations' => 'Интеграции',
        'platform' => 'Платформа',
    ],

];
