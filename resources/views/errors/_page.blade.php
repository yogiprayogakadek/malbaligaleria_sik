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
    body { margin: 0; min-height: 100vh; background: #f1f5f9; color: #0f172a; }
    .error-shell { min-height: 100vh; display: grid; grid-template-rows: auto 1fr auto; }
    .error-header { min-height: 72px; display: flex; align-items: center; justify-content: center; padding: 16px 24px; background: #fff; border-bottom: 1px solid #e2e8f0; }
    .error-logo { display: block; width: auto; height: 38px; object-fit: contain; }
    .error-main { display: grid; place-items: center; padding: 40px 20px; }
    .error-panel { width: min(100%, 560px); background: #fff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 36px; box-shadow: 0 8px 24px rgba(15, 23, 42, .07); }
    .error-code { display: inline-flex; min-width: 54px; height: 32px; align-items: center; justify-content: center; padding: 0 10px; border-radius: 6px; background: #eff6ff; color: #2563eb; font-size: 13px; font-weight: 800; }
    h1 { margin: 18px 0 10px; font-size: 28px; line-height: 1.25; letter-spacing: 0; }
    p { margin: 0; color: #475569; font-size: 15px; line-height: 1.7; }
    .error-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 28px; }
    .error-reference { margin-top: 18px; color: #64748b; font-size: 12px; }
    .error-reference code { color: #334155; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-weight: 700; }
    .error-action { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 16px; background: #fff; color: #0f172a; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
    .error-action--primary { border-color: #3b82f6; background: #3b82f6; color: #fff; }
    .error-action:focus-visible { outline: 3px solid #bfdbfe; outline-offset: 2px; }
    .error-footer { padding: 20px; color: #64748b; font-size: 12px; text-align: center; }
    @media (max-width: 560px) {
      .error-header { min-height: 64px; }
      .error-logo { height: 32px; }
      .error-main { align-items: start; padding: 28px 16px; }
      .error-panel { padding: 26px 22px; }
      h1 { font-size: 23px; }
      .error-actions { display: grid; }
      .error-action { width: 100%; }
    }
  </style>
</head>
<body>
  <div class="error-shell">
    <header class="error-header">
      <a href="{{ url('/') }}" aria-label="Kembali ke beranda Mal Bali Galeria">
        <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="error-logo">
      </a>
    </header>
    <main class="error-main">
      <section class="error-panel" aria-labelledby="error-title">
        <span class="error-code">{{ $status }}</span>
        <h1 id="error-title">{{ $title }}</h1>
        <p>{{ $message }}</p>
        @if($requestId = request()->attributes->get('request_id'))
          <p class="error-reference">Referensi bantuan: <code>{{ $requestId }}</code></p>
        @endif
        <div class="error-actions">
          @if($retry ?? false)
            <a href="{{ request()->fullUrl() }}" class="error-action error-action--primary">Coba lagi</a>
          @endif
          <a href="{{ url('/') }}" class="error-action {{ ($retry ?? false) ? '' : 'error-action--primary' }}">Ke beranda</a>
        </div>
      </section>
    </main>
    <footer class="error-footer">&copy; {{ now()->year }} Mal Bali Galeria &middot; Property Management</footer>
  </div>
</body>
</html>
