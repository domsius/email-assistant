import React, { useState, useCallback, useEffect } from "react";
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
import { usePage } from "@inertiajs/react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { EmailEditor } from "@/components/ui/email-editor";
import { SignatureService } from "@/services/SignatureService";
import { EmailContentIframe } from "@/components/inbox/email-content-iframe";


interface EmailDetailViewProps {
  email: EmailMessage;
  onBackToList: () => void;
}

interface ReplyState {
  isReplying: boolean;
  replyType: "reply" | "replyAll" | "forward" | "draft" | null;
  to: string;
  cc: string;
  subject: string;
  body: string;
}

export function EmailDetailView({ email, onBackToList }: EmailDetailViewProps) {
  const { handleToggleStar, enterComposeMode } = useInbox();
  const { props } = usePage<any>();
  const emailAccounts = props.emailAccounts || [];
  
  const [replyState, setReplyState] = useState<ReplyState>({
    isReplying: false,
    replyType: null,
    to: "",
    cc: "",
    subject: "",
    body: ""
  });
  const [isGeneratingAI, setIsGeneratingAI] = useState(false);
  const [replySignature, setReplySignature] = useState<string>("");
  const [preservedSignature, setPreservedSignature] = useState<string>("");
  
  // If this is a draft, set up reply state for editing
  useEffect(() => {
    if (email.isDraft) {
      // Set up the reply state with draft data
      setReplyState({
        isReplying: true,
        replyType: "draft" as any, // Using "draft" as a special type
        to: email.to || email.recipients || "",
        cc: email.cc || email.cc_recipients || "",
        subject: email.subject || "",
        body: email.body_content || email.content || email.plainTextContent || ""
      });
    }
  }, [email.isDraft]);
  
  // Fetch signature when starting a reply (but not for drafts which already have signatures)
  useEffect(() => {
    // Skip signature loading for drafts - they already have their signature
    if (replyState.isReplying && replyState.replyType !== "forward" && replyState.replyType !== "draft" && emailAccounts.length > 0) {
      // Get the email account that received this email
      // First, try to find by email_account_id if available
      let recipientAccount = null;
      let fromAddress = "";
      
      // Check if email has an emailAccountId property
      if (email.emailAccountId) {
        recipientAccount = emailAccounts.find((acc: any) => 
          acc.id === email.emailAccountId || acc.id === email.emailAccountId.toString()
        );
        if (recipientAccount) {
          fromAddress = recipientAccount.email;
          
          // Check if the email was sent to an alias
          if (recipientAccount.aliases && recipientAccount.aliases.length > 0 && email.to) {
            const emailTo = email.to.toLowerCase();
            const matchedAlias = recipientAccount.aliases.find((alias: any) =>
              emailTo.includes(alias.email_address.toLowerCase())
            );
            if (matchedAlias) {
              fromAddress = matchedAlias.email_address;
            }
          }
        }
      }
      
      // If not found, try to match by recipient email addresses
      if (!recipientAccount) {
        const emailTo = email.to?.toLowerCase() || "";
        
        for (const acc of emailAccounts) {
          // Check main email
          if (emailTo.includes(acc.email.toLowerCase())) {
            recipientAccount = acc;
            fromAddress = acc.email;
            break;
          }
          
          // Check aliases
          if (acc.aliases && acc.aliases.length > 0) {
            const matchedAlias = acc.aliases.find((alias: any) =>
              emailTo.includes(alias.email_address.toLowerCase())
            );
            if (matchedAlias) {
              recipientAccount = acc;
              fromAddress = matchedAlias.email_address;
              break;
            }
          }
        }
      }
      
      if (recipientAccount && fromAddress) {
        // Fetch signature using secure service
        SignatureService.fetchSignature(recipientAccount.id, fromAddress)
          .then(sanitizedSignature => {
            if (sanitizedSignature && !replyState.body.includes(sanitizedSignature)) {
              console.log('Loaded sanitized reply signature:', sanitizedSignature.substring(0, 100) + '...');
              
              setReplySignature(sanitizedSignature);
              setPreservedSignature(sanitizedSignature); // Store the signature separately
              
              // Insert signature before the citation/quoted text
              let newBody = replyState.body;
              if (replyState.body.includes('<div style="border-left:')) {
                // Insert signature before the citation
                newBody = sanitizedSignature + replyState.body;
              } else if (replyState.body.includes('---------- Forwarded message')) {
                // Insert signature before the forwarded message
                newBody = sanitizedSignature + replyState.body;
              } else {
                // Normal signature insertion
                newBody = SignatureService.insertSignature(replyState.body, sanitizedSignature);
              }
              
              setReplyState(prev => ({
                ...prev,
                body: newBody
              }));
            }
          })
          .catch(error => {
            console.error('Failed to fetch reply signature:', error);
            toast.error('Unable to load email signature for reply');
            
            // Use fallback signature
            const fallbackSignature = SignatureService.getDefaultSignature();
            setReplySignature(fallbackSignature);
          });
      }
    }
  }, [replyState.isReplying, replyState.replyType, emailAccounts]);

  const handleReply = useCallback((type: "reply" | "replyAll" | "forward") => {
    let defaultTo = "";
    let defaultSubject = "";
    let defaultBody = "";
    
    // Format the original email as a citation
    const formatDate = (date: string) => {
      return format(new Date(date), "EEEE, MMMM d, yyyy 'at' h:mm a");
    };
    
    const originalEmailCitation = `<br><br><div style="border-left: 2px solid #ccc; padding-left: 10px; margin-left: 10px; color: #666;">
On ${formatDate(email.receivedAt || email.date || new Date().toISOString())}, ${email.sender} &lt;${email.senderEmail}&gt; wrote:<br><br>
${email.content || email.plainTextContent || email.snippet || ""}
</div>`;
    
    switch (type) {
      case "reply":
        defaultTo = email.senderEmail;
        defaultSubject = email.subject.startsWith("Re: ") ? email.subject : `Re: ${email.subject}`;
        defaultBody = originalEmailCitation;
        break;
      case "replyAll":
        defaultTo = email.senderEmail;
        defaultSubject = email.subject.startsWith("Re: ") ? email.subject : `Re: ${email.subject}`;
        defaultBody = originalEmailCitation;
        break;
      case "forward":
        defaultTo = "";
        defaultSubject = email.subject.startsWith("Fwd: ") ? email.subject : `Fwd: ${email.subject}`;
        // For forward, include the original email details in the body
        defaultBody = `<br><br>---------- Forwarded message ----------<br>
From: ${email.sender} &lt;${email.senderEmail}&gt;<br>
Date: ${formatDate(email.receivedAt || email.date || new Date().toISOString())}<br>
Subject: ${email.subject}<br>
To: ${email.to || ""}<br><br>
${email.content || email.plainTextContent || email.snippet || ""}`;
        break;
    }
    
    setReplyState({
      isReplying: true,
      replyType: type,
      to: defaultTo,
      cc: "",
      subject: defaultSubject,
      body: defaultBody
    });
  }, [email]);

  // Auto-save draft function
  const saveDraft = useCallback(async () => {
    if (!replyState.body.trim() && !replyState.subject.trim()) {
      return;
    }

    try {
      // Get the account that received this email
      let emailAccountId: number | null = null;
      
      if (email.emailAccountId) {
        const recipientAccount = emailAccounts.find((acc: any) => 
          acc.id === email.emailAccountId || acc.id === email.emailAccountId.toString()
        );
        if (recipientAccount) {
          emailAccountId = recipientAccount.id;
        }
      }
      
      if (!emailAccountId && emailAccounts.length > 0) {
        emailAccountId = emailAccounts[0].id;
      }
      
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
          emailAccountId: emailAccountId,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to save draft');
      }
    } catch (error) {
      console.error('Failed to save draft:', error);
    }
  }, [replyState, email.id, emailAccounts]);

  // Auto-save draft when reply content changes
  useEffect(() => {
    if (!replyState.isReplying) return;
    
    const timeoutId = setTimeout(() => {
      saveDraft();
    }, 2000); // Save after 2 seconds of inactivity
    
    return () => clearTimeout(timeoutId);
  }, [replyState.body, replyState.subject, replyState.to, replyState.cc, replyState.isReplying, saveDraft]);

  const handleCancelReply = useCallback(() => {
    // Just reset reply state without saving
    setReplyState({
      isReplying: false,
      replyType: null,
      to: "",
      cc: "",
      subject: "",
      body: ""
    });
    setReplySignature("");
    setPreservedSignature("");
  }, []);

  const handleSendReply = useCallback(() => {
    // Use the existing compose functionality
    const composeData: any = {
      to: replyState.to,
      cc: replyState.cc,
      subject: replyState.subject,
      body: replyState.body,
      action: replyState.replyType === 'draft' ? 'draft' : (replyState.replyType || "reply"),
    };

    // For drafts, include the draft ID and original email if exists
    if (email.isDraft) {
      composeData.draftId = email.draftId || email.id;
      if (email.originalEmail) {
        composeData.originalEmail = email.originalEmail;
        composeData.inReplyTo = email.originalEmail.id?.toString();
      }
      // Find the from address for the draft
      let defaultFrom = "";
      if (email.emailAccountId) {
        const account = emailAccounts.find((acc: any) => 
          acc.id === email.emailAccountId || acc.id === email.emailAccountId.toString()
        );
        if (account) {
          defaultFrom = account.email;
        }
      }
      composeData.defaultFrom = defaultFrom || email.from;
    } else {
      // For regular replies
      composeData.inReplyTo = email.id.toString();
      composeData.originalEmail = email;
    }

    enterComposeMode(composeData);
    
    // Reset reply state
    handleCancelReply();
  }, [replyState, email, enterComposeMode, handleCancelReply, emailAccounts]);

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
        // Preserve existing signature and citation
        const currentBody = replyState.body || "";
        
        // Look for existing signature - check multiple patterns
        const signaturePatterns = [
          /<div[^>]*class="[^"]*email-signature[^"]*"[^>]*>[\s\S]*$/i,
          // Also look for the signature if it's stored without the class
          // Check for common signature indicators
          /(<br><br>)?(<div[^>]*>)?-{2,}[^-][\s\S]*?(Wyrd[iI]t|<img[^>]*wyrdit[^>]*>)[\s\S]*$/i,
          // Check for signature with phone/email pattern
          /(<br><br>)?[\s\S]*?(\+370[\s\S]*?@[\s\S]*?\.(com|lt|eu|org))[\s\S]*$/i
        ];
        
        let signatureMatch = null;
        for (const pattern of signaturePatterns) {
          signatureMatch = currentBody.match(pattern);
          if (signatureMatch) break;
        }
        
        // Look for existing citation (reply quote)
        const citationPattern = /<div style="border-left:[^"]*"[^>]*>[\s\S]*$/i;
        const citationMatch = currentBody.match(citationPattern);
        
        // Look for forwarded message
        const forwardPattern = /---------- Forwarded message ----------[\s\S]*$/i;
        const forwardMatch = currentBody.match(forwardPattern);
        
        let finalBody = aiContent;
        
        // Remove any existing signature from AI content (shouldn't have one, but just in case)
        for (const pattern of signaturePatterns) {
          finalBody = finalBody.replace(pattern, '');
        }
        
        // Build the final body: AI content + signature + citation/forward
        // Use the preserved signature if we couldn't find it in the body
        const signatureToUse = signatureMatch ? signatureMatch[0] : preservedSignature;
        
        if (signatureToUse && (citationMatch || forwardMatch)) {
          // We have both signature and citation/forward - arrange them properly
          console.log("Preserving signature and citation/forward");
          
          // Add signature after AI content
          finalBody = finalBody + '<br><br>' + signatureToUse;
          
          // Add citation/forward after signature
          if (citationMatch) {
            finalBody = finalBody + citationMatch[0];
          } else if (forwardMatch) {
            finalBody = finalBody + '<br><br>' + forwardMatch[0];
          }
        } else if (signatureToUse) {
          // Only signature, no citation
          console.log("Preserving signature only");
          finalBody = finalBody + '<br><br>' + signatureToUse;
        } else if (citationMatch || forwardMatch) {
          // Only citation/forward, no signature (shouldn't happen normally)
          console.log("Preserving citation/forward only");
          if (citationMatch) {
            finalBody = finalBody + citationMatch[0];
          } else if (forwardMatch) {
            finalBody = finalBody + '<br><br>' + forwardMatch[0];
          }
        }
        
        setReplyState(prev => ({ ...prev, body: finalBody }));
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

  // For drafts, we'll show a modified view similar to reply mode

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
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      const downloadUrl = `/emails/${email.id}/attachments/${attachment.id}/download`;
                      console.log('Downloading attachment:', downloadUrl);
                      window.location.href = downloadUrl;
                    }}
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

          {/* Email Body - Don't show for drafts since they're being edited */}
          {!email.isDraft && (
            <div className="mb-8">
              <EmailContentIframe 
                content={email.content || email.plainTextContent || email.snippet || "No content available"}
                className="email-content-frame"
              />
            </div>
          )}

          {/* Action Buttons - Don't show for drafts */}
          {!replyState.isReplying && !email.isDraft && (
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
                  {replyState.replyType === 'draft' ? 'Edit Draft' : `${replyState.replyType} to ${email.sender}`}
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

              {/* Reply Body */}
              <div className="mb-4">
                <EmailEditor
                  content={replyState.body}
                  onChange={(html) => setReplyState(prev => ({ ...prev, body: html }))}
                  placeholder="Type your message..."
                  minHeight="200px"
                />
              </div>

              {/* Reply Actions */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  {/* Generate with AI button */}
                  {(
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
                  )}
                  <Button onClick={handleSendReply} className="gap-2">
                    <Send className="h-4 w-4" />
                    Send
                  </Button>
                  <Button variant="outline" onClick={handleCancelReply}>
                    Cancel
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