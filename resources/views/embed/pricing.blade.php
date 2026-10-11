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
                $symbol    = $pricing['currencies'][$pricing['currency']] ?? strtoupper($pricing['currency']);
                $isCurrent = ($subscription['plan'] ?? null) === $key && ($subscription['subscribed'] ?? false);
                $perLabel  = __('larasaas::larasaas.pricing.' . ($interval === 'yearly' ? 'per_yearly' : 'per_monthly'));
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
                    <span class="ls-amount">{{ $symbol }}{{ $amount }}</span>
                    <span class="ls-per">/ {{ $perLabel }}</span>
                </div>
                @if ($interval === 'yearly' && ! empty($pricing['yearly_savings_badge']))
                    <div class="ls-yearly-note">{{ $pricing['yearly_savings_badge'] }}</div>
                @endif

                @php($trialDays = $plan['trial_days'] ?? $pricing['trial_days'] ?? 0)
                @if ($trialDays && empty($subscription['on_trial']) && ! $isCurrent)
                    <div class="ls-trial">{{ str_replace('{days}', $trialDays, __('larasaas::larasaas.pricing.trial')) }}</div>
                @endif

                <ul class="ls-features">
                    @foreach ($plan['features'] ?? [] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>

                <form method="POST"
                      action="{{ $subscribe[$key] ?? '#' }}"
                      target="_top">
                    <input type="hidden" name="interval" value="{{ $interval }}">
                    <input type="hidden" name="success_url" value="{{ $returns['success'] }}">
                    <input type="hidden" name="cancel_url" value="{{ $returns['canceled'] }}">
                    <button type="submit" class="ls-btn" {{ $isCurrent ? 'disabled' : '' }}>
                        @if ($isCurrent)
                            {{ __('larasaas::larasaas.pricing.current_plan') }}
                        @elseif (! empty($subscription['subscribed']))
                            {{ __('larasaas::larasaas.pricing.switch_plan') }}
                        @else
                            {{ __('larasaas::larasaas.pricing.get_started') }}
                        @endif
                    </button>
                </form>

                @if (! empty($pricing['contact_sales']['enabled']) && ($plan['monthly']['price'] ?? null) === null)
                    <a class="ls-btn is-ghost" style="margin-top:10px"
                       href="{{ $pricing['contact_sales']['url'] }}" target="_blank" rel="noopener">
                        {{ $pricing['contact_sales']['label'] }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <p class="ls-muted" style="text-align:center; margin-top:22px; font-size:12.5px">
        {{ str_replace('{currency}', strtoupper($pricing['currency']), __('larasaas::larasaas.pricing.footnote')) }}
    </p>
@endsection
