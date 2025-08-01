import { usePage } from "@inertiajs/react";
import { router } from "@inertiajs/react";
import {
  Archive,
  ArchiveX,
  File,
  Inbox as InboxIcon,
  Plus,
  SendIcon,
  Trash2,
  Mail,
  MailOpen,
} from "lucide-react";
import type { NavSubItem } from "@/types";

// Global reference to access compose function from anywhere
// This is set by the inbox context when it's available
let globalComposeFunction: ((data: any) => void) | null = null;

export function setGlobalComposeFunction(fn: ((data: any) => void) | null) {
  globalComposeFunction = fn;
}

export function useInboxNavigation(): NavSubItem[] | null {
  const page = usePage();
  const isInboxPage = page.url.startsWith('/inbox');
  
  if (!isInboxPage) {
    return null;
  }

  // Check if inbox-specific props are available in the page props
  const inboxProps = page.props as any;
  
  if (!inboxProps.folders) {
    return null;
  }

  const folders = inboxProps.folders || {};
  const currentFolder = inboxProps.currentFolder || 'inbox';
  const currentFilter = inboxProps.currentFilter || 'all';
  const selectedAccount = inboxProps.selectedAccount;

  const handleFolderSelect = (folderId: string) => {
    const params = new URLSearchParams();
    params.set("folder", folderId);
    params.set("filter", currentFilter);
    if (selectedAccount) {
      params.set("account", selectedAccount.toString());
    }
    router.get(`/inbox?${params.toString()}`);
  };

  const handleCompose = () => {
    if (globalComposeFunction) {
      globalComposeFunction({
        to: "",
        subject: "",
        body: "",
        action: "new",
      });
    } else {
      // Fallback: navigate to inbox if compose function is not available
      router.get("/inbox");
    }
  };

  const subitems: NavSubItem[] = [
    {
      title: "Compose",
      href: "#",
      icon: Plus,
      onClick: handleCompose,
    },
    {
      title: "Inbox",
      href: "/inbox?folder=inbox",
      icon: InboxIcon,
      count: folders.inbox,
      isActive: currentFolder === "inbox",
      onClick: () => handleFolderSelect("inbox"),
    },
    {
      title: "Drafts",
      href: "/inbox?folder=drafts",
      icon: File,
      count: folders.drafts,
      isActive: currentFolder === "drafts",
      onClick: () => handleFolderSelect("drafts"),
    },
    {
      title: "Sent",
      href: "/inbox?folder=sent",
      icon: SendIcon,
      count: folders.sent,
      isActive: currentFolder === "sent",
      onClick: () => handleFolderSelect("sent"),
    },
    {
      title: "Junk",
      href: "/inbox?folder=junk",
      icon: ArchiveX,
      count: folders.junk,
      isActive: currentFolder === "junk",
      onClick: () => handleFolderSelect("junk"),
    },
    {
      title: "Trash",
      href: "/inbox?folder=trash",
      icon: Trash2,
      count: folders.trash,
      isActive: currentFolder === "trash",
      onClick: () => handleFolderSelect("trash"),
    },
    {
      title: "Archive",
      href: "/inbox?folder=archive",
      icon: Archive,
      count: folders.archive,
      isActive: currentFolder === "archive",
      onClick: () => handleFolderSelect("archive"),
    },
  ];

  return subitems;
}