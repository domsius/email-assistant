import React from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

export function OverviewChart() {
  return (
    <Card className="col-span-4">
      <CardHeader>
        <CardTitle>Overview</CardTitle>
        <CardDescription>Email processing trends over time</CardDescription>
      </CardHeader>
      <CardContent className="pl-2">
        <Tabs defaultValue="week" className="space-y-4">
          <TabsList>
            <TabsTrigger value="week">Week</TabsTrigger>
            <TabsTrigger value="month">Month</TabsTrigger>
            <TabsTrigger value="year">Year</TabsTrigger>
          </TabsList>
          <TabsContent value="week" className="space-y-4">
            <div className="h-[350px] flex items-center justify-center text-muted-foreground">
              <div className="text-center">
                <svg
                  className="mx-auto h-12 w-12 text-gray-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
                <p className="mt-2 text-sm">
                  Chart visualization would go here
                </p>
                <p className="text-xs text-muted-foreground">
                  Connect a charting library to visualize data
                </p>
              </div>
            </div>
          </TabsContent>
          <TabsContent value="month" className="space-y-4">
            <div className="h-[350px] flex items-center justify-center text-muted-foreground">
              <p>Monthly view coming soon</p>
            </div>
          </TabsContent>
          <TabsContent value="year" className="space-y-4">
            <div className="h-[350px] flex items-center justify-center text-muted-foreground">
              <p>Yearly view coming soon</p>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}
