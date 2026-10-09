"use client";

import { motion } from "framer-motion";
import { Check, KeyRound, User, Shield, Layers, CreditCard, Table2, Megaphone, Flag, ArrowRight } from "lucide-react";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";
import { CodeBlock } from "./code";

const HOST_CHIPS = [
  { icon: User, label: "App\\Models\\User" },
  { icon: KeyRound, label: "Your login & register" },
  { icon: Shield, label: "Your guard · session" },
  { icon: Layers, label: "Your middleware" },
];

const KIT_CHIPS = [
  { icon: CreditCard, label: "Stripe Billing", color: "#635bff" },
  { icon: Table2, label: "Pricing Table", color: "#ff2d20" },
  { icon: Megaphone, label: "Announcements", color: "#a855f7" },
  { icon: Flag, label: "Feature Flags", color: "#f59e0b" },
];

const SNIPPET = `'user_model' => App\\Models\\User::class,   // ← yours
// 'user_model' => App\\Models\\Member::class,  // or anything

'billing' => [
    'billable' => 'workspace',                  // or 'user'
    'workspace_resolver' => fn ($user) => $user->currentWorkspace(),
],`;

export function Integration() {
  const { t } = useSite();

  return (
    <section id="integration" className="relative py-28 md:py-36">
      <div className="mx-auto max-w-6xl px-5">
        <SectionHeading kicker="integration.kicker" title="integration.title" sub="integration.sub" />

        <div className="grid items-center gap-10 lg:grid-cols-2">
          {/* checklist + config */}
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="space-y-4"
          >
            {[1, 2, 3].map((n) => (
              <motion.div
                key={n}
                initial={{ opacity: 0, x: -18 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ delay: n * 0.08 }}
                className="flex items-start gap-3.5"
              >
                <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-accent/15 text-accent">
                  <Check size={12} strokeWidth={3} />
                </span>
                <p className="text-sm leading-relaxed text-fg/90">{t(`integration.point${n}`)}</p>
              </motion.div>
            ))}

            <div className="pt-4">
              <CodeBlock code={SNIPPET} title="config/larasaas.php — excerpt" lang="php" />
            </div>
          </motion.div>

          {/* animated bridge diagram */}
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.1 }}
            className="relative grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-5"
          >
            {/* host card */}
            <div className="ring-line rounded-3xl bg-card p-5">
              <p className="mb-1 font-mono text-[10px] uppercase tracking-widest text-muted">{t("integration.host")}</p>
              <p className="mb-4 text-sm font-bold">routes/web.php · auth</p>
              <div className="space-y-2">
                {HOST_CHIPS.map((chip, i) => (
                  <motion.div
                    key={chip.label}
                    initial={{ opacity: 0, x: -14 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    viewport={{ once: true }}
                    transition={{ delay: 0.15 * i }}
                    className="flex items-center gap-2.5 rounded-xl border border-line bg-card-2/60 px-3 py-2"
                    dir="ltr"
                  >
                    <chip.icon size={13} className="shrink-0 text-fg/70" />
                    <span className="truncate font-mono text-[11px] text-fg/80">{chip.label}</span>
                  </motion.div>
                ))}
              </div>
            </div>

            {/* bridge */}
            <div className="relative flex h-40 w-10 items-center sm:w-14" dir="ltr">
              <div className="h-px w-full bg-gradient-to-r from-fg/30 via-accent to-fg/30 [background-size:200%_100%]" />
              <motion.span
                className="absolute h-2 w-2 rounded-full bg-accent shadow-[0_0_12px_2px_var(--accent)]"
                animate={{ left: ["0%", "85%", "0%"] }}
                transition={{ duration: 3.2, repeat: Infinity, ease: "easeInOut" }}
              />
              <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                <span className="glass grid h-8 w-8 place-items-center rounded-full">
                  <ArrowRight size={13} />
                </span>
              </span>
            </div>

            {/* kit card */}
            <div className="relative rounded-3xl bg-gradient-to-b from-accent/50 via-line to-line p-[1.5px]">
              <div className="rounded-[calc(theme(borderRadius.3xl)-1.5px)] bg-card p-5">
                <p className="mb-1 font-mono text-[10px] uppercase tracking-widest text-muted">composer package</p>
                <p className="mb-4 text-sm font-bold">
                  Lara<span className="text-grad">SaaS</span>
                </p>
                <div className="space-y-2">
                  {KIT_CHIPS.map((chip, i) => (
                    <motion.div
                      key={chip.label}
                      initial={{ opacity: 0, x: 14 }}
                      whileInView={{ opacity: 1, x: 0 }}
                      viewport={{ once: true }}
                      transition={{ delay: 0.35 + 0.13 * i }}
                      animate={{ borderColor: ["var(--line)", chip.color + "66", "var(--line)"] }}
                      className="flex items-center gap-2.5 rounded-xl border bg-card-2/60 px-3 py-2"
                      style={{ borderColor: "var(--line)" }}
                    >
                      <motion.span
                        className="grid h-6 w-6 shrink-0 place-items-center rounded-lg"
                        animate={{ backgroundColor: [chip.color + "14", chip.color + "33", chip.color + "14"] }}
                        transition={{ duration: 2.6, repeat: Infinity, delay: i * 0.7 }}
                      >
                        <chip.icon size={12} style={{ color: chip.color }} />
                      </motion.span>
                      <span className="truncate text-[11px] font-semibold text-fg/80">{chip.label}</span>
                    </motion.div>
                  ))}
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
