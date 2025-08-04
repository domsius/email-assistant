import React from "react";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  Archive,
  CheckCircle,
  Inbox as InboxIcon,
  RefreshCw,
  ShieldAlert,
  ShieldCheck,
  Trash2,
  Trash,
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
    handleNotSpam,
    handlePermanentDelete,
    handleSync,
  } = useInbox();
  
  const hasSelectedEmails = selectedEmails.length > 0;
  
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

        {hasSelectedEmails && (
          <>
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
          <>
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
              <TooltipContent>Restore</TooltipContent>
            </Tooltip>

            <AlertDialog>
              <Tooltip>
                <TooltipTrigger asChild>
                  <AlertDialogTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      disabled={selectedEmails.length === 0}
                    >
                      <Trash className="h-4 w-4" />
                    </Button>
                  </AlertDialogTrigger>
                </TooltipTrigger>
                <TooltipContent>Delete Permanently</TooltipContent>
              </Tooltip>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>
                    Delete emails permanently?
                  </AlertDialogTitle>
                  <AlertDialogDescription>
                    This action cannot be undone. {selectedEmails.length} email
                    {selectedEmails.length > 1 ? "s" : ""} will be permanently
                    deleted from your account.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction onClick={handlePermanentDelete}>
                    Delete Permanently
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </>
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

            {activeFolder === "junk" && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={handleNotSpam}
                    disabled={selectedEmails.length === 0}
                  >
                    <ShieldCheck className="h-4 w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Not Spam</TooltipContent>
              </Tooltip>
            )}
          </>
        )}
            
            <Separator orientation="vertical" className="mx-1 h-6" />
          </>
        )}

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
