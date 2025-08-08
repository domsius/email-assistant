import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

export function useSSEEmails() {
  const eventSourceRef = useRef<EventSource | null>(null);

  useEffect(() => {
    // Create SSE connection
    eventSourceRef.current = new EventSource('/api/emails/stream');

    eventSourceRef.current.onmessage = (event) => {
      const email = JSON.parse(event.data);
      
      // Show notification
      toast.success('New email received', {
        description: `From: ${email.sender_name || email.sender_email}`,
      });
      
      // Reload email list
      router.reload({
        only: ['emails', 'folders', 'pagination'],
        preserveScroll: true,
        preserveState: true,
      });
    };

    eventSourceRef.current.onerror = (error) => {
      console.error('SSE error:', error);
      // Reconnect will happen automatically
    };

    // Cleanup on unmount
    return () => {
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
      }
    };
  }, []);
}