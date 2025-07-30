import React from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";

interface Activity {
  id: string;
  user: {
    name: string;
    email: string;
    avatar?: string;
  };
  action: string;
  target: string;
  timestamp: string;
  type: "email" | "account" | "system";
}

const mockActivities: Activity[] = [
  {
    id: "1",
    user: {
      name: "John Doe",
      email: "john@example.com",
      avatar: "/avatars/01.png",
    },
    action: "processed",
    target: "15 customer emails",
    timestamp: "2 minutes ago",
    type: "email",
  },
  {
    id: "2",
    user: {
      name: "Sarah Smith",
      email: "sarah@example.com",
    },
    action: "connected",
    target: "new email account",
    timestamp: "5 minutes ago",
    type: "account",
  },
  {
    id: "3",
    user: {
      name: "System",
      email: "system@app.com",
    },
    action: "generated",
    target: "AI response for ticket #1234",
    timestamp: "10 minutes ago",
    type: "system",
  },
  {
    id: "4",
    user: {
      name: "Mike Johnson",
      email: "mike@example.com",
    },
    action: "classified",
    target: "25 emails as 'Support'",
    timestamp: "15 minutes ago",
    type: "email",
  },
  {
    id: "5",
    user: {
      name: "Emily Davis",
      email: "emily@example.com",
    },
    action: "updated",
    target: "processing rules",
    timestamp: "30 minutes ago",
    type: "system",
  },
];

export function RecentActivity() {
  return (
    <Card className="col-span-3">
      <CardHeader>
        <CardTitle>Recent Activity</CardTitle>
        <CardDescription>
          Latest actions in your email processing system
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ScrollArea className="h-[350px] pr-4">
          <div className="space-y-4">
            {mockActivities.map((activity) => (
              <div key={activity.id} className="flex items-start space-x-4">
                <Avatar className="h-9 w-9">
                  <AvatarImage
                    src={activity.user.avatar}
                    alt={activity.user.name}
                  />
                  <AvatarFallback>
                    {activity.user.name
                      .split(" ")
                      .map((n) => n[0])
                      .join("")
                      .toUpperCase()}
                  </AvatarFallback>
                </Avatar>
                <div className="flex-1 space-y-1">
                  <div className="flex items-center gap-2">
                    <p className="text-sm font-medium">{activity.user.name}</p>
                    <Badge variant="outline" className="text-xs">
                      {activity.type}
                    </Badge>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    {activity.action}{" "}
                    <span className="font-medium text-foreground">
                      {activity.target}
                    </span>
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {activity.timestamp}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </ScrollArea>
      </CardContent>
    </Card>
  );
}
