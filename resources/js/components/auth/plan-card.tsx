import React from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Check } from "lucide-react";
import { cn } from "@/lib/utils";

interface PlanCardProps {
  planKey: string;
  plan: {
    name: string;
    description: string;
    price: number;
    email_limit: number;
    features: string[];
  };
  isSelected: boolean;
  isRecommended?: boolean;
  onSelect: (planKey: string) => void;
  disabled?: boolean;
}

export function PlanCard({
  planKey,
  plan,
  isSelected,
  isRecommended = false,
  onSelect,
  disabled = false,
}: PlanCardProps) {
  return (
    <Card
      className={cn(
        "relative cursor-pointer transition-all hover:shadow-lg",
        isSelected && "ring-2 ring-primary",
        disabled && "cursor-not-allowed opacity-50",
      )}
      onClick={() => !disabled && onSelect(planKey)}
    >
      {isRecommended && (
        <Badge className="absolute -top-2 left-1/2 -translate-x-1/2 bg-primary z-10">
          Recommended
        </Badge>
      )}
      <CardHeader className="pb-4">
        <CardTitle className="flex items-baseline justify-between">
          <span>{plan.name}</span>
          <span className="text-2xl font-bold">
            ${plan.price}
            <span className="text-sm font-normal text-muted-foreground">
              /mo
            </span>
          </span>
        </CardTitle>
        <p className="text-sm text-muted-foreground">{plan.description}</p>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="text-center">
          <p className="text-3xl font-bold">
            {plan.email_limit.toLocaleString()}
          </p>
          <p className="text-sm text-muted-foreground">emails/month</p>
        </div>
        <ul className="space-y-2 text-sm">
          {plan.features.slice(0, 3).map((feature, index) => (
            <li key={index} className="flex items-start gap-2">
              <Check className="h-4 w-4 text-primary mt-0.5 shrink-0" />
              <span>{feature}</span>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}
