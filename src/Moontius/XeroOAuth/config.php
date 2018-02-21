<?php

return [
    /*
      |--------------------------------------------------------------------------
      | OAUTH XERO CONFIG
      |--------------------------------------------------------------------------
      |
      |
     */
    'oauth' => [
        'callback' => env('XERO_OAUTH_CALLBACK', 'http://localhost/xero_oauth_callback'),
        'key' => env('XERO_OAUTH_CONSUMER_KEY', ''),
        'secret' => env('XERO_OAUTH_CONSUMER_SECRET', ''),
        'type' => env('XERO_APP_TYPE', 'Public'),
        'core_version' => '2.0',
        'payroll_version' => '1.0',
        'file_version' => '1.0'
    ]
];
