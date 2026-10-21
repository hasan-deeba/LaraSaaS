@extends('larasaas::embed.layout')

@section('title', __('larasaas::larasaas.billing.confirm_plan_title'))

@section('content')
    <div class="ls-hero">
        <h1>{{ __('larasaas::larasaas.billing.confirm_plan_title') }}</h1>
        <p>{{ __('larasaas::larasaas.billing.confirm_plan_summary', ['current' => $current_plan, 'target' => $target_plan['name']]) }}</p>
    </div>

    <section class="ls-section">
        <h2>{{ $target_plan['name'] }}</h2>
        <p class="ls-sub">{{ __('larasaas::larasaas.billing.confirm_plan_interval', ['interval' => __('larasaas::larasaas.pricing.' . $interval)]) }}</p>

        <dl class="ls-kv">
            <dt>{{ __('larasaas::larasaas.billing.amount_due_today') }}</dt>
            <dd>{{ $preview['amount_due_now_formatted'] }}</dd>

            @if ($preview['estimated_credit'] > 0)
                <dt>{{ __('larasaas::larasaas.billing.estimated_credit') }}</dt>
                <dd>{{ $preview['estimated_credit_formatted'] }}</dd>
            @endif

            <dt>{{ __('larasaas::larasaas.billing.new_plan_price') }}</dt>
            <dd>
                @if (is_numeric($target_plan[$interval]['price'] ?? null))
                    {{ $pricing['currencies'][$pricing['currency']] ?? strtoupper($pricing['currency']) }}{{ $target_plan[$interval]['price'] }}
                    / {{ __('larasaas::larasaas.pricing.' . ($interval === 'yearly' ? 'per_yearly' : 'per_monthly')) }}
                @else
                    {{ $target_plan[$interval]['price'] ?? __('larasaas::larasaas.pricing.custom') }}
                @endif
            </dd>

            @if ($preview['next_renewal_at'])
                <dt>{{ __('larasaas::larasaas.billing.next_renewal') }}</dt>
                <dd>{{ \Illuminate\Support\Carbon::parse($preview['next_renewal_at'])->toFormattedDateString() }}</dd>
            @endif
        </dl>

        <p class="ls-sub">{{ __('larasaas::larasaas.billing.confirm_plan_estimate_note') }}</p>
        <p class="ls-sub">{{ __('larasaas::larasaas.billing.downgrade_credit_note') }}</p>

        <div class="ls-row">
            <form method="POST" action="{{ $confirm_url }}">
                <input type="hidden" name="interval" value="{{ $interval }}">
                <button type="submit" class="ls-btn is-auto">{{ __('larasaas::larasaas.billing.confirm_plan_change') }}</button>
            </form>
            <a class="ls-btn is-auto is-ghost" href="{{ $cancel_url }}">{{ __('larasaas::larasaas.billing.back_to_pricing') }}</a>
        </div>
    </section>
@endsection
