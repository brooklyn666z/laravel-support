<?php

declare(strict_types=1);

namespace Fillindev\Support;

use Fillindev\Support\Enums\TicketStatus;

final class SystemMessageBody
{
    public static function assigned(string $operatorName): string
    {
        return 'На обращение назначен оператор '.$operatorName.'.';
    }

    public static function operatorJoined(string $operatorName): string
    {
        return 'Оператор '.$operatorName.' подключился к обращению.';
    }

    public static function status(TicketStatus $status): ?string
    {
        return match ($status) {
            TicketStatus::InProgress => 'Обращение взято в работу.',
            TicketStatus::WaitingCustomer => 'Ожидается ваш ответ.',
            TicketStatus::Resolved => 'Обращение отмечено как решённое.',
            default => null,
        };
    }

    public static function closed(): string
    {
        return 'Обращение закрыто.';
    }

    public static function reopened(): string
    {
        return 'Обращение переоткрыто.';
    }
}
