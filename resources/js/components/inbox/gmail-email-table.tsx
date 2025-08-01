import React, { useCallback } from "react";
import { format } from "date-fns";
import { 
  Star, 
  Paperclip, 
  ArrowLeft,
  Archive,
  Trash2
} from "lucide-react";
import { cn } from "@/lib/utils";
import { EmailMessage } from "@/types/inbox";
import { useInbox } from "@/contexts/inbox-context";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface GmailEmailTableProps {
  emails: EmailMessage[];
  selectedEmail?: EmailMessage | null;
  onEmailSelect: (email: EmailMessage) => void;
  onBackToList?: () => void;
  view: "list" | "detail";
  isNested?: boolean;
}

function EmailTableHeader({ 
  onSelectAll, 
  hasSelectedEmails, 
  allSelected 
}: {
  onSelectAll: (checked: boolean) => void;
  hasSelectedEmails: boolean;
  allSelected: boolean;
}) {
  return (
    <TableHeader>
      <TableRow className="border-b">
        <TableHead className="w-12">
          <Checkbox
            checked={allSelected}
            onCheckedChange={onSelectAll}
            aria-label="Select all emails"
          />
        </TableHead>
        <TableHead className="w-12"></TableHead>
        <TableHead className="w-32">Sender</TableHead>
        <TableHead className="flex-1">Subject</TableHead>
        <TableHead className="w-12"></TableHead>
        <TableHead className="w-32 text-right">Date</TableHead>
      </TableRow>
    </TableHeader>
  );
}

function EmailTableRow({ 
  email, 
  isSelected, 
  isChecked, 
  onSelect, 
  onToggleCheck, 
  onToggleStar,
  activeFolder 
}: {
  email: EmailMessage;
  isSelected: boolean;
  isChecked: boolean;
  onSelect: () => void;
  onToggleCheck: (checked: boolean) => void;
  onToggleStar: () => void;
}) {
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const isToday = date.toDateString() === now.toDateString();
    
    if (isToday) {
      return format(date, "h:mm a");
    } else {
      return format(date, "MMM d");
    }
  };

  const truncateText = (text: string, maxLength: number) => {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + "...";
  };

  return (
    <TableRow
      className={cn(
        "cursor-pointer hover:bg-muted/50 group",
        isSelected && "bg-muted",
        !email.isRead && !email.isDraft && "font-medium bg-blue-50/50 dark:bg-blue-950/20",
        email.isDraft && "bg-gray-50/50 dark:bg-gray-950/20"
      )}
      onClick={onSelect}
    >
      <TableCell className="w-12" onClick={(e) => e.stopPropagation()}>
        <Checkbox
          checked={isChecked}
          onCheckedChange={onToggleCheck}
          aria-label={`Select email from ${email.sender}`}
        />
      </TableCell>
      
      <TableCell className="w-12" onClick={(e) => e.stopPropagation()}>
        <Button
          variant="ghost"
          size="sm"
          className="h-6 w-6 p-0"
          onClick={onToggleStar}
        >
          <Star
            className={cn(
              "h-4 w-4",
              email.isStarred
                ? "fill-yellow-400 text-yellow-400"
                : "text-muted-foreground hover:text-yellow-400"
            )}
          />
        </Button>
      </TableCell>
      
      <TableCell className="w-32">
        <div className="flex items-center gap-2">
          <span className={cn(
            "truncate",
            !email.isRead && !email.isDraft && "font-semibold"
          )}>
            {email.isDraft ? "Draft" : email.sender}
          </span>
          {email.isDraft && (
            <Badge variant="secondary" className="text-xs">
              Draft
            </Badge>
          )}
        </div>
      </TableCell>
      
      <TableCell className="flex-1">
        <div className="flex items-center gap-2">
          <span className={cn(
            "truncate",
            !email.isRead && !email.isDraft && "font-semibold"
          )}>
            {email.subject || "(No Subject)"}
          </span>
          {email.snippet && (
            <span className="text-muted-foreground text-sm">
              - {truncateText(email.snippet, 80)}
            </span>
          )}
        </div>
      </TableCell>
      
      <TableCell className="w-12">
        {email.attachments && email.attachments.length > 0 && (
          <Paperclip className="h-4 w-4 text-muted-foreground" />
        )}
      </TableCell>
      
      <TableCell className="w-32 text-right text-sm text-muted-foreground">
        {formatDate(email.receivedAt || email.date || new Date().toISOString())}
      </TableCell>
    </TableRow>
  );
}

