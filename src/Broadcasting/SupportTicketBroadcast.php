<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Изменение тикета.
 * Создание и смена статуса (включая закрытие и переоткрытие) идут в support.lifecycle.
 * Назначение, приоритет и категория остаются на карточке, у автора и в очереди.
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
        $requester = new PrivateChannel(SupportChannels::user(
            (string) $this->ticket['requester_type'],
            (int) $this->ticket['requester_id'],
        ));

        if ($this->reason->isLifecycle()) {
            return [
                new PrivateChannel(SupportChannels::LIFECYCLE),
                $requester,
            ];
        }

        return [
            new PrivateChannel(SupportChannels::ticket((int) $this->ticket['id'])),
            $requester,
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
