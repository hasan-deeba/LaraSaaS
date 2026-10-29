<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Support\Facades\Log;
use LaraSaaS\Events\SubscriptionChanged;
use LaraSaaS\LaraSaaS;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extends Cashier's webhook controller: Stripe state is still synced by
 * Cashier, while the package fires one domain event per lifecycle change
 * so the HOST application can react (workspace limits, e-mails, caches).
 */
class StripeWebhookController extends CashierWebhookController
{
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $this->fire('customer.subscription.created', $payload);

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload): ?Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->fire('customer.subscription.updated', $payload);

        return $response;
    }

    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $this->fire('customer.subscription.deleted', $payload);

        return $response;
    }

    protected function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $response = method_exists(parent::class, 'handleInvoicePaymentSucceeded')
            ? parent::handleInvoicePaymentSucceeded($payload)
            : $this->successMethod();

        $this->fire('invoice.payment_succeeded', $payload);

        return $response;
    }

    /**
     * Every billable lookup Cashier performs for a webhook funnels through here
     * (`customer.subscription.*`, `invoice.*`, `customer.updated|deleted`,
     * `payment_method.automatically_updated`, …) and resolves customers with
     * CASHIER's own static customer model — a hardcoded `App\Models\User`
     * unless it was pointed elsewhere. When that class cannot be autoloaded,
     * `class_uses_recursive()` blows up with the cryptic
     * `class_parents(): Class App\Models\User does not exist` and Stripe sees a
     * 500, while Checkout itself kept working.
     *
     * LaraSaaS syncs Cashier to `larasaas.user_model` / the workspace model on
     * boot; this is the safety net that turns any remaining mismatch into ONE
     * actionable log line instead of a PHP class-loading error, and recovers
     * automatically when only the static was clobbered.
     *
     * Note: `Laravel\Cashier\Billable` is a PHP trait (not an interface/class),
     * so never declare `: ?Billable` here — PHP throws a `TypeError` whenever a
     * real model instance is returned.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getUserByStripeId($stripeId)
    {
        /** @var LaraSaaS $larasaas */
        $larasaas = app(LaraSaaS::class);

        if (! $this->customerModelIsUsable($larasaas)) {
            // Nothing usable configured — try to recover from our own config
            // (e.g. a host boot callback reset the static to a stale class).
            $larasaas->syncCashierCustomerModel();
        }

        if ($this->customerModelIsUsable($larasaas)) {
            return parent::getUserByStripeId($stripeId);
        }

        $cashierModel = (string) $larasaas->cashierCustomerModel();
        $billableType = $larasaas->billableType();

        Log::error(sprintf(
            'LaraSaaS: Stripe webhook could not resolve the billable customer — Cashier looks customers up '
            . 'with [%s], a class that cannot be loaded, and the configured LaraSaaS billable is [%s]. '
            . 'Point larasaas.user_model (or larasaas.billing.workspace_model when billing.billable is '
            . '"workspace") at your Billable model; LaraSaaS then points Cashier at it automatically. '
            . 'Stripe retries failed webhooks, so the event is not lost.',
            $cashierModel !== '' ? $cashierModel : 'unset',
            $billableType !== '' ? $billableType : 'unset',
        ));

        throw new \RuntimeException(sprintf(
            'LaraSaaS: Cashier customer model [%s] does not exist, so the Stripe webhook cannot find the '
            . 'billable customer. Your configured billable model is [%s] — make sure it exists and uses '
            . 'Laravel\Cashier\Billable, and that nothing overrides Cashier::useCustomerModel() with a '
            . 'different class.',
            $cashierModel !== '' ? $cashierModel : 'unset',
            $billableType !== '' ? $billableType : 'unset',
        ));
    }

    /** Can Cashier actually load the model it resolves customers with? */
    protected function customerModelIsUsable(LaraSaaS $larasaas): bool
    {
        $model = (string) $larasaas->cashierCustomerModel();

        return $model !== '' && class_exists($model);
    }

    protected function fire(string $event, array $payload): void
    {
        SubscriptionChanged::dispatch($event, $payload, $payload['data']['object']['customer'] ?? null);
    }
}
