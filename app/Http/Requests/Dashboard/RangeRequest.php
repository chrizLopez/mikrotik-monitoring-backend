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
            'range' => ['nullable', 'in:today,24h,7d,30d,cycle'],
        ];
    }

    public function range(): string
    {
        return $this->string('range')->toString() ?: 'cycle';
    }
}
