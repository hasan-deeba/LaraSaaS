"use client";

import { useSite } from "./providers";
import { Wordmark, GithubIcon } from "./nav";
import { Heart } from "lucide-react";

export function Footer() {
  const { t } = useSite();

  return (
    <footer className="relative overflow-hidden border-t border-line pt-16">
      <div className="mx-auto max-w-6xl px-5">
        <div className="flex flex-wrap items-start justify-between gap-8 pb-12">
          <div className="max-w-sm">
            <Wordmark />
            <p className="mt-4 text-sm leading-relaxed text-muted">{t("footer.tag")}</p>
          </div>

          <div className="flex gap-16">
            <div className="grid gap-2.5 text-sm">
              <span className="font-mono text-[10px] uppercase tracking-widest text-muted">Modules</span>
              {[
                ["#pricing", "nav.pricing"],
                ["#announcements", "nav.announcements"],
              ].map(([href, key]) => (
                <a key={key} href={href} className="text-muted transition hover:text-fg">
                  {t(key)}
                </a>
              ))}
            </div>
            <div className="grid gap-2.5 text-sm">
              <span className="font-mono text-[10px] uppercase tracking-widest text-muted">Package</span>
              {[
                ["#integration", "nav.integration"],
                ["#config", "nav.config"],
                ["#install", "nav.install"],
              ].map(([href, key]) => (
                <a key={key} href={href} className="text-muted transition hover:text-fg">
                  {t(key)}
                </a>
              ))}
            </div>
            <div className="grid gap-2.5 text-sm">
              <span className="font-mono text-[10px] uppercase tracking-widest text-muted">Links</span>
              <a href="https://github.com" target="_blank" rel="noreferrer" className="flex items-center gap-2 text-muted transition hover:text-fg">
                <GithubIcon size={13} /> GitHub
              </a>
              <a href="#" className="text-muted transition hover:text-fg">Packagist</a>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line py-6 text-[11px] text-muted">
          <span className="flex items-center gap-1.5">
            made with <Heart size={11} className="text-accent" fill="currentColor" /> for the Laravel community
          </span>
          <span className="font-mono">{t("footer.made")}</span>
        </div>
      </div>

      <div
        aria-hidden
        className="pointer-events-none select-none bg-gradient-to-b from-fg/[0.07] to-transparent bg-clip-text pb-2 text-center font-bold leading-[0.8] tracking-tighter text-transparent"
        style={{ fontSize: "clamp(80px, 18vw, 220px)" }}
      >
        LaraSaaS
      </div>
    </footer>
  );
}
