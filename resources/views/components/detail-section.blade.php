@props(['title', 'description' => null, 'icon' => 'file'])

<section {{ $attributes->class(['detail-section']) }}>
  <header class="detail-section-header">
    <svg aria-hidden="true"><use href="#i-{{ $icon }}"/></svg>
    <div><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>
  </header>
  <div class="detail-section-body">{{ $slot }}</div>
</section>
