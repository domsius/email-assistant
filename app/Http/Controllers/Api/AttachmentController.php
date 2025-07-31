<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Services\AttachmentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttachmentController extends Controller
{
    public function __construct(
        private AttachmentStorageService $attachmentStorage
    ) {}

    /**
     * Upload attachment for email composition
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:25600', // 25MB max
            'email_account_id' => 'required|exists:email_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify the email account belongs to the user's company
        $emailAccount = EmailAccount::where('id', $request->email_account_id)
            ->where('company_id', auth()->user()->company_id)
            ->first();

        if (!$emailAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Email account not found',
            ], 404);
        }

        try {
            $file = $request->file('file');
            
            // Store the file
            $stored = $this->attachmentStorage->storeUploadedFile($file, $emailAccount->id);
            
            // Generate a temporary ID for the frontend
            $tempId = 'temp_' . uniqid();
            
            // Store in session for later use when sending email
            $attachments = session('compose_attachments', []);
            $attachments[$tempId] = array_merge($stored, [
                'temp_id' => $tempId,
                'email_account_id' => $emailAccount->id,
            ]);
            session(['compose_attachments' => $attachments]);
            
            return response()->json([
                'success' => true,
                'attachment' => [
                    'id' => $tempId,
                    'filename' => $stored['filename'],
                    'size' => $stored['size'],
                    'formattedSize' => $this->attachmentStorage->getHumanFileSize($stored['size']),
                    'contentType' => $stored['content_type'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove uploaded attachment
     */
    public function remove(Request $request, string $tempId): JsonResponse
    {
        $attachments = session('compose_attachments', []);
        
        if (!isset($attachments[$tempId])) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }
        
        // Delete the file
        $this->attachmentStorage->delete($attachments[$tempId]['path']);
        
        // Remove from session
        unset($attachments[$tempId]);
        session(['compose_attachments' => $attachments]);
        
        return response()->json([
            'success' => true,
            'message' => 'Attachment removed',
        ]);
    }
}