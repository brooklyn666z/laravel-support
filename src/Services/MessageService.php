<?php

declare(strict_types=1);

namespace Fillindev\Support\Services;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Events\MessagePosted;
use Fillindev\Support\Exceptions\InvalidTicketTransition;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Fillindev\Support\SupportIds;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MessageService
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function post(
        Ticket $ticket,
        SupportableUser $author,
        string $body,
        bool $internal = false,
    ): TicketMessage {
        if ($ticket->status === TicketStatus::Closed) {
            throw InvalidTicketTransition::closed();
        }

        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Сообщение не может быть пустым.');
        }

        return DB::transaction(function () use ($ticket, $author, $body, $internal): TicketMessage {
            $message = $ticket->messages()->create([
                'author_id' => SupportIds::toInt($author->getSupportUserId()),
                'is_internal' => $internal,
                'body' => $body,
            ]);

            if (! $internal) {
                $ticket->update(['last_replied_at' => now()]);
            }

            $this->events->dispatch(new MessagePosted($ticket, $message, $author));

            return $message->refresh();
        });
    }
}
