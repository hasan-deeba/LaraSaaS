"use client";

import { motion } from "framer-motion";
import { ArrowLeft, Database, Share2 } from "lucide-react";
import { useSite } from "./providers";
import { PricingTableDemo } from "./larasaas/pricing-table";
import { Wordmark } from "./nav";
import { CodeBlock } from "./code";
import { pricingToPhp, type DemoPricing } from "@/lib/larasaas";

export function PresetView({ config, slug }: { config: DemoPricing; slug: string }) {
  const { theme, toggleTheme } = useSite();

  return (
    <div className="min-h-screen">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-5 pt-6">
        <a href="/" className="flex items-center gap-3">
          <Wordmark />
        </a>
        <div className="flex items-center gap-2">
          <span className="hidden items-center gap-2 rounded-full border border-line px-3.5 py-1.5 font-mono text-[11px] text-muted sm:inline-flex">
            <Share2 size={11} className="text-accent-2" />
            /p/{slug}
          </span>
          <button
            onClick={toggleTheme}
            className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-muted transition hover:text-fg"
          >
            {theme === "dark" ? "Light" : "Dark"}
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-5 pb-24 pt-16">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
          className="mb-14 text-center"
        >
          <span className="eyebrow">
            <span className="dot" />
            <Database size={11} className="text-accent" />
            shared pricing preset · stored in Postgres
          </span>
          <h1 className="mx-auto mt-6 max-w-2xl text-4xl font-bold tracking-tight md:text-6xl">
            This table was <span className="text-grad">generated from data.</span>
          </h1>
          <p className="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-muted md:text-base">
            Someone designed these plans in the LaraSaaS playground and hit save. The same JSON drops into
            config/larasaas.php and renders in any Laravel app.
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 32 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.15, duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
        >
          <PricingTableDemo config={config} viewer="visitor" />
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 24 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="mx-auto mt-14 max-w-3xl"
        >
          <CodeBlock code={pricingToPhp(config)} title="config/larasaas.php — generated" lang="php" maxHeight={380} />
        </motion.div>

        <div className="mt-14 text-center">
          <a
            href="/#pricing"
            className="inline-flex items-center gap-2 rounded-full bg-fg px-6 py-3 text-sm font-semibold text-bg transition hover:opacity-85"
          >
            <ArrowLeft size={15} className="rtl:rotate-180" />
            Build your own in the playground
          </a>
        </div>
      </main>
    </div>
  );
}
