<?php

namespace Tests\Unit\Services;

use App\DTOs\FolderCountsDTO;
use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\User;
use App\Repositories\EmailRepository;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->emailRepository = Mockery::mock(EmailRepository::class);
    $this->emailService = new EmailService($this->emailRepository);
    $this->company = \App\Models\Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->companyId = $this->company->id;

    // Fake the queue to prevent actual job dispatching
    Queue::fake();
});

afterEach(function () {
    Mockery::close();
});

describe('getInboxEmails', function () {
    it('returns paginated emails with proper structure', function () {
        $emails = EmailMessage::factory()->count(3)->create();
        $paginator = new LengthAwarePaginator(
            $emails,
            10,
            5,
            1,
            ['path' => '/inbox']
        );

        $this->emailRepository
            ->shouldReceive('getPaginatedEmails')
            ->once()
            ->with($this->companyId, null, 'inbox', null, 5, null, 'all')
            ->andReturn($paginator);

        $result = $this->emailService->getInboxEmails(
            $this->companyId,
            null,
            'inbox',
            null,
            5,
            null
        );

        expect($result)->toHaveKeys(['data', 'links', 'meta']);
        expect($result['data'])->toBeArray()->toHaveCount(3);
        expect($result['links'])->toHaveKeys(['first', 'last', 'prev', 'next']);
        expect($result['meta'])->toHaveKeys(['current_page', 'from', 'last_page', 'per_page', 'to', 'total']);
    });

    it('passes correct parameters to repository', function () {
        $paginator = new LengthAwarePaginator([], 0, 10, 1);

        $this->emailRepository
            ->shouldReceive('getPaginatedEmails')
            ->once()
            ->with($this->companyId, 123, 'spam', 'test search', 25, '2', 'all')
            ->andReturn($paginator);

        $this->emailService->getInboxEmails(
            $this->companyId,
            123,
            'spam',
            'test search',
            25,
            '2'
        );
    });

    it('passes unread filter to repository', function () {
        $paginator = new LengthAwarePaginator([], 0, 10, 1);

        $this->emailRepository
            ->shouldReceive('getPaginatedEmails')
            ->once()
            ->with($this->companyId, null, 'inbox', null, 10, null, 'unread')
            ->andReturn($paginator);

        $this->emailService->getInboxEmails(
            $this->companyId,
            null,
            'inbox',
            null,
            10,
            null,
            'unread'
        );
    });
});

describe('getFolderCounts', function () {
    it('returns folder counts from repository', function () {
        $folderCounts = new FolderCountsDTO(
            inbox: 10,
            drafts: 2,
            sent: 5,
            junk: 3,
            trash: 1,
            archive: 4
        );

        $this->emailRepository
            ->shouldReceive('getFolderCounts')
            ->once()
            ->with($this->companyId, null)
            ->andReturn($folderCounts);

        $result = $this->emailService->getFolderCounts($this->companyId);

        expect($result)->toEqual([
            'inbox' => 10,
            'drafts' => 2,
            'sent' => 5,
            'junk' => 3,
            'trash' => 1,
            'archive' => 4,
        ]);
    });
});

describe('archiveEmails', function () {
    it('archives emails successfully', function () {
        $emailIds = [1, 2, 3];

        $this->emailRepository
            ->shouldReceive('archiveEmails')
            ->once()
            ->with($emailIds, $this->companyId)
            ->andReturn(3);

        $result = $this->emailService->archiveEmails($emailIds, $this->companyId);

        expect($result)->toEqual([
            'success' => true,
            'message' => '3 email(s) archived successfully',
        ]);
    });

    it('returns error when no emails selected', function () {
        $result = $this->emailService->archiveEmails([], $this->companyId);

        expect($result)->toEqual([
            'success' => false,
            'message' => 'No emails selected',
        ]);
    });

    it('returns error when no emails found', function () {
        $this->emailRepository
            ->shouldReceive('archiveEmails')
            ->once()
            ->andReturn(0);

        $result = $this->emailService->archiveEmails([1, 2], $this->companyId);

        expect($result)->toEqual([
            'success' => false,
            'message' => 'No emails found or unauthorized',
        ]);
    });
});

describe('deleteEmails', function () {
    it('moves emails to trash successfully', function () {
        $emailIds = [1, 2, 3];

        $this->emailRepository
            ->shouldReceive('deleteEmails')
            ->once()
            ->with($emailIds, $this->companyId)
            ->andReturn(3);

        $result = $this->emailService->deleteEmails($emailIds, $this->companyId);

        expect($result)->toEqual([
            'success' => true,
            'message' => '3 email(s) moved to trash',
        ]);
    });
});

describe('moveToSpam', function () {
    it('moves emails to spam successfully', function () {
        $emailIds = [1, 2];

        $this->emailRepository
            ->shouldReceive('moveToSpam')
            ->once()
            ->with($emailIds, $this->companyId)
            ->andReturn(2);

        $result = $this->emailService->moveToSpam($emailIds, $this->companyId);

        expect($result)->toEqual([
            'success' => true,
            'message' => '2 email(s) moved to spam',
        ]);
    });
});

describe('toggleStar', function () {
    it('toggles star status successfully', function () {
        $email = EmailMessage::factory()->create(['is_starred' => false]);

        $this->emailRepository
            ->shouldReceive('toggleStar')
            ->once()
            ->with(1, $this->companyId)
            ->andReturn($email);

        $result = $this->emailService->toggleStar(1, $this->companyId);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toContain('Email');
        expect($result)->toHaveKey('isStarred');
    });

    it('returns error when email not found', function () {
        $this->emailRepository
            ->shouldReceive('toggleStar')
            ->once()
            ->andReturn(null);

        $result = $this->emailService->toggleStar(999, $this->companyId);

        expect($result)->toEqual([
            'success' => false,
            'message' => 'Email not found',
        ]);
    });
});

describe('syncEmails', function () {
    it('syncs single email account successfully', function () {
        $account = EmailAccount::factory()->create([
            'company_id' => $this->companyId,
            'email_address' => 'test@example.com',
        ]);

        $result = $this->emailService->syncEmails($this->companyId, $account->id);

        expect($result)->toEqual([
            'success' => true,
            'message' => 'Sync initiated for test@example.com',
        ]);

        Queue::assertPushed(SyncEmailAccountJob::class, function ($job) use ($account) {
            return $job->emailAccount->id === $account->id;
        });
    });

    it('syncs all email accounts successfully', function () {
        EmailAccount::factory()->count(3)->create([
            'company_id' => $this->companyId,
            'is_active' => true,
        ]);

        $result = $this->emailService->syncEmails($this->companyId);

        expect($result)->toEqual([
            'success' => true,
            'message' => 'Sync initiated for 3 accounts',
        ]);

        Queue::assertPushed(SyncEmailAccountJob::class, 3);
    });

    it('returns error when no accounts to sync', function () {
        $result = $this->emailService->syncEmails($this->companyId);

        expect($result)->toEqual([
            'success' => false,
            'message' => 'No active email accounts to sync',
        ]);

        Queue::assertNotPushed(SyncEmailAccountJob::class);
    });
});
