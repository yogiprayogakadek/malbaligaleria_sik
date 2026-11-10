@include('errors._page', [
  'status' => $exception->getStatusCode(),
  'title' => 'Permintaan tidak dapat diproses',
  'message' => 'Periksa alamat atau kembali ke halaman sebelumnya, lalu ulangi tindakan yang ingin dilakukan.',
])
