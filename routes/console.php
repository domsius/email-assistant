<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
