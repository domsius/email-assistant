<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Sync Email Limit
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum number of emails to sync during
    | the initial synchronization when a new email account is connected.
    | 
    | - Initial sync: Fetches the most recent emails up to this limit (default: 200)
    | - After initial sync: Real-time updates via webhooks (Gmail) or periodic sync
    | - The limit applies PER ACCOUNT during initial setup
    | 
    | Note: Gmail webhooks are automatically configured after initial sync
    | to receive real-time notifications for new emails.
    |
    */
    'sync_email_limit' => env('MAIL_SYNC_EMAIL_LIMIT', 200),
];
