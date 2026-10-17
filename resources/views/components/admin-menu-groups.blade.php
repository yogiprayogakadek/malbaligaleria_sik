@props(['variant' => 'sidebar', 'pendingCount' => 0])

@foreach(\App\Support\AdminMenuCatalog::groups() as $group)
  @if($variant === 'sidebar')
    <div class="sidebar-nav-group">
      <span class="sidebar-nav-heading">{{ $group['label'] }}</span>
      @foreach($group['items'] as $item)
        <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs(...$item['active']) ? 'active' : '' }}" title="{{ $item['label'] }}">
          <svg><use href="#{{ $item['icon'] }}"/></svg>
          @if($item['badge'] === 'pendingTRCount')<span class="nav-badge" data-pending-count @if($pendingCount === 0) hidden @endif>{{ $pendingCount }}</span>@endif
          <span class="nav-label">{{ $item['label'] }}</span>
        </a>
      @endforeach
    </div>
  @else
    <div class="admin-mobile-menu-group">
      <h2>{{ $group['label'] }}</h2>
      @foreach($group['items'] as $item)
        <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs(...$item['active'])])>
          <svg><use href="#{{ $item['icon'] }}"/></svg><span>{{ $item['label'] }}</span>
          @if($item['badge'] === 'pendingTRCount' && $pendingCount > 0)<small>{{ $pendingCount > 99 ? '99+' : $pendingCount }}</small>@endif
        </a>
      @endforeach
    </div>
  @endif
@endforeach
