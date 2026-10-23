<?php

declare(strict_types=1);

use Fillindev\Support\SupportTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tickets = SupportTables::name('tickets');
        $messages = SupportTables::name('ticket_messages');
        $events = SupportTables::name('ticket_events');

        if (Schema::hasColumn($tickets, 'tenant_id')) {
            Schema::table($tickets, function (Blueprint $table): void {
                $table->dropIndex(['tenant_id', 'status']);
                $table->dropColumn('tenant_id');
            });
        }

        if (! Schema::hasColumn($tickets, 'requester_type')) {
            Schema::table($tickets, function (Blueprint $table): void {
                $table->string('requester_type')->default('user');
                $table->string('assignee_type')->nullable();
                $table->index(['requester_type', 'requester_id']);
            });

            DB::table($tickets)->whereNotNull('assignee_id')->update(['assignee_type' => 'user']);
        }

        if (! Schema::hasColumn($messages, 'author_type')) {
            Schema::table($messages, function (Blueprint $table): void {
                $table->string('author_type')->default('user');
            });
        }

        if (! Schema::hasColumn($events, 'actor_type')) {
            Schema::table($events, function (Blueprint $table): void {
                $table->string('actor_type')->nullable();
            });

            DB::table($events)->whereNotNull('actor_id')->update(['actor_type' => 'user']);
        }
    }

    public function down(): void
    {
        $tickets = SupportTables::name('tickets');
        $messages = SupportTables::name('ticket_messages');
        $events = SupportTables::name('ticket_events');

        if (Schema::hasColumn($events, 'actor_type')) {
            Schema::table($events, function (Blueprint $table): void {
                $table->dropColumn('actor_type');
            });
        }

        if (Schema::hasColumn($messages, 'author_type')) {
            Schema::table($messages, function (Blueprint $table): void {
                $table->dropColumn('author_type');
            });
        }

        if (Schema::hasColumn($tickets, 'requester_type')) {
            Schema::table($tickets, function (Blueprint $table): void {
                $table->dropIndex(['requester_type', 'requester_id']);
                $table->dropColumn(['requester_type', 'assignee_type']);
            });
        }

        if (! Schema::hasColumn($tickets, 'tenant_id')) {
            Schema::table($tickets, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index(['tenant_id', 'status']);
            });
        }
    }
};
