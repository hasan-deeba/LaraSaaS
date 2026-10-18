import React, { useMemo, useState } from 'react';
import { LaraSaaSIcon, t } from '../larasaas';

/**
 * <PricingTable /> — every row, price, badge and CTA is generated from
 * admin-managed plans and pricing display settings. It inherits the host's dark mode (.dark
 * class), typography and accent (--larasaas-accent) automatically.
 */

export interface PlanPrice {
    price: number | string | null;
    stripe_id?: string | null;
}

export interface Plan {
    name: string;
    description?: string;
    highlight?: boolean;
    badge?: string | null;
    trial_days?: number | null;
    monthly?: PlanPrice;
    yearly?: PlanPrice;
    features?: string[];
    entitlements?: Record<string, boolean | number>;
    limits?: Record<string, number>;
}

export interface PricingConfig {
    currency: string;
    currencies: Record<string, string>;
    intervals: string[];
    default_interval: string;
    yearly_savings_badge?: string | null;
    trial_days?: number | null;
    contact_sales?: { enabled: boolean; label?: string; url: string } | null;
    plans: Record<string, Plan>;
}

export interface SubscriptionState {
    subscribed?: boolean;
    on_grace_period?: boolean;
    ends_at?: string | null;
    current_price_id?: string | null;
    plan?: string | null;
}

interface Props {
    pricing: PricingConfig;
    subscription?: SubscriptionState | null;
    onSelect?: (planKey: string, interval: string) => void;
    initialInterval?: string;
    className?: string;
}

export default function PricingTable({ pricing, subscription, onSelect, initialInterval, className = '' }: Props) {
    const intervals = pricing.intervals?.length ? pricing.intervals : ['monthly'];
    const [interval, setInterval] = useState(initialInterval ?? pricing.default_interval ?? intervals[0]);

    const symbol = pricing.currencies?.[pricing.currency] ?? pricing.currency.toUpperCase() + ' ';
    const plans = useMemo(() => Object.entries(pricing.plans ?? {}), [pricing.plans]);

    const monthlyEquivalent = (plan: Plan): string | null => {
        const price = plan[interval as 'monthly' | 'yearly']?.price;
        if (interval !== 'yearly' || typeof price !== 'number') return null;
        return (price / 12).toLocaleString(undefined, { maximumFractionDigits: 2 });
    };

    const isCurrent = (key: string, plan: Plan): boolean => {
        if (!subscription?.subscribed) return false;
        const priceId = plan[interval as 'monthly' | 'yearly']?.stripe_id;
        return subscription.current_price_id === priceId || (subscription.plan === key && subscription.current_price_id === priceId);
    };

    return (
        <div data-larasaas dir={undefined} className={`ls-pricing ${className}`} style={{ fontFamily: 'inherit' }}>
            {intervals.length > 1 && (
                <div className="ls-pricing__toggle" role="tablist" aria-label="Billing interval">
                    {intervals.map((value) => (
                        <button
                            key={value}
                            role="tab"
                            aria-selected={interval === value}
                            onClick={() => setInterval(value)}
                            className={`ls-pricing__toggle-btn ${interval === value ? 'is-active' : ''}`}
                            type="button"
                        >
                            {t(`pricing.${value}`, value)}
                            {value === 'yearly' && pricing.yearly_savings_badge && (
                                <span className="ls-pricing__savings">{pricing.yearly_savings_badge}</span>
                            )}
                        </button>
                    ))}
                </div>
            )}

            <div className="ls-pricing__grid" style={{ ['--ls-columns' as any]: Math.min(plans.length, 3) }}>
                {plans.map(([key, plan]) => {
                    const entry = plan[interval as 'monthly' | 'yearly'];
                    const price = entry?.price ?? null;
                    const custom = price === null || typeof price === 'string';
                    const current = isCurrent(key, plan);
                    const equivalent = monthlyEquivalent(plan);

                    return (
                        <article
                            key={key}
                            className={`ls-plan ${plan.highlight ? 'ls-plan--highlight' : ''} ${current ? 'ls-plan--current' : ''}`}
                        >
                            {(plan.badge || (plan.highlight && !plan.badge)) && (
                                <span className="ls-plan__badge">{plan.badge ?? t('pricing.most_popular', 'Most popular')}</span>
                            )}

                            <header className="ls-plan__head">
                                <h3 className="ls-plan__name">{plan.name}</h3>
                                {plan.description && <p className="ls-plan__desc">{plan.description}</p>}
                            </header>

                            <div className="ls-plan__price">
                                {custom ? (
                                    <span className="ls-plan__amount ls-plan__amount--custom">{price ?? t('pricing.custom', 'Custom')}</span>
                                ) : (
                                    <>
                                        <span className="ls-plan__currency">{symbol}</span>
                                        <span className="ls-plan__amount">{price}</span>
                                        <span className="ls-plan__per">/{t(`pricing.per_${interval}`, interval === 'yearly' ? 'yr' : 'mo')}</span>
                                    </>
                                )}
                                {equivalent && (
                                    <span className="ls-plan__equivalent">
                                        {symbol}{equivalent}/{t('pricing.per_month_short', 'mo')} · {t('pricing.billed_yearly', 'billed yearly')}
                                    </span>
                                )}
                            </div>

                            {plan.trial_days ? (
                                <p className="ls-plan__trial">{t('pricing.trial', '{days}-day free trial').replace('{days}', String(plan.trial_days))}</p>
                            ) : null}

                            <ul className="ls-plan__features">
                                {(plan.features ?? []).map((feature) => (
                                    <li key={feature}>
                                        <span className="ls-plan__check"><LaraSaaSIcon name="check" size={14} /></span>
                                        <span>{feature}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="ls-plan__cta">
                                {custom && pricing.contact_sales?.enabled ? (
                                    <a className="ls-btn ls-btn--ghost" href={pricing.contact_sales.url}>
                                        {pricing.contact_sales.label ?? t('pricing.contact_sales', 'Talk to sales')}
                                    </a>
                                ) : current ? (
                                    <button className="ls-btn ls-btn--ghost" disabled type="button">
                                        {subscription?.on_grace_period
                                            ? t('billing.ends_on', 'Ends on {date}').replace('{date}', subscription.ends_at ?? '')
                                            : t('pricing.current_plan', 'Current plan')}
                                    </button>
                                ) : (
                                    <button
                                        className={`ls-btn ${plan.highlight ? 'ls-btn--accent' : ''}`}
                                        type="button"
                                        disabled={!entry?.stripe_id}
                                        onClick={() => onSelect?.(key, interval)}
                                    >
                                        {subscription?.subscribed
                                            ? t('pricing.switch_plan', 'Switch plan')
                                            : t('pricing.get_started', 'Get started')}
                                    </button>
                                )}
                            </div>
                        </article>
                    );
                })}
            </div>
        </div>
    );
}
