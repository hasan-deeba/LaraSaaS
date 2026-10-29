import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { larasaasPricingPresets } from "@/db/schema";
import type { DemoPlan, DemoPricing } from "@/lib/larasaas";
import { randomBytes } from "crypto";

export const dynamic = "force-dynamic";

const CURRENCIES = new Set(["usd", "eur", "sar"]);

function sanitizeString(value: unknown, max: number, fallback = ""): string {
  return typeof value === "string" ? value.slice(0, max) : fallback;
}

function sanitizePrice(value: unknown): number | null {
  if (value === null || value === undefined) return null;
  const n = Number(value);
  return Number.isFinite(n) && n >= 0 && n <= 100_000 ? Math.round(n) : null;
}

function sanitizePlan(raw: unknown): DemoPlan | null {
  if (typeof raw !== "object" || raw === null) return null;
  const plan = raw as Record<string, unknown>;
  const key = sanitizeString(plan.key, 24).replace(/[^a-z0-9-_]/gi, "").toLowerCase();
  const name = sanitizeString(plan.name, 40);
  if (!key || !name) return null;

  return {
    key,
    name,
    description: plan.description ? sanitizeString(plan.description, 120) : undefined,
    highlight: Boolean(plan.highlight),
    badge: plan.badge ? sanitizeString(plan.badge, 24) : null,
    trial: typeof plan.trial === "number" && plan.trial >= 0 && plan.trial <= 365 ? Math.round(plan.trial) : null,
    monthly: sanitizePrice(plan.monthly),
    yearly: sanitizePrice(plan.yearly),
    features: Array.isArray(plan.features)
      ? plan.features.slice(0, 10).map((f) => sanitizeString(f, 60)).filter(Boolean)
      : [],
  };
}

function newSlug(): string {
  return randomBytes(6).toString("base64url").slice(0, 8);
}

/** POST — save a pricing playground config as a shareable preset. */
export async function POST(request: NextRequest) {
  const body = (await request.json().catch(() => null)) as { config?: unknown; name?: unknown } | null;
  const raw = body?.config;

  if (typeof raw !== "object" || raw === null) {
    return NextResponse.json({ error: "config is required" }, { status: 422 });
  }

  const rawConfig = raw as Record<string, unknown>;
  const plans = Array.isArray(rawConfig.plans)
    ? rawConfig.plans.map(sanitizePlan).filter((p): p is DemoPlan => p !== null).slice(0, 6)
    : [];

  if (!plans.length) {
    return NextResponse.json({ error: "config.plans must contain at least one valid plan" }, { status: 422 });
  }

  const currency = sanitizeString(rawConfig.currency, 8);
  const config: DemoPricing = {
    currency: CURRENCIES.has(currency) ? currency : "usd",
    plans,
  };

  const name = body?.name ? sanitizeString(body.name, 60) : null;

  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const [preset] = await db
        .insert(larasaasPricingPresets)
        .values({ slug: newSlug(), name, config })
        .returning({ slug: larasaasPricingPresets.slug });

      return NextResponse.json({ slug: preset.slug, url: `/p/${preset.slug}` }, { status: 201 });
    } catch {
      /* slug collision — retry */
    }
  }

  return NextResponse.json({ error: "could not allocate preset slug" }, { status: 500 });
}
