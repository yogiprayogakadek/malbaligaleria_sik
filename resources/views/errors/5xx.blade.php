@include('errors._page', [
  'status' => $exception->getStatusCode(),
  'title' => 'Layanan mengalami gangguan',
  'message' => 'Sistem belum dapat menyelesaikan permintaan ini. Silakan kembali atau coba lagi beberapa saat lagi.',
  'retry' => true,
])
