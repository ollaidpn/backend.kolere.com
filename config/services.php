<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'fayko' => [
        'public_key' => env('FAYKO_PUBLIC_KEY'),
        'secret_key' => env('FAYKO_SECRET_KEY'),
        'webhook_key' => env('FAYKO_WEBHOOK_KEY'),
    ],

    'diotko_sms' => [
        'api_url'    => env('DIOTKO_SMS_API_URL', 'https://artefacts.diotko.com/api/v1/send'),
        'public_key' => env('DIOTKO_SMS_PUBLIC_KEY'),
        'secret_key' => env('DIOTKO_SMS_SECRET_KEY'),
    ],

];

