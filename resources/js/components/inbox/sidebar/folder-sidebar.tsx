import React from "react";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import {
  Archive,
  ArchiveX,
  File,
  Inbox as InboxIcon,
  SendIcon,
  Trash2,
  Plus,
} from "lucide-react";
import { useInbox } from "@/contexts/inbox-context";

interface NavItem {
  id: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  count: number;
}

const navItems: Omit<NavItem, "count">[] = [
  {
    id: "inbox",
    label: "All Mail",
    icon: InboxIcon,
  },
  {
    id: "drafts",
    label: "Drafts",
    icon: File,
  },
  {
    id: "sent",
    label: "Sent",
    icon: SendIcon,
  },
  {
    id: "junk",
    label: "Junk",
    icon: ArchiveX,
  },
  {
    id: "trash",
    label: "Trash",
    icon: Trash2,
  },
  {
    id: "archive",
    label: "Archive",
    icon: Archive,
  },
];

export const FolderSidebar = React.memo(function FolderSidebar() {
  const {
    folders,
    activeFolder,
    isCollapsed,
    setActiveFolder,
    selectedAccount,
    activeFilter,
    enterComposeMode,
  } = useInbox();

  const handleFolderSelect = (folderId: string) => {
    setActiveFolder(folderId);
    const params = new URLSearchParams();
    params.set("folder", folderId);
    params.set("filter", activeFilter);
    if (selectedAccount) {
      params.set("account", selectedAccount.toString());
    }
    router.get(`/inbox?${params.toString()}`);
  };
  const navItemsWithCount = navItems.map((item) => ({
    ...item,
    count: folders[item.id as keyof typeof folders],
  }));

  return (
    <>
      <div className="p-2">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="default"
              size="sm"
              className={cn(
                "w-full justify-start",
                isCollapsed && "justify-center",
              )}
              onClick={() =>
                enterComposeMode({
                  to: "",
                  subject: "",
                  body: "",
                  action: "new",
                })
              }
            >
              <Plus className={cn("h-4 w-4", !isCollapsed && "mr-2")} />
              {!isCollapsed && <span>Compose</span>}
            </Button>
          </TooltipTrigger>
          {isCollapsed && <TooltipContent side="right">Compose</TooltipContent>}
        </Tooltip>
      </div>
      <Separator />
      <nav className="flex-1 space-y-1 p-2">
        {navItemsWithCount.map((item) => (
          <Tooltip key={item.id}>
            <TooltipTrigger asChild>
              <Button
                variant={activeFolder === item.id ? "secondary" : "ghost"}
                size="sm"
                className={cn(
                  "w-full justify-start",
                  isCollapsed && "justify-center",
                )}
                onClick={() => handleFolderSelect(item.id)}
              >
                <item.icon className={cn("h-4 w-4", !isCollapsed && "mr-2")} />
                {!isCollapsed && (
                  <>
                    <span>{item.label}</span>
                    {item.count > 0 && (
                      <span className="ml-auto text-xs text-muted-foreground">
                        {item.count}
                      </span>
                    )}
                  </>
                )}
              </Button>
            </TooltipTrigger>
            {isCollapsed && (
              <TooltipContent side="right">
                {item.label}
                {item.count > 0 && ` (${item.count})`}
              </TooltipContent>
            )}
          </Tooltip>
        ))}
      </nav>
    </>
  );
});
