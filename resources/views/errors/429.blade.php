@include('errors._page', [
  'status' => 429,
  'title' => 'Terlalu banyak permintaan',
  'message' => 'Tunggu sebentar sebelum mencoba kembali. Pembatasan ini membantu melindungi sistem dari penyalahgunaan.',
  'retry' => true,
])
