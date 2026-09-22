<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Префикс таблиц
    |--------------------------------------------------------------------------
    |
    | Все таблицы пакета получают этот префикс, чтобы не пересекаться
    | с таблицами приложения-хоста. Итоговое имя: {prefix}{table}.
    |
    */
    'table_prefix' => env('SUPPORT_TABLE_PREFIX', 'support_'),

    /*
    |--------------------------------------------------------------------------
    | Имена таблиц (без префикса)
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'tickets' => 'tickets',
        'ticket_messages' => 'ticket_messages',
        'ticket_categories' => 'ticket_categories',
        'ticket_events' => 'ticket_events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bindings хоста
    |--------------------------------------------------------------------------
    |
    | FQCN реализаций контрактов в приложении-хосте.
    | null — биндинг не регистрируется, пакет остаётся переносимым без хоста.
    |
    | Контракты:
    | - user_resolver         → Fillindev\Support\Contracts\SupportUserResolver
    | - tenant_resolver       → Fillindev\Support\Contracts\SupportTenantResolver
    | - notification_gateway  → Fillindev\Support\Contracts\SupportNotificationGateway
    |
    */
    'bindings' => [
        'user_resolver' => null,
        'tenant_resolver' => null,
        'notification_gateway' => null,
    ],

];
