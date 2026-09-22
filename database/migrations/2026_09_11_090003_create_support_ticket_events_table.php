<?php

declare(strict_types=1);

use Fillindev\Support\SupportTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SupportTables::name('ticket_events'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SupportTables::name('tickets'))
                ->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('type');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SupportTables::name('ticket_events'));
    }
};
