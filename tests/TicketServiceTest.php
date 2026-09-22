<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Enums\TicketEventType;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Exceptions\InvalidTicketTransition;
use Fillindev\Support\Models\TicketCategory;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\Services\TicketService;
use Fillindev\Support\Tests\Stubs\FakeTenant;
use Fillindev\Support\Tests\Stubs\FakeUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to run ticket service tests.');
        }

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    public function test_happy_path_creates_thread_assignment_and_history(): void
    {
        $requester = new FakeUser(7, 'Клиент');
        $agent = new FakeUser(3, 'Оператор');
        $category = TicketCategory::query()->create([
            'code' => 'technical',
            'name' => 'Технический вопрос',
            'sort' => 20,
            'is_active' => true,
        ]);

        $tickets = $this->app->make(TicketService::class);
        $messages = $this->app->make(MessageService::class);

        $ticket = $tickets->create(
            $requester,
            'Не открывается модуль',
            'Первое сообщение',
            new FakeTenant(10, 'Ромашка'),
            $category->id,
            TicketPriority::High,
        );

        $this->assertSame('SUP-'.now()->year.'-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT), $ticket->number);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame(10, $ticket->tenant_id);
        $this->assertSame(7, $ticket->requester_id);
        $this->assertSame(1, $ticket->messages()->public()->count());

        $ticket = $tickets->assign($ticket, $agent, $agent);
        $this->assertSame(3, $ticket->assignee_id);

        $messages->post($ticket, $agent, 'Ответ клиенту');
        $messages->post($ticket, $agent, 'Внутренняя заметка', internal: true);

        $ticket->refresh();
        $this->assertSame(2, $ticket->messages()->public()->count());
        $this->assertSame(3, $ticket->messages()->count());

        $ticket = $tickets->changeStatus($ticket, TicketStatus::InProgress, $agent);
        $ticket = $tickets->close($ticket, $agent);
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        try {
            $messages->post($ticket, $requester, 'После закрытия');
            $this->fail('Закрытый тикет не должен принимать сообщения.');
        } catch (InvalidTicketTransition) {
            $this->assertTrue(true);
        }

        $ticket = $tickets->reopen($ticket, $agent);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->closed_at);

        $types = $ticket->events()->orderBy('id')->get()->map(fn ($event) => $event->type->value)->all();

        $this->assertSame([
            TicketEventType::Created->value,
            TicketEventType::Assigned->value,
            TicketEventType::MessagePosted->value,
            TicketEventType::MessagePosted->value,
            TicketEventType::StatusChanged->value,
            TicketEventType::Closed->value,
            TicketEventType::Reopened->value,
        ], $types);
    }
}
