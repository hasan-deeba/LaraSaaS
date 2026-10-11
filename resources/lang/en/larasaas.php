<?php

return [

    'common' => [
        'save'   => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'dismiss'=> 'Dismiss',
        'close'  => 'Close',
    ],

    'dashboard' => [
        'title'          => 'Dashboard',
        'greeting'       => 'Welcome back, :name',
        'plan'           => 'Plan',
        'status'         => 'Status',
        'manage_billing' => 'Manage billing',
        'upgrade'        => 'Upgrade',
        'trial_until'    => 'Trial until :date',
        'beta_ready'     => 'Enabled for you',
    ],

    'pricing' => [
        'title'          => 'Pricing',
        'subtitle'       => 'Start free, scale when you do. Cancel anytime.',
        'monthly'        => 'Monthly',
        'yearly'         => 'Yearly',
        'per_monthly'    => 'mo',
        'per_yearly'     => 'yr',
        'per_month_short'=> 'mo',
        'billed_yearly'  => 'billed yearly',
        'most_popular'   => 'Most popular',
        'current_plan'   => 'Current plan',
        'switch_plan'    => 'Switch plan',
        'get_started'    => 'Get started',
        'contact_sales'  => 'Talk to sales',
        'custom'         => 'Custom',
        'trial'          => '{days}-day free trial',
        'footnote'       => 'Prices in {currency}. Taxes may apply. Secure checkout by Stripe.',
    ],

    'billing' => [
        'title'                => 'Billing',
        'subtitle'             => 'Manage your subscription, payment methods and invoices.',
        'status'               => 'Status',
        'active'               => 'Active',
        'free'                 => 'Free',
        'no_subscription'      => 'No active subscription',
        'subscription_required'=> 'A subscription is required to access this area.',
        'plan_required'        => 'Your current plan does not include this feature.',
        'missing_price'        => 'Plan :plan has no Stripe price id configured for :interval.',
        'portal'               => 'Customer portal',
        'cancel'               => 'Cancel subscription',
        'resume'               => 'Resume subscription',
        'canceled'             => 'Subscription canceled — you keep access until the period ends.',
        'checkout_canceled'    => 'Checkout canceled — no charge was made.',
        'success'              => 'Your subscription is active. Welcome aboard!',
        'on_trial'             => 'Trial',
        'cancels_on'           => 'Cancels on :date',
        'ends_on'              => 'Ends on :date',
        'resumed'              => 'Subscription resumed. Welcome back!',
        'swapped'              => 'Plan updated successfully.',
        'change_plan'          => 'Change plan',
        'invoices'             => 'Invoices',
        'no_invoices'          => 'No invoices yet.',
        'date'                 => 'Date',
        'amount'               => 'Amount',
    ],

    'announcements' => [
        'created' => 'Announcement broadcasted.',
        'updated' => 'Announcement updated.',
        'deleted' => 'Announcement removed.',
    ],

    'flags' => [
        'unavailable' => 'This feature is not available for your account yet.',
        'activated'   => 'Flag :flag activated.',
        'deactivated' => 'Flag :flag deactivated.',
    ],

    'embed' => [
        'not_authenticated' => 'This embed link is missing or expired. Ask the host application to generate a fresh one with LaraSaaS::embedUrl().',
        'powered_by'        => 'Secured by :app',
    ],
];
