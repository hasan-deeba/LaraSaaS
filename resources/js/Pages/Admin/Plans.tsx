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
    const errors = form.errors as Record<string, string>;
    const errorFor = (key: string) => errors[key];
    const inputClass = (key: string) => `ls-input${errorFor(key) ? ' is-invalid' : ''}`;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); done(); } };
        if (plan?.id) form.put(`${url}/${plan.id}`, options);
        else form.post(url, options);
    };

    const field = (name: string, label: string, control: React.ReactNode, hint?: string) => (
        <label className={`ls-field${errorFor(name) ? ' has-error' : ''}`}>
            <span className="ls-field__label">{label}</span>
            {control}
            {hint && <small className="ls-field__hint">{hint}</small>}
            {errorFor(name) && <small className="ls-field__error">{errorFor(name)}</small>}
        </label>
    );

    return (
        <form className="ls-card ls-form ls-plan-editor" onSubmit={submit}>
            <div className="ls-plan-editor__heading">
                <div>
                    <p className="ls-eyebrow">{plan ? 'Plan settings' : 'New offering'}</p>
                    <h2>{plan ? `Edit ${plan.name}` : 'Create a plan'}</h2>
                </div>
                <button className="ls-icon-btn" type="button" onClick={done} aria-label="Close plan editor">×</button>
            </div>

            {Object.keys(errors).length > 0 && (
                <div className="ls-form-error" role="alert">
                    <strong>Please review the highlighted fields.</strong>
                    <ul>{Object.entries(errors).map(([key, message]) => <li key={key}>{key}: {message}</li>)}</ul>
                </div>
            )}

            <section className="ls-editor-section">
                <h3>Plan details</h3>
                <div className="ls-form__grid">
                    {field('key', 'Plan key', <input className={inputClass('key')} required disabled={!!plan} value={form.data.key} onChange={e => form.setData('key', e.target.value)} placeholder="starter" />, 'A stable identifier, such as starter or pro.')}
                    {field('name', 'Display name', <input className={inputClass('name')} required value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="Starter" />)}
                    {field('badge', 'Badge', <input className={inputClass('badge')} value={form.data.badge} onChange={e => form.setData('badge', e.target.value)} placeholder="Most popular" />)}
                    {field('trial_days', 'Trial days', <input className={inputClass('trial_days')} type="number" min="0" max="730" value={form.data.trial_days ?? ''} onChange={e => form.setData('trial_days', e.target.value)} placeholder="Use default" />, 'Leave blank to use the configured default.')}
                    {field('sort_order', 'Display order', <input className={inputClass('sort_order')} type="number" min="0" value={form.data.sort_order} onChange={e => form.setData('sort_order', Number(e.target.value))} />)}
                </div>
                {field('description', 'Short description', <textarea className={inputClass('description')} rows={3} value={form.data.description} onChange={e => form.setData('description', e.target.value)} placeholder="Who this plan is for" />)}
                <div className="ls-option-grid">
                    <label className="ls-option-card">
                        <span><strong>Available for purchase</strong><small>Allow new customers to subscribe to this plan.</small></span>
                        <input type="checkbox" checked={form.data.active} onChange={e => form.setData('active', e.target.checked)} />
                    </label>
                    <label className="ls-option-card">
                        <span><strong>Highlight plan</strong><small>Give this plan extra emphasis on the pricing page.</small></span>
                        <input type="checkbox" checked={form.data.highlight} onChange={e => form.setData('highlight', e.target.checked)} />
                    </label>
                </div>
            </section>

            <section className="ls-editor-section">
                <div className="ls-editor-section__heading">
                    <div><h3>Billing intervals</h3><p>Enter Stripe recurring Price IDs in the same Stripe account and mode as your app.</p></div>
                </div>
                <div className="ls-interval-grid">
                    {(['monthly', 'yearly'] as const).map(interval => {
                        const price = form.data[interval];
                        return (
                            <fieldset key={interval} className={`ls-interval-card${errorFor(interval) || errorFor(`${interval}.stripe_id`) ? ' has-error' : ''}`}>
                                <legend>{interval === 'monthly' ? 'Monthly' : 'Yearly'}</legend>
                                <label className="ls-option-card ls-option-card--compact">
                                    <span><strong>Offer {interval} billing</strong></span>
                                    <input type="checkbox" disabled={!!plan} checked={!!price} onChange={e => form.setData(interval, e.target.checked ? { price: 0, stripe_id: '' } : null)} />
                                </label>
                                {price && <div className="ls-form__grid">
                                    {field(`${interval}.price`, 'Display price', <input className={inputClass(`${interval}.price`)} type="number" min="0" step="0.01" required value={price.price} onChange={e => form.setData(interval, { ...price, price: e.target.value })} />)}
                                    {field(`${interval}.stripe_id`, 'Stripe Price ID', <input className={inputClass(`${interval}.stripe_id`)} required disabled={!!plan} value={price.stripe_id} onChange={e => form.setData(interval, { ...price, stripe_id: e.target.value })} placeholder="price_..." />)}
                                </div>}
                                {(errorFor(interval) || errorFor(`${interval}.stripe_id`)) && <small className="ls-field__error">{errorFor(interval) ?? errorFor(`${interval}.stripe_id`)}</small>}
                            </fieldset>
                        );
                    })}
                </div>
                <p className="ls-field__hint">Display prices are for the pricing page. Stripe charges the amount configured on the Price ID. To replace a Stripe price, archive this plan and create a new one.</p>
            </section>

            <section className="ls-editor-section">
                <div className="ls-editor-section__heading">
                    <div><h3>Plan features</h3><p>Set the entitlements included with this subscription.</p></div>
                </div>
                <div className="ls-feature-grid">
                    {Object.entries(definitions).map(([key, definition]) => (
                        <div key={key} className={`ls-feature-card${errorFor(`features.${key}`) ? ' has-error' : ''}`}>
                            <div className="ls-feature-card__copy">
                                <span className="ls-feature-card__key">{key}</span>
                                <strong>{definition.label ?? key}{definition.unit ? ` (${definition.unit})` : ''}</strong>
                                <small>{definition.type === 'boolean' ? 'Included with this plan' : 'Maximum allowance for this plan'}</small>
                            </div>
                            {definition.type === 'boolean' ? (
                                <label className="ls-switch" aria-label={`${definition.label ?? key} included`}>
                                    <input type="checkbox" checked={!!form.data.features[key]} onChange={e => form.setData('features', { ...form.data.features, [key]: e.target.checked })} />
                                    <span aria-hidden="true" />
                                </label>
                            ) : (
                                <div className="ls-feature-card__number">
                                    <input className={inputClass(`features.${key}`)} type="number" min={definition.allow_unlimited ? -1 : 0} step={definition.type === 'integer' ? 1 : 'any'} value={Number(form.data.features[key])} onChange={e => form.setData('features', { ...form.data.features, [key]: Number(e.target.value) })} />
                                    {definition.unit && <span>{definition.unit}</span>}
                                    {definition.allow_unlimited && <small>-1 = unlimited</small>}
                                </div>
                            )}
                            {errorFor(`features.${key}`) && <small className="ls-field__error">{errorFor(`features.${key}`)}</small>}
                        </div>
                    ))}
                    {Object.keys(definitions).length === 0 && <p className="ls-empty">No plan features are configured yet.</p>}
                </div>
            </section>

            <div className="ls-plan-editor__footer">
                <button className="ls-btn ls-btn--accent" type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save plan'}</button>
                <button className="ls-btn ls-btn--ghost" type="button" onClick={done}>Cancel</button>
            </div>
        </form>
    );
}

