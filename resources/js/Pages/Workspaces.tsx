import React, { useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { bootLaraSaaS, t } from '../larasaas';

interface WorkspaceSummary {
    id: number;
    name: string;
    slug: string;
    role: string | null;
    owner_id: number;
    created_at?: string | null;
}

interface RoleDefinitions {
    [key: string]: { label?: string; implies?: string[] };
}

/**
 * GET /app/workspaces → multi-tenant workspace management: the parallel
 * organizations the signed-in user belongs to, and workspace creation.
 * Only registered when `larasaas.multi_tenant.enabled` is true.
 */
export default function Workspaces() {
    const { workspaces, roles, store_url, team_url_base, larasaas } = usePage<{
        workspaces: WorkspaceSummary[];
        roles: RoleDefinitions;
        store_url: string;
        team_url_base: string;
        larasaas: any;
    }>().props;

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    const form = useForm<{ name: string; slug: string }>({ name: '', slug: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
            <Head title={t('workspaces.title', 'Workspaces')} />

            <header className="ls-page__header">
                <h1 className="ls-page__title">{t('workspaces.title', 'Workspaces')}</h1>
                <p className="ls-page__subtitle">
                    {t('workspaces.subtitle', 'Operate within parallel organizational structures — each workspace has its own team, roles and billing.')}
                </p>
            </header>

            <form className="ls-card ls-form" onSubmit={submit}>
                <h2>{t('workspaces.create', 'Create a workspace')}</h2>
                <div className="ls-form__grid">
                    <label>
                        {t('workspaces.name', 'Name')}
                        <input
                            className="ls-input"
                            required
                            maxLength={140}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Acme Inc"
                        />
                    </label>
                    <label>
                        {t('workspaces.slug', 'URL slug (optional)')}
                        <input
                            className="ls-input"
                            maxLength={160}
                            value={form.data.slug}
                            onChange={(e) => form.setData('slug', e.target.value)}
                            placeholder="acme-inc"
                        />
                    </label>
                </div>
                {Object.keys(form.errors).length > 0 && (
                    <ul role="alert">
                        {Object.entries(form.errors).map(([key, error]) => (
                            <li key={key}>
                                {key}: {error}
                            </li>
                        ))}
                    </ul>
                )}
                <div className="ls-row">
                    <button className="ls-btn ls-btn--accent" type="submit" disabled={form.processing}>
                        {t('workspaces.create', 'Create a workspace')}
                    </button>
                </div>
            </form>

            <div className="ls-admin-list">
                {workspaces.length === 0 && <p className="ls-empty">{t('workspaces.empty', 'No workspaces yet — create your first one above.')}</p>}
                {workspaces.map((workspace) => (
                    <div className="ls-admin-row" key={workspace.id}>
                        <div className="ls-admin-row__main">
                            <strong>{workspace.name}</strong>
                            <small className="ls-mono">/{workspace.slug}</small>
                        </div>
                        <span className="ls-flag-pill">{workspace.role ? t(`workspaces.roles.${workspace.role}`, workspace.role) : ''}</span>
                        <a className="ls-btn ls-btn--sm" href={`${team_url_base}/${workspace.id}`}>
                            {t('workspaces.manage_team', 'Manage team')}
                        </a>
                    </div>
                ))}
            </div>
        </div>
    );
}
