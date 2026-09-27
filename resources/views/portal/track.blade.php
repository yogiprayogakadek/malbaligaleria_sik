@extends('layouts.portal')

@section('title', 'Cek Status Izin : Mal Bali Galeria')
@section('page-title', 'Cek Status')

@section('content')
<section class="view active" id="view-track" aria-labelledby="trackTitle">
  <div class="page-wrap page-wrap--narrow">
    <div class="page-header">
      <div>
        <p class="page-eyebrow">PELACAKAN STATUS</p>
        <h1 id="trackTitle" class="page-title">Cek Status Izin</h1>
        <p class="page-subtitle">Masukkan nomor surat permohonan izin untuk memeriksa status verifikasi secara langsung.</p>
      </div>
    </div>

    @if($errors->has('permit_number'))
      <div class="status-banner status-banner--rejected" style="margin-bottom: 20px;">
        <div class="status-banner-icon">
          <svg><use href="#i-info"/></svg>
        </div>
        <div class="status-banner-content">
          <strong>Permohonan Tidak Ditemukan</strong>
          <p>{{ $errors->first('permit_number') }} Pastikan nomor surat sesuai format (contoh: MBG/SIK/III/0001).</p>
        </div>
      </div>
    @endif

    <div class="track-card">
      <form id="trackForm" class="track-form" action="{{ route('loading.track') }}" method="POST">
        @csrf
        <div class="form-group @error('permit_number') form-group--error @enderror">
          <label for="trackPermitNumber">Nomor Surat Izin</label>
          <input id="trackPermitNumber"
                 name="permit_number"
                 type="text"
                 value="{{ old('permit_number', $reference) }}"
                 placeholder="Contoh: MBG/SIK/III/0001"
                 autocomplete="off"
                 required>
          <span class="form-hint">Nomor surat diperoleh setelah permohonan berhasil dikirimkan.</span>
        </div>

        <button class="btn-primary btn-primary--full" type="submit">
          <span>Periksa Status Surat</span>
          <svg><use href="#i-arrow-right"/></svg>
        </button>
      </form>

      <div class="track-help-box">
        <svg width="20" height="20"><use href="#i-help"/></svg>
        <div>
          <strong>Lupa Nomor Surat?</strong>
          <p>Jika Anda mengajukan permohonan saat login, Anda dapat melihat seluruh riwayat di menu <a href="{{ route('permits.index') }}">Permohonan Saya</a>.</p>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
