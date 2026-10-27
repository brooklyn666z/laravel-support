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

        Schema::table($tickets, function (Blueprint $table) {
            $table->timestamp('requester_last_message_at')->nullable()->after('agent_last_read_at');
            $table->timestamp('agent_last_message_at')->nullable()->after('requester_last_message_at');
        });

        foreach (DB::table($tickets)->select('id', 'requester_type', 'requester_id')->get() as $ticket) {
            $requesterAt = DB::table($messages)
                ->where('ticket_id', $ticket->id)
                ->where('is_internal', false)
                ->where('author_type', $ticket->requester_type)
                ->where('author_id', $ticket->requester_id)
                ->max('created_at');

            $agentAt = DB::table($messages)
                ->where('ticket_id', $ticket->id)
                ->where('is_internal', false)
                ->where(function ($query) use ($ticket) {
                    $query->where('author_type', '!=', $ticket->requester_type)
                        ->orWhere('author_id', '!=', $ticket->requester_id);
                })
                ->max('created_at');

            DB::table($tickets)->where('id', $ticket->id)->update([
                'requester_last_message_at' => $requesterAt,
                'agent_last_message_at' => $agentAt,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table(SupportTables::name('tickets'), function (Blueprint $table) {
            $table->dropColumn(['requester_last_message_at', 'agent_last_message_at']);
        });
    }
};
