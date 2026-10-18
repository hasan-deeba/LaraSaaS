"use client";

import { useEffect, useRef, useState } from "react";
import {
  motion,
  useMotionValue,
  useSpring,
  useTransform,
} from "framer-motion";
import { Copy, Check, ArrowDown, Megaphone, Flag, CreditCard, Sparkles } from "lucide-react";
import { useSite } from "./providers";

const COMMANDS = [
  "composer require hasan-deeba/larasaas",
  "php artisan larasaas:install --migrate",
  "stripe listen --forward-to localhost/larasaas/webhooks/stripe",
  "open http://localhost:8000/app/pricing",
];

const MARQUEE = [
  "Stripe Billing",
  "Laravel Cashier",
  "Config-driven Pricing",
  "Scheduled Announcements",
  "Pennant Feature Flags",
  "React Components",
  "Dark / Light Native",
  "RTL & i18n",
  "Your User Model",
  "MIT Licensed",
];

function useTyped(commands: string[]) {
  const [text, setText] = useState("");
  const [line, setLine] = useState(0);
  const state = useRef({ char: 0, deleting: false });

  useEffect(() => {
    const current = commands[line % commands.length];
    const { char, deleting } = state.current;

    const tick = () => {
      if (!deleting) {
        state.current.char = Math.min(char + 1, current.length);
        setText(current.slice(0, state.current.char));
        if (state.current.char === current.length) {
          state.current.deleting = true;
          return 2200;
        }
        return 34 + Math.random() * 46;
      }

      state.current.char = Math.max(0, char - 4);
      setText(current.slice(0, state.current.char));
      if (state.current.char === 0) {
        state.current.deleting = false;
        setLine((l) => (l + 1) % commands.length);
        return 400;
      }
      return 18;
    };

    const timer = setTimeout(tick, deleting ? 900 : 600);
    return () => clearTimeout(timer);
  }, [text, line, commands]);

  return { text, line };
}

function FloatingCard({
  className,
  delay = 0,
  children,
  x,
}: {
  className?: string;
  delay?: number;
  children: React.ReactNode;
  x?: ReturnType<typeof useSpring>;
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 30, scale: 0.92 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      transition={{ delay, duration: 0.9, ease: [0.22, 1, 0.36, 1] }}
      style={x ? { x } : undefined}
      className={`glass absolute rounded-2xl p-3.5 shadow-2xl ${className ?? ""}`}
    >
      {children}
    </motion.div>
  );
}

