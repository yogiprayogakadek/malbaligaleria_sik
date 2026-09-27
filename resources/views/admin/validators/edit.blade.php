@extends('layouts.admin')

@section('title', 'Edit Validator : Admin MBG')
@section('page-title', 'Edit Validator')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="admin-detail-heading">
    <a href="{{ route('admin.validators.index') }}" class="back-link"><svg width="16" height="16"><use href="#i-arrow-left"/></svg>Kembali ke data validator</a>
    <h1>Edit akun validator</h1>
    <p>Perbarui identitas, divisi, atau kata sandi {{ $validator->name }}.</p>
  </div>

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Informasi akun</h2><p>Status saat ini: {{ $validator->is_active ? 'aktif' : 'nonaktif' }}.</p></div>
      <span class="admin-account-state {{ $validator->is_active ? 'active' : 'inactive' }}">{{ $validator->is_active ? 'Aktif' : 'Nonaktif' }}</span>
    </div>
    @include('admin.validators._form', [
      'validator' => $validator,
      'action' => route('admin.validators.update', $validator),
      'availabilityUrl' => route('admin.validators.availability.edit', $validator),
      'method' => 'PUT',
      'submitLabel' => 'Simpan perubahan',
    ])
  </section>
</div>
@endsection
