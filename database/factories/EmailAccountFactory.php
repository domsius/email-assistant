<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmailAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email_address' => $this->faker->unique()->safeEmail(),
            'provider' => $this->faker->randomElement(['gmail', 'outlook', 'yahoo']),
            'provider_account_id' => $this->faker->uuid(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_expires_at' => $this->faker->dateTimeBetween('now', '+1 hour'),
            'provider_settings' => json_encode([
                'folders' => ['INBOX', 'SENT', 'DRAFTS', 'SPAM', 'TRASH'],
            ]),
            'oauth_state' => null,
            'is_active' => true,
            'last_sync_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function gmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'gmail',
            'email_address' => $this->faker->userName().'@gmail.com',
        ]);
    }

    public function outlook(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'outlook',
            'email_address' => $this->faker->userName().'@outlook.com',
        ]);
    }
}
