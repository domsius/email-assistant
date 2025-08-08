import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

export function useRealtimeEmails(companyId: number, folder: string = 'INBOX') {
  useEffect(() => {
    // Echo is already initialized in echo.ts
    if (!window.Echo) {
      console.error('Laravel Echo not initialized');
      return;
    }

    // Subscribe to company's email channel
    const channel = window.Echo.private(`company.${companyId}.emails`);
    
    // Listen for new emails
    channel.listen('.email.received', (e: any) => {
      console.log('New email received:', e);
      
      // Show notification
      toast.success('New email received', {
        description: `From: ${e.sender_name || e.sender_email}`,
        duration: 5000,
      });
      
      // Reload only if viewing the same folder
      if (e.folder === folder) {
        // Reload email list preserving scroll position
        router.reload({
          only: ['emails', 'pagination'],
          preserveScroll: true,
          preserveState: true,
        });
      }
    });

    // Cleanup on unmount
    return () => {
      window.Echo.leave(`company.${companyId}.emails`);
    };
  }, [companyId, folder]);
}