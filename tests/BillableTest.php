<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use LaraSaaS\LaraSaaS;
use Laravel\Cashier\Billable;
use LogicException;

class BillableTest extends TestCase
{
    public function test_guests_have_no_billable_or_subscription(): void
    {
        $request = $this->requestFor(null);

        $this->assertNull(app(LaraSaaS::class)->billable($request));
        $this->assertSame(['subscribed' => false], app(LaraSaaS::class)->subscriptionForUi($request));
    }

    public function test_user_without_trait_gets_actionable_error_before_subscription_lookup(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[' . User::class . '] model to use the Laravel\\Cashier\\Billable trait');

        app(LaraSaaS::class)->subscriptionForUi($this->requestFor(new User));
    }

    public function test_direct_inherited_and_nested_traits_are_supported(): void
    {
        foreach ([new BillingUser, new InheritedBillingUser, new NestedBillingUser] as $user) {
            $this->assertSame($user, app(LaraSaaS::class)->billable($this->requestFor($user)));
        }
    }

    public function test_workspace_resolver_validates_workspace_not_the_user(): void
    {
        $workspace = new BillingWorkspace;
        config([
            'larasaas.billing.billable' => 'workspace',
            'larasaas.billing.workspace_resolver' => fn ($user) => $workspace,
        ]);

        $this->assertSame($workspace, app(LaraSaaS::class)->billable($this->requestFor(new User)));
    }

    public function test_workspace_without_trait_gets_actionable_error(): void
    {
        config([
            'larasaas.billing.billable' => 'workspace',
            'larasaas.billing.workspace_resolver' => fn ($user) => new PlainWorkspace,
        ]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[' . PlainWorkspace::class . '] model to use the Laravel\\Cashier\\Billable trait');

        app(LaraSaaS::class)->billable($this->requestFor(new BillingUser));
    }

    public function test_workspace_method_is_also_validated(): void
    {
        config(['larasaas.billing.billable' => 'workspace']);
        $user = new WorkspaceUser;
        $user->workspace = new BillingWorkspace;
        $this->assertSame($user->workspace, app(LaraSaaS::class)->billable($this->requestFor($user)));

        $user->workspace = new PlainWorkspace;
        $this->expectException(LogicException::class);
        app(LaraSaaS::class)->billable($this->requestFor($user));
    }

    public function test_absent_workspace_is_still_null(): void
    {
        config([
            'larasaas.billing.billable' => 'workspace',
            'larasaas.billing.workspace_resolver' => fn ($user) => null,
        ]);
        $request = $this->requestFor(new BillingUser);
        $this->assertNull(app(LaraSaaS::class)->billable($request));
        $this->assertSame(['subscribed' => false], app(LaraSaaS::class)->subscriptionForUi($request));
    }

    public function test_workspace_mode_without_resolver_retains_validated_user_fallback(): void
    {
        config(['larasaas.billing.billable' => 'workspace']);
        $user = new BillingUser;
        $this->assertSame($user, app(LaraSaaS::class)->billable($this->requestFor($user)));

        $this->expectException(LogicException::class);
        app(LaraSaaS::class)->billable($this->requestFor(new User));
    }

    private function requestFor(?User $user): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

class BillingUser extends User
{
    use Billable;
}

class InheritedBillingUser extends BillingUser {}

trait NestedBilling
{
    use Billable;
}

class NestedBillingUser extends User
{
    use NestedBilling;
}

class PlainWorkspace extends Model {}

class BillingWorkspace extends Model
{
    use Billable;
}

class WorkspaceUser extends User
{
    public ?Model $workspace = null;

    public function currentWorkspace(): ?Model
    {
        return $this->workspace;
    }
}
