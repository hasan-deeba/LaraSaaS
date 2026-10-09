<?php

namespace LaraSaaS\Http\Controllers;

use LaraSaaS\Events\SubscriptionChanged;
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

    protected function handleCustomerSubscriptionUpdated(array $payload): Response
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
        $response = parent::handleInvoicePaymentSucceeded($payload);

        $this->fire('invoice.payment_succeeded', $payload);

        return $response;
    }

    protected function fire(string $event, array $payload): void
    {
        SubscriptionChanged::dispatch($event, $payload, $payload['data']['object']['customer'] ?? null);
    }
}
