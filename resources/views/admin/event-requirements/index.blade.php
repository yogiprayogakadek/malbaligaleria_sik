@extends('layouts.admin')

@section('title', 'Kebutuhan Event - Admin MBG')
@section('page-title', 'Kebutuhan Event')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header"><div><div class="page-breadcrumb"><span>Perizinan</span><svg><use href="#i-chevron-right"/></svg><span>Kebutuhan Event</span></div><h1 class="page-title">Daftar kebutuhan Event</h1><p class="page-subtitle">Kelola fasilitas dan akses yang dapat dipilih pemohon. Perubahan tidak memengaruhi surat lama.</p></div></div>
  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <section class="admin-panel event-requirement-panel"><div class="admin-panel-header"><div><h2>Tambah kebutuhan</h2><p>Gunakan kelompok untuk menjaga pilihan tetap mudah dipindai.</p></div></div>
    <form method="POST" action="{{ route('admin.event-requirements.store') }}" class="event-requirement-admin-form">@csrf
      <label><span>Kelompok</span><input name="group_name" value="{{ old('group_name') }}" maxlength="80" placeholder="Contoh: Fasilitas" autocomplete="off" required></label>
      <label><span>Nama kebutuhan</span><input name="name" value="{{ old('name') }}" maxlength="160" placeholder="Contoh: Kelistrikan" autocomplete="off" required></label>
      <label><span>Urutan</span><input name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', 100) }}" required></label>
      <input type="hidden" name="is_active" value="1">
      <button class="admin-primary-button" type="submit"><svg><use href="#i-plus"/></svg>Tambah</button>
    </form>
  </section>

  <section class="admin-panel event-requirement-panel"><div class="admin-panel-header"><div><h2>Kebutuhan tersedia</h2><p>{{ $requirements->count() }} pilihan tercatat.</p></div></div>
    <div class="admin-table-wrap event-requirement-table-wrap"><table class="admin-table event-requirement-table"><thead><tr><th>Kelompok</th><th>Nama</th><th>Urutan</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
      @forelse($requirements as $item)
        <tr>
          <td data-label="Kelompok"><input form="event-requirement-{{ $item->id }}" name="group_name" value="{{ $item->group_name }}" maxlength="80" aria-label="Kelompok kebutuhan" required></td>
          <td data-label="Nama kebutuhan"><input form="event-requirement-{{ $item->id }}" name="name" value="{{ $item->name }}" maxlength="160" aria-label="Nama kebutuhan" required></td>
          <td data-label="Urutan"><input form="event-requirement-{{ $item->id }}" name="sort_order" type="number" min="0" max="65535" value="{{ $item->sort_order }}" aria-label="Urutan kebutuhan" required></td>
          <td data-label="Status"><select form="event-requirement-{{ $item->id }}" name="is_active" aria-label="Status kebutuhan"><option value="1" @selected($item->is_active)>Aktif</option><option value="0" @selected(!$item->is_active)>Nonaktif</option></select></td>
          <td data-label="Tindakan"><div class="officer-row-actions"><form id="event-requirement-{{ $item->id }}" method="POST" action="{{ route('admin.event-requirements.update', $item) }}">@csrf @method('PUT')<button class="admin-secondary-button" type="submit">Simpan</button></form><form method="POST" action="{{ route('admin.event-requirements.destroy', $item) }}" data-confirm="Hapus kebutuhan ini? Surat lama tidak akan berubah.">@csrf @method('DELETE')<button class="admin-status-button danger" type="submit">Hapus</button></form></div></td>
        </tr>
      @empty
        <tr class="event-requirement-empty-row"><td colspan="5"><div class="admin-table-empty"><svg><use href="#i-calendar"/></svg><strong>Belum ada kebutuhan Event</strong><span>Tambahkan pilihan pertama melalui formulir di atas.</span></div></td></tr>
      @endforelse
    </tbody></table></div>
  </section>
</div>
@endsection
