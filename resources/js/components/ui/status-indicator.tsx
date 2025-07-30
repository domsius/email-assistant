import * as React from "react";
import { cn } from "@/lib/utils";
import { cva, type VariantProps } from "class-variance-authority";
import {
  CheckCircle,
  Clock,
  AlertCircle,
  RefreshCw,
  XCircle,
  type LucideIcon,
} from "lucide-react";

const statusIndicatorVariants = cva(
  "inline-flex items-center gap-1.5 text-sm font-medium",
  {
    variants: {
      status: {
        success: "text-green-600 dark:text-green-500",
        pending: "text-orange-600 dark:text-orange-500",
        processing: "text-blue-600 dark:text-blue-500",
        error: "text-red-600 dark:text-red-500",
        inactive: "text-gray-500 dark:text-gray-400",
      },
      size: {
        default: "text-sm",
        sm: "text-xs",
        lg: "text-base",
      },
    },
    defaultVariants: {
      status: "pending",
      size: "default",
    },
  },
);

const statusIcons: Record<string, LucideIcon> = {
  success: CheckCircle,
  active: CheckCircle,
  processed: CheckCircle,
  completed: CheckCircle,
  pending: Clock,
  waiting: Clock,
  scheduled: Clock,
  processing: RefreshCw,
  syncing: RefreshCw,
  loading: RefreshCw,
  error: AlertCircle,
  failed: XCircle,
  inactive: Clock,
};

export interface StatusIndicatorProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof statusIndicatorVariants> {
  label?: string;
  showIcon?: boolean;
  iconClassName?: string;
  pulse?: boolean;
}

export function StatusIndicator({
  className,
  status,
  size,
  label,
  showIcon = true,
  iconClassName,
  pulse = false,
  ...props
}: StatusIndicatorProps) {
  const Icon = status ? statusIcons[status] || Clock : Clock;
  const shouldPulse = pulse || status === "processing" || status === "syncing";

  const iconSize = {
    sm: "h-3 w-3",
    default: "h-4 w-4",
    lg: "h-5 w-5",
  }[size || "default"];

  return (
    <div
      className={cn(statusIndicatorVariants({ status, size }), className)}
      {...props}
    >
      {showIcon && (
        <Icon
          className={cn(
            iconSize,
            shouldPulse && "animate-pulse",
            status === "syncing" || status === "processing"
              ? "animate-spin"
              : "",
            iconClassName,
          )}
        />
      )}
      {label && <span>{label}</span>}
    </div>
  );
}
