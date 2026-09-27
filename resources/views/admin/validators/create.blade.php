@extends('layouts.admin')

@section('title', 'Tambah Validator : Admin MBG')
@section('page-title', 'Tambah Validator')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="admin-detail-heading">
    <a href="{{ route('admin.validators.index') }}" class="back-link"><svg width="16" height="16"><use href="#i-arrow-left"/></svg>Kembali ke data validator</a>
    <h1>Tambah akun validator</h1>
    <p>Role ditetapkan sebagai validator oleh server dan tidak dapat diubah dari formulir.</p>
  </div>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Informasi akun</h2><p>Setiap data diperiksa saat diisi dan divalidasi ulang ketika disimpan.</p></div></div>
    @include('admin.validators._form', [
      'validator' => null,
      'action' => route('admin.validators.store'),
      'availabilityUrl' => route('admin.validators.availability'),
      'method' => 'POST',
      'submitLabel' => 'Simpan validator',
    ])
  </section>
</div>
@endsection
