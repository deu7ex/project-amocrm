<?php


return [
    'spreadsheet_id'    => env('AMOCRM_SPREADSHEET_ID', ''),
    'id_column'         => env('AMOCRM_AMOCRM_ID_COLUMN', 'G'),
    'name_index'        => env('AMOCRM_SHEET_NAME_INDEX', 0),
    'price_index'       => env('AMOCRM_SHEET_PRICE_INDEX', 1),
    'contact_index'     => env('AMOCRM_SHEET_CONTACT_INDEX', 2),
    'email_index'       => env('AMOCRM_SHEET_EMAIL_INDEX', 5),
    'phone_index'       => env('AMOCRM_SHEET_PHONE_INDEX', 4),
    'column_amo_index'  => env('AMOCRM_SHEET_COLUMN_INDEX', 6),
    'pipeline_id'       => env('AMOCRM_PIPELINE_ID', 1),
    'status_id'         => env('AMOCRM_STATUS_ID', 1),
];
