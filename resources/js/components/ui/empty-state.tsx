import * as React from "react";
import { cn } from "@/lib/utils";

interface EmptyStateProps extends React.HTMLAttributes<HTMLDivElement> {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: React.ReactNode;
}

export function EmptyState({
  icon,
  title,
  description,
  action,
  className,
  ...props
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        "flex min-h-[400px] flex-col items-center justify-center space-y-4 text-center",
        className,
      )}
      {...props}
    >
      {icon && (
        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
          {icon}
        </div>
      )}
      <div className="space-y-2">
        <h3 className="text-xl font-semibold">{title}</h3>
        {description && (
          <p className="text-sm text-muted-foreground max-w-sm mx-auto">
            {description}
          </p>
        )}
      </div>
      {action && <div className="pt-4">{action}</div>}
    </div>
  );
}