export default function PlansAdmin() {
    const { plans, definitions, plans_url, larasaas } = usePage<{ plans: Plan[]; definitions: Record<string, Definition>; plans_url: string; larasaas: any }>().props;
    const [editing, setEditing] = useState<Plan>();
    const [creating, setCreating] = useState(false);
    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);
    const closeEditor = () => { setEditing(undefined); setCreating(false); };
    const edit = (plan: Plan) => { setCreating(false); setEditing(plan); };
    const create = () => { setEditing(undefined); setCreating(true); };

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page ls-admin-page">
            <Head title="Plans" />
            <header className="ls-admin-page__header">
                <div><p className="ls-eyebrow">Billing catalog</p><h1 className="ls-page__title">Plans</h1><p className="ls-page__subtitle">Manage the plans and billing intervals customers can choose.</p></div>
                <button className="ls-btn ls-btn--accent" type="button" onClick={create}>＋ Create plan</button>
            </header>

            <div className="ls-admin-list">
                {plans.length === 0 ? (
                    <div className="ls-admin-empty"><span className="ls-admin-empty__icon">＋</span><h2>No plans yet</h2><p>Create a plan to start showing pricing to customers.</p><button className="ls-btn ls-btn--accent" type="button" onClick={create}>Create your first plan</button></div>
                ) : plans.map(plan => (
                    <article key={plan.id} className="ls-admin-row">
                        <div className="ls-admin-row__main">
                            <div className="ls-admin-row__title"><strong>{plan.name}</strong><span className={`ls-status ${plan.active ? 'is-active' : 'is-archived'}`}>{plan.active ? 'Available' : 'Archived'}</span>{plan.highlight && <span className="ls-status is-highlighted">Highlighted</span>}</div>
                            <small>{plan.key}{plan.description ? ` · ${plan.description}` : ''}</small>
                            <div className="ls-admin-row__meta">
                                {plan.monthly && <span>Monthly · {plan.monthly.price}</span>}
                                {plan.yearly && <span>Yearly · {plan.yearly.price}</span>}
                                <span>{Object.values(plan.features ?? {}).filter(Boolean).length} configured entitlements</span>
                            </div>
                        </div>
                        <div className="ls-admin-row__actions">
                            <button className="ls-btn ls-btn--sm" type="button" onClick={() => edit(plan)}>Edit</button>
                            {plan.active && <button className="ls-btn ls-btn--sm ls-btn--danger" type="button" onClick={() => {
                                if (window.confirm('Archive this plan? Existing subscribers keep their subscriptions.')) router.delete(`${plans_url}/${plan.id}`, { preserveScroll: true, onSuccess: closeEditor });
                            }}>Archive</button>}
                        </div>
                    </article>
                ))}
            </div>

            {(creating || editing) && <PlanEditor key={editing?.id ?? 'new'} plan={editing} definitions={definitions} url={plans_url} done={closeEditor} />}
        </div>
    );
}
