"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import { FolderClosed, FileCode2, FileJson, FileText, Database, Route, Box, Radio, CreditCard, Globe, Palette, TerminalSquare } from "lucide-react";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";

interface Node {
  path: string;
  depth: number;
  folder?: boolean;
  icon?: React.ComponentType<{ size?: number; className?: string }>;
  note: string;
}

const TREE: Node[] = [
  { path: "larasaas/", depth: 0, folder: true, icon: Box, note: "The composer package root. Push it to GitHub, tag a release, publish on Packagist." },
  { path: "composer.json", depth: 1, icon: FileJson, note: "Auto-discovers the service provider + facade. Requires Cashier and the Inertia Laravel adapter." },
  { path: "config/larasaas.php", depth: 1, icon: Palette, note: "The control room: user model, routes, typed plan entitlements, announcement types, theming, locales." },
  { path: "src/LaraSaaSServiceProvider.php", depth: 1, icon: FileCode2, note: "Registers routes, migration publishing, translations, publishes, the admin gate and middleware aliases." },
  { path: "src/LaraSaaS.php + Facades/", depth: 1, icon: FileCode2, note: "The manager: resolves your User model, the billable (user or workspace), plans → UI payloads." },
  { path: "src/Http/Controllers/", depth: 1, icon: Route, note: "Dashboard, Billing (Cashier checkout/swap/cancel/portal), Announcements API and Admin panels." },
  { path: "src/Http/Controllers/StripeWebhookController.php", depth: 2, icon: CreditCard, note: "Extends Cashier's webhook controller and fires one SubscriptionChanged event per lifecycle change." },
  { path: "src/Http/Middleware/", depth: 1, icon: ShieldIcon, note: "larasaas.subscribed (+ price pinning) and the Inertia shared-props middleware." },
  { path: "src/Models/", depth: 1, icon: Database, note: "Announcement (+ scopes: visibleTo, notDismissedBy) and AnnouncementDismissal — pointing at YOUR user model." },
  { path: "database/migrations/", depth: 1, icon: Database, note: "larasaas_announcements + dismissals. Cashier's tables are published by the installer." },
  { path: "resources/js/components/", depth: 1, icon: BracesIcon, note: "PricingTable and AnnouncementStack — React components, CSS variables, dark/light + RTL native." },
  { path: "resources/js/Pages/", depth: 1, icon: BracesIcon, note: "Inertia pages: Pricing, Billing, Dashboard, Admin/Announcements." },
  { path: "resources/js/larasaas.css", depth: 1, icon: Palette, note: "Design tokens for light/dark that follow the host .dark class — imported once in your app.css." },
  { path: "resources/lang/{en,fr,ar}/", depth: 1, icon: Globe, note: "Publishable translations; components follow the host locale automatically." },
  { path: "routes/", depth: 1, icon: Route, note: "web.php, api.php, webhook.php — wrapped in groups built entirely from config." },
  { path: "src/Console/InstallCommand.php", depth: 1, icon: TerminalSquare, note: "php artisan larasaas:install — publishes everything, offers migrations, prints the Stripe checklist." },
  { path: "src/Events/SubscriptionChanged.php", depth: 1, icon: Radio, note: "Listen to it in your app for lifecycle automations — seats, mailers, caches." },
  { path: "README.md + LICENSE (MIT)", depth: 1, icon: FileText, note: "Full docs: requirements, install, Stripe webhook setup, local path-repo development." },
];

function ShieldIcon(props: { size?: number; className?: string }) {
  return <FileCode2 {...props} />;
}
function BracesIcon(props: { size?: number; className?: string }) {
  return <FileCode2 {...props} />;
}

export function Structure() {
  const { t } = useSite();
  const [active, setActive] = useState(2);

  const current = TREE[active] ?? TREE[0];

  return (
    <section id="structure" className="relative py-28 md:py-36">
      <div className="mx-auto max-w-6xl px-5">
        <SectionHeading kicker="structure.kicker" title="structure.title" />

        <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            className="ring-line overflow-hidden rounded-3xl bg-card"
          >
            <div className="border-b border-line px-5 py-3 font-mono text-[11px] uppercase tracking-widest text-muted">
              ~/packages/larasaas
            </div>
            <div className="max-h-[520px] overflow-auto p-2.5 font-mono text-[12.5px]" dir="ltr">
              {TREE.map((node, i) => {
                const Icon = node.icon ?? (node.folder ? FolderClosed : FileCode2);
                return (
                  <motion.button
                    key={node.path}
                    initial={{ opacity: 0, x: -10 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    viewport={{ once: true }}
                    transition={{ delay: i * 0.03 }}
                    onMouseEnter={() => setActive(i)}
                    onFocus={() => setActive(i)}
                    className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-start transition ${
                      active === i ? "bg-card-2 text-fg" : "text-muted hover:text-fg"
                    }`}
                    style={{ paddingInlineStart: `${0.75 + node.depth * 1.1}rem` }}
                  >
                    {node.depth > 0 && <span className="text-line select-none">├</span>}
                    <Icon size={13} className={active === i ? "text-accent" : ""} />
                    <span className="truncate">{node.path}</span>
                  </motion.button>
                );
              })}
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.1 }}
            className="lg:sticky lg:top-28 lg:h-fit"
          >
            <div className="ring-line rounded-3xl bg-card p-6">
              <p className="font-mono text-[11px] uppercase tracking-widest text-accent">{t("structure.kicker")}</p>
              <motion.div
                key={current.path}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25 }}
              >
                <code className="mt-3 block break-words font-mono text-lg font-bold" dir="ltr">
                  {current.path}
                </code>
                <p className="mt-3 text-sm leading-relaxed text-muted">{current.note}</p>
              </motion.div>

              <div className="mt-6 grid grid-cols-2 gap-3 border-t border-line pt-5">
                {[
                  ["~30", "files"],
                  ["6", "publish tags"],
                  ["3", "locales"],
                  ["0", "npm deps in kit"],
                ].map(([num, label]) => (
                  <div key={label}>
                    <p className="font-mono text-xl font-bold">{num}</p>
                    <p className="text-[11px] text-muted">{label}</p>
                  </div>
                ))}
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
