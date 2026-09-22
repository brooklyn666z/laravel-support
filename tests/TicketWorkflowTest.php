<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Exceptions\InvalidTicketTransition;
use Fillindev\Support\TicketNumberGenerator;
use Fillindev\Support\TicketWorkflow;

class TicketWorkflowTest extends TestCase
{
    public function test_open_ticket_can_be_closed_but_not_reopened_from_open(): void
    {
        TicketWorkflow::assertCan(TicketStatus::Open, TicketStatus::Closed);

        $this->expectException(InvalidTicketTransition::class);

        TicketWorkflow::assertCan(TicketStatus::InProgress, TicketStatus::Open);
    }

    public function test_closed_ticket_can_only_be_reopened(): void
    {
        TicketWorkflow::assertCan(TicketStatus::Closed, TicketStatus::Open);

        $this->expectException(InvalidTicketTransition::class);

        TicketWorkflow::assertCan(TicketStatus::Closed, TicketStatus::InProgress);
    }

    public function test_number_includes_year_and_id(): void
    {
        $this->assertSame('SUP-2026-00042', (new TicketNumberGenerator)->fromId(42, 2026));
    }
}
