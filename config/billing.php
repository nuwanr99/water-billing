<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bill Due Days
    |--------------------------------------------------------------------------
    |
    | Days between on-site bill generation and its due date. Past the due
    | date the scheduler marks unpaid bills overdue and posts the billing
    | category's late-fee penalty (D-23).
    |
    */

    'due_days' => (int) env('BILLING_DUE_DAYS', 15),

];
