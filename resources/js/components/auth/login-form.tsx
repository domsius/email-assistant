import React from "react";
import { Link, useForm } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Icons } from "@/components/icons";
import { cn } from "@/lib/utils";

interface LoginFormProps extends React.HTMLAttributes<HTMLDivElement> {
  canResetPassword?: boolean;
}

export function LoginForm({
  className,
  canResetPassword,
  ...props
}: LoginFormProps) {
  const { data, setData, post, processing, errors } = useForm({
    email: "",
    password: "",
    remember: false,
  });

  const [showPassword, setShowPassword] = React.useState(false);

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    post(route("login"));
  }

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <form onSubmit={onSubmit} className="p-6 md:p-8">
            <div className="flex flex-col gap-6">
              <div className="flex flex-col items-center text-center">
                <h1 className="text-2xl font-bold">Welcome back</h1>
                <p className="text-muted-foreground text-balance">
                  Enter your email to sign in to your account
                </p>
              </div>
              <div className="grid gap-3">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  placeholder="name@example.com"
                  type="email"
                  autoCapitalize="none"
                  autoComplete="email"
                  autoCorrect="off"
                  disabled={processing}
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  aria-invalid={!!errors.email}
                />
                {errors.email && (
                  <p className="text-sm text-destructive">{errors.email}</p>
                )}
              </div>
              <div className="grid gap-3">
                <div className="flex items-center">
                  <Label htmlFor="password">Password</Label>
                  {canResetPassword && (
                    <Link
                      href={route("password.request")}
                      className="ml-auto text-sm underline-offset-2 hover:underline"
                    >
                      Forgot password?
                    </Link>
                  )}
                </div>
                <div className="relative">
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    disabled={processing}
                    value={data.password}
                    onChange={(e) => setData("password", e.target.value)}
                    aria-invalid={!!errors.password}
                  />
                </div>
                {errors.password && (
                  <p className="text-sm text-destructive">{errors.password}</p>
                )}
              </div>
              <Button type="submit" className="w-full" disabled={processing}>
                {processing ? (
                  <>
                    <Icons.spinner className="mr-2 h-4 w-4 animate-spin" />
                    Signing in...
                  </>
                ) : (
                  "Sign in"
                )}
              </Button>
              <div className="after:border-border relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t">
                <span className="bg-card text-muted-foreground relative z-10 px-2">
                  Or continue with
                </span>
              </div>
              <div className="grid grid-cols-3 gap-4">
                <Button
                  variant="outline"
                  type="button"
                  className="w-full"
                  disabled={processing}
                >
                  <Icons.apple className="h-4 w-4" />
                  <span className="sr-only">Login with Apple</span>
                </Button>
                <Button
                  variant="outline"
                  type="button"
                  className="w-full"
                  disabled={processing}
                >
                  <Icons.google className="h-4 w-4" />
                  <span className="sr-only">Login with Google</span>
                </Button>
                <Button
                  variant="outline"
                  type="button"
                  className="w-full"
                  disabled={processing}
                >
                  <Icons.gitHub className="h-4 w-4" />
                  <span className="sr-only">Login with GitHub</span>
                </Button>
              </div>
              <div className="text-center text-sm">
                Don&apos;t have an account?{" "}
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
                  id="login-pattern"
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
              <rect width="100%" height="100%" fill="url(#login-pattern)" />
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
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>
                <p className="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                  Secure Login
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
      <div className="text-muted-foreground *:[a]:hover:text-primary text-center text-xs text-balance *:[a]:underline *:[a]:underline-offset-4">
        By clicking continue, you agree to our <a href="#">Terms of Service</a>{" "}
        and <a href="#">Privacy Policy</a>.
      </div>
    </div>
  );
}
