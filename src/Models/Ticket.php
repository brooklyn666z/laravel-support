<?php

declare(strict_types=1);

namespace Fillindev\Support\Models;

use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\SupportTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'number',
        'requester_type',
        'requester_id',
        'assignee_type',
        'assignee_id',
        'category_id',
        'subject',
        'status',
        'priority',
        'last_replied_at',
        'requester_last_read_at',
        'agent_last_read_at',
        'requester_last_message_at',
        'agent_last_message_at',
        'resolved_at',
        'closed_at',
    ];

    public function getTable(): string
    {
        return SupportTables::name('tickets');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requester_id' => 'integer',
            'assignee_id' => 'integer',
            'category_id' => 'integer',
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'last_replied_at' => 'datetime',
            'requester_last_read_at' => 'datetime',
            'agent_last_read_at' => 'datetime',
            'requester_last_message_at' => 'datetime',
            'agent_last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    /**
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class, 'ticket_id');
    }

    /**
     * Время последнего публичного сообщения этой стороны.
     * Внутренняя заметка курсор не двигает.
     */
    public function rememberPartyMessageAt(TicketMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }

        $fromRequester = (string) $message->author_type === (string) $this->requester_type
            && (int) $message->author_id === (int) $this->requester_id;

        $this->update([
            $fromRequester ? 'requester_last_message_at' : 'agent_last_message_at' => $message->created_at,
        ]);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeForRequester(Builder $query, string $type, int|string $requesterId): Builder
    {
        return $query
            ->where('requester_type', $type)
            ->where('requester_id', $requesterId);
    }
}
