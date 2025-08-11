export interface EmailMessage {
  id: number | string; // Allow string for draft IDs
  subject: string;
  sender: string;
  senderEmail: string;
  content: string;
  snippet: string;
  receivedAt: string;
  status: "pending" | "processing" | "processed";
  isRead: boolean;
  isStarred: boolean;
  isImportant?: boolean;
  isSelected: boolean;
  language?: string;
  topic?: string;
  sentiment?: "positive" | "negative" | "neutral";
  urgency?: "high" | "medium" | "low";
  emailAccountId: number;
  threadId?: string;
  labels?: string[];
  plainTextContent?: string;
  attachments?: Array<{
    id: string;
    filename: string;
    size: number;
    type: string;
  }>;
  aiAnalysis?: {
    summary: string;
    keyPoints: string[];
    suggestedResponse?: string;
    confidence: number;
  };
  // Draft-specific fields
  isDraft?: boolean;
  draftId?: number;
  action?: "new" | "reply" | "replyAll" | "forward";
  to?: string;
  from?: string;
  date?: string;
  recipients?: string;
  cc_recipients?: string;
  bcc_recipients?: string;
  // Additional fields returned by API for drafts
  cc?: string;
  bcc?: string;
  body_content?: string;
  originalEmail?: any; // Could be EmailMessage but allow any for flexibility
}

export interface EmailAccountAlias {
  id: number;
  email_address: string;
  name?: string;
  is_default: boolean;
  is_verified: boolean;
  reply_to_address?: string;
}

export interface EmailAccount {
  id: number;
  email: string;
  provider: string;
  isActive: boolean;
  company_id?: number;
  aliases?: EmailAccountAlias[];
}

export interface FolderCounts {
  inbox: number;
  drafts: number;
  sent: number;
  junk: number;
  trash: number;
  archive: number;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
}

export interface InboxProps {
  emails: EmailMessage[];
  emailAccounts: EmailAccount[];
  selectedAccount: number | null;
  folders: FolderCounts;
  currentFolder?: string;
  currentFilter?: string;
  searchQuery?: string;
  pagination?: {
    links: PaginationLinks;
    meta: PaginationMeta;
  };
  error?: string;
}
