import React, { useCallback, useState } from "react";
import { useInbox } from "@/contexts/inbox-context";
import { EmailMessage } from "@/types/inbox";
import { GmailEmailTable } from "./gmail-email-table";
import { GmailAccordionInbox } from "./gmail-accordion-inbox";
import { EmailDetailView } from "./email-detail-view";
import { ComposeDialog } from "./compose-dialog";
import { SearchBar } from "./search-bar";
import { EmailToolbar } from "./toolbar/email-toolbar";
import { PaginationControls } from "./pagination-controls";
import { EmailListSkeleton } from "./email-list/email-list-skeleton";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { TooltipProvider } from "@/components/ui/tooltip";

interface GmailInboxContentProps {
  pagination?: any;
}

export function GmailInboxContent({ pagination }: GmailInboxContentProps) {
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
  } = useInbox();

  const [selectedEmailForDetail, setSelectedEmailForDetail] = useState<EmailMessage | null>(null);

  const handleEmailSelect = useCallback((email: EmailMessage) => {
    setSelectedEmail(email);
    setSelectedEmailForDetail(email);
    setViewMode("detail");
  }, [setSelectedEmail, setViewMode]);

  const handleBackToList = useCallback(() => {
    setViewMode("list");
    setSelectedEmailForDetail(null);
  }, [setViewMode]);

  const handleTabChange = useCallback((value: string) => {
    setActiveFilter(value);
    // Navigation logic - similar to original implementation
    const currentUrl = new URL(window.location.href);
    const params = currentUrl.searchParams;
    params.set("filter", value);
    
    const newUrl = `/inbox?${params.toString()}`;
    window.history.replaceState({}, "", newUrl);
  }, [setActiveFilter]);


  return (
    <TooltipProvider delayDuration={0}>
      <div className="flex flex-col h-full">
        <Tabs
          value={activeFilter}
          onValueChange={handleTabChange}
          className="h-full flex flex-col"
        >
          {/* Header */}
          <div className="flex-shrink-0">
            <SearchBar />
            <EmailToolbar />
          </div>

          {/* Main Content Area */}
          <div className="flex-1 overflow-hidden">
            <TabsContent value="all" className="m-0 h-full flex flex-col">
              {isLoading ? (
                <EmailListSkeleton />
              ) : viewMode === "detail" && selectedEmailForDetail ? (
                <EmailDetailView 
                  email={selectedEmailForDetail} 
                  onBackToList={handleBackToList}
                />
              ) : (
                <>
                  <div className="flex-1 overflow-hidden">
                    {activeFolder === "inbox" ? (
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
                  </div>
                  {pagination && (
                    <div className="flex-shrink-0 border-t">
                      <PaginationControls pagination={pagination} />
                    </div>
                  )}
                </>
              )}
            </TabsContent>
            
            <TabsContent value="unread" className="m-0 h-full flex flex-col">
              {isLoading ? (
                <EmailListSkeleton />
              ) : viewMode === "detail" && selectedEmailForDetail ? (
                <EmailDetailView 
                  email={selectedEmailForDetail} 
                  onBackToList={handleBackToList}
                />
              ) : (
                <>
                  <div className="flex-1 overflow-hidden">
                    <GmailEmailTable
                      emails={emails}
                      selectedEmail={selectedEmail}
                      onEmailSelect={handleEmailSelect}
                      onBackToList={handleBackToList}
                      view={viewMode}
                    />
                  </div>
                  {pagination && (
                    <div className="flex-shrink-0 border-t">
                      <PaginationControls pagination={pagination} />
                    </div>
                  )}
                </>
              )}
            </TabsContent>
          </div>
        </Tabs>
      </div>
      
      {/* Compose Dialog */}
      {isComposing && composeData && (
        <ComposeDialog
          composeData={composeData}
          originalEmail={composeData.originalEmail}
          draftId={composeData.draftId}
        />
      )}
    </TooltipProvider>
  );
}