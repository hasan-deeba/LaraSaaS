<?php

namespace LaraSaaS\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the package's Stripe webhook controller whenever a subscription
 * is created, updated or deleted. Listen to it from the host app to sync
 * workspace entitlements, send lifecycle e-mails, warm caches, …
 */
class SubscriptionChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $stripeEvent,       // e.g. "customer.subscription.updated"
        public array $payload,            // raw Stripe object
        public ?string $stripeId = null,  // customer id
    ) {}
}
