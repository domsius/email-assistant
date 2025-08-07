<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Only show documents uploaded by the current user
        $documents = Document::where('company_id', $companyId)
            ->where('uploaded_by', $user->id)
            ->with('uploadedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'filename' => $doc->filename,
                    'type' => pathinfo($doc->filename, PATHINFO_EXTENSION) ?: 'txt',
                    'size' => $doc->file_size,
                    'status' => $doc->status,
                    'chunks' => $doc->chunk_count ?? 0,
                    'embeddings' => $doc->chunk_count ?? 0,
                    'uploadedAt' => $doc->created_at->toISOString(),
                    'processedAt' => $doc->status === 'processed' && $doc->updated_at ? $doc->updated_at->toISOString() : null,
                    'error' => $doc->error_message,
                ];
            });

        $stats = [
            'totalDocuments' => $documents->count(),
            'processedDocuments' => $documents->where('status', 'processed')->count(),
            'totalChunks' => $documents->sum('chunk_count'),
            'totalEmbeddings' => $documents->sum('chunk_count'),
            'storageUsed' => $documents->sum('file_size'),
            'storageLimit' => 1073741824, // 1GB
        ];

        return Inertia::render('knowledge-base', [
            'documents' => $documents,
            'stats' => $stats,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:10240', // 10MB max
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $user = $request->user();
            $file = $request->file('file');
            
            // Store file
            $path = $file->store('documents/' . $user->company_id, 'local');
            
            // Create document record
            $document = Document::create([
                'company_id' => $user->company_id,
                'uploaded_by' => $user->id,
                'title' => $request->title ?: $file->getClientOriginalName(),
                'filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $request->description,
                'status' => 'pending',
            ]);

            // Process document asynchronously
            ProcessDocument::dispatch($document);

            return redirect()->route('knowledge-base')->with('success', 'Document uploaded successfully and is being processed.');

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return redirect()->route('knowledge-base')->with('error', 'Failed to upload document: ' . $e->getMessage());
        }
    }

    public function destroy(Document $document)
    {
        $user = request()->user();
        
        // Ensure user can only delete documents they uploaded
        if ($document->company_id !== $user->company_id || $document->uploaded_by !== $user->id) {
            return redirect()->route('knowledge-base')->with('error', 'Unauthorized access.');
        }

        try {
            // Delete all chunks from Elasticsearch
            foreach ($document->chunks as $chunk) {
                if ($chunk->elasticsearch_index) {
                    app(ElasticsearchService::class)->deleteDocument($chunk->elasticsearch_index);
                }
            }
            
            // Delete file from storage
            Storage::delete($document->file_path);
            
            // Delete document and chunks from database
            $document->chunks()->delete();
            $document->delete();

            return redirect()->route('knowledge-base')->with('success', 'Document deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Document deletion failed', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
            ]);

            return redirect()->route('knowledge-base')->with('error', 'Failed to delete document: ' . $e->getMessage());
        }
    }

    public function reprocess(Document $document)
    {
        $user = request()->user();
        
        // Ensure user can only reprocess documents they uploaded
        if ($document->company_id !== $user->company_id || $document->uploaded_by !== $user->id) {
            return redirect()->route('knowledge-base')->with('error', 'Unauthorized access.');
        }

        try {
            $document->update(['status' => 'pending']);
            ProcessDocument::dispatch($document);

            return redirect()->route('knowledge-base')->with('success', 'Document queued for reprocessing.');
        } catch (\Exception $e) {
            return redirect()->route('knowledge-base')->with('error', 'Failed to reprocess document: ' . $e->getMessage());
        }
    }
}