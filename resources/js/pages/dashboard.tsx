import { Link, usePage } from "@inertiajs/react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head } from "@inertiajs/react";
import {
  Mail,
  MailOpen,
  Brain,
  Languages,
  TrendingUp,
  Users,
  FileText,
  AlertCircle,
  CheckCircle,
  Clock,
} from "lucide-react";
import {
  SummaryStatsCard,
  StatsGroup,
} from "@/components/ui/summary-stats-card";
import { OverviewChart } from "@/components/dashboard/overview-chart";
import { RecentActivity } from "@/components/dashboard/recent-activity";
import { DataTable } from "@/components/ui/data-table";

interface DashboardProps {
  stats: {
    totalEmails: number;
    processedEmails: number;
    pendingEmails: number;
    aiResponses: number;
    languagesDetected: number;
    topicsClassified: number;
    connectedAccounts: number;
    totalCustomers: number;
  };
  recentEmails: Array<{
    id: number;
    subject: string;
    sender: string;
    receivedAt: string;
    status: string;
    language?: string;
    topic?: string;
    sentiment?: string;
    urgency?: string;
  }>;
  processingQueue: Array<{
    id: number;
    type: string;
    status: string;
    progress: number;
    createdAt: string;
  }>;
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
  },
];

export default function Dashboard({
  stats = {
    totalEmails: 0,
    processedEmails: 0,
    pendingEmails: 0,
    aiResponses: 0,
    languagesDetected: 0,
    topicsClassified: 0,
    connectedAccounts: 0,
    totalCustomers: 0,
  },
  recentEmails = [],
  processingQueue = [],
}: DashboardProps) {
  const { auth } = usePage().props as any;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />
      <div className="flex-1 space-y-4 p-8 pt-6">
        {/* Header */}
        <div className="flex items-center justify-between space-y-2">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">
              Welcome back, {auth?.user?.name || "User"}
            </h2>
            <p className="text-muted-foreground">
              Here's what's happening with your emails today.
            </p>
          </div>
          <div className="flex items-center space-x-2">
            <Button>
              <Mail className="mr-2 h-4 w-4" />
              Process Emails
            </Button>
          </div>
        </div>

        {/* Stats Cards */}
        <StatsGroup columns={4}>
          <SummaryStatsCard
            title="Total Emails"
            value={stats.totalEmails}
            description={`${stats.processedEmails} processed`}
            icon={Mail}
            trend={
              stats.processedEmails > 0
                ? {
                    value: Math.round(
                      (stats.processedEmails / stats.totalEmails) * 100,
                    ),
                    label: "processed",
                  }
                : undefined
            }
          />
          <SummaryStatsCard
            title="Pending Emails"
            value={stats.pendingEmails}
            description="Awaiting processing"
            icon={Clock}
          />
          <SummaryStatsCard
            title="AI Responses"
            value={stats.aiResponses}
            description="Generated responses"
            icon={Brain}
          />
          <SummaryStatsCard
            title="Languages"
            value={stats.languagesDetected}
            description="Languages detected"
            icon={Languages}
          />
        </StatsGroup>

        {/* Charts and Activity */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
          <OverviewChart />
          <RecentActivity />
        </div>

        {/* Email Processing Queue */}
        <Card>
          <CardHeader>
            <CardTitle>Processing Queue</CardTitle>
            <CardDescription>Emails currently being processed</CardDescription>
          </CardHeader>
          <CardContent>
            <DataTable
              columns={[
                {
                  accessorKey: "subject",
                  header: "Subject",
                },
                {
                  accessorKey: "from",
                  header: "From",
                },
                {
                  accessorKey: "status",
                  header: "Status",
                  cell: ({ row }) => {
                    const status = row.getValue("status") as string;
                    return (
                      <Badge
                        variant={
                          status === "processing"
                            ? "default"
                            : status === "completed"
                              ? "secondary"
                              : "outline"
                        }
                      >
                        {status}
                      </Badge>
                    );
                  },
                },
                {
                  accessorKey: "priority",
                  header: "Priority",
                },
                {
                  accessorKey: "timestamp",
                  header: "Time",
                },
              ]}
              data={processingQueue.map((email) => ({
                subject: email.subject || "No subject",
                from: email.from || "Unknown",
                status: email.status || "pending",
                priority: email.priority || "normal",
                timestamp: email.created_at
                  ? new Date(email.created_at).toLocaleTimeString()
                  : "Unknown",
              }))}
            />
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
