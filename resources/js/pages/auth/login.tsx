import { Head } from "@inertiajs/react";
import { LoginForm } from "@/components/auth/login-form";
import { Alert, AlertDescription } from "@/components/ui/alert";

interface LoginProps {
  status?: string;
  canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
  return (
    <div className="bg-muted flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
      <Head title="Log in" />

      <div className="w-full max-w-sm md:max-w-3xl">
        {status && (
          <Alert className="mb-4">
            <AlertDescription>{status}</AlertDescription>
          </Alert>
        )}

        <LoginForm canResetPassword={canResetPassword} />
      </div>
    </div>
  );
}
