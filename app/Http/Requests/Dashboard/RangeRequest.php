<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class RangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:10m,1h,today,24h,7d,30d,cycle,prev_cycle'],
        ];
    }

    public function range(): string
    {
        return $this->string('range')->toString() ?: 'cycle';
    }

    public function limit(string $key = 'limit', int $default = 10, int $min = 1, int $max = 100): int
    {
        return max($min, min($max, (int) ($this->input($key) ?: $default)));
    }
}
