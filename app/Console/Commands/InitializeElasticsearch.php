<?php

namespace App\Console\Commands;

use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class InitializeElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize Elasticsearch index for document storage';

    /**
     * Execute the console command.
     */
    public function handle(ElasticsearchService $elasticsearch)
    {
        $this->info('Checking Elasticsearch connection...');

        if (! $elasticsearch->isAvailable()) {
            $this->error('Elasticsearch is not available. Please ensure it is running.');

            return 1;
        }

        $this->info('Elasticsearch is available.');

        try {
            $this->info('Initializing Elasticsearch index...');
            $elasticsearch->initializeIndex();
            $this->info('Elasticsearch index initialized successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to initialize index: '.$e->getMessage());

            return 1;
        }
    }
}
