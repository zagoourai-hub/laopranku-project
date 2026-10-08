@include('errors.page', [
    'code' => 503,
    'title' => __('Sedang tidak tersedia'),
    // abort(503, '...') in the app already carries an Indonesian, user-facing reason;
    // maintenance mode throws the English default, which is skipped.
    'message' => filled($exception?->getMessage()) && $exception->getMessage() !== 'Service Unavailable' ? $exception->getMessage() : __('Layanan sedang dalam perbaikan atau sibuk. Coba lagi beberapa saat lagi.'),
])
