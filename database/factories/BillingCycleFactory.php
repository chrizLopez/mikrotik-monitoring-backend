<?php

namespace Database\Factories;

use App\Models\BillingCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingCycleFactory extends Factory
{
    protected $model = BillingCycle::class;

    public function definition(): array
    {
        $startsAt = now()->startOfMonth();
        $endsAt = now()->startOfMonth()->addMonth();

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'label' => $startsAt->format('M j').' - '.$endsAt->copy()->subSecond()->format('M j, Y'),
            'is_current' => true,
        ];
    }
}
