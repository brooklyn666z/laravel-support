<?php

declare(strict_types=1);

namespace Fillindev\Support\Models;

use Fillindev\Support\SupportTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'author_id',
        'is_internal',
        'body',
    ];

    public function getTable(): string
    {
        return SupportTables::name('ticket_messages');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'author_id' => 'integer',
            'is_internal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Сообщения, которые видит клиент.
     *
     * @param  Builder<TicketMessage>  $query
     * @return Builder<TicketMessage>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }
}
