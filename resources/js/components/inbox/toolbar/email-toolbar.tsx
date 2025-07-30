import React from "react";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  Archive,
  CheckCircle,
  Inbox as InboxIcon,
  RefreshCw,
  ShieldAlert,
  Trash2,
} from "lucide-react";
import { useInbox } from "@/contexts/inbox-context";

export const EmailToolbar = React.memo(function EmailToolbar() {
  const {
    emails,
    activeFolder,
    selectedEmails,
    selectedAccount,
    selectAllEmails,
    handleArchive,
    handleUnarchive,
    handleRestore,
    handleDelete,
    handleMoveToSpam,
    handleSync,
  } = useInbox();
  return (
    <div className="flex items-center justify-between px-4 py-2">
      <div className="flex items-center gap-2">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => selectAllEmails(emails)}
            >
              <CheckCircle className="h-4 w-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Select all</TooltipContent>
        </Tooltip>

        {activeFolder === "archive" ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                onClick={handleUnarchive}
                disabled={selectedEmails.length === 0}
              >
                <InboxIcon className="h-4 w-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>Move to Inbox</TooltipContent>
          </Tooltip>
        ) : activeFolder === "trash" ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                onClick={handleRestore}
                disabled={selectedEmails.length === 0}
              >
                <InboxIcon className="h-4 w-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>Restore to Inbox</TooltipContent>
          </Tooltip>
        ) : (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                onClick={handleArchive}
                disabled={selectedEmails.length === 0}
              >
                <Archive className="h-4 w-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>Archive</TooltipContent>
          </Tooltip>
        )}

        {activeFolder !== "trash" && (
          <>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={handleDelete}
                  disabled={selectedEmails.length === 0}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Delete</TooltipContent>
            </Tooltip>

            {activeFolder !== "junk" && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={handleMoveToSpam}
                    disabled={selectedEmails.length === 0}
                  >
                    <ShieldAlert className="h-4 w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Mark as Spam</TooltipContent>
              </Tooltip>
            )}
          </>
        )}

        <Separator orientation="vertical" className="mx-1 h-6" />

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => handleSync(selectedAccount)}
            >
              <RefreshCw className="h-4 w-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Refresh</TooltipContent>
        </Tooltip>
      </div>
    </div>
  );
});
