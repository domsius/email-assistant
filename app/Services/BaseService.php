<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    /**
     * The service name for logging
     */
    protected string $serviceName;

    /**
     * Default retry attempts for operations
     */
    protected int $defaultRetries = 3;

    /**
     * Default retry delay in milliseconds
     */
    protected int $defaultRetryDelay = 1000;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->serviceName = class_basename(static::class);
    }

    /**
     * Execute an operation with error handling and logging
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function executeWithErrorHandling(callable $operation, string $operationName, array $context = [])
    {
        $startTime = microtime(true);

        try {
            $this->logDebug("Starting {$operationName}", $context);

            $result = $operation();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logInfo("{$operationName} completed successfully", array_merge($context, [
                'duration_ms' => $duration,
            ]));

            return $result;

        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logError("{$operationName} failed", array_merge($context, [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => $duration,
                'trace' => $e->getTraceAsString(),
            ]));

            throw $e;
        }
    }

    /**
     * Execute an operation with retry logic
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function executeWithRetry(
        callable $operation,
        string $operationName,
        array $context = [],
        ?int $maxRetries = null,
        ?int $retryDelay = null
    ) {
        $maxRetries = $maxRetries ?? $this->defaultRetries;
        $retryDelay = $retryDelay ?? $this->defaultRetryDelay;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->executeWithErrorHandling($operation, $operationName, array_merge($context, [
                    'attempt' => $attempt,
                    'max_attempts' => $maxRetries,
                ]));

            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    $this->logWarning("{$operationName} failed, retrying", [
                        'attempt' => $attempt,
                        'max_attempts' => $maxRetries,
                        'error' => $e->getMessage(),
                        'retry_delay_ms' => $retryDelay,
                    ]);

                    usleep($retryDelay * 1000); // Convert milliseconds to microseconds
                }
            }
        }

        throw $lastException;
    }

    /**
     * Execute an operation within a database transaction
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function executeInTransaction(callable $operation, string $operationName, array $context = [])
    {
        return DB::transaction(function () use ($operation, $operationName, $context) {
            return $this->executeWithErrorHandling($operation, $operationName, $context);
        });
    }

    /**
     * Execute a batch operation with progress tracking
     */
    protected function executeBatchOperation(
        array $items,
        callable $processor,
        string $operationName,
        int $batchSize = 100
    ): array {
        $total = count($items);
        $processed = 0;
        $failed = 0;
        $errors = [];

        $this->logInfo("Starting batch {$operationName}", [
            'total_items' => $total,
            'batch_size' => $batchSize,
        ]);

        foreach (array_chunk($items, $batchSize) as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;

            try {
                $this->executeInTransaction(function () use ($batch, $processor, &$processed) {
                    foreach ($batch as $item) {
                        $processor($item);
                        $processed++;
                    }
                }, "Batch {$batchNumber} of {$operationName}", [
                    'batch_number' => $batchNumber,
                    'batch_size' => count($batch),
                ]);

            } catch (Exception $e) {
                $failed += count($batch);
                $errors[] = [
                    'batch' => $batchNumber,
                    'error' => $e->getMessage(),
                ];

                $this->logError("Batch {$batchNumber} failed", [
                    'batch_number' => $batchNumber,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->logDebug('Batch progress', [
                'processed' => $processed,
                'failed' => $failed,
                'remaining' => $total - $processed - $failed,
                'progress_percent' => round(($processed + $failed) / $total * 100, 2),
            ]);
        }

        $this->logInfo("Batch {$operationName} completed", [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'errors' => count($errors),
        ]);

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Validate required configuration
     *
     * @throws Exception
     */
    protected function validateConfiguration(array $requiredKeys, string $configPath): void
    {
        foreach ($requiredKeys as $key) {
            $value = config("{$configPath}.{$key}");

            if (is_null($value) || $value === '') {
                throw new Exception("Missing required configuration: {$configPath}.{$key}");
            }
        }
    }

    /**
     * Log debug message
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug("[{$this->serviceName}] {$message}", $context);
    }

    /**
     * Log info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info("[{$this->serviceName}] {$message}", $context);
    }

    /**
     * Log warning message
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning("[{$this->serviceName}] {$message}", $context);
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error("[{$this->serviceName}] {$message}", $context);
    }

    /**
     * Handle API rate limiting
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function handleRateLimit(string $key, int $maxAttempts, int $decayMinutes, callable $callback)
    {
        $attempts = cache()->get($key, 0);

        if ($attempts >= $maxAttempts) {
            $waitTime = cache()->get("{$key}:wait_until");
            if ($waitTime && $waitTime > now()) {
                $seconds = $waitTime->diffInSeconds(now());
                throw new Exception("Rate limit exceeded. Please wait {$seconds} seconds.");
            }
        }

        try {
            $result = $callback();

            // Reset on success
            cache()->forget($key);
            cache()->forget("{$key}:wait_until");

            return $result;

        } catch (Exception $e) {
            // Increment attempts
            cache()->increment($key);
            cache()->put("{$key}:wait_until", now()->addMinutes($decayMinutes), $decayMinutes * 60);

            throw $e;
        }
    }

    /**
     * Measure operation performance
     *
     * @return array ['result' => mixed, 'duration_ms' => float, 'memory_mb' => float]
     */
    protected function measurePerformance(callable $operation, string $operationName): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $operation();

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2);

        $this->logDebug("{$operationName} performance", [
            'duration_ms' => $duration,
            'memory_mb' => $memoryUsed,
        ]);

        return [
            'result' => $result,
            'duration_ms' => $duration,
            'memory_mb' => $memoryUsed,
        ];
    }
}
