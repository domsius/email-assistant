import { useState } from "react";
import { Link, usePage, router, useForm } from "@inertiajs/react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { DocumentCard, DocumentListItem } from "@/components/ui/document-card";
import {
  SummaryStatsCard,
  StatsGroup,
} from "@/components/ui/summary-stats-card";
import { EmptyState } from "@/components/ui/empty-state";
import { StatusIndicator } from "@/components/ui/status-indicator";
import { FileTypeIcon } from "@/components/ui/file-type-icon";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head } from "@inertiajs/react";
import {
  FileText,
  Upload,
  Search,
  Trash2,
  Download,
  Eye,
  Clock,
  CheckCircle,
  AlertCircle,
  FileIcon,
  FileType,
  Database,
  Brain,
  Sparkles,
  RefreshCw,
} from "lucide-react";

interface Document {
  id: number;
  title: string;
  filename: string;
  type: "pdf" | "docx" | "txt" | "md";
  size: number;
  status: "pending" | "processing" | "processed" | "error";
  chunks: number;
  embeddings: number;
  uploadedAt: string;
  processedAt?: string;
  error?: string;
}

interface KnowledgeBaseProps {
  documents: Document[];
  stats: {
    totalDocuments: number;
    processedDocuments: number;
    totalChunks: number;
    totalEmbeddings: number;
    storageUsed: number;
    storageLimit: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
  },
  {
    title: "Knowledge Base",
    href: "/knowledge-base",
  },
];

export default function KnowledgeBase({
  documents = [],
  stats = {
    totalDocuments: 0,
    processedDocuments: 0,
    totalChunks: 0,
    totalEmbeddings: 0,
    storageUsed: 0,
    storageLimit: 1073741824, // 1GB default
  },
}: KnowledgeBaseProps) {
  const [searchQuery, setSearchQuery] = useState("");
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const form = useForm({
    title: "",
    file: null as File | null,
  });

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setSelectedFile(file);
      form.setData("file", file);
      if (!form.data.title) {
        form.setData("title", file.name.replace(/\.[^/.]+$/, ""));
      }
    }
  };

  const handleUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (form.data.file) {
      form.post("/knowledge-base/upload", {
        onSuccess: () => {
          setIsUploadOpen(false);
          form.reset();
          setSelectedFile(null);
        },
      });
    }
  };

  const handleDelete = (id: number) => {
    if (confirm("Are you sure you want to delete this document?")) {
      router.delete(`/knowledge-base/${id}`);
    }
  };

  const handleReprocess = (id: number) => {
    router.post(`/knowledge-base/${id}/reprocess`);
  };

  const filteredDocuments = documents.filter(
    (doc) =>
      doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      doc.filename.toLowerCase().includes(searchQuery.toLowerCase()),
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Knowledge Base" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 overflow-x-auto">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">
              Knowledge Base
            </h1>
            <p className="text-muted-foreground">
              Manage documents for AI-powered responses
            </p>
          </div>
          <Dialog open={isUploadOpen} onOpenChange={setIsUploadOpen}>
            <DialogTrigger asChild>
              <Button>
                <Upload className="h-4 w-4 mr-2" />
                Upload Document
              </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
              <form onSubmit={handleUpload}>
                <DialogHeader>
                  <DialogTitle>Upload Document</DialogTitle>
                  <DialogDescription>
                    Upload a document to enhance AI responses with your
                    knowledge
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <label htmlFor="title" className="text-sm font-medium">
                      Document Title
                    </label>
                    <Input
                      id="title"
                      placeholder="Enter document title"
                      value={form.data.title}
                      onChange={(e) => form.setData("title", e.target.value)}
                      required
                    />
                  </div>
                  <div className="grid gap-2">
                    <label htmlFor="file" className="text-sm font-medium">
                      File
                    </label>
                    <Input
                      id="file"
                      type="file"
                      accept=".pdf,.docx,.txt,.md"
                      onChange={handleFileChange}
                      required
                    />
                    {selectedFile && (
                      <p className="text-sm text-muted-foreground">
                        Selected: {selectedFile.name} (
                        {formatFileSize(selectedFile.size)})
                      </p>
                    )}
                  </div>
                </div>
                <DialogFooter>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsUploadOpen(false)}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing ? (
                      <>
                        <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                        Uploading...
                      </>
                    ) : (
                      <>
                        <Upload className="h-4 w-4 mr-2" />
                        Upload
                      </>
                    )}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {/* Stats Cards */}
        <StatsGroup columns={4}>
          <SummaryStatsCard
            title="Total Documents"
            value={stats.totalDocuments}
            description={`${stats.processedDocuments} processed`}
            icon={FileText}
          />
          <SummaryStatsCard
            title="Document Chunks"
            value={stats.totalChunks}
            description="Searchable segments"
            icon={Database}
          />
          <SummaryStatsCard
            title="Embeddings"
            value={stats.totalEmbeddings}
            description="Vector embeddings"
            icon={Brain}
          />
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">
                Storage Used
              </CardTitle>
              <Database className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {formatFileSize(stats.storageUsed)}
              </div>
              <Progress
                value={(stats.storageUsed / stats.storageLimit) * 100}
                className="mt-2"
              />
            </CardContent>
          </Card>
        </StatsGroup>

        {/* Document List */}
        <Card className="flex-1">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>Documents</CardTitle>
                <CardDescription>
                  Your uploaded documents for AI knowledge enhancement
                </CardDescription>
              </div>
              <div className="relative w-64">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search documents..."
                  className="pl-8"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {filteredDocuments.length === 0 ? (
              <EmptyState
                icon={<FileText className="h-10 w-10" />}
                title={
                  searchQuery ? "No documents found" : "No documents uploaded"
                }
                description={
                  searchQuery
                    ? "Try adjusting your search query"
                    : "Upload your first document to enhance AI responses"
                }
                action={
                  !searchQuery && (
                    <Button onClick={() => setIsUploadOpen(true)}>
                      <Upload className="h-4 w-4 mr-2" />
                      Upload Document
                    </Button>
                  )
                }
                className="py-12"
              />
            ) : (
              <div className="space-y-4">
                {filteredDocuments.map((doc) => (
                  <DocumentListItem
                    key={doc.id}
                    {...doc}
                    onView={(id) => router.get(`/knowledge-base/${id}`)}
                    onDownload={(id) =>
                      window.open(`/knowledge-base/${id}/download`, "_blank")
                    }
                    onDelete={handleDelete}
                    onReprocess={handleReprocess}
                  />
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
