<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailMessageFactory extends Factory
{
    protected $model = EmailMessage::class;

    public function definition(): array
    {
        $bodyContent = $this->faker->paragraphs(3, true);

        return [
            'email_account_id' => EmailAccount::factory(),
            'customer_id' => Customer::factory(),
            'topic_id' => null,
            'message_id' => $this->faker->uuid(),
            'thread_id' => $this->faker->optional()->uuid(),
            'subject' => $this->faker->sentence(),
            'body_content' => $bodyContent,
            'sender_email' => $this->faker->email(),
            'sender_name' => $this->faker->name(),
            'detected_language' => 'lt',
            'language_confidence' => 0.95,
            'received_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'status' => 'processed',
            'is_reply' => false,
            'replied_to_message_id' => null,
            // Fields added by migrations
            'folder' => 'INBOX',
            'from_email' => $this->faker->email(),
            'body_plain' => strip_tags($bodyContent),
            'snippet' => $this->faker->text(150),
            'processing_status' => 'processed',
            'labels' => [],
            'is_read' => $this->faker->boolean(70),
            'is_starred' => false,
            'has_attachments' => $this->faker->boolean(20),
            'is_deleted' => false,
            'deleted_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'is_spam' => false,
            'spam_marked_at' => null,
        ];
    }

    public function withStarred(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_starred' => true,
        ]);
    }

    public function asReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reply' => true,
        ]);
    }

    public function inbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'folder' => 'INBOX',
            'is_archived' => false,
            'is_deleted' => false,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'folder' => 'SENT',
            'is_archived' => false,
            'is_deleted' => false,
        ]);
    }

    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'folder' => 'SPAM',
            'is_spam' => true,
            'is_archived' => false,
            'is_deleted' => false,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }
}
