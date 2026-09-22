<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Enums\TicketEventType;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketCategory;
use Fillindev\Support\Models\TicketEvent;
use Fillindev\Support\Models\TicketMessage;

class ModelsTest extends TestCase
{
    public function test_models_use_support_tables_and_relations(): void
    {
        $ticket = new Ticket;
        $category = new TicketCategory;

        $this->assertSame('support_tickets', $ticket->getTable());
        $this->assertSame('support_ticket_messages', (new TicketMessage)->getTable());
        $this->assertSame('support_ticket_categories', $category->getTable());
        $this->assertSame('support_ticket_events', (new TicketEvent)->getTable());

        $this->assertInstanceOf(TicketCategory::class, $ticket->category()->getRelated());
        $this->assertInstanceOf(TicketMessage::class, $ticket->messages()->getRelated());
        $this->assertInstanceOf(TicketEvent::class, $ticket->events()->getRelated());
        $this->assertInstanceOf(Ticket::class, $category->tickets()->getRelated());
        $this->assertInstanceOf(Ticket::class, (new TicketMessage)->ticket()->getRelated());
        $this->assertInstanceOf(Ticket::class, (new TicketEvent)->ticket()->getRelated());
    }

    public function test_ticket_casts_status_priority_and_ids(): void
    {
        $ticket = new Ticket([
            'status' => 'closed',
            'priority' => 'high',
            'requester_id' => '4',
            'assignee_id' => null,
        ]);

        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertSame(4, $ticket->requester_id);
        $this->assertNull($ticket->assignee_id);
    }

    public function test_message_and_event_casts(): void
    {
        $message = new TicketMessage([
            'is_internal' => 1,
            'author_id' => '9',
        ]);
        $event = new TicketEvent([
            'type' => 'status_changed',
            'meta' => ['from' => 'open'],
            'actor_id' => null,
        ]);

        $this->assertTrue($message->is_internal);
        $this->assertSame(9, $message->author_id);
        $this->assertSame(TicketEventType::StatusChanged, $event->type);
        $this->assertSame(['from' => 'open'], $event->meta);
        $this->assertNull($event->actor_id);
        $this->assertNull(TicketEvent::UPDATED_AT);
    }
}
