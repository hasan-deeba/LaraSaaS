import { notFound } from "next/navigation";
import { eq } from "drizzle-orm";
import { db } from "@/db";
import { larasaasPricingPresets } from "@/db/schema";
import { SiteProviders } from "@/components/providers";
import { PresetView } from "@/components/preset-view";
import type { DemoPricing } from "@/lib/larasaas";

export const dynamic = "force-dynamic";

export default async function PresetPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;

  const rows = await db
    .select()
    .from(larasaasPricingPresets)
    .where(eq(larasaasPricingPresets.slug, slug.slice(0, 16)))
    .limit(1);

  const preset = rows[0];
  if (!preset) notFound();

  return (
    <SiteProviders>
      <PresetView config={preset.config as DemoPricing} slug={preset.slug} />
    </SiteProviders>
  );
}
