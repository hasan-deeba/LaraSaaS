import React, { useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import PricingTable, { PricingConfig, SubscriptionState } from '../components/PricingTable';
import AnnouncementStack from '../components/AnnouncementStack';
import { bootLaraSaaS, t } from '../larasaas';

/**
 * GET /app/pricing → BillingController@pricing
 * Selecting a plan posts to the Cashier-powered subscribe endpoint,
 * which responds with an Inertia::location redirect to Stripe Checkout.
 */
type PricingLayoutProps = {
    larasaas?: { authenticated?: boolean };
};

function Pricing() {
    const { pricing, subscription, notice, larasaas } = usePage<{
        pricing: PricingConfig;
        subscription: SubscriptionState;
        notice?: string;
        larasaas: any;
    }>().props;

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    const select = (planKey: string, interval: string) => {
        if (!larasaas?.authenticated) {
            window.location.assign(larasaas?.login_url ?? '/login');
            return;
        }
        const route = subscription?.subscribed
            ? `${larasaas.web_base}/billing/swap/${planKey}`
            : `${larasaas.web_base}/billing/subscribe/${planKey}`;

        router.post(route, { interval });
    };

    return (
        <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
            <Head title={t('pricing.title', 'Pricing')} />
            {larasaas?.authenticated && <AnnouncementStack />}

            <header className="ls-page__header">
                <h1 className="ls-page__title">{t('pricing.title', 'Pricing')}</h1>
                <p className="ls-page__subtitle">{t('pricing.subtitle', 'Start free, scale when you do. Cancel anytime.')}</p>
            </header>

            {notice && <div className="ls-notice">{notice}</div>}

            <PricingTable pricing={pricing} subscription={subscription} onSelect={select} />

            <p className="ls-page__footnote">
                {t('pricing.footnote', 'Prices in {currency}. Taxes may apply. Secure checkout by Stripe.').replace(
                    '{currency}',
                    pricing.currency.toUpperCase(),
                )}
            </p>
        </div>
    );
}

// Pricing is public. If the visitor is a guest, bypass the host app's default
// authenticated layout (which may assume an auth.user exists). Signed-in users
// can continue to use the host layout.
Object.assign(Pricing, {
    layout: ({ larasaas }: PricingLayoutProps) => (larasaas?.authenticated ? {} : null),
});

export default Pricing;
