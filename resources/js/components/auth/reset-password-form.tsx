import React from "react";
import { Link, useForm } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Icons } from "@/components/icons";
import { cn } from "@/lib/utils";

interface ResetPasswordFormProps extends React.HTMLAttributes<HTMLDivElement> {
  token: string;
  email: string;
}

export function ResetPasswordForm({
  className,
  token,
  email,
  ...props
}: ResetPasswordFormProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    token: token,
    email: email,
    password: "",
    password_confirmation: "",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    post(route("password.store"), {
      onFinish: () => reset("password", "password_confirmation"),
    });
  }

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <form onSubmit={onSubmit} className="p-6 md:p-8">
            <div className="flex flex-col gap-6">
              <div className="flex flex-col items-center text-center">
                <h1 className="text-2xl font-bold">Reset password</h1>
                <p className="text-muted-foreground text-balance">
                  Please enter your new password below
                </p>
              </div>

              <div className="grid gap-3">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  disabled={processing}
                  readOnly
                  aria-invalid={!!errors.email}
                />
                {errors.email && (
                  <p className="text-sm text-destructive">{errors.email}</p>
                )}
              </div>

              <div className="grid gap-3">
                <Label htmlFor="password">New Password</Label>
                <Input
                  id="password"
                  type="password"
                  placeholder="Enter new password"
                  required
                  autoFocus
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                  disabled={processing}
                  aria-invalid={!!errors.password}
                />
                {errors.password && (
                  <p className="text-sm text-destructive">{errors.password}</p>
                )}
              </div>

              <div className="grid gap-3">
                <Label htmlFor="password_confirmation">
                  Confirm New Password
                </Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  placeholder="Confirm new password"
                  required
                  value={data.password_confirmation}
                  onChange={(e) =>
                    setData("password_confirmation", e.target.value)
                  }
                  disabled={processing}
                  aria-invalid={!!errors.password_confirmation}
                />
                {errors.password_confirmation && (
                  <p className="text-sm text-destructive">
                    {errors.password_confirmation}
                  </p>
                )}
              </div>

              <Button type="submit" className="w-full" disabled={processing}>
                {processing ? (
                  <>
                    <Icons.spinner className="mr-2 h-4 w-4 animate-spin" />
                    Resetting password...
                  </>
                ) : (
                  "Reset password"
                )}
              </Button>

              <div className="text-center text-sm">
                Remember your password?{" "}
                <Link
                  href={route("login")}
                  className="underline underline-offset-4"
                >
                  Back to login
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
                  id="reset-pattern"
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
              <rect width="100%" height="100%" fill="url(#reset-pattern)" />
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
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                  />
                </svg>
                <p className="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                  Secure Password Reset
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
