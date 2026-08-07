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
    | Webhook path
    |--------------------------------------------------------------------------
    |
    | Where the active gateway's webhook route is registered. Remember to
    | exclude this URI from CSRF verification in the host app's
    | bootstrap/app.php — the provider calling it can't supply a token.
    |
    */

    'webhook_path' => env('PAYMENT_GATEWAY_WEBHOOK_PATH', 'laravel-payments/webhook'),

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
