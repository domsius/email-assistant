import { useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';

interface UseEmailPollingOptions {
  enabled: boolean;
  folder: string;
  interval?: number;
  maxAttempts?: number;
}

export function useEmailPolling({
  enabled,
  folder,
  interval = 3000,
  maxAttempts = 10,
}: UseEmailPollingOptions) {
  const attemptRef = useRef(0);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const stopPolling = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    attemptRef.current = 0;
  }, []);

  const startPolling = useCallback(() => {
    if (!enabled || intervalRef.current) return;

    attemptRef.current = 0;
    intervalRef.current = setInterval(() => {
      attemptRef.current++;

      // Stop polling after max attempts
      if (attemptRef.current >= maxAttempts) {
        stopPolling();
        return;
      }

      // Reload only email data
      router.reload({
        only: ['emails', 'folders', 'pagination'],
        preserveScroll: true,
        preserveState: true,
      });
    }, interval);
  }, [enabled, interval, maxAttempts, stopPolling]);

  useEffect(() => {
    if (enabled) {
      startPolling();
    } else {
      stopPolling();
    }

    return () => {
      stopPolling();
    };
  }, [enabled, startPolling, stopPolling]);

  return { stopPolling };
}