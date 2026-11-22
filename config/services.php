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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    /*
     | Google Gemini: AI suggestions for SEO and site content (Administration → SEO & Site Content).
     | The key lives only in .env. Models are tried in order; later ones are fallbacks.
     */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'models' => env('GEMINI_MODELS', 'gemini-flash-lite-latest,gemini-flash-latest'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 40),
        'hourly_limit' => (int) env('GEMINI_HOURLY_LIMIT', 60),
        'deadline' => (int) env('GEMINI_DEADLINE', 55),
        'backoff_ms' => (int) env('GEMINI_BACKOFF_MS', 700),
    ],

    'deploy' => [
        'secret' => env('DEPLOY_WEBHOOK_SECRET'),
        'branch' => env('DEPLOY_BRANCH', 'main'),
    ],

];
