{{-- ==========================================================================
     DAILY — COMPONENTE MAESTRO: DailyDashboardLayout
     Centraliza el fondo transparente cristalino (Liquid Glass / Crystal UI),
     espaciado uniforme, encabezados estándar, contenedor responsive y
     transiciones continuas sin pantallas blancas para todos los dashboards de DAILY.
     ========================================================================== --}}
@props([
	'id' => null,
	'title' => null,
	'subtitle' => null,
	'badge' => null,
	'headerRight' => null,
	'tabs' => null,
	'filters' => null,
	'kpis' => null,
	'class' => '',
	'loading' => false,
])

<div @if($id) id="{{ $id }}" @endif class="daily-dashboard-layout crystal-canvas {{ $class }}">
	@if($title || $subtitle || $badge || $headerRight)
	<header class="daily-dashboard-header">
		<div class="daily-dashboard-header-left">
			@if($title)<h1 class="daily-dashboard-title">{{ $title }}</h1>@endif
			@if($subtitle)<p class="daily-dashboard-subtitle">{{ $subtitle }}</p>@endif
		</div>
		@if($badge || $headerRight)
		<div class="daily-dashboard-header-right">
			@if($badge)<span class="daily-dashboard-badge">{{ $badge }}</span>@endif
			@if($headerRight){!! $headerRight !!}@endif
		</div>
		@endif
	</header>
	@endif

	@if($tabs)
	<div class="daily-dashboard-tabs">
		{!! $tabs !!}
	</div>
	@endif

	@if($filters)
	<div class="daily-dashboard-filters">
		{!! $filters !!}
	</div>
	@endif

	@if($kpis)
	<div class="daily-dashboard-kpis">
		{!! $kpis !!}
	</div>
	@endif

	<div class="daily-dashboard-content @if($loading) is-loading @endif">
		@if($loading)
		<div class="daily-dashboard-skeleton" aria-hidden="true">
			<div class="skeleton-card skeleton-header"></div>
			<div class="skeleton-grid">
				<div class="skeleton-card"></div>
				<div class="skeleton-card"></div>
				<div class="skeleton-card"></div>
			</div>
		</div>
		@else
		{{ $slot }}
		@endif
	</div>
</div>

