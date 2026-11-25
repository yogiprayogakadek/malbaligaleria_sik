@extends('layouts.admin')

@section('title', 'Atur Sidebar : Admin MBG')
@section('page-title', 'Atur Sidebar')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page sidebar-settings-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Konfigurasi</span><svg><use href="#i-chevron-right"/></svg><span>Atur sidebar</span></div>
      <h1 class="page-title">Pilih menu sidebar</h1>
      <p class="page-subtitle">Tampilkan menu yang paling sering Anda gunakan. Halaman yang disembunyikan tetap dapat dibuka melalui tautan langsung.</p>
    </div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <form method="POST" action="{{ route('admin.settings.sidebar.update') }}" class="sidebar-settings-form" data-sidebar-settings>
    @csrf
    @method('PUT')

    <section class="admin-panel sidebar-settings-summary">
      <div>
        <h2>Menu yang ditampilkan</h2>
        <p><strong data-sidebar-visible-count>{{ $hideableCount - count($hiddenRoutes) }}</strong> menu pilihan akan tampil pada sidebar desktop dan drawer menu mobile.</p>
      </div>
      <span>Atur sidebar selalu tersedia</span>
    </section>

    <div class="sidebar-settings-groups">
      @foreach($groups as $group)
        <fieldset class="admin-panel sidebar-settings-group" data-sidebar-group>
          <legend>{{ $group['label'] }}</legend>
          <div class="sidebar-settings-group-actions">
            <button type="button" class="admin-secondary-button" data-sidebar-group-select="all">Pilih semua</button>
            <button type="button" class="admin-secondary-button" data-sidebar-group-select="none">Kosongkan</button>
          </div>
          <div class="sidebar-settings-list">
            @foreach($group['items'] as $item)
              <label class="sidebar-settings-item {{ $item['hideable'] ? '' : 'is-locked' }}">
                @if($item['hideable'])
                  <input type="checkbox" name="visible_routes[]" value="{{ $item['route'] }}" @checked(! in_array($item['route'], $hiddenRoutes, true)) data-sidebar-menu-item>
                @else
                  <input type="checkbox" checked disabled aria-describedby="sidebar-menu-locked-{{ $loop->parent->index }}-{{ $loop->index }}">
                @endif
                <svg aria-hidden="true"><use href="#{{ $item['icon'] }}"/></svg>
                <span><strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small></span>
                @unless($item['hideable'])<em id="sidebar-menu-locked-{{ $loop->parent->index }}-{{ $loop->index }}">Selalu tampil</em>@endunless
              </label>
            @endforeach
          </div>
        </fieldset>
      @endforeach
    </div>

    <div class="admin-panel sidebar-settings-actions">
      <p>Perubahan hanya berlaku pada akun Admin Anda.</p>
      <div>
        <button type="submit" name="action" value="show_all" class="admin-secondary-button">Tampilkan semua</button>
        <button type="submit" name="action" value="save" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan tampilan sidebar</button>
      </div>
    </div>
  </form>
</div>
@endsection
