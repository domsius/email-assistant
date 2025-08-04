import React, { useState, useCallback } from "react";
import { format } from "date-fns";
import { router } from "@inertiajs/react";
import { 
  ArrowLeft,
  Reply,
  ReplyAll,
  Forward,
  Star,
  Paperclip,
  Send,
  Bold,
  Italic,
  Underline,
  Link,
  ImageIcon,
  Sparkles,
  Download
} from "lucide-react";
import { cn, authenticatedFetch } from "@/lib/utils";
import { EmailMessage } from "@/types/inbox";
import { useInbox } from "@/contexts/inbox-context";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

interface EmailDetailViewProps {
  email: EmailMessage;
  onBackToList: () => void;
}

interface ReplyState {
  isReplying: boolean;
  replyType: "reply" | "replyAll" | "forward" | null;
  to: string;
  cc: string;
  subject: string;
  body: string;
}

export function EmailDetailView({ email, onBackToList }: EmailDetailViewProps) {
  const { handleToggleStar, enterComposeMode } = useInbox();
  
  const [replyState, setReplyState] = useState<ReplyState>({
    isReplying: false,
    replyType: null,
    to: "",
    cc: "",
    subject: "",
    body: ""
  });
  const [isGeneratingAI, setIsGeneratingAI] = useState(false);

  const handleReply = useCallback((type: "reply" | "replyAll" | "forward") => {
    let defaultTo = "";
    let defaultSubject = "";
    
    switch (type) {
      case "reply":
        defaultTo = email.senderEmail;
        defaultSubject = email.subject.startsWith("Re: ") ? email.subject : `Re: ${email.subject}`;
        break;
      case "replyAll":
        defaultTo = email.senderEmail;
        defaultSubject = email.subject.startsWith("Re: ") ? email.subject : `Re: ${email.subject}`;
        break;
      case "forward":
        defaultTo = "";
        defaultSubject = email.subject.startsWith("Fwd: ") ? email.subject : `Fwd: ${email.subject}`;
        break;
    }
    
    setReplyState({
      isReplying: true,
      replyType: type,
      to: defaultTo,
      cc: "",
      subject: defaultSubject,
      body: ""
    });
  }, [email]);

  const handleCancelReply = useCallback(async () => {
    // Save as draft if there's content
    if (replyState.body.trim() || replyState.subject.trim()) {
      try {
        const response = await authenticatedFetch('/drafts/save', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            to: replyState.to,
            cc: replyState.cc,
            subject: replyState.subject,
            body: replyState.body,
            in_reply_to: email.id,
            is_reply: replyState.replyType === 'reply' || replyState.replyType === 'replyAll',
          }),
        });

        if (response.ok) {
          toast.success('Draft saved');
        } else {
          throw new Error('Failed to save draft');
        }
      } catch (error) {
        console.error('Failed to save draft:', error);
        toast.error('Failed to save draft');
      }
    }
    
    // Reset reply state
    setReplyState({
      isReplying: false,
      replyType: null,
      to: "",
      cc: "",
      subject: "",
      body: ""
    });
  }, [replyState, email.id]);

  const handleSendReply = useCallback(() => {
    // Use the existing compose functionality
    enterComposeMode({
      to: replyState.to,
      cc: replyState.cc,
      subject: replyState.subject,
      body: replyState.body,
      action: replyState.replyType || "reply",
      inReplyTo: email.id.toString(),
      originalEmail: email,
    });
    
    // Reset reply state
    handleCancelReply();
  }, [replyState, email, enterComposeMode, handleCancelReply]);

  const handleGenerateAI = async () => {
    if (!email || !email.id) {
      toast.error("No email to respond to");
      return;
    }

    setIsGeneratingAI(true);

    try {
      const response = await authenticatedFetch(
        `/api/emails/${email.id}/generate-response`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({
            tone: "professional",
            style: "conversational",
            include_signature: true,
          }),
        },
      );

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      // Handle the response - check for different possible response structures
      let aiContent = "";
      if (data?.data?.draft?.ai_generated_content) {
        aiContent = data.data.draft.ai_generated_content;
      } else if (data?.response) {
        aiContent = data.response;
      } else if (data?.ai_generated_content) {
        aiContent = data.ai_generated_content;
      }

      if (aiContent) {
        setReplyState(prev => ({ ...prev, body: aiContent }));
        toast.success("AI response generated successfully");
      } else {
        console.error("Unexpected response format:", data);
        toast.error("Could not generate response - unexpected format");
      }
    } catch (error) {
      console.error("Error generating AI response:", error);
      toast.error("Failed to generate AI response. Please try again.");
    } finally {
      setIsGeneratingAI(false);
    }
  };

  const getInitials = (name: string) => {
    return name
      .split(" ")
      .map(n => n[0])
      .join("")
      .toUpperCase()
      .slice(0, 2);
  };

  return (
    <div className="flex flex-col h-full bg-background">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b">
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            className="h-8 w-8 p-0 cursor-pointer"
            onClick={onBackToList}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <span className="text-sm text-muted-foreground">Back to list</span>
        </div>
        
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => handleToggleStar(email.id)}
            className="h-8 w-8 p-0 cursor-pointer"
          >
            <Star
              className={cn(
                "h-4 w-4",
                email.isStarred
                  ? "fill-yellow-400 text-yellow-400"
                  : "text-muted-foreground hover:text-yellow-400"
              )}
            />
          </Button>
        </div>
      </div>

      {/* Email Content */}
      <div className="flex-1 overflow-auto bg-white">
        <div className="mx-auto p-6">
          {/* Email Header */}
          <div className="mb-6">
            {/* <h1 className="text-3xl font-semibold mb-4 leading-tight">
              {email.subject || "(No Subject)"}
            </h1> */}
            
            <div className="flex items-start gap-4">
              <Avatar className="h-10 w-10">
                <AvatarFallback className="bg-primary/10 text-primary">
                  {getInitials(email.sender)}
                </AvatarFallback>
              </Avatar>
              
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <span className="font-semibold">{email.sender}</span>
                  <span className="text-muted-foreground">&lt;{email.senderEmail}&gt;</span>
                </div>
                
                <div className="text-sm text-muted-foreground">
                  {format(
                    new Date(email.receivedAt || email.date || new Date()), 
                    "EEEE, MMMM d, yyyy 'at' h:mm a"
                  )}
                </div>
                
                {email.to && (
                  <div className="text-sm text-muted-foreground mt-1">
                    to {email.to}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Attachments */}
          {email.attachments && email.attachments.length > 0 && (
            <div className="mb-6 p-4 bg-muted/30 rounded-lg">
              <div className="flex items-center gap-2 mb-3">
                <Paperclip className="h-4 w-4" />
                <span className="font-medium">
                  {email.attachments.length} attachment{email.attachments.length > 1 ? 's' : ''}
                </span>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                {email.attachments.map((attachment) => (
                  <a
                    key={attachment.id} 
                    href={`/emails/${email.id}/attachments/${attachment.id}/download`}
                    download={attachment.filename}
                    className="group flex items-center gap-2 p-2 bg-background rounded border hover:bg-muted/50 cursor-pointer transition-colors relative"
                  >
                    <div className="h-8 w-8 bg-primary/10 rounded flex items-center justify-center">
                      <span className="text-xs font-medium text-primary">
                        {attachment.filename.split('.').pop()?.toUpperCase().slice(0, 3)}
                      </span>
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-medium truncate">{attachment.filename}</div>
                      <div className="text-xs text-muted-foreground">
                        {(attachment.size / 1024).toFixed(1)} KB
                      </div>
                    </div>
                    <Download className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                  </a>
                ))}
              </div>
            </div>
          )}

          {/* Email Body */}
          <div className="prose prose-sm max-w-none mb-8">
            <div 
              className="email-content"
              dangerouslySetInnerHTML={{ 
                __html: email.content || email.plainTextContent || email.snippet || "No content available" 
              }} 
            />
          </div>

          {/* Action Buttons */}
          {!replyState.isReplying && (
            <div className="flex items-center gap-2 mb-6">
              <Button 
                variant="outline" 
                onClick={() => handleReply("reply")}
                className="gap-2"
              >
                <Reply className="h-4 w-4" />
                Reply
              </Button>
              <Button 
                variant="outline" 
                onClick={() => handleReply("replyAll")}
                className="gap-2"
              >
                <ReplyAll className="h-4 w-4" />
                Reply All
              </Button>
              <Button 
                variant="outline" 
                onClick={() => handleReply("forward")}
                className="gap-2"
              >
                <Forward className="h-4 w-4" />
                Forward
              </Button>
            </div>
          )}

          {/* Inline Reply Composer */}
          {replyState.isReplying && (
            <div className="border rounded-lg p-4 bg-white">
              <div className="mb-4">
                <h3 className="font-semibold mb-3 capitalize">
                  {replyState.replyType} to {email.sender}
                </h3>
                
                <div className="space-y-3">
                  <div>
                    <Input
                      placeholder="To"
                      value={replyState.to}
                      onChange={(e) => setReplyState(prev => ({ ...prev, to: e.target.value }))}
                    />
                  </div>
                  
                  {(replyState.replyType === "replyAll" || replyState.cc) && (
                    <div>
                      <Input
                        placeholder="Cc"
                        value={replyState.cc}
                        onChange={(e) => setReplyState(prev => ({ ...prev, cc: e.target.value }))}
                      />
                    </div>
                  )}
                  
                  <div>
                    <Input
                      placeholder="Subject"
                      value={replyState.subject}
                      onChange={(e) => setReplyState(prev => ({ ...prev, subject: e.target.value }))}
                    />
                  </div>
                </div>
              </div>

              {/* Formatting Toolbar */}
              <div className="flex items-center gap-1 mb-3 p-2 bg-background rounded border">
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                  <Bold className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                  <Italic className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                  <Underline className="h-4 w-4" />
                </Button>
                <Separator orientation="vertical" className="h-6" />
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                  <Link className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                  <ImageIcon className="h-4 w-4" />
                </Button>
              </div>

              {/* Reply Body */}
              <div className="mb-4 relative">
                <Textarea
                  placeholder="Type your message..."
                  value={replyState.body}
                  onChange={(e) => setReplyState(prev => ({ ...prev, body: e.target.value }))}
                  className="min-h-[120px] resize-none"
                />
                {/* Generate with AI button - show only when body is empty */}
                {!replyState.body.trim() && (
                  <div className="absolute bottom-2 left-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={handleGenerateAI}
                      disabled={isGeneratingAI}
                      className="gap-2"
                      type="button"
                    >
                      <Sparkles className="h-4 w-4" />
                      {isGeneratingAI ? "Generating..." : "Generate with AI"}
                    </Button>
                  </div>
                )}
              </div>

              {/* Reply Actions */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Button onClick={handleSendReply} className="gap-2">
                    <Send className="h-4 w-4" />
                    Send
                  </Button>
                  <Button variant="outline" onClick={handleCancelReply}>
                    {replyState.body.trim() || replyState.subject.trim() ? 'Save as Draft' : 'Cancel'}
                  </Button>
                </div>
                
                <div className="text-xs text-muted-foreground">
                  Press Ctrl+Enter to send
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}