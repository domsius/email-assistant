import React from "react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head, usePage } from "@inertiajs/react";
import { InboxProvider } from "@/contexts/inbox-context";
import { InboxContent } from "@/components/inbox/inbox-content";
import { ErrorBoundary } from "@/components/error-boundary";
import { useRealtimeEmails } from "@/hooks/use-realtime-emails";
import type { InboxProps } from "@/types/inbox";

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
  },
  {
    title: "Inbox",
    href: "/inbox",
  },
];

export default function Inbox({
  emails = [],
  emailAccounts = [],
  selectedAccount = null,
  folders = {
    inbox: 0,
    drafts: 0,
    sent: 0,
    junk: 0,
    trash: 0,
    archive: 0,
  },
  currentFolder = "inbox",
  currentFilter = "all",
  searchQuery,
  pagination,
  error,
  auth,
}: InboxProps & { auth?: any }) {
  const { props } = usePage<InboxProps & { auth?: any }>();

  // Use props directly for data that can be updated via partial reloads
  const currentEmails = props.emails || emails;
  const currentFilterValue = props.currentFilter || currentFilter;
  const currentFolders = props.folders || folders;
  const currentPagination = props.pagination || pagination;
  const currentSearchQuery = props.searchQuery || searchQuery || "";
  
  // Enable real-time email updates
  const companyId = props.auth?.user?.company_id || auth?.user?.company_id;
  if (companyId) {
    useRealtimeEmails(companyId, currentFolder);
  }

  return (
    <InboxProvider
      emails={currentEmails}
      emailAccounts={emailAccounts}
      selectedAccount={selectedAccount}
      folders={currentFolders}
      currentFolder={currentFolder}
      currentFilter={currentFilterValue}
      searchQuery={currentSearchQuery}
    >
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Inbox" />
        {error && (
          <div className="bg-destructive/15 text-destructive px-4 py-3 text-sm">
            {error}
          </div>
        )}
        <ErrorBoundary>
          <InboxContent pagination={currentPagination} />
        </ErrorBoundary>
      </AppLayout>
    </InboxProvider>
  );
}
