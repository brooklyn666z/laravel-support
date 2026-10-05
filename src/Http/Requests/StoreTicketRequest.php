<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Requests;

use Fillindev\Support\Enums\TicketPriority;
use Fillindev\Support\SupportTables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', Rule::exists(SupportTables::name('ticket_categories'), 'id')],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