export function GmailEmailTable({
  emails,
  selectedEmail,
  onEmailSelect,
  onBackToList,
  view,
  isNested = false
}: GmailEmailTableProps) {
  const {
    selectedEmails,
    toggleEmailSelection,
    setSelectedEmails,
    handleToggleStar,
  } = useInbox();

  const handleSelectAll = useCallback((checked: boolean) => {
    if (checked) {
      setSelectedEmails(emails.map(e => e.id));
    } else {
      setSelectedEmails([]);
    }
  }, [emails, setSelectedEmails]);

  const allSelected = emails.length > 0 && emails.every(email => selectedEmails.includes(email.id));
  const hasSelectedEmails = selectedEmails.length > 0;

  if (view === "detail" && selectedEmail) {
    return (
      <div className="flex flex-col h-full">
        <div className="flex items-center gap-2 p-4 border-b">
          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0"
            onClick={onBackToList}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              className="h-6 w-6 p-0"
              onClick={() => handleToggleStar(selectedEmail.id)}
            >
              <Star
                className={cn(
                  "h-4 w-4",
                  selectedEmail.isStarred
                    ? "fill-yellow-400 text-yellow-400"
                    : "text-muted-foreground hover:text-yellow-400"
                )}
              />
            </Button>
          </div>
        </div>
        
        <div className="flex-1 overflow-auto">
          {/* Email detail content will go here */}
          <div className="p-6">
            <div className="mb-6">
              <h1 className="text-2xl font-semibold mb-2">{selectedEmail.subject || "(No Subject)"}</h1>
              <div className="flex items-center gap-4 text-sm text-muted-foreground">
                <div>
                  <span className="font-medium text-foreground">{selectedEmail.sender}</span>
                  <span className="ml-1">&lt;{selectedEmail.senderEmail}&gt;</span>
                </div>
                <div>
                  {format(new Date(selectedEmail.receivedAt || selectedEmail.date || new Date()), "MMM d, yyyy 'at' h:mm a")}
                </div>
              </div>
            </div>
            
            {selectedEmail.attachments && selectedEmail.attachments.length > 0 && (
              <div className="mb-6">
                <div className="flex items-center gap-2 mb-2">
                  <Paperclip className="h-4 w-4" />
                  <span className="text-sm font-medium">
                    {selectedEmail.attachments.length} attachment{selectedEmail.attachments.length > 1 ? 's' : ''}
                  </span>
                </div>
                <div className="flex flex-wrap gap-2">
                  {selectedEmail.attachments.map((attachment) => (
                    <Badge key={attachment.id} variant="outline" className="text-xs">
                      {attachment.filename}
                    </Badge>
                  ))}
                </div>
              </div>
            )}
            
            <div className="prose max-w-none">
              <div 
                dangerouslySetInnerHTML={{ 
                  __html: selectedEmail.content || selectedEmail.plainTextContent || selectedEmail.snippet || "No content available" 
                }} 
              />
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      {hasSelectedEmails && (
        <div className="flex items-center gap-2 p-3 bg-muted/50 border-b">
          <span className="text-sm text-muted-foreground">
            {selectedEmails.length} selected
          </span>
        </div>
      )}
      
      <div className="flex-1 overflow-auto">
        <Table>
          {!isNested && (
            <EmailTableHeader
              onSelectAll={handleSelectAll}
              hasSelectedEmails={hasSelectedEmails}
              allSelected={allSelected}
            />
          )}
          <TableBody>
            {emails.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                  No messages found
                </TableCell>
              </TableRow>
            ) : (
              emails.map((email) => (
                <EmailTableRow
                  key={email.id}
                  email={email}
                  isSelected={selectedEmail?.id === email.id}
                  isChecked={selectedEmails.includes(email.id)}
                  onSelect={() => onEmailSelect(email)}
                  onToggleCheck={(checked) => toggleEmailSelection(email.id, checked)}
                  onToggleStar={() => handleToggleStar(email.id)}
                />
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}