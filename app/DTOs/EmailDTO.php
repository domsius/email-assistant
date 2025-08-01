<?php

namespace App\DTOs;

use App\Enums\EmailSentiment;
use App\Enums\EmailStatus;
use App\Enums\EmailUrgency;
use App\Models\EmailMessage;
use App\Services\HtmlSanitizerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmailDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly string $sender,
        public readonly string $senderEmail,
        public readonly string $content,
        public readonly string $snippet,
        public readonly Carbon $receivedAt,
        public readonly EmailStatus $status,
        public readonly bool $isRead,
        public readonly bool $isStarred,
        public readonly int $emailAccountId,
        public readonly ?string $threadId,
        public readonly array $labels,
        public readonly ?string $language,
        public readonly ?string $topic,
        public readonly ?EmailSentiment $sentiment,
        public readonly ?EmailUrgency $urgency,
        public readonly ?AiAnalysisDTO $aiAnalysis,
        public readonly array $attachments,
        public readonly ?string $recipients = null,
    ) {}

    public static function fromModel(EmailMessage $email): self
    {
        $aiAnalysis = null;
        if ($email->ai_analysis) {
            $aiAnalysis = new AiAnalysisDTO(
                summary: $email->ai_analysis['summary'] ?? '',
                keyPoints: $email->ai_analysis['key_points'] ?? [],
                suggestedResponse: $email->ai_analysis['suggested_response'] ?? null,
                confidence: $email->ai_analysis['confidence'] ?? 0,
            );
        }

        $urgency = null;
        if ($email->urgency_level) {
            $urgency = EmailUrgency::tryFrom($email->urgency_level);
        }

        $sentiment = null;
        if ($email->sentiment_score !== null) {
            // Map sentiment score to sentiment enum
            if ($email->sentiment_score > 0.6) {
                $sentiment = EmailSentiment::POSITIVE;
            } elseif ($email->sentiment_score < -0.6) {
                $sentiment = EmailSentiment::NEGATIVE;
            } else {
                $sentiment = EmailSentiment::NEUTRAL;
            }
        }

        // For listing view, we don't load body fields to improve performance
        // Only sanitize content if body fields are loaded
        $content = '';
        if (isset($email->body_html) || isset($email->body_plain) || isset($email->body_content)) {
            $content = self::sanitizeHtml($email->body_html ?? $email->body_plain ?? $email->body_content ?? '', $email);
        }

        // Extract the primary recipient (first email in to_recipients)
        $recipients = null;
        if ($email->to_recipients) {
            $toRecipients = is_string($email->to_recipients) 
                ? json_decode($email->to_recipients, true) 
                : $email->to_recipients;
            if (is_array($toRecipients) && !empty($toRecipients)) {
                $recipients = $toRecipients[0];
            }
        }

        return new self(
            id: $email->id,
            subject: self::sanitizeText($email->subject ?? ''),
            sender: self::sanitizeText($email->sender_name ?? $email->from_email ?? ''),
            senderEmail: self::sanitizeText($email->from_email ?? ''),
            content: $content,
            snippet: self::sanitizeText($email->snippet ?? ''),
            receivedAt: $email->received_at,
            status: EmailStatus::tryFrom($email->processing_status ?? 'pending') ?? EmailStatus::PENDING,
            isRead: $email->is_read,
            isStarred: $email->is_starred,
            emailAccountId: $email->email_account_id,
            threadId: $email->thread_id,
            labels: $email->labels ?? [],
            language: $email->detected_language,
            topic: $email->topic?->name,
            sentiment: $sentiment,
            urgency: $urgency,
            aiAnalysis: $aiAnalysis,
            attachments: $email->relationLoaded('attachments') ?
                $email->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                    'formattedSize' => $attachment->formatted_size,
                    'contentType' => $attachment->content_type,
                    'contentId' => $attachment->content_id,
                    'isInline' => $attachment->isInline(),
                    'isImage' => $attachment->isImage(),
                    'downloadUrl' => $attachment->download_url,
                    'thumbnailUrl' => $attachment->thumbnail_url,
                ])->toArray() : [],
            recipients: $recipients,
        );
    }

    private static function sanitizeText(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    private static function sanitizeHtml(string $html, ?EmailMessage $email = null): string
    {
        $sanitizer = app(HtmlSanitizerService::class);

        // Debug logging
        Log::info('EmailDTO: Processing HTML', [
            'has_email' => $email !== null,
            'has_attachments_loaded' => $email ? $email->relationLoaded('attachments') : false,
            'attachment_count' => $email ? $email->attachments->count() : 0,
            'has_cid_urls' => str_contains($html, 'cid:'),
            'html_preview' => substr($html, 0, 500),
        ]);

        // Replace CID URLs with actual URLs if we have the email model
        if ($email && $email->relationLoaded('attachments')) {
            $originalHtml = $html;
            $html = self::replaceCidUrls($html, $email);
            
            Log::info('EmailDTO: CID replacement result', [
                'original_has_cid' => str_contains($originalHtml, 'cid:'),
                'result_has_cid' => str_contains($html, 'cid:'),
                'html_changed' => $originalHtml !== $html,
            ]);
        }

        return self::sanitizeText($sanitizer->sanitize($html));
    }

    /**
     * Replace cid: URLs with actual API URLs for inline images
     */
    private static function replaceCidUrls(string $html, EmailMessage $email): string
    {
        // Find all cid: URLs in the HTML
        $pattern = '/src=["\']?cid:([^"\'\s>]+)["\']?/i';

        Log::info('EmailDTO: Starting CID replacement', [
            'email_id' => $email->id,
            'pattern' => $pattern,
            'attachment_count' => $email->attachments->count(),
            'attachment_content_ids' => $email->attachments->pluck('content_id')->toArray(),
        ]);

        return preg_replace_callback($pattern, function ($matches) use ($email) {
            $contentId = $matches[1];
            
            Log::info('EmailDTO: Found CID match', [
                'full_match' => $matches[0],
                'content_id' => $contentId,
            ]);

            // Find the attachment with this content ID
            $attachment = $email->attachments->first(function ($att) use ($contentId) {
                return $att->content_id === $contentId ||
                       $att->content_id === '<'.$contentId.'>' ||
                       $att->content_id === 'cid:'.$contentId;
            });

            if ($attachment) {
                // Replace with web route URL (uses session authentication)
                $webUrl = "/emails/{$email->id}/inline/{$contentId}";
                
                Log::info('EmailDTO: Found matching attachment', [
                    'attachment_id' => $attachment->id,
                    'attachment_content_id' => $attachment->content_id,
                    'web_url' => $webUrl,
                ]);

                return 'src="'.$webUrl.'"';
            }

            Log::info('EmailDTO: No matching attachment found for CID', [
                'content_id' => $contentId,
                'available_attachments' => $email->attachments->map(function($att) {
                    return [
                        'id' => $att->id,
                        'content_id' => $att->content_id,
                        'filename' => $att->filename,
                    ];
                })->toArray(),
            ]);

            // If no attachment found, return original
            return $matches[0];
        }, $html);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'sender' => $this->sender,
            'senderEmail' => $this->senderEmail,
            'content' => $this->content,
            'snippet' => $this->snippet,
            'receivedAt' => $this->receivedAt->toIso8601String(),
            'status' => $this->status->value,
            'isRead' => $this->isRead,
            'isStarred' => $this->isStarred,
            'isSelected' => false,
            'emailAccountId' => $this->emailAccountId,
            'threadId' => $this->threadId,
            'labels' => $this->labels,
            'language' => $this->language,
            'topic' => $this->topic,
            'sentiment' => $this->sentiment?->value,
            'urgency' => $this->urgency?->value,
            'aiAnalysis' => $this->aiAnalysis?->toArray(),
            'attachments' => $this->attachments,
            'recipients' => $this->recipients,
        ];
    }
}
