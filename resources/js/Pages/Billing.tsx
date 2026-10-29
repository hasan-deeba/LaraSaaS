import React, { useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import PricingTable, { PricingConfig, SubscriptionState } from '../components/PricingTable';
import { bootLaraSaaS, t } from '../larasaas';

interface Invoice {
    id: string;
    date: string;
    total: string;
    status: string;
    pdf?: string;
}

/**
 * GET /app/billing → subscription management: current plan state, swaps,
 * cancel/resume, Stripe customer portal and invoice history.
 */
export default function Billing() {
    const { pricing, subscription, invoices, larasaas } = usePage<{
        pricing: PricingConfig;
        subscription: SubscriptionState;
        invoices: Invoice[];
        larasaas: any;
    }>().props;

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    const webBase = larasaas?.web_base ?? '/app';
    const portal = () => router.get(`${webBase}/billing/portal`);
    const cancel = () => router.post(`${webBase}/billing/cancel`);
    const resume = () => router.post(`${webBase}/billing/resume`);
    const selectPlan = (key: string, interval: string) => {
        const action = subscription?.subscribed ? 'swap' : 'subscribe';
        router.post(`${webBase}/billing/${action}/${key}`, { interval });
    };

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
            <Head title={t('billing.title', 'Billing')} />

            <header className="ls-page__header">
                <h1 className="ls-page__title">{t('billing.title', 'Billing')}</h1>
                <p className="ls-page__subtitle">{t('billing.subtitle', 'Manage your subscription, payment methods and invoices.')}</p>
            </header>

            <section className="ls-card">
                <div className="ls-card__row">
                    <div>
                        <p className="ls-card__label">{t('billing.status', 'Status')}</p>
                        <p className="ls-card__value">
                            {subscription?.subscribed
                                ? subscription.on_grace_period
                                    ? t('billing.cancels_on', 'Cancels on {date}').replace('{date}', subscription.ends_at ?? '')
                                    : t('billing.active', 'Active')
                                : t('billing.no_subscription', 'No active subscription')}
                        </p>
                    </div>

                    <div className="ls-card__actions">
                        {subscription?.subscribed ? (
                            <>
                                <button className="ls-btn" onClick={portal} type="button">
                                    {t('billing.portal', 'Customer portal')}
                                </button>
                                {subscription.on_grace_period ? (
                                    <button className="ls-btn ls-btn--accent" onClick={resume} type="button">
                                        {t('billing.resume', 'Resume subscription')}
                                    </button>
                                ) : (
                                    <button className="ls-btn ls-btn--ghost" onClick={cancel} type="button">
                                        {t('billing.cancel', 'Cancel subscription')}
                                    </button>
                                )}
                            </>
                        ) : (
                            <a className="ls-btn ls-btn--accent" href={`${webBase}/pricing`}>
                                {t('pricing.get_started', 'Get started')}
                            </a>
                        )}
                    </div>
                </div>
            </section>

            <h2 className="ls-section-title">{t('billing.change_plan', 'Change plan')}</h2>
            <PricingTable pricing={pricing} subscription={subscription} onSelect={selectPlan} />

            <h2 className="ls-section-title">{t('billing.invoices', 'Invoices')}</h2>
            <section className="ls-card">
                {invoices?.length ? (
                    <table className="ls-table">
                        <thead>
                            <tr>
                                <th>{t('billing.date', 'Date')}</th>
                                <th>{t('billing.amount', 'Amount')}</th>
                                <th>{t('billing.status', 'Status')}</th>
                                <th aria-label="download" />
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((invoice) => (
                                <tr key={invoice.id}>
                                    <td>{invoice.date}</td>
                                    <td>{invoice.total}</td>
                                    <td>{invoice.status}</td>
                                    <td>
                                        {invoice.pdf && (
                                            <a href={invoice.pdf} target="_blank" rel="noreferrer">
                                                PDF
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="ls-empty">{t('billing.no_invoices', 'No invoices yet.')}</p>
                )}
            </section>
        </div>
    );
}
