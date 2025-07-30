import React, { useCallback } from "react";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { Separator } from "@/components/ui/separator";
import { TooltipProvider } from "@/components/ui/tooltip";
import {
  ResizableHandle,
  ResizablePanel,
  ResizablePanelGroup,
} from "@/components/ui/resizable";
import { EmailPreview } from "@/components/ui/email-preview";
import { MailOpen } from "lucide-react";
import { EmailList } from "./email-list/email-list";
import { EmailListSkeleton } from "./email-list/email-list-skeleton";
import { EmailPreviewSkeleton } from "./email-preview/email-preview-skeleton";
import { EmailToolbar } from "./toolbar/email-toolbar";
import { SearchBar } from "./search-bar";
import { PaginationControls } from "./pagination-controls";
import { EmailListHeader } from "./email-list-header";
import { SidebarPanel } from "./sidebar-panel";
import { useInbox } from "@/contexts/inbox-context";
import { router } from "@inertiajs/react";
import { ComposePanel } from "./compose-panel";
import type { PaginationLinks, PaginationMeta } from "@/types/inbox";

interface InboxContentProps {
  pagination?: {
    links: PaginationLinks;
    meta: PaginationMeta;
  };
}

export function InboxContent({ pagination }: InboxContentProps) {
  const {
    selectedEmail,
    emails,
    isLoading,
    activeFilter,
    setActiveFilter,
    activeFolder,
    selectedAccount,
    searchQuery,
    isComposing,
    composeData,
    enterComposeMode,
  } = useInbox();

  const handleReply = useCallback(() => {
    if (selectedEmail) {
      enterComposeMode({
        to: selectedEmail.senderEmail,
        subject: `Re: ${selectedEmail.subject}`,
        body: "",
        action: "reply",
        inReplyTo: selectedEmail.id.toString(),
        originalEmail: selectedEmail,
      });
    }
  }, [selectedEmail, enterComposeMode]);

  const handleReplyAll = useCallback(() => {
    if (selectedEmail) {
      enterComposeMode({
        to: selectedEmail.senderEmail,
        subject: `Re: ${selectedEmail.subject}`,
        body: "",
        action: "replyAll",
        inReplyTo: selectedEmail.id.toString(),
        originalEmail: selectedEmail,
      });
    }
  }, [selectedEmail, enterComposeMode]);

  const handleForward = useCallback(() => {
    if (selectedEmail) {
      enterComposeMode({
        to: "",
        subject: `Fwd: ${selectedEmail.subject}`,
        body: "",
        action: "forward",
        originalEmail: selectedEmail,
      });
    }
  }, [selectedEmail, enterComposeMode]);

  const handleEditDraft = useCallback(async () => {
    if (selectedEmail && selectedEmail.isDraft) {
      // Fetch full draft content if needed
      let fullEmail = selectedEmail;
      if (!selectedEmail.content || selectedEmail.content === "") {
        try {
          const response = await fetch(`/api/emails/${selectedEmail.id}`, {
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest",
              "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: "same-origin",
          });

          if (response.ok) {
            const emailData = await response.json();
            fullEmail = {
              ...selectedEmail,
              content: emailData.body_html || emailData.body_plain || emailData.body_content || "",
              plainTextContent: emailData.body_plain || emailData.body_content || "",
              recipients: emailData.recipients,
              cc_recipients: emailData.cc_recipients,
              bcc_recipients: emailData.bcc_recipients,
              to: emailData.recipients || emailData.to || "",
              cc: emailData.cc_recipients || emailData.cc || "",
              bcc: emailData.bcc_recipients || emailData.bcc || "",
            };
          }
        } catch (error) {
          console.error("Failed to fetch draft content:", error);
          return;
        }
      }

      // Enter compose mode with draft data and show original draft below
      enterComposeMode({
        to: fullEmail.to || fullEmail.recipients || "",
        cc: fullEmail.cc || fullEmail.cc_recipients || "",
        bcc: fullEmail.bcc || fullEmail.bcc_recipients || "",
        subject: fullEmail.subject || "",
        body: "", // Empty body for new content
        action: "draft" as const,
        draftId: typeof fullEmail.id === 'string' && fullEmail.id.startsWith('draft-')
          ? parseInt(fullEmail.id.replace('draft-', ''))
          : fullEmail.draftId,
        originalEmail: {
          ...fullEmail,
          sender: fullEmail.sender || "Draft",
          senderEmail: fullEmail.senderEmail || "",
          receivedAt: fullEmail.receivedAt || new Date().toISOString(),
          content: fullEmail.content || fullEmail.plainTextContent || "",
        },
      });
    }
  }, [selectedEmail, enterComposeMode]);

  const handleDownloadAttachment = useCallback(
    (attachmentId: string) => {
      // Create a download link for the attachment
      window.open(
        `/api/emails/${selectedEmail?.id}/attachments/${attachmentId}/download`,
        "_blank",
      );
    },
    [selectedEmail],
  );

  const handleTabChange = useCallback(
    (value: string) => {
      setActiveFilter(value);
      // Navigate with the new filter
      const currentUrl = new URL(window.location.href);
      const params = currentUrl.searchParams;

      // Clear all params and only set non-empty values
      params.delete("account");
      params.delete("search");
      params.delete("page"); // Reset to page 1 when changing filters

      params.set("folder", activeFolder);
      params.set("filter", value);

      // Preserve the current per_page parameter if it exists
      const currentPerPage = params.get("per_page");
      if (currentPerPage) {
        params.set("per_page", currentPerPage);
      }
      if (selectedAccount) {
        params.set("account", selectedAccount.toString());
      }
      if (searchQuery) {
        params.set("search", searchQuery);
      }

      const newUrl = `/inbox?${params.toString()}`;

      router.get(newUrl, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
        only: ["emails", "folders", "currentFilter", "pagination"],
      });
    },
    [setActiveFilter, activeFolder, selectedAccount, searchQuery],
  );

  return (
    <TooltipProvider delayDuration={0}>
      <ResizablePanelGroup
        direction="horizontal"
        onLayout={(sizes: number[]) => {
          document.cookie = `react-resizable-panels:layout=${JSON.stringify(sizes)}`;
        }}
        className="h-full items-stretch"
      >
        <SidebarPanel />

        <ResizableHandle withHandle />

        <ResizablePanel defaultSize={30} minSize={20} className="flex flex-col">
          <Tabs
            value={activeFilter}
            onValueChange={handleTabChange}
            className="h-full flex flex-col"
          >
            <EmailListHeader currentTab={activeFilter} />
            <Separator />
            <SearchBar />
            <EmailToolbar />
            <TabsContent value="all" className="m-0 h-full flex flex-col">
              {isLoading ? <EmailListSkeleton /> : <EmailList />}
              <PaginationControls pagination={pagination} />
            </TabsContent>
            <TabsContent value="unread" className="m-0 h-full flex flex-col">
              {isLoading ? <EmailListSkeleton /> : <EmailList />}
              <PaginationControls pagination={pagination} />
            </TabsContent>
          </Tabs>
        </ResizablePanel>

        <ResizableHandle withHandle />

        <ResizablePanel defaultSize={50} className="flex flex-col">
          {isComposing && composeData ? (
            <ComposePanel
              composeData={composeData}
              originalEmail={composeData.originalEmail}
              draftId={composeData.draftId}
            />
          ) : isLoading ? (
            <EmailPreviewSkeleton />
          ) : selectedEmail ? (
            <EmailPreview
              email={{
                ...selectedEmail,
                plainTextContent: selectedEmail.plainTextContent || undefined,
                attachments: selectedEmail.attachments || undefined,
              }}
              onReply={handleReply}
              onReplyAll={handleReplyAll}
              onForward={handleForward}
              onDownloadAttachment={handleDownloadAttachment}
              onEditDraft={handleEditDraft}
            />
          ) : (
            <div className="flex h-full items-center justify-center p-8 text-center">
              <div className="mx-auto flex max-w-[420px] flex-col items-center justify-center text-center">
                <MailOpen className="h-12 w-12 text-muted-foreground" />
                <h3 className="mt-4 text-lg font-semibold">
                  No message selected
                </h3>
                <p className="mb-4 mt-2 text-sm text-muted-foreground">
                  Select a message from the list to read it here.
                </p>
              </div>
            </div>
          )}
        </ResizablePanel>
      </ResizablePanelGroup>
    </TooltipProvider>
  );
}
