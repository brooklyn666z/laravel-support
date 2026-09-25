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
        Schema::table(SupportTables::name('tickets'), function (Blueprint $table) {
            $table->timestamp('requester_last_read_at')->nullable()->after('last_replied_at');
            $table->timestamp('agent_last_read_at')->nullable()->after('requester_last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table(SupportTables::name('tickets'), function (Blueprint $table) {
            $table->dropColumn(['requester_last_read_at', 'agent_last_read_at']);
        });
    }
};
