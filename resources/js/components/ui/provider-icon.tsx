import * as React from "react";
import { cn } from "@/lib/utils";
import { Mail, type LucideIcon } from "lucide-react";
import { cva, type VariantProps } from "class-variance-authority";

const providerIconVariants = cva(
  "rounded-full flex items-center justify-center",
  {
    variants: {
      size: {
        sm: "h-6 w-6",
        default: "h-8 w-8",
        lg: "h-10 w-10",
        xl: "h-12 w-12",
      },
    },
    defaultVariants: {
      size: "default",
    },
  },
);

const iconSizeMap = {
  sm: "h-3 w-3",
  default: "h-4 w-4",
  lg: "h-5 w-5",
  xl: "h-6 w-6",
};

export interface ProviderConfig {
  icon: LucideIcon;
  bgColor: string;
  iconColor: string;
  label: string;
}

const providerMap: Record<string, ProviderConfig> = {
  gmail: {
    icon: Mail,
    bgColor: "bg-red-100 dark:bg-red-900/20",
    iconColor: "text-red-600 dark:text-red-400",
    label: "Gmail",
  },
  google: {
    icon: Mail,
    bgColor: "bg-red-100 dark:bg-red-900/20",
    iconColor: "text-red-600 dark:text-red-400",
    label: "Google",
  },
  outlook: {
    icon: Mail,
    bgColor: "bg-blue-100 dark:bg-blue-900/20",
    iconColor: "text-blue-600 dark:text-blue-400",
    label: "Outlook",
  },
  microsoft: {
    icon: Mail,
    bgColor: "bg-blue-100 dark:bg-blue-900/20",
    iconColor: "text-blue-600 dark:text-blue-400",
    label: "Microsoft",
  },
  yahoo: {
    icon: Mail,
    bgColor: "bg-purple-100 dark:bg-purple-900/20",
    iconColor: "text-purple-600 dark:text-purple-400",
    label: "Yahoo",
  },
  icloud: {
    icon: Mail,
    bgColor: "bg-gray-100 dark:bg-gray-900/20",
    iconColor: "text-gray-600 dark:text-gray-400",
    label: "iCloud",
  },
  imap: {
    icon: Mail,
    bgColor: "bg-slate-100 dark:bg-slate-900/20",
    iconColor: "text-slate-600 dark:text-slate-400",
    label: "IMAP/SMTP",
  },
  zoho: {
    icon: Mail,
    bgColor: "bg-yellow-100 dark:bg-yellow-900/20",
    iconColor: "text-yellow-600 dark:text-yellow-400",
    label: "Zoho Mail",
  },
  protonmail: {
    icon: Mail,
    bgColor: "bg-indigo-100 dark:bg-indigo-900/20",
    iconColor: "text-indigo-600 dark:text-indigo-400",
    label: "ProtonMail",
  },
};

// Default configuration for unknown providers
const defaultProvider: ProviderConfig = {
  icon: Mail,
  bgColor: "bg-gray-100 dark:bg-gray-900/20",
  iconColor: "text-gray-600 dark:text-gray-400",
  label: "Email",
};

export interface ProviderIconProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof providerIconVariants> {
  provider: string;
  showLabel?: boolean;
  labelClassName?: string;
}

export function ProviderIcon({
  provider,
  size,
  showLabel = false,
  labelClassName,
  className,
  ...props
}: ProviderIconProps) {
  const config = providerMap[provider.toLowerCase()] || defaultProvider;
  const Icon = config.icon;

  if (showLabel) {
    return (
      <div className="flex items-center gap-2" {...props}>
        <div
          className={cn(
            providerIconVariants({ size }),
            config.bgColor,
            className,
          )}
        >
          <Icon
            className={cn(iconSizeMap[size || "default"], config.iconColor)}
          />
        </div>
        <span className={cn("font-medium", labelClassName)}>
          {config.label}
        </span>
      </div>
    );
  }

  return (
    <div
      className={cn(providerIconVariants({ size }), config.bgColor, className)}
      {...props}
    >
      <Icon className={cn(iconSizeMap[size || "default"], config.iconColor)} />
    </div>
  );
}

// Utility function to get provider label
export function getProviderLabel(provider: string): string {
  return providerMap[provider.toLowerCase()]?.label || provider;
}

// Utility function to check if provider is supported
export function isProviderSupported(provider: string): boolean {
  return provider.toLowerCase() in providerMap;
}
