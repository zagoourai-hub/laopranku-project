<?php

// Sandbox only for now: MIDTRANS_IS_PRODUCTION stays false until task 4.3d.
return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
];
