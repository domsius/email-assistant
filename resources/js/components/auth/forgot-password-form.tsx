import React from "react";
import { Link, useForm } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Icons } from "@/components/icons";
import { cn } from "@/lib/utils";

interface ForgotPasswordFormProps extends React.HTMLAttributes<HTMLDivElement> {
  status?: string;
}

export function ForgotPasswordForm({
  className,
  status,
  ...props
}: ForgotPasswordFormProps) {
  const { data, setData, post, processing, errors } = useForm({
    email: "",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    post(route("password.email"));
  }

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <form onSubmit={onSubmit} className="p-6 md:p-8">
            <div className="flex flex-col gap-6">
              <div className="flex flex-col items-center text-center">
                <h1 className="text-2xl font-bold">Forgot password?</h1>
                <p className="text-muted-foreground text-balance">
                  Enter your email below and we'll send you a password reset
                  link
                </p>
              </div>

              {status && (
                <Alert>
                  <AlertDescription className="text-center">
                    {status}
                  </AlertDescription>
                </Alert>
              )}

              <div className="grid gap-3">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="m@example.com"
                  required
                  autoFocus
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  disabled={processing}
                  aria-invalid={!!errors.email}
                />
                {errors.email && (
                  <p className="text-sm text-destructive">{errors.email}</p>
                )}
              </div>

              <Button type="submit" className="w-full" disabled={processing}>
                {processing ? (
                  <>
                    <Icons.spinner className="mr-2 h-4 w-4 animate-spin" />
                    Sending reset link...
                  </>
                ) : (
                  "Send password reset link"
                )}
              </Button>

              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <span className="w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                  <span className="bg-card px-2 text-muted-foreground">Or</span>
                </div>
              </div>

              <div className="text-center text-sm">
                Remember your password?{" "}
                <Link
                  href={route("login")}
                  className="underline underline-offset-4"
                >
                  Back to login
                </Link>
              </div>

              <div className="text-center text-sm">
                Don't have an account?{" "}
                <Link
                  href={route("register")}
                  className="underline underline-offset-4"
                >
                  Sign up
                </Link>
              </div>
            </div>
          </form>
          <div className="relative hidden bg-gray-100 dark:bg-gray-900 md:block">
            <svg
              className="absolute inset-0 h-full w-full"
              xmlns="http://www.w3.org/2000/svg"
            >
              <defs>
                <pattern
                  id="forgot-pattern"
                  x="0"
                  y="0"
                  width="40"
                  height="40"
                  patternUnits="userSpaceOnUse"
                >
                  <circle
                    cx="20"
                    cy="20"
                    r="1.5"
                    className="fill-gray-300 dark:fill-gray-700"
                  />
                </pattern>
              </defs>
              <rect width="100%" height="100%" fill="url(#forgot-pattern)" />
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="text-center">
                <svg
                  className="mx-auto h-24 w-24 text-gray-400 dark:text-gray-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={1}
                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                  />
                </svg>
                <p className="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                  Password Recovery
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
