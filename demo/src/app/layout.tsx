import type { Metadata } from "next";
import type { ReactNode } from "react";
import { Space_Grotesk, JetBrains_Mono } from "next/font/google";
import "./globals.css";

const spaceGrotesk = Space_Grotesk({
  subsets: ["latin"],
  variable: "--font-sg",
});

const jetbrainsMono = JetBrains_Mono({
  subsets: ["latin"],
  variable: "--font-jm",
});

export const metadata: Metadata = {
  title: "LaraSaaS — The drop-in SaaS engine for Laravel + React",
  description:
    "Stripe billing, dynamic pricing and announcements for any Laravel app. Composer-installable. Uses YOUR User model, YOUR auth, YOUR theme.",
  openGraph: {
    title: "LaraSaaS — Ship your SaaS, keep your stack",
    description:
      "Composer-installable SaaS starter: Cashier billing, admin-managed plans and announcements with React components.",
  },
};

const themeScript = `
(function () {
  try {
    var stored = localStorage.getItem("ls-theme");
    var dark = stored ? stored === "dark" : true;
    document.documentElement.classList.toggle("dark", dark);
    var locale = localStorage.getItem("ls-locale");
    if (locale) {
      document.documentElement.lang = locale;
      document.documentElement.dir = locale === "ar" ? "rtl" : "ltr";
    }
  } catch (e) {
    document.documentElement.classList.add("dark");
  }
})();
`;

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en" className="dark" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
      </head>
      <body className={`${spaceGrotesk.variable} ${jetbrainsMono.variable} font-display noise`}>
        {children}
      </body>
    </html>
  );
}
