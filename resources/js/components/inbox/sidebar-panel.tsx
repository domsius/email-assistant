import React from "react";
import { ResizablePanel } from "@/components/ui/resizable";
import { cn } from "@/lib/utils";
import { useInbox } from "@/contexts/inbox-context";
import { AccountSwitcher } from "./sidebar/account-switcher";
import { FolderSidebar } from "./sidebar/folder-sidebar";

export function SidebarPanel() {
  const { isCollapsed, setIsCollapsed } = useInbox();

  return (
    <ResizablePanel
      defaultSize={20}
      collapsedSize={4}
      collapsible={true}
      minSize={15}
      maxSize={30}
      onCollapse={() => setIsCollapsed(true)}
      onExpand={() => setIsCollapsed(false)}
      className={cn(
        isCollapsed && "min-w-[50px]",
        "transition-all duration-300 ease-in-out",
      )}
    >
      <div className="flex h-full flex-col">
        <AccountSwitcher />
        <FolderSidebar />
      </div>
    </ResizablePanel>
  );
}
