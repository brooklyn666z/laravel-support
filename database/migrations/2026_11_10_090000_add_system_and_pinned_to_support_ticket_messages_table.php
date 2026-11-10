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
        Schema::table(SupportTables::name('ticket_messages'), function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_internal');
            $table->boolean('is_pinned')->default(false)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table(SupportTables::name('ticket_messages'), function (Blueprint $table) {
            $table->dropColumn(['is_system', 'is_pinned']);
        });
    }
};
