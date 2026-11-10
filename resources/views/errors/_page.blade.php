@php
  $homeUrl = url('/');
  $previousUrl = url()->previous();
  $previousIsSafe = $previousUrl !== request()->fullUrl()
    && $previousUrl !== $homeUrl
    && str_starts_with($previousUrl, rtrim($homeUrl, '/').'/');
  $canRetry = ($retry ?? false) && request()->isMethod('GET');
  $recoveryUrl = $canRetry ? request()->fullUrl() : ($previousIsSafe ? $previousUrl : $homeUrl);
  $recoveryLabel = $canRetry ? 'Coba lagi' : ($previousIsSafe ? 'Kembali' : 'Ke beranda');
  $showHomeAction = $recoveryUrl !== $homeUrl;
  $tone = $tone ?? ($status >= 500 ? 'danger' : ($status === 429 || $status === 419 ? 'warning' : 'info'));
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f1f5f9">
  <meta name="robots" content="noindex, nofollow">
  <title>{{ $status }} : {{ $title }} : Mal Bali Galeria</title>
  <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
  <style>
    :root { color-scheme: light; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    * { box-sizing: border-box; }
    ::selection { background: #bfdbfe; color: #0f172a; }
    body { margin: 0; min-width: 320px; min-height: 100vh; min-height: 100dvh; background: #f1f5f9; color: #0f172a; }
    .error-shell { min-height: 100vh; min-height: 100dvh; display: grid; grid-template-rows: auto 1fr auto; }
    .error-header { min-height: 72px; display: flex; align-items: center; justify-content: center; padding: 16px 24px; background: #fff; border-bottom: 1px solid #e2e8f0; }
    .error-logo-link { display: inline-flex; min-width: 44px; min-height: 44px; align-items: center; justify-content: center; border-radius: 12px; }
    .error-logo { display: block; width: auto; height: 38px; object-fit: contain; }
    .error-main { display: grid; place-items: center; padding: 48px 20px; }
    .error-panel { width: min(100%, 680px); border: 1px solid #cbd5e1; border-radius: 16px; background: #fff; }
    .error-content { padding: 36px; }
    .error-heading { display: grid; grid-template-columns: 64px minmax(0, 1fr); align-items: start; gap: 20px; }
    .error-mark { width: 64px; height: 64px; display: grid; place-items: center; border: 1px solid #bfdbfe; border-radius: 12px; background: #eff6ff; color: #2563eb; }
    .error-mark svg { width: 30px; height: 30px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .error-panel[data-tone="warning"] .error-mark { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
    .error-panel[data-tone="danger"] .error-mark { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
    .error-code { display: inline-flex; min-width: 48px; min-height: 28px; align-items: center; justify-content: center; padding: 2px 9px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; color: #334155; font-size: 13px; font-weight: 800; font-variant-numeric: tabular-nums; }
    h1 { max-width: 24ch; margin: 12px 0 10px; font-size: clamp(1.625rem, 4vw, 2rem); line-height: 1.2; letter-spacing: -.025em; text-wrap: balance; }
    .error-message { max-width: 62ch; margin: 0; color: #475569; font-size: 15px; line-height: 1.7; overflow-wrap: anywhere; }
    .error-reference { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 10px; margin: 28px 0 0; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #475569; font-size: 12px; line-height: 1.6; }
    .error-reference code { color: #0f172a; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-weight: 700; overflow-wrap: anywhere; }
    .error-actions { display: flex; flex-wrap: wrap; gap: 10px; padding: 20px 36px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 16px 16px; }
    .error-action { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #94a3b8; border-radius: 12px; padding: 0 18px; background: #fff; color: #0f172a; font: inherit; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; transition: background-color 160ms ease-out, border-color 160ms ease-out; }
    .error-action:hover { background: #f1f5f9; border-color: #64748b; }
    .error-action--primary { border-color: #2563eb; background: #2563eb; color: #fff; }
    .error-action--primary:hover { border-color: #1d4ed8; background: #1d4ed8; }
    .error-action:focus-visible, .error-logo-link:focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
    .error-footer { padding: 20px; color: #475569; font-size: 12px; text-align: center; }
    @media (max-width: 640px) {
      .error-header { min-height: 64px; padding: 10px 16px; }
      .error-logo { height: 32px; }
      .error-main { align-items: start; padding: 28px 16px; }
      .error-content { padding: 24px 20px; }
      .error-heading { grid-template-columns: 1fr; gap: 16px; }
      .error-mark { width: 52px; height: 52px; }
      .error-mark svg { width: 26px; height: 26px; }
      h1 { margin-top: 10px; }
      .error-actions { display: grid; padding: 16px 20px 20px; }
      .error-action { width: 100%; }
    }
    @media (forced-colors: active) {
      .error-mark, .error-code, .error-action, .error-panel { border-color: CanvasText; }
    }
  </style>
</head>
<body>
  <div class="error-shell">
    <header class="error-header">
      <a href="{{ $homeUrl }}" class="error-logo-link" aria-label="Kembali ke beranda Mal Bali Galeria">
        <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="error-logo">
      </a>
    </header>
    <main class="error-main">
      <section class="error-panel" data-tone="{{ $tone }}" aria-labelledby="error-title">
        <div class="error-content">
          <div class="error-heading">
            <div class="error-mark" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 8v5"/><path d="M12 17h.01"/><path d="M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
            </div>
            <div>
              <span class="error-code">{{ $status }}</span>
              <h1 id="error-title">{{ $title }}</h1>
              <p class="error-message">{{ $message }}</p>
            </div>
          </div>
          @if($requestId = request()->attributes->get('request_id'))
            <p class="error-reference"><span>Referensi bantuan</span><code>{{ $requestId }}</code></p>
          @endif
        </div>
        <div class="error-actions">
          <a href="{{ $recoveryUrl }}" class="error-action error-action--primary">{{ $recoveryLabel }}</a>
          @if($showHomeAction)
            <a href="{{ $homeUrl }}" class="error-action">Ke beranda</a>
          @endif
        </div>
      </section>
    </main>
    <footer class="error-footer">&copy; {{ now()->year }} Mal Bali Galeria &middot; Property Management</footer>
  </div>
</body>
</html>
