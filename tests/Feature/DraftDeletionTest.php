<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\EmailDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EmailAccount $emailAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a company first
        $company = \App\Models\Company::factory()->create();

        $this->user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->emailAccount = EmailAccount::factory()->create([
            'company_id' => $company->id,
        ]);
    }

    public function test_can_delete_draft_through_email_delete_endpoint(): void
    {
        // Create a draft
        $draft = EmailDraft::create([
            'user_id' => $this->user->id,
            'email_account_id' => $this->emailAccount->id,
            'subject' => 'Test Draft',
            'body' => 'Test draft content',
            'to' => 'test@example.com',
            'last_saved_at' => now(),
        ]);

        $this->actingAs($this->user);

        // Send draft ID with 'draft-' prefix as it comes from frontend
        $response = $this->post('/emails/delete', [
            'emailIds' => ['draft-'.$draft->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 draft(s) deleted');

        // Verify draft was deleted
        $this->assertDatabaseMissing('email_drafts', [
            'id' => $draft->id,
        ]);
    }

    public function test_can_delete_mixed_drafts_and_emails(): void
    {
        // Create a draft
        $draft = EmailDraft::create([
            'user_id' => $this->user->id,
            'email_account_id' => $this->emailAccount->id,
            'subject' => 'Test Draft',
            'body' => 'Test draft content',
            'to' => 'test@example.com',
            'last_saved_at' => now(),
        ]);

        // Create an email using factory
        $email = \App\Models\EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'folder' => 'INBOX',
            'is_deleted' => false,
        ]);

        $this->actingAs($this->user);

        // Send both draft and email IDs
        $response = $this->post('/emails/delete', [
            'emailIds' => ['draft-'.$draft->id, $email->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 draft(s) deleted and 1 email(s) moved to trash');

        // Verify draft was deleted
        $this->assertDatabaseMissing('email_drafts', [
            'id' => $draft->id,
        ]);

        // Verify email was soft deleted
        $this->assertDatabaseHas('email_messages', [
            'id' => $email->id,
            'is_deleted' => true,
        ]);
    }

    public function test_cannot_delete_other_users_drafts(): void
    {
        // Create another user
        $otherUser = User::factory()->create();

        // Create a draft for the other user
        $draft = EmailDraft::create([
            'user_id' => $otherUser->id,
            'email_account_id' => $this->emailAccount->id,
            'subject' => 'Other User Draft',
            'body' => 'Should not be deletable',
            'to' => 'test@example.com',
            'last_saved_at' => now(),
        ]);

        $this->actingAs($this->user);

        // Try to delete the other user's draft
        $response = $this->post('/emails/delete', [
            'emailIds' => ['draft-'.$draft->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No emails found or unauthorized');

        // Verify draft was NOT deleted
        $this->assertDatabaseHas('email_drafts', [
            'id' => $draft->id,
        ]);
    }
}
