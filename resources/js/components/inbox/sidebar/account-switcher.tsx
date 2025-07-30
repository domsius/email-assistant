import React from "react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";
import { MoreVertical } from "lucide-react";
import { router } from "@inertiajs/react";
import { useInbox } from "@/contexts/inbox-context";

export const AccountSwitcher = React.memo(function AccountSwitcher() {
  const { emailAccounts, selectedAccount, isCollapsed } = useInbox();
  const currentAccount = emailAccounts.find((a) => a.id === selectedAccount);
  const displayEmail = currentAccount?.email || "All Accounts";
  const initials = displayEmail.substring(0, 2).toUpperCase();

  return (
    <div
      className={cn(
        "flex h-[52px] items-center justify-center px-2",
        isCollapsed ? "h-[52px]" : "px-2",
      )}
    >
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            className={cn(
              "w-full justify-start",
              isCollapsed && "justify-center",
            )}
          >
            <Avatar className="h-5 w-5">
              <AvatarImage src="/avatars/01.png" alt={displayEmail} />
              <AvatarFallback>{initials}</AvatarFallback>
            </Avatar>
            {!isCollapsed && (
              <>
                <span className="ml-2 text-sm font-medium">{displayEmail}</span>
                <MoreVertical className="ml-auto h-4 w-4" />
              </>
            )}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="w-[200px]">
          <DropdownMenuLabel>Email Accounts</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onClick={() => {
              const params = new URLSearchParams(window.location.search);
              params.delete("account");
              params.delete("page"); // Reset to page 1
              router.get(`/inbox?${params.toString()}`);
            }}
          >
            All Accounts
          </DropdownMenuItem>
          {emailAccounts.map((account) => (
            <DropdownMenuItem
              key={account.id}
              onClick={() => {
                const params = new URLSearchParams(window.location.search);
                params.set("account", account.id.toString());
                params.delete("page"); // Reset to page 1
                router.get(`/inbox?${params.toString()}`);
              }}
            >
              {account.email}
            </DropdownMenuItem>
          ))}
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => router.get("/email-accounts")}>
            Manage accounts
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
});
