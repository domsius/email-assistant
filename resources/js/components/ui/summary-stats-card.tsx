import * as React from "react";
import { cn } from "@/lib/utils";
import { type LucideIcon } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cva, type VariantProps } from "class-variance-authority";

const statsCardVariants = cva("relative overflow-hidden", {
  variants: {
    trend: {
      up: "before:absolute before:inset-0 before:bg-gradient-to-br before:from-green-500/5 before:to-transparent",
      down: "before:absolute before:inset-0 before:bg-gradient-to-br before:from-red-500/5 before:to-transparent",
      neutral: "",
    },
  },
  defaultVariants: {
    trend: "neutral",
  },
});

export interface SummaryStatsCardProps
  extends React.ComponentPropsWithoutRef<typeof Card>,
    VariantProps<typeof statsCardVariants> {
  title: string;
  value: string | number;
  description?: string;
  icon?: LucideIcon;
  trend?: {
    value: number;
    label?: string;
  };
  loading?: boolean;
}

export function SummaryStatsCard({
  className,
  title,
  value,
  description,
  icon: Icon,
  trend,
  loading = false,
  ...props
}: SummaryStatsCardProps) {
  const trendDirection = trend
    ? trend.value > 0
      ? "up"
      : trend.value < 0
        ? "down"
        : "neutral"
    : "neutral";

  return (
    <Card
      className={cn(statsCardVariants({ trend: trendDirection }), className)}
      {...props}
    >
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="space-y-2">
            <div className="h-7 w-20 animate-pulse rounded bg-muted" />
            <div className="h-4 w-16 animate-pulse rounded bg-muted" />
          </div>
        ) : (
          <>
            <div className="flex items-baseline space-x-2">
              <div className="text-2xl font-bold">{value}</div>
              {trend && (
                <span
                  className={cn(
                    "text-xs font-medium",
                    trend.value > 0 && "text-green-600 dark:text-green-500",
                    trend.value < 0 && "text-red-600 dark:text-red-500",
                    trend.value === 0 && "text-muted-foreground",
                  )}
                >
                  {trend.value > 0 && "+"}
                  {trend.value}%{trend.label && ` ${trend.label}`}
                </span>
              )}
            </div>
            {description && (
              <p className="text-xs text-muted-foreground mt-1">
                {description}
              </p>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
}

// Compound component for grouped stats
export interface StatsGroupProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode;
  columns?: 1 | 2 | 3 | 4 | 5 | 6;
}

export function StatsGroup({
  children,
  columns = 4,
  className,
  ...props
}: StatsGroupProps) {
  const gridCols = {
    1: "grid-cols-1",
    2: "grid-cols-1 md:grid-cols-2",
    3: "grid-cols-1 md:grid-cols-2 lg:grid-cols-3",
    4: "grid-cols-1 md:grid-cols-2 lg:grid-cols-4",
    5: "grid-cols-1 md:grid-cols-2 lg:grid-cols-5",
    6: "grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6",
  };

  return (
    <div className={cn("grid gap-4", gridCols[columns], className)} {...props}>
      {children}
    </div>
  );
}
