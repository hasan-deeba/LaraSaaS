@extends('larasaas::embed.layout')

@section('title', __('larasaas::larasaas.billing.title'))

@section('content')
    <div class="ls-hero">
        <h1>{{ __('larasaas::larasaas.billing.title') }}</h1>
        <p>{{ __('larasaas::larasaas.billing.subtitle') }}</p>
    </div>

    <div class="ls-section">
        <h2>{{ __('larasaas::larasaas.billing.status') }}</h2>
        <p class="ls-sub">
            @if (! empty($subscription['subscribed']))
                @if (! empty($subscription['on_trial']))
                    <span class="ls-badge is-ok">{{ __('larasaas::larasaas.billing.on_trial') }}</span>
                @elseif (! empty($subscription['on_grace_period']))
                    <span class="ls-badge is-warn">{{ __('larasaas::larasaas.billing.cancels_on', ['date' => $subscription['ends_at'] ?? '—']) }}</span>
                @else
                    <span class="ls-badge is-ok">{{ __('larasaas::larasaas.billing.active') }}</span>
                @endif
            @else
                <span class="ls-badge is-muted">{{ __('larasaas::larasaas.billing.free') }}</span>
            @endif
        </p>

        @php
            $planKey  = $subscription['plan'] ?? null;
            $planName = $planKey ? ($pricing['plans'][$planKey]['name'] ?? $planKey) : __('larasaas::larasaas.billing.no_subscription');
        @endphp

        <dl class="ls-kv">
            <dt>{{ __('larasaas::larasaas.dashboard.plan') }}</dt>
            <dd>{{ $planName }}</dd>

            @if (! empty($subscription['on_trial']) && ! empty($subscription['trial_ends_at']))
                <dt>{{ __('larasaas::larasaas.billing.status') }}</dt>
                <dd>{{ __('larasaas::larasaas.dashboard.trial_until', ['date' => $subscription['trial_ends_at']]) }}</dd>
            @elseif (! empty($subscription['ends_at']))
                <dt>{{ __('larasaas::larasaas.billing.status') }}</dt>
                <dd>{{ ($subscription['on_grace_period'] ?? false)
                        ? __('larasaas::larasaas.billing.cancels_on', ['date' => $subscription['ends_at']])
                        : __('larasaas::larasaas.billing.ends_on', ['date' => $subscription['ends_at']]) }}</dd>
            @endif

            @if (! empty($subscription['stripe_status']))
                <dt>Stripe</dt>
                <dd>{{ $subscription['stripe_status'] }}</dd>
            @endif
        </dl>

        <div class="ls-row">
            <form method="POST" action="{{ $actions['portal'] }}" target="_top">
                <button type="submit" class="ls-btn is-auto">{{ __('larasaas::larasaas.billing.portal') }}</button>
            </form>

            @if (! empty($subscription['subscribed']) && empty($subscription['on_grace_period']))
                <form method="POST" action="{{ $actions['cancel'] }}">
                    <button type="submit" class="ls-btn is-auto is-ghost">{{ __('larasaas::larasaas.billing.cancel') }}</button>
                </form>
            @elseif (! empty($subscription['on_grace_period']))
                <form method="POST" action="{{ $actions['resume'] }}">
                    <button type="submit" class="ls-btn is-auto">{{ __('larasaas::larasaas.billing.resume') }}</button>
                </form>
            @endif

            <a class="ls-btn is-auto is-ghost" href="{{ $nav['pricing'] }}">{{ __('larasaas::larasaas.billing.change_plan') }}</a>
        </div>
    </div>

    <div class="ls-section">
        <h2>{{ __('larasaas::larasaas.billing.invoices') }}</h2>
        @if (empty($invoices))
            <p class="ls-sub" style="margin:0">{{ __('larasaas::larasaas.billing.no_invoices') }}</p>
        @else
            <table class="ls-table">
                <thead>
                    <tr>
                        <th>{{ __('larasaas::larasaas.billing.date') }}</th>
                        <th>{{ __('larasaas::larasaas.billing.amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice['date'] }}</td>
                            <td>{{ $invoice['total'] }}</td>
                            <td>
                                @if (! empty($invoice['pdf']))
                                    <a href="{{ $invoice['pdf'] }}" target="_blank" rel="noopener">PDF</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
