<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Panduan Sistem Perizinan MBG</title>
<style>
@page { margin: 28px 34px; } body { color: #17253a; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.5; } header { padding-bottom: 14px; border-bottom: 2px solid #2563eb; } header img { width: 76px; } h1 { margin: 8px 0 2px; color: #173e70; font-size: 20px; } header p, footer { color: #66758a; } h2 { margin: 20px 0 8px; color: #173e70; font-size: 14px; } h3 { margin: 14px 0 6px; font-size: 11px; } ol { margin: 0; padding-left: 20px; } li { margin-bottom: 7px; } table { width: 100%; border-collapse: collapse; } th, td { padding: 7px 8px; border: 1px solid #dce4ee; text-align: left; vertical-align: top; } th { background: #eef4fb; color: #173e70; } td:first-child { width: 28%; font-weight: bold; } footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #dce4ee; font-size: 8px; }
</style></head>
<body>
<header>@if($logoData)<img src="{{ $logoData }}" alt="">@endif<h1>Panduan Sistem Perizinan</h1><p>Mal Bali Galeria · Dibuat {{ $generatedAt->translatedFormat('d F Y, H:i') }} WITA</p></header>
<h2>Alur Permohonan</h2><ol>@foreach($workflow as $step)<li><strong>{{ $step['title'] }}</strong><br>{{ $step['description'] }}</li>@endforeach</ol>
<h2>Fungsi Menu Admin</h2>
@foreach($groups as $group)<h3>{{ $group['label'] }}</h3><table><thead><tr><th>Menu</th><th>Fungsi</th></tr></thead><tbody>@foreach($group['items'] as $item)<tr><td>{{ $item['label'] }}</td><td>{{ $item['description'] }}</td></tr>@endforeach</tbody></table>@endforeach
<footer>Panduan internal Sistem Informasi Perizinan Mal Bali Galeria.</footer>
</body></html>
