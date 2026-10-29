"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { useSite } from "./providers";
import { SectionHeading } from "./section-heading";
import { CodeBlock } from "./code";

const TABS: { key: string; file: string; code: string }[] = [
  {
    key: "plans",
    file: "'plan_features' =>",
    code: `// Developers define capabilities; admins create plans in the dashboard.
'plan_features' => [
    'uploads' => ['label' => 'File uploads', 'type' => 'boolean', 'default' => false],
    'storage_gb' => [
        'label' => 'Storage', 'type' => 'integer', 'unit' => 'GB',
        'default' => 0, 'allow_unlimited' => true,
    ],
],

'pricing' => [
    'default_currency' => 'usd',
    'default_interval' => 'monthly',
    'intervals' => ['monthly', 'yearly'],
],

// Your dashboard: LaraSaaS::createPlan($data) → Starter: 10 GB; Pro: 100 GB.
// Plans and Stripe Price IDs live in the database, NOT config.
// Server-side enforcement (your app tracks usage):
LaraSaaS::allows($request, 'uploads');
LaraSaaS::allows($request, 'storage_gb', $projectedUsageGb);`,
  },
  {
    key: "announce",
    file: "'announcements' =>",
    code: `'announcements' => [
    'poll_seconds' => 60,
    'types' => [
        'info'    => ['color' => '#3b82f6', 'icon' => 'info'],
        'success' => ['color' => '#22c55e', 'icon' => 'check-circle'],
        'warning' => ['color' => '#f59e0b', 'icon' => 'alert-triangle'],
        'danger'  => ['color' => '#ef4444', 'icon' => 'alert-octagon'],
        'promo'   => ['color' => '#a855f7', 'icon' => 'sparkles'],
    ],
],

// Scheduling + audiences live on the model:
Announcement::create([
    'title'      => 'Black Friday',
    'type'       => 'promo',
    'starts_at'  => now()->addWeek(),
    'ends_at'    => now()->addWeeks(2),
    'dismissible'=> true,
    'audience'   => ['workspaces' => [1, 4]],
]);`,
  },
  {
    key: "billing",
    file: "'billing' + routes",
    code: `'billing' => [
    'billable'            => 'user',                 // or 'workspace'
    'workspace_model'     => App\\Models\\Workspace::class,
    'workspace_resolver'  => fn ($user) => $user->currentWorkspace(),
    'subscription'        => 'default',
    'trial_days'          => 14,
    'allow_promotion_codes' => true,
],

'route' => [
    'prefix'     => 'app',              // /app/pricing, /app/billing…
    'domain'     => null,               // or billing.yourapp.test
    'middleware' => ['web', 'auth'],    // your stacks, unchanged
],

// gate anything behind a subscription
Route::middleware('larasaas.subscribed')->group(function () {
    Route::get('/reports', ReportController::class);
});`,
  },
  {
    key: "theming",
    file: "'theme' + i18n",
    code: `// Components inherit your theme — zero wiring.
// Tokens flip with your .dark class (or media query),
// and RTL flows through logical CSS properties.

'theme' => [
    'default' => 'system',   // system | light | dark (hint only)
    'accent'  => '#ff2d20',  // --larasaas-accent, yours to override
],

// Text follows app()->getLocale(); add languages with
// php artisan vendor:publish --tag=larasaas-lang
'rtl_locales' => ['ar', 'he', 'fa', 'ur'],

// custom accent from your own CSS:
// :root { --larasaas-accent: #7c3aed; }

// publish and extend translations:
// php artisan vendor:publish --tag=larasaas-lang`,
  },
];

export function ConfigReference() {
  const { t } = useSite();
  const [active, setActive] = useState(TABS[0].key);
  const tab = TABS.find((x) => x.key === active) ?? TABS[0];

  return (
    <section id="config" className="relative py-28 md:py-36">
      <div className="pointer-events-none absolute end-0 top-20 h-[400px] w-[400px] rounded-full bg-accent/10 blur-[130px]" />
      <div className="relative mx-auto max-w-6xl px-5">
        <SectionHeading kicker="config.kicker" title="config.title" sub="config.sub" align="center" />

        <motion.div
          initial={{ opacity: 0, y: 24 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          className="mx-auto max-w-3xl"
        >
          <div className="mb-4 flex flex-wrap justify-center gap-1.5" dir="ltr">
            {TABS.map((x) => (
              <button
                key={x.key}
                onClick={() => setActive(x.key)}
                className={`relative rounded-full px-4 py-2 font-mono text-[11px] transition ${
                  active === x.key ? "text-bg" : "text-muted hover:text-fg border border-line"
                }`}
              >
                {active === x.key && (
                  <motion.span
                    layoutId="config-tab"
                    className="absolute inset-0 -z-10 rounded-full bg-fg"
                    transition={{ type: "spring", stiffness: 500, damping: 40 }}
                  />
                )}
                {x.file}
              </button>
            ))}
          </div>

          <AnimatePresence mode="wait">
            <motion.div
              key={tab.key}
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -14 }}
              transition={{ duration: 0.3 }}
            >
              <CodeBlock code={tab.code} title={`config/larasaas.php`} lang="php" maxHeight={440} />
            </motion.div>
          </AnimatePresence>
        </motion.div>
      </div>
    </section>
  );
}
