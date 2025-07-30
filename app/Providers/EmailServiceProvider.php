<?php

namespace App\Providers;

use App\Repositories\EmailRepository;
use App\Services\EmailService;
use App\Services\HtmlSanitizerService;
use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register HtmlSanitizerService as singleton
        $this->app->singleton(HtmlSanitizerService::class, function ($app) {
            return new HtmlSanitizerService;
        });

        // Register EmailRepository
        $this->app->bind(EmailRepository::class, function ($app) {
            return new EmailRepository(
                $app->make(\App\Models\EmailMessage::class)
            );
        });

        // Register EmailService
        $this->app->bind(EmailService::class, function ($app) {
            return new EmailService(
                $app->make(EmailRepository::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
