import React, { useState, useCallback } from "react";
import { X, Minus, Square, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ComposePanel } from "./compose-panel";
import { useInbox } from "@/contexts/inbox-context";
import type { ComposeData } from "@/contexts/inbox-context";
import { cn } from "@/lib/utils";

interface ComposeDialogProps {
  composeData: ComposeData;
  originalEmail?: any;
  draftId?: number;
}

export function ComposeDialog({ composeData, originalEmail, draftId }: ComposeDialogProps) {
  const { exitComposeMode, isComposeMinimized, setComposeMinimized } = useInbox();
  const [size] = useState({ width: 540, height: 640 });

  const handleMinimize = useCallback(() => {
    setComposeMinimized(!isComposeMinimized);
  }, [isComposeMinimized, setComposeMinimized]);

  const handleClose = useCallback(() => {
    exitComposeMode();
  }, [exitComposeMode]);

  const getActionLabel = () => {
    switch (composeData.action) {
      case "reply":
        return "Reply";
      case "replyAll":
        return "Reply All";
      case "forward":
        return "Forward";
      default:
        return "New Message";
    }
  };

  const getActionColor = () => {
    switch (composeData.action) {
      case "reply":
        return "bg-primary/10 text-primary hover:bg-primary/20";
      case "replyAll":
        return "bg-green-500/10 text-green-700 hover:bg-green-500/20 dark:text-green-400";
      case "forward":
        return "bg-orange-500/10 text-orange-700 hover:bg-orange-500/20 dark:text-orange-400";
      default:
        return "bg-purple-500/10 text-purple-700 hover:bg-purple-500/20 dark:text-purple-400";
    }
  };

  if (isComposeMinimized) {
    return (
      <div 
        className="fixed z-[9999] transition-all duration-300 ease-in-out"
        style={{ 
          right: 20, 
          bottom: 0
        }}
      >
        <Card className="border border-border shadow-lg hover:shadow-xl transition-shadow duration-200 rounded-lg">
          <div
            className="flex items-center justify-between px-4 py-3 select-none min-w-[320px] rounded-lg"
          >
            <div className="flex items-center gap-3 flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <Mail className="h-4 w-4 text-muted-foreground flex-shrink-0" />
              </div>
              
              <div className="flex items-center gap-2 flex-1 min-w-0">
                <Badge variant="secondary" className={cn("text-xs font-medium flex-shrink-0", getActionColor())}>
                  {getActionLabel()}
                </Badge>
                <span className="text-sm font-medium text-foreground truncate">
                  {composeData.subject || "No Subject"}
                </span>
              </div>
            </div>
            
            <div className="flex items-center gap-1 flex-shrink-0" data-no-drag="true">
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 hover:bg-muted transition-colors"
                onClick={handleMinimize}
                title="Maximize"
              >
                <Square className="h-3.5 w-3.5" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 hover:bg-destructive/20 hover:text-destructive transition-colors"
                onClick={handleClose}
                title="Close"
              >
                <X className="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div 
      className={cn(
        "fixed z-[9999] transition-all duration-300 ease-in-out",
        "animate-in slide-in-from-bottom-5 pointer-events-auto"
      )}
      style={{ 
        right: 45,
        bottom: 45
      }}
    >
      <Card 
        className="bg-card border border-border shadow-2xl flex flex-col rounded-lg overflow-hidden py-0"
        style={{ 
          width: size.width,
          height: size.height,
          pointerEvents: 'auto'
        }}
      >
        {/* Header */}
        <CardHeader className="p-0">
          <div
            className="flex items-center justify-between px-4 py-3 bg-muted/30 border-b border-border select-none"
          >
            <div className="flex items-center gap-3 flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <Mail className="h-4 w-4 text-muted-foreground" />
              </div>
              
              <div className="flex items-center gap-3 flex-1 min-w-0">
                <Badge variant="secondary" className={cn("text-xs font-medium", getActionColor())}>
                  {getActionLabel()}
                </Badge>
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-semibold text-foreground truncate">
                    {composeData.subject || "No Subject"}
                  </div>
                  {composeData.to && (
                    <div className="text-xs text-muted-foreground truncate">
                      To: {composeData.to}
                    </div>
                  )}
                </div>
              </div>
            </div>
            
            <div className="flex items-center gap-1" data-no-drag="true">
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 hover:bg-muted transition-colors"
                onClick={handleMinimize}
                title="Minimize"
              >
                <Minus className="h-3.5 w-3.5" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 hover:bg-destructive/20 hover:text-destructive transition-colors"
                onClick={handleClose}
                title="Close"
              >
                <X className="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        </CardHeader>

        {/* Content */}
        <CardContent className="p-0 flex-1 overflow-hidden">
          <ComposePanel
              composeData={composeData}
              originalEmail={originalEmail}
              draftId={draftId}
              isInDialog={true}
            />
        </CardContent>
      </Card>
    </div>
  );
}