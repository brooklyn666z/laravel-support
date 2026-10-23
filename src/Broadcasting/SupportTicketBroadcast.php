<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Изменение тикета.
 * Создание и смена статуса идут в support.lifecycle и личный канал автора.
 * Смена приоритета и назначение оператора тоже приходят в support.lifecycle и остаются на карточке, у автора и в очереди.
 * Категория — на карточке, у автора и в очереди.
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

        $channels = [
            new PrivateChannel(SupportChannels::ticket((int) $this->ticket['id'])),
            $requester,
            new PrivateChannel(SupportChannels::QUEUE),
        ];

        if ($this->reason->alsoOnLifecycle()) {
            array_unshift($channels, new PrivateChannel(SupportChannels::LIFECYCLE));
        }

        return $channels;
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
