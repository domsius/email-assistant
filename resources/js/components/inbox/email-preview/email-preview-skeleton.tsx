import { Skeleton } from "@/components/ui/skeleton";

export function EmailPreviewSkeleton() {
  return (
    <div className="flex h-full flex-col">
      {/* Header */}
      <div className="flex items-center justify-between border-b p-4">
        <div className="space-y-2">
          <Skeleton className="h-6 w-96" /> {/* Subject */}
          <div className="flex items-center gap-2">
            <Skeleton className="h-4 w-24" /> {/* From label */}
            <Skeleton className="h-4 w-48" /> {/* Sender */}
            <Skeleton className="h-4 w-32" /> {/* Date */}
          </div>
        </div>
        <div className="flex gap-2">
          <Skeleton className="h-8 w-8" /> {/* Reply */}
          <Skeleton className="h-8 w-8" /> {/* Reply All */}
          <Skeleton className="h-8 w-8" /> {/* Forward */}
          <Skeleton className="h-8 w-8" /> {/* More */}
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 p-6 space-y-4">
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-full mt-6" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-2/3" />
        <Skeleton className="h-4 w-full mt-6" />
        <Skeleton className="h-4 w-5/6" />
      </div>
    </div>
  );
}
