import React, { useCallback, useState } from "react";
import { router } from "@inertiajs/react";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { Separator } from "@/components/ui/separator";
import { TooltipProvider } from "@/components/ui/tooltip";
import { EmailListSkeleton } from "./email-list/email-list-skeleton";
import { EmailToolbar } from "./toolbar/email-toolbar";
import { SearchBar } from "./search-bar";
import { PaginationControls } from "./pagination-controls";
import { useInbox } from "@/contexts/inbox-context";
import { ComposeDialog } from "./compose-dialog";
import { GmailEmailTable } from "./gmail-email-table";
import { GmailAccordionInbox } from "./gmail-accordion-inbox";
import { EmailDetailView } from "./email-detail-view";
import type { PaginationLinks, PaginationMeta, EmailMessage } from "@/types/inbox";

interface InboxContentProps {
  pagination?: {
    links: PaginationLinks;
    meta: PaginationMeta;
  };
}

export function InboxContent({ pagination }: InboxContentProps) {
  const {
    emails,
    selectedEmail,
    isLoading,
    activeFilter,
    setActiveFilter,
    activeFolder,
    setSelectedEmail,
    isComposing,
    composeData,
    viewMode,
    setViewMode,
    setSelectedEmails,
  } = useInbox();

  const [selectedEmailForDetail, setSelectedEmailForDetail] = useState<EmailMessage | null>(null);

  const handleEmailSelect = useCallback((email: EmailMessage) => {
    setSelectedEmail(email);
    setSelectedEmailForDetail(email);
    setViewMode("detail");
    // Also select the email in the selection list for toolbar actions
    setSelectedEmails([email.id]);
  }, [setSelectedEmail, setViewMode, setSelectedEmails]);

  const handleBackToList = useCallback(() => {
    setViewMode("list");
    setSelectedEmailForDetail(null);
    // Clear selected emails when going back to list
    setSelectedEmails([]);
  }, [setViewMode, setSelectedEmails]);

  const handleTabChange = useCallback((value: string) => {
    setActiveFilter(value);
    
    // Get current URL parameters
    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);
    
    // Update filter parameter
    params.set('filter', value);
    
    // Keep other parameters like folder, account, search
    const folder = params.get('folder') || 'inbox';
    const account = params.get('account');
    const search = params.get('search');
    const perPage = params.get('per_page');
    
    // Build new URL
    const newParams: Record<string, any> = {
      folder,
      filter: value,
      page: '1', // Reset to first page when changing filter
    };
    
    if (account) newParams.account = account;
    if (search) newParams.search = search;
    if (perPage) newParams.per_page = perPage;
    
    // Navigate to new URL
    router.get('/inbox', newParams, {
      preserveScroll: true,
      preserveState: true,
      only: ['emails', 'pagination'],
    });
  }, [setActiveFilter]);

  if (viewMode === "detail" && selectedEmailForDetail) {
    return (
      <TooltipProvider delayDuration={0}>
        <div className="flex h-full flex-col">
          <div className="flex items-center justify-end px-4 pb-0">
            <SearchBar />
          </div>

          <Separator />

          <EmailToolbar />

          <Separator />
          
          <EmailDetailView
            email={selectedEmailForDetail}
            onBackToList={handleBackToList}
          />
          {isComposing && composeData && (
            <ComposeDialog 
              key="compose-dialog"
              composeData={composeData} 
              originalEmail={composeData.originalEmail}
            />
          )}
        </div>
      </TooltipProvider>
    );
  }

  return (
    <TooltipProvider delayDuration={0}>
      <div className="flex h-full flex-col">
        <Tabs
          value={activeFilter}
          onValueChange={handleTabChange}
          className="h-full flex flex-col"
        >
          <div className="flex items-center justify-end px-4 pb-0">
            <SearchBar />
          </div>

          <Separator />

          <EmailToolbar />

          <Separator />

          <TabsContent
            value={activeFilter}
            className="flex-1 overflow-hidden m-0"
          >
            {isLoading ? (
              <EmailListSkeleton />
            ) : activeFolder === "inbox" ? (
              <GmailAccordionInbox
                emails={emails}
                selectedEmail={selectedEmail}
                onEmailSelect={handleEmailSelect}
                onBackToList={handleBackToList}
                view={viewMode}
              />
            ) : (
              <GmailEmailTable
                emails={emails}
                selectedEmail={selectedEmail}
                onEmailSelect={handleEmailSelect}
                onBackToList={handleBackToList}
                view={viewMode}
              />
            )}
          </TabsContent>

          {pagination && (
            <>
              <Separator />
              <PaginationControls pagination={pagination} />
            </>
          )}
        </Tabs>

        {isComposing && composeData && (
          <ComposeDialog 
            key="compose-dialog"
            composeData={composeData} 
          />
        )}
      </div>
    </TooltipProvider>
  );
}