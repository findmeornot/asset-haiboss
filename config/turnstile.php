<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile Site Key
    |--------------------------------------------------------------------------
    |
    | Site key yang ditampilkan di halaman web (public).
    | Dapatkan dari: https://dash.cloudflare.com/
    |
    */
    'site_key' => env('TURNSTILE_SITE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile Secret Key
    |--------------------------------------------------------------------------
    |
    | Secret key untuk verifikasi server-side. Jangan expose ke client.
    | Dapatkan dari: https://dash.cloudflare.com/
    |
    */
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
];
