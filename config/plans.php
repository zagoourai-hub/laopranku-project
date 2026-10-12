<?php

return [
    'free_documents_per_month' => 3,
    'pro_price' => 29000,
    'pro_days' => 30,
    'pdf_pro_only' => true,
    // Generate AI per hari (structure, outline, tulis bab). Angka awal, masih dikonfirmasi bigboss.
    'free_generations_per_day' => 5,
    'pro_generations_per_day' => 50,
    // Tamu berbagi IP (mis. Wi-Fi sekolah), jadi tamu juga dibatasi per IP, bukan hanya per akun.
    'guest_generations_per_ip_per_minute' => 10,
    'guest_generations_per_ip_per_day' => 60,
    'guest_accounts_per_ip_per_day' => 30,
];
