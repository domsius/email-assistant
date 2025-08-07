import { useState } from "react";
import { Head } from "@inertiajs/react";
import { router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Plus, Edit, Trash2, Settings, Sparkles, BookOpenText, HelpCircle, ShoppingCart, CreditCard } from "lucide-react";
import { toast } from "sonner";

interface GlobalPrompt {
  id: number;
  name: string;
  prompt_content: string;
  description?: string;
  prompt_type: string;
  is_active: boolean;
  settings?: {
    temperature?: number;
    max_tokens?: number;
    additional_instructions?: string;
  };
  creator?: {
    name: string;
  };
  updater?: {
    name: string;
  };
  created_at: string;
  updated_at: string;
}

interface Props {
  prompts: GlobalPrompt[];
  promptTypes: Record<string, string>;
}

export default function GlobalPrompts({ prompts, promptTypes }: Props) {
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingPrompt, setEditingPrompt] = useState<GlobalPrompt | null>(null);
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null);
  
  const [formData, setFormData] = useState({
    name: "",
    prompt_content: "",
    description: "",
    prompt_type: "general",
    is_active: false,
    settings: {
      temperature: 0.7,
      max_tokens: 1000,
      additional_instructions: "",
    },
  });

  const getPromptIcon = (type: string) => {
    switch (type) {
      case "rag_enhanced":
        return <BookOpenText className="h-4 w-4" />;
      case "support":
        return <HelpCircle className="h-4 w-4" />;
      case "sales":
        return <ShoppingCart className="h-4 w-4" />;
      case "billing":
        return <CreditCard className="h-4 w-4" />;
      default:
        return <Sparkles className="h-4 w-4" />;
    }
  };

  const handleCreate = () => {
    router.post("/admin/global-prompts", formData, {
      onSuccess: () => {
        toast.success("Global AI prompt created successfully");
        setIsCreateOpen(false);
        resetForm();
      },
      onError: () => {
        toast.error("Failed to create prompt");
      },
    });
  };

  const handleUpdate = () => {
    if (!editingPrompt) return;
    
    router.put(`/admin/global-prompts/${editingPrompt.id}`, formData, {
      onSuccess: () => {
        toast.success("Global AI prompt updated successfully");
        setEditingPrompt(null);
        resetForm();
      },
      onError: () => {
        toast.error("Failed to update prompt");
      },
    });
  };

  const handleDelete = (id: number) => {
    router.delete(`/admin/global-prompts/${id}`, {
      onSuccess: () => {
        toast.success("Global AI prompt deleted successfully");
        setDeleteConfirmId(null);
      },
      onError: () => {
        toast.error("Failed to delete prompt");
      },
    });
  };

  const handleToggleActive = (prompt: GlobalPrompt) => {
    router.post(`/admin/global-prompts/${prompt.id}/toggle-active`, {}, {
      onSuccess: () => {
        toast.success(prompt.is_active ? "Prompt deactivated" : "Prompt activated");
      },
      onError: () => {
        toast.error("Failed to toggle prompt status");
      },
    });
  };

  const resetForm = () => {
    setFormData({
      name: "",
      prompt_content: "",
      description: "",
      prompt_type: "general",
      is_active: false,
      settings: {
        temperature: 0.7,
        max_tokens: 1000,
        additional_instructions: "",
      },
    });
  };

  const openEditDialog = (prompt: GlobalPrompt) => {
    setEditingPrompt(prompt);
    setFormData({
      name: prompt.name,
      prompt_content: prompt.prompt_content,
      description: prompt.description || "",
      prompt_type: prompt.prompt_type,
      is_active: prompt.is_active,
      settings: {
        temperature: prompt.settings?.temperature || 0.7,
        max_tokens: prompt.settings?.max_tokens || 1000,
        additional_instructions: prompt.settings?.additional_instructions || "",
      },
    });
  };

  const groupedPrompts = prompts.reduce((acc, prompt) => {
    if (!acc[prompt.prompt_type]) {
      acc[prompt.prompt_type] = [];
    }
    acc[prompt.prompt_type].push(prompt);
    return acc;
  }, {} as Record<string, GlobalPrompt[]>);

  return (
    <AppLayout>
      <Head title="Global AI Prompts - Admin" />
      
      <div className="container mx-auto py-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Global AI Prompts</h1>
            <p className="text-muted-foreground mt-1">
              Configure company-wide AI response prompts that work alongside RAG
            </p>
          </div>
          
          <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
            <DialogTrigger asChild>
              <Button onClick={() => setIsCreateOpen(true)}>
                <Plus className="mr-2 h-4 w-4" />
                New Prompt
              </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Create Global AI Prompt</DialogTitle>
                <DialogDescription>
                  Create a new global prompt that will be applied to all AI responses
                </DialogDescription>
              </DialogHeader>
              
              <div className="space-y-4">
                <div className="grid gap-2">
                  <Label htmlFor="name">Prompt Name</Label>
                  <Input
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="e.g., Customer Support Tone"
                  />
                </div>
                
                <div className="grid gap-2">
                  <Label htmlFor="type">Prompt Type</Label>
                  <Select
                    value={formData.prompt_type}
                    onValueChange={(value) => setFormData({ ...formData, prompt_type: value })}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(promptTypes).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          <div className="flex items-center gap-2">
                            {getPromptIcon(value)}
                            {label}
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                
                <div className="grid gap-2">
                  <Label htmlFor="content">Prompt Content</Label>
                  <Textarea
                    id="content"
                    rows={10}
                    value={formData.prompt_content}
                    onChange={(e) => setFormData({ ...formData, prompt_content: e.target.value })}
                    placeholder="Enter your global AI instructions here..."
                    className="font-mono text-sm"
                  />
                  <p className="text-xs text-muted-foreground">
                    This prompt will be prepended to all AI responses. Use clear, specific instructions.
                  </p>
                </div>
                
                <div className="grid gap-2">
                  <Label htmlFor="description">Description (Optional)</Label>
                  <Textarea
                    id="description"
                    rows={3}
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    placeholder="Describe when and how this prompt should be used..."
                  />
                </div>
                
                <div className="space-y-4 border-t pt-4">
                  <h4 className="text-sm font-medium">Advanced Settings</h4>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="temperature">Temperature: {formData.settings.temperature}</Label>
                      <input
                        type="range"
                        id="temperature"
                        min="0"
                        max="2"
                        step="0.1"
                        value={formData.settings.temperature}
                        onChange={(e) => setFormData({
                          ...formData,
                          settings: { ...formData.settings, temperature: parseFloat(e.target.value) }
                        })}
                        className="w-full"
                      />
                      <p className="text-xs text-muted-foreground">
                        Controls randomness: 0 = focused, 2 = creative
                      </p>
                    </div>
                    
                    <div className="grid gap-2">
                      <Label htmlFor="max_tokens">Max Tokens</Label>
                      <Input
                        id="max_tokens"
                        type="number"
                        min="1"
                        max="4000"
                        value={formData.settings.max_tokens}
                        onChange={(e) => setFormData({
                          ...formData,
                          settings: { ...formData.settings, max_tokens: parseInt(e.target.value) }
                        })}
                      />
                      <p className="text-xs text-muted-foreground">
                        Maximum response length
                      </p>
                    </div>
                  </div>
                  
                  <div className="grid gap-2">
                    <Label htmlFor="additional">Additional Instructions (Optional)</Label>
                    <Textarea
                      id="additional"
                      rows={3}
                      value={formData.settings.additional_instructions}
                      onChange={(e) => setFormData({
                        ...formData,
                        settings: { ...formData.settings, additional_instructions: e.target.value }
                      })}
                      placeholder="Any additional instructions to append..."
                    />
                  </div>
                </div>
                
                <div className="flex items-center space-x-2">
                  <Switch
                    id="active"
                    checked={formData.is_active}
                    onCheckedChange={(checked) => setFormData({ ...formData, is_active: checked })}
                  />
                  <Label htmlFor="active">Activate immediately</Label>
                </div>
              </div>
              
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsCreateOpen(false)}>
                  Cancel
                </Button>
                <Button onClick={handleCreate}>
                  Create Prompt
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
        
        <Tabs defaultValue="all" className="w-full">
          <TabsList>
            <TabsTrigger value="all">All Prompts</TabsTrigger>
            {Object.entries(promptTypes).map(([value, label]) => (
              <TabsTrigger key={value} value={value}>
                <div className="flex items-center gap-2">
                  {getPromptIcon(value)}
                  {label}
                </div>
              </TabsTrigger>
            ))}
          </TabsList>
          
          <TabsContent value="all" className="space-y-4">
            {prompts.length === 0 ? (
              <Card>
                <CardContent className="py-8 text-center">
                  <p className="text-muted-foreground">No global prompts configured yet.</p>
                  <p className="text-sm text-muted-foreground mt-2">
                    Create your first prompt to customize AI responses across your organization.
                  </p>
                </CardContent>
              </Card>
            ) : (
              prompts.map((prompt) => (
                <Card key={prompt.id}>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        {getPromptIcon(prompt.prompt_type)}
                        <div>
                          <CardTitle className="text-lg">{prompt.name}</CardTitle>
                          <CardDescription className="mt-1">
                            {prompt.description || "No description provided"}
                          </CardDescription>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant={prompt.is_active ? "default" : "secondary"}>
                          {prompt.is_active ? "Active" : "Inactive"}
                        </Badge>
                        <Badge variant="outline">{promptTypes[prompt.prompt_type]}</Badge>
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-4">
                      <div className="bg-muted/50 p-4 rounded-lg">
                        <pre className="text-sm whitespace-pre-wrap font-mono">
                          {prompt.prompt_content.slice(0, 200)}
                          {prompt.prompt_content.length > 200 && "..."}
                        </pre>
                      </div>
                      
                      {prompt.settings && (
                        <div className="flex gap-4 text-sm text-muted-foreground">
                          <span>Temperature: {prompt.settings.temperature || 0.7}</span>
                          <span>Max Tokens: {prompt.settings.max_tokens || 1000}</span>
                        </div>
                      )}
                      
                      <div className="flex items-center justify-between">
                        <div className="text-xs text-muted-foreground">
                          Created by {prompt.creator?.name} • 
                          {prompt.updater && ` Updated by ${prompt.updater.name} • `}
                          Last modified {new Date(prompt.updated_at).toLocaleDateString()}
                        </div>
                        
                        <div className="flex gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleToggleActive(prompt)}
                          >
                            <Settings className="mr-1 h-3 w-3" />
                            {prompt.is_active ? "Deactivate" : "Activate"}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => openEditDialog(prompt)}
                          >
                            <Edit className="mr-1 h-3 w-3" />
                            Edit
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setDeleteConfirmId(prompt.id)}
                          >
                            <Trash2 className="mr-1 h-3 w-3" />
                            Delete
                          </Button>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))
            )}
          </TabsContent>
          
          {Object.entries(promptTypes).map(([type, label]) => (
            <TabsContent key={type} value={type} className="space-y-4">
              {groupedPrompts[type]?.length > 0 ? (
                groupedPrompts[type].map((prompt) => (
                  <Card key={prompt.id}>
                    <CardHeader>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          {getPromptIcon(prompt.prompt_type)}
                          <div>
                            <CardTitle className="text-lg">{prompt.name}</CardTitle>
                            <CardDescription className="mt-1">
                              {prompt.description || "No description provided"}
                            </CardDescription>
                          </div>
                        </div>
                        <Badge variant={prompt.is_active ? "default" : "secondary"}>
                          {prompt.is_active ? "Active" : "Inactive"}
                        </Badge>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div className="space-y-4">
                        <div className="bg-muted/50 p-4 rounded-lg">
                          <pre className="text-sm whitespace-pre-wrap font-mono">
                            {prompt.prompt_content}
                          </pre>
                        </div>
                        
                        <div className="flex items-center justify-between">
                          <div className="text-xs text-muted-foreground">
                            Created by {prompt.creator?.name}
                          </div>
                          
                          <div className="flex gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleToggleActive(prompt)}
                            >
                              {prompt.is_active ? "Deactivate" : "Activate"}
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openEditDialog(prompt)}
                            >
                              Edit
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => setDeleteConfirmId(prompt.id)}
                            >
                              Delete
                            </Button>
                          </div>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))
              ) : (
                <Card>
                  <CardContent className="py-8 text-center">
                    <p className="text-muted-foreground">No {label.toLowerCase()} prompts configured.</p>
                  </CardContent>
                </Card>
              )}
            </TabsContent>
          ))}
        </Tabs>
        
        {/* Edit Dialog */}
        <Dialog open={!!editingPrompt} onOpenChange={(open) => !open && setEditingPrompt(null)}>
          <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>Edit Global AI Prompt</DialogTitle>
              <DialogDescription>
                Update the global prompt configuration
              </DialogDescription>
            </DialogHeader>
            
            <div className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-name">Prompt Name</Label>
                <Input
                  id="edit-name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                />
              </div>
              
              <div className="grid gap-2">
                <Label htmlFor="edit-type">Prompt Type</Label>
                <Select
                  value={formData.prompt_type}
                  onValueChange={(value) => setFormData({ ...formData, prompt_type: value })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(promptTypes).map(([value, label]) => (
                      <SelectItem key={value} value={value}>
                        <div className="flex items-center gap-2">
                          {getPromptIcon(value)}
                          {label}
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              
              <div className="grid gap-2">
                <Label htmlFor="edit-content">Prompt Content</Label>
                <Textarea
                  id="edit-content"
                  rows={10}
                  value={formData.prompt_content}
                  onChange={(e) => setFormData({ ...formData, prompt_content: e.target.value })}
                  className="font-mono text-sm"
                />
              </div>
              
              <div className="grid gap-2">
                <Label htmlFor="edit-description">Description</Label>
                <Textarea
                  id="edit-description"
                  rows={3}
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                />
              </div>
              
              <div className="space-y-4 border-t pt-4">
                <h4 className="text-sm font-medium">Advanced Settings</h4>
                
                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label>Temperature: {formData.settings.temperature}</Label>
                    <input
                      type="range"
                      min="0"
                      max="2"
                      step="0.1"
                      value={formData.settings.temperature}
                      onChange={(e) => setFormData({
                        ...formData,
                        settings: { ...formData.settings, temperature: parseFloat(e.target.value) }
                      })}
                      className="w-full"
                    />
                  </div>
                  
                  <div className="grid gap-2">
                    <Label>Max Tokens</Label>
                    <Input
                      type="number"
                      min="1"
                      max="4000"
                      value={formData.settings.max_tokens}
                      onChange={(e) => setFormData({
                        ...formData,
                        settings: { ...formData.settings, max_tokens: parseInt(e.target.value) }
                      })}
                    />
                  </div>
                </div>
              </div>
              
              <div className="flex items-center space-x-2">
                <Switch
                  checked={formData.is_active}
                  onCheckedChange={(checked) => setFormData({ ...formData, is_active: checked })}
                />
                <Label>Active</Label>
              </div>
            </div>
            
            <DialogFooter>
              <Button variant="outline" onClick={() => setEditingPrompt(null)}>
                Cancel
              </Button>
              <Button onClick={handleUpdate}>
                Update Prompt
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
        
        {/* Delete Confirmation Dialog */}
        <Dialog open={!!deleteConfirmId} onOpenChange={(open) => !open && setDeleteConfirmId(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Prompt</DialogTitle>
              <DialogDescription>
                Are you sure you want to delete this prompt? This action cannot be undone.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setDeleteConfirmId(null)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={() => deleteConfirmId && handleDelete(deleteConfirmId)}>
                Delete
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}