<?php

declare(strict_types=1);

namespace Fillindev\Support\Exceptions;

use DomainException;
use Fillindev\Support\Enums\TicketStatus;

class InvalidTicketTransition extends DomainException
{
    public static function status(TicketStatus $from, TicketStatus $to): self
    {
        return new self("Нельзя сменить статус с {$from->value} на {$to->value}.");
    }

    public static function closed(): self
    {
        return new self('Закрытое обращение нельзя дополнить сообщением. Сначала переоткройте его.');
    }
}
