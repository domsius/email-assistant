import { NavFooter } from "@/components/nav-footer";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import { type NavItem } from "@/types";
import { Link, usePage } from "@inertiajs/react";
import {
  FileText,
  Inbox,
  LayoutGrid,
  Mail,
} from "lucide-react";
import { useInboxNavigation } from "@/hooks/use-inbox-navigation";
import AppLogo from "./app-logo";

const baseNavItems: NavItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
    icon: LayoutGrid,
  },
  {
    title: "All Mail",
    href: "/inbox",
    icon: Inbox,
  },
  {
    title: "Email Accounts",
    href: "/email-accounts",
    icon: Mail,
  },
  {
    title: "Knowledge Base",
    href: "/knowledge-base",
    icon: FileText,
  },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
  const inboxSubitems = useInboxNavigation();
  
  // Create dynamic navigation items
  const mainNavItems = baseNavItems.map(item => {
    if (item.title === "All Mail" && inboxSubitems) {
      return {
        ...item,
        subitems: inboxSubitems,
      };
    }
    return item;
  });

  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/" prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <NavMain items={mainNavItems} />
      </SidebarContent>

      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
