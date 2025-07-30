import { Head } from "@inertiajs/react";
import { router } from "@inertiajs/react";
import { useState, useEffect, useRef, useCallback } from "react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import axios from "axios";
import { Send, X, Paperclip, Mail, ArrowLeft } from "lucide-react";

interface ComposeData {
  action: "new" | "reply" | "replyAll" | "forward";
  to: string;
  cc: string;
  bcc: string;
  subject: string;
  body: string;
  inReplyTo?: string | null;
  references?: string | null;
}

interface OriginalEmail {
  id: number;
  subject: string;
  from: string;
  date: string;
}

interface ComposeProps {
  composeData: ComposeData;
  originalEmail?: OriginalEmail | null;
  draftId?: number | null;
}

const breadcrumbs = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Inbox", href: "/inbox" },
  { title: "Compose", href: "/compose" },
];

export default function Compose({
  composeData,
  originalEmail,
  draftId: initialDraftId,
}: ComposeProps) {
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
      document.getElementById("to")?.focus();
    } else if (!formData.subject) {
      document.getElementById("subject")?.focus();
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

    // For now, just redirect back to inbox
    setTimeout(() => {
      router.visit("/inbox");
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
    router.visit("/inbox");
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
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Compose Email" />

      <div className="container max-w-5xl mx-auto p-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="icon"
                onClick={() => router.visit("/inbox")}
              >
                <ArrowLeft className="h-4 w-4" />
              </Button>
              <CardTitle className="text-2xl flex items-center gap-2">
                <Mail className="h-5 w-5" />
                {getActionLabel()}
              </CardTitle>
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                onClick={handleCancel}
                disabled={isSending}
              >
                <X className="h-4 w-4 mr-2" />
                Cancel
              </Button>
              <Button onClick={handleSend} disabled={isSending}>
                <Send className="h-4 w-4 mr-2" />
                {isSending ? "Sending..." : "Send"}
              </Button>
            </div>
          </CardHeader>

          <CardContent className="space-y-4">
            {originalEmail && composeData.action !== "new" && (
              <div className="bg-muted/50 rounded-lg p-3 text-sm">
                <div className="font-medium">
                  {composeData.action === "forward"
                    ? "Forwarding"
                    : "Replying to"}
                  :
                </div>
                <div className="text-muted-foreground">
                  {originalEmail.subject} • From: {originalEmail.from}
                </div>
              </div>
            )}

            <div className="space-y-4">
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Label htmlFor="to" className="w-16">
                    To:
                  </Label>
                  <Input
                    id="to"
                    type="email"
                    placeholder="recipient@example.com"
                    value={formData.to}
                    onChange={(e) => handleFormChange("to", e.target.value)}
                    className="flex-1"
                  />
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setShowCc(!showCc)}
                  >
                    Cc
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setShowBcc(!showBcc)}
                  >
                    Bcc
                  </Button>
                </div>

                {showCc && (
                  <div className="flex items-center gap-2">
                    <Label htmlFor="cc" className="w-16">
                      Cc:
                    </Label>
                    <Input
                      id="cc"
                      type="email"
                      placeholder="cc@example.com"
                      value={formData.cc}
                      onChange={(e) => handleFormChange("cc", e.target.value)}
                      className="flex-1"
                    />
                  </div>
                )}

                {showBcc && (
                  <div className="flex items-center gap-2">
                    <Label htmlFor="bcc" className="w-16">
                      Bcc:
                    </Label>
                    <Input
                      id="bcc"
                      type="email"
                      placeholder="bcc@example.com"
                      value={formData.bcc}
                      onChange={(e) => handleFormChange("bcc", e.target.value)}
                      className="flex-1"
                    />
                  </div>
                )}
              </div>

              <div className="flex items-center gap-2">
                <Label htmlFor="subject" className="w-16">
                  Subject:
                </Label>
                <Input
                  id="subject"
                  type="text"
                  placeholder="Email subject"
                  value={formData.subject}
                  onChange={(e) => handleFormChange("subject", e.target.value)}
                  className="flex-1"
                />
              </div>

              <div className="space-y-2">
                <Textarea
                  ref={bodyRef}
                  placeholder="Compose your email..."
                  value={formData.body}
                  onChange={(e) => handleFormChange("body", e.target.value)}
                  className="min-h-[400px] resize-none"
                />
              </div>

              <div className="flex items-center justify-between pt-4 border-t">
                <Button variant="ghost" size="sm" disabled>
                  <Paperclip className="h-4 w-4 mr-2" />
                  Attach files
                </Button>

                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                  {isSavingDraft ? (
                    <span className="flex items-center gap-2">
                      <span className="h-2 w-2 bg-blue-500 rounded-full animate-pulse" />
                      Saving...
                    </span>
                  ) : hasUnsavedChanges ? (
                    <span className="flex items-center gap-2">
                      <span className="h-2 w-2 bg-yellow-500 rounded-full" />
                      Draft
                    </span>
                  ) : lastSaved ? (
                    <span className="flex items-center gap-2">
                      <span className="h-2 w-2 bg-green-500 rounded-full" />
                      Saved
                    </span>
                  ) : null}
                  <span>Email sending functionality coming soon</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
