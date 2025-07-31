<?php

namespace Database\Seeders;

use App\Models\EmailMessage;
use App\Models\EmailAttachment;
use Illuminate\Database\Seeder;

class TestAttachmentsSeeder extends Seeder
{
    public function run(): void
    {
        // Get some recent emails to add attachments to
        $emails = EmailMessage::latest()->take(5)->get();

        foreach ($emails as $email) {
            // Add 1-3 attachments per email
            $attachmentCount = rand(1, 3);
            
            for ($i = 0; $i < $attachmentCount; $i++) {
                $this->createAttachment($email);
            }
        }
    }

    private function createAttachment(EmailMessage $email): void
    {
        $attachmentTypes = [
            [
                'filename' => 'document.pdf',
                'content_type' => 'application/pdf',
                'size' => rand(100000, 5000000),
                'content_disposition' => 'attachment',
            ],
            [
                'filename' => 'spreadsheet.xlsx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size' => rand(50000, 2000000),
                'content_disposition' => 'attachment',
            ],
            [
                'filename' => 'presentation.pptx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'size' => rand(200000, 10000000),
                'content_disposition' => 'attachment',
            ],
            [
                'filename' => 'image.png',
                'content_type' => 'image/png',
                'size' => rand(50000, 1000000),
                'content_id' => 'image' . uniqid() . '@mail',
                'content_disposition' => 'inline',
            ],
            [
                'filename' => 'photo.jpg',
                'content_type' => 'image/jpeg',
                'size' => rand(100000, 3000000),
                'content_disposition' => 'attachment',
            ],
            [
                'filename' => 'archive.zip',
                'content_type' => 'application/zip',
                'size' => rand(500000, 20000000),
                'content_disposition' => 'attachment',
            ],
        ];

        $attachment = $attachmentTypes[array_rand($attachmentTypes)];
        
        EmailAttachment::create([
            'email_message_id' => $email->id,
            'filename' => $attachment['filename'],
            'content_type' => $attachment['content_type'],
            'size' => $attachment['size'],
            'content_id' => $attachment['content_id'] ?? null,
            'content_disposition' => $attachment['content_disposition'],
            'download_url' => '/api/emails/' . $email->id . '/attachments/' . uniqid(),
            'thumbnail_url' => isset($attachment['content_id']) ? '/api/emails/' . $email->id . '/inline/' . $attachment['content_id'] : null,
        ]);
    }
}