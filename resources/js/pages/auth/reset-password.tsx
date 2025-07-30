import { Head } from "@inertiajs/react";
import { ResetPasswordForm } from "@/components/auth/reset-password-form";

interface ResetPasswordProps {
  token: string;
  email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
  return (
    <div className="bg-muted flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
      <Head title="Reset password" />

      <div className="w-full max-w-sm md:max-w-3xl">
        <ResetPasswordForm token={token} email={email} />
      </div>
    </div>
  );
}
