<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use App\Services\LanguageDetectionService;
use Illuminate\Console\Command;

class ProcessLanguageDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:detect-languages {--limit=100 : Number of emails to process} {--force : Re-process emails that already have language detection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and update languages for existing emails';

    private LanguageDetectionService $languageService;

    public function __construct(LanguageDetectionService $languageService)
    {
        parent::__construct();
        $this->languageService = $languageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $force = $this->option('force');

        // Get emails based on force option
        if ($force) {
            $emails = EmailMessage::orderBy('received_at', 'desc')
                ->limit($limit)
                ->get();
        } else {
            $emails = EmailMessage::whereNull('detected_language')
                ->orderBy('received_at', 'desc')
                ->limit($limit)
                ->get();
        }

        if ($emails->isEmpty()) {
            $this->info('No emails found without language detection.');

            return 0;
        }

        $this->info("Processing language detection for {$emails->count()} emails...");

        $processed = 0;
        $progressBar = $this->output->createProgressBar($emails->count());
        $progressBar->start();

        foreach ($emails as $email) {
            try {
                // Combine subject and body for language detection
                $textToAnalyze = $email->subject.' '.$email->body_content;

                // Detect language
                $result = $this->languageService->detectLanguage($textToAnalyze);

                // Update email with detected language
                $email->update([
                    'detected_language' => $result['primary_language'],
                    'language_confidence' => $result['confidence'],
                ]);

                $processed++;
                $progressBar->advance();

            } catch (\Exception $e) {
                $this->error("Error processing email {$email->id}: ".$e->getMessage());
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Language detection completed!');
        $this->info("Processed: {$processed} emails");

        // Show language distribution
        $this->showLanguageDistribution();

        return 0;
    }

    private function showLanguageDistribution()
    {
        $this->newLine();
        $this->info('Language distribution:');

        $distribution = EmailMessage::whereNotNull('detected_language')
            ->selectRaw('detected_language, COUNT(*) as count')
            ->groupBy('detected_language')
            ->orderBy('count', 'desc')
            ->get();

        $headers = ['Language', 'Count', 'Percentage'];
        $total = $distribution->sum('count');

        $rows = $distribution->map(function ($item) use ($total) {
            $languageName = $this->languageService->getLanguageName($item->detected_language);
            $percentage = round(($item->count / $total) * 100, 1);

            return [
                $languageName." ({$item->detected_language})",
                $item->count,
                $percentage.'%',
            ];
        })->toArray();

        $this->table($headers, $rows);
    }
}
