"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { translate, type Locale } from "@/lib/i18n";

type Theme = "dark" | "light";

interface SiteState {
  theme: Theme;
  toggleTheme: () => void;
  locale: Locale;
  setLocale: (locale: Locale) => void;
  t: (key: string) => string;
}

const SiteContext = createContext<SiteState>({
  theme: "dark",
  toggleTheme: () => {},
  locale: "en",
  setLocale: () => {},
  t: (key) => key,
});

export function SiteProviders({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>("dark");
  const [locale, setLocaleState] = useState<Locale>("en");

  useEffect(() => {
    setTheme(document.documentElement.classList.contains("dark") ? "dark" : "light");
    const stored = localStorage.getItem("ls-locale") as Locale | null;
    if (stored && ["en", "fr", "ar"].includes(stored)) setLocaleState(stored);
  }, []);

  const toggleTheme = useCallback(() => {
    setTheme((current) => {
      const next = current === "dark" ? "light" : "dark";
      document.documentElement.classList.toggle("dark", next === "dark");
      localStorage.setItem("ls-theme", next);
      return next;
    });
  }, []);

  const setLocale = useCallback((next: Locale) => {
    setLocaleState(next);
    localStorage.setItem("ls-locale", next);
    document.documentElement.lang = next;
    document.documentElement.dir = next === "ar" ? "rtl" : "ltr";
  }, []);

  const t = useCallback((key: string) => translate(locale, key), [locale]);

  const value = useMemo(
    () => ({ theme, toggleTheme, locale, setLocale, t }),
    [theme, toggleTheme, locale, setLocale, t],
  );

  return <SiteContext.Provider value={value}>{children}</SiteContext.Provider>;
}

export function useSite() {
  return useContext(SiteContext);
}
