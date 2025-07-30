import { Skeleton } from "@/components/ui/skeleton";

export function EmailListSkeleton() {
  return (
    <div className="flex flex-col">
      {[...Array(5)].map((_, index) => (
        <div key={index} className="flex items-start gap-4 border-b p-4">
          {/* Checkbox skeleton */}
          <Skeleton className="h-4 w-4 mt-1" />

          {/* Star skeleton */}
          <Skeleton className="h-4 w-4 mt-1" />

          {/* Email content skeleton */}
          <div className="flex-1 space-y-2">
            <div className="flex items-center gap-2">
              <Skeleton className="h-4 w-32" /> {/* Sender */}
              <Skeleton className="h-4 w-20" /> {/* Time */}
            </div>
            <Skeleton className="h-5 w-3/4" /> {/* Subject */}
            <Skeleton className="h-4 w-full" /> {/* Snippet line 1 */}
            <Skeleton className="h-4 w-2/3" /> {/* Snippet line 2 */}
          </div>
        </div>
      ))}
    </div>
  );
}
