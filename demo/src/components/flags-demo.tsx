"use client";

import { useMemo, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Flag, Sparkles, LayoutDashboard, BarChart3, Building2, Bot, Lock, ToggleLeft, ToggleRight } from "lucide-react";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";

interface FlagDef {
  name: string;
  strategy: string;
  description: string;
  workspaces: number[];
  icon: React.ComponentType<{ size?: number; className?: string }>;
  color: string;
}

const FLAGS: FlagDef[] = [
  {
    name: "new-dashboard",
    strategy: "['percentage' => 50]",
    description: "Stable-bucket rollout to half of all users.",
    workspaces: [],
    icon: LayoutDashboard,
    color: "#3b82f6",
  },
  {
    name: "ai-assistant",
    strategy: "['workspaces' => [1]]",
    description: "Targeted: only workspace id 1 (Acme Corp).",
    workspaces: [1],
    icon: Bot,
    color: "#a855f7",
  },
  {
    name: "beta-reports",
    strategy: "['enabled' => false]",
    description: "Off by default — flip it for everyone or a scope.",
    workspaces: [],
    icon: BarChart3,
    color: "#f59e0b",
  },
];

const WORKSPACES = [
  { id: 1, name: "Acme Corp" },
  { id: 2, name: "Globex Inc" },
];

type Activation = { everyone: boolean; workspaces: number[] };

