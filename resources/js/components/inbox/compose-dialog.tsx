import React, { useState, useCallback } from "react";
import { X, Minus, Square, Move } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { ComposePanel } from "./compose-panel";
import { useInbox } from "@/contexts/inbox-context";
import type { ComposeData } from "@/contexts/inbox-context";

interface ComposeDialogProps {
  composeData: ComposeData;
  originalEmail?: any;
  draftId?: number;
}

export function ComposeDialog({ composeData, originalEmail, draftId }: ComposeDialogProps) {
  const { exitComposeMode, isComposeMinimized, setComposeMinimized } = useInbox();
  const [position, setPosition] = useState({ x: window.innerWidth - 520, y: window.innerHeight - 620 });
  const [size, setSize] = useState({ width: 500, height: 600 });
  const [isDragging, setIsDragging] = useState(false);
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 });

  const handleMouseDown = useCallback((e: React.MouseEvent) => {
    if (e.target !== e.currentTarget) return; // Only drag from header
    setIsDragging(true);
    setDragStart({
      x: e.clientX - position.x,
      y: e.clientY - position.y,
    });
  }, [position]);

  const handleMouseMove = useCallback((e: MouseEvent) => {
    if (!isDragging) return;
    
    const newX = Math.max(0, Math.min(window.innerWidth - size.width, e.clientX - dragStart.x));
    const newY = Math.max(0, Math.min(window.innerHeight - (isComposeMinimized ? 60 : size.height), e.clientY - dragStart.y));
    
    setPosition({ x: newX, y: newY });
  }, [isDragging, dragStart, size, isComposeMinimized]);

  const handleMouseUp = useCallback(() => {
    setIsDragging(false);
  }, []);

  // Add mouse event listeners
  React.useEffect(() => {
    if (isDragging) {
      document.addEventListener('mousemove', handleMouseMove);
      document.addEventListener('mouseup', handleMouseUp);
      
      return () => {
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
      };
    }
  }, [isDragging, handleMouseMove, handleMouseUp]);

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

  return (
    <div className="fixed z-[9999]" style={{ left: position.x, top: position.y }}>
      <Card 
        className={`bg-white border shadow-2xl transition-all duration-200 ${
          isComposeMinimized ? 'h-14' : ''
        }`}
        style={{ 
          width: size.width,
          ...(isComposeMinimized ? {} : { height: size.height })
        }}
      >
        {/* Header */}
        <div
          className={`flex items-center justify-between px-4 py-3 border-b cursor-move select-none ${
            isComposeMinimized ? 'border-b-0' : ''
          }`}
          onMouseDown={handleMouseDown}
        >
          <div className="flex items-center gap-2">
            <Move className="h-4 w-4 text-muted-foreground" />
            <span className="text-sm font-medium truncate">
              {getActionLabel()}
              {composeData.subject && ` - ${composeData.subject}`}
            </span>
          </div>
          
          <div className="flex items-center gap-1">
            <Button
              variant="ghost"
              size="sm"
              className="h-6 w-6 p-0 hover:bg-muted"
              onClick={handleMinimize}
            >
              {isComposeMinimized ? <Square className="h-3 w-3" /> : <Minus className="h-3 w-3" />}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="h-6 w-6 p-0 hover:bg-muted"
              onClick={handleClose}
            >
              <X className="h-3 w-3" />
            </Button>
          </div>
        </div>

        {/* Content */}
        {!isComposeMinimized && (
          <div className="overflow-hidden" style={{ height: size.height - 60 }}>
            <ComposePanel
              composeData={composeData}
              originalEmail={originalEmail}
              draftId={draftId}
              isInDialog={true}
            />
          </div>
        )}
      </Card>
    </div>
  );
}