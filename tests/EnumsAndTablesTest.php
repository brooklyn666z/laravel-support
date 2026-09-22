<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Enums\TicketEventType;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\SupportTables;

class EnumsAndTablesTest extends TestCase
{
    public function test_table_names_use_configured_prefix(): void
    {
        $this->assertSame('support_tickets', SupportTables::name('tickets'));
        $this->assertSame('support_ticket_messages', SupportTables::name('ticket_messages'));
        $this->assertSame('support_ticket_categories', SupportTables::name('ticket_categories'));
        $this->assertSame('support_ticket_events', SupportTables::name('ticket_events'));
    }

    public function test_default_status_and_priority_values(): void
    {
        $this->assertSame('open', TicketStatus::Open->value);
        $this->assertSame('normal', TicketPriority::Normal->value);
        $this->assertSame('created', TicketEventType::Created->value);
        $this->assertSame('message_posted', TicketEventType::MessagePosted->value);
    }
}
