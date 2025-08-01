import React, {
  createContext,
  useContext,
  useState,
  useCallback,
  ReactNode,
  useEffect,
} from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { setGlobalComposeFunction } from "@/hooks/use-inbox-navigation";
import type { EmailMessage, EmailAccount, FolderCounts } from "@/types/inbox";

interface InboxState {
  selectedEmails: (number | string)[];
  selectedEmail: EmailMessage | null;
  searchQuery: string;
  activeFolder: string;
  activeFilter: string;
  isCollapsed: boolean;
  isLoading: boolean;
  isComposing: boolean;
  composeData: ComposeData | null;
  isComposeMinimized: boolean;
  justSentEmail: boolean;
  viewMode: "list" | "detail";
}

export interface ComposeData {
  to: string;
  cc?: string;
  bcc?: string;
  subject: string;
  body: string;
  action?: "new" | "reply" | "replyAll" | "forward" | "draft";
  inReplyTo?: string;
  references?: string[];
  originalEmail?: EmailMessage;
  draftId?: number | null;
  defaultFrom?: string; // The email address that received the original email
}

interface InboxActions {
  setSelectedEmails: (emails: (number | string)[]) => void;
  setSelectedEmail: (email: EmailMessage | null) => void;
  setSearchQuery: (query: string) => void;
  setActiveFolder: (folder: string) => void;
  setActiveFilter: (filter: string) => void;
  setIsCollapsed: (collapsed: boolean) => void;
  toggleEmailSelection: (emailId: number | string, checked: boolean) => void;
  selectAllEmails: (emails: EmailMessage[]) => void;
  handleArchive: () => void;
  handleUnarchive: () => void;
  handleRestore: () => void;
  handleDelete: () => void;
  handleMoveToSpam: () => void;
  handleNotSpam: () => void;
  handlePermanentDelete: () => void;
  handleToggleStar: (emailId: number | string) => void;
  handleToggleRead: (emailId: number | string) => void;
  handleSync: (selectedAccount: number | null) => void;
  enterComposeMode: (data: ComposeData) => void;
  exitComposeMode: () => void;
  setJustSentEmail: (sent: boolean) => void;
  setViewMode: (mode: "list" | "detail") => void;
  setComposeMinimized: (minimized: boolean) => void;
}

interface InboxContextValue extends InboxState, InboxActions {
  emails: EmailMessage[];
  emailAccounts: EmailAccount[];
  selectedAccount: number | null;
  folders: FolderCounts;
}

const InboxContext = createContext<InboxContextValue | null>(null);

interface InboxProviderProps {
  children: ReactNode;
  emails: EmailMessage[];
  emailAccounts: EmailAccount[];
  selectedAccount: number | null;
  folders: FolderCounts;
  currentFolder: string;
  currentFilter?: string;
  searchQuery?: string;
}

