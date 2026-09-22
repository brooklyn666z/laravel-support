<?php

declare(strict_types=1);

namespace Fillindev\Support\Models;

use Fillindev\Support\SupportTables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sort',
        'is_active',
    ];

    public function getTable(): string
    {
        return SupportTables::name('ticket_categories');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    /**
     * @param  Builder<TicketCategory>  $query
     * @return Builder<TicketCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
