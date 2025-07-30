import * as React from "react";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { formatDistanceToNow } from "date-fns";
import {
  AlertCircle,
  CheckCircle,
  Clock,
  Languages,
  Star,
  FileText,
} from "lucide-react";

interface EmailMessage {
  id: number | string;
  subject: string;
  sender: string;
  senderEmail: string;
  content: string;
  snippet: string;
  receivedAt: string;
  status: "pending" | "processing" | "processed";
  isRead: boolean;
  isStarred: boolean;
  isSelected: boolean;
  language?: string;
  topic?: string;
  sentiment?: "positive" | "negative" | "neutral";
  urgency?: "high" | "medium" | "low";
  emailAccountId: number;
  threadId?: string;
  labels?: string[];
  aiAnalysis?: {
    summary: string;
    keyPoints: string[];
    suggestedResponse?: string;
    confidence: number;
  };
  // Draft-specific fields
  isDraft?: boolean;
  draftId?: number;
  action?: "new" | "reply" | "replyAll" | "forward";
  to?: string;
  from?: string;
  date?: string;
}

interface EmailListItemProps {
  email: EmailMessage;
  isSelected: boolean;
  isChecked: boolean;
  onSelect: () => void;
  onToggleCheck: (checked: boolean) => void;
  onToggleStar: () => void;
}

export function EmailListItem({
  email,
  isSelected,
  isChecked,
  onSelect,
  onToggleCheck,
  onToggleStar,
}: EmailListItemProps) {
  const getStatusIcon = (status: string) => {
    switch (status) {
      case "processed":
        return <CheckCircle className="h-3 w-3 text-green-500" />;
      case "processing":
        return <Clock className="h-3 w-3 text-blue-500 animate-pulse" />;
      case "pending":
        return <AlertCircle className="h-3 w-3 text-orange-500" />;
      default:
        return null;
    }
  };

  const getSentimentColor = (sentiment?: string) => {
    switch (sentiment) {
      case "positive":
        return "text-green-600";
      case "negative":
        return "text-red-600";
      case "neutral":
        return "text-gray-600";
      default:
        return "text-gray-600";
    }
  };

  return (
    <div
      className={cn(
        "flex items-start gap-2 rounded-lg border p-3 text-sm transition-all hover:bg-accent",
        isSelected && "bg-muted",
        !email.isRead &&
          !email.isDraft &&
          "bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-900",
        email.isDraft &&
          "bg-gray-50 dark:bg-gray-950/20 border-gray-300 dark:border-gray-800",
      )}
    >
      <input
        type="checkbox"
        checked={isChecked}
        onChange={(e) => {
          e.stopPropagation();
          onToggleCheck(e.target.checked);
        }}
        className="mt-1"
        onClick={(e) => e.stopPropagation()}
      />
      <div
        className={cn(
          "flex flex-1 flex-col items-start gap-2 text-left cursor-pointer",
          !email.isRead && "font-semibold",
        )}
        onClick={onSelect}
      >
        <div className="flex w-full flex-col gap-1">
          <div className="flex items-center">
            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1">
                {email.isDraft ? (
                  <FileText className="h-3 w-3 text-gray-500" />
                ) : (
                  getStatusIcon(email.status)
                )}
                <span className="font-medium">
                  {email.isDraft ? (
                    <>Draft to: {email.to || "No recipients"}</>
                  ) : (
                    email.sender
                  )}
                </span>
              </div>
              {!email.isDraft && (
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    onToggleStar();
                  }}
                  className="flex items-center"
                >
                  <Star
                    className={cn(
                      "h-3 w-3",
                      email.isStarred
                        ? "fill-yellow-400 text-yellow-400"
                        : "text-gray-400",
                    )}
                  />
                </button>
              )}
            </div>
            <div className="ml-auto flex items-center gap-2">
              {email.urgency && (
                <Badge variant="outline" className="text-xs">
                  {email.urgency}
                </Badge>
              )}
              <span className="text-xs text-muted-foreground">
                {formatDistanceToNow(new Date(email.receivedAt), {
                  addSuffix: true,
                })}
              </span>
            </div>
          </div>
          <div className="text-xs font-medium">{email.subject}</div>
        </div>
        <div className="line-clamp-2 text-xs text-muted-foreground">
          {email.snippet}
        </div>
        <div className="flex items-center gap-2">
          {email.language && (
            <Badge variant="secondary" className="text-xs">
              <Languages className="mr-1 h-3 w-3" />
              {email.language}
            </Badge>
          )}
          {email.topic && (
            <Badge variant="outline" className="text-xs">
              {email.topic}
            </Badge>
          )}
          {email.sentiment && (
            <span className={cn("text-xs", getSentimentColor(email.sentiment))}>
              {email.sentiment}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

export type { EmailMessage };
