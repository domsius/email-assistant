import React from "react";
import { Link, useForm } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Icons } from "@/components/icons";
import { PlanCard } from "./plan-card";
import { cn } from "@/lib/utils";

interface RegisterFormProps extends React.HTMLAttributes<HTMLDivElement> {
  plans: Record<
    string,
    {
      name: string;
      description: string;
      price: number;
      email_limit: number;
      features: string[];
    }
  >;
}

export function RegisterForm({
  className,
  plans,
  ...props
}: RegisterFormProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    company_name: "",
    plan: "starter", // Default to starter as it's recommended
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    post(route("register"), {
      onFinish: () => reset("password", "password_confirmation"),
    });
  }

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <form onSubmit={onSubmit} className="space-y-6">
        {/* Plan Selection */}
        <div className="space-y-4">
          <div className="text-center">
            <h1 className="text-3xl font-bold">Choose Your Plan</h1>
            <p className="text-muted-foreground mt-2">
              Start with a plan that fits your needs. You can upgrade anytime.
            </p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Object.entries(plans).map(([key, plan]) => (
              <PlanCard
                key={key}
                planKey={key}
                plan={plan}
                isSelected={data.plan === key}
                isRecommended={key === "starter"}
                onSelect={(planKey) => setData("plan", planKey)}
                disabled={processing}
              />
            ))}
          </div>
          {errors.plan && (
            <p className="text-sm text-destructive text-center">
              {errors.plan}
            </p>
          )}
        </div>

        {/* Account Details */}
        <Card className="overflow-hidden">
          <CardContent className="p-6 md:p-8">
            <div className="space-y-6">
              <div className="text-center">
                <h2 className="text-2xl font-bold">Create Your Account</h2>
                <p className="text-muted-foreground mt-2">
                  Enter your details to get started
                </p>
              </div>

              <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-4">
                  <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                      id="name"
                      type="text"
                      placeholder="John Doe"
                      required
                      autoFocus
                      value={data.name}
                      onChange={(e) => setData("name", e.target.value)}
                      disabled={processing}
                      aria-invalid={!!errors.name}
                    />
                    {errors.name && (
                      <p className="text-sm text-destructive">{errors.name}</p>
                    )}
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                      id="email"
                      type="email"
                      placeholder="m@example.com"
                      required
                      value={data.email}
                      onChange={(e) => setData("email", e.target.value)}
                      disabled={processing}
                      aria-invalid={!!errors.email}
                    />
                    {errors.email && (
                      <p className="text-sm text-destructive">{errors.email}</p>
                    )}
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="company_name">Company Name</Label>
                    <Input
                      id="company_name"
                      type="text"
                      placeholder="Acme Corp"
                      required
                      value={data.company_name}
                      onChange={(e) => setData("company_name", e.target.value)}
                      disabled={processing}
                      aria-invalid={!!errors.company_name}
                    />
                    {errors.company_name && (
                      <p className="text-sm text-destructive">
                        {errors.company_name}
                      </p>
                    )}
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                      id="password"
                      type="password"
                      required
                      value={data.password}
                      onChange={(e) => setData("password", e.target.value)}
                      disabled={processing}
                      aria-invalid={!!errors.password}
                    />
                    {errors.password && (
                      <p className="text-sm text-destructive">
                        {errors.password}
                      </p>
                    )}
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">
                      Confirm Password
                    </Label>
                    <Input
                      id="password_confirmation"
                      type="password"
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
                </div>
              </div>

              <Button
                type="submit"
                className="w-full"
                size="lg"
                disabled={processing}
              >
                {processing ? (
                  <>
                    <Icons.spinner className="mr-2 h-4 w-4 animate-spin" />
                    Creating account...
                  </>
                ) : (
                  <>Create account with {plans[data.plan]?.name} plan</>
                )}
              </Button>

              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <span className="w-full border-t" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                  <span className="bg-background px-2 text-muted-foreground">
                    Or continue with
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <Button variant="outline" type="button" disabled={processing}>
                  <Icons.google className="h-4 w-4" />
                  <span className="sr-only">Sign up with Google</span>
                </Button>
                <Button variant="outline" type="button" disabled={processing}>
                  <Icons.gitHub className="h-4 w-4" />
                  <span className="sr-only">Sign up with GitHub</span>
                </Button>
                <Button variant="outline" type="button" disabled={processing}>
                  <Icons.apple className="h-4 w-4" />
                  <span className="sr-only">Sign up with Apple</span>
                </Button>
              </div>

              <div className="text-center text-sm">
                Already have an account?{" "}
                <Link
                  href={route("login")}
                  className="underline underline-offset-4 hover:text-primary"
                >
                  Sign in
                </Link>
              </div>
            </div>
          </CardContent>
        </Card>
      </form>

      <div className="text-center text-xs text-muted-foreground">
        By clicking continue, you agree to our{" "}
        <a href="#" className="underline underline-offset-4 hover:text-primary">
          Terms of Service
        </a>{" "}
        and{" "}
        <a href="#" className="underline underline-offset-4 hover:text-primary">
          Privacy Policy
        </a>
        .
      </div>
    </div>
  );
}
