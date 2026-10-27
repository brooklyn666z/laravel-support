<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Broadcasting\SupportBroadcastReason;
use Fillindev\Support\Broadcasting\SupportMessageBroadcast;
use Fillindev\Support\Broadcasting\SupportTicketBroadcast;
use Fillindev\Support\Broadcasting\SupportTicketReadBroadcast;
use Fillindev\Support\Broadcasting\SupportUnreadBroadcast;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\Services\TicketService;
use Fillindev\Support\Routing\SupportRoutes;
use Fillindev\Support\Tests\Stubs\FakeAttachmentStore;
use Fillindev\Support\Tests\Stubs\FakeNotificationGateway;
use Fillindev\Support\Tests\Stubs\FakeUserResolver;
use Fillindev\Support\Tests\Stubs\HttpActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class SupportBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to run support broadcast tests.');
        }

        parent::setUp();

        Gate::before(fn () => true);
        FakeAttachmentStore::reset();
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
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('broadcasting.connections.null', [
            'driver' => 'null',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('support.bindings', [
            'user_resolver' => FakeUserResolver::class,
            'notification_gateway' => FakeNotificationGateway::class,
            'attachment_store' => FakeAttachmentStore::class,
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware(SubstituteBindings::class)->group(function () {
            SupportRoutes::register();
        });
    }

    public function test_create_broadcasts_ticket_and_opening_message(): void
    {
        Event::fake([
            SupportMessageBroadcast::class,
            SupportTicketBroadcast::class,
            SupportTicketReadBroadcast::class,
        ]);

        $response = $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Не открывается модуль',
                'body' => 'Текст первого сообщения',
            ]);

        $response->assertCreated();
        $ticketId = (int) $response->json('data.id');

        Event::assertDispatched(SupportTicketBroadcast::class, function (SupportTicketBroadcast $event) use ($ticketId): bool {
            return $event->reason === SupportBroadcastReason::Created
                && (int) $event->ticket['id'] === $ticketId
                && $event->ticket['subject'] === 'Не открывается модуль'
                && $event->ticket['body'] === 'Текст первого сообщения'
                && ! array_key_exists('messages', $event->ticket)
                && $event->ticket['requester_type'] === 'user'
                && $event->broadcastAs() === 'support.ticket.updated'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.lifecycle',
                    'private-support.user.user.7',
                ];
        });

        Event::assertDispatched(SupportMessageBroadcast::class, function (SupportMessageBroadcast $event) use ($ticketId): bool {
            return $event->internal === false
                && $event->ticketId === $ticketId
                && $event->message['body'] === 'Текст первого сообщения'
                && $event->message['attachments'] === []
                && $event->message['author']['id'] === 7
                && $event->message['author']['type'] === 'user'
                && $event->broadcastAs() === 'support.message.posted'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.ticket.'.$ticketId,
                    'private-support.user.user.7',
                    'private-support.queue',
                ];
        });
    }

    public function test_public_reply_includes_attachments(): void
    {
        Event::fake([
            SupportMessageBroadcast::class,
            SupportTicketBroadcast::class,
            SupportTicketReadBroadcast::class,
        ]);

        $ticketId = (int) $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Вложение',
                'body' => 'Первое',
            ])
            ->json('data.id');

        Event::fake([
            SupportMessageBroadcast::class,
            SupportTicketBroadcast::class,
            SupportTicketReadBroadcast::class,
        ]);

        $this->actingAs(new HttpActor(7))->post('/support/tickets/'.$ticketId.'/messages', [
            'body' => 'Смотрите файл',
            'attachments' => [
                UploadedFile::fake()->create('note.txt', 12, 'text/plain'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        Event::assertDispatched(SupportMessageBroadcast::class, function (SupportMessageBroadcast $event) use ($ticketId): bool {
            return $event->message['body'] === 'Смотрите файл'
                && $event->message['attachments'][0]['file_name'] === 'note.txt'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.ticket.'.$ticketId,
                    'private-support.user.user.7',
                    'private-support.queue',
                ];
        });
    }

    public function test_internal_note_does_not_reach_the_client_channels(): void
    {
        Event::fake([
            SupportMessageBroadcast::class,
            SupportTicketBroadcast::class,
            SupportTicketReadBroadcast::class,
        ]);

        $ticketId = (int) $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Заметка',
                'body' => 'Первое',
            ])
            ->json('data.id');

        Event::fake([
            SupportMessageBroadcast::class,
        ]);

        $this->actingAs(new HttpActor(3))
            ->postJson('/support/admin/tickets/'.$ticketId.'/messages', [
                'body' => 'Только операторам',
                'is_internal' => true,
            ])
            ->assertCreated();

        Event::assertDispatched(SupportMessageBroadcast::class, function (SupportMessageBroadcast $event) use ($ticketId): bool {
            return $event->internal === true
                && $event->message['body'] === 'Только операторам'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.ticket.'.$ticketId.'.staff',
                    'private-support.queue',
                ];
        });
    }

    public function test_show_broadcasts_read_once(): void
    {
        Event::fake([
            SupportMessageBroadcast::class,
            SupportTicketBroadcast::class,
            SupportTicketReadBroadcast::class,
        ]);

        $ticketId = (int) $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Прочтение',
                'body' => 'Текст',
            ])
            ->json('data.id');

        Event::fake([
            SupportTicketReadBroadcast::class,
        ]);

        $this->actingAs(new HttpActor(7))
            ->getJson('/support/tickets/'.$ticketId)
            ->assertOk();

        Event::assertDispatchedTimes(SupportTicketReadBroadcast::class, 1);
        Event::assertDispatched(SupportTicketReadBroadcast::class, function (SupportTicketReadBroadcast $event) use ($ticketId): bool {
            return $event->by === 'requester'
                && $event->ticketId === $ticketId
                && $event->requesterLastReadAt !== null
                && $event->broadcastAs() === 'support.ticket.read';
        });

        $this->actingAs(new HttpActor(7))
            ->getJson('/support/tickets/'.$ticketId)
            ->assertOk();

        Event::assertDispatchedTimes(SupportTicketReadBroadcast::class, 1);
    }

    public function test_priority_change_broadcasts_ticket_update(): void
    {
        Event::fake([
            SupportTicketBroadcast::class,
            SupportMessageBroadcast::class,
        ]);

        $ticketId = (int) $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Приоритет',
                'body' => 'Текст',
            ])
            ->json('data.id');

        Event::fake([
            SupportTicketBroadcast::class,
        ]);

        $this->actingAs(new HttpActor(3))
            ->postJson('/support/admin/tickets/'.$ticketId.'/priority', [
                'priority' => 'urgent',
            ])
            ->assertOk();

        Event::assertDispatched(SupportTicketBroadcast::class, function (SupportTicketBroadcast $event) use ($ticketId): bool {
            return $event->reason === SupportBroadcastReason::Priority
                && $event->ticket['priority'] === 'urgent'
                && $event->broadcastWith()['reason'] === 'priority'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.lifecycle',
                    'private-support.ticket.'.$ticketId,
                    'private-support.user.user.7',
                    'private-support.queue',
                ];
        });
    }

    public function test_assign_broadcasts_ticket_update_on_lifecycle(): void
    {
        Event::fake([
            SupportTicketBroadcast::class,
            SupportMessageBroadcast::class,
        ]);

        $ticketId = (int) $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Назначение',
                'body' => 'Текст',
            ])
            ->json('data.id');

        Event::fake([
            SupportTicketBroadcast::class,
        ]);

        $this->actingAs(new HttpActor(3))
            ->postJson('/support/admin/tickets/'.$ticketId.'/assign', [
                'assignee_id' => 3,
            ])
            ->assertOk();

        Event::assertDispatched(SupportTicketBroadcast::class, function (SupportTicketBroadcast $event) use ($ticketId): bool {
            return $event->reason === SupportBroadcastReason::Assigned
                && (int) $event->ticket['id'] === $ticketId
                && (int) $event->ticket['assignee_id'] === 3
                && $event->broadcastWith()['reason'] === 'assigned'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.lifecycle',
                    'private-support.ticket.'.$ticketId,
                    'private-support.user.user.7',
                    'private-support.queue',
                ];
        });
    }

    public function test_status_change_goes_to_lifecycle_channel(): void
    {
        Event::fake([
            SupportTicketBroadcast::class,
            SupportMessageBroadcast::class,
        ]);

        $ticketId = (int) $this->actingAs(new HttpActor(id: 4, type: 'agent'))
            ->postJson('/support/tickets', [
                'subject' => 'Статус',
                'body' => 'Текст',
            ])
            ->json('data.id');

        Event::fake([
            SupportTicketBroadcast::class,
        ]);

        $this->actingAs(new HttpActor(3))
            ->postJson('/support/admin/tickets/'.$ticketId.'/status', [
                'status' => 'in_progress',
            ])
            ->assertOk();

        Event::assertDispatched(SupportTicketBroadcast::class, function (SupportTicketBroadcast $event) use ($ticketId): bool {
            return $event->reason === SupportBroadcastReason::Status
                && (int) $event->ticket['id'] === $ticketId
                && $event->ticket['requester_type'] === 'agent'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.lifecycle',
                    'private-support.user.agent.4',
                ];
        });
    }

    public function test_read_message_broadcasts_read_and_unread_counts(): void
    {
        Event::fake([
            SupportMessageBroadcast::class,
            SupportTicketBroadcast::class,
            SupportTicketReadBroadcast::class,
            SupportUnreadBroadcast::class,
        ]);

        $client = new HttpActor(7);
        $operator = new HttpActor(3, 'Оператор', 'operator@example.com');
        $tickets = $this->app->make(TicketService::class);
        $messages = $this->app->make(MessageService::class);

        $ticket = $tickets->create($client, 'Прочтение', 'Моё сообщение');
        $reply = $messages->post($ticket, $operator, 'Ответ');

        $this->assertNotNull($ticket->refresh()->requester_last_message_at);
        $this->assertNotNull($ticket->agent_last_message_at);

        Event::fake([
            SupportTicketReadBroadcast::class,
            SupportUnreadBroadcast::class,
        ]);

        $this->actingAs($client)
            ->postJson('/support/messages/'.$reply->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.message_id', $reply->id)
            ->assertJsonPath('data.by', 'requester');

        Event::assertDispatched(SupportTicketReadBroadcast::class, function (SupportTicketReadBroadcast $event) use ($ticket, $reply): bool {
            return $event->messageId === $reply->id
                && $event->by === 'requester'
                && $event->ticketId === (int) $ticket->id
                && $event->broadcastAs() === 'support.ticket.read'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.ticket.'.$ticket->id,
                    'private-support.user.user.7',
                    'private-support.queue',
                ];
        });

        Event::assertDispatched(SupportUnreadBroadcast::class, function (SupportUnreadBroadcast $event) use ($ticket): bool {
            return $event->ticketId === (int) $ticket->id
                && $event->requester === ['messages' => 0, 'tickets' => 0]
                && $event->broadcastAs() === 'support.unread.updated'
                && $this->channelNames($event->broadcastOn()) === [
                    'private-support.ticket.'.$ticket->id,
                    'private-support.user.user.7',
                    'private-support.queue',
                ];
        });

        $openingId = (int) $ticket->messages()->oldest('id')->value('id');

        $this->actingAs($operator)
            ->postJson('/support/messages/'.$openingId.'/read')
            ->assertOk()
            ->assertJsonPath('data.by', 'agent');
    }

    /**
     * @param  list<\Illuminate\Broadcasting\PrivateChannel>  $channels
     * @return list<string>
     */
    private function channelNames(array $channels): array
    {
        return array_map(fn ($channel) => $channel->name, $channels);
    }
}
