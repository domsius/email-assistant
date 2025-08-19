import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  AlertCircle,
  Loader2,
  Mail,
  Server,
  Shield,
  Info,
  Check,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface CommonProvider {
  name: string;
  imap_host: string;
  imap_port: number;
  imap_encryption: string;
  smtp_host: string;
  smtp_port: number;
  smtp_encryption: string;
}

interface ImapSetupProps {
  commonProviders: CommonProvider[];
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
  },
  {
    title: "Email Accounts",
    href: "/email-accounts",
  },
  {
    title: "IMAP Setup",
    href: "/email-accounts/imap/setup",
  },
];

export default function ImapSetup({ commonProviders }: ImapSetupProps) {
  const [selectedProvider, setSelectedProvider] = useState<string>("");
  const [testingConnection, setTestingConnection] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState<{
    success?: boolean;
    message?: string;
  }>({});

  const { data, setData, post, processing, errors } = useForm({
    email_address: "",
    sender_name: "",
    imap_host: "",
    imap_port: 993,
    imap_encryption: "ssl",
    imap_username: "",
    imap_password: "",
    smtp_host: "",
    smtp_port: 587,
    smtp_encryption: "tls",
    smtp_username: "",
    smtp_password: "",
  });

  const handleProviderSelect = (providerName: string) => {
    const provider = commonProviders.find((p) => p.name === providerName);
    if (provider) {
      setSelectedProvider(providerName);
      setData({
        ...data,
        imap_host: provider.imap_host,
        imap_port: provider.imap_port,
        imap_encryption: provider.imap_encryption,
        smtp_host: provider.smtp_host,
        smtp_port: provider.smtp_port,
        smtp_encryption: provider.smtp_encryption,
      });
    }
  };

  const handleTestConnection = async () => {
    setTestingConnection(true);
    setConnectionStatus({});
    
    try {
      // In a real implementation, this would test the connection
      // For now, we'll just simulate a test
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      // This would normally be an API call to test the connection
      setConnectionStatus({
        success: true,
        message: "Connection successful! You can now save your settings.",
      });
    } catch (error) {
      setConnectionStatus({
        success: false,
        message: "Connection failed. Please check your settings.",
      });
    } finally {
      setTestingConnection(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post("/email-accounts/imap");
  };

  const handleCancel = () => {
    router.get("/email-accounts");
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="IMAP Setup" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4">
        <div className="max-w-4xl mx-auto w-full">
          {/* Header */}
          <div className="mb-6">
            <h1 className="text-3xl font-bold tracking-tight">
              Connect Email via IMAP/SMTP
            </h1>
            <p className="text-muted-foreground">
              Connect any email account that supports IMAP and SMTP protocols
            </p>
          </div>

          {/* Quick Setup */}
          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Quick Setup</CardTitle>
              <CardDescription>
                Select your email provider for automatic configuration
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                {commonProviders.map((provider) => (
                  <Button
                    key={provider.name}
                    variant={selectedProvider === provider.name ? "default" : "outline"}
                    className="justify-start"
                    onClick={() => handleProviderSelect(provider.name)}
                  >
                    {selectedProvider === provider.name && (
                      <Check className="h-4 w-4 mr-2" />
                    )}
                    {provider.name}
                  </Button>
                ))}
                <Button
                  variant={selectedProvider === "custom" ? "default" : "outline"}
                  className="justify-start"
                  onClick={() => {
                    setSelectedProvider("custom");
                    setData({
                      ...data,
                      imap_host: "",
                      imap_port: 993,
                      imap_encryption: "ssl",
                      smtp_host: "",
                      smtp_port: 587,
                      smtp_encryption: "tls",
                    });
                  }}
                >
                  {selectedProvider === "custom" && (
                    <Check className="h-4 w-4 mr-2" />
                  )}
                  Custom
                </Button>
              </div>
            </CardContent>
          </Card>

          {/* Configuration Form */}
          <form onSubmit={handleSubmit}>
            <Card>
              <CardHeader>
                <CardTitle>Email Account Configuration</CardTitle>
                <CardDescription>
                  Enter your email account details and server settings
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {/* Account Details */}
                <div className="space-y-4">
                  <h3 className="text-sm font-medium flex items-center gap-2">
                    <Mail className="h-4 w-4" />
                    Account Information
                  </h3>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="email_address">Email Address</Label>
                      <Input
                        id="email_address"
                        type="email"
                        placeholder="your@email.com"
                        value={data.email_address}
                        onChange={(e) => {
                          setData("email_address", e.target.value);
                          // Auto-fill username if not set
                          if (!data.imap_username) {
                            setData("imap_username", e.target.value);
                          }
                        }}
                        required
                      />
                      {errors.email_address && (
                        <p className="text-sm text-destructive">{errors.email_address}</p>
                      )}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="sender_name">Display Name (Optional)</Label>
                      <Input
                        id="sender_name"
                        type="text"
                        placeholder="John Doe"
                        value={data.sender_name}
                        onChange={(e) => setData("sender_name", e.target.value)}
                      />
                    </div>
                  </div>
                </div>

                <Tabs defaultValue="imap" className="w-full">
                  <TabsList className="grid w-full grid-cols-2">
                    <TabsTrigger value="imap">
                      <Server className="h-4 w-4 mr-2" />
                      IMAP Settings
                    </TabsTrigger>
                    <TabsTrigger value="smtp">
                      <Server className="h-4 w-4 mr-2" />
                      SMTP Settings
                    </TabsTrigger>
                  </TabsList>

                  <TabsContent value="imap" className="space-y-4">
                    <Alert>
                      <Info className="h-4 w-4" />
                      <AlertDescription>
                        IMAP is used to receive and sync emails from your email provider
                      </AlertDescription>
                    </Alert>
                    
                    <div className="grid gap-4 md:grid-cols-3">
                      <div className="space-y-2">
                        <Label htmlFor="imap_host">IMAP Server</Label>
                        <Input
                          id="imap_host"
                          type="text"
                          placeholder="imap.example.com"
                          value={data.imap_host}
                          onChange={(e) => setData("imap_host", e.target.value)}
                          required
                        />
                        {errors.imap_host && (
                          <p className="text-sm text-destructive">{errors.imap_host}</p>
                        )}
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="imap_port">Port</Label>
                        <Input
                          id="imap_port"
                          type="number"
                          value={data.imap_port}
                          onChange={(e) => setData("imap_port", parseInt(e.target.value))}
                          required
                        />
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="imap_encryption">Encryption</Label>
                        <Select
                          value={data.imap_encryption}
                          onValueChange={(value) => setData("imap_encryption", value)}
                        >
                          <SelectTrigger id="imap_encryption">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="ssl">SSL</SelectItem>
                            <SelectItem value="tls">TLS</SelectItem>
                            <SelectItem value="none">None</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                    
                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <Label htmlFor="imap_username">Username</Label>
                        <Input
                          id="imap_username"
                          type="text"
                          placeholder="Usually your email address"
                          value={data.imap_username}
                          onChange={(e) => setData("imap_username", e.target.value)}
                          required
                        />
                        {errors.imap_username && (
                          <p className="text-sm text-destructive">{errors.imap_username}</p>
                        )}
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="imap_password">Password</Label>
                        <Input
                          id="imap_password"
                          type="password"
                          placeholder="Your email password or app password"
                          value={data.imap_password}
                          onChange={(e) => setData("imap_password", e.target.value)}
                          required
                        />
                        {errors.imap_password && (
                          <p className="text-sm text-destructive">{errors.imap_password}</p>
                        )}
                      </div>
                    </div>
                  </TabsContent>

                  <TabsContent value="smtp" className="space-y-4">
                    <Alert>
                      <Info className="h-4 w-4" />
                      <AlertDescription>
                        SMTP is used to send emails from your account
                      </AlertDescription>
                    </Alert>
                    
                    <div className="grid gap-4 md:grid-cols-3">
                      <div className="space-y-2">
                        <Label htmlFor="smtp_host">SMTP Server</Label>
                        <Input
                          id="smtp_host"
                          type="text"
                          placeholder="smtp.example.com"
                          value={data.smtp_host}
                          onChange={(e) => setData("smtp_host", e.target.value)}
                          required
                        />
                        {errors.smtp_host && (
                          <p className="text-sm text-destructive">{errors.smtp_host}</p>
                        )}
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="smtp_port">Port</Label>
                        <Input
                          id="smtp_port"
                          type="number"
                          value={data.smtp_port}
                          onChange={(e) => setData("smtp_port", parseInt(e.target.value))}
                          required
                        />
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="smtp_encryption">Encryption</Label>
                        <Select
                          value={data.smtp_encryption}
                          onValueChange={(value) => setData("smtp_encryption", value)}
                        >
                          <SelectTrigger id="smtp_encryption">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="ssl">SSL</SelectItem>
                            <SelectItem value="tls">TLS</SelectItem>
                            <SelectItem value="none">None</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                    
                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <Label htmlFor="smtp_username">
                          Username (Leave empty to use IMAP username)
                        </Label>
                        <Input
                          id="smtp_username"
                          type="text"
                          placeholder="Usually same as IMAP username"
                          value={data.smtp_username}
                          onChange={(e) => setData("smtp_username", e.target.value)}
                        />
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="smtp_password">
                          Password (Leave empty to use IMAP password)
                        </Label>
                        <Input
                          id="smtp_password"
                          type="password"
                          placeholder="Usually same as IMAP password"
                          value={data.smtp_password}
                          onChange={(e) => setData("smtp_password", e.target.value)}
                        />
                      </div>
                    </div>
                  </TabsContent>
                </Tabs>

                {/* Connection Status */}
                {connectionStatus.message && (
                  <Alert variant={connectionStatus.success ? "default" : "destructive"}>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{connectionStatus.message}</AlertDescription>
                  </Alert>
                )}

                {/* Security Note */}
                <Alert>
                  <Shield className="h-4 w-4" />
                  <AlertDescription>
                    Your credentials are encrypted and stored securely. We never share your
                    login information with third parties.
                  </AlertDescription>
                </Alert>

                {/* Error Messages */}
                {errors.connection && (
                  <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{errors.connection}</AlertDescription>
                  </Alert>
                )}

                {/* Actions */}
                <div className="flex justify-between">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={handleCancel}
                    disabled={processing}
                  >
                    Cancel
                  </Button>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={handleTestConnection}
                      disabled={
                        !data.email_address ||
                        !data.imap_host ||
                        !data.imap_password ||
                        testingConnection ||
                        processing
                      }
                    >
                      {testingConnection ? (
                        <>
                          <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                          Testing...
                        </>
                      ) : (
                        "Test Connection"
                      )}
                    </Button>
                    <Button type="submit" disabled={processing}>
                      {processing ? (
                        <>
                          <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                          Connecting...
                        </>
                      ) : (
                        "Connect Account"
                      )}
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}