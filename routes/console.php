<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule email syncing for all active accounts every 10 minutes (full sync)
Schedule::command('sync:emails --all --limit=25 --batch-size=5 --no-interaction')
    ->everyTenMinutes()
    ->name('sync-all-emails')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/email-sync.log'));

// Quick sync for Gmail accounts every 1 minutes (lightweight check)
Schedule::command('gmail:quick-sync --history')
    ->everyMinute()
    ->name('gmail-quick-sync')
    ->withoutOverlapping()
    ->runInBackground();

// Clean up old audit logs monthly (keep last 6 months)
Schedule::call(function () {
    \App\Models\AuditLog::where('created_at', '<', now()->subMonths(6))->delete();
})->monthly()->name('cleanup-audit-logs');

// Renew Gmail push notification watches daily
// Watches expire after 7 days max, so we renew those expiring within 24 hours
Schedule::command('gmail:setup-watches --renew')
    ->daily()
    ->name('renew-gmail-watches')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/gmail-watch.log'));
