import React from "react";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import {
  AlertCircle,
  Shield,
  Paperclip,
  FileText,
  Download,
  Eye,
  Trash2,
} from "lucide-react";

interface EmailSecurityBadgesProps {
  isSecure?: boolean;
  spamScore?: number;
}

export function EmailSecurityBadges({
  isSecure,
  spamScore,
}: EmailSecurityBadgesProps) {
  if (isSecure === undefined && spamScore === undefined) {
    return null;
  }

  return (
    <div className="flex items-center gap-4">
      {isSecure && (
        <div className="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-500">
          <Shield className="h-4 w-4" />
          <span>Verified Sender</span>
        </div>
      )}
      {spamScore !== undefined && spamScore > 5 && (
        <Alert className="py-2">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            This email has a high spam score ({spamScore}/10)
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
}

interface EmailHeadersProps {
  headers?: Record<string, string>;
  show: boolean;
}

export function EmailHeaders({ headers, show }: EmailHeadersProps) {
  if (!show || !headers) {
    return null;
  }

  return (
    <div className="mt-4 p-4 bg-muted rounded-lg">
      <h4 className="font-medium mb-2">Email Headers</h4>
      <div className="space-y-1 text-xs font-mono">
        {Object.entries(headers).map(([key, value]) => (
          <div key={key}>
            <span className="font-semibold">{key}:</span> {value}
          </div>
        ))}
      </div>
    </div>
  );
}

interface EmailAttachment {
  id: string;
  filename: string;
  size: number;
  type: string;
}

interface EmailAttachmentsProps {
  attachments?: EmailAttachment[];
  onDownload?: (attachmentId: string) => void;
}

export function EmailAttachments({
  attachments,
  onDownload,
}: EmailAttachmentsProps) {
  if (!attachments || attachments.length === 0) {
    return null;
  }

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  };

  return (
    <>
      <div className="p-6">
        <h4 className="font-medium mb-3 flex items-center gap-2">
          <Paperclip className="h-4 w-4" />
          Attachments ({attachments.length})
        </h4>
        <div className="space-y-2">
          {attachments.map((attachment) => (
            <div
              key={attachment.id}
              className="flex items-center justify-between p-3 rounded-lg border bg-muted/50"
            >
              <div className="flex items-center gap-3">
                <FileText className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{attachment.filename}</p>
                  <p className="text-xs text-muted-foreground">
                    {attachment.type} • {formatFileSize(attachment.size)}
                  </p>
                </div>
              </div>
              {onDownload && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => onDownload(attachment.id)}
                >
                  <Download className="h-4 w-4" />
                </Button>
              )}
            </div>
          ))}
        </div>
      </div>
    </>
  );
}

interface EmailActionButtonsProps {
  isDraft?: boolean;
  onReply?: () => void;
  onReplyAll?: () => void;
  onForward?: () => void;
  onEditDraft?: () => void;
  onDeleteDraft?: () => void;
  showHeaders: boolean;
  onToggleHeaders: () => void;
}

export function EmailActionButtons({
  isDraft,
  onReply,
  onReplyAll,
  onForward,
  onEditDraft,
  onDeleteDraft,
  showHeaders,
  onToggleHeaders,
}: EmailActionButtonsProps) {
  return (
    <div className="flex items-center gap-2">
      {isDraft && onEditDraft ? (
        <>
          <Button variant="default" size="sm" onClick={onEditDraft}>
            Edit Draft
          </Button>
          {onDeleteDraft && (
            <Button variant="destructive" size="sm" onClick={onDeleteDraft}>
              <Trash2 className="h-4 w-4 mr-2" />
              Delete Draft
            </Button>
          )}
        </>
      ) : (
        <>
          {onReply && (
            <Button variant="outline" size="sm" onClick={onReply}>
              Reply
            </Button>
          )}
          {onReplyAll && (
            <Button variant="outline" size="sm" onClick={onReplyAll}>
              Reply All
            </Button>
          )}
          {onForward && (
            <Button variant="outline" size="sm" onClick={onForward}>
              Forward
            </Button>
          )}
        </>
      )}
      <Button variant="ghost" size="sm" onClick={onToggleHeaders}>
        <Eye className="h-4 w-4 mr-2" />
        {showHeaders ? "Hide" : "Show"} Headers
      </Button>
    </div>
  );
}
