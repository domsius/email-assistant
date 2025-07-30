import * as React from "react";
import { cn } from "@/lib/utils";
import {
  FileText,
  FileType,
  FileIcon,
  FileCode,
  FileImage,
  FileVideo,
  FileAudio,
  FileArchive,
  FileSpreadsheet,
  File,
  type LucideIcon,
} from "lucide-react";
import { cva, type VariantProps } from "class-variance-authority";

const fileIconVariants = cva("flex items-center justify-center rounded-lg", {
  variants: {
    size: {
      sm: "h-8 w-8",
      default: "h-10 w-10",
      lg: "h-12 w-12",
      xl: "h-16 w-16",
    },
  },
  defaultVariants: {
    size: "default",
  },
});

const iconSizeMap = {
  sm: "h-4 w-4",
  default: "h-5 w-5",
  lg: "h-6 w-6",
  xl: "h-8 w-8",
};

export interface FileTypeConfig {
  icon: LucideIcon;
  bgColor: string;
  iconColor: string;
}

const fileTypeMap: Record<string, FileTypeConfig> = {
  // Documents
  pdf: {
    icon: FileType,
    bgColor: "bg-red-100 dark:bg-red-900/20",
    iconColor: "text-red-600 dark:text-red-400",
  },
  doc: {
    icon: FileText,
    bgColor: "bg-blue-100 dark:bg-blue-900/20",
    iconColor: "text-blue-600 dark:text-blue-400",
  },
  docx: {
    icon: FileText,
    bgColor: "bg-blue-100 dark:bg-blue-900/20",
    iconColor: "text-blue-600 dark:text-blue-400",
  },
  txt: {
    icon: FileIcon,
    bgColor: "bg-gray-100 dark:bg-gray-900/20",
    iconColor: "text-gray-600 dark:text-gray-400",
  },
  md: {
    icon: FileCode,
    bgColor: "bg-green-100 dark:bg-green-900/20",
    iconColor: "text-green-600 dark:text-green-400",
  },

  // Spreadsheets
  xls: {
    icon: FileSpreadsheet,
    bgColor: "bg-emerald-100 dark:bg-emerald-900/20",
    iconColor: "text-emerald-600 dark:text-emerald-400",
  },
  xlsx: {
    icon: FileSpreadsheet,
    bgColor: "bg-emerald-100 dark:bg-emerald-900/20",
    iconColor: "text-emerald-600 dark:text-emerald-400",
  },
  csv: {
    icon: FileSpreadsheet,
    bgColor: "bg-emerald-100 dark:bg-emerald-900/20",
    iconColor: "text-emerald-600 dark:text-emerald-400",
  },

  // Code
  js: {
    icon: FileCode,
    bgColor: "bg-yellow-100 dark:bg-yellow-900/20",
    iconColor: "text-yellow-600 dark:text-yellow-400",
  },
  jsx: {
    icon: FileCode,
    bgColor: "bg-cyan-100 dark:bg-cyan-900/20",
    iconColor: "text-cyan-600 dark:text-cyan-400",
  },
  ts: {
    icon: FileCode,
    bgColor: "bg-blue-100 dark:bg-blue-900/20",
    iconColor: "text-blue-600 dark:text-blue-400",
  },
  tsx: {
    icon: FileCode,
    bgColor: "bg-cyan-100 dark:bg-cyan-900/20",
    iconColor: "text-cyan-600 dark:text-cyan-400",
  },
  json: {
    icon: FileCode,
    bgColor: "bg-orange-100 dark:bg-orange-900/20",
    iconColor: "text-orange-600 dark:text-orange-400",
  },

  // Images
  jpg: {
    icon: FileImage,
    bgColor: "bg-purple-100 dark:bg-purple-900/20",
    iconColor: "text-purple-600 dark:text-purple-400",
  },
  jpeg: {
    icon: FileImage,
    bgColor: "bg-purple-100 dark:bg-purple-900/20",
    iconColor: "text-purple-600 dark:text-purple-400",
  },
  png: {
    icon: FileImage,
    bgColor: "bg-purple-100 dark:bg-purple-900/20",
    iconColor: "text-purple-600 dark:text-purple-400",
  },
  gif: {
    icon: FileImage,
    bgColor: "bg-purple-100 dark:bg-purple-900/20",
    iconColor: "text-purple-600 dark:text-purple-400",
  },
  svg: {
    icon: FileImage,
    bgColor: "bg-purple-100 dark:bg-purple-900/20",
    iconColor: "text-purple-600 dark:text-purple-400",
  },

  // Video
  mp4: {
    icon: FileVideo,
    bgColor: "bg-pink-100 dark:bg-pink-900/20",
    iconColor: "text-pink-600 dark:text-pink-400",
  },
  avi: {
    icon: FileVideo,
    bgColor: "bg-pink-100 dark:bg-pink-900/20",
    iconColor: "text-pink-600 dark:text-pink-400",
  },
  mov: {
    icon: FileVideo,
    bgColor: "bg-pink-100 dark:bg-pink-900/20",
    iconColor: "text-pink-600 dark:text-pink-400",
  },

  // Audio
  mp3: {
    icon: FileAudio,
    bgColor: "bg-indigo-100 dark:bg-indigo-900/20",
    iconColor: "text-indigo-600 dark:text-indigo-400",
  },
  wav: {
    icon: FileAudio,
    bgColor: "bg-indigo-100 dark:bg-indigo-900/20",
    iconColor: "text-indigo-600 dark:text-indigo-400",
  },

  // Archives
  zip: {
    icon: FileArchive,
    bgColor: "bg-amber-100 dark:bg-amber-900/20",
    iconColor: "text-amber-600 dark:text-amber-400",
  },
  rar: {
    icon: FileArchive,
    bgColor: "bg-amber-100 dark:bg-amber-900/20",
    iconColor: "text-amber-600 dark:text-amber-400",
  },
  "7z": {
    icon: FileArchive,
    bgColor: "bg-amber-100 dark:bg-amber-900/20",
    iconColor: "text-amber-600 dark:text-amber-400",
  },
};

// Default configuration for unknown file types
const defaultFileType: FileTypeConfig = {
  icon: File,
  bgColor: "bg-gray-100 dark:bg-gray-900/20",
  iconColor: "text-gray-600 dark:text-gray-400",
};

export interface FileTypeIconProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof fileIconVariants> {
  fileType: string;
  filename?: string;
}

export function FileTypeIcon({
  fileType,
  filename,
  size,
  className,
  ...props
}: FileTypeIconProps) {
  // Extract extension from filename if fileType not provided
  const extension = fileType || filename?.split(".").pop()?.toLowerCase() || "";
  const config = fileTypeMap[extension] || defaultFileType;
  const Icon = config.icon;

  return (
    <div
      className={cn(fileIconVariants({ size }), config.bgColor, className)}
      {...props}
    >
      <Icon className={cn(iconSizeMap[size || "default"], config.iconColor)} />
    </div>
  );
}

// Utility function to get file type from filename
export function getFileType(filename: string): string {
  return filename.split(".").pop()?.toLowerCase() || "";
}

// Utility function to check if file type is supported
export function isFileTypeSupported(fileType: string): boolean {
  return fileType in fileTypeMap;
}
