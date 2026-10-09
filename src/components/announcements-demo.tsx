"use client";

import { useEffect, useState } from "react";
import { motion } from "framer-motion";
import { Info, CheckCircle2, AlertTriangle, OctagonAlert, Sparkles, Radio, Loader2, Database } from "lucide-react";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";
import { AnnouncementStackSite, type SiteAnnouncement } from "./larasaas/announcements";
import { ANNOUNCEMENT_TYPES } from "@/lib/larasaas";

const TYPE_ICONS: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
  info: Info,
  success: CheckCircle2,
  warning: AlertTriangle,
  danger: OctagonAlert,
  promo: Sparkles,
};

export function AnnouncementsDemo() {
  const { t } = useSite();
  const [items, setItems] = useState<SiteAnnouncement[]>([]);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState<string | null>(null);

  const load = async () => {
    try {
      const res = await fetch("/api/larasaas-demo/announcements", { cache: "no-store" });
      const json = (await res.json()) as { data: SiteAnnouncement[] };
      setItems(json.data);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const broadcast = async (type: string) => {
    setSending(type);
    try {
      await fetch("/api/larasaas-demo/announcements", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ type }),
      });
      await load();
    } finally {
      setSending(null);
    }
  };

  const dismiss = async (id: number) => {
    setItems((current) => current.filter((item) => item.id !== id));
    await fetch(`/api/larasaas-demo/announcements/${id}/dismiss`, { method: "POST" });
  };

  return (
    <section id="announcements" className="relative py-28 md:py-36">
      <div className="pointer-events-none absolute start-0 top-1/3 h-[420px] w-[420px] rounded-full bg-violet/10 blur-[130px]" />
      <div className="relative mx-auto max-w-6xl px-5">
        <SectionHeading kicker="ann.kicker" title="ann.title" sub="ann.sub" />

        <div className="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
          {/* console */}
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="ring-line h-fit rounded-3xl bg-card p-6"
          >
            <div className="mb-5 flex items-center justify-between">
              <span className="flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-muted">
                <Radio size={14} className="text-accent" />
                {t("ann.console")}
              </span>
              <span className="flex items-center gap-1.5 rounded-full bg-card-2 px-2.5 py-1 font-mono text-[10px] text-muted">
                <Database size={10} />
                postgres · larasaas_announcements
              </span>
            </div>

            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
              {Object.entries(ANNOUNCEMENT_TYPES).map(([type, meta]) => {
                const Icon = TYPE_ICONS[type] ?? Info;
                return (
                  <button
                    key={type}
                    onClick={() => broadcast(type)}
                    disabled={sending !== null}
                    className="group flex flex-col items-start gap-2 rounded-2xl border border-line bg-card-2/50 p-3.5 text-start transition hover:-translate-y-0.5 disabled:opacity-50"
                  >
                    <span
                      className="grid h-8 w-8 place-items-center rounded-xl transition group-hover:scale-110"
                      style={{ background: `${meta.color}1f`, color: meta.color }}
                    >
                      {sending === type ? <Loader2 size={14} className="animate-spin" /> : <Icon size={14} />}
                    </span>
                    <span className="font-mono text-[11px] font-semibold uppercase tracking-wider text-fg/80">
                      .{type}
                    </span>
                  </button>
                );
              })}
              <div className="col-span-2 sm:col-span-1" />
            </div>

            <button
              onClick={() => broadcast("random")}
              disabled={sending !== null}
              className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full bg-fg py-3 text-sm font-semibold text-bg transition hover:opacity-85 disabled:opacity-50"
            >
              {sending ? <Loader2 size={15} className="animate-spin" /> : <Radio size={15} />}
              Surprise broadcast
            </button>

            <p className="mt-4 text-[11px] leading-relaxed text-muted">{t("ann.persisted")}</p>
          </motion.div>

          {/* live stack */}
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.08 }}
          >
            <div className="mb-4 flex items-center justify-between">
              <span className="flex items-center gap-2 text-sm font-semibold">
                <span className="pulse-dot h-2 w-2 rounded-full bg-emerald-500" />
                {items.length} {t("ann.live")}
              </span>
              <span className="font-mono text-[10px] uppercase tracking-widest text-muted">GET /api/…/announcements</span>
            </div>

            {loading ? (
              <div className="space-y-3">
                {[0, 1, 2].map((i) => (
                  <div key={i} className="glass h-20 animate-pulse rounded-2xl" />
                ))}
              </div>
            ) : items.length ? (
              <AnnouncementStackSite items={items} onDismiss={dismiss} />
            ) : (
              <div className="glass grid h-40 place-items-center rounded-2xl text-sm text-muted">
                {t("ann.empty")}
              </div>
            )}
          </motion.div>
        </div>
      </div>
    </section>
  );
}
