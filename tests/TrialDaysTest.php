<?php

namespace LaraSaaS\Tests;

use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\BillingActions;

class TrialDaysTest extends TestCase
{
    public function test_first_subscription_uses_global_trial_when_plan_has_none(): void
    {
        config(['larasaas.billing.trial_days' => 14]);

        $this->assertSame(14, $this->actions(subscribedBefore: false)->trial(['trial_days' => null]));
    }

    public function test_plan_trial_overrides_global_trial(): void
    {
        config(['larasaas.billing.trial_days' => 14]);

        $this->assertSame(30, $this->actions(subscribedBefore: false)->trial(['trial_days' => 30]));
        $this->assertSame(0, $this->actions(subscribedBefore: false)->trial(['trial_days' => 0]));
    }

    public function test_returning_customers_never_get_a_second_trial(): void
    {
        config(['larasaas.billing.trial_days' => 14]);

        $this->assertSame(0, $this->actions(subscribedBefore: true)->trial(['trial_days' => null]));
        $this->assertSame(0, $this->actions(subscribedBefore: true)->trial(['trial_days' => 30]));
    }

    public function test_disabled_global_trial(): void
    {
        config(['larasaas.billing.trial_days' => null]);

        $this->assertSame(0, $this->actions(subscribedBefore: false)->trial(['trial_days' => null]));
    }

    protected function actions(bool $subscribedBefore): object
    {
        return new class(app(LaraSaaS::class), $subscribedBefore) extends BillingActions
        {
            public function __construct(LaraSaaS $larasaas, private bool $before)
            {
                parent::__construct($larasaas);
            }

            public function trial(array $plan): int
            {
                return $this->trialDays(null, $plan, 'default');
            }

            protected function hasSubscribedBefore($billable, string $name): bool
            {
                return $this->before;
            }
        };
    }
}
