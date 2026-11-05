<?php

declare(strict_types=1);

namespace Fillindev\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatisticsRequest extends FormRequest
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
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'operator_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'operator_type' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
