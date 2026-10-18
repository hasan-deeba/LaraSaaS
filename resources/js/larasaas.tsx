import React from 'react';

/**
 * Shared runtime config for the LaraSaaS React components.
 *
 * Pages boot it from the Inertia shared props (`larasaas`); in a plain
 * Blade/Vite setup, the @larasaas directive writes the same payload to
 * `window.LaraSaaS`. Components therefore work in BOTH worlds.
 */

export interface LaraSaaSConfig {
    locale: string;
    dir: 'ltr' | 'rtl';
    translations: Record<string, any>;
    accent: string;
    api_base: string;
    poll_seconds: number;
    is_admin: boolean;
    features: Record<string, boolean>;
}

declare global {
    interface Window {
        LaraSaaS?: Partial<LaraSaaSConfig>;
    }
}

export function bootLaraSaaS(config: Partial<LaraSaaSConfig> = {}) {
    if (typeof window === 'undefined') return;

    window.LaraSaaS = { ...(window.LaraSaaS ?? {}), ...config };

    if (config.accent) {
        document.documentElement.style.setProperty('--larasaas-accent', config.accent);
    }
    if (config.dir) {
        // The kit politely inherits the HOST theme, but exposes dir to children.
        document.querySelectorAll('[data-larasaas]').forEach((el) => el.setAttribute('dir', config.dir ?? 'ltr'));
    }
}

export function laraSaaS(): Partial<LaraSaaSConfig> {
    return typeof window === 'undefined' ? {} : window.LaraSaaS ?? {};
}

/** Dot-walking, gracefully-fallbacking translator. */
export function t(key: string, fallback?: string): string {
    const translations = (laraSaaS().translations ?? {}) as Record<string, any>;

    const value = key.split('.').reduce<any>(
        (carry, segment) => (carry && typeof carry === 'object' ? carry[segment] : undefined),
        translations,
    );

    return typeof value === 'string' ? value : (fallback ?? key);
}

export function apiUrl(path: string): string {
    const base = (laraSaaS().api_base ?? '/api/larasaas').replace(/\/$/, '');
    return `${base}/${path.replace(/^\//, '')}`;
}

/** Session-auth fetch wrapper (works with meta csrf or XSRF cookie). */
export async function larasaasFetch(path: string, options: RequestInit = {}): Promise<any> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers as Record<string, string> | undefined),
    };

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (meta) headers['X-CSRF-TOKEN'] = meta;

    const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);

    const response = await fetch(apiUrl(path), { credentials: 'same-origin', ...options, headers });

    if (!response.ok) throw new Error(`LaraSaaS request failed: ${response.status}`);

    return response.json();
}

/* ----------------------------------------------------------------------- */
/*  Dependency-free inline icon set (the kit ships zero npm deps).          */
/* ----------------------------------------------------------------------- */

const PATHS: Record<string, React.ReactNode> = {
    info: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8h.01M11 12h1v5h1" />
        </>
    ),
    'check-circle': (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12.5 2.5 2.5 4.5-6" />
        </>
    ),
    'alert-triangle': (
        <>
            <path d="M12 3 2.8 19.2a1 1 0 0 0 .87 1.5h16.66a1 1 0 0 0 .87-1.5L12 3Z" />
            <path d="M12 9.5v4.5M12 17.5h.01" />
        </>
    ),
    'alert-octagon': (
        <>
            <path d="M7.9 3h8.2L21 7.9v8.2L16.1 21H7.9L3 16.1V7.9L7.9 3Z" />
            <path d="M12 8v5M12 16.5h.01" />
        </>
    ),
    sparkles: (
        <>
            <path d="M12 4l1.7 4.6L18.5 10l-4.8 1.4L12 16l-1.7-4.6L5.5 10l4.8-1.4L12 4Z" />
            <path d="M18.5 16.5h.01M5.5 17.5h.01" />
        </>
    ),
    x: <path d="M6 6l12 12M18 6 6 18" />,
    check: <path d="m5 12.5 4.5 4.5L19 7.5" />,
    external: (
        <>
            <path d="M14 5h5v5" />
            <path d="M19 5 11 13" />
            <path d="M19 14v4a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 18V7a1.5 1.5 0 0 1 1.5-1.5H11" />
        </>
    ),
};

export function LaraSaaSIcon({ name, size = 16, strokeWidth = 1.75 }: { name: string; size?: number; strokeWidth?: number }) {
    return (
        <svg
            viewBox="0 0 24 24"
            width={size}
            height={size}
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {PATHS[name] ?? PATHS.info}
        </svg>
    );
}
