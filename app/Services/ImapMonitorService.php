<?php

namespace App\Services;

use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class ImapMonitorService
{
    /**
     * Check if IMAP server supports IDLE
     */
    public static function supportsIdle(EmailAccount $account): bool
    {
        $cacheKey = "imap_idle_support_{$account->id}";
        
        return Cache::remember($cacheKey, 86400, function () use ($account) {
            try {
                $manager = new ClientManager();
                $username = $account->imap_username ?? $account->email_address;
                
                $config = [
                    'host' => $account->imap_host,
                    'port' => $account->imap_port ?? 993,
                    'encryption' => $account->imap_encryption ?? 'ssl',
                    'validate_cert' => $account->imap_validate_cert ?? true,
                    'username' => $username,
                    'password' => $account->imap_password,
                    'protocol' => 'imap',
                    'authentication' => 'login',
                ];

                $client = $manager->make($config);
                $client->connect();
                
                // Check if IDLE is in the capability list
                $capabilities = $client->getCapabilities();
                $supportsIdle = in_array('IDLE', $capabilities);
                
                $client->disconnect();
                
                Log::info('IMAP IDLE support check', [
                    'account_id' => $account->id,
                    'email' => $account->email_address,
                    'supports_idle' => $supportsIdle,
                    'capabilities' => $capabilities,
                ]);
                
                return $supportsIdle;
            } catch (\Exception $e) {
                Log::error('Failed to check IMAP IDLE support', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        });
    }

    /**
     * Get optimal sync interval based on email activity
     */
    public static function getOptimalSyncInterval(EmailAccount $account): int
    {
        // Check recent email activity
        $recentEmailCount = $account->emailMessages()
            ->where('received_at', '>', Carbon::now()->subHours(24))
            ->count();
        
        // Adaptive intervals based on activity
        if ($recentEmailCount > 50) {
            return 1; // Very active: sync every minute
        } elseif ($recentEmailCount > 20) {
            return 2; // Active: sync every 2 minutes
        } elseif ($recentEmailCount > 5) {
            return 5; // Moderate: sync every 5 minutes
        } else {
            return 10; // Low activity: sync every 10 minutes
        }
    }

    /**
     * Should this account be synced now?
     */
    public static function shouldSync(EmailAccount $account): bool
    {
        if (!$account->last_sync_at) {
            return true; // Never synced
        }
        
        $interval = self::getOptimalSyncInterval($account);
        $nextSync = $account->last_sync_at->addMinutes($interval);
        
        return Carbon::now()->isAfter($nextSync);
    }

    /**
     * Trigger a sync for the account
     */
    public static function triggerSync(EmailAccount $account, array $options = []): void
    {
        $defaultOptions = [
            'quick_sync' => true,
            'limit' => 10,
            'fetch_all' => false,
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        Log::info('Triggering IMAP sync', [
            'account_id' => $account->id,
            'email' => $account->email_address,
            'options' => $options,
        ]);
        
        SyncEmailAccountJob::dispatch($account, $options);
        
        // Update last sync time
        $account->update(['last_sync_at' => now()]);
    }

    /**
     * Get sync statistics for monitoring
     */
    public static function getSyncStats(EmailAccount $account): array
    {
        $stats = Cache::remember("imap_sync_stats_{$account->id}", 300, function () use ($account) {
            $last24h = Carbon::now()->subHours(24);
            
            return [
                'total_emails' => $account->emailMessages()->count(),
                'emails_24h' => $account->emailMessages()
                    ->where('received_at', '>', $last24h)
                    ->count(),
                'unread_count' => $account->emailMessages()
                    ->where('is_read', false)
                    ->count(),
                'last_sync' => $account->last_sync_at?->diffForHumans(),
                'sync_interval' => self::getOptimalSyncInterval($account),
                'supports_idle' => self::supportsIdle($account),
            ];
        });
        
        return $stats;
    }
}