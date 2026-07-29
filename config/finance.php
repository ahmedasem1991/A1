<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice Due Period
    |--------------------------------------------------------------------------
    |
    | Number of days after the invoice date that payment is due, used when
    | no explicit due date is supplied when a sale is recorded. Invoices
    | past this date with an outstanding balance are flagged as overdue.
    |
    */

    'invoice_due_days' => env('FINANCE_INVOICE_DUE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Supplier Bill Due Period
    |--------------------------------------------------------------------------
    |
    | Same as above, for supplier bills when no due date is set explicitly.
    |
    */

    'supplier_bill_due_days' => env('FINANCE_SUPPLIER_BILL_DUE_DAYS', 14),

];
