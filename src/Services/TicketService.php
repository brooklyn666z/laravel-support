<?php

declare(strict_types=1);

namespace Fillindev\Support\Services;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportTenant;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Events\TicketAssigned;
use Fillindev\Support\Events\TicketCategoryChanged;
use Fillindev\Support\Events\TicketClosed;
use Fillindev\Support\Events\TicketCreated;
use Fillindev\Support\Events\TicketPriorityChanged;
use Fillindev\Support\Events\TicketReopened;
use Fillindev\Support\Events\TicketStatusChanged;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketCategory;
use Fillindev\Support\Models\TicketMessage;
use Fillindev\Support\SupportIds;
use Fillindev\Support\TicketNumberGenerator;
use Fillindev\Support\TicketWorkflow;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketService
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly TicketNumberGenerator $numbers,
    ) {}

    public function create(
        SupportableUser $requester,
        string $subject,
        string $body,
        ?SupportTenant $tenant = null,
        ?int $categoryId = null,
        TicketPriority $priority = TicketPriority::Normal,
    ): Ticket {
        $subject = trim($subject);
        $body = trim($body);
        $this->assertFilled($subject, 'Тема');
        $this->assertFilled($body, 'Сообщение');
        $this->assertCategory($categoryId);

        return DB::transaction(function () use ($requester, $subject, $body, $tenant, $categoryId, $priority): Ticket {
            $requesterId = SupportIds::toInt($requester->getSupportUserId());

            $ticket = Ticket::query()->create([
                'number' => 'tmp-'.Str::uuid(),
                'tenant_id' => $tenant === null ? null : SupportIds::toInt($tenant->getSupportTenantId()),
                'requester_id' => $requesterId,
                'category_id' => $categoryId,
                'subject' => $subject,
                'status' => TicketStatus::Open,
                'priority' => $priority,
                'last_replied_at' => now(),
            ]);

            $ticket->update([
                'number' => $this->numbers->fromId($ticket->id),
            ]);

            $message = $ticket->messages()->create([
                'author_id' => $requesterId,
                'is_internal' => false,
                'body' => $body,
            ]);

            $this->events->dispatch(new TicketCreated($ticket, $message, $requester));

            return $ticket->refresh();
        });
    }

    public function assign(Ticket $ticket, SupportableUser $assignee, SupportableUser $actor): Ticket
    {
        $assigneeId = SupportIds::toInt($assignee->getSupportUserId());

        if ($ticket->assignee_id === $assigneeId) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $assigneeId, $actor): Ticket {
            $previousAssigneeId = $ticket->assignee_id;
            $ticket->update(['assignee_id' => $assigneeId]);
            $this->events->dispatch(new TicketAssigned($ticket, $previousAssigneeId, $actor));

            return $ticket->refresh();
        });
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, SupportableUser $actor): Ticket
    {
        if ($ticket->status === $status) {
            return $ticket;
        }

        if ($status === TicketStatus::Closed) {
            return $this->close($ticket, $actor);
        }

        if ($status === TicketStatus::Open && in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            return $this->reopen($ticket, $actor);
        }

        TicketWorkflow::assertCan($ticket->status, $status);

        return DB::transaction(function () use ($ticket, $status, $actor): Ticket {
            $from = $ticket->status;
            $ticket->status = $status;

            if ($status === TicketStatus::Resolved) {
                $ticket->resolved_at = now();
            }

            $ticket->save();
            $this->events->dispatch(new TicketStatusChanged($ticket, $from, $status, $actor));

            return $ticket->refresh();
        });
    }

    public function close(Ticket $ticket, SupportableUser $actor): Ticket
    {
        if ($ticket->status === TicketStatus::Closed) {
            return $ticket;
        }

        TicketWorkflow::assertCan($ticket->status, TicketStatus::Closed);

        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $from = $ticket->status;
            $ticket->status = TicketStatus::Closed;
            $ticket->closed_at = now();
            $ticket->save();
            $this->events->dispatch(new TicketClosed($ticket, $from, $actor));

            return $ticket->refresh();
        });
    }

    public function reopen(Ticket $ticket, SupportableUser $actor): Ticket
    {
        TicketWorkflow::assertCan($ticket->status, TicketStatus::Open);

        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $from = $ticket->status;
            $ticket->status = TicketStatus::Open;
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
            $ticket->save();
            $this->events->dispatch(new TicketReopened($ticket, $from, $actor));

            return $ticket->refresh();
        });
    }

    /**
     * Запоминает время отправки последнего сообщения, которое увидел клиент или оператор.
     */
    public function markRead(Ticket $ticket, bool $byAgent): Ticket
    {
        $latest = $ticket->messages()
            ->when(! $byAgent, fn ($query) => $query->where('is_internal', false))
            ->latest('id')
            ->first();

        if (! $latest instanceof TicketMessage) {
            return $ticket;
        }

        $column = $byAgent ? 'agent_last_read_at' : 'requester_last_read_at';
        $current = $ticket->{$column};

        if ($current !== null && $current->equalTo($latest->created_at)) {
            return $ticket;
        }

        $ticket->update([$column => $latest->created_at]);

        return $ticket->refresh();
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, SupportableUser $actor): Ticket
    {
        if ($ticket->priority === $priority) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $priority, $actor): Ticket {
            $from = $ticket->priority;
            $ticket->update(['priority' => $priority]);
            $this->events->dispatch(new TicketPriorityChanged($ticket, $from, $priority, $actor));

            return $ticket->refresh();
        });
    }

    public function changeCategory(Ticket $ticket, ?int $categoryId, SupportableUser $actor): Ticket
    {
        if ($ticket->category_id === $categoryId) {
            return $ticket;
        }

        $this->assertCategory($categoryId);

        return DB::transaction(function () use ($ticket, $categoryId, $actor): Ticket {
            $fromCategoryId = $ticket->category_id;
            $ticket->update(['category_id' => $categoryId]);
            $this->events->dispatch(new TicketCategoryChanged($ticket, $fromCategoryId, $categoryId, $actor));

            return $ticket->refresh();
        });
    }

    private function assertFilled(string $value, string $label): void
    {
        if ($value === '') {
            throw new InvalidArgumentException($label.' не заполнено.');
        }
    }

    private function assertCategory(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $exists = TicketCategory::query()
            ->whereKey($categoryId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Категория не найдена или отключена.');
        }
    }
}
