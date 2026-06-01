<?php
return [
    'asaas' => [
        'ambiente'      => env('ASAAS_AMBIENTE', 'sandbox'),
        'api_key'       => env('ASAAS_API_KEY', ''),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN', ''),
    ],
    'mercadopago' => [
        'access_token'   => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET', ''),
    ],
    'trial_dias' => env('BILLING_TRIAL_DIAS', 14),
];
