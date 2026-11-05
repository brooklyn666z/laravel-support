<?php

declare(strict_types=1);

namespace Fillindev\Support\Http;

use Fillindev\Support\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Фильтры очереди оператора.
 * status сужает только страницу: summary по вкладкам считается без него.
 * Остальные поля сужают и список, и summary.
 */
final class AdminTicketQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public static function base(array $filters): Builder
    {
        $query = Ticket::query();

        self::whereFilled($query, $filters, 'id', fn (Builder $query, mixed $value) => $query->whereKey($value));
        self::whereFilled($query, $filters, 'priority', fn (Builder $query, mixed $value) => $query->where('priority', $value));
        self::whereFilled($query, $filters, 'category_id', fn (Builder $query, mixed $value) => $query->where('category_id', $value));
        self::whereFilled($query, $filters, 'requester_type', fn (Builder $query, mixed $value) => $query->where('requester_type', $value));
        self::whereFilled($query, $filters, 'requester_id', fn (Builder $query, mixed $value) => $query->where('requester_id', $value));
        self::whereFilled($query, $filters, 'assignee_id', function (Builder $query, mixed $value) use ($filters): void {
            $query->where('assignee_id', $value);
            $type = self::value($filters, 'assignee_type');
            if ($type !== null) {
                $query->where('assignee_type', $type);
            }
        });
        self::whereFilled($query, $filters, 'subject', function (Builder $query, mixed $value): void {
            $needle = addcslashes((string) $value, '%_\\');
            $query->where('subject', 'like', '%'.$needle.'%');
        });
        self::whereFilled($query, $filters, 'updated_from', function (Builder $query, mixed $value): void {
            $query->where('updated_at', '>=', Carbon::parse((string) $value)->startOfDay());
        });
        self::whereFilled($query, $filters, 'updated_to', function (Builder $query, mixed $value): void {
            $query->where('updated_at', '<=', Carbon::parse((string) $value)->endOfDay());
        });

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $base
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public static function page(Builder $base, array $filters): Builder
    {
        $page = clone $base;
        $status = self::value($filters, 'status');

        if ($status !== null) {
            $page->where('status', $status);
        }

        return $page;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    private static function whereFilled(Builder $query, array $filters, string $key, callable $apply): void
    {
        $value = self::value($filters, $key);

        if ($value === null) {
            return;
        }

        $apply($query, $value);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function value(array $filters, string $key): mixed
    {
        if (! array_key_exists($key, $filters)) {
            return null;
        }

        $value = $filters[$key];

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
