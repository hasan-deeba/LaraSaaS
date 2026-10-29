"use client";

import { useEffect, useState } from "react";
import { motion, useScroll, useSpring, AnimatePresence } from "framer-motion";
import { Moon, Sun, Menu, X, Rocket, Languages } from "lucide-react";
import { useSite } from "./providers";
import { LOCALES } from "@/lib/i18n";

export function GithubIcon({ size = 16 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.55v-2.17c-3.2.7-3.87-1.36-3.87-1.36-.52-1.33-1.28-1.69-1.28-1.69-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.75 2.69 1.25 3.34.95.11-.74.4-1.25.72-1.53-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.29 1.19-3.09-.12-.29-.51-1.46.11-3.05 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.78 0c2.2-1.49 3.17-1.18 3.17-1.18.62 1.59.23 2.76.11 3.05.74.8 1.19 1.83 1.19 3.09 0 4.42-2.7 5.39-5.26 5.68.41.36.77 1.05.77 2.13v3.16c0 .31.21.67.8.55A11.51 11.51 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z" />
    </svg>
  );
}

const LINKS = [
  { href: "#integration", key: "nav.integration" },
  { href: "#pricing", key: "nav.pricing" },
  { href: "#announcements", key: "nav.announcements" },
  { href: "#config", key: "nav.config" },
  { href: "#install", key: "nav.install" },
];

export function Wordmark({ size = 18 }: { size?: number }) {
  return (
    <span className="inline-flex items-center gap-2.5 font-bold tracking-tight">
      <span
        className="grid place-items-center rounded-lg bg-gradient-to-br from-accent to-accent-2 text-white shadow-[0_8px_24px_-8px_var(--accent)]"
        style={{ width: size + 12, height: size + 12 }}
      >
        <Rocket size={size} strokeWidth={2.2} />
      </span>
      <span style={{ fontSize: size }}>
        Lara<span className="text-grad">SaaS</span>
      </span>
    </span>
  );
}

export function Nav() {
  const { theme, toggleTheme, locale, setLocale, t } = useSite();
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const [langOpen, setLangOpen] = useState(false);
  const { scrollYProgress } = useScroll();
  const progress = useSpring(scrollYProgress, { stiffness: 120, damping: 26 });

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <>
      <motion.div
        className="fixed inset-x-0 top-0 z-[80] h-0.5 origin-left bg-gradient-to-r from-accent via-accent-2 to-violet"
        style={{ scaleX: progress }}
      />

      <header className="fixed inset-x-0 top-0 z-[75] flex justify-center px-4 pt-4">
        <div
          className={`flex w-full max-w-6xl items-center justify-between gap-3 rounded-2xl px-4 py-2.5 transition-all duration-300 ${
            scrolled ? "glass shadow-[0_20px_50px_-20px_rgba(0,0,0,0.35)]" : "border border-transparent"
          }`}
        >
          <a href="#" aria-label="LaraSaaS home" onClick={() => setOpen(false)}>
            <Wordmark />
          </a>

          <nav className="hidden items-center gap-1 lg:flex">
            {LINKS.map((link) => (
              <a
                key={link.href}
                href={link.href}
                className="rounded-full px-3.5 py-1.5 text-sm text-muted transition hover:bg-card-2 hover:text-fg"
              >
                {t(link.key)}
              </a>
            ))}
          </nav>

          <div className="flex items-center gap-1.5">
            <div className="relative">
              <button
                onClick={() => setLangOpen((v) => !v)}
                aria-label="Language"
                className="grid h-9 w-9 place-items-center rounded-full border border-line text-muted transition hover:text-fg"
              >
                <Languages size={16} />
              </button>
              <AnimatePresence>
                {langOpen && (
                  <motion.div
                    initial={{ opacity: 0, y: 6, scale: 0.96 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: 6, scale: 0.96 }}
                    transition={{ duration: 0.16 }}
                    className="glass absolute end-0 mt-2 w-40 overflow-hidden rounded-xl p-1 shadow-xl"
                  >
                    {LOCALES.map((l) => (
                      <button
                        key={l.code}
                        onClick={() => {
                          setLocale(l.code);
                          setLangOpen(false);
                        }}
                        className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition hover:bg-card-2 ${
                          locale === l.code ? "text-accent" : "text-muted"
                        }`}
                      >
                        {l.label}
                        {locale === l.code && <span className="h-1.5 w-1.5 rounded-full bg-accent" />}
                      </button>
                    ))}
                  </motion.div>
                )}
              </AnimatePresence>
            </div>

            <button
              onClick={toggleTheme}
              aria-label="Toggle theme"
              className="grid h-9 w-9 place-items-center rounded-full border border-line text-muted transition hover:text-fg"
            >
              {theme === "dark" ? <Sun size={16} /> : <Moon size={16} />}
            </button>

            <a
              href="https://github.com"
              target="_blank"
              rel="noreferrer"
              className="hidden h-9 items-center gap-2 rounded-full bg-fg px-4 text-sm font-semibold text-bg transition hover:opacity-85 sm:inline-flex"
            >
              <GithubIcon size={15} />
              {t("nav.star")}
            </a>

            <button
              onClick={() => setOpen((v) => !v)}
              aria-label="Menu"
              className="grid h-9 w-9 place-items-center rounded-full border border-line text-muted lg:hidden"
            >
              {open ? <X size={16} /> : <Menu size={16} />}
            </button>
          </div>
        </div>
      </header>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[72] bg-bg/90 backdrop-blur-xl lg:hidden"
          >
            <div className="flex h-full flex-col items-center justify-center gap-2">
              {LINKS.map((link, i) => (
                <motion.a
                  key={link.href}
                  href={link.href}
                  onClick={() => setOpen(false)}
                  initial={{ opacity: 0, y: 16 }}
                  animate={{ opacity: 1, y: 0, transition: { delay: 0.05 * i } }}
                  className="px-6 py-3 text-3xl font-bold tracking-tight text-fg"
                >
                  {t(link.key)}
                </motion.a>
              ))}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}
