<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests;

use Fillindev\Support\Database\Seeders\TicketCategorySeeder;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required to run migration tests.');
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

    public function test_support_tables_are_created(): void
    {
        $this->assertTrue(Schema::hasTable('support_ticket_categories'));
        $this->assertTrue(Schema::hasTable('support_tickets'));
        $this->assertTrue(Schema::hasTable('support_ticket_messages'));
        $this->assertTrue(Schema::hasTable('support_ticket_events'));

        $this->assertTrue(Schema::hasColumns('support_tickets', [
            'number',
            'tenant_id',
            'requester_id',
            'assignee_id',
            'category_id',
            'subject',
            'status',
            'priority',
            'last_replied_at',
            'resolved_at',
            'closed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('support_ticket_messages', [
            'ticket_id',
            'author_id',
            'is_internal',
            'body',
        ]));
        $this->assertTrue(Schema::hasColumns('support_ticket_events', [
            'ticket_id',
            'actor_id',
            'type',
            'old_value',
            'new_value',
            'meta',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('support_ticket_events', 'updated_at'));
    }

    public function test_category_seeder_inserts_default_categories(): void
    {
        $this->seed(TicketCategorySeeder::class);

        $categoryId = DB::table('support_ticket_categories')->where('code', 'billing')->value('id');

        $this->assertNotNull($categoryId);
        $this->assertSame(4, DB::table('support_ticket_categories')->count());

        $ticketId = DB::table('support_tickets')->insertGetId([
            'number' => 'SUP-2026-00001',
            'tenant_id' => 5,
            'requester_id' => 7,
            'category_id' => $categoryId,
            'subject' => 'Не открывается модуль',
            'status' => TicketStatus::Open->value,
            'priority' => TicketPriority::Normal->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('support_ticket_messages')->insert([
            'ticket_id' => $ticketId,
            'author_id' => 7,
            'is_internal' => false,
            'body' => 'Текст обращения',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('support_ticket_events')->insert([
            'ticket_id' => $ticketId,
            'actor_id' => 7,
            'type' => 'created',
            'new_value' => TicketStatus::Open->value,
            'created_at' => now(),
        ]);

        $this->assertSame(1, DB::table('support_ticket_messages')->count());
        $this->assertSame(1, DB::table('support_ticket_events')->count());
    }
}
