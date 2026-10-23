<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Новое сообщение тикета.
 * Публичное уходит клиенту и в очередь. Внутренняя заметка — только операторам.
 */
class SupportMessageBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public int $ticketId,
        public string $requesterType,
        public int $requesterId,
        public bool $internal,
        public array $message,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->internal) {
            return [
                new PrivateChannel(SupportChannels::ticketStaff($this->ticketId)),
                new PrivateChannel(SupportChannels::QUEUE),
            ];
        }

        return [
            new PrivateChannel(SupportChannels::ticket($this->ticketId)),
            new PrivateChannel(SupportChannels::user($this->requesterType, $this->requesterId)),
            new PrivateChannel(SupportChannels::QUEUE),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.message.posted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'message' => $this->message,
        ];
    }
}
