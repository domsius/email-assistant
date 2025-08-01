import { useState, useEffect, useRef, useCallback } from "react";
import axios from "axios";
import { toast } from "sonner";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { authenticatedFetch, cn } from "@/lib/utils";
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
  Sparkles,
  Eye,
  Pencil,
  FileIcon,
  Trash2,
  Clock,
  CheckCircle,
  AlertCircle,
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

interface Attachment {
  id: string;
  filename: string;
  size: number;
  formattedSize: string;
  contentType: string;
  uploading?: boolean;
  error?: string;
}

export function ComposePanel({
  composeData,
  originalEmail,
  draftId: initialDraftId,
}: ComposePanelProps) {
  const { exitComposeMode, selectedAccount, emailAccounts, setJustSentEmail } = useInbox();
  const [showCc, setShowCc] = useState(!!composeData.cc);
  const [showBcc, setShowBcc] = useState(!!composeData.bcc);
  const [isSending, setIsSending] = useState(false);
  const [draftId, setDraftId] = useState<number | null>(initialDraftId || null);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [isSavingDraft, setIsSavingDraft] = useState(false);
  const [draftSaveStatus, setDraftSaveStatus] = useState<
    "saved" | "saving" | "error" | null
  >(null);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [isGeneratingAI, setIsGeneratingAI] = useState(false);
  const [showPreview, setShowPreview] = useState(false);
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);
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
  const handleFormChange = useCallback((field: string, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setHasUnsavedChanges(true);
    setDraftSaveStatus(null); // Clear status when user makes changes
  }, []);

  useEffect(() => {
    // Focus on appropriate field only on initial mount
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
  }, []); // Empty dependency array - only run on mount

  // Auto-save draft
  const saveDraft = useCallback(async () => {
    if (isSavingDraft) return;

    // Get the account to use for saving
    let accountToUse = selectedAccount;

    // If no account is selected, try to use the first available account
    if (!accountToUse && emailAccounts.length > 0) {
      accountToUse = emailAccounts[0].id;
      toast.info(
        `Using ${emailAccounts[0].email} for draft since no account was selected`,
      );
    }

    // If still no account available, show error
    if (!accountToUse) {
      console.error("No email account available for draft save");
      toast.error("No email account available to save draft");
      setDraftSaveStatus("error");
      return;
    }

    setIsSavingDraft(true);
    setDraftSaveStatus("saving");

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
        emailAccountId: accountToUse,
      });

      if (!draftId && response.data.id) {
        setDraftId(response.data.id);
      }
      setLastSaved(new Date());
      setHasUnsavedChanges(false);
      setDraftSaveStatus("saved");

      // Clear the saved status after 3 seconds
      setTimeout(() => {
        setDraftSaveStatus(null);
      }, 3000);
    } catch (error) {
      console.error("Failed to save draft:", error);
      setDraftSaveStatus("error");

      // Parse specific error messages from backend
      let errorMessage = "Failed to save draft";
      if (axios.isAxiosError(error)) {
        if (error.response?.data?.message) {
          errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
          const errors = error.response.data.errors;
          const errorMessages = Object.values(errors).flat();
          if (errorMessages.length > 0) {
            errorMessage = errorMessages[0] as string;
          }
        }
      }

      toast.error(errorMessage);
    } finally {
      setIsSavingDraft(false);
    }
  }, [
    formData,
    draftId,
    composeData,
    originalEmail,
    isSavingDraft,
    selectedAccount,
    emailAccounts,
  ]);

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

  const handleFormat = useCallback(
    (format: "bold" | "italic" | "underline") => {
      const textarea = bodyRef.current;
      if (!textarea) return;

      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const selectedText = formData.body.substring(start, end);

      if (selectedText) {
        let formattedText = "";
        switch (format) {
          case "bold":
            formattedText = `<strong>${selectedText}</strong>`;
            break;
          case "italic":
            formattedText = `<em>${selectedText}</em>`;
            break;
          case "underline":
            formattedText = `<u>${selectedText}</u>`;
            break;
        }

        const newBody =
          formData.body.substring(0, start) +
          formattedText +
          formData.body.substring(end);
        handleFormChange("body", newBody);

        // Restore cursor position after the formatted text
        setTimeout(() => {
          textarea.focus();
          const newCursorPos = start + formattedText.length;
          textarea.setSelectionRange(newCursorPos, newCursorPos);
        }, 0);
      } else {
        // If no text is selected, insert formatting tags at cursor position
        let tags = "";
        let tagLength = 0;
        switch (format) {
          case "bold":
            tags = "<strong></strong>";
            tagLength = 8; // length of <strong>
            break;
          case "italic":
            tags = "<em></em>";
            tagLength = 4; // length of <em>
            break;
          case "underline":
            tags = "<u></u>";
            tagLength = 3; // length of <u>
            break;
        }

        const newBody =
          formData.body.substring(0, start) +
          tags +
          formData.body.substring(start);
        handleFormChange("body", newBody);

        // Place cursor inside the tags
        setTimeout(() => {
          textarea.focus();
          const cursorPos = start + tagLength;
          textarea.setSelectionRange(cursorPos, cursorPos);
        }, 0);
      }
    },
    [formData.body, handleFormChange],
  );

  // Keyboard shortcuts for formatting
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.target === bodyRef.current) {
        switch (e.key.toLowerCase()) {
          case "b":
            e.preventDefault();
            handleFormat("bold");
            break;
          case "i":
            e.preventDefault();
            handleFormat("italic");
            break;
          case "u":
            e.preventDefault();
            handleFormat("underline");
            break;
        }
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [formData.body, handleFormat]);

  const handleSend = async () => {
    if (!formData.to && !formData.cc && !formData.bcc) {
      toast.error("Please add at least one recipient");
      return;
    }

    if (!formData.subject) {
      if (!confirm("Send email without a subject?")) {
        return;
      }
    }

    if (!selectedAccount) {
      toast.error("Please select an email account");
      return;
    }

    setIsSending(true);

    router.post(
      "/emails/send",
      {
        emailAccountId: selectedAccount,
        to: formData.to,
        cc: formData.cc || "",
        bcc: formData.bcc || "",
        subject: formData.subject || "(No Subject)",
        body: formData.body,
        draftId: draftId,
        inReplyTo: composeData.inReplyTo || null,
        references: composeData.references || null,
        attachmentIds: attachments.map((att) => att.id),
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          toast.success("Email sent successfully!");

          // Clear form and exit compose mode
          setFormData({
            to: "",
            cc: "",
            bcc: "",
            subject: "",
            body: "",
          });

          // Set flag that email was just sent
          setJustSentEmail(true);

          // Exit compose mode first
          exitComposeMode();

          // If we're not already in the sent folder, navigate to it
          const currentUrl = new URL(window.location.href);
          const currentFolder = currentUrl.searchParams.get("folder") || "inbox";
          
          if (currentFolder !== "sent") {
            // Navigate to sent folder after a short delay to allow the email to be processed
            setTimeout(() => {
              router.visit("/inbox?folder=sent", {
                preserveState: false,
                preserveScroll: true,
                only: ["emails", "folders", "pagination"],
              });
            }, 500); // Reduced to 0.5 seconds
          } else {
            // If already in sent folder, refresh after a delay
            setTimeout(() => {
              router.reload({
                only: ["emails", "folders", "pagination"],
                preserveScroll: true,
              });
            }, 1000); // Reduced to 1 second
          }
        },
        onError: (errors) => {
          // Handle validation errors
          if (errors.to) {
            toast.error(errors.to);
          } else if (errors.body) {
            toast.error(errors.body);
          } else if (errors.emailAccountId) {
            toast.error(errors.emailAccountId);
          } else {
            // General error message
            const errorMessage =
              typeof errors === "object"
                ? Object.values(errors).flat().join(" ")
                : "Failed to send email";
            toast.error(errorMessage);
          }
        },
        onFinish: () => {
          setIsSending(false);
        },
      },
    );
  };

  const handleCancel = () => {
    // Always exit compose mode when cancel is clicked
    exitComposeMode();
  };

  const handleGenerateAI = async () => {
    if (!originalEmail) {
      toast.error("No email to reply to");
      return;
    }

    setIsGeneratingAI(true);

    try {
      // Use authenticated fetch helper for API calls
      const response = await authenticatedFetch(
        `/api/emails/${originalEmail.id}/generate-response`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({
            // Send optional parameters that the API accepts
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
      if (data?.data?.draft?.ai_generated_content) {
        handleFormChange("body", data.data.draft.ai_generated_content);
        toast.success("AI response generated successfully");
      } else if (data?.response) {
        handleFormChange("body", data.response);
        toast.success("AI response generated successfully");
      } else if (data?.ai_generated_content) {
        handleFormChange("body", data.ai_generated_content);
        toast.success("AI response generated successfully");
      } else {
        console.error("Unexpected response format:", data);
        toast.error("Could not generate response - unexpected format");
      }
    } catch (error) {
      console.error("Failed to generate AI response:", error);
      toast.error("Failed to generate AI response");
    } finally {
      setIsGeneratingAI(false);
    }
  };

  // Handle file upload
  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    if (files.length === 0) return;

    for (const file of files) {
      const tempId = `temp_${Date.now()}_${Math.random()}`;

      // Add to attachments with uploading state
      setAttachments((prev) => [
        ...prev,
        {
          id: tempId,
          filename: file.name,
          size: file.size,
          formattedSize: formatFileSize(file.size),
          contentType: file.type,
          uploading: true,
        },
      ]);

      try {
        const formData = new FormData();
        formData.append("file", file);
        formData.append("email_account_id", selectedAccount?.toString() || "");

        const response = await authenticatedFetch("/api/attachments/upload", {
          method: "POST",
          body: formData,
        });

        if (!response.ok) {
          throw new Error("Upload failed");
        }

        const data = await response.json();

        // Update attachment with server response
        setAttachments((prev) =>
          prev.map((att) =>
            att.id === tempId ? { ...data.attachment, uploading: false } : att,
          ),
        );
      } catch (error) {
        console.error("File upload failed:", error);
        setAttachments((prev) =>
          prev.map((att) =>
            att.id === tempId
              ? { ...att, uploading: false, error: "Upload failed" }
              : att,
          ),
        );
        toast.error(`Failed to upload ${file.name}`);
      }
    }

    // Reset file input
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const handleRemoveAttachment = async (attachmentId: string) => {
    try {
      await authenticatedFetch(`/api/attachments/${attachmentId}`, {
        method: "DELETE",
      });

      setAttachments((prev) => prev.filter((att) => att.id !== attachmentId));
    } catch (error) {
      console.error("Failed to remove attachment:", error);
      toast.error("Failed to remove attachment");
    }
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
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

  // Helper to render draft save status
  const renderDraftStatus = () => {
    if (draftSaveStatus === "saving") {
      return (
        <div className="flex items-center gap-1 text-sm text-muted-foreground">
          <Clock className="h-3 w-3 animate-pulse" />
          <span>Saving...</span>
        </div>
      );
    }

    if (draftSaveStatus === "saved") {
      return (
        <div className="flex items-center gap-1 text-sm text-green-600">
          <CheckCircle className="h-3 w-3" />
          <span>Draft saved</span>
        </div>
      );
    }

    if (draftSaveStatus === "error") {
      return (
        <div className="flex items-center gap-1 text-sm text-red-600">
          <AlertCircle className="h-3 w-3" />
          <span>Save failed</span>
        </div>
      );
    }

    // Show last saved time if available and no current status
    if (lastSaved && !draftSaveStatus) {
      return (
        <span className="text-sm text-muted-foreground">
          Draft saved {lastSaved.toLocaleTimeString()}
        </span>
      );
    }

    return null;
  };

  return (
    <div className="flex flex-col h-full">
      <Card className="flex-1 flex flex-col">
        <CardHeader className="border-b px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <h2 className="text-lg font-semibold">{getActionLabel()}</h2>
              {renderDraftStatus()}
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
                <Label
                  htmlFor="compose-cc"
                  className="text-sm font-medium w-20"
                >
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
                <Label
                  htmlFor="compose-bcc"
                  className="text-sm font-medium w-20"
                >
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
              <Label
                htmlFor="compose-subject"
                className="text-sm font-medium w-20"
              >
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
            {showPreview ? (
              <div className="min-h-[300px] h-full p-0">
                <div className="mb-2 text-sm text-muted-foreground">
                  Preview Mode
                </div>
                <div
                  className="prose prose-sm max-w-none"
                  dangerouslySetInnerHTML={{ __html: formData.body }}
                />
              </div>
            ) : (
              <Textarea
                ref={bodyRef}
                placeholder="Write your message..."
                value={formData.body}
                onChange={(e) => handleFormChange("body", e.target.value)}
                className="min-h-[300px] h-full resize-none border-0 bg-transparent placeholder:text-muted-foreground focus-visible:ring-0 p-0"
              />
            )}
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

          {/* Attachments */}
          {attachments.length > 0 && (
            <div className="border-t px-6 py-4">
              <div className="space-y-2">
                <h4 className="text-sm font-medium">Attachments</h4>
                <div className="flex flex-wrap gap-2">
                  {attachments.map((attachment) => (
                    <div
                      key={attachment.id}
                      className={cn(
                        "flex items-center gap-2 px-3 py-2 rounded-md border bg-muted/50",
                        attachment.error && "border-red-500 bg-red-50",
                        attachment.uploading && "opacity-60",
                      )}
                    >
                      <FileIcon className="h-4 w-4 text-muted-foreground" />
                      <div className="flex flex-col">
                        <span className="text-sm font-medium">
                          {attachment.filename}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {attachment.formattedSize}
                          {attachment.uploading && " • Uploading..."}
                          {attachment.error && " • Failed"}
                        </span>
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0 ml-2"
                        onClick={() => handleRemoveAttachment(attachment.id)}
                        disabled={attachment.uploading}
                      >
                        <Trash2 className="h-3 w-3" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Toolbar */}
          <div className="border-t">
            <div className="flex items-center justify-between px-6 py-3">
              <div className="flex items-center gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-8 w-8 p-0"
                  title="Attach file"
                  onClick={() => fileInputRef.current?.click()}
                  type="button"
                >
                  <Paperclip className="h-4 w-4" />
                </Button>
                <input
                  ref={fileInputRef}
                  type="file"
                  multiple
                  className="hidden"
                  onChange={handleFileSelect}
                  accept="*/*"
                />
                <Separator orientation="vertical" className="mx-1 h-6" />
                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    title="Bold (Ctrl+B)"
                    onClick={() => handleFormat("bold")}
                    type="button"
                  >
                    <Bold className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    title="Italic (Ctrl+I)"
                    onClick={() => handleFormat("italic")}
                    type="button"
                  >
                    <Italic className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    title="Underline (Ctrl+U)"
                    onClick={() => handleFormat("underline")}
                    type="button"
                  >
                    <Underline className="h-4 w-4" />
                  </Button>
                </div>
                <Separator orientation="vertical" className="mx-1 h-6" />
                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    title="Insert link"
                    type="button"
                  >
                    <Link className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    title="Insert image"
                    type="button"
                  >
                    <Image className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0"
                    title="Insert emoji"
                    type="button"
                  >
                    <Smile className="h-4 w-4" />
                  </Button>
                </div>
                <Separator orientation="vertical" className="mx-1 h-6" />
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-8 px-2 gap-1"
                  title="Toggle preview"
                  onClick={() => setShowPreview(!showPreview)}
                  type="button"
                >
                  {showPreview ? (
                    <>
                      <Pencil className="h-4 w-4" />
                      <span className="text-xs">Edit</span>
                    </>
                  ) : (
                    <>
                      <Eye className="h-4 w-4" />
                      <span className="text-xs">Preview</span>
                    </>
                  )}
                </Button>
                {originalEmail &&
                  (composeData.action === "reply" ||
                    composeData.action === "replyAll") && (
                    <>
                      <Separator orientation="vertical" className="mx-1 h-6" />
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 px-3 gap-2"
                        title="Generate response with AI"
                        onClick={handleGenerateAI}
                        disabled={isGeneratingAI}
                      >
                        <Sparkles
                          className={`h-4 w-4 ${isGeneratingAI ? "animate-pulse" : ""}`}
                        />
                        <span className="text-xs">Generate with AI</span>
                      </Button>
                    </>
                  )}
                <Separator orientation="vertical" className="mx-1 h-6" />
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-8 w-8 p-0"
                  title="More options"
                >
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
