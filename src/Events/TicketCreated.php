<?php

declare(strict_types=1);

namespace Fillindev\Support\Events;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;

final class TicketCreated
{
    public function __construct(
        public Ticket $ticket,
        public TicketMessage $message,
        public SupportableUser $actor,
    ) {}
}
