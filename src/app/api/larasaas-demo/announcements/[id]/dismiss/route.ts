import { NextResponse } from "next/server";
import { db } from "@/db";
import { larasaasAnnouncementDismissals } from "@/db/schema";
import { VISITOR_COOKIE, getVisitor } from "@/lib/demo";

export const dynamic = "force-dynamic";

/** POST — persist a dismissal for this visitor (survives reloads). */
export async function POST(_request: Request, context: { params: Promise<{ id: string }> }) {
  const { id } = await context.params;
  const announcementId = Number(id);

  if (!Number.isInteger(announcementId) || announcementId < 1) {
    return NextResponse.json({ error: "invalid announcement id" }, { status: 400 });
  }

  const visitor = await getVisitor();

  try {
    await db
      .insert(larasaasAnnouncementDismissals)
      .values({ announcementId, visitorId: visitor.id })
      .onConflictDoNothing({
        target: [
          larasaasAnnouncementDismissals.announcementId,
          larasaasAnnouncementDismissals.visitorId,
        ],
      });
  } catch {
    return NextResponse.json({ error: "announcement not found" }, { status: 404 });
  }

  const response = NextResponse.json({ dismissed: true });
  if (visitor.isNew) {
    response.cookies.set(VISITOR_COOKIE, visitor.id, {
      httpOnly: true,
      sameSite: "lax",
      path: "/",
      maxAge: 60 * 60 * 24 * 365,
    });
  }
  return response;
}
