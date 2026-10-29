@extends('layouts.master')

@push('head')
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('pwa/icon-192.png') }}">
  <meta name="application-name" content="MBG Staff">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="MBG Staff">
@endpush

@section('body-class', 'auth-page ' . (request()->routeIs('login') ? 'auth-page--login' : '') . (request()->routeIs('register') ? ' auth-page--register' : '') . (request()->routeIs('password.*') ? ' auth-page--password' : '') . (request()->routeIs('two-factor.*') ? ' auth-page--two-factor' : ''))

@section('body')
<div class="auth-screen">

  <div class="auth-layout {{ request()->routeIs('login', 'register') ? 'auth-layout--with-media' : '' }}">

    @if(request()->routeIs('login', 'register'))
    <div class="auth-media" aria-hidden="true">
      <picture>
        <source media="(max-width: 860px)" srcset="{{ asset('assets/images/portrait.png') }}">
        <img src="{{ asset('assets/images/desktop.png') }}" alt="" fetchpriority="high">
      </picture>
    </div>
    @endif

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
      <!-- Specific Form Content -->
      @yield('auth-card')
    </div><!-- /.auth-card -->

  </div><!-- /.auth-container -->

  </div><!-- /.auth-layout -->

</div><!-- /.auth-screen -->
@endsection
