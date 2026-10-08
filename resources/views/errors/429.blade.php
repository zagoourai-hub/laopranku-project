@include('errors.page', [
    'code' => 429,
    'title' => __('Terlalu banyak permintaan'),
    'message' => __('Kamu mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar, lalu coba lagi.'),
])
