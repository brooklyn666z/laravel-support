<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Enums\TicketEventType;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketEvent;
use Fillindev\Support\Routing\SupportRoutes;
use Fillindev\Support\Services\TicketService;
use Fillindev\Support\Tests\Stubs\FakeAttachmentStore;
use Fillindev\Support\Tests\Stubs\FakeNotificationGateway;
use Fillindev\Support\Tests\Stubs\FakeTenant;
use Fillindev\Support\Tests\Stubs\FakeTenantResolver;
use Fillindev\Support\Tests\Stubs\FakeUserResolver;
use Fillindev\Support\Tests\Stubs\HttpActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class SupportHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to run support http tests.');
        }

        parent::setUp();

        Gate::before(fn () => true);
        FakeTenantResolver::$current = null;
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
        $app['config']->set('support.bindings', [
            'user_resolver' => FakeUserResolver::class,
            'tenant_resolver' => FakeTenantResolver::class,
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

    public function test_registered_paths_do_not_include_account(): void
    {
        $uris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn ($uri) => str_contains((string) $uri, 'support'))
            ->unique()
            ->values()
            ->all();

        $expected = [
            'support/categories',
            'support/tickets',
            'support/tickets/{ticket}',
            'support/tickets/{ticket}/messages',
            'support/tickets/{ticket}/close',
            'support/admin/tickets',
            'support/admin/tickets/{ticket}',
            'support/admin/tickets/{ticket}/messages',
            'support/admin/tickets/{ticket}/assign',
            'support/admin/tickets/{ticket}/status',
            'support/admin/tickets/{ticket}/priority',
            'support/admin/tickets/{ticket}/close',
            'support/admin/tickets/{ticket}/reopen',
        ];

        foreach ($expected as $uri) {
            $this->assertContains($uri, $uris);
        }

        foreach ($uris as $uri) {
            $this->assertStringNotContainsString('account', (string) $uri);
        }

        $this->assertNotContains('support/tickets/{ticket}/priority', $uris);
    }

    public function test_create_stores_tenant_id_when_resolver_returns_tenant(): void
    {
        FakeTenantResolver::$current = new FakeTenant(15, 'Север');

        $response = $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Не открывается модуль',
                'body' => 'Текст первого сообщения',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.tenant_id', 15);
        $response->assertJsonPath('data.requester_id', 7);
        $response->assertJsonPath('data.body', 'Текст первого сообщения');

        $this->assertSame(15, Ticket::query()->firstOrFail()->tenant_id);
    }

    public function test_create_without_tenant_when_resolver_returns_null(): void
    {
        FakeTenantResolver::$current = null;

        $response = $this->actingAs(new HttpActor(7))
            ->postJson('/support/tickets', [
                'subject' => 'Без тенанта',
                'body' => 'Сообщение',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.tenant_id', null);
        $this->assertNull(Ticket::query()->firstOrFail()->tenant_id);
    }

    public function test_index_and_show_include_body_and_summary(): void
    {
        FakeTenantResolver::$current = new FakeTenant(15, 'Север');
        $actor = new HttpActor(7);
        $tickets = $this->app->make(TicketService::class);

        $open = $tickets->create($actor, 'Первый', 'Текст открытия', FakeTenantResolver::$current);
        $closed = $tickets->create($actor, 'Второй', 'Уже закрыт', FakeTenantResolver::$current);
        $tickets->close($closed, $actor);

        $other = $tickets->create(new HttpActor(8), 'Чужой', 'Не мой', new FakeTenant(99, 'Другой'));

        $index = $this->actingAs($actor)->getJson('/support/tickets');
        $index->assertOk();
        $index->assertJsonPath('summary', [
            'open' => 1,
            'in_progress' => 0,
            'waiting_customer' => 0,
            'resolved' => 0,
            'closed' => 1,
        ]);

        $bodies = collect($index->json('data'))->pluck('body', 'id');
        $this->assertSame('Текст открытия', $bodies[$open->id]);
        $this->assertSame('Уже закрыт', $bodies[$closed->id]);
        $this->assertArrayNotHasKey($other->id, $bodies->all());
        $this->assertArrayNotHasKey('messages', $index->json('data.0'));

        $show = $this->actingAs($actor)->getJson('/support/tickets/'.$open->id);
        $show->assertOk();
        $show->assertJsonPath('data.body', 'Текст открытия');
        $show->assertJsonPath('data.messages.0.body', 'Текст открытия');
        $show->assertJsonPath('data.messages.0.attachments', []);
        $this->assertIsArray($show->json('data.messages'));
    }

    public function test_admin_summary_ignores_status_filter(): void
    {
        FakeTenantResolver::$current = new FakeTenant(15, 'Север');
        $actor = new HttpActor(7);
        $tickets = $this->app->make(TicketService::class);
        $tickets->create($actor, 'Открытый', 'Открытый текст', FakeTenantResolver::$current);
        $closed = $tickets->create($actor, 'Закрытый', 'Закрытый текст', FakeTenantResolver::$current);
        $tickets->close($closed, $actor);

        $response = $this->actingAs($actor)->getJson('/support/admin/tickets?status=open');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.body', 'Открытый текст');
        $response->assertJsonPath('data.0.status', TicketStatus::Open->value);
        $response->assertJsonPath('summary.closed', 1);
        $response->assertJsonPath('summary.open', 1);
        $response->assertJsonPath('summary.in_progress', 0);
        $response->assertJsonPath('summary.waiting_customer', 0);
        $response->assertJsonPath('summary.resolved', 0);
    }

    public function test_admin_priority_change_writes_ticket_event(): void
    {
        $actor = new HttpActor(7);
        $ticket = $this->app->make(TicketService::class)->create($actor, 'Приоритет', 'Текст');

        $response = $this->actingAs($actor)->postJson(
            '/support/admin/tickets/'.$ticket->id.'/priority',
            ['priority' => 'urgent'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.priority', 'urgent');
        $response->assertJsonPath('data.body', 'Текст');

        $event = TicketEvent::query()->where('ticket_id', $ticket->id)->where('type', TicketEventType::PriorityChanged)->first();
        $this->assertNotNull($event);
        $this->assertSame('normal', $event->old_value);
        $this->assertSame('urgent', $event->new_value);
        $this->assertSame(7, $event->actor_id);
    }

    public function test_message_with_file_returns_attachments_and_author(): void
    {
        FakeTenantResolver::$current = new FakeTenant(15, 'Север');
        $actor = new HttpActor(7);
        $ticket = $this->app->make(TicketService::class)->create(
            $actor,
            'Вложение',
            'Первое',
            FakeTenantResolver::$current,
        );

        $response = $this->actingAs($actor)->post(
            '/support/tickets/'.$ticket->id.'/messages',
            [
                'body' => 'Смотрите файл',
                'attachments' => [
                    UploadedFile::fake()->create('note.txt', 12, 'text/plain'),
                ],
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.body', 'Первое');
        $response->assertJsonPath('data.messages.0.attachments', []);
        $response->assertJsonPath('data.messages.1.body', 'Смотрите файл');
        $response->assertJsonPath('data.messages.1.attachments.0.file_name', 'note.txt');
        $response->assertJsonPath('data.messages.1.attachments.0.mime_type', 'text/plain');
        $response->assertJsonPath('data.messages.1.attachments.0.url', '/storage/note.txt');
        $response->assertJsonPath('data.messages.1.author', [
            'id' => 7,
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'phone_code' => '+7',
            'phone' => '9001234567',
        ]);
    }
}
