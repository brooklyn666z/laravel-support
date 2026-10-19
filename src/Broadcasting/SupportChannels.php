<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

/**
 * Имена private-каналов техподдержки.
 * Авторизацию регистрирует хост: пакет не знает роли и тенант.
 */
final class SupportChannels
{
    public const QUEUE = 'support.queue';

    public const TICKET_PATTERN = 'support.ticket.{id}';

    public const TICKET_STAFF_PATTERN = 'support.ticket.{id}.staff';

    public const USER_PATTERN = 'support.user.{id}';

    public static function ticket(int $ticketId): string
    {
        return 'support.ticket.'.$ticketId;
    }

    public static function ticketStaff(int $ticketId): string
    {
        return 'support.ticket.'.$ticketId.'.staff';
    }

    public static function user(int $userId): string
    {
        return 'support.user.'.$userId;
    }
}
