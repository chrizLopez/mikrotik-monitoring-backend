<?php

namespace App\Http\Requests\Dashboard;

class UsersIndexRequest extends RangeRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'in:GROUP_A,GROUP_B'],
            'state' => ['nullable', 'in:NORMAL,THROTTLED'],
            'sort' => ['nullable', 'in:name,used_bytes,remaining_quota,usage_percent,last_updated'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function search(): ?string
    {
        $value = trim($this->string('search')->toString());

        return $value !== '' ? $value : null;
    }

    public function group(): ?string
    {
        return match ($this->string('group')->toString()) {
            'GROUP_A' => config('dashboard.group_names.A', 'Group A'),
            'GROUP_B' => config('dashboard.group_names.B', 'Group B'),
            default => null,
        };
    }

    public function state(): ?string
    {
        $value = $this->string('state')->toString();

        return $value !== '' ? $value : null;
    }

    public function sort(): string
    {
        return $this->string('sort')->toString() ?: 'name';
    }

    public function direction(): string
    {
        return $this->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
    }

    public function perPage(): int
    {
        return $this->limit('per_page', 15, 5, 100);
    }
}
