<?php

declare(strict_types=1);

namespace Fillindev\Support\Events;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Models\Ticket;

final class TicketAssigned
{
    public function __construct(
        public Ticket $ticket,
        public ?int $previousAssigneeId,
        public SupportableUser $actor,
    ) {}
}
