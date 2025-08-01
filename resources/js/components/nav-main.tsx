import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/components/ui/sidebar";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { type NavItem } from "@/types";
import { Link, usePage } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";
import { useState } from "react";

export function NavMain({ items = [] }: { items: NavItem[] }) {
  const page = usePage();
  const [openItems, setOpenItems] = useState<string[]>(() => {
    // Auto-open the All Mail item if we're on the inbox page
    return page.url.startsWith('/inbox') ? ['All Mail'] : [];
  });

  const toggleOpen = (title: string) => {
    setOpenItems(prev => 
      prev.includes(title) 
        ? prev.filter(item => item !== title)
        : [...prev, title]
    );
  };

  return (
    <SidebarGroup className="px-2 py-0">
      <SidebarGroupLabel>Platform</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => {
          const hasSubitems = item.subitems && item.subitems.length > 0;
          const isOpen = openItems.includes(item.title);
          const isActiveParent = page.url.startsWith(item.href);

          if (hasSubitems) {
            return (
              <Collapsible key={item.title} open={isOpen} onOpenChange={() => toggleOpen(item.title)}>
                <SidebarMenuItem>
                  <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                      isActive={isActiveParent}
                      tooltip={{ children: item.title }}
                    >
                      {item.icon && <item.icon />}
                      <span>{item.title}</span>
                      <ChevronRight className={`ml-auto h-4 w-4 transition-transform ${isOpen ? 'rotate-90' : ''}`} />
                    </SidebarMenuButton>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <SidebarMenuSub>
                      {item.subitems.map((subitem) => (
                        <SidebarMenuSubItem key={subitem.title}>
                          <SidebarMenuSubButton
                            asChild={!subitem.onClick}
                            isActive={subitem.isActive || (subitem.href && page.url.includes(subitem.href))}
                            onClick={subitem.onClick}
                          >
                            {subitem.onClick ? (
                              <button type="button" className="flex w-full items-center">
                                {subitem.icon && <subitem.icon className="mr-2 h-4 w-4" />}
                                <span>{subitem.title}</span>
                                {subitem.count !== undefined && subitem.count > 0 && (
                                  <span className="ml-auto text-xs text-muted-foreground">
                                    {subitem.count}
                                  </span>
                                )}
                              </button>
                            ) : (
                              <Link href={subitem.href} prefetch>
                                {subitem.icon && <subitem.icon className="mr-2 h-4 w-4" />}
                                <span>{subitem.title}</span>
                                {subitem.count !== undefined && subitem.count > 0 && (
                                  <span className="ml-auto text-xs text-muted-foreground">
                                    {subitem.count}
                                  </span>
                                )}
                              </Link>
                            )}
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      ))}
                    </SidebarMenuSub>
                  </CollapsibleContent>
                </SidebarMenuItem>
              </Collapsible>
            );
          }

          return (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton
                asChild
                isActive={isActiveParent}
                tooltip={{ children: item.title }}
              >
                <Link href={item.href} prefetch>
                  {item.icon && <item.icon />}
                  <span>{item.title}</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          );
        })}
      </SidebarMenu>
    </SidebarGroup>
  );
}
