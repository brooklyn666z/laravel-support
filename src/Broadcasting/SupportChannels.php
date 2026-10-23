<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use InvalidArgumentException;

/**
 * Имена private-каналов техподдержки.
 * Авторизацию регистрирует пакет через Gate: роли задаёт политика хоста.
 */
final class SupportChannels
{
    public const QUEUE = 'support.queue';

    public const LIFECYCLE = 'support.lifecycle';

    public const TICKET_PATTERN = 'support.ticket.{id}';

    public const TICKET_STAFF_PATTERN = 'support.ticket.{id}.staff';

    public const USER_PATTERN = 'support.user.{type}.{id}';

    public static function ticket(int $ticketId): string
    {
        return 'support.ticket.'.$ticketId;
    }

    public static function ticketStaff(int $ticketId): string
    {
        return 'support.ticket.'.$ticketId.'.staff';
    }

    public static function user(string $type, int|string $userId): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $type)) {
            throw new InvalidArgumentException('Тип пользователя поддержки содержит недопустимые символы.');
        }

        return 'support.user.'.$type.'.'.$userId;
    }
}
