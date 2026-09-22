# laravel-support

Переносимый Composer-пакет технической поддержки для Laravel.

Сценарий: клиент аккаунта пишет операторам **платформы** (не внутренняя ТП аккаунта). Пакет не зависит от моделей хоста (`User`, `Account` и т.п.) — только logical id без FK.

## Установка

### Path-репозиторий (локальная разработка)

В `composer.json` приложения-хоста:

```json
{
    "require": {
        "fillindev/laravel-support": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../laravel_support",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Затем:

```bash
composer require fillindev/laravel-support:*@dev
```

Опубликовать конфиг:

```bash
php artisan vendor:publish --tag=support-config
```

Провайдер регистрируется через package auto-discovery.

### VCS-репозиторий

Репозиторий пакета: [`brooklyn666z/laravel-support`](https://github.com/brooklyn666z/laravel-support).

```json
{
    "require": {
        "fillindev/laravel-support": "^1.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:brooklyn666z/laravel-support.git"
        }
    ]
}
```

## Что в core пакета

- Модели: Ticket, TicketMessage, TicketCategory, TicketEvent
- Сервисы: `TicketService`, `MessageService` и доменные события (история пишется слушателем пакета)
- PHP enums: статус и приоритет тикета
- Контракты: `SupportableUser`, `SupportTenant`, `SupportUserResolver`, `SupportTenantResolver`, `SupportNotificationGateway`
- Уведомления хоста подписываются на доменные события и вызывают `SupportNotificationGateway`

Internal note — флаг `TicketMessage.is_internal`, не отдельная сущность.

## Контракты хоста

Реализации задаются в `config/support.php` → `bindings` (FQCN). Пока значение `null`, контейнер не создаёт резолвер и явно сообщает, какой ключ не настроен.

```php
'bindings' => [
    'user_resolver' => \App\Support\UserResolver::class,
    'tenant_resolver' => \App\Support\TenantResolver::class,
    'notification_gateway' => \App\Support\NotificationGateway::class,
],
```

## Что остаётся в хосте

- Реализации контрактов (резолверы пользователя/тенанта)
- `SupportNotificationGateway` (почта, Telegram, VK и любые каналы)
- Вложения (morph / media library приложения)
- HTTP API, политики, UI
- Spatie Permission, Nutgram, VK SDK и прочие интеграции

## Миграции

Подключаются автоматически через `SupportServiceProvider`. Справочник категорий:

```bash
php artisan db:seed --class="Fillindev\\Support\\Database\\Seeders\\TicketCategorySeeder"
```

## Требования

- PHP ^8.3
- Laravel 13 (`illuminate/support` ^13.0)
