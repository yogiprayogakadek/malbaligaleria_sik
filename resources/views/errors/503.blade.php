@include('errors._page', [
  'status' => 503,
  'title' => 'Layanan sementara tidak tersedia',
  'message' => 'Sistem sedang mengalami gangguan layanan atau pembaruan. Permintaan Anda belum dapat diproses. Silakan coba kembali beberapa saat lagi.',
  'retry' => true,
])
