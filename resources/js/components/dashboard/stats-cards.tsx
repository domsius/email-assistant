import React from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  MailIcon,
  CheckCircle,
  Clock,
  AlertCircle,
  TrendingUp,
  Users,
  Globe,
  BrainIcon,
} from "lucide-react";

interface StatCardProps {
  title: string;
  value: string | number;
  description?: string;
  icon: React.ReactNode;
  trend?: {
    value: number;
    isPositive: boolean;
  };
}

function StatCard({ title, value, description, icon, trend }: StatCardProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        {icon}
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        {description && (
          <p className="text-xs text-muted-foreground">{description}</p>
        )}
        {trend && (
          <div className="flex items-center gap-1 mt-2">
            <TrendingUp
              className={`h-3 w-3 ${trend.isPositive ? "text-green-500" : "text-red-500"}`}
            />
            <span
              className={`text-xs ${trend.isPositive ? "text-green-500" : "text-red-500"}`}
            >
              {trend.isPositive ? "+" : ""}
              {trend.value}%
            </span>
            <span className="text-xs text-muted-foreground">
              from last month
            </span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

interface StatsCardsProps {
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
}

export function StatsCards({ stats }: StatsCardsProps) {
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <StatCard
        title="Total Emails"
        value={stats.totalEmails.toLocaleString()}
        description="All time email count"
        icon={<MailIcon className="h-4 w-4 text-muted-foreground" />}
        trend={{ value: 12.5, isPositive: true }}
      />
      <StatCard
        title="Processed"
        value={stats.processedEmails.toLocaleString()}
        description={`${((stats.processedEmails / stats.totalEmails) * 100).toFixed(1)}% completion rate`}
        icon={<CheckCircle className="h-4 w-4 text-green-500" />}
      />
      <StatCard
        title="Pending"
        value={stats.pendingEmails.toLocaleString()}
        description="Awaiting processing"
        icon={<Clock className="h-4 w-4 text-orange-500" />}
      />
      <StatCard
        title="AI Responses"
        value={stats.aiResponses.toLocaleString()}
        description="Generated automatically"
        icon={<BrainIcon className="h-4 w-4 text-blue-500" />}
        trend={{ value: 8.2, isPositive: true }}
      />
    </div>
  );
}
