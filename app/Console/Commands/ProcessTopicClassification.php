<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use App\Services\TopicClassificationService;
use Illuminate\Console\Command;

class ProcessTopicClassification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:classify-topics {--limit=100 : Number of emails to process} {--force : Re-process emails that already have topic classification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify topics for existing emails';

    private TopicClassificationService $topicService;

    public function __construct(TopicClassificationService $topicService)
    {
        parent::__construct();
        $this->topicService = $topicService;
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
            $emails = EmailMessage::whereNull('topic_id')
                ->orderBy('received_at', 'desc')
                ->limit($limit)
                ->get();
        }

        if ($emails->isEmpty()) {
            $this->info('No emails found without topic classification.');

            return 0;
        }

        $this->info("Processing topic classification for {$emails->count()} emails...");

        $processed = 0;
        $progressBar = $this->output->createProgressBar($emails->count());
        $progressBar->start();

        foreach ($emails as $email) {
            try {
                // Classify topic
                $result = $this->topicService->classifyTopic($email->subject, $email->body_content);

                // Update email with classified topic
                $email->update([
                    'topic_id' => $result['topic_id'],
                    'topic_confidence' => $result['confidence'],
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
        $this->info('Topic classification completed!');
        $this->info("Processed: {$processed} emails");

        // Show topic distribution
        $this->showTopicDistribution();

        return 0;
    }

    private function showTopicDistribution()
    {
        $this->newLine();
        $this->info('Topic distribution:');

        $distribution = EmailMessage::whereNotNull('topic_id')
            ->join('topics', 'email_messages.topic_id', '=', 'topics.id')
            ->selectRaw('topics.name, topics.color, COUNT(*) as count')
            ->groupBy('topics.id', 'topics.name', 'topics.color')
            ->orderBy('count', 'desc')
            ->get();

        $headers = ['Topic', 'Count', 'Percentage'];
        $total = $distribution->sum('count');

        if ($total > 0) {
            $rows = $distribution->map(function ($item) use ($total) {
                $percentage = round(($item->count / $total) * 100, 1);

                return [
                    $item->name,
                    $item->count,
                    $percentage.'%',
                ];
            })->toArray();

            $this->table($headers, $rows);
        } else {
            $this->info('No emails with topic classification found.');
        }
    }
}
