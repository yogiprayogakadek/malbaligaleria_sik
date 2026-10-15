@include('errors._page', [
  'status' => 500,
  'title' => 'Halaman belum dapat dibuka',
  'message' => 'Terjadi gangguan saat memproses permintaan. Data teknis tidak ditampilkan demi keamanan. Silakan coba kembali beberapa saat lagi.',
  'retry' => true,
])
