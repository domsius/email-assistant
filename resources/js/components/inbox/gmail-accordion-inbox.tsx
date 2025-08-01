import React, { useState, useCallback } from "react";
import { ChevronDown, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { GmailEmailTable } from "./gmail-email-table";
import { EmailMessage } from "@/types/inbox";
import { useInbox } from "@/contexts/inbox-context";
import { cn } from "@/lib/utils";

interface EmailSection {
  id: string;
  title: string;
  emails: EmailMessage[];
  isExpanded: boolean;
}

interface GmailAccordionInboxProps {
  emails: EmailMessage[];
  selectedEmail: EmailMessage | null;
  onEmailSelect: (email: EmailMessage) => void;
  onBackToList: () => void;
  view: "list" | "detail";
}

export function GmailAccordionInbox({
  emails,
  selectedEmail,
  onEmailSelect,
  onBackToList,
  view,
}: GmailAccordionInboxProps) {
  const { folders } = useInbox();
  
  // Separate emails into sections
  const unreadEmails = emails.filter(email => !email.isRead);
  const readEmails = emails.filter(email => email.isRead);
  
  const [sections, setSections] = useState<EmailSection[]>([
    {
      id: "unread",
      title: "Unread",
      emails: unreadEmails,
      isExpanded: true,
    },
    {
      id: "everything-else",
      title: "Everything else",
      emails: readEmails,
      isExpanded: true,
    },
  ]);

  const toggleSection = useCallback((sectionId: string) => {
    setSections(prev =>
      prev.map(section =>
        section.id === sectionId
          ? { ...section, isExpanded: !section.isExpanded }
          : section
      )
    );
  }, []);

  // Update sections when emails change
  React.useEffect(() => {
    const unreadEmails = emails.filter(email => !email.isRead);
    const readEmails = emails.filter(email => email.isRead);
    
    setSections(prev => [
      { ...prev[0], emails: unreadEmails },
      { ...prev[1], emails: readEmails },
    ]);
  }, [emails]);

  return (
    <div className="flex flex-col h-full">
      {sections.map((section) => (
        <div key={section.id} className="border-b last:border-b-0">
          {/* Section Header */}
          <Button
            variant="ghost"
            className="w-full flex items-center justify-start px-4 py-2 hover:bg-muted/50"
            onClick={() => toggleSection(section.id)}
          >
            {section.isExpanded ? (
              <ChevronDown className="h-4 w-4 mr-2" />
            ) : (
              <ChevronRight className="h-4 w-4 mr-2" />
            )}
            <span className="font-medium text-sm">
              {section.title}
            </span>
            {section.emails.length > 0 && (
              <span className="ml-2 text-xs text-muted-foreground">
                ({section.emails.length})
              </span>
            )}
          </Button>

          {/* Section Content */}
          {section.isExpanded && section.emails.length > 0 && (
            <div className={cn(
              "transition-all duration-200",
              section.isExpanded ? "opacity-100" : "opacity-0"
            )}>
              <GmailEmailTable
                emails={section.emails}
                selectedEmail={selectedEmail}
                onEmailSelect={onEmailSelect}
                onBackToList={onBackToList}
                view={view}
                isNested={true}
              />
            </div>
          )}

          {/* Empty State */}
          {section.isExpanded && section.emails.length === 0 && (
            <div className="px-4 py-8 text-center text-muted-foreground text-sm">
              No {section.title.toLowerCase()} messages
            </div>
          )}
        </div>
      ))}
    </div>
  );
}