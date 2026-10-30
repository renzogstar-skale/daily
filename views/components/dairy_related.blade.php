@php $dairyRel = \Grocy\Helpers\DairyContextNav::ForView($viewName); @endphp
@if($dairyRel)
{{-- Navegación contextual: conexiones operativas verificadas (SystemMapCatalog::CONNECTIONS), filtradas por permiso --}}
<section class="dairy-related d-print-none" aria-label="Módulos conectados">
	<div class="dairy-related-head">
		<i class="fa-solid fa-diagram-project"></i>
		<span>Módulos conectados con <strong>{{ $dairyRel['module'] }}</strong></span>
		@if(\Grocy\Helpers\DairyAccess::CanOpen('/system-map'))<a href="{{ $U('/system-map') }}" class="dairy-related-map">Ver mapa del sistema</a>@endif
	</div>
	<div class="dairy-related-grid">
		@foreach(['in' => 'Recibe información de', 'out' => 'Actualiza / alimenta a'] as $dir => $title)
		@if(count($dairyRel[$dir]))
		<div class="dairy-related-col">
			<div class="dairy-related-title">{{ $title }}</div>
			@foreach($dairyRel[$dir] as $l)
			<a class="dairy-related-link" href="{{ $U($l['route']) }}" title="{{ $l['label'] }}">
				<span class="dairy-related-icon"><i class="fa-solid {{ $l['icon'] }}"></i></span>
				<span class="dairy-related-text"><strong>{{ $l['name'] }}</strong><small>{{ $l['label'] }}</small></span>
				<i class="fa-solid {{ $dir === 'in' ? 'fa-arrow-left' : 'fa-arrow-right' }} dairy-related-arrow"></i>
			</a>
			@endforeach
		</div>
		@endif
		@endforeach
	</div>
</section>
@endif
