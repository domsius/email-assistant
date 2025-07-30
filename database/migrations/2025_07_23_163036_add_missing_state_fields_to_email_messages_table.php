<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Check if columns don't already exist before adding
            if (! Schema::hasColumn('email_messages', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('status');
            }

            if (! Schema::hasColumn('email_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('is_deleted');
            }

            if (! Schema::hasColumn('email_messages', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_deleted');
            }

            if (! Schema::hasColumn('email_messages', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_archived');
            }

            if (! Schema::hasColumn('email_messages', 'is_spam')) {
                $table->boolean('is_spam')->default(false)->after('archived_at');
            }

            if (! Schema::hasColumn('email_messages', 'spam_marked_at')) {
                $table->timestamp('spam_marked_at')->nullable()->after('is_spam');
            }

            if (! Schema::hasColumn('email_messages', 'is_sent')) {
                $table->boolean('is_sent')->default(false)->after('spam_marked_at');
            }

            if (! Schema::hasColumn('email_messages', 'recipient_email')) {
                $table->string('recipient_email')->nullable()->after('sender_email');
            }

            if (! Schema::hasColumn('email_messages', 'is_reply')) {
                $table->boolean('is_reply')->default(false)->after('is_sent');
            }

            if (! Schema::hasColumn('email_messages', 'replied_to_message_id')) {
                $table->unsignedBigInteger('replied_to_message_id')->nullable()->after('is_reply');
                $table->foreign('replied_to_message_id')->references('id')->on('email_messages')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropForeign(['replied_to_message_id']);
            $table->dropColumn([
                'archived_at',
                'spam_marked_at',
                'is_sent',
                'recipient_email',
                'is_reply',
                'replied_to_message_id',
            ]);
        });
    }
};
