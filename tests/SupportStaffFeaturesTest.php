<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketCategory;
use Fillindev\Support\Routing\SupportRoutes;
use Fillindev\Support\Services\MessageService;
use Fillindev\Support\Services\TicketService;
use Fillindev\Support\Tests\Stubs\FakeAttachmentStore;
use Fillindev\Support\Tests\Stubs\FakeNotificationGateway;
use Fillindev\Support\Tests\Stubs\FakeUserResolver;
use Fillindev\Support\Tests\Stubs\HttpActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class SupportStaffFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to run support http tests.');
        }

        parent::setUp();

        Gate::before(function ($user, string $ability) {
            if ($ability === 'handle') {
                return null;
            }

            return true;
        });
        Gate::define('handle', fn () => true);
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

    public function test_client_tickets_are_sorted_by_last_message(): void
    {
        $actor = new HttpActor(7);
        $tickets = $this->app->make(TicketService::class);
        $messages = $this->app->make(MessageService::class);

        Carbon::setTestNow('2026-11-01 10:00:00');
        $older = $tickets->create($actor, 'Старый', 'Первый');
        Carbon::setTestNow('2026-11-01 11:00:00');
        $newer = $tickets->create($actor, 'Новый', 'Второй');
        Carbon::setTestNow('2026-11-01 12:00:00');
        $messages->post($older, $actor, 'Уточнение');

        $ids = collect($this->actingAs($actor)->getJson('/support/tickets')->json('data'))->pluck('id')->all();

        $this->assertSame([$older->id, $newer->id], $ids);

        Carbon::setTestNow();
    }

    public function test_requester_can_reopen_only_resolved_ticket(): void
    {
        $actor = new HttpActor(7);
        $tickets = $this->app->make(TicketService::class);
        $resolved = $tickets->create($actor, 'Решён', 'Текст');
        $tickets->changeStatus($resolved, TicketStatus::Resolved, $actor);

        $response = $this->actingAs($actor)->postJson('/support/tickets/'.$resolved->id.'/reopen');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'open');
        $this->assertNull($response->json('data.resolved_at'));

        $closed = $tickets->create($actor, 'Закрыт', 'Текст');
        $tickets->close($closed, $actor);
        $this->actingAs($actor)->postJson('/support/tickets/'.$closed->id.'/reopen')->assertStatus(422);

        $open = $tickets->create($actor, 'Открыт', 'Текст');
        $this->actingAs($actor)->postJson('/support/tickets/'.$open->id.'/reopen')->assertStatus(422);
    }

    public function test_several_operators_can_reply_in_one_chat(): void
    {
        $client = new HttpActor(7);
        $first = new HttpActor(3, 'Первый');
        $second = new HttpActor(4, 'Второй');
        $tickets = $this->app->make(TicketService::class);
        $ticket = $tickets->create($client, 'Чат', 'Вопрос');

        $this->actingAs($first)->postJson('/support/admin/tickets/'.$ticket->id.'/messages', [
            'body' => 'Ответ первого',
        ])->assertCreated();
        $this->actingAs($second)->postJson('/support/admin/tickets/'.$ticket->id.'/messages', [
            'body' => 'Ответ второго',
        ])->assertCreated();
        $this->actingAs(new HttpActor(9, 'Заметка'))->postJson('/support/admin/tickets/'.$ticket->id.'/messages', [
            'body' => 'Внутренняя',
            'is_internal' => true,
        ])->assertCreated();

        $card = $this->actingAs($first)->getJson('/support/admin/tickets/'.$ticket->id);
        $card->assertOk();
        $card->assertJsonPath('data.assignee_id', null);
        $this->assertSame([3, 4], collect($card->json('data.operators'))->pluck('id')->all());

        $this->actingAs($first)->postJson('/support/admin/tickets/'.$ticket->id.'/operators', [
            'operator_id' => 5,
        ])->assertOk();

        $this->actingAs($first)->postJson('/support/admin/tickets/'.$ticket->id.'/assign', [
            'assignee_id' => 3,
        ])->assertOk()->assertJsonPath('data.assignee_id', 3);

        $operators = collect($this->actingAs($first)->getJson('/support/admin/tickets/'.$ticket->id)->json('data.operators'))->pluck('id')->all();
        $this->assertSame([3, 4, 5], $operators);
    }

    public function test_admin_index_filters_every_queue_field_and_keeps_status_out_of_summary(): void
    {
        $actor = new HttpActor(7);
        $category = TicketCategory::query()->create([
            'code' => 'technical',
            'name' => 'Техническая',
            'sort' => 1,
            'is_active' => true,
        ]);
        $tickets = $this->app->make(TicketService::class);
        $low = $tickets->create($actor, 'Низкий вопрос', 'Текст', $category->id, TicketPriority::Low);
        $urgent = $tickets->create($actor, 'Срочно доступ', 'Текст', null, TicketPriority::Urgent);
        $tickets->assign($urgent, new HttpActor(4, 'Оператор'), $actor);
        Ticket::query()->whereKey($low->id)->update(['updated_at' => '2026-01-01 00:00:00']);

        $byPriority = $this->actingAs($actor)->getJson('/support/admin/tickets?priority=low');
        $byPriority->assertOk();
        $byPriority->assertJsonCount(1, 'data');
        $byPriority->assertJsonPath('data.0.id', $low->id);
        $byPriority->assertJsonPath('summary.open', 1);

        $bySubject = $this->actingAs($actor)->getJson('/support/admin/tickets?subject='.urlencode('доступ'));
        $bySubject->assertJsonCount(1, 'data');
        $bySubject->assertJsonPath('data.0.id', $urgent->id);
        $bySubject->assertJsonPath('summary.open', 1);

        $byCategory = $this->actingAs($actor)->getJson('/support/admin/tickets?category_id='.$category->id);
        $byCategory->assertJsonPath('data.0.id', $low->id);
        $byCategory->assertJsonPath('summary.open', 1);

        $byAssignee = $this->actingAs($actor)->getJson('/support/admin/tickets?assignee_id=4');
        $byAssignee->assertJsonPath('data.0.id', $urgent->id);

        $byId = $this->actingAs($actor)->getJson('/support/admin/tickets?id='.$urgent->id);
        $byId->assertJsonCount(1, 'data');
        $byId->assertJsonPath('data.0.id', $urgent->id);

        $byUpdated = $this->actingAs($actor)->getJson('/support/admin/tickets?updated_from=2026-10-01');
        $byUpdated->assertJsonCount(1, 'data');
        $byUpdated->assertJsonPath('data.0.id', $urgent->id);
        $byUpdated->assertJsonPath('summary.open', 1);

        $byStatus = $this->actingAs($actor)->getJson('/support/admin/tickets?status=open');
        $byStatus->assertJsonCount(2, 'data');
        $byStatus->assertJsonPath('summary.open', 2);

        $this->actingAs($actor)->getJson('/support/admin/tickets?updated_to=2026-01-01&updated_from=2026-02-01')
            ->assertStatus(422);
    }

    public function test_statistics_covers_all_operators_and_one_operator(): void
    {
        $client = new HttpActor(7);
        $operator = new HttpActor(3, 'Оператор');
        $tickets = $this->app->make(TicketService::class);
        $messages = $this->app->make(MessageService::class);

        Carbon::setTestNow('2026-11-02 12:00:00');
        $solved = $tickets->create($client, 'Решим', 'Вопрос');
        Carbon::setTestNow('2026-11-02 12:30:00');
        $messages->post($solved, $operator, 'Ответ');
        Carbon::setTestNow('2026-11-02 15:00:00');
        $tickets->changeStatus($solved, TicketStatus::Resolved, $operator);
        Carbon::setTestNow('2026-11-01 09:00:00');
        $tickets->create($client, 'Открыт', 'Ещё');
        Carbon::setTestNow();

        $all = $this->actingAs($operator)->getJson('/support/admin/statistics?date_from=2026-11-01&date_to=2026-11-02');
        $all->assertOk();
        $all->assertJsonPath('data.cards.total', 2);
        $all->assertJsonPath('data.cards.open', 1);
        $all->assertJsonPath('data.cards.resolved', 1);
        $all->assertJsonPath('data.cards.solved', 1);
        $all->assertJsonPath('data.cards.closed', 0);
        $all->assertJsonPath('data.cards.solved_share', 50);
        $all->assertJsonPath('data.cards.first_response_minutes', 30);
        $all->assertJsonPath('data.cards.resolution_hours', 3);
        $all->assertJsonPath('data.operator', null);
        $all->assertJsonPath('data.volume.0.date', '2026-11-01');
        $all->assertJsonPath('data.volume.0.created', 1);
        $all->assertJsonPath('data.volume.1.date', '2026-11-02');
        $all->assertJsonPath('data.volume.1.created', 1);
        $all->assertJsonPath('data.volume.1.solved', 1);
        $all->assertJsonPath('data.operators.0.id', 3);
        $all->assertJsonPath('data.operators.0.received', 1);
        $all->assertJsonPath('data.operators.0.solved', 1);

        $one = $this->actingAs($operator)->getJson('/support/admin/statistics?date_from=2026-11-01&date_to=2026-11-02&operator_id=3');
        $one->assertOk();
        $one->assertJsonPath('data.cards.total', 1);
        $one->assertJsonPath('data.cards.solved', 1);
        $one->assertJsonPath('data.operator.id', 3);
        $one->assertJsonPath('data.operators', null);

        $this->actingAs($operator)->getJson('/support/admin/statistics?date_from=2026-11-03&date_to=2026-11-01')
            ->assertStatus(422);
    }
}
