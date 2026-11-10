<!DOCTYPE html>
<html lang="{{ $lang }}" data-page="{{ $page }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{ $pageTitle }}</title>
	<meta name="description" content="@yield('description', $L('Plataforma para gestionar ventas, inventarios, almacenes, recursos humanos, despachos y prevención de pérdidas en supermercados, retail y cadenas multisucursal.', 'Platform to manage sales, inventory, warehouses, workforce, dispatch and loss prevention for supermarkets, retail and multi-branch chains.'))">
	<meta name="theme-color" content="#00a84a">
	<link rel="canonical" href="{{ $canonical }}">
	<link rel="alternate" hreflang="es" href="{{ $canonical }}?lang=es">
	<link rel="alternate" hreflang="en" href="{{ $canonical }}?lang=en">
	<link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
	<meta property="og:type" content="website">
	<meta property="og:title" content="{{ $pageTitle }}">
	<meta property="og:url" content="{{ $canonical }}">
	<meta property="og:image" content="{{ $U('/img/site/today.webp') }}">
	<meta property="og:locale" content="{{ $lang === 'en' ? 'en_US' : 'es_ES' }}">
	<link rel="icon" href="{{ $U('/img/icon.svg') }}" type="image/svg+xml">
	<script>document.documentElement.classList.add('js')</script>
	<link rel="stylesheet" href="{{ $A('/css/daily_site.css') }}">
	@yield('head')
	<script type="application/ld+json">{!! json_encode(['@' . 'context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'DAILY', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'inLanguage' => ['es', 'en'], 'description' => $L('Inteligencia y control para cada operación de tu negocio.', 'Intelligence and control for every operation in your business.')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body>
	<a class="s-skip" href="#main">{{ $L('Saltar al contenido', 'Skip to content') }}</a>
	@include('site.partials.icons')
	@include('site.partials.header')
	<main id="main" tabindex="-1">
		@yield('content')
	</main>
	@include('site.partials.footer')
	<dialog class="s-zoombox" id="s-zoombox" aria-label="{{ $L('Vista ampliada', 'Enlarged view') }}">
		<button class="s-zoombox__close" type="button" aria-label="{{ $L('Cerrar', 'Close') }}"><svg class="s-ico"><use href="#i-close"/></svg></button>
		<div class="s-zoombox__body"><img alt="" decoding="async"></div>
	</dialog>
	<script src="{{ $A('/js/daily_site.js') }}" defer></script>
	@stack('scripts')
</body>
</html>
