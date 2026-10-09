"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import { Check, Copy, PartyPopper } from "lucide-react";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";
import { Terminal } from "./code";
import { GithubIcon } from "./nav";

const STEPS = [
  {
    num: "01",
    title: "Require",
    lines: ["composer require larasaas/larasaas"],
    note: "install.note1",
  },
  {
    num: "02",
    title: "Install",
    lines: ["php artisan larasaas:install --migrate"],
    note: "install.note2",
  },
  {
    num: "03",
    title: "Stripe keys",
    lines: ["STRIPE_KEY=pk_test_…  STRIPE_SECRET=sk_test_…", "STRIPE_PRICE_GROWTH_MONTHLY=price_…"],
    note: "install.note3",
  },
  {
    num: "04",
    title: "Ship it",
    lines: ["php artisan serve", "# → http://localhost:8000/app/pricing"],
    note: "install.note4",
  },
];

export function Install() {
  const { t } = useSite();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText("composer require larasaas/larasaas");
    } catch {}
    setCopied(true);
    setTimeout(() => setCopied(false), 1800);
  };

  return (
    <section id="install" className="relative py-28 md:py-36">
      <div className="mx-auto max-w-6xl px-5">
        <SectionHeading kicker="install.kicker" title="install.title" align="center" />

        <div className="grid gap-5 md:grid-cols-2">
          {STEPS.map((step, i) => (
            <motion.div
              key={step.num}
              initial={{ opacity: 0, y: 28 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: "-60px" }}
              transition={{ delay: i * 0.08, duration: 0.65, ease: [0.22, 1, 0.36, 1] }}
              className="group relative"
            >
              <div className="mb-3 flex items-baseline gap-3">
                <span className="text-grad font-mono text-4xl font-bold">{step.num}</span>
                <span className="text-xl font-bold tracking-tight">{step.title}</span>
              </div>
              <Terminal lines={step.lines} title={`step ${step.num}`} />
              <p className="mt-3 text-xs leading-relaxed text-muted">{t(step.note)}</p>
            </motion.div>
          ))}
        </div>

        {/* final CTA */}
        <motion.div
          initial={{ opacity: 0, y: 32 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
          className="relative mt-20 overflow-hidden rounded-[2.5rem] bg-fg px-6 py-16 text-center text-bg md:py-24"
        >
          <div className="pointer-events-none absolute -top-24 start-1/4 h-72 w-72 rounded-full bg-accent/30 blur-[100px]" />
          <div className="pointer-events-none absolute -bottom-24 end-1/4 h-72 w-72 rounded-full bg-violet/30 blur-[100px]" />

          <div className="relative">
            <span className="inline-flex items-center gap-2 rounded-full border border-bg/20 px-4 py-1.5 font-mono text-[11px] uppercase tracking-widest opacity-80">
              <PartyPopper size={12} className="text-accent-2" />
              {t("install.final_a")}
            </span>
            <h3 className="mx-auto mt-6 max-w-3xl text-4xl font-bold leading-[1.02] tracking-tight md:text-6xl">
              {t("install.final_b")}
            </h3>

            <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
              <button
                onClick={copy}
                className="shine inline-flex items-center gap-3 rounded-full bg-bg px-6 py-3.5 font-mono text-sm text-fg transition-transform hover:scale-[1.03] active:scale-95"
              >
                <span className="text-accent">$</span>
                composer require larasaas/larasaas
                {copied ? <Check size={15} className="text-emerald-500" /> : <Copy size={15} className="opacity-60" />}
              </button>
              <a
                href="https://github.com"
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2.5 rounded-full border border-bg/25 px-6 py-3.5 text-sm font-semibold transition hover:bg-bg/10"
              >
                <GithubIcon size={16} />
                larasaas/larasaas
              </a>
            </div>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
