<?php

namespace App\Http\Requests;

use App\Growth\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrowthRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', Rule::in(Period::ALLOWED)],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function days(): int
    {
        return (int) $this->validated('days', 30);
    }

    public function fresh(): bool
    {
        return $this->boolean('refresh');
    }
}
