import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { PaginationLinks, PaginationMeta } from "@/types/inbox";
import { useInbox } from "@/contexts/inbox-context";

interface PaginationControlsProps {
  pagination: {
    links: PaginationLinks;
    meta: PaginationMeta;
  };
}

export function PaginationControls({ pagination }: PaginationControlsProps) {
  if (!pagination || pagination.meta.last_page <= 1) {
    return null;
  }

  const handlePageChange = (url: string | null) => {
    
    if (url) {
      // Parse the URL and preserve existing query parameters
      const parsedUrl = new URL(url, window.location.origin);
      const currentUrl = new URL(window.location.href);
      

      // Remove Inertia-specific parameters that shouldn't be in the URL
      const inertiaParams = ["preserveState", "preserveScroll", "replace", "only"];
      inertiaParams.forEach(param => {
        parsedUrl.searchParams.delete(param);
      });

      // Preserve existing query parameters (except Inertia ones)
      currentUrl.searchParams.forEach((value, key) => {
        if (key !== "page" && !parsedUrl.searchParams.has(key) && !inertiaParams.includes(key)) {
          // Only add non-empty values
          if (value && value.trim() !== "") {
            parsedUrl.searchParams.set(key, value);
          }
        }
      });

      const finalUrl = parsedUrl.pathname + parsedUrl.search;

      router.get(finalUrl, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
        only: ["emails", "pagination"],
      });
    }
  };

  const handlePerPageChange = (value: string) => {
    
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set("per_page", value);
    currentUrl.searchParams.set("page", "1"); // Reset to first page

    // Remove Inertia-specific parameters
    const inertiaParams = ["preserveState", "preserveScroll", "replace", "only"];
    inertiaParams.forEach(param => {
      currentUrl.searchParams.delete(param);
    });

    const newUrl = currentUrl.pathname + currentUrl.search;

    router.get(newUrl, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
      only: ["emails", "pagination"],
    });
  };

  return (
    <div className="flex items-center justify-between border-t px-4 py-2">
      <div className="text-xs text-muted-foreground">
        Showing {pagination.meta.from || 0} to {pagination.meta.to || 0} of{" "}
        {pagination.meta.total} emails
      </div>
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2">
          <span className="text-sm text-muted-foreground">Show:</span>
          <Select
            value={pagination.meta.per_page.toString()}
            onValueChange={handlePerPageChange}
          >
            <SelectTrigger className="h-8 w-[70px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="5">5</SelectItem>
              <SelectItem value="10">10</SelectItem>
              <SelectItem value="25">25</SelectItem>
              <SelectItem value="50">50</SelectItem>
              <SelectItem value="100">100</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(pagination.links.prev)}
            disabled={!pagination.links.prev}
          >
            Previous
          </Button>
          <span className="text-sm">
            Page {pagination.meta.current_page} of {pagination.meta.last_page}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(pagination.links.next)}
            disabled={!pagination.links.next}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  );
}