export function InboxProvider({
  children,
  emails,
  emailAccounts,
  selectedAccount,
  folders,
  currentFolder,
  currentFilter = "all",
  searchQuery = "",
}: InboxProviderProps) {
  const [state, setState] = useState<InboxState>({
    selectedEmails: [],
    selectedEmail: emails[0] || null,
    searchQuery: searchQuery,
    activeFolder: currentFolder,
    activeFilter: currentFilter,
    isCollapsed: false,
    isLoading: false,
    isComposing: false,
    composeData: null,
    isComposeMinimized: false,
    justSentEmail: false,
    viewMode: "list",
  });

  // Update activeFilter when currentFilter prop changes
  React.useEffect(() => {
    setState((prev) => ({ ...prev, activeFilter: currentFilter }));
  }, [currentFilter]);

  // Update selectedEmail when emails change (e.g., when filter changes)
  React.useEffect(() => {
    setState((prev) => {
      const newSelectedEmail =
        emails.find((e) => e.id === prev.selectedEmail?.id) ||
        emails[0] ||
        null;
      return {
        ...prev,
        selectedEmail: newSelectedEmail,
      };
    });
  }, [emails]);

  const setSelectedEmails = useCallback((emails: (number | string)[]) => {
    setState((prev) => ({ ...prev, selectedEmails: emails }));
  }, []);

  const setSelectedEmail = useCallback(async (email: EmailMessage | null) => {
    setState((prev) => ({ ...prev, selectedEmail: email }));

    if (!email) {
      return;
    }

    // For drafts, we'll handle opening in compose mode via useEffect
    if (email.isDraft) {
      return;
    }

    // Fetch full email content if not already loaded
    if (!email.content || email.content === "") {
      try {
        const response = await fetch(`/api/emails/${email.id}`, {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN":
              document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") || "",
          },
          credentials: "same-origin",
        });

        if (response.ok) {
          const fullEmail = await response.json();

          // Update the selected email with full content
          setState((prev) => ({
            ...prev,
            selectedEmail: {
              ...email,
              content:
                fullEmail.body_html ||
                fullEmail.body_plain ||
                fullEmail.body_content ||
                "",
              plainTextContent:
                fullEmail.body_plain || fullEmail.body_content || "",
            },
          }));
        }
      } catch (error) {
        console.error("Failed to fetch email content:", error);
      }
    }

    // Mark email as read if it's unread
    if (email && !email.isRead) {
      router.post(
        `/emails/${email.id}/toggle-read`,
        {},
        {
          preserveScroll: true,
          only: ["emails"], // Only reload emails to update read status
        },
      );
    }
  }, []);

  const setSearchQuery = useCallback((query: string) => {
    setState((prev) => ({ ...prev, searchQuery: query }));
  }, []);

  const setActiveFolder = useCallback((folder: string) => {
    setState((prev) => ({ ...prev, activeFolder: folder }));
  }, []);

  const setActiveFilter = useCallback((filter: string) => {
    setState((prev) => ({ ...prev, activeFilter: filter }));
  }, []);
  const reloadWithCurrentParams = useCallback(
    (only: string[] = ["emails", "folders"]) => {
      // Reload with current URL parameters to preserve filter, folder, account, etc.
      router.get(window.location.href, {
        only,
        preserveState: false,
        preserveScroll: true,
      });
    },
    [],
  );

  const setIsCollapsed = useCallback((collapsed: boolean) => {
    setState((prev) => ({ ...prev, isCollapsed: collapsed }));
  }, []);

  const toggleEmailSelection = useCallback(
    (emailId: number | string, checked: boolean) => {
      setState((prev) => ({
        ...prev,
        selectedEmails: checked
          ? [...prev.selectedEmails, emailId]
          : prev.selectedEmails.filter((id) => id !== emailId),
      }));
    },
    [],
  );

  const selectAllEmails = useCallback((emails: EmailMessage[]) => {
    setState((prev) => ({
      ...prev,
      selectedEmails:
        prev.selectedEmails.length === emails.length
          ? []
          : emails.map((email) => email.id),
    }));
  }, []);

  const handleArchive = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        router.post(
          "/emails/archive",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handleUnarchive = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        router.post(
          "/emails/unarchive",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handleRestore = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        router.post(
          "/emails/restore",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handleDelete = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        const emailCount = prev.selectedEmails.length;
        router.post(
          "/emails/delete",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              toast.success(
                `${emailCount} email${emailCount > 1 ? "s" : ""} moved to trash`,
                {
                  action: {
                    label: "View Trash",
                    onClick: () => {
                      router.get("/inbox?folder=trash");
                    },
                  },
                  duration: 5000,
                },
              );
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
              toast.error("Failed to delete emails");
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handleMoveToSpam = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        const emailCount = prev.selectedEmails.length;
        router.post(
          "/emails/spam",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              toast.success(
                `${emailCount} email${emailCount > 1 ? "s" : ""} moved to spam`,
                {
                  action: {
                    label: "View Spam",
                    onClick: () => {
                      router.get("/inbox?folder=junk");
                    },
                  },
                  duration: 5000,
                },
              );
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
              toast.error("Failed to move emails to spam");
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handleNotSpam = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        const emailCount = prev.selectedEmails.length;
        router.post(
          "/emails/not-spam",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              toast.success(
                `${emailCount} email${emailCount > 1 ? "s" : ""} moved to inbox`,
                {
                  action: {
                    label: "View Inbox",
                    onClick: () => {
                      router.get("/inbox");
                    },
                  },
                  duration: 5000,
                },
              );
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
              toast.error("Failed to move emails from spam");
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handlePermanentDelete = useCallback(() => {
    setState((prev) => {
      if (prev.selectedEmails.length > 0) {
        const emailCount = prev.selectedEmails.length;
        router.post(
          "/emails/permanent-delete",
          {
            emailIds: prev.selectedEmails,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              setState((current) => ({
                ...current,
                selectedEmails: [],
                isLoading: false,
              }));
              toast.success(
                `${emailCount} email${emailCount > 1 ? "s" : ""} permanently deleted`,
                {
                  duration: 5000,
                },
              );
              reloadWithCurrentParams(["emails", "folders"]);
            },
            onError: () => {
              setState((current) => ({ ...current, isLoading: false }));
              toast.error("Failed to permanently delete emails");
            },
          },
        );
        return { ...prev, isLoading: true };
      }
      return prev;
    });
  }, [reloadWithCurrentParams]);

  const handleToggleStar = useCallback(
    (emailId: number | string) => {
      setState((prev) => ({ ...prev, isLoading: true }));
      router.post(
        `/emails/${emailId}/toggle-star`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            setState((prev) => ({ ...prev, isLoading: false }));
            reloadWithCurrentParams(["emails"]);
          },
          onError: () => {
            setState((prev) => ({ ...prev, isLoading: false }));
          },
        },
      );
    },
    [reloadWithCurrentParams],
  );

  const handleToggleRead = useCallback(
    (emailId: number | string) => {
      setState((prev) => ({ ...prev, isLoading: true }));
      router.post(
        `/emails/${emailId}/toggle-read`,
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            setState((prev) => ({ ...prev, isLoading: false }));
            reloadWithCurrentParams(["emails"]);
          },
          onError: () => {
            setState((prev) => ({ ...prev, isLoading: false }));
          },
        },
      );
    },
    [reloadWithCurrentParams],
  );

  const handleSync = useCallback((selectedAccount: number | null) => {
    setState((prev) => ({ ...prev, isLoading: true }));
    router.post(
      "/emails/sync",
      {
        accountId: selectedAccount,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setState((prev) => ({ ...prev, isLoading: false }));
          // Reload with current URL parameters
          router.get(window.location.href, {
            only: ["emails", "folders"],
            preserveState: false,
            preserveScroll: true,
          });
        },
        onError: () => {
          setState((prev) => ({ ...prev, isLoading: false }));
        },
      },
    );
  }, []);

  const enterComposeMode = useCallback((data: ComposeData) => {
    setState((prev) => ({
      ...prev,
      isComposing: true,
      composeData: data,
    }));
  }, []);

  const exitComposeMode = useCallback(() => {
    setState((prev) => ({
      ...prev,
      isComposing: false,
      composeData: null,
    }));
  }, []);

  const setJustSentEmail = useCallback((sent: boolean) => {
    setState((prev) => ({
      ...prev,
      justSentEmail: sent,
    }));
  }, []);

  const setViewMode = useCallback((mode: "list" | "detail") => {
    setState((prev) => ({
      ...prev,
      viewMode: mode,
    }));
  }, []);

  const setComposeMinimized = useCallback((minimized: boolean) => {
    setState((prev) => ({
      ...prev,
      isComposeMinimized: minimized,
    }));
  }, []);

  // Register/unregister the compose function for global access
  useEffect(() => {
    setGlobalComposeFunction(enterComposeMode);
    return () => {
      setGlobalComposeFunction(null);
    };
  }, [enterComposeMode]);

  const value: InboxContextValue = {
    ...state,
    emails,
    emailAccounts,
    selectedAccount,
    folders,
    setSelectedEmails,
    setSelectedEmail,
    setSearchQuery,
    setActiveFolder,
    setActiveFilter,
    setIsCollapsed,
    toggleEmailSelection,
    selectAllEmails,
    handleArchive,
    handleUnarchive,
    handleRestore,
    handleDelete,
    handleMoveToSpam,
    handleNotSpam,
    handlePermanentDelete,
    handleToggleStar,
    handleToggleRead,
    handleSync,
    enterComposeMode,
    exitComposeMode,
    setJustSentEmail,
    setViewMode,
    setComposeMinimized,
  };

  return (
    <InboxContext.Provider value={value}>{children}</InboxContext.Provider>
  );
}

export function useInbox() {
  const context = useContext(InboxContext);
  if (!context) {
    throw new Error("useInbox must be used within InboxProvider");
  }
  return context;
}
