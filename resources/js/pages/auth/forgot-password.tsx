import { Head } from "@inertiajs/react";
import { ForgotPasswordForm } from "@/components/auth/forgot-password-form";

export default function ForgotPassword({ status }: { status?: string }) {
  return (
    <div className="bg-muted flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
      <Head title="Forgot password" />

      <div className="w-full max-w-sm md:max-w-3xl">
        <ForgotPasswordForm status={status} />
      </div>
    </div>
  );
}
