<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Requests;

use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(TicketPriority::class)],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'assignee_type' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
            'requester_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'requester_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'updated_from' => ['sometimes', 'nullable', 'date'],
            'updated_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:updated_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
