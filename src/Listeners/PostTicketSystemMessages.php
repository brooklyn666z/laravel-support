<?php

declare(strict_types=1);

namespace Fillindev\Support\Listeners;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Events\TicketAssigned;
use Fillindev\Support\Events\TicketClosed;
use Fillindev\Support\Events\TicketOperatorJoined;
use Fillindev\Support\Events\TicketReopened;
use Fillindev\Support\Events\TicketStatusChanged;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\SystemMessageBody;
use Illuminate\Contracts\Events\Dispatcher;

class PostTicketSystemMessages
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly SupportUserResolver $users,
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TicketAssigned::class => 'assigned',
            TicketOperatorJoined::class => 'operatorJoined',
            TicketStatusChanged::class => 'statusChanged',
            TicketClosed::class => 'closed',
            TicketReopened::class => 'reopened',
        ];
    }

    public function assigned(TicketAssigned $event): void
    {
        $this->messages->postSystem(
            $event->ticket,
            $event->actor,
            SystemMessageBody::assigned($this->operatorName(
                (string) $event->ticket->assignee_type,
                $event->ticket->assignee_id,
            )),
        );
    }

    public function operatorJoined(TicketOperatorJoined $event): void
    {
        $this->messages->postSystem(
            $event->ticket,
            $event->actor,
            SystemMessageBody::operatorJoined($event->operator->getSupportDisplayName()),
        );
    }

    public function statusChanged(TicketStatusChanged $event): void
    {
        $body = SystemMessageBody::status($event->to);

        if ($body === null) {
            return;
        }

        $this->messages->postSystem($event->ticket, $event->actor, $body);
    }

    public function closed(TicketClosed $event): void
    {
        $this->messages->postSystem($event->ticket, $event->actor, SystemMessageBody::closed());
    }

    public function reopened(TicketReopened $event): void
    {
        $this->messages->postSystem($event->ticket, $event->actor, SystemMessageBody::reopened());
    }

    private function operatorName(?string $type, mixed $id): string
    {
        if ($type === null || $type === '' || $id === null) {
            return 'оператор';
        }

        $user = $this->users->resolve($type, (int) $id);

        if ($user instanceof SupportableUser) {
            return $user->getSupportDisplayName();
        }

        return 'оператор';
    }
}
