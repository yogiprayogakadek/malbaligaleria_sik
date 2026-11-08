@props([
  'title',
  'subtitle' => null,
  'breadcrumbs' => [],
])

<header {{ $attributes->class(['page-header']) }}>
  <div class="page-header-copy">
    @if($breadcrumbs)
      <nav class="page-breadcrumb" aria-label="Breadcrumb">
        @foreach($breadcrumbs as $breadcrumb)
          @if(!$loop->first)<svg width="14" height="14" aria-hidden="true"><use href="#i-chevron-right"/></svg>@endif
          @if(!empty($breadcrumb['url']))
            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
          @else
            <span aria-current="page">{{ $breadcrumb['label'] }}</span>
          @endif
        @endforeach
      </nav>
    @endif
    <h1 class="page-title">{{ $title }}</h1>
    @if($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endif
  </div>
  @if(trim((string) $slot) !== '')<div class="page-header-actions">{{ $slot }}</div>@endif
</header>
