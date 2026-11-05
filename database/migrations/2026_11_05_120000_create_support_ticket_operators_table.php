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
        Schema::create(SupportTables::name('ticket_operators'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SupportTables::name('tickets'))
                ->cascadeOnDelete();
            $table->string('operator_type');
            $table->unsignedBigInteger('operator_id');
            $table->timestamps();

            $table->unique(['ticket_id', 'operator_type', 'operator_id']);
            $table->index(['operator_type', 'operator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SupportTables::name('ticket_operators'));
    }
};
