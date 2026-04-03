<?php

namespace App\Http\Requests\Dashboard;

class TopUsersRequest extends RangeRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function limit(): int
    {
        return (int) ($this->input('limit') ?: 10);
    }
}
