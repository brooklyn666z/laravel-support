<?php

declare(strict_types=1);

namespace Fillindev\Support\Services;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportUserResolver;
use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Fillindev\Support\Models\Ticket;
use Fillindev\Support\Models\TicketMessage;
use Fillindev\Support\Models\TicketOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Сводка по тикетам для старшего оператора.
 * Карточки, статусы, приоритеты и категории считаются по обращениям, созданным в периоде.
 * Линия «решено» на графике объёма — обращения, решённые в эти дни, в том числе созданные раньше.
 */
class StatisticsService
{
    public function __construct(
        private readonly SupportUserResolver $users,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forPeriod(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now())->endOfDay();
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->startOfDay();

        if ($fromDay->greaterThan($toDay)) {
            throw new InvalidArgumentException('Дата начала позже даты окончания.');
        }

        if ($fromDay->diff($toDay)->days > 366) {
            throw new InvalidArgumentException('Период не длиннее 366 дней.');
        }

        $operatorId = isset($filters['operator_id']) ? (int) $filters['operator_id'] : null;
        $operatorType = isset($filters['operator_type']) && $filters['operator_type'] !== ''
            ? (string) $filters['operator_type']
            : 'user';

        $created = $this->createdQuery($from, $to, $operatorId, $operatorType)
            ->with([
                'category',
                'operators',
                'messages' => fn ($query) => $query->public()->orderBy('id'),
            ])
            ->get();

        $cards = $this->cards($created);
        $days = $this->days($fromDay, $toDay);

        return [
            'period' => [
                'from' => $fromDay->toDateString(),
                'to' => $toDay->toDateString(),
            ],
            'operator' => $operatorId === null ? null : [
                'type' => $operatorType,
                'id' => $operatorId,
                'name' => $this->name($operatorType, $operatorId),
            ],
            'cards' => $cards,
            'volume' => $this->volume($created, $from, $to, $operatorId, $operatorType, $days),
            'statuses' => $this->statuses($created),
            'priorities' => $this->priorities($created),
            'categories' => $this->categories($created),
            'first_response' => $this->dailyFirstResponse($created, $days),
            'resolution' => $this->dailyResolution($created, $days),
            'operators' => $operatorId === null ? $this->operatorLoad($created) : null,
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    private function createdQuery(Carbon $from, Carbon $to, ?int $operatorId, string $operatorType): Builder
    {
        $query = Ticket::query()->whereBetween('created_at', [$from, $to]);

        return $this->restrictOperator($query, $operatorId, $operatorType);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function restrictOperator(Builder $query, ?int $operatorId, string $operatorType): Builder
    {
        if ($operatorId === null) {
            return $query;
        }

        $operators = (new TicketOperator)->getTable();

        return $query->where(function (Builder $inner) use ($operatorId, $operatorType, $operators): void {
            $inner->where(function (Builder $assigned) use ($operatorId, $operatorType): void {
                $assigned->where('assignee_type', $operatorType)->where('assignee_id', $operatorId);
            })->orWhereIn('id', function ($sub) use ($operatorId, $operatorType, $operators): void {
                $sub->select('ticket_id')
                    ->from($operators)
                    ->where('operator_type', $operatorType)
                    ->where('operator_id', $operatorId);
            });
        });
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array<string, int|float|null>
     */
    private function cards(Collection $tickets): array
    {
        $total = $tickets->count();
        $solved = $tickets->filter(fn (Ticket $ticket) => $this->isSolved($ticket))->count();
        $byStatus = $this->statuses($tickets);

        return [
            'total' => $total,
            'open' => $byStatus[TicketStatus::Open->value],
            'in_progress' => $byStatus[TicketStatus::InProgress->value],
            'waiting_customer' => $byStatus[TicketStatus::WaitingCustomer->value],
            'resolved' => $byStatus[TicketStatus::Resolved->value],
            'closed' => $byStatus[TicketStatus::Closed->value],
            'solved' => $solved,
            'first_response_minutes' => $this->averageInt($tickets->map(fn (Ticket $ticket) => $this->firstResponseMinutes($ticket))->filter(fn ($value) => $value !== null)->all()),
            'resolution_hours' => $this->averageHours($tickets->map(fn (Ticket $ticket) => $this->resolutionHours($ticket))->filter(fn ($value) => $value !== null)->all()),
            'solved_share' => $total === 0 ? 0 : (int) round($solved / $total * 100),
        ];
    }

    /**
     * @param  Collection<int, Ticket>  $created
     * @param  list<string>  $days
     * @return list<array{date: string, created: int, solved: int}>
     */
    private function volume(Collection $created, Carbon $from, Carbon $to, ?int $operatorId, string $operatorType, array $days): array
    {
        $createdByDay = [];
        foreach ($created as $ticket) {
            $key = $ticket->created_at?->toDateString();
            if ($key === null) {
                continue;
            }
            $createdByDay[$key] = ($createdByDay[$key] ?? 0) + 1;
        }

        $solvedByDay = [];
        $solved = $this->restrictOperator(Ticket::query(), $operatorId, $operatorType)
            ->where(function (Builder $query) use ($from, $to): void {
                $query->whereBetween('resolved_at', [$from, $to])
                    ->orWhere(function (Builder $closed) use ($from, $to): void {
                        $closed->whereNull('resolved_at')
                            ->where('status', TicketStatus::Closed->value)
                            ->whereBetween('closed_at', [$from, $to]);
                    });
            })
            ->get(['id', 'resolved_at', 'closed_at', 'status']);

        foreach ($solved as $ticket) {
            $at = $this->solvedAt($ticket);
            if ($at === null) {
                continue;
            }
            $key = $at->toDateString();
            $solvedByDay[$key] = ($solvedByDay[$key] ?? 0) + 1;
        }

        $points = [];
        foreach ($days as $day) {
            $points[] = [
                'date' => $day,
                'created' => $createdByDay[$day] ?? 0,
                'solved' => $solvedByDay[$day] ?? 0,
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array<string, int>
     */
    private function statuses(Collection $tickets): array
    {
        $counts = [];
        foreach (TicketStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($tickets as $ticket) {
            $status = $ticket->status instanceof TicketStatus ? $ticket->status->value : (string) $ticket->status;
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array<string, int>
     */
    private function priorities(Collection $tickets): array
    {
        $counts = [];
        foreach (TicketPriority::cases() as $priority) {
            $counts[$priority->value] = 0;
        }

        foreach ($tickets as $ticket) {
            $priority = $ticket->priority instanceof TicketPriority ? $ticket->priority->value : (string) $ticket->priority;
            if (array_key_exists($priority, $counts)) {
                $counts[$priority]++;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return list<array{id: int|null, name: string, count: int}>
     */
    private function categories(Collection $tickets): array
    {
        /** @var array<string, array{id: int|null, name: string, count: int}> $rows */
        $rows = [];

        foreach ($tickets as $ticket) {
            $id = $ticket->category_id === null ? null : (int) $ticket->category_id;
            $key = $id === null ? 'none' : (string) $id;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'id' => $id,
                    'name' => $id === null ? 'Без категории' : (string) ($ticket->category->name ?? 'Без категории'),
                    'count' => 0,
                ];
            }
            $rows[$key]['count']++;
        }

        $list = array_values($rows);
        usort($list, function (array $left, array $right): int {
            return $right['count'] <=> $left['count'] ?: strcmp($left['name'], $right['name']);
        });

        return $list;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  list<string>  $days
     * @return list<array{date: string, minutes: int|null}>
     */
    private function dailyFirstResponse(Collection $tickets, array $days): array
    {
        /** @var array<string, list<float>> $samples */
        $samples = [];
        foreach ($tickets as $ticket) {
            $minutes = $this->firstResponseMinutes($ticket);
            $day = $ticket->created_at?->toDateString();
            if ($minutes === null || $day === null) {
                continue;
            }
            $samples[$day][] = $minutes;
        }

        $points = [];
        foreach ($days as $day) {
            $points[] = [
                'date' => $day,
                'minutes' => $this->averageInt($samples[$day] ?? []),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  list<string>  $days
     * @return list<array{date: string, hours: float|null}>
     */
    private function dailyResolution(Collection $tickets, array $days): array
    {
        /** @var array<string, list<float>> $samples */
        $samples = [];
        foreach ($tickets as $ticket) {
            $hours = $this->resolutionHours($ticket);
            $day = $ticket->created_at?->toDateString();
            if ($hours === null || $day === null) {
                continue;
            }
            $samples[$day][] = $hours;
        }

        $points = [];
        foreach ($days as $day) {
            $points[] = [
                'date' => $day,
                'hours' => $this->averageHours($samples[$day] ?? []),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return list<array{type: string, id: int, name: string|null, received: int, solved: int}>
     */
    private function operatorLoad(Collection $tickets): array
    {
        /** @var array<string, array{type: string, id: int, received: int, solved: int}> $rows */
        $rows = [];

        foreach ($tickets as $ticket) {
            $keys = [];
            if ($ticket->assignee_id !== null) {
                $type = (string) ($ticket->assignee_type ?: 'user');
                $keys[$type.':'.$ticket->assignee_id] = [$type, (int) $ticket->assignee_id];
            }
            foreach ($ticket->operators as $operator) {
                $type = (string) $operator->operator_type;
                $keys[$type.':'.$operator->operator_id] = [$type, (int) $operator->operator_id];
            }

            $solved = $this->isSolved($ticket);
            foreach ($keys as $key => [$type, $id]) {
                if (! isset($rows[$key])) {
                    $rows[$key] = [
                        'type' => $type,
                        'id' => $id,
                        'received' => 0,
                        'solved' => 0,
                    ];
                }
                $rows[$key]['received']++;
                if ($solved) {
                    $rows[$key]['solved']++;
                }
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'type' => $row['type'],
                'id' => $row['id'],
                'name' => $this->name($row['type'], $row['id']),
                'received' => $row['received'],
                'solved' => $row['solved'],
            ];
        }

        usort($list, function (array $left, array $right): int {
            return $right['received'] <=> $left['received']
                ?: $right['solved'] <=> $left['solved']
                ?: $left['id'] <=> $right['id'];
        });

        return $list;
    }

    private function isSolved(Ticket $ticket): bool
    {
        return in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true);
    }

    private function solvedAt(Ticket $ticket): ?Carbon
    {
        if ($ticket->resolved_at !== null) {
            return $ticket->resolved_at;
        }

        if ($ticket->status === TicketStatus::Closed && $ticket->closed_at !== null) {
            return $ticket->closed_at;
        }

        return null;
    }

    private function firstResponseMinutes(Ticket $ticket): ?float
    {
        if ($ticket->created_at === null) {
            return null;
        }

        foreach ($ticket->messages as $message) {
            if (! $message instanceof TicketMessage || $message->is_internal) {
                continue;
            }

            $fromRequester = (string) $message->author_type === (string) $ticket->requester_type
                && (int) $message->author_id === (int) $ticket->requester_id;

            if ($fromRequester || $message->created_at === null) {
                continue;
            }

            return max(0, $message->created_at->getTimestamp() - $ticket->created_at->getTimestamp()) / 60;
        }

        return null;
    }

    private function resolutionHours(Ticket $ticket): ?float
    {
        $solvedAt = $this->solvedAt($ticket);
        if ($solvedAt === null || $ticket->created_at === null) {
            return null;
        }

        return max(0, $solvedAt->getTimestamp() - $ticket->created_at->getTimestamp()) / 3600;
    }

    /**
     * @param  list<float>  $samples
     */
    private function averageInt(array $samples): ?int
    {
        if ($samples === []) {
            return null;
        }

        return (int) round(array_sum($samples) / count($samples));
    }

    /**
     * @param  list<float>  $samples
     */
    private function averageHours(array $samples): ?float
    {
        if ($samples === []) {
            return null;
        }

        return round(array_sum($samples) / count($samples), 1);
    }

    /**
     * @return list<string>
     */
    private function days(Carbon $fromDay, Carbon $toDay): array
    {
        $days = [];
        $cursor = $fromDay->copy();

        while ($cursor->lte($toDay)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }

    private function name(string $type, int $id): ?string
    {
        $user = $this->users->resolve($type, $id);

        return $user instanceof SupportableUser ? $user->getSupportDisplayName() : null;
    }
}
