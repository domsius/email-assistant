<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert email_messages ai_analysis to text for encryption
        Schema::table('email_messages', function (Blueprint $table) {
            $table->text('ai_analysis_encrypted')->nullable()->after('ai_analysis');
        });

        // Convert documents metadata to text for encryption
        Schema::table('documents', function (Blueprint $table) {
            $table->text('metadata_encrypted')->nullable()->after('metadata');
        });

        // Convert document_chunks metadata and embedding for encryption
        Schema::table('document_chunks', function (Blueprint $table) {
            $table->text('metadata_encrypted')->nullable()->after('metadata');
            $table->text('embedding_encrypted')->nullable()->after('embedding');
        });

        // Convert emotion_analyses data to text for encryption
        // Skip emotion_analyses as it doesn't have analysis_data column

        // Migrate existing data to encrypted columns
        $this->migrateDataToEncryptedColumns();

        // Drop old unencrypted columns
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('ai_analysis');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'embedding']);
        });

        // Skip emotion_analyses as it doesn't have analysis_data column

        // Rename encrypted columns to original names
        Schema::table('email_messages', function (Blueprint $table) {
            $table->renameColumn('ai_analysis_encrypted', 'ai_analysis');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('metadata_encrypted', 'metadata');
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->renameColumn('metadata_encrypted', 'metadata');
            $table->renameColumn('embedding_encrypted', 'embedding');
        });

        // Skip emotion_analyses as it doesn't have analysis_data column
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive operation - encrypted data cannot be reversed
        // Only proceed if you have backups

        // Convert back to original column types
        Schema::table('email_messages', function (Blueprint $table) {
            $table->json('ai_analysis_original')->nullable()->after('ai_analysis');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->json('metadata_original')->nullable()->after('metadata');
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->json('metadata_original')->nullable()->after('metadata');
            $table->json('embedding_original')->nullable()->after('embedding');
        });

        // Skip emotion_analyses as it doesn't have analysis_data column

        // Drop encrypted columns
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('ai_analysis');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'embedding']);
        });

        // Skip emotion_analyses as it doesn't have analysis_data column

        // Rename columns
        Schema::table('email_messages', function (Blueprint $table) {
            $table->renameColumn('ai_analysis_original', 'ai_analysis');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('metadata_original', 'metadata');
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->renameColumn('metadata_original', 'metadata');
            $table->renameColumn('embedding_original', 'embedding');
        });

        Schema::table('emotion_analyses', function (Blueprint $table) {
            $table->renameColumn('analysis_data_original', 'analysis_data');
        });
    }

    private function migrateDataToEncryptedColumns(): void
    {
        // Migrate email_messages
        DB::table('email_messages')
            ->whereNotNull('ai_analysis')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('email_messages')
                        ->where('id', $record->id)
                        ->update([
                            'ai_analysis_encrypted' => Crypt::encryptString($record->ai_analysis),
                        ]);
                }
            });

        // Migrate documents
        DB::table('documents')
            ->whereNotNull('metadata')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('documents')
                        ->where('id', $record->id)
                        ->update([
                            'metadata_encrypted' => Crypt::encryptString($record->metadata),
                        ]);
                }
            });

        // Migrate document_chunks
        DB::table('document_chunks')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    $updates = [];
                    if ($record->metadata) {
                        $updates['metadata_encrypted'] = Crypt::encryptString($record->metadata);
                    }
                    if ($record->embedding) {
                        $updates['embedding_encrypted'] = Crypt::encryptString($record->embedding);
                    }

                    if (! empty($updates)) {
                        DB::table('document_chunks')
                            ->where('id', $record->id)
                            ->update($updates);
                    }
                }
            });

        // Skip emotion_analyses migration as it doesn't have analysis_data column
    }
};
