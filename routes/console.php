<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule email syncing for all active accounts every minute
Schedule::command('sync:emails --all --limit=50 --batch-size=10 --no-interaction')
    ->everyMinute()
    ->name('sync-all-emails')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/email-sync.log'));

// Process pending emails every 5 minutes
Schedule::command('process:incoming-emails')
    ->everyFiveMinutes()
    ->name('process-emails')
    ->withoutOverlapping();

// Clean up old audit logs monthly (keep last 6 months)
Schedule::call(function () {
    \App\Models\AuditLog::where('created_at', '<', now()->subMonths(6))->delete();
})->monthly()->name('cleanup-audit-logs');
