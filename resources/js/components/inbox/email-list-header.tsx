import { TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useInbox } from "@/contexts/inbox-context";

interface EmailListHeaderProps {
  currentTab: string;
}

const folderLabels: Record<string, string> = {
  inbox: "Inbox",
  drafts: "Drafts",
  sent: "Sent",
  junk: "Junk",
  trash: "Trash",
  archive: "Archive",
};

export function EmailListHeader({ currentTab }: EmailListHeaderProps) {
  const { activeFolder } = useInbox();

  return (
    <div className="flex items-center px-4 py-2">
      <h1 className="text-xl font-bold">
        {folderLabels[activeFolder] || "Inbox"}
      </h1>
      <TabsList className="ml-auto">
        <TabsTrigger value="all" className="text-zinc-600 dark:text-zinc-200">
          All mail
        </TabsTrigger>
        <TabsTrigger
          value="unread"
          className="text-zinc-600 dark:text-zinc-200"
        >
          Unread
        </TabsTrigger>
      </TabsList>
    </div>
  );
}
