import { useState, useEffect, useRef, useCallback } from "react";
import axios from "axios";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  Send,
  X,
  Paperclip,
  Bold,
  Italic,
  Underline,
  Link,
  Image,
  Smile,
  MoreHorizontal,
  ChevronDown,
} from "lucide-react";
import { useInbox } from "@/contexts/inbox-context";

interface ComposePanelProps {
  composeData: {
    to: string;
    cc?: string;
    bcc?: string;
    subject: string;
    body: string;
    action?: "new" | "reply" | "replyAll" | "forward" | "draft";
    inReplyTo?: string;
    references?: string[];
  };
  originalEmail?: {
    id: number | string;
    subject: string;
    sender: string;
    senderEmail: string;
    receivedAt: string;
    content: string;
  };
  draftId?: number | null;
}

export function ComposePanel({
  composeData,
  originalEmail,
  draftId: initialDraftId,
}: ComposePanelProps) {
  const { exitComposeMode } = useInbox();
  const [showCc, setShowCc] = useState(!!composeData.cc);
  const [showBcc, setShowBcc] = useState(!!composeData.bcc);
  const [isSending, setIsSending] = useState(false);
  const [draftId, setDraftId] = useState<number | null>(initialDraftId || null);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [isSavingDraft, setIsSavingDraft] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [formData, setFormData] = useState({
    to: composeData.to,
    cc: composeData.cc,
    bcc: composeData.bcc,
    subject: composeData.subject,
    body: composeData.body,
  });

  const bodyRef = useRef<HTMLTextAreaElement>(null);
  const saveTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Helper function to handle form changes
  const handleFormChange = (field: string, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
  };

  useEffect(() => {
    // Focus on appropriate field
    if (!formData.to && composeData.action !== "forward") {
      document.getElementById("compose-to")?.focus();
    } else if (!formData.subject) {
      document.getElementById("compose-subject")?.focus();
    } else {
      bodyRef.current?.focus();
      // Place cursor at beginning of body for replies
      if (composeData.action === "reply" || composeData.action === "replyAll") {
        bodyRef.current?.setSelectionRange(0, 0);
      }
    }
  }, []);

  // Auto-save draft
  const saveDraft = useCallback(async () => {
    if (isSavingDraft) return;

    setIsSavingDraft(true);
    try {
      const response = await axios.post("/drafts/save", {
        id: draftId,
        to: formData.to,
        cc: formData.cc,
        bcc: formData.bcc,
        subject: formData.subject,
        body: formData.body,
        action: composeData.action,
        inReplyTo: composeData.inReplyTo,
        references: composeData.references,
        originalEmailId: originalEmail?.id,
      });

      if (!draftId && response.data.id) {
        setDraftId(response.data.id);
      }
      setLastSaved(new Date());
      setHasUnsavedChanges(false);
    } catch (error) {
      console.error("Failed to save draft:", error);
      toast.error("Failed to save draft");
    } finally {
      setIsSavingDraft(false);
    }
  }, [formData, draftId, composeData, originalEmail, isSavingDraft]);

  // Debounced auto-save
  useEffect(() => {
    // Only set up auto-save if there are unsaved changes
    if (!hasUnsavedChanges) {
      return;
    }

    // Clear existing timeout
    if (saveTimeoutRef.current) {
      clearTimeout(saveTimeoutRef.current);
    }

    // Don't auto-save if nothing to save
    if (
      !formData.to &&
      !formData.cc &&
      !formData.bcc &&
      !formData.subject &&
      !formData.body
    ) {
      return;
    }

    // Set new timeout for auto-save
    saveTimeoutRef.current = setTimeout(() => {
      saveDraft();
    }, 2000); // Save after 2 seconds of inactivity

    // Cleanup
    return () => {
      if (saveTimeoutRef.current) {
        clearTimeout(saveTimeoutRef.current);
      }
    };
  }, [formData, saveDraft, hasUnsavedChanges]);

  const handleSend = () => {
    if (!formData.to && !formData.cc && !formData.bcc) {
      alert("Please add at least one recipient");
      return;
    }

    if (!formData.subject) {
      if (!confirm("Send email without a subject?")) {
        return;
      }
    }

    setIsSending(true);

    // TODO: Implement actual send functionality
    console.log("Sending email:", {
      ...formData,
      inReplyTo: composeData.inReplyTo,
      references: composeData.references,
    });

    // For now, just exit compose mode
    setTimeout(() => {
      exitComposeMode();
    }, 1000);
  };

  const handleCancel = () => {
    if (
      formData.body ||
      formData.to ||
      formData.subject !== composeData.subject
    ) {
      if (!confirm("Discard this email?")) {
        return;
      }
    }
    exitComposeMode();
  };

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
    <div className="flex flex-col h-full">
      <Card className="flex-1 flex flex-col">
        <CardHeader className="border-b px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <h2 className="text-lg font-semibold">{getActionLabel()}</h2>
              {lastSaved && (
                <span className="text-sm text-muted-foreground">
                  Draft saved {lastSaved.toLocaleTimeString()}
                </span>
              )}
              {isSavingDraft && (
                <span className="text-sm text-muted-foreground">Saving...</span>
              )}
            </div>
            <Button variant="ghost" size="icon" onClick={handleCancel}>
              <X className="h-4 w-4" />
            </Button>
          </div>
        </CardHeader>



        <CardContent className="flex-1 flex flex-col p-0">
          <div className="space-y-0">
            {/* To Field */}
            <div className="flex items-center border-b px-6 py-3">
              <Label htmlFor="compose-to" className="text-sm font-medium w-20">
                To
              </Label>
              <Input
                id="compose-to"
                type="email"
                placeholder="Recipients"
                value={formData.to}
                onChange={(e) => handleFormChange("to", e.target.value)}
                className="flex-1 border-0 bg-transparent placeholder:text-muted-foreground focus-visible:ring-0 px-3"
              />
              <div className="flex items-center gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowCc(!showCc)}
                  className="text-xs text-muted-foreground hover:text-foreground"
                >
                  Cc
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowBcc(!showBcc)}
                  className="text-xs text-muted-foreground hover:text-foreground"
                >
                  Bcc
                </Button>
              </div>
            </div>

            {/* CC Field */}
            {showCc && (
              <div className="flex items-center border-b px-6 py-3">
                <Label htmlFor="compose-cc" className="text-sm font-medium w-20">
                  Cc
                </Label>
                <Input
                  id="compose-cc"
                  type="email"
                  placeholder="Cc Recipients"
                  value={formData.cc}
                  onChange={(e) => handleFormChange("cc", e.target.value)}
                  className="flex-1 border-0 bg-transparent placeholder:text-muted-foreground focus-visible:ring-0 px-3"
                />
              </div>
            )}

            {/* BCC Field */}
            {showBcc && (
              <div className="flex items-center border-b px-6 py-3">
                <Label htmlFor="compose-bcc" className="text-sm font-medium w-20">
                  Bcc
                </Label>
                <Input
                  id="compose-bcc"
                  type="email"
                  placeholder="Bcc Recipients"
                  value={formData.bcc}
                  onChange={(e) => handleFormChange("bcc", e.target.value)}
                  className="flex-1 border-0 bg-transparent placeholder:text-muted-foreground focus-visible:ring-0 px-3"
                />
              </div>
            )}

            {/* Subject Field */}
            <div className="flex items-center border-b px-6 py-3">
              <Label htmlFor="compose-subject" className="text-sm font-medium w-20">
                Subject
              </Label>
              <Input
                id="compose-subject"
                type="text"
                placeholder="Subject"
                value={formData.subject}
                onChange={(e) => handleFormChange("subject", e.target.value)}
                className="flex-1 border-0 bg-transparent placeholder:text-muted-foreground focus-visible:ring-0 px-3"
              />
            </div>
          </div>

          {/* Body */}
          <div className="flex-1 px-6 py-4">
            <Textarea
              ref={bodyRef}
              placeholder="Write your message..."
              value={formData.body}
              onChange={(e) => handleFormChange("body", e.target.value)}
              className="min-h-[300px] h-full resize-none border-0 bg-transparent placeholder:text-muted-foreground focus-visible:ring-0 p-0"
            />
          </div>

          {/* Original Email Preview */}
          {originalEmail && (
            <div className="border-t bg-muted/10">
              <div className="px-6 py-4">
                <div className="text-sm text-muted-foreground mb-3">
                  On {new Date(originalEmail.receivedAt).toLocaleString()},{" "}
                  {originalEmail.sender} &lt;{originalEmail.senderEmail}&gt;
                  wrote:
                </div>
                <div className="border-l-4 border-muted pl-4">
                  <div
                    dangerouslySetInnerHTML={{ __html: originalEmail.content }}
                    className="prose prose-sm max-w-none text-muted-foreground [&>*]:text-muted-foreground"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Toolbar */}
          <div className="border-t">
            <div className="flex items-center justify-between px-6 py-3">
              <div className="flex items-center gap-1">
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Attach file">
                  <Paperclip className="h-4 w-4" />
                </Button>
                <Separator orientation="vertical" className="mx-1 h-6" />
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Bold">
                    <Bold className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Italic">
                    <Italic className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Underline">
                    <Underline className="h-4 w-4" />
                  </Button>
                </div>
                <Separator orientation="vertical" className="mx-1 h-6" />
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Insert link">
                    <Link className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Insert image">
                    <Image className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="Insert emoji">
                    <Smile className="h-4 w-4" />
                  </Button>
                </div>
                <Separator orientation="vertical" className="mx-1 h-6" />
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0" title="More options">
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              </div>

              <div className="flex items-center gap-2">
                <Button variant="ghost" size="sm" onClick={handleCancel}>
                  Cancel
                </Button>
                <Button size="sm" onClick={handleSend} disabled={isSending}>
                  {isSending ? (
                    <>Sending...</>
                  ) : (
                    <>
                      Send
                      <Send className="ml-2 h-4 w-4" />
                    </>
                  )}
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}