export function Hero() {
  const { t } = useSite();
  const { text: typed, line } = useTyped(COMMANDS);
  const [copied, setCopied] = useState(false);

  const mx = useMotionValue(0);
  const my = useMotionValue(0);
  const sx = useSpring(mx, { stiffness: 60, damping: 20 });
  const sy = useSpring(my, { stiffness: 60, damping: 20 });
  const px1 = useTransform(sx, (v) => v * -18);
  const px2 = useTransform(sx, (v) => v * 26);
  const py1 = useTransform(sy, (v) => v * -12);
  const py2 = useTransform(sy, (v) => v * 16);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText("composer require hasan-deeba/larasaas");
    } catch {}
    setCopied(true);
    setTimeout(() => setCopied(false), 1800);
  };

  const wordsA = t("hero.title_a").split(" ");
  const wordsB = t("hero.title_b").split(" ");

  return (
    <section
      className="relative overflow-hidden pb-10 pt-36 md:pt-44"
      onMouseMove={(e) => {
        const r = e.currentTarget.getBoundingClientRect();
        mx.set((e.clientX - r.left) / r.width - 0.5);
        my.set((e.clientY - r.top) / r.height - 0.5);
      }}
    >
      {/* backdrop */}
      <div className="grid-bg pointer-events-none absolute inset-0" />
      <div className="pointer-events-none absolute -top-40 start-1/4 h-[480px] w-[480px] rounded-full bg-accent/15 blur-[130px]" />
      <div className="pointer-events-none absolute top-40 -end-20 h-[380px] w-[380px] rounded-full bg-violet/15 blur-[130px]" />

      <div className="relative mx-auto grid max-w-6xl items-center gap-14 px-5 lg:grid-cols-[1.05fr_0.95fr]">
        {/* copy */}
        <div>
          <motion.span
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            className="eyebrow"
          >
            <span className="dot" />
            {t("hero.kicker")}
          </motion.span>

          <h1 className="mt-6 text-[13.5vw] font-bold leading-[0.98] tracking-[-0.04em] sm:text-6xl md:text-7xl xl:text-[5.2rem]">
            <span className="block">
              {wordsA.map((w, i) => (
                <motion.span
                  key={w + i}
                  className="inline-block"
                  initial={{ opacity: 0, y: 40, rotateX: 60 }}
                  animate={{ opacity: 1, y: 0, rotateX: 0 }}
                  transition={{ delay: 0.1 + i * 0.09, duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
                >
                  {w}&nbsp;
                </motion.span>
              ))}
            </span>
            <span className="text-grad block">
              {wordsB.map((w, i) => (
                <motion.span
                  key={w + i}
                  className="inline-block"
                  initial={{ opacity: 0, y: 40, rotateX: 60 }}
                  animate={{ opacity: 1, y: 0, rotateX: 0 }}
                  transition={{ delay: 0.32 + i * 0.09, duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
                >
                  {w}&nbsp;
                </motion.span>
              ))}
            </span>
          </h1>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5, duration: 0.7 }}
            className="mt-6 max-w-xl text-base leading-relaxed text-muted md:text-lg"
          >
            {t("hero.sub")}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.62, duration: 0.7 }}
            className="mt-8 flex flex-wrap items-center gap-3"
          >
            <button
              onClick={copy}
              className="shine group inline-flex items-center gap-3 rounded-full bg-fg px-5 py-3 font-mono text-sm text-bg transition-transform hover:scale-[1.02] active:scale-95"
              aria-label={t("hero.copy_cmd")}
            >
              <span className="text-accent">$</span>
              composer require hasan-deeba/larasaas
              {copied ? <Check size={15} className="text-emerald-500" /> : <Copy size={15} className="opacity-60 group-hover:opacity-100" />}
            </button>

            <a
              href="#pricing"
              className="inline-flex items-center gap-2 rounded-full border border-line px-5 py-3 text-sm font-semibold text-fg transition hover:bg-card-2"
            >
              {t("hero.explore")}
              <ArrowDown size={15} />
            </a>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.8 }}
            className="mt-10 grid max-w-md grid-cols-2 gap-6 sm:grid-cols-4"
          >
            {[
              ["04", t("hero.stat_modules")],
              ["01", t("hero.stat_command")],
              ["00", t("hero.stat_rewrites")],
              ["MIT", t("hero.stat_license")],
            ].map(([num, label]) => (
              <div key={label}>
                <p className="font-mono text-2xl font-bold tracking-tight text-fg">{num}</p>
                <p className="mt-1 text-[11px] leading-tight text-muted">{label}</p>
              </div>
            ))}
          </motion.div>
        </div>

        {/* terminal + floating module cards */}
        <div className="relative hidden h-[480px] lg:block" style={{ perspective: 1200 }}>
          <motion.div
            initial={{ opacity: 0, y: 40, rotateY: -8 }}
            animate={{ opacity: 1, y: 0, rotateY: 0 }}
            transition={{ delay: 0.35, duration: 1, ease: [0.22, 1, 0.36, 1] }}
            style={{ x: px1, y: py1 }}
            className="code-shell absolute start-0 top-6 w-[92%]"
            dir="ltr"
          >
            <div className="flex items-center justify-between border-b border-white/[0.06] px-4 py-3">
              <span className="flex gap-1.5">
                <i className="h-2.5 w-2.5 rounded-full bg-[#ff5f57]" />
                <i className="h-2.5 w-2.5 rounded-full bg-[#febc2e]" />
                <i className="h-2.5 w-2.5 rounded-full bg-[#28c840]" />
              </span>
              <span className="font-mono text-[11px] text-zinc-500">your-laravel-app — zsh</span>
            </div>
            <div className="code-body min-h-[170px] space-y-2.5 p-5">
              {COMMANDS.map((cmd, i) => (
                <div key={cmd} className="min-h-[1.7em] whitespace-pre-wrap">
                  {line > i && (
                    <>
                      <span className="text-accent">$ </span>
                      <span className="text-zinc-300">{cmd}</span>
                    </>
                  )}
                  {line === i && (
                    <>
                      <span className="text-accent">$ </span>
                      {typed}
                      <span className="term-caret" />
                    </>
                  )}
                </div>
              ))}
              <div className="pt-1 text-[11px] text-emerald-400">
                {line >= 1 && "✓ LaraSaaS ready — billing · pricing · announcements · flags"}
              </div>
            </div>
          </motion.div>

          <FloatingCard className="end-0 top-0 w-52" delay={0.7} x={px2}>
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-semibold uppercase tracking-widest text-muted">Growth</span>
              <span className="rounded-full bg-accent px-2 py-0.5 text-[9px] font-bold text-white">POPULAR</span>
            </div>
            <p className="mt-2 text-2xl font-bold tracking-tight">$29<span className="text-xs font-normal text-muted">/mo</span></p>
            {/* fake shimmer bar */}
            <div className="mt-3 h-1 rounded-full bg-gradient-to-r from-accent to-accent-2" />
            <p className="mt-2 flex items-center gap-1.5 text-[10px] text-muted"><CreditCard size={11} /> Stripe · Cashier</p>
          </FloatingCard>

          <FloatingCard className="bottom-24 end-4 w-60" delay={0.9} x={px1}>
            <div className="flex items-start gap-2.5" dir="ltr">
              <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-violet/15 text-violet">
                <Megaphone size={12} />
              </span>
              <div>
                <p className="text-xs font-semibold text-fg">v3.0 just shipped</p>
                <p className="text-[10px] leading-snug text-muted">Pennant flags, RTL polish & a brand new portal.</p>
              </div>
              <span className="ms-auto h-1.5 w-1.5 shrink-0 rounded-full bg-violet" />
            </div>
          </FloatingCard>

          <FloatingCard className="bottom-2 start-6 w-56" delay={1.1} x={px2}>
            <div className="flex items-center justify-between" dir="ltr">
              <span className="font-mono text-[10px] text-muted">beta-reports</span>
              <span className="flex h-4.5 w-8 items-center rounded-full bg-emerald-500/90 p-0.5">
                <span className="ms-auto h-3.5 w-3.5 rounded-full bg-white" />
              </span>
            </div>
            <div className="mt-2 flex items-center justify-between" dir="ltr">
              <span className="font-mono text-[10px] text-muted">ai-assistant</span>
              <span className="flex h-4.5 w-8 items-center rounded-full bg-zinc-600/60 p-0.5">
                <span className="h-3.5 w-3.5 rounded-full bg-white/90" />
              </span>
            </div>
            <p className="mt-2 flex items-center gap-1.5 text-[10px] text-muted">
              <Flag size={10} className="text-accent-2" /> workspace · Acme Corp
            </p>
          </FloatingCard>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 1.3 }}
            className="absolute -bottom-8 end-10 flex items-center gap-2 text-[11px] text-muted"
          >
            <Sparkles size={12} className="text-accent" />
            React components · inherit your theme
          </motion.div>
        </div>
      </div>

      {/* marquee */}
      <div className="relative mt-20 border-y border-line py-4" dir="ltr">
        <div className="flex overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_12%,black_88%,transparent)]">
          <div className="marquee-track flex shrink-0 items-center gap-10 pe-10">
            {[...MARQUEE, ...MARQUEE].map((item, i) => (
              <span key={i} className="flex items-center gap-10 whitespace-nowrap font-mono text-xs uppercase tracking-[0.2em] text-muted">
                {item}
                <span className="h-1 w-1 rounded-full bg-accent" />
              </span>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
