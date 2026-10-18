import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { bootLaraSaaS } from '../../larasaas';

/**
 * Admin panel for Pennant feature flags — inspect the config-driven
 * strategy per flag, then force-activate / deactivate for everyone or
 * for a specific workspace id (stored in Pennant's database driver).
 */
export default function FeaturesAdmin() {
    const { definitions, larasaas } = usePage<{
        definitions: Record<string, Record<string, unknown>>;
        larasaas: any;
    }>().props;

    const [workspaceIds, setWorkspaceIds] = useState<Record<string, string>>({});

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    const act = (flag: string, action: 'activate' | 'deactivate', scope?: { scope_type: string; scope_id?: string }) =>
        router.post(`/app/admin/features/${flag}/${action}`, scope ?? { scope_type: 'everyone' }, { preserveScroll: true });

    const describe = (definition: Record<string, unknown>): string => {
        if (definition.enabled === true) return 'always on';
        if (definition.enabled === false) return 'always off';
        if (definition.percentage) return `${definition.percentage}% rollout`;
        if (Array.isArray(definition.workspaces) && definition.workspaces.length) return `workspaces: ${definition.workspaces.join(', ')}`;
        if (Array.isArray(definition.emails) && definition.emails.length) return `emails: ${definition.emails.join(', ')}`;
        if (definition.resolve) return 'custom resolver';
        return 'config default';
    };

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
            <Head title="Feature flags" />

            <header className="ls-page__header">
                <h1 className="ls-page__title">Feature flags</h1>
                <p className="ls-page__subtitle">Database-driven rollouts, targeted per workspace — powered by Laravel Pennant.</p>
            </header>

            <div className="ls-admin-list">
                {Object.entries(definitions ?? {}).map(([flag, definition]) => (
                    <div key={flag} className="ls-admin-row">
                        <div className="ls-admin-row__main">
                            <strong className="ls-mono">{flag}</strong>
                            <small>{describe(definition as Record<string, unknown>)}</small>
                        </div>

                        <input
                            className="ls-input ls-input--sm"
                            placeholder="workspace id"
                            value={workspaceIds[flag] ?? ''}
                            onChange={(e) => setWorkspaceIds((ids) => ({ ...ids, [flag]: e.target.value }))}
                        />

                        <button
                            className="ls-btn ls-btn--sm"
                            type="button"
                            disabled={!workspaceIds[flag]}
                            onClick={() => act(flag, 'activate', { scope_type: 'workspace', scope_id: workspaceIds[flag] })}
                        >
                            On for workspace
                        </button>
                        <button className="ls-btn ls-btn--sm ls-btn--accent" type="button" onClick={() => act(flag, 'activate')}>
                            On for everyone
                        </button>
                        <button className="ls-btn ls-btn--sm ls-btn--ghost" type="button" onClick={() => act(flag, 'deactivate')}>
                            Off
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}
