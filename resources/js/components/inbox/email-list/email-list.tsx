import React from "react";
import { ScrollArea } from "@/components/ui/scroll-area";
import { EmailListItem } from "@/components/ui/email-list-item";
import { Mail } from "lucide-react";
import { useInbox } from "@/contexts/inbox-context";
import { router } from "@inertiajs/react";
import type { EmailMessage } from "@/types/inbox";

interface EmailListProps {
  emails?: EmailMessage[];
}

export const EmailList = React.memo(function EmailList({
  emails: propEmails,
}: EmailListProps) {
  const {
    emails: contextEmails,
    selectedEmail,
    selectedEmails,
    setSelectedEmail,
    toggleEmailSelection,
    handleToggleStar,
  } = useInbox();

  const emails = propEmails || contextEmails;
  if (emails.length === 0) {
    return (
      <div className="p-8 text-center text-muted-foreground">
        <Mail className="mx-auto h-12 w-12 opacity-50" />
        <p className="mt-4">No messages found</p>
      </div>
    );
  }

  return (
    <ScrollArea className="flex-1">
      <div className="flex flex-col gap-2 p-4 pt-0">
        {emails.map((email) => (
          <EmailListItem
            key={email.id}
            email={email}
            isSelected={selectedEmail?.id === email.id}
            isChecked={selectedEmails.includes(
              typeof email.id === "string" ? email.id : email.id,
            )}
            onSelect={() => setSelectedEmail(email)}
            onToggleCheck={(checked) => toggleEmailSelection(email.id, checked)}
            onToggleStar={() => handleToggleStar(email.id)}
          />
        ))}
      </div>
    </ScrollArea>
  );
});
