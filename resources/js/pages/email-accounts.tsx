import { useState, useEffect } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { EmailAccountCard } from "@/components/ui/email-account-card";
import {
  SummaryStatsCard,
  StatsGroup,
} from "@/components/ui/summary-stats-card";
import { EmptyState } from "@/components/ui/empty-state";
import { ProviderIcon } from "@/components/ui/provider-icon";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head } from "@inertiajs/react";
import {
  Mail,
  Plus,
  Settings,
  Trash2,
  RefreshCw,
  CheckCircle,
  AlertCircle,
  Clock,
  Calendar,
  BarChart,
  Zap,
  Shield,
  Link2,
  Brain,
} from "lucide-react";

interface SyncProgress {
  status: "idle" | "syncing" | "completed" | "failed";
  progress: number;
  total: number;
  percentage: number;
  error?: string | null;
  startedAt?: string;
  completedAt?: string;
}

interface EmailAccount {
  id: number;
  email: string;
  provider: "gmail" | "outlook";
  status: "active" | "syncing" | "error" | "inactive";
  lastSyncAt?: string;
  createdAt: string;
  stats: {
    totalEmails: number;
    processedEmails: number;
    pendingEmails: number;
    lastEmailAt?: string;
  };
  settings: {
    syncEnabled: boolean;
    syncFrequency: number; // minutes
    processAutomatically: boolean;
  };
  syncProgress?: SyncProgress;
  error?: string;
}

interface EmailAccountsProps {
  accounts: EmailAccount[];
  canAddMore: boolean;
  maxAccounts: number;
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
  },
  {
    title: "Email Accounts",
    href: "/email-accounts",
  },
];

