<?php

declare(strict_types=1);

namespace Fillindev\Support\Models;

use Fillindev\Support\SupportTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketOperator extends Model
{
    protected $fillable = [
        'ticket_id',
        'operator_type',
        'operator_id',
    ];

    public function getTable(): string
    {
        return SupportTables::name('ticket_operators');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'operator_id' => 'integer',
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
