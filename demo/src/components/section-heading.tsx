"use client";

import { motion } from "framer-motion";
import { useSite } from "./providers";

export function SectionHeading({
  kicker,
  title,
  sub,
  align = "start",
}: {
  kicker: string;
  title: string;
  sub?: string;
  align?: "start" | "center";
}) {
  const { t } = useSite();
  const centered = align === "center";

  return (
    <motion.div
      initial={{ opacity: 0, y: 24 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-80px" }}
      transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
      className={`mb-12 grid gap-5 md:grid-cols-[1fr_auto] md:items-end ${centered ? "text-center md:grid-cols-1 md:justify-items-center" : ""}`}
    >
      <div className="max-w-2xl">
        <span className="eyebrow">
          <span className="dot" />
          {t(kicker)}
        </span>
        <h2 className="mt-5 text-4xl font-bold leading-[1.05] tracking-tight md:text-6xl">{t(title)}</h2>
      </div>
      {sub && (
        <p className="max-w-md text-sm leading-relaxed text-muted md:text-base">{t(sub)}</p>
      )}
    </motion.div>
  );
}
