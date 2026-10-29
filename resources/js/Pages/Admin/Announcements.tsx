import React, { useEffect } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { bootLaraSaaS, LaraSaaSIcon } from '../../larasaas';
import type { UiAnnouncement } from '../../components/AnnouncementStack';

/**
 * Admin panel for global broadcasts — scheduling, colored typings,
 * CTA buttons and active toggling.
 */
export default function AnnouncementsAdmin() {
    const { announcements, types, larasaas, notice } = usePage<{
        announcements: UiAnnouncement[];
        types: Record<string, { color: string; icon: string }>;
        larasaas: any;
        notice?: string;
    }>().props;

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    const webBase = (larasaas?.web_base ?? '/app').replace(/\/+$/, '');
    const announcementsUrl = `${webBase}/admin/announcements`;

    const form = useForm({
        title: '',
        body: '',
        type: 'info',
        color: '',
        action_label: '',
        action_url: '',
        dismissible: true,
        starts_at: '',
        ends_at: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            action_label: data.action_label.trim() || null,
            action_url: data.action_url.trim() || null,
        }));
        form.post(announcementsUrl, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const toggle = (announcement: UiAnnouncement) =>
        router.put(`${announcementsUrl}/${announcement.id}`, { active: !announcement.active }, { preserveScroll: true });

    const destroy = (id: number) =>
        router.delete(`${announcementsUrl}/${id}`, { preserveScroll: true });

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page ls-admin-announcements">
            <Head title="Announcements" />

            <header className="ls-page__header">
                <h1 className="ls-page__title">Announcements</h1>
                <p className="ls-page__subtitle">Broadcast to every screen — scheduled, colored and dismissible.</p>
            </header>

            {notice && <div className="ls-notice" role="status">{notice}</div>}

            <form onSubmit={submit} className="ls-card ls-form ls-admin-announcements__form">
                {Object.keys(form.errors).length > 0 && (
                    <div className="ls-form-error" role="alert">
                        <strong>Please correct the following before broadcasting:</strong>
                        <ul>
                            {Object.values(form.errors).map((error, index) => <li key={index}>{error}</li>)}
                        </ul>
                    </div>
                )}
                <div className="ls-form__grid">
                    <input className={`ls-input ${form.errors.title ? 'is-invalid' : ''}`} placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required aria-invalid={!!form.errors.title} />
                    <select className="ls-input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {Object.keys(types ?? { info: { color: '#3b82f6', icon: 'info' } }).map((type) => (
                            <option key={type} value={type}>{type}</option>
                        ))}
                    </select>
                </div>
                <textarea className={`ls-input ${form.errors.body ? 'is-invalid' : ''}`} placeholder="Message" rows={6} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required aria-invalid={!!form.errors.body} />
                <div className="ls-form__grid ls-admin-announcements__dates">
                    <label className="ls-input-label">Starts <input className="ls-input" type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></label>
                    <label className="ls-input-label">Ends <input className="ls-input" type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} /></label>
                </div>
                <div className="ls-form__grid">
                    <input className="ls-input" placeholder="CTA label (optional)" value={form.data.action_label} onChange={(e) => form.setData('action_label', e.target.value)} />
                    <input className="ls-input" placeholder="CTA URL (optional)" value={form.data.action_url} onChange={(e) => form.setData('action_url', e.target.value)} />
                </div>
                <label className="ls-checkbox">
                    <input type="checkbox" checked={form.data.dismissible} onChange={(e) => form.setData('dismissible', e.target.checked)} />
                    Dismissible
                </label>
                <button className="ls-btn ls-btn--accent" disabled={form.processing} type="submit">
                    Broadcast
                </button>
            </form>

            <div className="ls-admin-list">
                {(announcements ?? []).map((announcement) => (
                    <div key={announcement.id} className="ls-admin-row" style={{ ['--ls-announcement-color' as any]: announcement.color }}>
                        <span className="ls-announcement__rail" aria-hidden="true" />
                        <LaraSaaSIcon name={announcement.icon} size={16} />
                        <div className="ls-admin-row__main">
                            <strong>{announcement.title}</strong>
                            <small>
                                {announcement.type}
                                {announcement.starts_at ? ` · from ${new Date(announcement.starts_at).toLocaleString()}` : ''}
                                {announcement.ends_at ? ` · until ${new Date(announcement.ends_at).toLocaleString()}` : ' · no end date'}
                            </small>
                        </div>
                        <span className={`ls-flag-pill ${announcement.live ? 'is-on' : ''}`}>{announcement.live ? 'live' : 'off'}</span>
                        <button className="ls-btn ls-btn--sm" type="button" onClick={() => toggle(announcement)}>
                            {announcement.active ? 'Disable' : 'Enable'}
                        </button>
                        <button className="ls-btn ls-btn--sm ls-btn--ghost" type="button" onClick={() => destroy(announcement.id)}>
                            Delete
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}
