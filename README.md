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
- WebSocket: `SupportMessageBroadcast`, `SupportTicketBroadcast`, `SupportTicketReadBroadcast`. Имена каналов — `Fillindev\Support\Broadcasting\SupportChannels`. Авторизацию каналов регистрирует хост
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

## WebSocket

События уходят в очередь (`ShouldBroadcast`). Нужны `BROADCAST_CONNECTION=pusher` (Soketi) и worker очереди.

| Событие Echo | Когда |
|---|---|
| `.support.message.posted` | Сообщение, уже с вложениями |
| `.support.ticket.updated` | Создание, назначение, статус, приоритет, категория, закрытие, переоткрытие |
| `.support.ticket.read` | Карточку открыли и дочитали |

Каналы (`Echo.private`, префикс `private-` добавляет Echo):

- `support.ticket.{id}` — клиент и операторы, без внутренних заметок
- `support.ticket.{id}.staff` — только операторы, внутренние заметки
- `support.user.{id}` — личный канал автора обращения
- `support.queue` — общая очередь операторов

Внутренняя заметка не попадает в `support.ticket.{id}` и `support.user.{id}`.

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
