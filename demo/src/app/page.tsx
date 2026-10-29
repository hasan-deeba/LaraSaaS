import { SiteProviders } from "@/components/providers";
import { Nav } from "@/components/nav";
import { Hero } from "@/components/hero";
import { Integration } from "@/components/integration";
import { PricingPlayground } from "@/components/pricing-playground";
import { AnnouncementsDemo } from "@/components/announcements-demo";
import { ConfigReference } from "@/components/config-reference";
import { Structure } from "@/components/structure";
import { Install } from "@/components/install";
import { Footer } from "@/components/footer";

export default function Home() {
  return (
    <SiteProviders>
      <Nav />
      <main>
        <Hero />
        <Integration />
        <PricingPlayground />
        <AnnouncementsDemo />
        <ConfigReference />
        <Structure />
        <Install />
      </main>
      <Footer />
    </SiteProviders>
  );
}
