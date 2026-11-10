<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexMessagesRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'prev' => ['boolean'],
            'next' => ['boolean'],
        ];
    }
}
