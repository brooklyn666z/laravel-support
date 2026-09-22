<?php

declare(strict_types=1);

use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\SupportTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SupportTables::name('tickets'), function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained(SupportTables::name('ticket_categories'))
                ->nullOnDelete();
            $table->string('subject');
            $table->string('status')->default(TicketStatus::Open->value);
            $table->string('priority')->default(TicketPriority::Normal->value);
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index('requester_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SupportTables::name('tickets'));
    }
};
