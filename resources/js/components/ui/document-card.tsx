import * as React from "react";
import { cn } from "@/lib/utils";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { StatusIndicator } from "@/components/ui/status-indicator";
import { FileTypeIcon } from "@/components/ui/file-type-icon";
import {
  MetadataDisplay,
  type MetadataItem,
} from "@/components/ui/metadata-display";
import {
  MoreVertical,
  Download,
  Eye,
  Trash2,
  RefreshCw,
  AlertCircle,
  Database,
  Brain,
} from "lucide-react";

export interface DocumentProps {
  id: number;
  title: string;
  filename: string;
  type: string;
  size: number;
  status: "pending" | "processing" | "processed" | "error";
  chunks?: number;
  embeddings?: number;
  uploadedAt: string;
  processedAt?: string;
  error?: string;
  onView?: (id: number) => void;
  onDownload?: (id: number) => void;
  onDelete?: (id: number) => void;
  onReprocess?: (id: number) => void;
}

export function DocumentCard({
  id,
  title,
  filename,
  type,
  size,
  status,
  chunks = 0,
  embeddings = 0,
  uploadedAt,
  processedAt,
  error,
  onView,
  onDownload,
  onDelete,
  onReprocess,
}: DocumentProps) {
  const metadataItems: MetadataItem[] = [
    {
      label: "Size",
      value: size,
      format: "filesize",
    },
    {
      label: "Uploaded",
      value: uploadedAt,
      format: "relative",
    },
  ];

  if (processedAt) {
    metadataItems.push({
      label: "Processed",
      value: processedAt,
      format: "relative",
    });
  }

  return (
    <Card className="group relative overflow-hidden transition-all hover:shadow-md">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-2">
          <div className="flex items-start gap-3 min-w-0">
            <FileTypeIcon fileType={type} size="default" />
            <div className="flex-1 min-w-0">
              <CardTitle className="text-base line-clamp-1">{title}</CardTitle>
              <p className="text-xs text-muted-foreground line-clamp-1 mt-1">
                {filename}
              </p>
            </div>
          </div>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <MoreVertical className="h-4 w-4" />
                <span className="sr-only">Document options</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {onView && (
                <DropdownMenuItem onClick={() => onView(id)}>
                  <Eye className="mr-2 h-4 w-4" />
                  View
                </DropdownMenuItem>
              )}
              {onDownload && (
                <DropdownMenuItem onClick={() => onDownload(id)}>
                  <Download className="mr-2 h-4 w-4" />
                  Download
                </DropdownMenuItem>
              )}
              {onReprocess && status === "error" && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={() => onReprocess(id)}>
                    <RefreshCw className="mr-2 h-4 w-4" />
                    Reprocess
                  </DropdownMenuItem>
                </>
              )}
              {onDelete && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => onDelete(id)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="mr-2 h-4 w-4" />
                    Delete
                  </DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {/* Status */}
        <div className="flex items-center justify-between">
          <StatusIndicator status={status} label={status} size="sm" />
          {status === "processed" && (
            <div className="flex items-center gap-3 text-xs text-muted-foreground">
              <span className="flex items-center gap-1">
                <Database className="h-3 w-3" />
                {chunks} chunks
              </span>
              <span className="flex items-center gap-1">
                <Brain className="h-3 w-3" />
                {embeddings} embeddings
              </span>
            </div>
          )}
        </div>

        {/* Error Message */}
        {error && (
          <Alert variant="destructive" className="py-2">
            <AlertCircle className="h-3 w-3" />
            <AlertDescription className="text-xs">{error}</AlertDescription>
          </Alert>
        )}

        {/* Metadata */}
        <MetadataDisplay
          items={metadataItems}
          orientation="horizontal"
          size="sm"
          separator
        />
      </CardContent>
    </Card>
  );
}

// List variant for more compact display
export interface DocumentListItemProps extends DocumentProps {
  variant?: "card" | "list";
}

export function DocumentListItem({
  variant = "list",
  ...props
}: DocumentListItemProps) {
  if (variant === "card") {
    return <DocumentCard {...props} />;
  }

  const {
    id,
    title,
    filename,
    type,
    size,
    status,
    chunks = 0,
    embeddings = 0,
    uploadedAt,
    error,
    onView,
    onDownload,
    onDelete,
    onReprocess,
  } = props;

  return (
    <div className="group flex items-center gap-4 rounded-lg border p-4 transition-colors hover:bg-accent/50">
      <FileTypeIcon fileType={type} size="sm" />

      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h4 className="text-sm font-medium line-clamp-1">{title}</h4>
            <p className="text-xs text-muted-foreground line-clamp-1">
              {filename}
            </p>
          </div>
          <StatusIndicator status={status} size="sm" />
        </div>

        {error && (
          <p className="text-xs text-destructive mt-1 line-clamp-1">{error}</p>
        )}

        <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
          <MetadataDisplay
            items={[
              { value: size, format: "filesize" },
              { value: uploadedAt, format: "relative" },
            ]}
            orientation="horizontal"
            size="sm"
            separator
          />
          {status === "processed" && (
            <>
              <span>•</span>
              <span>{chunks} chunks</span>
              <span>•</span>
              <span>{embeddings} embeddings</span>
            </>
          )}
        </div>
      </div>

      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        {onView && (
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => onView(id)}
          >
            <Eye className="h-4 w-4" />
          </Button>
        )}
        {onDownload && (
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => onDownload(id)}
          >
            <Download className="h-4 w-4" />
          </Button>
        )}
        {onDelete && (
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => onDelete(id)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        )}
      </div>
    </div>
  );
}
