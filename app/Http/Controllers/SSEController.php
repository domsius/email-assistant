<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SSEController extends Controller
{
    public function stream(Request $request)
    {
        return new StreamedResponse(function () use ($request) {
            $lastId = 0;
            
            while (true) {
                // Check for new emails
                $newEmails = EmailMessage::whereHas('emailAccount', function ($query) use ($request) {
                    $query->where('company_id', $request->user()->company_id);
                })
                ->where('id', '>', $lastId)
                ->where('folder', 'INBOX')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

                if ($newEmails->isNotEmpty()) {
                    foreach ($newEmails as $email) {
                        echo "data: " . json_encode([
                            'id' => $email->id,
                            'subject' => $email->subject,
                            'sender_email' => $email->sender_email,
                            'sender_name' => $email->sender_name,
                            'snippet' => $email->snippet,
                            'received_at' => $email->received_at,
                        ]) . "\n\n";
                        
                        $lastId = max($lastId, $email->id);
                    }
                    
                    ob_flush();
                    flush();
                }
                
                // Wait 2 seconds before checking again
                sleep(2);
                
                // Heartbeat to keep connection alive
                echo ": heartbeat\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}