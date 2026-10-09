"use client";

import { motion, AnimatePresence } from "framer-motion";
import { Info, CheckCircle2, AlertTriangle, OctagonAlert, Sparkles, X, ArrowUpRight } from "lucide-react";

export interface SiteAnnouncement {
  id: number;
  title: string;
  body: string;
  type: string;
  color: string;
  icon: string;
  dismissible: boolean;
  action_label?: string | null;
  action_url?: string | null;
  ends_at?: string | null;
}

const ICONS: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
  info: Info,
  check: CheckCircle2,
  triangle: AlertTriangle,
  octagon: OctagonAlert,
  sparkles: Sparkles,
};

export function AnnouncementStackSite({
  items,
  onDismiss,
}: {
  items: SiteAnnouncement[];
  onDismiss: (id: number) => void;
}) {
  return (
    <div className="space-y-3">
      <AnimatePresence initial={false}>
        {items.map((a) => {
          const Icon = ICONS[a.icon] ?? Info;
          return (
            <motion.div
              key={a.id}
              layout
              initial={{ opacity: 0, y: -14, scale: 0.97 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, x: 40, scale: 0.96 }}
              transition={{ type: "spring", stiffness: 380, damping: 32 }}
              className="glass group relative flex items-start gap-3.5 overflow-hidden rounded-2xl p-4"
            >
              <span className="absolute inset-y-0 start-0 w-[3px]" style={{ background: a.color }} />
              <span
                className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl"
                style={{ background: `${a.color}1f`, color: a.color }}
              >
                <Icon size={15} />
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate text-sm font-bold tracking-tight">{a.title}</p>
                  <span
                    className="rounded-full px-2 py-0.5 font-mono text-[9px] uppercase tracking-widest"
                    style={{ background: `${a.color}1a`, color: a.color }}
                  >
                    {a.type}
                  </span>
                </div>
                <p className="mt-1 text-xs leading-relaxed text-muted">{a.body}</p>
                <div className="mt-2 flex flex-wrap items-center gap-3">
                  {a.action_url && a.action_label && (
                    <a
                      href={a.action_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1 text-[11px] font-bold"
                      style={{ color: a.color }}
                    >
                      {a.action_label}
                      <ArrowUpRight size={11} />
                    </a>
                  )}
                  {a.ends_at && (
                    <span className="font-mono text-[10px] text-muted">
                      ends {new Date(a.ends_at).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
                    </span>
                  )}
                </div>
              </div>
              {a.dismissible && (
                <button
                  onClick={() => onDismiss(a.id)}
                  aria-label="Dismiss announcement"
                  className="grid h-6 w-6 shrink-0 place-items-center rounded-full text-muted opacity-60 transition hover:bg-card-2 hover:opacity-100"
                >
                  <X size={13} />
                </button>
              )}
            </motion.div>
          );
        })}
      </AnimatePresence>
    </div>
  );
}
