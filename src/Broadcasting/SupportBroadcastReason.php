<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

enum SupportBroadcastReason: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Status = 'status';
    case Priority = 'priority';
    case Category = 'category';
    case Closed = 'closed';
    case Reopened = 'reopened';

    /**
     * Создание и любая смена статуса, включая закрытие и переоткрытие.
     * Эти причины идут только в support.lifecycle и личный канал автора.
     */
    public function isLifecycle(): bool
    {
        return match ($this) {
            self::Created, self::Status, self::Closed, self::Reopened => true,
            default => false,
        };
    }

    /**
     * Приоритет и назначение оператора дополнительно приходят в support.lifecycle.
     */
    public function alsoOnLifecycle(): bool
    {
        return match ($this) {
            self::Priority, self::Assigned => true,
            default => false,
        };
    }
}
