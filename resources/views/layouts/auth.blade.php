@extends('layouts.master')

@section('body-class', 'auth-page ' . (request()->routeIs('login') ? 'auth-page--login' : '') . (request()->routeIs('password.required.*') ? ' auth-page--password' : ''))

@section('body')
<div class="auth-screen">

  <!-- Ambient background soft blooms -->
  <div class="auth-bloom auth-bloom--1" aria-hidden="true"></div>
  <div class="auth-bloom auth-bloom--2" aria-hidden="true"></div>
  <div class="auth-bloom auth-bloom--3" aria-hidden="true"></div>

  <div class="auth-container">

    <!-- Top Hero with Mal Bali Galeria Logo Watermark -->
    <div class="auth-hero">
      <div class="auth-hero-glow" aria-hidden="true"></div>
      <a href="{{ route('portal.dashboard') }}" aria-label="Beranda Portal">
        <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="auth-hero-logo" draggable="false">
      </a>
      <p class="auth-hero-tagline">@yield('auth-tagline', 'Portal Perizinan Resmi Tenant')</p>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">

      @unless(request()->routeIs('password.required.*'))
      <!-- Tab switcher: Masuk / Daftar -->
      <div class="auth-tabs" role="tablist" aria-label="Mode akses">
        <a href="{{ route('login') }}"
           class="auth-tab {{ request()->routeIs('login') ? 'active' : '' }}"
           role="tab"
           aria-selected="{{ request()->routeIs('login') ? 'true' : 'false' }}">
          Masuk
        </a>
        <a href="{{ route('register') }}"
           class="auth-tab {{ request()->routeIs('register') ? 'active' : '' }}"
           role="tab"
           aria-selected="{{ request()->routeIs('register') ? 'true' : 'false' }}">
          Daftar
        </a>
        <div class="auth-tab-indicator {{ request()->routeIs('register') ? 'at-register' : '' }}"></div>
      </div>
      @endunless

      <!-- Specific Form Content -->
      @yield('auth-card')

      @unless(request()->routeIs('password.required.*'))
      <!-- Continue without account footer -->
      <div class="auth-guest-row">
        <div class="auth-guest-divider"><span>atau</span></div>
        <a href="{{ route('portal.dashboard') }}" class="btn-auth-guest" id="continueAsGuest">
          Lanjut tanpa menggunakan akun
        </a>
      </div>
      @endunless

    </div><!-- /.auth-card -->

  </div><!-- /.auth-container -->

</div><!-- /.auth-screen -->
@endsection
