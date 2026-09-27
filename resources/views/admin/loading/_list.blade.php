@if($permits->isEmpty())
  <div class="tr-empty"><svg width="38" height="38"><use href="#i-box"/></svg><p>Belum ada data loading barang.</p></div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Tenant</th><th>Nomor surat</th><th>Arah</th><th>Status</th><th>Tanggal</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
      <tbody>
        @foreach($permits as $permit)
          <tr>
            <td><strong>{{ $permit->tenant_name }}</strong><span>{{ $permit->applicant_name }}</span></td>
            <td><code>{{ $permit->permit_number }}</code></td>
            <td>{{ $permit->direction_label }}</td>
            <td><span class="admin-status admin-status--{{ $permit->status }}">{{ $permit->status_label }}</span></td>
            <td>{{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }}</td>
            <td><a href="{{ route('admin.loading.show', $permit->permit_number) }}" class="admin-row-action" aria-label="Lihat {{ $permit->permit_number }}"><svg><use href="#i-chevron-right"/></svg></a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="admin-mobile-list">
    @foreach($permits as $permit)
      <a href="{{ route('admin.loading.show', $permit->permit_number) }}" class="admin-mobile-row">
        <span class="admin-mobile-row-main"><strong>{{ $permit->tenant_name }}</strong><code>{{ $permit->permit_number }}</code><small>{{ $permit->direction_label }} · {{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y') }}</small></span>
        <span class="admin-status admin-status--{{ $permit->status }}">{{ $permit->status_label }}</span>
      </a>
    @endforeach
  </div>
@endif
