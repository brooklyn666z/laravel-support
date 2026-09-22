<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

/**
 * Доставка уведомлений хостом.
 * Пакет не знает каналы (почта, Telegram, VK): он только сообщает событие и получателей.
 */
interface SupportNotificationGateway
{
    /**
     * @param  list<SupportableUser>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function notify(string $event, array $recipients, array $context = []): void;
}
