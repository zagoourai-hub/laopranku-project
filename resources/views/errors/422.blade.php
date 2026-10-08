@include('errors.page', [
    'code' => 422,
    'title' => __('Dokumen belum bisa diproses'),
    // abort(422, '...') in the app already carries an Indonesian, user-facing reason.
    'message' => filled($exception?->getMessage()) ? $exception->getMessage() : __('Isi dokumen belum lengkap atau belum valid.'),
])
