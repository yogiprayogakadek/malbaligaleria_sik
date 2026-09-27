@extends('layouts.portal')

@section('title', 'Permohonan Saya : Mal Bali Galeria')
@section('page-title', 'Permohonan')

@section('content')
<section class="view active" id="view-history" aria-labelledby="historyTitle">
  <div class="page-wrap">
    <div class="page-header">
      <div>
        <p class="page-eyebrow">ARSIP DOKUMEN</p>
        <h1 id="historyTitle" class="page-title">Permohonan Saya</h1>
        <p class="page-subtitle">Riwayat permohonan surat izin operasional gedung Mal Bali Galeria.</p>
      </div>
      <a href="{{ route('loading.create') }}" class="btn-primary">
        <svg><use href="#i-plus"/></svg>
        Permohonan baru
      </a>
    </div>

    @auth
      @if(isset($permits) && $permits->count() > 0)
        <div class="permit-list-card">
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Nomor Surat</th>
                  <th>Tenant / Penanggung Jawab</th>
                  <th>Jenis &amp; Arah</th>
                  <th>Tanggal Izin</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                @foreach($permits as $permit)
                  <tr>
                    <td>
                      <span class="permit-number">{{ $permit->permit_number }}</span>
                      <small class="permit-meta">{{ $permit->created_at->format('d/m/Y H:i') }}</small>
                    </td>
                    <td>
                      <strong>{{ $permit->tenant_name }}</strong>
                      <small class="permit-meta">{{ $permit->applicant_name }} ({{ $permit->applicant_phone }})</small>
                    </td>
                    <td>
                      <span class="badge-tag">Loading {{ $permit->direction_label }}</span>
                      <small class="permit-meta">{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</small>
                    </td>
                    <td>
                      <span>{{ $permit->start_date->format('d M Y') }}</span>
                      <small class="permit-meta">s.d. {{ $permit->end_date->format('d M Y') }}</small>
                    </td>
                    <td>
                      <span class="badge-status badge-status--{{ $permit->status }}">
                        {{ $permit->status_label }}
                      </span>
                    </td>
                    <td>
                      <div class="table-actions">
                        <a href="{{ route('loading.show', $permit->permit_number) }}" class="btn-table-action" title="Lihat Detail">
                          Detail
                        </a>
                        @if($permit->status === 'approved')
                          <a href="{{ route('loading.letter', $permit->permit_number) }}" class="btn-table-action btn-table-action--success" target="_blank" title="Unduh Surat Resmi">
                            Surat
                          </a>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          @if($permits->hasPages())
            <div class="pagination-wrap">
              {{ $permits->links() }}
            </div>
          @endif
        </div>
      @else
        <!-- Empty State -->
        <div class="empty-state">
          <div class="empty-state-icon">
            <svg><use href="#i-file"/></svg>
          </div>
          <h2>Belum ada permohonan</h2>
          <p>Anda belum memiliki riwayat pengajuan surat izin loading di sistem ini.</p>
          <a href="{{ route('loading.create') }}" class="btn-primary">
            <svg><use href="#i-plus"/></svg>
            Ajukan permohonan pertama
          </a>
        </div>
      @endif
    @else
      <div class="auth-prompt-card">
        <div class="auth-prompt-icon">
          <svg><use href="#i-lock"/></svg>
        </div>
        <div class="auth-prompt-body">
          <h2>Masuk untuk Melihat Riwayat Lengkap</h2>
          <p>Daftar permohonan yang diajukan oleh unit tenant Anda dapat dipantau di halaman ini setelah masuk akun.</p>
          <div class="auth-prompt-actions">
            <a href="{{ route('login') }}" class="btn-primary">Masuk ke Akun Tenant</a>
            <a href="{{ route('portal.track') }}" class="btn-secondary">Cek Status Tanpa Login</a>
          </div>
        </div>
      </div>
    @endauth

  </div>
</section>
@endsection
