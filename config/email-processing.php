<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Processing Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures how emails are processed and stored in the system.
    | For GDPR compliance, you can disable email content storage.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Storage Mode
    |--------------------------------------------------------------------------
    |
    | Determines how email data is stored:
    | - 'full': Store complete email content in database (traditional mode)
    | - 'metadata': Store only metadata, fetch content on-demand (GDPR compliant)
    |
    */
    'storage_mode' => env('EMAIL_STORAGE_MODE', 'metadata'),

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage TTL
    |--------------------------------------------------------------------------
    |
    | Time in seconds to keep email content in temporary cache when viewing
    | emails in metadata-only mode. Default: 5 minutes (300 seconds)
    |
    */
    'temp_storage_ttl' => env('EMAIL_TEMP_STORAGE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Process Metrics
    |--------------------------------------------------------------------------
    |
    | Enable/disable various email processing metrics
    |
    */
    'metrics' => [
        'sentiment_analysis' => env('EMAIL_SENTIMENT_ANALYSIS', true),
        'urgency_detection' => env('EMAIL_URGENCY_DETECTION', true),
        'language_detection' => env('EMAIL_LANGUAGE_DETECTION', true),
        'topic_classification' => env('EMAIL_TOPIC_CLASSIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for email synchronization
    |
    */
    'sync' => [
        'batch_size' => env('EMAIL_SYNC_BATCH_SIZE', 1000),
        'max_age_days' => env('EMAIL_SYNC_MAX_AGE_DAYS', 30), // Only sync emails from last N days
    ],
];
