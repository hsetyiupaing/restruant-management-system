<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront Restaurant
    |--------------------------------------------------------------------------
    |
    | The public site is a single-venue storefront. This slug picks the venue
    | rendered on the home and menu pages; when it is missing or inactive the
    | first active restaurant is used instead, so a fresh database still works.
    |
    */

    'storefront_slug' => env('RESTAURANT_STOREFRONT_SLUG', 'ember-and-oak'),

    /*
    |--------------------------------------------------------------------------
    | Money and Locale
    |--------------------------------------------------------------------------
    |
    | Prices are stored as decimals in the database and emitted by the API as
    | strings. The frontend formats them for this locale and currency.
    |
    */

    'currency' => env('RESTAURANT_CURRENCY', 'THB'),

    'locale' => env('RESTAURANT_LOCALE', 'th-TH'),

];
