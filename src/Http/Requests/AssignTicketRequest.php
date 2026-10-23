<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
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
            'assignee_id' => ['required', 'integer'],
            'assignee_type' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
