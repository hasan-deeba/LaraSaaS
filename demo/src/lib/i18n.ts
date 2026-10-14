export type Locale = "en" | "fr" | "ar";

export const LOCALES: { code: Locale; label: string; dir: "ltr" | "rtl" }[] = [
  { code: "en", label: "English", dir: "ltr" },
  { code: "fr", label: "Français", dir: "ltr" },
  { code: "ar", label: "العربية", dir: "rtl" },
];

type Dict = Record<string, string>;

const en: Dict = {
  "nav.integration": "Integration",
  "nav.pricing": "Pricing",
  "nav.announcements": "Announcements",
  "nav.flags": "Flags",
  "nav.config": "Config",
  "nav.install": "Install",
  "nav.star": "Star on GitHub",

  "hero.kicker": "Composer-installable SaaS starter kit",
  "hero.title_a": "Ship your SaaS.",
  "hero.title_b": "Keep your stack.",
  "hero.sub":
    "LaraSaaS drops Stripe billing, dynamic pricing, announcements and feature flags into any Laravel app — through your existing User model, your auth, your dark mode. One composer require away.",
  "hero.copy_cmd": "Copy install command",
  "hero.copied": "Copied to clipboard",
  "hero.explore": "Explore live demos",
  "hero.stat_modules": "modules included",
  "hero.stat_command": "command to install",
  "hero.stat_rewrites": "rewrites of your app",
  "hero.stat_license": "license, forever",

  "integration.kicker": "Zero lock-in",
  "integration.title": "It lives inside your app — not beside it.",
  "integration.sub":
    "Your login, your registration, your User model. LaraSaaS reads them from config and minds its own business.",
  "integration.point1": "No LaraSaaS\\User — the kit resolves whatever model you point it to.",
  "integration.point2": "Your guard, your middleware stack, your route names.",
  "integration.point3": "Bill the user… or a workspace. One config key apart.",
  "integration.host": "Your Laravel app",
  "integration.kit": "LaraSaaS",

  "pricing.kicker": "Module 01 — Billing",
  "pricing.title": "A pricing table with a brain.",
  "pricing.sub":
    "This playground uses sample data. In production, admins create plans in the dashboard and developers define typed features in config. Public pricing works without login.",
  "pricing.editor": "Demo pricing data (JSON)",
  "pricing.apply": "Config applied",
  "pricing.invalid": "Invalid JSON — fix it to apply",
  "pricing.save": "Save preset & get share link",
  "pricing.saving": "Saving…",
  "pricing.saved": "Share it:",
  "pricing.as_php": "View sample PHP data",
  "pricing.you_are": "Preview as",
  "pricing.visitor": "Visitor",
  "pricing.member": "Growth member",

  "ann.kicker": "Module 02 — Announcements",
  "ann.title": "Broadcasts, scheduled and polite.",
  "ann.sub":
    "Colored typings, start/end windows, CTAs and per-user dismissals — straight from the demo Postgres table. Broadcast one right now; it persists until you dismiss it.",
  "ann.console": "Server console",
  "ann.live": "live announcements",
  "ann.empty": "Nothing live right now — broadcast something from the console.",
  "ann.persisted": "Dismissals persist across reloads (per visitor cookie).",
  "ann.ends": "Ends",

  "flags.kicker": "Module 03 — Feature flags",
  "flags.title": "Rollouts with surgical precision.",
  "flags.sub":
    "Laravel Pennant flags, declared in config and evaluated per scope. Flip the toggles to see targeted widgets react — a preview of Feature::for($workspace).",
  "flags.workspace": "Workspace",
  "flags.scope": "Evaluated scope",

  "config.kicker": "One file rules all",
  "config.title": "Everything is a config key.",
  "config.sub": "The entire kit — routes, feature definitions, announcements, flags, theming, i18n — bends to config/larasaas.php.",

  "structure.kicker": "Under the hood",
  "structure.title": "Peek inside the package.",

  "install.kicker": "Ship today",
  "install.title": "Zero to SaaS in four commands.",
  "install.note1": "Works with any Laravel 10/11/12 app — Breeze React recommended.",
  "install.note2": "Publishes config, migrations, translations and the React components.",
  "install.note3": "Create plans and assign Stripe Price IDs at /app/admin/plans.",
  "install.note4": "That's it. /app/pricing is live. Start selling.",
  "install.final_a": "Ready when you are.",
  "install.final_b": "Your SaaS starts here.",

  "footer.tag": "MIT licensed. Built for the Laravel community.",
  "footer.made": "PHP 8.2+ · Laravel 10–12 · Cashier · Pennant · Inertia · React",

  "pricing.monthly": "Monthly",
  "pricing.yearly": "Yearly",
  "pricing.save_badge": "2 months free",
  "pricing.current": "Current plan",
  "pricing.switch": "Switch plan",
  "pricing.start": "Get started",
  "pricing.sales": "Talk to sales",
  "pricing.trial": "{days}-day free trial",
  "pricing.per_month": "mo",
  "pricing.per_year": "yr",
  "pricing.billed_yearly": "billed yearly",
};

