<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FetchJiraTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:fetch-tasks 
                            {--domain= : JIRA domain (e.g., yourcompany.atlassian.net)}
                            {--email= : JIRA account email}
                            {--token= : JIRA API token}
                            {--project= : JIRA project key (optional)}
                            {--jql= : Custom JQL query (optional)}
                            {--output=storage/app/jira-tasks.json : Output file path}
                            {--max=50 : Maximum number of tasks to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch JIRA tasks and save them as JSON';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domain = $this->option('domain') ?: env('JIRA_DOMAIN');
        $email = $this->option('email') ?: env('JIRA_EMAIL');
        $token = $this->option('token') ?: env('ATLASSIAN_API_TOKEN');
        
        if (!$domain || !$email || !$token) {
            $this->error('JIRA credentials are required. Provide them via options or environment variables.');
            $this->info('Required: --domain, --email, --token');
            $this->info('Or set: JIRA_DOMAIN, JIRA_EMAIL, ATLASSIAN_API_TOKEN in .env');
            return 1;
        }

        $project = $this->option('project');
        $customJql = $this->option('jql');
        $outputPath = $this->option('output');
        $maxResults = $this->option('max');

        // Build JQL query
        $jql = $this->buildJqlQuery($customJql, $project);
        
        $this->info("Fetching JIRA tasks from: {$domain}");
        $this->info("JQL Query: {$jql}");
        
        try {
            // Fetch tasks from JIRA
            $tasks = $this->fetchJiraTasks($domain, $email, $token, $jql, $maxResults);
            
            if (empty($tasks)) {
                $this->warn('No tasks found matching the criteria.');
                return 0;
            }
            
            // Process and format tasks
            $formattedTasks = $this->formatTasks($tasks);
            
            // Save to JSON file
            $this->saveToJsonFile($formattedTasks, $outputPath);
            
            $this->info("Successfully fetched {$formattedTasks['total']} tasks");
            $this->info("Tasks saved to: {$outputPath}");
            
            // Display summary
            $this->displaySummary($formattedTasks);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Failed to fetch JIRA tasks: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Build JQL query
     */
    private function buildJqlQuery(?string $customJql, ?string $project): string
    {
        if ($customJql) {
            return $customJql;
        }
        
        $jql = 'ORDER BY updated DESC';
        
        if ($project) {
            $jql = "project = {$project} " . $jql;
        }
        
        return $jql;
    }
    
    /**
     * Fetch tasks from JIRA API with pagination support
     */
    private function fetchJiraTasks(string $domain, string $email, string $token, string $jql, int $maxResults): array
    {
        $url = "https://{$domain}/rest/api/3/search";
        $allIssues = [];
        $startAt = 0;
        $pageSize = min(100, $maxResults); // Jira API max is 100 per page
        
        $this->info('Fetching tasks with pagination...');
        
        while (count($allIssues) < $maxResults) {
            $currentPageSize = min($pageSize, $maxResults - count($allIssues));
            
            $ch = curl_init();
            
            $queryParams = http_build_query([
                'jql' => $jql,
                'startAt' => $startAt,
                'maxResults' => $currentPageSize,
                'fields' => 'summary,status,assignee,reporter,priority,created,updated,description,issuetype,project,components,labels,fixVersions,customfield_10016,timetracking,comment'
            ]);
            
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$url}?{$queryParams}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ],
                CURLOPT_USERPWD => "{$email}:{$token}",
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new \Exception('CURL Error: ' . curl_error($ch));
            }
            
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $errorMsg = json_decode($response, true);
                throw new \Exception("JIRA API Error (HTTP {$httpCode}): " . ($errorMsg['errorMessages'][0] ?? 'Unknown error'));
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JIRA response: ' . json_last_error_msg());
            }
            
            $issues = $data['issues'] ?? [];
            $total = $data['total'] ?? 0;
            
            if (empty($issues)) {
                break; // No more issues to fetch
            }
            
            $allIssues = array_merge($allIssues, $issues);
            $this->info(sprintf('Fetched %d/%d tasks (total available: %d)', count($allIssues), $maxResults, $total));
            
            // Check if we've fetched all available issues
            if (count($allIssues) >= $total) {
                break;
            }
            
            $startAt += count($issues);
        }
        
        return $allIssues;
    }
    
    /**
     * Format JIRA tasks for better readability
     */
    private function formatTasks(array $tasks): array
    {
        $formatted = [
            'fetched_at' => now()->toIso8601String(),
            'total' => count($tasks),
            'tasks' => []
        ];
        
        foreach ($tasks as $task) {
            $fields = $task['fields'];
            
            $formatted['tasks'][] = [
                'key' => $task['key'],
                'id' => $task['id'],
                'summary' => $fields['summary'] ?? '',
                'description' => $fields['description'] ?? '',
                'status' => [
                    'name' => $fields['status']['name'] ?? '',
                    'category' => $fields['status']['statusCategory']['name'] ?? ''
                ],
                'priority' => [
                    'name' => $fields['priority']['name'] ?? 'None',
                    'id' => $fields['priority']['id'] ?? null
                ],
                'type' => [
                    'name' => $fields['issuetype']['name'] ?? '',
                    'subtask' => $fields['issuetype']['subtask'] ?? false
                ],
                'project' => [
                    'key' => $fields['project']['key'] ?? '',
                    'name' => $fields['project']['name'] ?? ''
                ],
                'assignee' => [
                    'name' => $fields['assignee']['displayName'] ?? null,
                    'email' => $fields['assignee']['emailAddress'] ?? null,
                    'accountId' => $fields['assignee']['accountId'] ?? null
                ],
                'reporter' => [
                    'name' => $fields['reporter']['displayName'] ?? null,
                    'email' => $fields['reporter']['emailAddress'] ?? null,
                    'accountId' => $fields['reporter']['accountId'] ?? null
                ],
                'created' => $fields['created'] ?? null,
                'updated' => $fields['updated'] ?? null,
                'labels' => $fields['labels'] ?? [],
                'components' => array_map(fn($c) => $c['name'] ?? '', $fields['components'] ?? []),
                'story_points' => $fields['customfield_10016'] ?? null,
                'time_tracking' => [
                    'originalEstimate' => $fields['timetracking']['originalEstimate'] ?? null,
                    'remainingEstimate' => $fields['timetracking']['remainingEstimate'] ?? null,
                    'timeSpent' => $fields['timetracking']['timeSpent'] ?? null
                ],
                'comments_count' => $fields['comment']['total'] ?? 0,
                'url' => "https://" . ($this->option('domain') ?: env('JIRA_DOMAIN')) . "/browse/{$task['key']}"
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Save formatted tasks to JSON file
     */
    private function saveToJsonFile(array $tasks, string $filePath): void
    {
        $directory = dirname($filePath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $json = json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to encode tasks to JSON: ' . json_last_error_msg());
        }
        
        if (file_put_contents($filePath, $json) === false) {
            throw new \Exception("Failed to write JSON to file: {$filePath}");
        }
    }
    
    /**
     * Display summary of fetched tasks
     */
    private function displaySummary(array $tasks): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tasks', $tasks['total']],
                ['Fetched At', $tasks['fetched_at']],
                ['Status Distribution', $this->getStatusDistribution($tasks['tasks'])],
                ['Priority Distribution', $this->getPriorityDistribution($tasks['tasks'])],
                ['Type Distribution', $this->getTypeDistribution($tasks['tasks'])]
            ]
        );
    }
    
    private function getStatusDistribution(array $tasks): string
    {
        $statuses = array_count_values(array_column(array_column($tasks, 'status'), 'name'));
        return collect($statuses)->map(fn($count, $status) => "{$status}: {$count}")->implode(', ');
    }
    
    private function getPriorityDistribution(array $tasks): string
    {
        $priorities = array_count_values(array_column(array_column($tasks, 'priority'), 'name'));
        return collect($priorities)->map(fn($count, $priority) => "{$priority}: {$count}")->implode(', ');
    }
    
    private function getTypeDistribution(array $tasks): string
    {
        $types = array_count_values(array_column(array_column($tasks, 'type'), 'name'));
        return collect($types)->map(fn($count, $type) => "{$type}: {$count}")->implode(', ');
    }
}
