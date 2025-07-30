<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckEmailContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:email-content {emailId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if an email has content in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $emailId = $this->argument('emailId');

        $email = \App\Models\EmailMessage::find($emailId);

        if (! $email) {
            $this->error("Email with ID {$emailId} not found.");

            return 1;
        }

        $this->info('Email found:');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $email->id],
                ['Subject', $email->subject],
                ['From', $email->from_email],
                ['Body HTML Length', strlen($email->body_html ?? '')],
                ['Body Plain Length', strlen($email->body_plain ?? '')],
                ['Body HTML (first 200 chars)', substr($email->body_html ?? '', 0, 200)],
                ['Body Plain (first 200 chars)', substr($email->body_plain ?? '', 0, 200)],
                ['Is Draft', $email->is_draft ? 'Yes' : 'No'],
                ['Folder', $email->folder],
            ]
        );

        return 0;
    }
}