const fr: Dict = {
  "nav.integration": "Intégration",
  "nav.pricing": "Tarifs",
  "nav.announcements": "Annonces",
  "nav.flags": "Flags",
  "nav.config": "Config",
  "nav.install": "Installation",
  "nav.star": "Voir sur GitHub",

  "hero.kicker": "Kit SaaS installable via Composer",
  "hero.title_a": "Lancez votre SaaS.",
  "hero.title_b": "Gardez votre stack.",
  "hero.sub":
    "LaraSaaS ajoute la facturation Stripe, des tarifs dynamiques, des annonces et des feature flags à toute application Laravel — via votre modèle User, votre auth, votre mode sombre. En un composer require.",
  "hero.copy_cmd": "Copier la commande",
  "hero.copied": "Copié dans le presse-papiers",
  "hero.explore": "Voir les démos en direct",
  "hero.stat_modules": "modules inclus",
  "hero.stat_command": "commande d'installation",
  "hero.stat_rewrites": "réécritures de votre app",
  "hero.stat_license": "licence, pour toujours",

  "integration.kicker": "Zéro enfermement",
  "integration.title": "Il vit dans votre app — pas à côté.",
  "integration.sub":
    "Votre login, votre inscription, votre modèle User. LaraSaaS les lit depuis la configuration et reste à sa place.",
  "integration.point1": "Pas de LaraSaaS\\User — le kit utilise le modèle que vous indiquez.",
  "integration.point2": "Votre guard, vos middlewares, vos noms de routes.",
  "integration.point3": "Facturez l'utilisateur… ou un workspace. Une clé de config.",
  "integration.host": "Votre app Laravel",
  "integration.kit": "LaraSaaS",

  "pricing.kicker": "Module 01 — Facturation",
  "pricing.title": "Une grille tarifaire intelligente.",
  "pricing.sub":
    "Ce simulateur utilise des exemples. En production, les admins créent les offres dans le tableau de bord ; les développeurs définissent les fonctionnalités typées en config. Les tarifs sont publics.",
  "pricing.editor": "Config des plans (JSON)",
  "pricing.apply": "Config appliquée",
  "pricing.invalid": "JSON invalide — corrigez pour appliquer",
  "pricing.save": "Enregistrer et partager",
  "pricing.saving": "Enregistrement…",
  "pricing.saved": "Partagez :",
  "pricing.as_php": "Voir les données PHP",
  "pricing.you_are": "Aperçu en tant que",
  "pricing.visitor": "Visiteur",
  "pricing.member": "Membre Growth",

  "ann.kicker": "Module 02 — Annonces",
  "ann.title": "Des diffusions programmées et élégantes.",
  "ann.sub":
    "Types colorés, fenêtres de planification, CTA et dismissals par utilisateur — directement depuis la table Postgres de démo. Diffusez-en une maintenant ; elle persiste jusqu'à ce que vous la fermiez.",
  "ann.console": "Console serveur",
  "ann.live": "annonces en direct",
  "ann.empty": "Rien à afficher — diffusez quelque chose depuis la console.",
  "ann.persisted": "Les dismissals persistent après rechargement (cookie visiteur).",
  "ann.ends": "Se termine",

  "flags.kicker": "Module 03 — Feature flags",
  "flags.title": "Des déploiements chirurgicaux.",
  "flags.sub":
    "Des flags Laravel Pennant, déclarés en config et évalués par scope. Basculez les interrupteurs pour voir les widgets réagir — un aperçu de Feature::for($workspace).",
  "flags.workspace": "Workspace",
  "flags.scope": "Scope évalué",

  "config.kicker": "Un fichier pour tout régir",
  "config.title": "Tout est une clé de config.",
  "config.sub": "Tout le kit — routes, fonctionnalités, annonces, flags, thème, i18n — suit config/larasaas.php.",

  "structure.kicker": "Sous le capot",
  "structure.title": "Un regard dans le package.",

  "install.kicker": "Livrez aujourd'hui",
  "install.title": "De zéro à SaaS en quatre commandes.",
  "install.note1": "Compatible Laravel 10/11/12 — Breeze React recommandé.",
  "install.note2": "Publie config, migrations, traductions et composants React.",
  "install.note3": "Créez les offres et renseignez leurs prix Stripe dans /app/admin/plans.",
  "install.note4": "C'est tout. /app/pricing est en ligne. Vendez.",
  "install.final_a": "Vous êtes prêt ?",
  "install.final_b": "Votre SaaS commence ici.",

  "footer.tag": "Licence MIT. Conçu pour la communauté Laravel.",
  "footer.made": "PHP 8.2+ · Laravel 10–12 · Cashier · Pennant · Inertia · React",

  "pricing.monthly": "Mensuel",
  "pricing.yearly": "Annuel",
  "pricing.save_badge": "2 mois offerts",
  "pricing.current": "Formule actuelle",
  "pricing.switch": "Changer de formule",
  "pricing.start": "Commencer",
  "pricing.sales": "Parler aux ventes",
  "pricing.trial": "Essai gratuit de {days} jours",
  "pricing.per_month": "mois",
  "pricing.per_year": "an",
  "pricing.billed_yearly": "facturé annuellement",
};

