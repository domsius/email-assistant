import React, { useState } from "react";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

import { Separator } from "@/components/ui/separator";
import { format } from "date-fns";
import { Calendar } from "lucide-react";
import { cn } from "@/lib/utils";
import {
  EmailSecurityBadges,
  EmailHeaders,
  EmailAttachments,
  EmailActionButtons,
} from "@/components/ui/email-preview-components";
import { EmailErrorBoundary } from "@/components/ui/email-error-boundary";
import { EmailContentRenderer } from "@/components/ui/email-content-renderer";

interface EmailPreviewProps {
  email: {
    id: number | string;
    subject: string;
    sender: string;
    senderEmail: string;
    recipients?: string[];
    cc?: string[];
    bcc?: string[];
    receivedAt: string;
    content: string;
    plainTextContent?: string;
    attachments?: Array<{
      id: string;
      filename: string;
      size: number;
      type: string;
    }>;
    headers?: Record<string, string>;
    isSecure?: boolean;
    spamScore?: number;
    isDraft?: boolean;
    draftId?: number;
  };
  className?: string;
  onReply?: () => void;
  onReplyAll?: () => void;
  onForward?: () => void;
  onDownloadAttachment?: (attachmentId: string) => void;
  onEditDraft?: () => void;
}

function EmailPreviewContent({
  email,
  className,
  onReply,
  onReplyAll,
  onForward,
  onDownloadAttachment,
  onEditDraft,
}: EmailPreviewProps) {
  const [showHeaders, setShowHeaders] = useState(false);
  const [useIframeIsolation, setUseIframeIsolation] = useState(true);

  // Pass raw content to EmailContentRenderer, let it handle sanitization
  // This ensures styles can be properly extracted before sanitization
  const emailContent = email.content;


  return (
    <Card className={cn("flex flex-col h-full", className)}>
      <CardHeader className="space-y-4">
        {/* Security Indicators */}
        <EmailSecurityBadges
          isSecure={email.isSecure}
          spamScore={email.spamScore}
        />

        {/* Email Header */}
        <div className="space-y-3">
          <CardTitle className="text-2xl font-bold">{email.subject}</CardTitle>

          <div className="space-y-2 text-sm">
            <div className="flex items-start gap-2">
              <span className="text-muted-foreground min-w-[80px]">From:</span>
              <div>
                <span className="font-medium">{email.sender}</span>
                <span className="text-muted-foreground">
                  {" "}
                  &lt;{email.senderEmail}&gt;
                </span>
              </div>
            </div>

            {email.recipients && email.recipients.length > 0 && (
              <div className="flex items-start gap-2">
                <span className="text-muted-foreground min-w-[80px]">To:</span>
                <span>{email.recipients.join(", ")}</span>
              </div>
            )}

            {email.cc && email.cc.length > 0 && (
              <div className="flex items-start gap-2">
                <span className="text-muted-foreground min-w-[80px]">Cc:</span>
                <span>{email.cc.join(", ")}</span>
              </div>
            )}

            <div className="flex items-center gap-2">
              <Calendar className="h-4 w-4 text-muted-foreground" />
              <span>{format(new Date(email.receivedAt), "PPpp")}</span>
            </div>
          </div>

          {/* Action Buttons */}
          <EmailActionButtons
            isDraft={email.isDraft}
            onReply={onReply}
            onReplyAll={onReplyAll}
            onForward={onForward}
            onEditDraft={onEditDraft}
            showHeaders={showHeaders}
            onToggleHeaders={() => setShowHeaders(!showHeaders)}
          />

          {/* Extended Headers */}
          <EmailHeaders headers={email.headers} show={showHeaders} />
        </div>
      </CardHeader>

      <Separator />

      <CardContent className="flex-1 overflow-auto p-6">
        <EmailContentRenderer
          htmlContent={emailContent}
          className="min-h-[200px]"
          enableIframeIsolation={useIframeIsolation}
        />

        {/* Attachments */}
        {email.attachments && email.attachments.length > 0 && (
          <>
            <Separator />
            <EmailAttachments
              attachments={email.attachments}
              onDownload={onDownloadAttachment}
            />
          </>
        )}
      </CardContent>
    </Card>
  );
}

// Export a separate header component for use in lists
export function EmailPreviewHeader({
  email,
  className,
}: {
  email: Pick<EmailPreviewProps["email"], "subject" | "sender" | "receivedAt">;
  className?: string;
}) {
  return (
    <div className={cn("space-y-1", className)}>
      <h3 className="font-medium line-clamp-1">{email.subject}</h3>
      <div className="flex items-center gap-4 text-sm text-muted-foreground">
        <span>{email.sender}</span>
        <span>{format(new Date(email.receivedAt), "MMM d, yyyy")}</span>
      </div>
    </div>
  );
}

// Export the main component wrapped with error boundary
export function EmailPreview(props: EmailPreviewProps) {
  return (
    <EmailErrorBoundary
      onError={(error, errorInfo) => {
        console.error("EmailPreview error:", error, errorInfo);
        // You could also send this to your error tracking service
      }}
    >
      <EmailPreviewContent {...props} />
    </EmailErrorBoundary>
  );
}
