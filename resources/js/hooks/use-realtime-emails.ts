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

    console.log('Setting up WebSocket for company:', companyId, 'folder:', folder);

    // Subscribe to company's email channel
    const channel = window.Echo.private(`company.${companyId}.emails`);
    
    // Add subscription success/error handlers
    channel.subscribed(() => {
      console.log('Successfully subscribed to company.${companyId}.emails channel');
    });

    channel.error((error: any) => {
      console.error('Channel subscription error:', error);
    });
    
    // Listen for new emails
    channel.listen('.email.received', (e: any) => {
      console.log('New email received:', e);
      
      // Show notification
      toast.success('New email received', {
        description: `From: ${e.sender_name || e.sender_email}`,
        duration: 5000,
      });
      
      // Debug info
      console.log('Email folder:', e.folder, 'Current folder:', folder);
      console.log('Router object:', router);
      
      // Force a full page reload for now to ensure updates show
      console.log('Triggering page reload in 500ms...');
      setTimeout(() => {
        window.location.reload();
      }, 500);
    });

    // Cleanup on unmount
    return () => {
      window.Echo.leave(`company.${companyId}.emails`);
    };
  }, [companyId, folder]);
}