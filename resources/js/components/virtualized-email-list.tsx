import { useRef, useCallback } from "react";
import { FixedSizeList as List } from "react-window";
import InfiniteLoader from "react-window-infinite-loader";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Star, Languages, CheckCircle, Clock, AlertCircle } from "lucide-react";
import { formatDistanceToNow } from "date-fns";

interface EmailMessage {
  id: number;
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
}

interface VirtualizedEmailListProps {
  emails: EmailMessage[];
  selectedEmail: EmailMessage | null;
  selectedEmails: number[];
  onEmailSelect: (email: EmailMessage) => void;
  onEmailToggle: (emailId: number, checked: boolean) => void;
  onStarToggle: (emailId: number) => void;
  hasNextPage: boolean;
  loadMoreEmails: () => void;
  height: number;
}

export default function VirtualizedEmailList({
  emails,
  selectedEmail,
  selectedEmails,
  onEmailSelect,
  onEmailToggle,
  onStarToggle,
  hasNextPage,
  loadMoreEmails,
  height,
}: VirtualizedEmailListProps) {
  const listRef = useRef<List>(null);

  const itemCount = hasNextPage ? emails.length + 1 : emails.length;
  const loadMoreItemsStartIndex = emails.length;

  const isItemLoaded = useCallback(
    (index: number) => !hasNextPage || index < emails.length,
    [hasNextPage, emails.length],
  );

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

  const Row = ({
    index,
    style,
  }: {
    index: number;
    style: React.CSSProperties;
  }) => {
    if (!isItemLoaded(index)) {
      return (
        <div style={style} className="flex items-center justify-center p-4">
          <div className="text-sm text-muted-foreground">Loading more...</div>
        </div>
      );
    }

    const email = emails[index];

    return (
      <div style={style} className="px-4">
        <div
          className={cn(
            "flex items-start gap-2 rounded-lg border p-3 text-sm transition-all hover:bg-accent",
            selectedEmail?.id === email.id && "bg-muted",
          )}
        >
          <input
            type="checkbox"
            checked={selectedEmails.includes(email.id)}
            onChange={(e) => {
              e.stopPropagation();
              onEmailToggle(email.id, e.target.checked);
            }}
            className="mt-1"
            onClick={(e) => e.stopPropagation()}
          />
          <div
            className={cn(
              "flex flex-1 flex-col items-start gap-2 text-left cursor-pointer",
              !email.isRead && "font-semibold",
            )}
            onClick={() => onEmailSelect(email)}
          >
            <div className="flex w-full flex-col gap-1">
              <div className="flex items-center">
                <div className="flex items-center gap-2">
                  <div className="flex items-center gap-1">
                    {getStatusIcon(email.status)}
                    <span className="font-medium">{email.sender}</span>
                  </div>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      onStarToggle(email.id);
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
                <span
                  className={cn("text-xs", getSentimentColor(email.sentiment))}
                >
                  {email.sentiment}
                </span>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  };

  return (
    <InfiniteLoader
      isItemLoaded={isItemLoaded}
      itemCount={itemCount}
      loadMoreItems={loadMoreEmails}
      minimumBatchSize={10}
      threshold={10}
    >
      {({ onItemsRendered, ref }) => (
        <List
          ref={(list) => {
            ref(list);
            if (list) {
              (listRef as any).current = list;
            }
          }}
          height={height}
          itemCount={itemCount}
          itemSize={120}
          onItemsRendered={onItemsRendered}
          width="100%"
        >
          {Row}
        </List>
      )}
    </InfiniteLoader>
  );
}