const ar: Dict = {
  "nav.integration": "الدمج",
  "nav.pricing": "الأسعار",
  "nav.announcements": "الإعلانات",
  "nav.flags": "الميزات",
  "nav.config": "الإعدادات",
  "nav.install": "التثبيت",
  "nav.star": "عرض على GitHub",

  "hero.kicker": "حزمة SaaS تُثبَّت عبر Composer",
  "hero.title_a": "أطلق SaaS الخاص بك.",
  "hero.title_b": "واحتفظ ببنيتك التقنية.",
  "hero.sub":
    "يضيف LaraSaaS فوترة Stripe وجداول أسعار ديناميكية وإعلانات وأعلام ميزات إلى أي تطبيق Laravel — عبر نموذج User الحالي، ونظام الدخول الخاص بك، والوضع الداكن لديك. كل ذلك بأمر composer require واحد.",
  "hero.copy_cmd": "نسخ أمر التثبيت",
  "hero.copied": "تم النسخ",
  "hero.explore": "استكشف العروض الحية",
  "hero.stat_modules": "وحدات مدمجة",
  "hero.stat_command": "أمر واحد للتثبيت",
  "hero.stat_rewrites": "إعادة كتابة لتطبيقك",
  "hero.stat_license": "رخصة، للأبد",

  "integration.kicker": "بدون قيود",
  "integration.title": "يعيش داخل تطبيقك — لا بجانبه.",
  "integration.sub":
    "تسجيل الدخول لديك، التسجيل لديك، نموذج User لديك. يقرأها LaraSaaS من ملف الإعدادات ويبقى في حدوده.",
  "integration.point1": "لا يوجد LaraSaaS\\User — تستخدم الحزمة النموذج الذي تحدده أنت.",
  "integration.point2": "الحارس الخاص بك، وسيطاتك، أسماء مساراتك.",
  "integration.point3": "وَجِّه الفوترة للمستخدم… أو لمساحة عمل. مفتاح إعداد واحد.",
  "integration.host": "تطبيق Laravel خاصتك",
  "integration.kit": "LaraSaaS",

  "pricing.kicker": "الوحدة 01 — الفوترة",
  "pricing.title": "جدول أسعارٍ يفكر بنفسه.",
  "pricing.sub":
    "هذه بيانات توضيحية فقط. يضيف المسؤول الخطط من لوحة التحكم ويحدد المطور الميزات وأنواعها في الإعدادات. عرض الأسعار متاح بدون تسجيل دخول.",
  "pricing.editor": "إعداد الخطط (JSON)",
  "pricing.apply": "تم تطبيق الإعداد",
  "pricing.invalid": "JSON غير صالح — صحّحه للتطبيق",
  "pricing.save": "حفظ الإعداد ومشاركته",
  "pricing.saving": "جارٍ الحفظ…",
  "pricing.saved": "شاركه:",
  "pricing.as_php": "نسخ كإعداد PHP",
  "pricing.you_are": "المعاينة بصفتك",
  "pricing.visitor": "زائر",
  "pricing.member": "مشترك Growth",

  "ann.kicker": "الوحدة 02 — الإعلانات",
  "ann.title": "بثٌّ مجدول ومهذّب.",
  "ann.sub":
    "أنواع ملوّنة، نوافذ زمنية، أزرار إجراء، وتجاهل لكل مستخدم — مباشرة من جدول Postgres التجريبي. ابثّ واحداً الآن؛ يبقى حتى تتجاهله.",
  "ann.console": "لوحة الخادم",
  "ann.live": "إعلانات نشطة",
  "ann.empty": "لا شيء نشطاً الآن — ابثّ شيئاً من اللوحة.",
  "ann.persisted": "حالات التجاهل تبقى بعد إعادة التحميل (لكل زائر).",
  "ann.ends": "ينتهي",

  "flags.kicker": "الوحدة 03 — أعلام الميزات",
  "flags.title": "إطلاق تدريجي بدقة جراحية.",
  "flags.sub":
    "أعلام Laravel Pennant تُعرَّف في الإعدادات وتُقيَّم حسب النطاق. بدّل المفاتيح لترى الوحدات تتفاعل — معاينة لـ Feature::for($workspace).",
  "flags.workspace": "مساحة العمل",
  "flags.scope": "النطاق المُقيَّم",

  "config.kicker": "ملف واحد يحكم كل شيء",
  "config.title": "كل شيء مفتاح إعداد.",
  "config.sub": "الحزمة كاملة — المسارات، تعريفات الميزات، الإعلانات، الأعلام، المظهر، الترجمة — تتبع config/larasaas.php.",

  "structure.kicker": "تحت الغطاء",
  "structure.title": "ألق نظرة داخل الحزمة.",

  "install.kicker": "أطلقه اليوم",
  "install.title": "من الصفر إلى SaaS بأربعة أوامر.",
  "install.note1": "يعمل مع Laravel 10/11/12 — يُفضَّل Breeze React.",
  "install.note2": "ينشر الإعدادات والهجرات والترجمات ومكوّنات React.",
  "install.note3": "أضف الخطط وأسعار Stripe من /app/admin/plans.",
  "install.note4": "انتهى الأمر. /app/pricing يعمل. ابدأ البيع.",
  "install.final_a": "هل أنت جاهز؟",
  "install.final_b": "رحلة SaaS تبدأ هنا.",

  "footer.tag": "رخصة MIT. صُنع لمجتمع Laravel.",
  "footer.made": "PHP 8.2+ · Laravel 10–12 · Cashier · Pennant · Inertia · React",

  "pricing.monthly": "شهري",
  "pricing.yearly": "سنوي",
  "pricing.save_badge": "شهران مجاناً",
  "pricing.current": "خطتك الحالية",
  "pricing.switch": "تغيير الخطة",
  "pricing.start": "ابدأ الآن",
  "pricing.sales": "تواصل مع المبيعات",
  "pricing.trial": "تجربة مجانية لمدة {days} يوماً",
  "pricing.per_month": "شهر",
  "pricing.per_year": "سنة",
  "pricing.billed_yearly": "تُدفع سنوياً",
};

const DICTS: Record<Locale, Dict> = { en, fr, ar };

export function translate(locale: Locale, key: string): string {
  return DICTS[locale][key] ?? en[key] ?? key;
}
