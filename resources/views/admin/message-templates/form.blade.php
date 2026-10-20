@extends('layouts.admin')

@php($editing = $template !== null)
@section('title', ($editing ? 'Edit' : 'Tambah').' Template - Admin MBG')
@section('page-title', $editing ? 'Edit Template' : 'Tambah Template')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="admin-detail-heading">
    <a href="{{ route('admin.message-templates.index') }}" class="back-link"><svg><use href="#i-arrow-left"/></svg>Kembali ke template</a>
    <h1>{{ $editing ? 'Edit template pesan' : 'Tambah template pesan' }}</h1>
    <p>Gunakan hanya variabel yang dipilih agar isi pesan tetap dapat dirender dengan aman.</p>
  </div>

  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>Periksa field yang ditandai. {{ $errors->first() }}</span></div>@endif

  <section class="admin-panel">
    <form method="POST" action="{{ $editing ? route('admin.message-templates.update', $template) : route('admin.message-templates.store') }}" class="admin-form admin-settings-form">
      @csrf
      @if($editing) @method('PUT') @endif
      <div class="admin-form-grid">
        <label><span>Nama template</span><input name="name" value="{{ old('name', $template?->name) }}" maxlength="120" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Kunci template</span><input name="key" value="{{ old('key', $template?->key) }}" maxlength="80" pattern="[a-z0-9_]+" {{ $editing ? 'readonly' : 'required' }}><small>Huruf kecil, angka, dan garis bawah.</small>@error('key')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Kanal</span><select name="channel" {{ $editing ? 'disabled' : 'required' }}>@foreach(['all' => 'Semua kanal', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'push' => 'Push notification', 'webhook' => 'Webhook'] as $value => $label)<option value="{{ $value }}" @selected(old('channel', $template?->channel) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span>Status</span><select name="is_active"><option value="1" @selected((int)old('is_active', $template?->is_active ?? true) === 1)>Aktif</option><option value="0" @selected((int)old('is_active', $template?->is_active ?? true) === 0)>Nonaktif</option></select></label>
        <label class="admin-form-span"><span>Subjek</span><input name="subject" value="{{ old('subject', $template?->subject) }}" maxlength="180">@error('subject')<small class="field-error">{{ $message }}</small>@enderror</label>
        <fieldset class="admin-form-span message-variable-fieldset">
          <legend>Variabel yang diizinkan</legend>
          <div class="message-variable-grid">
            @foreach($availableVariables as $variable => $label)
              <label><input type="checkbox" name="allowed_variables[]" value="{{ $variable }}" @checked(in_array($variable, old('allowed_variables', $template?->allowed_variables ?? []), true))><span><strong>{{ $label }}</strong><code>{{ '{'.'{'.$variable.'}'.'}' }}</code></span></label>
            @endforeach
          </div>
          @error('allowed_variables')<small class="field-error">{{ $message }}</small>@enderror
        </fieldset>
        <label class="admin-form-span"><span>Isi pesan</span><textarea name="body" rows="8" maxlength="3000" required>{{ old('body', $template?->body) }}</textarea>@error('body')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<small class="field-error">{{ $message }}</small>@enderror</label>
      </div>
      <div class="admin-form-actions"><button class="admin-primary-button"><svg><use href="#i-check"/></svg>{{ $editing ? 'Simpan perubahan' : 'Tambah template' }}</button></div>
    </form>
  </section>
</div>
@endsection
