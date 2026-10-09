import React, { useCallback, useEffect, useState } from 'react';
import { LaraSaaSIcon, larasaasFetch, laraSaaS } from '../larasaas';

/**
 * <AnnouncementStack /> — polls the package API for live broadcasts and
 * renders them with colored typings. Dismissals POST back to Laravel and
 * are persisted per user (survives sessions & devices).
 */

export interface UiAnnouncement {
    id: number;
    title: string;
    body: string;
    type: string;
    color: string;
    icon: string;
    dismissible: boolean;
    active: boolean;
    live: boolean;
    action?: { label: string; url: string } | null;
    starts_at?: string | null;
    ends_at?: string | null;
}

interface Props {
    /** Position variant: floating stack (default) or inline banners. */
    variant?: 'floating' | 'inline';
    limit?: number;
    className?: string;
}

export default function AnnouncementStack({ variant = 'floating', limit = 5, className = '' }: Props) {
    const [announcements, setAnnouncements] = useState<UiAnnouncement[]>([]);
    const [leaving, setLeaving] = useState<number[]>([]);

    const load = useCallback(async () => {
        try {
            const { data } = await larasaasFetch('announcements');
            setAnnouncements((data ?? []).slice(0, limit));
        } catch {
            /* host offline / unauthenticated — stay silent */
        }
    }, [limit]);

    useEffect(() => {
        load();
        const every = Math.max(10, (laraSaaS().poll_seconds ?? 60)) * 1000;
        const timer = window.setInterval(load, every);
        return () => window.clearInterval(timer);
    }, [load]);

    const dismiss = async (id: number) => {
        setLeaving((ids) => [...ids, id]);

        try {
            await larasaasFetch(`announcements/${id}/dismiss`, { method: 'POST' });
        } finally {
            window.setTimeout(() => setAnnouncements((list) => list.filter((item) => item.id !== id)), 220);
        }
    };

    if (!announcements.length) return null;

    return (
        <div data-larasaas className={`ls-announcements ls-announcements--${variant} ${className}`} role="region" aria-live="polite">
            {announcements.map((announcement) => (
                <div
                    key={announcement.id}
                    className={`ls-announcement ${leaving.includes(announcement.id) ? 'is-leaving' : ''}`}
                    style={{ ['--ls-announcement-color' as any]: announcement.color }}
                >
                    <span className="ls-announcement__rail" aria-hidden="true" />
                    <span className="ls-announcement__icon">
                        <LaraSaaSIcon name={announcement.icon} size={17} />
                    </span>

                    <div className="ls-announcement__content">
                        <p className="ls-announcement__title">{announcement.title}</p>
                        <p className="ls-announcement__body">{announcement.body}</p>
                        {announcement.action && (
                            <a className="ls-announcement__action" href={announcement.action.url}>
                                {announcement.action.label}
                                <LaraSaaSIcon name="external" size={12} />
                            </a>
                        )}
                    </div>

                    {announcement.dismissible && (
                        <button
                            type="button"
                            className="ls-announcement__dismiss"
                            aria-label="Dismiss"
                            onClick={() => dismiss(announcement.id)}
                        >
                            <LaraSaaSIcon name="x" size={14} />
                        </button>
                    )}
                </div>
            ))}
        </div>
    );
}
