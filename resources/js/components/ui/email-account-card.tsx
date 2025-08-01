import * as React from "react";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { StatusIndicator } from "@/components/ui/status-indicator";
import {
  MetadataDisplay,
  type MetadataItem,
} from "@/components/ui/metadata-display";
import {
  Mail,
  Settings,
  Trash2,
  RefreshCw,
  Calendar,
  Clock,
  AlertCircle,
  Zap,
  Brain,
} from "lucide-react";

export interface EmailAccountStats {
  totalEmails: number;
  processedEmails: number;
  pendingEmails: number;
  lastEmailAt?: string;
}

export interface EmailAccountSettings {
  syncEnabled: boolean;
  syncFrequency: number; // minutes
  processAutomatically: boolean;
}

export interface SyncProgress {
  status: "idle" | "syncing" | "completed" | "failed";
  progress: number;
  total: number;
  percentage: number;
  error?: string | null;
  startedAt?: string;
  completedAt?: string;
}

export interface EmailAccountProps {
  id: number;
  email: string;
  provider: "gmail" | "outlook" | string;
  status: "active" | "syncing" | "error" | "inactive";
  lastSyncAt?: string;
  createdAt: string;
  stats: EmailAccountStats;
  settings: EmailAccountSettings;
  syncProgress?: SyncProgress;
  error?: string;
  onSync?: (id: number) => void;
  onDelete?: (id: number) => void;
  onToggleSync?: (id: number, enabled: boolean) => void;
}

export function EmailAccountCard({
  id,
  email,
  provider,
  status,
  lastSyncAt,
  createdAt,
  stats,
  settings,
  syncProgress,
  error,
  onSync,
  onDelete,
  onToggleSync,
}: EmailAccountProps) {
  // Debug log
  console.log(`EmailAccountCard ${email}:`, { status, syncProgress });
  const getProviderIcon = () => {
    const baseClasses = "h-8 w-8 rounded-full flex items-center justify-center";
    switch (provider) {
      case "gmail":
        return (
          <div className={cn(baseClasses, "bg-red-100 dark:bg-red-900/20")}>
            <Mail className="h-4 w-4 text-red-600 dark:text-red-400" />
          </div>
        );
      case "outlook":
        return (
          <div className={cn(baseClasses, "bg-blue-100 dark:bg-blue-900/20")}>
            <Mail className="h-4 w-4 text-blue-600 dark:text-blue-400" />
          </div>
        );
      default:
        return (
          <div className={cn(baseClasses, "bg-gray-100 dark:bg-gray-900/20")}>
            <Mail className="h-4 w-4 text-gray-600 dark:text-gray-400" />
          </div>
        );
    }
  };

  const progressPercentage =
    stats.totalEmails > 0
      ? (stats.processedEmails / stats.totalEmails) * 100
      : 0;

  const metadataItems: MetadataItem[] = [
    {
      icon: Calendar,
      label: "Connected",
      value: createdAt,
      format: "date",
    },
  ];

  if (lastSyncAt) {
    metadataItems.push({
      icon: Clock,
      label: "Last sync",
      value: lastSyncAt,
      format: "datetime",
    });
  }

  if (stats.lastEmailAt) {
    metadataItems.push({
      icon: Mail,
      label: "Latest email",
      value: stats.lastEmailAt,
      format: "date",
    });
  }

  return (
    <Card className="relative">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3">
            {getProviderIcon()}
            <div>
              <CardTitle className="text-base">{email}</CardTitle>
              <StatusIndicator
                status={status}
                label={status}
                size="sm"
                className="mt-1"
              />
            </div>
          </div>
          {onDelete && (
            <Button
              size="icon"
              variant="ghost"
              onClick={() => onDelete(id)}
              className="h-8 w-8"
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Error Message */}
        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {/* Sync Progress or Stats */}
        {syncProgress && syncProgress.status === "syncing" ? (
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Syncing emails...</span>
              <span className="font-medium">{syncProgress.percentage}%</span>
            </div>
            <Progress value={syncProgress.percentage} className="h-2" />
            <div className="flex justify-between text-xs text-muted-foreground">
              <span>
                {syncProgress.progress} of {syncProgress.total} emails
              </span>
              <span className="text-blue-600 dark:text-blue-400">
                Syncing...
              </span>
            </div>
          </div>
        ) : (
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Total Emails</span>
              <span className="font-medium">{stats.totalEmails}</span>
            </div>
            <Progress value={progressPercentage} />
            <div className="flex justify-between text-xs text-muted-foreground">
              <span>{stats.processedEmails} processed</span>
              <span>{stats.pendingEmails} pending</span>
            </div>
          </div>
        )}

        {/* Metadata */}
        <MetadataDisplay items={metadataItems} size="sm" />

        {/* Settings & Actions */}
        <div className="flex items-center justify-between pt-2 border-t">
          <div className="flex items-center gap-2">
            <Badge
              variant={settings.syncEnabled ? "default" : "secondary"}
              className="text-xs"
            >
              {settings.syncEnabled ? (
                <>
                  <Zap className="h-3 w-3 mr-1" />
                  Auto-sync ON
                </>
              ) : (
                "Auto-sync OFF"
              )}
            </Badge>
            {settings.processAutomatically && (
              <Badge variant="outline" className="text-xs">
                <Brain className="h-3 w-3 mr-1" />
                Auto-process
              </Badge>
            )}
          </div>
          <div className="flex gap-1">
            {onSync && (
              <Button
                size="icon"
                variant="ghost"
                onClick={() => onSync(id)}
                disabled={status === "syncing"}
                className="h-8 w-8"
              >
                <RefreshCw
                  className={cn(
                    "h-4 w-4",
                    status === "syncing" && "animate-spin",
                  )}
                />
              </Button>
            )}
            <Link href={`/email-accounts/${id}/settings`}>
              <Button size="icon" variant="ghost" className="h-8 w-8">
                <Settings className="h-4 w-4" />
              </Button>
            </Link>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
