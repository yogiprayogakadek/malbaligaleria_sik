@extends('layouts.admin')

@section('title', ($category ? 'Edit' : 'Tambah').' Kategori : Admin MBG')
@section('page-title', $category ? 'Edit Kategori' : 'Tambah Kategori')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page"><div class="admin-detail-heading"><a href="{{ route('admin.work-categories.index') }}" class="back-link"><svg><use href="#i-arrow-left"/></svg>Kembali ke kategori</a><h1>{{ $category ? 'Edit kategori pekerjaan' : 'Tambah kategori pekerjaan' }}</h1><p>Divisi tujuan dan batas durasi diterapkan ulang oleh backend saat permohonan disimpan.</p></div>
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>Periksa kembali data yang ditandai.</span></div>@endif
  <section class="admin-panel"><form method="POST" action="{{ $category ? route('admin.work-categories.update', $category) : route('admin.work-categories.store') }}" class="admin-form admin-settings-form">@csrf @if($category)@method('PUT')@endif
    <div class="admin-form-grid">
      @unless($category)<label><span>Kode kategori</span><input name="code" value="{{ old('code') }}" placeholder="contoh: grand_opening" required maxlength="60">@error('code')<small class="admin-field-error">{{ $message }}</small>@enderror</label>@endunless
      <label><span>Nama kategori</span><input name="name" value="{{ old('name', $category?->name) }}" required maxlength="120">@error('name')<small class="admin-field-error">{{ $message }}</small>@enderror</label>
      <label><span>Divisi pemeriksa</span><select name="division" required>@foreach(['TR' => 'Tenant Relationship', 'MEP' => 'MEP', 'EP' => 'Event & Promotion'] as $code => $label)<option value="{{ $code }}" @selected(old('division', $category?->division) === $code)>{{ $label }}</option>@endforeach</select></label>
      <label><span>Durasi maksimum</span><input type="number" name="max_duration_days" value="{{ old('max_duration_days', $category?->max_duration_days ?? 7) }}" min="1" max="30" required><small>Hari kalender termasuk tanggal mulai.</small></label>
      <label><span>Urutan</span><input type="number" name="sort_order" value="{{ old('sort_order', $category?->sort_order ?? 100) }}" min="0" max="1000" required></label>
      <label><span>Status</span><select name="is_active"><option value="1" @selected((int) old('is_active', $category?->is_active ?? 1) === 1)>Aktif</option><option value="0" @selected((int) old('is_active', $category?->is_active ?? 1) === 0)>Nonaktif</option></select></label>
      <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<small class="admin-field-error">{{ $message }}</small>@enderror</label>
    </div><div class="admin-form-actions"><button class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan kategori</button></div>
  </form></section>
</div>
@endsection
