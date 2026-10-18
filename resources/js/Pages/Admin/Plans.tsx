import React, { useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { bootLaraSaaS } from '../../larasaas';

type Definition = { label?: string; type: 'boolean' | 'integer' | 'number'; unit?: string; default?: boolean | number; allow_unlimited?: boolean };
type Price = { price: number | string; stripe_id: string };
type Plan = {
    id?: number; key: string; name: string; description: string; badge: string;
    active: boolean; highlight: boolean; trial_days: number | string | null; sort_order: number;
    monthly: Price | null; yearly: Price | null; features: Record<string, boolean | number>;
};

function PlanEditor({ plan, definitions, url, done }: { plan?: Plan; definitions: Record<string, Definition>; url: string; done: () => void }) {
    const defaults = Object.fromEntries(Object.entries(definitions).map(([key, definition]) => [key, definition.default ?? (definition.type === 'boolean' ? false : 0)]));
    const form = useForm<Plan>({
        key: plan?.key ?? '', name: plan?.name ?? '', description: plan?.description ?? '', badge: plan?.badge ?? '',
        active: plan?.active ?? true, highlight: plan?.highlight ?? false, trial_days: plan?.trial_days ?? '', sort_order: plan?.sort_order ?? 0,
        monthly: plan?.monthly ?? null, yearly: plan?.yearly ?? null,
        features: Object.fromEntries(Object.keys(definitions).map(key => [key, plan?.features?.[key] ?? defaults[key]])),
    });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); done(); } };
        if (plan?.id) form.put(`${url}/${plan.id}`, options);
        else form.post(url, options);
    };
    return <form className="ls-card ls-form" onSubmit={submit}>
        <h2>{plan ? `Edit ${plan.name}` : 'Create plan'}</h2>
        <div className="ls-form__grid">
            <label>Key <input className="ls-input" required disabled={!!plan} value={form.data.key} onChange={e => form.setData('key', e.target.value)} placeholder="starter" /></label>
            <label>Name <input className="ls-input" required value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></label>
        </div>
        <label>Description <textarea className="ls-input" value={form.data.description} onChange={e => form.setData('description', e.target.value)} /></label>
        <div className="ls-form__grid">
            <label>Badge <input className="ls-input" value={form.data.badge} onChange={e => form.setData('badge', e.target.value)} /></label>
            <label>Trial days (blank uses default) <input className="ls-input" type="number" min="0" max="730" value={form.data.trial_days ?? ''} onChange={e => form.setData('trial_days', e.target.value)} /></label>
            <label>Display order <input className="ls-input" type="number" min="0" value={form.data.sort_order} onChange={e => form.setData('sort_order', Number(e.target.value))} /></label>
        </div>
        <label><input type="checkbox" checked={form.data.active} onChange={e => form.setData('active', e.target.checked)} /> Available for new subscriptions</label>
        <label><input type="checkbox" checked={form.data.highlight} onChange={e => form.setData('highlight', e.target.checked)} /> Highlight this plan</label>
        <p>Use existing recurring Stripe Price IDs in your configured currency. Display amounts do not change the price charged by Stripe. Archive and create a new plan to change Price IDs.</p>
        {(['monthly', 'yearly'] as const).map(interval => {
            const price = form.data[interval];
            return <fieldset key={interval}>
                <legend>{interval}</legend>
                <label><input type="checkbox" disabled={!!plan} checked={!!price} onChange={e => form.setData(interval, e.target.checked ? { price: 0, stripe_id: '' } : null)} /> Offer this interval</label>
                {price && <div className="ls-form__grid">
                    <label>Display price <input className="ls-input" type="number" min="0" step="0.01" required value={price.price} onChange={e => form.setData(interval, { ...price, price: e.target.value })} /></label>
                    <label>Stripe Price ID <input className="ls-input" required disabled={!!plan} value={price.stripe_id} onChange={e => form.setData(interval, { ...price, stripe_id: e.target.value })} placeholder="price_..." /></label>
                </div>}
            </fieldset>;
        })}
        <fieldset><legend>Plan features</legend>
            <p>These features are defined by your developer. Limits do not track usage automatically.</p>
            {Object.entries(definitions).map(([key, definition]) => <label key={key} className="ls-input-label">
                {definition.label ?? key}{definition.unit ? ` (${definition.unit})` : ''}
                {definition.type === 'boolean'
                    ? <input type="checkbox" checked={!!form.data.features[key]} onChange={e => form.setData('features', { ...form.data.features, [key]: e.target.checked })} />
                    : <input className="ls-input" type="number" min={definition.allow_unlimited ? -1 : 0} step={definition.type === 'integer' ? 1 : 'any'} value={Number(form.data.features[key])} onChange={e => form.setData('features', { ...form.data.features, [key]: Number(e.target.value) })} />}
                {definition.allow_unlimited && <small>Use -1 for unlimited.</small>}
            </label>)}
        </fieldset>
        {Object.entries(form.errors).length > 0 && <ul role="alert">{Object.entries(form.errors).map(([key, error]) => <li key={key}>{key}: {error}</li>)}</ul>}
        <div className="ls-row">
            <button className="ls-btn ls-btn--accent" type="submit" disabled={form.processing}>Save plan</button>
            {plan && <button className="ls-btn" type="button" onClick={done}>Cancel</button>}
        </div>
    </form>;
}

export default function PlansAdmin() {
    const { plans, definitions, plans_url, larasaas } = usePage<{ plans: Plan[]; definitions: Record<string, Definition>; plans_url: string; larasaas: any }>().props;
    const [editing, setEditing] = useState<Plan>();
    const [revision, setRevision] = useState(0);
    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);
    const done = () => { setEditing(undefined); setRevision(value => value + 1); };
    return <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
        <Head title="Plans" />
        <header className="ls-page__header"><h1 className="ls-page__title">Plans</h1><p className="ls-page__subtitle">Manage prices and assign developer-defined features. Changes are saved in the database.</p></header>
        <div className="ls-admin-list">
            {plans.length === 0 && <p>No plans yet. Create your first plan below.</p>}
            {plans.map(plan => <div key={plan.id} className="ls-admin-row">
                <div className="ls-admin-row__main"><strong>{plan.name}</strong><small>{plan.key} · {plan.active ? 'Active' : 'Archived'}</small></div>
                <button className="ls-btn ls-btn--sm" onClick={() => setEditing(plan)}>Edit</button>
                {plan.active && <button className="ls-btn ls-btn--sm" onClick={() => {
                    if (window.confirm('Archive this plan? Existing subscribers keep their entitlements.')) router.delete(`${plans_url}/${plan.id}`, { preserveScroll: true, onSuccess: done });
                }}>Archive</button>}
            </div>)}
        </div>
        <PlanEditor key={`${editing?.id ?? 'new'}-${revision}`} plan={editing} definitions={definitions} url={plans_url} done={done} />
    </div>;
}
