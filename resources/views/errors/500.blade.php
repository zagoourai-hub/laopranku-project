@include('errors.page', [
    'code' => 500,
    'title' => __('Terjadi kesalahan'),
    'message' => __('Ada masalah di server kami. Coba lagi beberapa saat lagi.'),
])
