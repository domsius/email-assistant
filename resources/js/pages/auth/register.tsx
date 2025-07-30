import { Head } from "@inertiajs/react";
import { RegisterForm } from "@/components/auth/register-form";

interface RegisterPageProps {
  plans: Record<string, {
    name: string;
    description: string;
    price: number;
    email_limit: number;
    features: string[];
  }>;
}

export default function Register({ plans }: RegisterPageProps) {
  return (
    <div className="bg-muted flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
      <Head title="Register" />

      <div className="w-full max-w-6xl">
        <RegisterForm plans={plans} />
      </div>
    </div>
  );
}
