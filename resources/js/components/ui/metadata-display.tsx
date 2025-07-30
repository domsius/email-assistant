import * as React from "react";
import { cn } from "@/lib/utils";
import { format, formatDistanceToNow } from "date-fns";
import { type LucideIcon } from "lucide-react";

export interface MetadataItem {
  icon?: LucideIcon;
  label?: string;
  value: string | number | Date;
  format?: "date" | "datetime" | "relative" | "number" | "filesize" | "custom";
  formatOptions?: any;
}

export interface MetadataDisplayProps
  extends React.HTMLAttributes<HTMLDivElement> {
  items: MetadataItem[];
  orientation?: "horizontal" | "vertical";
  size?: "sm" | "default" | "lg";
  separator?: boolean;
}

function formatValue(
  value: MetadataItem["value"],
  formatType?: MetadataItem["format"],
  options?: any,
): string {
  if (value === null || value === undefined) return "-";

  switch (formatType) {
    case "date":
      return format(new Date(value), options?.pattern || "MMM d, yyyy");
    case "datetime":
      return format(new Date(value), options?.pattern || "MMM d, yyyy h:mm a");
    case "relative":
      return formatDistanceToNow(new Date(value), {
        addSuffix: true,
        ...options,
      });
    case "number":
      return typeof value === "number"
        ? value.toLocaleString(options?.locale, options?.numberOptions)
        : String(value);
    case "filesize":
      if (typeof value !== "number") return String(value);
      const sizes = ["B", "KB", "MB", "GB", "TB"];
      if (value === 0) return "0 B";
      const i = Math.floor(Math.log(value) / Math.log(1024));
      return `${(value / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
    case "custom":
      return options?.formatter ? options.formatter(value) : String(value);
    default:
      return String(value);
  }
}

export function MetadataDisplay({
  items,
  orientation = "vertical",
  size = "default",
  separator = true,
  className,
  ...props
}: MetadataDisplayProps) {
  const sizeClasses = {
    sm: "text-xs",
    default: "text-sm",
    lg: "text-base",
  };

  const iconSizes = {
    sm: "h-3 w-3",
    default: "h-4 w-4",
    lg: "h-5 w-5",
  };

  return (
    <div
      className={cn(
        "flex gap-3",
        orientation === "vertical"
          ? "flex-col"
          : "flex-row flex-wrap items-center",
        sizeClasses[size],
        className,
      )}
      {...props}
    >
      {items.map((item, index) => {
        const Icon = item.icon;
        const formattedValue = formatValue(
          item.value,
          item.format,
          item.formatOptions,
        );

        return (
          <React.Fragment key={index}>
            <div className="flex items-center gap-1.5 text-muted-foreground">
              {Icon && <Icon className={cn(iconSizes[size])} />}
              {item.label && <span className="font-medium">{item.label}:</span>}
              <span className="text-foreground">{formattedValue}</span>
            </div>
            {separator &&
              orientation === "horizontal" &&
              index < items.length - 1 && (
                <span className="text-muted-foreground/50">•</span>
              )}
          </React.Fragment>
        );
      })}
    </div>
  );
}
