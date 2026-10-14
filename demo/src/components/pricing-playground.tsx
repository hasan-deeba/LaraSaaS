"use client";

import { useMemo, useState } from "react";
import { motion } from "framer-motion";
import { Save, Loader2, Check, Braces, FileCode2, Sun, Moon } from "lucide-react";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";
import { PricingTableDemo } from "./larasaas/pricing-table";
import { CodeBlock, CopyButton } from "./code";
import { DEFAULT_PRICING, pricingToPhp, type DemoPricing } from "@/lib/larasaas";

const CURRENCY_CHOICES = ["usd", "eur", "sar"] as const;

export function PricingPlayground() {
  const { t, theme } = useSite();
  const [config, setConfig] = useState<DemoPricing>(DEFAULT_PRICING);
  const [editor, setEditor] = useState(() => JSON.stringify(DEFAULT_PRICING, null, 2));
  const [editorOk, setEditorOk] = useState(true);
  const [viewer, setViewer] = useState<"visitor" | "member">("visitor");
  const [saving, setSaving] = useState(false);
  const [shareUrl, setShareUrl] = useState<string | null>(null);

  const php = useMemo(() => pricingToPhp(config), [config]);

  const applyEditor = (value: string) => {
    setEditor(value);
    try {
      const parsed = JSON.parse(value);
      if (!Array.isArray(parsed.plans) || parsed.plans.some((p: unknown) => typeof p !== "object" || p === null || !(p as { key?: string }).key)) {
        throw new Error("shape");
      }
      setConfig(parsed as DemoPricing);
      setEditorOk(true);
    } catch {
      setEditorOk(false);
    }
  };

  const setCurrency = (currency: (typeof CURRENCY_CHOICES)[number]) => {
    const next = { ...config, currency };
    setConfig(next);
    setEditor(JSON.stringify(next, null, 2));
    setEditorOk(true);
  };

  const save = async () => {
    setSaving(true);
    setShareUrl(null);
    try {
      const res = await fetch("/api/presets", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ config }),
      });
      const json = (await res.json()) as { slug?: string };
      if (json.slug) setShareUrl(`/p/${json.slug}`);
    } finally {
      setSaving(false);
    }
  };

  return (
    <section id="pricing" className="relative py-28 md:py-36">
      <div className="mx-auto max-w-6xl px-5">
        <SectionHeading kicker="pricing.kicker" title="pricing.title" sub="pricing.sub" />

        {/* control bar */}
        <motion.div
          initial={{ opacity: 0, y: 18 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          className="mb-8 flex flex-wrap items-center justify-center gap-3"
        >
          <div className="flex items-center gap-1 rounded-full border border-line bg-card p-1">
            {CURRENCY_CHOICES.map((c) => (
              <button
                key={c}
                onClick={() => setCurrency(c)}
                className={`rounded-full px-4 py-1.5 font-mono text-xs font-semibold uppercase transition ${
                  config.currency === c ? "bg-fg text-bg" : "text-muted hover:text-fg"
                }`}
              >
                {c}
              </button>
            ))}
          </div>

          <div className="flex items-center gap-1 rounded-full border border-line bg-card p-1">
            {(["visitor", "member"] as const).map((v) => (
              <button
                key={v}
                onClick={() => setViewer(v)}
                className={`rounded-full px-4 py-1.5 text-xs font-semibold transition ${
                  viewer === v ? "bg-fg text-bg" : "text-muted hover:text-fg"
                }`}
              >
                {t(`pricing.${v}`)}
              </button>
            ))}
          </div>

          <span className="flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 text-xs text-muted">
            {theme === "dark" ? <Moon size={12} className="text-accent" /> : <Sun size={12} className="text-accent" />}
            follows your site theme — flip it in the nav
          </span>
        </motion.div>

        <PricingTableDemo config={config} viewer={viewer} />

        {/* editor + php export */}
        <div className="mt-16 grid gap-6 lg:grid-cols-2">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="code-shell"
          >
            <div className="flex items-center justify-between gap-3 border-b border-white/[0.06] px-4 py-2.5">
              <span className="flex items-center gap-2 font-mono text-[11px] text-zinc-500">
                <Braces size={12} className="text-accent-2" />
                {t("pricing.editor")}
              </span>
              <span
                className={`flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-mono text-[10px] ${
                  editorOk ? "bg-emerald-500/15 text-emerald-400" : "bg-red-500/15 text-red-400"
                }`}
              >
                <span className={`h-1 w-1 rounded-full ${editorOk ? "bg-emerald-400" : "bg-red-400"}`} />
                {editorOk ? t("pricing.apply") : t("pricing.invalid")}
              </span>
            </div>
            <textarea
              value={editor}
              onChange={(e) => applyEditor(e.target.value)}
              spellCheck={false}
              dir="ltr"
              className="code-body h-[320px] w-full resize-none bg-transparent p-4 outline-none"
            />
            <div className="flex flex-wrap items-center gap-2 border-t border-white/[0.06] p-3">
              <button
                onClick={save}
                disabled={!editorOk || saving}
                className="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-xs font-bold text-white transition hover:opacity-90 disabled:opacity-40"
              >
                {saving ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />}
                {saving ? t("pricing.saving") : t("pricing.save")}
              </button>

              {shareUrl && (
                <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 font-mono text-[11px] text-emerald-300">
                  <Check size={12} />
                  {t("pricing.saved")}
                  <a href={shareUrl} target="_blank" rel="noreferrer" className="text-accent-2 underline underline-offset-2">
                    {shareUrl}
                  </a>
                  <CopyButton text={typeof window !== "undefined" ? `${location.origin}${shareUrl}` : shareUrl} />
                </span>
              )}
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.1 }}
          >
            <div className="mb-3 flex items-center gap-2 font-mono text-xs text-muted" dir="ltr">
              <FileCode2 size={14} className="text-accent-2" />
              {t("pricing.as_php")} → sample pricing data
            </div>
            <CodeBlock code={php} title="Sample pricing data — not config" lang="php" maxHeight={420} />
          </motion.div>
        </div>
      </div>
    </section>
  );
}
