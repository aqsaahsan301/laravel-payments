<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway
    |--------------------------------------------------------------------------
    |
    | Which driver PaymentManager resolves when the package's PaymentGateway
    | contract is resolved from the container. Swapping providers is a config
    | change (PAYMENT_GATEWAY=billplz, say) plus a new driver class — nothing
    | in the host app that depends on the contract needs to change.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Gateway configuration
    |--------------------------------------------------------------------------
    |
    | Per-driver settings, keyed by driver name (matching 'default' above and
    | the *Driver classes in src/Drivers).
    |
    */

    'gateways' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Host-app-facing plan slugs, mapped to each gateway's own price/plan
    | identifier. The host app only ever refers to plans by slug.
    |
    */

    'plans' => [
        // 'starter' => [
        //     'stripe' => 'price_...',
        // ],
    ],

];
