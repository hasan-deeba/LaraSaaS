@extends('larasaas::embed.layout')

@section('title', __('larasaas::larasaas.pricing.title'))

@section('content')
    <div class="ls-hero">
        <h1>{{ __('larasaas::larasaas.pricing.title') }}</h1>
        <p>{{ __('larasaas::larasaas.pricing.subtitle') }}</p>
    </div>

    @if (count($intervals) > 1)
        <div style="text-align:center">
            <div class="ls-toggle">
                @foreach ($intervals as $key => $url)
                    <a href="{{ $url }}" class="{{ $key === $interval ? 'is-active' : '' }}">
                        {{ __('larasaas::larasaas.pricing.' . $key) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ls-grid">
        @foreach ($pricing['plans'] as $key => $plan)
            @php
                $amount    = $plan[$interval]['price'] ?? null;
                $priceId   = $plan[$interval]['stripe_id'] ?? null;
                $custom    = $amount === null || is_string($amount);
                $symbol    = $pricing['currencies'][$pricing['currency']] ?? strtoupper($pricing['currency']);
                $isCurrent = ! empty($subscription['subscribed'])
                    && $priceId !== null
                    && ($subscription['current_price_id'] ?? null) === $priceId;
                $perLabel  = __('larasaas::larasaas.pricing.' . ($interval === 'yearly' ? 'per_yearly' : 'per_monthly'));
                $trialDays = $plan['trial_days'] ?? $pricing['trial_days'] ?? 0;
                $canTrial  = $trialDays && empty($subscription['subscribed']) && empty($subscription['stripe_status']);
            @endphp
            <div class="ls-card {{ ! empty($plan['highlight']) ? 'is-highlight' : '' }}">
                <div>
                    @if (! empty($plan['badge']))
                        <span class="ls-badge">{{ $plan['badge'] }}</span>
                    @endif
                    @if ($isCurrent)
                        <span class="ls-badge is-ok">{{ __('larasaas::larasaas.pricing.current_plan') }}</span>
                    @endif
                </div>

                <h3>{{ $plan['name'] }}</h3>
                <p class="ls-desc">{{ $plan['description'] ?? '' }}</p>

                <div class="ls-price">
                    @if ($custom)
                        <span class="ls-amount">{{ $amount ?? __('larasaas::larasaas.pricing.custom') }}</span>
                    @else
                        <span class="ls-amount">{{ $symbol }}{{ $amount }}</span>
                        <span class="ls-per">/ {{ $perLabel }}</span>
                    @endif
                </div>
                @if ($interval === 'yearly' && ! $custom && ! empty($pricing['yearly_savings_badge']))
                    <div class="ls-yearly-note">{{ $pricing['yearly_savings_badge'] }}</div>
                @endif

                @if ($canTrial && ! $isCurrent)
                    <div class="ls-trial">{{ str_replace('{days}', $trialDays, __('larasaas::larasaas.pricing.trial')) }}</div>
                @endif

                <ul class="ls-features">
                    @foreach ($plan['features'] ?? [] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>

                @if ($custom && ! empty($pricing['contact_sales']['enabled']))
                    <a class="ls-btn is-ghost"
                       href="{{ $pricing['contact_sales']['url'] }}" target="_blank" rel="noopener">
                        {{ $pricing['contact_sales']['label'] }}
                    </a>
                @elseif ($user && ! empty($subscription['subscribed']))
                    @if ($isCurrent || empty($priceId))
                        <button type="button" class="ls-btn" disabled>{{ __('larasaas::larasaas.pricing.current_plan') }}</button>
                    @else
                        <a class="ls-btn" href="{{ $preview[$key] ?? '#' }}">{{ __('larasaas::larasaas.pricing.switch_plan') }}</a>
                    @endif
                @elseif ($user)
                    <form method="POST" action="{{ $subscribe[$key] ?? '#' }}" target="_top">
                        <input type="hidden" name="interval" value="{{ $interval }}">
                        <button type="submit" class="ls-btn" {{ empty($priceId) ? 'disabled' : '' }}>
                            {{ __('larasaas::larasaas.pricing.get_started') }}
                        </button>
                    </form>
                @else
                    <a class="ls-btn" href="{{ config('larasaas.login_url', '/login') }}" target="_top">{{ __('larasaas::larasaas.pricing.sign_in') }}</a>
                @endif
            </div>
        @endforeach
    </div>

    <p class="ls-muted" style="text-align:center; margin-top:22px; font-size:12.5px">
        {{ str_replace('{currency}', strtoupper($pricing['currency']), __('larasaas::larasaas.pricing.footnote')) }}
    </p>
@endsection
