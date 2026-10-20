@extends('layouts.admin')

@section('title', 'Panduan Sistem - Admin MBG')
@section('page-title', 'Panduan Sistem')

@section('content')
<div class="page-wrap admin-page-wrap admin-guide-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Administrasi</span><svg><use href="#i-chevron-right"/></svg><span>Panduan sistem</span></div><h1 class="page-title">Alur kerja dan fungsi menu</h1><p class="page-subtitle">Panduan ringkas untuk mengoperasikan sistem perizinan sesuai kewenangan setiap divisi.</p></div>
    <a href="{{ route('admin.guide.download') }}" class="admin-primary-button"><svg><use href="#i-file"/></svg>Generate panduan PDF</a>
  </div>

  <section class="admin-panel admin-guide-flow" aria-labelledby="workflowTitle">
    <div class="admin-panel-header"><div><h2 id="workflowTitle">Alur permohonan</h2><p>Urutan proses sejak data dikirim hingga izin dapat digunakan.</p></div></div>
    <ol class="admin-guide-steps">
      @foreach($workflow as $step)
        <li><span>{{ $loop->iteration }}</span><div><strong>{{ $step['title'] }}</strong><p>{{ $step['description'] }}</p></div></li>
      @endforeach
    </ol>
  </section>

  <section class="admin-guide-menu" aria-labelledby="menuGuideTitle">
    <div class="admin-guide-section-heading"><div><h2 id="menuGuideTitle">Fungsi menu admin</h2><p>Daftar ini memakai sumber yang sama dengan sidebar dan menu mobile.</p></div></div>
    @foreach($groups as $group)
      <div class="admin-guide-group">
        <h3>{{ $group['label'] }}</h3>
        <div class="admin-guide-list">
          @foreach($group['items'] as $item)
            <a href="{{ route($item['route']) }}" class="admin-guide-item"><span class="admin-guide-item-icon"><svg><use href="#{{ $item['icon'] }}"/></svg></span><span><strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small></span><svg class="admin-guide-item-arrow"><use href="#i-chevron-right"/></svg></a>
          @endforeach
        </div>
      </div>
    @endforeach
  </section>
</div>
@endsection
