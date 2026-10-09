import React, { useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AnnouncementStack from '../components/AnnouncementStack';
import { FeatureGate, LaraSaaSFeaturesProvider } from '../components/FeatureGate';
import { bootLaraSaaS, t } from '../larasaas';

interface Stats {
    subscription: { subscribed?: boolean; plan?: string | null; on_trial?: boolean; trial_ends_at?: string | null };
    announcements: number;
    active_features: string[];
    plan?: string | null;
}

/** GET /app/dashboard → DashboardController */
export default function Dashboard() {
    const { greeting, stats, larasaas } = usePage<{ greeting: string; stats: Stats; larasaas: any }>().props;

    useEffect(() => bootLaraSaaS(larasaas), [larasaas]);

    return (
        <LaraSaaSFeaturesProvider initial={larasaas?.features}>
            <div data-larasaas dir={larasaas?.dir ?? 'ltr'} className="ls-page">
                <Head title={t('dashboard.title', 'Dashboard')} />
                <AnnouncementStack variant="inline" />

                <header className="ls-page__header">
                    <h1 className="ls-page__title">{greeting || t('dashboard.title', 'Dashboard')}</h1>
                </header>

                <div className="ls-stat-grid">
                    <section className="ls-card">
                        <p className="ls-card__label">{t('dashboard.plan', 'Plan')}</p>
                        <p className="ls-card__value ls-card__value--accent">{stats?.plan ?? t('billing.free', 'Free')}</p>
                        <a className="ls-card__link" href="/app/billing">{t('dashboard.manage_billing', 'Manage billing')}</a>
                    </section>

                    <section className="ls-card">
                        <p className="ls-card__label">{t('dashboard.status', 'Status')}</p>
                        <p className="ls-card__value">
                            {stats?.subscription?.on_trial
                                ? t('dashboard.trial_until', 'Trial until {date}').replace('{date}', stats.subscription.trial_ends_at ?? '')
                                : stats?.subscription?.subscribed
                                  ? t('billing.active', 'Active')
                                  : t('billing.no_subscription', 'No active subscription')}
                        </p>
                        {!stats?.subscription?.subscribed && (
                            <a className="ls-card__link" href="/app/pricing">{t('dashboard.upgrade', 'Upgrade')}</a>
                        )}
                    </section>

                    <FeatureGate flag="ai-assistant">
                        <section className="ls-card ls-card--accent">
                            <p className="ls-card__label">AI Assistant</p>
                            <p className="ls-card__value">{t('dashboard.beta_ready', 'Enabled for you')}</p>
                        </section>
                    </FeatureGate>
                </div>
            </div>
        </LaraSaaSFeaturesProvider>
    );
}
