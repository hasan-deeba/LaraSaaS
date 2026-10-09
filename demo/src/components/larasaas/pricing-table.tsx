"use client";

import { useMemo, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Check, ArrowRight } from "lucide-react";
import { CURRENCIES, type DemoPricing } from "@/lib/larasaas";
import { useSite } from "../providers";

type Viewer = "visitor" | "member";

interface Props {
  config: DemoPricing;
  viewer?: Viewer;
  currentPlanKey?: string;
}

/** Live demo of the package's <PricingTable /> — rendered with the site theme. */
export function PricingTableDemo({ config, viewer = "visitor", currentPlanKey = "growth" }: Props) {
  const { t } = useSite();
  const [interval, setIntervalKey] = useState<"monthly" | "yearly">("monthly");
  const currency = CURRENCIES[config.currency] ?? CURRENCIES.usd;
  const isMember = viewer === "member";

  const plans = useMemo(() => config.plans, [config.plans]);

  const price = (planKey: { monthly: number | null; yearly: number | null }) => {
    const usd = interval === "monthly" ? planKey.monthly : planKey.yearly;
    if (usd === null) return null;
    return currency.round(usd * currency.rateFromUsd);
  };

  const monthlyEquivalent = (yearly: number | null) =>
    yearly === null ? null : (yearly * currency.rateFromUsd) / 12;

  return (
    <div>
      {/* interval toggle */}
      <div className="mb-10 flex justify-center">
        <div className="relative flex items-center gap-1 rounded-full border border-line bg-card p-1">
          {(["monthly", "yearly"] as const).map((value) => (
            <button
              key={value}
              onClick={() => setIntervalKey(value)}
              className={`relative z-10 flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold transition-colors ${
                interval === value ? "text-fg" : "text-muted hover:text-fg"
              }`}
            >
              {interval === value && (
                <motion.span
                  layoutId="interval-pill"
                  className="absolute inset-0 -z-10 rounded-full bg-card-2 shadow [box-shadow:0_2px_12px_rgba(0,0,0,0.15)]"
                  transition={{ type: "spring", stiffness: 500, damping: 40 }}
                />
              )}
              {t(`pricing.${value}`)}
              {value === "yearly" && (
                <span className="rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-bold text-accent">
                  {t("pricing.save_badge")}
                </span>
              )}
            </button>
          ))}
        </div>
      </div>

      <div className="grid gap-5 md:grid-cols-3">
        {plans.map((plan, i) => {
          const amount = price(plan);
          const equivalent = interval === "yearly" ? monthlyEquivalent(plan.yearly) : null;
          const current = isMember && plan.key === currentPlanKey;

          return (
            <motion.article
              key={plan.key + plan.name}
              initial={{ opacity: 0, y: 32 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: "-60px" }}
              transition={{ delay: i * 0.08, duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
              whileHover={{ y: -6 }}
              className={`relative flex flex-col rounded-3xl p-[1.5px] ${
                plan.highlight
                  ? "bg-gradient-to-b from-accent via-accent/30 to-violet shadow-[0_30px_80px_-30px_var(--glow)]"
                  : "bg-line"
              }`}
            >
              {plan.badge && (
                <span className="absolute -top-3.5 end-6 z-10 rounded-full bg-gradient-to-r from-accent to-accent-2 px-3.5 py-1 text-[10px] font-bold uppercase tracking-widest text-white shadow-lg">
                  {plan.badge}
                </span>
              )}

              <div className={`flex h-full flex-col rounded-[calc(theme(borderRadius.3xl)-1.5px)] bg-card p-7 ${plan.highlight ? "bg-card/95 backdrop-blur-xl" : ""}`}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="text-lg font-bold tracking-tight">{plan.name}</h3>
                    {plan.description && <p className="mt-1 text-xs leading-relaxed text-muted">{plan.description}</p>}
                  </div>
                  {current && (
                    <span className="rounded-full border border-line px-2.5 py-1 font-mono text-[9px] uppercase tracking-widest text-muted">
                      you
                    </span>
                  )}
                </div>

                <div className="mt-6 flex items-end gap-1.5">
                  <span className="mb-1.5 text-lg font-semibold text-muted">{currency.symbol}</span>
                  <AnimatePresence mode="popLayout" initial={false}>
                    <motion.span
                      key={`${interval}-${config.currency}-${amount}`}
                      initial={{ opacity: 0, y: 14, filter: "blur(4px)" }}
                      animate={{ opacity: 1, y: 0, filter: "blur(0px)" }}
                      exit={{ opacity: 0, y: -14, filter: "blur(4px)" }}
                      transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
                      className="text-5xl font-bold tabular-nums tracking-tighter"
                    >
                      {amount ?? "—"}
                    </motion.span>
                  </AnimatePresence>
                  <span className="mb-1.5 text-sm text-muted">
                    /{interval === "monthly" ? t("pricing.per_month") : t("pricing.per_year")}
                  </span>
                </div>

                {equivalent !== null && (
                  <p className="mt-1 text-[11px] text-muted">
                    {currency.symbol}
                    {equivalent.toLocaleString(undefined, { maximumFractionDigits: 1 })}/{t("pricing.per_month")} ·{" "}
                    {t("pricing.billed_yearly")}
                  </p>
                )}

                {plan.trial ? (
                  <p className="mt-3 inline-flex w-fit items-center gap-1.5 rounded-full bg-accent/10 px-3 py-1 text-[11px] font-semibold text-accent">
                    <span className="h-1 w-1 rounded-full bg-accent" />
                    {t("pricing.trial").replace("{days}", String(plan.trial))}
                  </p>
                ) : null}

                <ul className="mt-6 flex-1 space-y-2.5">
                  {plan.features.map((feature) => (
                    <li key={feature} className="flex items-start gap-2.5 text-sm">
                      <span className={`mt-0.5 grid h-4.5 w-4.5 shrink-0 place-items-center rounded-full ${plan.highlight ? "bg-accent/15 text-accent" : "bg-card-2 text-muted"}`}>
                        <Check size={10} strokeWidth={3} />
                      </span>
                      <span className="text-fg/90">{feature}</span>
                    </li>
                  ))}
                </ul>

                <button
                  className={`group mt-7 inline-flex w-full items-center justify-center gap-2 rounded-full py-3 text-sm font-semibold transition-transform hover:scale-[1.02] active:scale-95 ${
                    current
                      ? "cursor-default border border-dashed border-line text-muted"
                      : plan.highlight
                        ? "bg-gradient-to-r from-accent to-accent-2 text-white shadow-[0_14px_32px_-12px_var(--accent)]"
                        : "bg-fg text-bg"
                  }`}
                >
                  {current ? (
                    t("pricing.current")
                  ) : (
                    <>
                      {isMember ? t("pricing.switch") : t("pricing.start")}
                      <ArrowRight size={14} className="transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" />
                    </>
                  )}
                </button>
              </div>
            </motion.article>
          );
        })}
      </div>
    </div>
  );
}