export default function EmailAccounts({
  accounts: initialAccounts = [],
  canAddMore = true,
  maxAccounts = 5,
}: EmailAccountsProps) {
  const [isAddDialogOpen, setIsAddDialogOpen] = useState(false);
  const [accounts, setAccounts] = useState<EmailAccount[]>(initialAccounts);
  
  // Debug log
  console.log("Email accounts data:", accounts);

  // Poll for sync progress updates
  useEffect(() => {
    const syncingAccounts = accounts.filter(
      (account) => account.syncProgress?.status === "syncing"
    );

    if (syncingAccounts.length === 0) {
      return; // No accounts are syncing
    }

    const pollInterval = setInterval(async () => {
      try {
        // Fetch progress for all syncing accounts
        const updatedAccounts = await Promise.all(
          accounts.map(async (account) => {
            if (account.syncProgress?.status !== "syncing") {
              return account; // Skip non-syncing accounts
            }

            // Fetch sync progress from API
            const response = await fetch(
              `/api/email-accounts/${account.id}/sync-progress`,
              {
                headers: {
                  Accept: "application/json",
                  "X-Requested-With": "XMLHttpRequest",
                  "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
                },
                credentials: "same-origin",
              }
            );

            if (response.ok) {
              const progressData = await response.json();
              
              // Update account with new progress
              return {
                ...account,
                syncProgress: progressData,
                status: progressData.sync_status === "syncing" ? "syncing" : 
                        progressData.sync_status === "failed" ? "error" : "active",
              };
            }

            return account;
          })
        );

        setAccounts(updatedAccounts);

        // Check if all syncs are complete
        const stillSyncing = updatedAccounts.some(
          (account) => account.syncProgress?.status === "syncing"
        );

        if (!stillSyncing) {
          // Reload the page to get fresh data when all syncs are done
          router.reload({ only: ["accounts"] });
        }
      } catch (error) {
        console.error("Failed to fetch sync progress:", error);
      }
    }, 2000); // Poll every 2 seconds

    return () => clearInterval(pollInterval);
  }, [accounts]);

  const handleSync = (accountId: number) => {
    router.post(`/email-accounts/${accountId}/sync`);
  };

  const handleToggleSync = (accountId: number, enabled: boolean) => {
    router.post(`/email-accounts/${accountId}/settings`, {
      syncEnabled: enabled,
    });
  };

  const handleDelete = (accountId: number) => {
    if (confirm("Are you sure you want to remove this email account?")) {
      router.delete(`/email-accounts/${accountId}`);
    }
  };

  const handleAddGmail = () => {
    // Use window.location for OAuth redirect instead of AJAX
    window.location.href = "/email-accounts/connect/gmail";
  };

  const handleAddOutlook = () => {
    // Use window.location for OAuth redirect instead of AJAX
    window.location.href = "/email-accounts/connect/outlook";
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Email Accounts" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 overflow-x-auto">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">
              Email Accounts
            </h1>
            <p className="text-muted-foreground">
              Manage your connected email accounts and sync settings
            </p>
          </div>
          <Dialog open={isAddDialogOpen} onOpenChange={setIsAddDialogOpen}>
            <DialogTrigger asChild>
              <Button disabled={!canAddMore}>
                <Plus className="h-4 w-4 mr-2" />
                Add Account
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add Email Account</DialogTitle>
                <DialogDescription>
                  Connect your email account to start processing emails with AI
                </DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 py-4">
                <Button
                  variant="outline"
                  className="justify-start h-auto p-4"
                  onClick={handleAddGmail}
                >
                  <div className="flex items-center gap-3">
                    <ProviderIcon provider="gmail" />
                    <div className="text-left">
                      <div className="font-medium">Connect Gmail</div>
                      <div className="text-sm text-muted-foreground">
                        Sign in with your Google account
                      </div>
                    </div>
                  </div>
                </Button>
                <Button
                  variant="outline"
                  className="justify-start h-auto p-4"
                  onClick={handleAddOutlook}
                >
                  <div className="flex items-center gap-3">
                    <ProviderIcon provider="outlook" />
                    <div className="text-left">
                      <div className="font-medium">Connect Outlook</div>
                      <div className="text-sm text-muted-foreground">
                        Sign in with your Microsoft account
                      </div>
                    </div>
                  </div>
                </Button>
              </div>
              <div className="text-sm text-muted-foreground">
                <Shield className="h-4 w-4 inline mr-1" />
                Your credentials are securely encrypted and never stored
              </div>
            </DialogContent>
          </Dialog>
        </div>

        {/* Account Limit Alert */}
        {!canAddMore && (
          <Alert>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              You've reached the maximum of {maxAccounts} email accounts. Remove
              an account to add a new one.
            </AlertDescription>
          </Alert>
        )}

        {/* Accounts Grid */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {accounts.length === 0 ? (
            <EmptyState
              icon={<Mail className="h-10 w-10" />}
              title="No email accounts connected"
              description="Connect your first email account to start processing emails with AI"
              action={
                <Button onClick={() => setIsAddDialogOpen(true)}>
                  <Plus className="h-4 w-4 mr-2" />
                  Add Your First Account
                </Button>
              }
              className="md:col-span-2 lg:col-span-3"
            />
          ) : (
            accounts.map((account) => (
              <EmailAccountCard
                key={account.id}
                {...account}
                onSync={handleSync}
                onDelete={handleDelete}
                onToggleSync={handleToggleSync}
              />
            ))
          )}
        </div>

        {/* Summary Stats */}
        {accounts.length > 0 && (
          <StatsGroup columns={4}>
            <SummaryStatsCard
              title="Total Accounts"
              value={accounts.length}
              description={`${maxAccounts - accounts.length} slots available`}
              icon={Mail}
            />
            <SummaryStatsCard
              title="Active Accounts"
              value={accounts.filter((a) => a.status === "active").length}
              description="Currently syncing"
              icon={CheckCircle}
            />
            <SummaryStatsCard
              title="Total Emails"
              value={accounts.reduce((sum, a) => sum + a.stats.totalEmails, 0)}
              description="Across all accounts"
              icon={BarChart}
            />
            <SummaryStatsCard
              title="Processing Rate"
              value={`${(() => {
                const total = accounts.reduce(
                  (sum, a) => sum + a.stats.totalEmails,
                  0,
                );
                const processed = accounts.reduce(
                  (sum, a) => sum + a.stats.processedEmails,
                  0,
                );
                return total > 0 ? Math.round((processed / total) * 100) : 0;
              })()}%`}
              description="Emails processed"
              icon={Brain}
            />
          </StatsGroup>
        )}
      </div>
    </AppLayout>
  );
}
