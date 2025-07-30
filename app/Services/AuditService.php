<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit event
     */
    public static function log(
        string $eventType,
        string $description,
        ?Model $auditable = null,
        ?array $data = null,
        ?int $userId = null,
        ?int $companyId = null
    ): AuditLog {
        // Get user and company from auth if not provided
        $userId = $userId ?? Auth::id();
        $companyId = $companyId ?? Auth::user()?->company_id;

        $auditData = [
            'company_id' => $companyId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'description' => $description,
            'data' => $data,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        if ($auditable) {
            $auditData['auditable_type'] = get_class($auditable);
            $auditData['auditable_id'] = $auditable->id;
        }

        return AuditLog::create($auditData);
    }

    /**
     * Log email access
     */
    public static function logEmailAccess(Model $email, string $action = 'viewed'): AuditLog
    {
        return self::log(
            AuditLog::EVENT_EMAIL_ACCESSED,
            "Email {$action}",
            $email,
            [
                'action' => $action,
                'subject' => $email->subject ?? null,
                'sender' => $email->sender_email ?? null,
            ]
        );
    }

    /**
     * Log email deletion
     */
    public static function logEmailDeletion(Model $email): AuditLog
    {
        return self::log(
            AuditLog::EVENT_EMAIL_DELETED,
            'Email deleted',
            $email,
            [
                'subject' => $email->subject ?? null,
                'sender' => $email->sender_email ?? null,
                'received_at' => $email->received_at ?? null,
            ]
        );
    }

    /**
     * Log data export
     */
    public static function logDataExport(string $exportType, array $filters = []): AuditLog
    {
        return self::log(
            AuditLog::EVENT_DATA_EXPORTED,
            "Data exported: {$exportType}",
            null,
            [
                'export_type' => $exportType,
                'filters' => $filters,
                'exported_at' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * Log AI response generation
     */
    public static function logAIResponse(Model $email, array $metadata = []): AuditLog
    {
        return self::log(
            AuditLog::EVENT_AI_RESPONSE_GENERATED,
            'AI response generated for email',
            $email,
            array_merge([
                'email_subject' => $email->subject ?? null,
                'model_used' => $metadata['model'] ?? null,
                'tokens_used' => $metadata['tokens_used'] ?? null,
            ], $metadata)
        );
    }

    /**
     * Log account connection
     */
    public static function logAccountConnection(Model $account, string $provider): AuditLog
    {
        return self::log(
            AuditLog::EVENT_ACCOUNT_CONNECTED,
            "Email account connected: {$provider}",
            $account,
            [
                'provider' => $provider,
                'email' => $account->email ?? null,
            ]
        );
    }

    /**
     * Log account disconnection
     */
    public static function logAccountDisconnection(Model $account, string $provider): AuditLog
    {
        return self::log(
            AuditLog::EVENT_ACCOUNT_DISCONNECTED,
            "Email account disconnected: {$provider}",
            $account,
            [
                'provider' => $provider,
                'email' => $account->email ?? null,
            ]
        );
    }

    /**
     * Log document upload
     */
    public static function logDocumentUpload(Model $document): AuditLog
    {
        return self::log(
            AuditLog::EVENT_DOCUMENT_UPLOADED,
            "Document uploaded: {$document->filename}",
            $document,
            [
                'filename' => $document->filename,
                'mime_type' => $document->mime_type,
                'size' => $document->file_size,
            ]
        );
    }

    /**
     * Log document deletion
     */
    public static function logDocumentDeletion(Model $document): AuditLog
    {
        return self::log(
            AuditLog::EVENT_DOCUMENT_DELETED,
            "Document deleted: {$document->filename}",
            $document,
            [
                'filename' => $document->filename,
                'mime_type' => $document->mime_type,
            ]
        );
    }

    /**
     * Log user login
     */
    public static function logLogin(Model $user): AuditLog
    {
        return self::log(
            AuditLog::EVENT_LOGIN,
            'User logged in',
            $user,
            [
                'email' => $user->email,
                'login_at' => now()->toIso8601String(),
            ],
            $user->id,
            $user->company_id
        );
    }

    /**
     * Log user logout
     */
    public static function logLogout(Model $user): AuditLog
    {
        return self::log(
            AuditLog::EVENT_LOGOUT,
            'User logged out',
            $user,
            [
                'email' => $user->email,
                'logout_at' => now()->toIso8601String(),
            ],
            $user->id,
            $user->company_id
        );
    }
}
