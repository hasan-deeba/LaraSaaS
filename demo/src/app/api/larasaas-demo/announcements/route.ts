import { NextRequest, NextResponse } from "next/server";
import { and, desc, eq, gte, isNull, lte, or, sql } from "drizzle-orm";
import { db } from "@/db";
import {
  larasaasAnnouncements,
  larasaasAnnouncementDismissals,
} from "@/db/schema";
import {
  BROADCAST_TEMPLATES,
  VISITOR_COOKIE,
  ensureSeeded,
  getVisitor,
  mapAnnouncement,
} from "@/lib/demo";
import { ANNOUNCEMENT_TYPES } from "@/lib/larasaas";

export const dynamic = "force-dynamic";

const COOKIE_OPTS = { httpOnly: true, sameSite: "lax" as const, path: "/", maxAge: 60 * 60 * 24 * 365 };

/** GET — active, in-window announcements not yet dismissed by this visitor. */
export async function GET() {
  await ensureSeeded();
  const visitor = await getVisitor();
  const now = new Date();

  const dismissed = await db
    .select({ id: larasaasAnnouncementDismissals.announcementId })
    .from(larasaasAnnouncementDismissals)
    .where(eq(larasaasAnnouncementDismissals.visitorId, visitor.id));

  const dismissedIds = new Set(dismissed.map((d) => d.id));

  const rows = await db
    .select()
    .from(larasaasAnnouncements)
    .where(
      and(
        eq(larasaasAnnouncements.active, true),
        or(isNull(larasaasAnnouncements.startsAt), lte(larasaasAnnouncements.startsAt, now)),
        or(isNull(larasaasAnnouncements.endsAt), gte(larasaasAnnouncements.endsAt, now)),
      ),
    )
    .orderBy(desc(larasaasAnnouncements.createdAt))
    .limit(12);

  const visible = rows.filter((row) => !dismissedIds.has(row.id)).map(mapAnnouncement);

  const response = NextResponse.json({ data: visible });
  if (visitor.isNew) response.cookies.set(VISITOR_COOKIE, visitor.id, COOKIE_OPTS);
  return response;
}

/** POST — broadcast a new announcement (the demo console). */
export async function POST(request: NextRequest) {
  const body = (await request.json().catch(() => ({}))) as { type?: string };
  const types = Object.keys(ANNOUNCEMENT_TYPES);
  const type = !body.type || body.type === "random" ? types[Math.floor(Math.random() * types.length)] : body.type;

  if (!types.includes(type)) {
    return NextResponse.json({ error: "unknown type" }, { status: 422 });
  }

  const meta = ANNOUNCEMENT_TYPES[type];
  const pool = BROADCAST_TEMPLATES[type] ?? [];
  const template = pool[Math.floor(Math.random() * pool.length)] ?? {
    title: "Broadcast",
    body: "A live announcement from the console.",
  };

  const [row] = await db
    .insert(larasaasAnnouncements)
    .values({
      title: template.title,
      body: template.body,
      type,
      color: meta.color,
      icon: meta.icon,
      actionLabel: template.action?.label ?? null,
      actionUrl: template.action?.url ?? null,
      dismissible: true,
      active: true,
      startsAt: new Date(),
      endsAt: template.endsInDays ? new Date(Date.now() + template.endsInDays * 86_400_000) : null,
    })
    .returning();

  // keep the demo table tidy: retain the newest 200 rows
  await db.execute(
    sql`DELETE FROM larasaas_announcements WHERE id < (SELECT COALESCE(MAX(id), 0) - 200 FROM larasaas_announcements)`,
  );

  return NextResponse.json({ data: mapAnnouncement(row) }, { status: 201 });
}
