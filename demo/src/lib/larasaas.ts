/* Showcase fixtures only. Production plans are managed in the database. */

export interface DemoPlan {
  key: string;
  name: string;
  description?: string;
  highlight?: boolean;
  badge?: string | null;
  trial?: number | null;
  monthly: number | null;
  yearly: number | null;
  features: string[];
}

export interface DemoPricing {
  currency: "usd" | "eur" | "sar" | string;
  plans: DemoPlan[];
}

export const CURRENCIES: Record<string, { symbol: string; rateFromUsd: number; round: (n: number) => number }> = {
  usd: { symbol: "$", rateFromUsd: 1, round: (n) => Math.round(n) },
  eur: { symbol: "€", rateFromUsd: 0.92, round: (n) => Math.round(n) },
  sar: { symbol: "ر.س", rateFromUsd: 3.75, round: (n) => Math.round(n) },
};

export const DEFAULT_PRICING: DemoPricing = {
  currency: "usd",
  plans: [
    {
      key: "starter",
      name: "Starter",
      description: "For side-projects and weekend hacks.",
      trial: 7,
      monthly: 9,
      yearly: 90,
      features: ["1 workspace", "3 projects", "Community support", "Basic analytics"],
    },
    {
      key: "growth",
      name: "Growth",
      description: "For teams shipping fast and measuring everything.",
      highlight: true,
      badge: "Most popular",
      trial: 14,
      monthly: 29,
      yearly: 290,
      features: ["5 workspaces", "Unlimited projects", "Priority support", "Advanced analytics", "Custom domains"],
    },
    {
      key: "scale",
      name: "Scale",
      description: "Compliance, SSO and a success manager.",
      trial: 30,
      monthly: 79,
      yearly: 790,
      features: ["Unlimited workspaces", "SSO / SAML", "Audit log", "99.9% SLA", "Dedicated manager"],
    },
  ],
};

export const ANNOUNCEMENT_TYPES: Record<string, { color: string; icon: string }> = {
  info: { color: "#3b82f6", icon: "info" },
  success: { color: "#22c55e", icon: "check" },
  warning: { color: "#f59e0b", icon: "triangle" },
  danger: { color: "#ef4444", icon: "octagon" },
  promo: { color: "#a855f7", icon: "sparkles" },
};

/** Read-only sample UI data, never a production plan configuration. */
export function pricingToPhp(pricing: DemoPricing): string {
  const quote = (value: string) => "'" + value.replace(/\\/g, "\\\\").replace(/'/g, "\\'") + "'";
  const plans = pricing.plans.map(plan => `    ${quote(plan.key)} => [
        'name' => ${quote(plan.name)},
        'monthly' => ['price' => ${plan.monthly ?? 'null'}],
        'yearly' => ['price' => ${plan.yearly ?? 'null'}],
        'features' => [${plan.features.map(quote).join(', ')}],
    ],`).join('\n');
  return `// Showcase data only — NOT config/larasaas.php.
// Production: your dashboard calls LaraSaaS::createPlan($data), then read:
// LaraSaaS::pricingForUi() or GET /api/larasaas/pricing
// No plans are seeded by the installer.
[
  'currency' => ${quote(pricing.currency)},
  'plans' => [
${plans}
  ],
]`;
}
