<?php

namespace App\Http\Requests\Mikrotik;

use Illuminate\Foundation\Http\FormRequest;

class MikrotikPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'router_name' => ['nullable', 'string'],
            'sent_at' => ['nullable', 'date'],
            'queues' => ['nullable', 'array'],
            'queues.*.name' => ['required', 'string'],
            'queues.*.upload_bytes' => ['required', 'numeric', 'min:0'],
            'queues.*.download_bytes' => ['required', 'numeric', 'min:0'],
            'queues.*.max_limit' => ['nullable', 'string'],
            'interfaces' => ['nullable', 'array'],
            'interfaces.*.name' => ['required', 'string'],
            'interfaces.*.rx_bytes' => ['required', 'numeric', 'min:0'],
            'interfaces.*.tx_bytes' => ['required', 'numeric', 'min:0'],
        ];
    }
}
