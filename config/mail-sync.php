<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Sync Days Limit
    |--------------------------------------------------------------------------
    |
    | This value determines how many days of email history to sync when
    | fetching emails from external providers (Gmail, Outlook). This helps
    | limit the amount of data synced, especially for initial syncs.
    |
    */
    'sync_days_limit' => env('MAIL_SYNC_DAYS_LIMIT', 7),
];