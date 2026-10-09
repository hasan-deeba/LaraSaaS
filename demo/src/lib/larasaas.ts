/* Shared constants for the live demos — mirrors config/larasaas.php */

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

/** Renders a plan config as a config/larasaas.php-ready PHP snippet. */
export function pricingToPhp(pricing: DemoPricing): string {
  const planBlocks = pricing.plans
    .map((plan) => {
      const badge = plan.badge ? `'${plan.badge}'` : "null";
      return `            '${plan.key}' => [
                'name'        => '${plan.name}',
                'description' => '${(plan.description ?? "").replace(/'/g, "\\'")}',
                'highlight'   => ${plan.highlight ? "true" : "false"},
                'badge'       => ${badge},
                'trial_days'  => ${plan.trial ?? "null"},
                'monthly'     => ['price' => ${plan.monthly ?? "null"}, 'stripe_id' => env('STRIPE_PRICE_${plan.key.toUpperCase()}_MONTHLY')],
                'yearly'      => ['price' => ${plan.yearly ?? "null"}, 'stripe_id' => env('STRIPE_PRICE_${plan.key.toUpperCase()}_YEARLY')],
                'features'    => [
${plan.features.map((f) => `                    '${f.replace(/'/g, "\\'")}',`).join("\n")}
                ],
            ],`;
    })
    .join("\n\n");

  return `'pricing' => [
        'default_currency'     => '${pricing.currency}',
        'default_interval'     => 'monthly',
        'intervals'            => ['monthly', 'yearly'],
        'yearly_savings_badge' => '2 months free',

        'plans' => [
${planBlocks}
        ],
    ],`;
}
