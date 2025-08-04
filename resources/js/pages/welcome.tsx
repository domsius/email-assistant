import { type SharedData } from "@/types";
import { Head, Link, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
  Brain,
  Shield,
  Users,
  Mail,
  ArrowRight,
  CheckCircle2,
  Sparkles,
  Lock,
  Globe,
  BarChart3,
  MessageSquare,
  Zap,
} from "lucide-react";
import { useState, useEffect } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export default function Welcome() {
  const { auth } = usePage<SharedData>().props;
  const [isPasswordProtected, setIsPasswordProtected] = useState(false);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    // Only enable password protection in production
    if (import.meta.env.PROD) {
      // Check if already authenticated in this session
      const authStatus = sessionStorage.getItem("homepage_auth");
      if (authStatus === "authenticated") {
        setIsAuthenticated(true);
      } else {
        setIsPasswordProtected(true);
      }
    }
  }, []);

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (password === "moneymoney") {
      setIsAuthenticated(true);
      setIsPasswordProtected(false);
      sessionStorage.setItem("homepage_auth", "authenticated");
      setError("");
    } else {
      setError("Incorrect password");
      setPassword("");
    }
  };

  // Show password dialog if in production and not authenticated
  if (isPasswordProtected && !isAuthenticated) {
    return (
      <>
        <Head title="AI-Powered Email Management for Modern Teams" />
        <Dialog open={true}>
          <DialogContent className="sm:max-w-md" onPointerDownOutside={(e) => e.preventDefault()}>
            <DialogHeader>
              <DialogTitle>Password Required</DialogTitle>
              <DialogDescription>
                Please enter the password to access this page.
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handlePasswordSubmit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter password"
                  autoFocus
                />
                {error && (
                  <p className="text-sm text-red-500">{error}</p>
                )}
              </div>
              <Button type="submit" className="w-full">
                Submit
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </>
    );
  }

  return (
    <>
      <Head title="AI-Powered Email Management for Modern Teams">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
          href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
          rel="stylesheet"
        />
      </Head>

      <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        {/* Navigation */}
        <header className="sticky top-0 z-50 w-full border-b border-[#19140035] bg-[#FDFDFC]/80 backdrop-blur dark:border-[#3E3E3A] dark:bg-[#0a0a0a]/80">
          <div className="container mx-auto flex h-16 items-center justify-between px-4 lg:px-8">
            <div className="flex items-center gap-8">
              <Link href="/" className="flex items-center gap-2 cursor-pointer">
                <Mail className="h-6 w-6" />
                <span className="text-xl font-semibold">EmailAI</span>
              </Link>
              <nav className="hidden gap-6 text-sm lg:flex">
                <a
                  href="#features"
                  className="hover:text-[#706f6c] dark:hover:text-[#A1A09A] cursor-pointer"
                >
                  Features
                </a>
                <a
                  href="#how-it-works"
                  className="hover:text-[#706f6c] dark:hover:text-[#A1A09A] cursor-pointer"
                >
                  How it Works
                </a>
                <a
                  href="#pricing"
                  className="hover:text-[#706f6c] dark:hover:text-[#A1A09A] cursor-pointer"
                >
                  Pricing
                </a>
              </nav>
            </div>
            <div className="flex items-center gap-4">
              {auth.user ? (
                <Button asChild>
                  <Link href={route("dashboard")}>Dashboard</Link>
                </Button>
              ) : (
                <>
                  <Button variant="ghost" asChild>
                    <Link href={route("login")}>Log in</Link>
                  </Button>
                  <Button asChild>
                    <Link href={route("register")}>Get Started</Link>
                  </Button>
                </>
              )}
            </div>
          </div>
        </header>

        {/* Hero Section */}
        <section className="relative overflow-hidden px-4 py-24 lg:px-8 lg:py-32">
          <div className="container mx-auto max-w-6xl">
            <div className="grid gap-12 lg:grid-cols-2 lg:gap-16">
              <div className="flex flex-col justify-center">
                <Badge className="mb-4 w-fit">
                  <Sparkles className="mr-1 h-3 w-3" />
                  Powered by Advanced AI
                </Badge>
                <h1 className="mb-6 text-4xl font-bold leading-tight lg:text-5xl xl:text-6xl">
                  AI-Powered Email Management for Modern Teams
                </h1>
                <p className="mb-8 text-lg text-[#706f6c] dark:text-[#A1A09A] lg:text-xl">
                  Transform your inbox with intelligent email processing,
                  automated responses, and advanced analytics. Save hours every
                  week while never missing important messages.
                </p>
                <div className="flex flex-col gap-4 sm:flex-row">
                  <Button size="lg" asChild>
                    <Link href={route("register")}>
                      Start Free Trial
                      <ArrowRight className="ml-2 h-4 w-4" />
                    </Link>
                  </Button>
                  <Button size="lg" variant="outline" asChild>
                    <a href="#how-it-works">Watch Demo</a>
                  </Button>
                </div>
              </div>
              <div className="relative">
                <div className="aspect-square rounded-lg bg-gradient-to-br from-blue-50 to-indigo-100 p-8 dark:from-blue-950/20 dark:to-indigo-950/20">
                  <div className="flex h-full items-center justify-center">
                    <div className="relative">
                      <Mail className="h-32 w-32 text-blue-500 dark:text-blue-400" />
                      <Brain className="absolute -right-4 -top-4 h-16 w-16 text-purple-500 dark:text-purple-400" />
                      <Zap className="absolute -bottom-4 -left-4 h-12 w-12 text-yellow-500 dark:text-yellow-400" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Features Section */}
        <section
          id="features"
          className="border-t border-[#19140035] px-4 py-24 dark:border-[#3E3E3A] lg:px-8"
        >
          <div className="container mx-auto max-w-6xl">
            <div className="mb-12 text-center">
              <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
                Everything you need to master your inbox
              </h2>
              <p className="mx-auto max-w-2xl text-lg text-[#706f6c] dark:text-[#A1A09A]">
                Our AI-powered platform brings intelligence to every aspect of
                email management
              </p>
            </div>

            <div className="grid gap-8 md:grid-cols-2">
              {/* AI Capabilities */}
              <Card className="relative overflow-hidden">
                <CardHeader>
                  <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/20">
                    <Brain className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                  </div>
                  <CardTitle className="text-2xl">AI Intelligence</CardTitle>
                  <CardDescription>
                    Advanced AI capabilities that understand your emails
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-3">
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Smart email summarization for quick overview</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Sentiment analysis to gauge email tone</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Automated response generation</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Multi-language detection and translation</span>
                    </li>
                  </ul>
                </CardContent>
              </Card>

              {/* Multi-Provider Support */}
              <Card className="relative overflow-hidden">
                <CardHeader>
                  <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                    <Globe className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                  </div>
                  <CardTitle className="text-2xl">
                    Universal Compatibility
                  </CardTitle>
                  <CardDescription>
                    Works seamlessly with all major email providers
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-3">
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Gmail integration with full API support</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Outlook & Microsoft 365 compatibility</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Easy OAuth2 setup in minutes</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Real-time email synchronization</span>
                    </li>
                  </ul>
                </CardContent>
              </Card>

              {/* Security Features */}
              <Card className="relative overflow-hidden">
                <CardHeader>
                  <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/20">
                    <Shield className="h-6 w-6 text-green-600 dark:text-green-400" />
                  </div>
                  <CardTitle className="text-2xl">
                    Enterprise Security
                  </CardTitle>
                  <CardDescription>
                    Bank-level security and privacy protection
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-3">
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>End-to-end encryption for all data</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>GDPR compliant with data sovereignty</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>OAuth2 authentication (no password storage)</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Regular security audits and compliance</span>
                    </li>
                  </ul>
                </CardContent>
              </Card>

              {/* Team Collaboration */}
              <Card className="relative overflow-hidden">
                <CardHeader>
                  <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                    <Users className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                  </div>
                  <CardTitle className="text-2xl">Team Collaboration</CardTitle>
                  <CardDescription>
                    Built for teams that work together
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-3">
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Shared workspaces and email delegation</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Role-based access control</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Collaborative email workflows</span>
                    </li>
                    <li className="flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                      <span>Team analytics and insights</span>
                    </li>
                  </ul>
                </CardContent>
              </Card>
            </div>
          </div>
        </section>

        {/* How It Works */}
        <section
          id="how-it-works"
          className="border-t border-[#19140035] bg-white px-4 py-24 dark:border-[#3E3E3A] dark:bg-[#161615] lg:px-8"
        >
          <div className="container mx-auto max-w-6xl">
            <div className="mb-12 text-center">
              <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
                Get started in minutes
              </h2>
              <p className="mx-auto max-w-2xl text-lg text-[#706f6c] dark:text-[#A1A09A]">
                Our simple setup process gets you up and running quickly
              </p>
            </div>

            <div className="grid gap-8 md:grid-cols-4">
              {/* Step 1 */}
              <div className="text-center">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                  1
                </div>
                <h3 className="mb-2 text-lg font-semibold">
                  Connect Your Email
                </h3>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                  Securely connect your Gmail or Outlook account with OAuth2
                </p>
              </div>

              {/* Step 2 */}
              <div className="text-center">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-purple-100 text-2xl font-bold text-purple-600 dark:bg-purple-900/20 dark:text-purple-400">
                  2
                </div>
                <h3 className="mb-2 text-lg font-semibold">AI Processing</h3>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                  Our AI analyzes and categorizes your emails automatically
                </p>
              </div>

              {/* Step 3 */}
              <div className="text-center">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-2xl font-bold text-green-600 dark:bg-green-900/20 dark:text-green-400">
                  3
                </div>
                <h3 className="mb-2 text-lg font-semibold">Get Insights</h3>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                  View summaries, sentiment analysis, and automated responses
                </p>
              </div>

              {/* Step 4 */}
              <div className="text-center">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 text-2xl font-bold text-orange-600 dark:bg-orange-900/20 dark:text-orange-400">
                  4
                </div>
                <h3 className="mb-2 text-lg font-semibold">Collaborate</h3>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                  Share workspaces and collaborate with your team efficiently
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* CTA Section */}
        <section
          id="pricing"
          className="border-t border-[#19140035] px-4 py-24 dark:border-[#3E3E3A] lg:px-8"
        >
          <div className="container mx-auto max-w-4xl text-center">
            <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
              Ready to transform your email workflow?
            </h2>
            <p className="mb-8 text-lg text-[#706f6c] dark:text-[#A1A09A]">
              Join thousands of teams already using EmailAI to save time and
              improve productivity
            </p>
            <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
              <Button size="lg" asChild>
                <Link href={route("register")}>
                  Start Your Free Trial
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Link>
              </Button>
              <Button size="lg" variant="outline" asChild>
                <a href="mailto:sales@emailai.com">Contact Sales</a>
              </Button>
            </div>
            <p className="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
              No credit card required • 14-day free trial • Cancel anytime
            </p>
          </div>
        </section>

        {/* Footer */}
        <footer className="border-t border-[#19140035] bg-white px-4 py-12 dark:border-[#3E3E3A] dark:bg-[#161615] lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="grid gap-8 md:grid-cols-4">
              <div>
                <div className="mb-4 flex items-center gap-2">
                  <Mail className="h-5 w-5" />
                  <span className="font-semibold">EmailAI</span>
                </div>
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                  AI-powered email management for modern teams
                </p>
              </div>

              <div>
                <h3 className="mb-3 font-semibold">Product</h3>
                <ul className="space-y-2 text-sm">
                  <li>
                    <a
                      href="#features"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Features
                    </a>
                  </li>
                  <li>
                    <a
                      href="#pricing"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Pricing
                    </a>
                  </li>
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Security
                    </a>
                  </li>
                </ul>
              </div>

              <div>
                <h3 className="mb-3 font-semibold">Company</h3>
                <ul className="space-y-2 text-sm">
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      About
                    </a>
                  </li>
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Blog
                    </a>
                  </li>
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Careers
                    </a>
                  </li>
                </ul>
              </div>

              <div>
                <h3 className="mb-3 font-semibold">Legal</h3>
                <ul className="space-y-2 text-sm">
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Privacy Policy
                    </a>
                  </li>
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Terms of Service
                    </a>
                  </li>
                  <li>
                    <a
                      href="#"
                      className="text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC] cursor-pointer"
                    >
                      Cookie Policy
                    </a>
                  </li>
                </ul>
              </div>
            </div>

            <Separator className="my-8" />

            <div className="flex flex-col items-center justify-between gap-4 text-sm text-[#706f6c] dark:text-[#A1A09A] sm:flex-row">
              <p>&copy; 2025 EmailAI. All rights reserved.</p>
              <div className="flex gap-4">
                <a
                  href="#"
                  className="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] cursor-pointer"
                >
                  Twitter
                </a>
                <a
                  href="#"
                  className="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] cursor-pointer"
                >
                  LinkedIn
                </a>
                <a
                  href="#"
                  className="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] cursor-pointer"
                >
                  GitHub
                </a>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
