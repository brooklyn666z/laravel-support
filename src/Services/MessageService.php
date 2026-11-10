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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MessageService
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly TicketService $tickets,
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
            if (! $internal && ! $this->authorIsRequester($ticket, $author)) {
                $this->tickets->attachOperator($ticket, $author, $author);
            }

            $message = $ticket->messages()->create([
                'author_type' => $author->getSupportUserType(),
                'author_id' => SupportIds::toInt($author->getSupportUserId()),
                'is_internal' => $internal,
                'is_system' => false,
                'is_pinned' => false,
                'body' => $body,
            ]);

            if (! $internal) {
                $ticket->update(['last_replied_at' => now()]);
            }

            $ticket->rememberPartyMessageAt($message);

            $this->events->dispatch(new MessagePosted($ticket, $message, $author));

            return $message->refresh();
        });
    }

    /**
     * Системная запись в ленте: клиент её видит, непрочитанные и last_replied_at не двигает.
     * Можно писать и в закрытое обращение — так фиксируются закрытие и переоткрытие.
     */
    public function postSystem(Ticket $ticket, SupportableUser $actor, string $body): TicketMessage
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Сообщение не может быть пустым.');
        }

        $message = $ticket->messages()->create([
            'author_type' => $actor->getSupportUserType(),
            'author_id' => SupportIds::toInt($actor->getSupportUserId()),
            'is_internal' => false,
            'is_system' => true,
            'is_pinned' => false,
            'body' => $body,
        ]);

        $this->events->dispatch(new MessagePosted($ticket->refresh(), $message, $actor));

        return $message->refresh();
    }

    public function setPinned(TicketMessage $message, bool $pinned): TicketMessage
    {
        if ((bool) $message->is_pinned === $pinned) {
            return $message;
        }

        $message->update(['is_pinned' => $pinned]);

        return $message->refresh();
    }

    /**
     * Окно ленты вокруг якоря, как в чатах agents: prev — старше, next — новее.
     * Якорь попадает в выборку только если prev и next оба true.
     *
     * @return Collection<int, TicketMessage>
     */
    public function around(
        TicketMessage $message,
        int $perPage = 20,
        bool $prev = true,
        bool $next = true,
        bool $publicOnly = true,
    ): Collection {
        $perPage = max(1, $perPage);
        $previous = new Collection;
        $following = new Collection;

        if ($prev) {
            $previous = $this->threadQuery($message, $publicOnly)
                ->where(function (Builder $query) use ($message): void {
                    $query->where('created_at', '<', $message->created_at)
                        ->orWhere(function (Builder $inner) use ($message): void {
                            $inner->where('created_at', $message->created_at)
                                ->where('id', '<', $message->id);
                        });
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($perPage)
                ->get()
                ->reverse()
                ->values();
        }

        if ($next) {
            $following = $this->threadQuery($message, $publicOnly)
                ->where(function (Builder $query) use ($message): void {
                    $query->where('created_at', '>', $message->created_at)
                        ->orWhere(function (Builder $inner) use ($message): void {
                            $inner->where('created_at', $message->created_at)
                                ->where('id', '>', $message->id);
                        });
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($perPage)
                ->get()
                ->values();
        }

        if ($prev && $next) {
            $previous->push($message);
        }

        return $previous->merge($following)->values();
    }

    /**
     * @return Builder<TicketMessage>
     */
    private function threadQuery(TicketMessage $message, bool $publicOnly): Builder
    {
        return TicketMessage::query()
            ->where('ticket_id', $message->ticket_id)
            ->when($publicOnly, fn (Builder $query) => $query->public());
    }

    private function authorIsRequester(Ticket $ticket, SupportableUser $author): bool
    {
        return $author->getSupportUserType() === (string) $ticket->requester_type
            && SupportIds::toInt($author->getSupportUserId()) === (int) $ticket->requester_id;
    }
}
