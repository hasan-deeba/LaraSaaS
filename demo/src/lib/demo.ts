import { cookies } from "next/headers";
import { sql } from "drizzle-orm";
import { db } from "@/db";
import {
  larasaasAnnouncements,
  type larasaasAnnouncements as AnnTable,
} from "@/db/schema";
import { ANNOUNCEMENT_TYPES } from "@/lib/larasaas";

export type AnnouncementRow = typeof AnnTable.$inferSelect;

export const VISITOR_COOKIE = "ls_vid";

export async function getVisitor(): Promise<{ id: string; isNew: boolean }> {
  const store = await cookies();
  const existing = store.get(VISITOR_COOKIE)?.value;
  if (existing) return { id: existing, isNew: false };
  return { id: crypto.randomUUID(), isNew: true };
}

/** Shape consumed by the showcase (mirrors the package's toUiArray()). */
export function mapAnnouncement(row: AnnouncementRow) {
  const meta = ANNOUNCEMENT_TYPES[row.type] ?? ANNOUNCEMENT_TYPES.info;
  return {
    id: row.id,
    title: row.title,
    body: row.body,
    type: row.type,
    color: row.color ?? meta.color,
    icon: row.icon ?? meta.icon,
    dismissible: row.dismissible,
    action_label: row.actionLabel,
    action_url: row.actionUrl,
    ends_at: row.endsAt?.toISOString() ?? null,
    starts_at: row.startsAt?.toISOString() ?? null,
  };
}

interface SeedSpec {
  title: string;
  body: string;
  type?: string;
  action?: { label: string; url: string };
  endsInDays?: number | null;
  startsInDays?: number;
}

/** Seed the demo table once — three announcements that show off scheduling + CTAs. */
export async function ensureSeeded() {
  const [{ count }] = await db
    .select({ count: sql<number>`count(*)::int` })
    .from(larasaasAnnouncements);

  if (count > 0) return;

  const seeds: SeedSpec[] = [
    {
      title: "Welcome to the LaraSaaS playground",
      body: "This banner is a real row in Postgres — same schema the Laravel package ships. Dismiss it and reload: it stays gone.",
      type: "info",
      startsInDays: -1,
    },
    {
      title: "Founding deal: Growth −40%",
      body: "A scheduled promo with an end window and a CTA — exactly what the admin panel writes for you.",
      type: "promo",
      action: { label: "See the plans", url: "#pricing" },
      endsInDays: 6,
    },
    {
      title: "Maintenance window",
      body: "Sunday 02:00–02:30 UTC. This banner self-destructs when its ends_at timestamp passes. No code deploy needed.",
      type: "warning",
      endsInDays: 5,
    },
  ];

  await db.insert(larasaasAnnouncements).values(
    seeds.map((seed) => {
      const meta = ANNOUNCEMENT_TYPES[seed.type ?? "info"] ?? ANNOUNCEMENT_TYPES.info;
      const now = Date.now();
      return {
        title: seed.title,
        body: seed.body,
        type: seed.type ?? "info",
        color: meta.color,
        icon: meta.icon,
        actionLabel: seed.action?.label ?? null,
        actionUrl: seed.action?.url ?? null,
        dismissible: true,
        active: true,
        startsAt: seed.startsInDays ? new Date(now + seed.startsInDays * 86_400_000) : new Date(),
        endsAt: seed.endsInDays ? new Date(now + seed.endsInDays * 86_400_000) : null,
      };
    }),
  );
}

/** Rotating copy for the broadcast console. */
export const BROADCAST_TEMPLATES: Record<string, SeedSpec[]> = {
  info: [
    { title: "Product update shipped", body: "A new build just rolled out to every workspace. Broadcasts like this come from the admin panel — no deploy." },
    { title: "Docs refreshed", body: "Integration recipes for workspaces, trials and customer portals were added.", action: { label: "Read the changelog", url: "#structure" } },
  ],
  success: [
    { title: "Nightly backup completed", body: "Snapshots finished for all workspaces in 42 seconds. Green across the board." },
    { title: "Deployment green", body: "v2.1.4 passed all checks and is live in every region." },
  ],
  warning: [
    { title: "Scheduled maintenance", body: "The API will be read-only on Sunday 02:00–02:30 UTC. This banner expires automatically.", endsInDays: 4 },
    { title: "Approaching rate limits", body: "Workspace #2 used 88% of its monthly quota. A nudge before it's an incident." },
  ],
  danger: [
    { title: "Payment retries failing", body: "3 invoices could not be collected. Dunning emails were queued via Cashier.", action: { label: "Open billing", url: "#pricing" } },
    { title: "Webhook endpoint erroring", body: "Stripe reported 12 failed deliveries in the last hour. Check /larasaas/webhooks/stripe." },
  ],
  promo: [
    { title: "Yearly plans: 2 months free", body: "Switch any workspace to yearly billing before the window closes.", action: { label: "See plans", url: "#pricing" }, endsInDays: 7 },
    { title: "Black Friday warm-up", body: "Growth plan −25% for upgrades made this week. Scheduled to vanish on its own.", action: { label: "Claim it", url: "#pricing" }, endsInDays: 6 },
  ],
};
