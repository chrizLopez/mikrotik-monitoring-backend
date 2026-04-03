<?php

namespace Database\Factories;

use App\Models\Isp;
use Illuminate\Database\Eloquent\Factories\Factory;

class IspFactory extends Factory
{
    protected $model = Isp::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'interface_name' => 'ether'.fake()->numberBetween(1, 10),
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
