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
        'tenant_id',
        'requester_id',
        'assignee_id',
        'category_id',
        'subject',
        'status',
        'priority',
        'last_replied_at',
        'requester_last_read_at',
        'agent_last_read_at',
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
            'tenant_id' => 'integer',
            'requester_id' => 'integer',
            'assignee_id' => 'integer',
            'category_id' => 'integer',
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'last_replied_at' => 'datetime',
            'requester_last_read_at' => 'datetime',
            'agent_last_read_at' => 'datetime',
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
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeForTenant(Builder $query, int|string|null $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeForRequester(Builder $query, int|string $requesterId): Builder
    {
        return $query->where('requester_id', $requesterId);
    }
}
