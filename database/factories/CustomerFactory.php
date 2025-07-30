<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'preferred_language' => 'lt',
            'category' => $this->faker->randomElement(['new', 'returning', 'vip', 'problematic']),
            'communication_preferences' => null,
            'first_contact_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'last_interaction_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'total_interactions' => $this->faker->numberBetween(0, 100),
            'total_follow_ups_sent' => $this->faker->numberBetween(0, 20),
            'satisfaction_score' => $this->faker->optional()->randomFloat(2, 1, 5),
            'journey_stage' => $this->faker->randomElement(['initial', 'engaged', 'qualified', 'converted', 'churned']),
            'notes' => $this->faker->optional()->text(),
        ];
    }
}
