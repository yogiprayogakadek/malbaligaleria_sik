@extends('layouts.master')

@section('title', 'Portal Sedang Tutup : Mal Bali Galeria')
@section('body-class', 'closed-page')

@push('styles')
  <link rel="stylesheet" href="{{ asset('admin.css') }}">
@endpush

@section('body')
<main class="closed-shell">
  <header class="closed-header">
    <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria">
    <span>Portal Perizinan Tenant</span>
  </header>

  <section class="closed-content" aria-labelledby="closedTitle">
    <span class="closed-icon" aria-hidden="true"><svg><use href="#i-clock"/></svg></span>
    <p class="closed-eyebrow">Di luar jam operasional</p>
    <h1 id="closedTitle">Portal sedang tidak beroperasi</h1>
    <p>Pengajuan dan pelacakan izin tersedia kembali sesuai jadwal operasional berikut.</p>

    @if($nextOpening)
      <div class="closed-next"><span>Dibuka kembali</span><strong>{{ config('operating-hours.days.'.$nextOpening->dayOfWeek) }}, {{ $nextOpening->format('d M Y') }} pukul {{ $nextOpening->format('H:i') }} WITA</strong></div>
    @endif

    <div class="closed-schedule" aria-label="Jadwal operasional mingguan">
      @foreach(config('operating-hours.days') as $dayNumber => $dayLabel)
        @php $schedule = $schedules->get($dayNumber); @endphp
        <div><span>{{ $dayLabel }}</span><strong>@if(!$schedule?->is_open)Tutup @elseif($schedule->is_24_hours)24 jam @else{{ substr($schedule->opens_at, 0, 5) }} - {{ substr($schedule->closes_at, 0, 5) }} WITA @endif</strong></div>
      @endforeach
    </div>

    <p class="closed-help">Untuk kebutuhan mendesak, hubungi pengelola gedung melalui kanal resmi Mal Bali Galeria.</p>
  </section>

</main>
@endsection