export function FlagsDemo() {
  const { t } = useSite();
  const [workspaceId, setWorkspaceId] = useState(1);
  const [state, setState] = useState<Record<string, Activation>>({
    "new-dashboard": { everyone: true, workspaces: [] },
    "ai-assistant": { everyone: false, workspaces: [] },
    "beta-reports": { everyone: false, workspaces: [] },
  });

  const active = (flag: FlagDef): boolean => {
    const act = state[flag.name];
    return act.everyone || flag.workspaces.includes(workspaceId) || act.workspaces.includes(workspaceId);
  };

  const toggleEveryone = (flag: string) =>
    setState((s) => ({ ...s, [flag]: { ...s[flag], everyone: !s[flag].everyone } }));

  const toggleWorkspace = (flag: string) =>
    setState((s) => {
      const current = s[flag].workspaces;
      const next = current.includes(workspaceId)
        ? current.filter((id) => id !== workspaceId)
        : [...current, workspaceId];
      return { ...s, [flag]: { ...s[flag], workspaces: next } };
    });

  const workspaceName = useMemo(
    () => WORKSPACES.find((w) => w.id === workspaceId)?.name ?? "",
    [workspaceId],
  );

  return (
    <section id="flags" className="relative py-28 md:py-36">
      <div className="mx-auto max-w-6xl px-5">
        <SectionHeading kicker="flags.kicker" title="flags.title" sub="flags.sub" />

        {/* workspace scope selector */}
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          className="mb-8 flex flex-wrap items-center justify-center gap-3"
        >
          <span className="flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-muted">
            <Building2 size={13} className="text-accent-2" />
            {t("flags.workspace")}
          </span>
          {WORKSPACES.map((w) => (
            <button
              key={w.id}
              onClick={() => setWorkspaceId(w.id)}
              className={`rounded-full px-4 py-2 font-mono text-xs font-semibold transition ${
                workspaceId === w.id ? "bg-fg text-bg" : "border border-line text-muted hover:text-fg"
              }`}
            >
              #{w.id} {w.name}
            </button>
          ))}
        </motion.div>

        <div className="grid gap-6 lg:grid-cols-2">
          {/* toggles */}
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="space-y-3"
          >
            {FLAGS.map((flag) => {
              const Icon = flag.icon;
              const on = active(flag);
              return (
                <div
                  key={flag.name}
                  className={`ring-line rounded-2xl border bg-card p-5 transition ${on ? "border-line" : "opacity-90"}`}
                >
                  <div className="flex items-start gap-4">
                    <span
                      className="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
                      style={{ background: `${flag.color}1f`, color: flag.color }}
                    >
                      <Icon size={17} />
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2" dir="ltr">
                        <code className="font-mono text-sm font-bold">{flag.name}</code>
                        <span
                          className={`rounded-full px-2 py-0.5 font-mono text-[9px] font-bold uppercase tracking-widest ${
                            on ? "bg-emerald-500/15 text-emerald-500" : "bg-card-2 text-muted"
                          }`}
                        >
                          {on ? "active" : "inactive"}
                        </span>
                      </div>
                      <p className="mt-1 text-xs text-muted">{flag.description}</p>
                      <code className="mt-2 block truncate font-mono text-[10px] text-muted/70" dir="ltr">
                        {flag.strategy}
                      </code>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1.5">
                      <button
                        onClick={() => toggleEveryone(flag.name)}
                        className="flex items-center gap-1.5 text-[11px] font-semibold text-fg transition hover:opacity-70"
                      >
                        {state[flag.name].everyone ? (
                          <ToggleRight size={22} className="text-emerald-500" />
                        ) : (
                          <ToggleLeft size={22} className="text-muted" />
                        )}
                        everyone
                      </button>
                      <button
                        onClick={() => toggleWorkspace(flag.name)}
                        className="flex items-center gap-1.5 text-[11px] font-semibold text-fg transition hover:opacity-70"
                      >
                        {state[flag.name].workspaces.includes(workspaceId) ? (
                          <ToggleRight size={22} className="text-violet" />
                        ) : (
                          <ToggleLeft size={22} className="text-muted" />
                        )}
                        this ws
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}

            <div className="rounded-2xl border border-dashed border-line p-4 font-mono text-[11px] leading-relaxed text-muted" dir="ltr">
              <span className="text-fg/70">{t("flags.scope")}:</span> Feature::for([$user,{" "}
              <span className="text-accent-2">$workspace = "#{workspaceId} {workspaceName}"</span>])
            </div>
          </motion.div>

          {/* preview pane */}
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.08 }}
            className="ring-line relative min-h-[380px] overflow-hidden rounded-3xl bg-card p-5"
          >
            <div className="mb-4 flex items-center justify-between">
              <span className="flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-muted">
                <Flag size={13} className="text-accent" />
                your app · live preview
              </span>
              <span className="rounded-full bg-card-2 px-2.5 py-1 font-mono text-[10px] text-muted">{workspaceName}</span>
            </div>

            <div className="grid gap-3">
              <AnimatePresence mode="popLayout">
                {active(FLAGS[0]) && (
                  <motion.div
                    key="dash"
                    layout
                    initial={{ opacity: 0, scale: 0.94, y: 14 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.94, y: -14 }}
                    transition={{ type: "spring", stiffness: 320, damping: 28 }}
                    className="glass rounded-2xl p-4"
                  >
                    <div className="mb-3 flex items-center justify-between">
                      <span className="flex items-center gap-2 text-xs font-bold">
                        <LayoutDashboard size={13} className="text-blue-500" />
                        New Dashboard
                      </span>
                      <span className="rounded-full bg-blue-500/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest text-blue-500">50% bucket</span>
                    </div>
                    <div className="flex items-end gap-1.5" dir="ltr">
                      {[38, 62, 45, 80, 56, 92, 70, 100, 64, 84].map((h, i) => (
                        <motion.span
                          key={i}
                          initial={{ height: 4 }}
                          animate={{ height: h / 2.2 }}
                          transition={{ delay: 0.05 * i, type: "spring", stiffness: 200, damping: 18 }}
                          className="w-full rounded-t-md bg-gradient-to-t from-blue-500/40 to-blue-400"
                        />
                      ))}
                    </div>
                  </motion.div>
                )}

                {active(FLAGS[1]) && (
                  <motion.div
                    key="ai"
                    layout
                    initial={{ opacity: 0, scale: 0.94, y: 14 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.94, y: -14 }}
                    transition={{ type: "spring", stiffness: 320, damping: 28 }}
                    className="glass rounded-2xl p-4"
                  >
                    <span className="flex items-center gap-2 text-xs font-bold">
                      <Sparkles size={13} className="text-violet" />
                      AI Assistant
                    </span>
                    <div className="mt-3 max-w-[85%] rounded-2xl rounded-ss-md bg-violet/15 p-3 text-[11px] leading-relaxed text-fg/90">
                      Your Growth plan renews in 4 days. Want me to prep the upgrade nudge for the 3 seats on trial?
                    </div>
                    <div className="mt-2 ms-auto w-fit rounded-full border border-line px-3 py-1 text-[10px] text-muted">Ask anything…</div>
                  </motion.div>
                )}

                {active(FLAGS[2]) && (
                  <motion.div
                    key="reports"
                    layout
                    initial={{ opacity: 0, scale: 0.94, y: 14 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.94, y: -14 }}
                    transition={{ type: "spring", stiffness: 320, damping: 28 }}
                    className="glass rounded-2xl p-4"
                  >
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-2 text-xs font-bold">
                        <BarChart3 size={13} className="text-amber-500" />
                        Beta Reports
                      </span>
                      <span className="font-mono text-lg font-bold text-amber-500">+18.2%</span>
                    </div>
                    <div className="mt-2 flex items-center gap-2 text-[10px] text-muted">
                      <span className="rounded-full bg-amber-500/15 px-2 py-0.5 font-mono text-amber-500">MRR</span>
                      workspace-scoped export shipped
                    </div>
                  </motion.div>
                )}

                {!FLAGS.some(active) && (
                  <motion.div
                    key="empty"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    className="grid h-48 place-items-center rounded-2xl border border-dashed border-line text-muted"
                  >
                    <span className="flex flex-col items-center gap-2 text-xs">
                      <Lock size={18} />
                      every flag is off for this scope
                    </span>
                  </motion.div>
                )}
              </AnimatePresence>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
