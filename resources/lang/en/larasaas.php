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
        'sign_in' => 'Sign in to choose a plan',
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
        'payment_failed'       => 'Payment could not be completed for the new plan. Please update your payment method in the customer portal and try again.',
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

    'workspaces' => [
        'title'        => 'Workspaces',
        'subtitle'     => 'Operate within parallel organizational structures — each workspace has its own team, roles and billing.',
        'create'       => 'Create a workspace',
        'name'         => 'Name',
        'slug'         => 'URL slug (optional)',
        'empty'        => 'No workspaces yet — create your first one above.',
        'manage_team'  => 'Manage team',
        'roles' => [
            'owner'  => 'Owner',
            'admin'  => 'Admin',
            'member' => 'Member',
        ],
    ],

    'teams' => [
        'title'          => 'Team',
        'subtitle'       => 'Granular workspace-level roles — Owner, Admin, Member — and invitations.',
        'back'           => 'All workspaces',
        'members'        => 'Members',
        'remove'         => 'Remove',
        'remove_confirm' => 'Remove this member from the workspace?',
        'invitations'    => 'Invitations',
        'invite_email'   => 'E-mail address',
        'role'           => 'Role',
        'send_invite'    => 'Send invitation',
        'no_invitations' => 'No invitations yet.',
        'accepted'       => 'Accepted',
        'pending'        => 'Pending',
        'expired'        => 'Expired',
        'revoke'         => 'Revoke',
        'revoke_confirm' => 'Revoke this invitation?',
    ],

];
