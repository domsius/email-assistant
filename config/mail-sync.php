<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Sync Email Limit
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum number of emails to sync when
    | fetching emails from external providers (Gmail, Outlook). This helps
    | limit the amount of data synced, especially for initial syncs.
    | The system will fetch the most recent emails up to this limit.
    |
    */
    'sync_email_limit' => env('MAIL_SYNC_EMAIL_LIMIT', 200),
];
