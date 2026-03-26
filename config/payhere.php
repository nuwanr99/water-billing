<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PayHere Hosted Checkout (D-33/D-36)
    |--------------------------------------------------------------------------
    |
    | Simple one-time hosted checkout: the pay pages POST the member to
    | PayHere with a signed hash; PayHere confirms server-to-server on the
    | notify webhook, verified by md5sig recomputation against the secret.
    |
    */

    'merchant_id' => env('PAYHERE_MERCHANT_ID'),

    'merchant_secret' => env('PAYHERE_MERCHANT_SECRET'),

    'sandbox' => (bool) env('PAYHERE_SANDBOX', true),

    'currency' => 'LKR',

];
