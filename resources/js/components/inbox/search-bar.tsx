import { useEffect, useRef } from "react";
import { router } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Search } from "lucide-react";
import { useDebounce } from "@/hooks/use-debounce";
import { useInbox } from "@/contexts/inbox-context";

export function SearchBar() {
  const {
    searchQuery,
    setSearchQuery,
    activeFolder,
    selectedAccount,
    activeFilter,
  } = useInbox();
  const debouncedSearchQuery = useDebounce(searchQuery, 300);
  const isInitialMount = useRef(true);
  const previousSearchQuery = useRef(searchQuery);

  useEffect(() => {
    // Skip the effect on initial mount and when only the search query changes
    const searchQueryChanged =
      previousSearchQuery.current !== debouncedSearchQuery;
    previousSearchQuery.current = debouncedSearchQuery;

    if (isInitialMount.current) {
      isInitialMount.current = false;
      return;
    }

    // Only navigate if the search query actually changed
    if (searchQueryChanged && debouncedSearchQuery !== undefined) {
      // Get current URL parameters to preserve page and per_page
      const currentUrl = new URL(window.location.href);
      const currentPerPage = currentUrl.searchParams.get("per_page");

      // Build query parameters, excluding empty values
      const params: Record<string, any> = {
        folder: activeFolder,
        filter: activeFilter,
      };

      // When search changes, reset to page 1
      params.page = "1";

      // Preserve the per_page parameter if it exists
      if (currentPerPage) {
        params.per_page = currentPerPage;
      }

      if (selectedAccount) {
        params.account = selectedAccount;
      }

      if (debouncedSearchQuery) {
        params.search = debouncedSearchQuery;
      }

      router.get("/inbox", params, {
        preserveScroll: true,
        preserveState: true,
        only: ["emails", "pagination"],
      });
    }
  }, [debouncedSearchQuery, activeFolder, selectedAccount, activeFilter]);

  return (
    <div className="px-4 py-2">
      <div className="relative">
        <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
        <Input
          placeholder="Search emails..."
          className="pl-8"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
        />
      </div>
    </div>
  );
}
