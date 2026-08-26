<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'frontend_url' => env('FRONTEND_PUBLIC_URL'),

    'tracking_url' => env('TRACKING_URL', 'https://tracking.oruidoscomputadores.pt'),

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ifthenpay' => [
        'mb_key' => env('IFTHENPAY_MB_KEY'),
        'mbway_key' => env('IFTHENPAY_MBWAY_KEY'),
        'antiphishing_key' => env('IFTHENPAY_ANTIPHISHING_KEY'),
    ],

    'moloni' => [
        'client_id' => env('MOLONI_CLIENT_ID'),
        'client_secret' => env('MOLONI_CLIENT_SECRET'),
        'company_id' => env('MOLONI_COMPANY_ID'),
        'iva_tax_id' => env('MOLONI_IVA_TAX_ID'),
    ],

];
