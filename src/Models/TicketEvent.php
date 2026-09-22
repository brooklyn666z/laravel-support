<?php

declare(strict_types=1);

namespace Fillindev\Support\Models;

use Fillindev\Support\Enums\TicketEventType;
use Fillindev\Support\SupportTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'actor_id',
        'type',
        'old_value',
        'new_value',
        'meta',
        'created_at',
    ];

    public function getTable(): string
    {
        return SupportTables::name('ticket_events');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'actor_id' => 'integer',
            'type' => TicketEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
