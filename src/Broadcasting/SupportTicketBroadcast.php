<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Жизненный цикл тикета: создание, назначение, статус, приоритет, категория, закрытие, переоткрытие.
 */
class SupportTicketBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function __construct(
        public SupportBroadcastReason $reason,
        public array $ticket,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(SupportChannels::ticket((int) $this->ticket['id'])),
            new PrivateChannel(SupportChannels::user((int) $this->ticket['requester_id'])),
            new PrivateChannel(SupportChannels::QUEUE),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.ticket.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason->value,
            'ticket' => $this->ticket,
        ];
    }
}
