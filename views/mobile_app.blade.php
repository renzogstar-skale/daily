<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	<script>
		(function() {
			try {
				var t = localStorage.getItem('daily_theme') || 'green';
				if (t === 'red' || t === 'yellow') {
					document.documentElement.setAttribute('data-theme', t);
				} else {
					document.documentElement.removeAttribute('data-theme');
				}
			} catch(e) {}
		})();
	</script>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#f5f7fa" id="mMetaThemeColor">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="DAILY">
	<meta name="format-detection" content="telephone=no">
	<meta name="robots" content="noindex,nofollow">
	<link rel="apple-touch-icon" href="{{ $U('/img/icon-1024.png?v=', true) }}{{ $version }}">
	<link rel="icon" type="image/svg+xml" href="{{ $U('/img/icon.svg?v=', true) }}{{ $version }}">
	<link rel="manifest" crossorigin="use-credentials" href="{{ $U('/manifest') . '?data=' . base64_encode('DAILY Mobile#' . $U('/m')) }}">
	<title>DAILY Mobile</title>
	<link href="{{ $U('/packages/@fontsource/roboto/400.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/packages/@fontsource/roboto/500.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/packages/@fontsource/roboto/700.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/all.min.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/css/dairy_m.css?v=', true) }}{{ $version }}-{{ $assetV }}" rel="stylesheet">
	<link href="{{ $U('/css/daily_theme.css?v=', true) }}{{ $version }}-{{ $assetV }}" rel="stylesheet">
</head>

<body>
	<div id="m-app" class="m-app" aria-live="polite">
		<header class="m-top" id="m-top">
			<button type="button" class="m-icon-btn" id="m-back" aria-label="Volver" hidden><i class="fa-solid fa-chevron-left"></i></button>
			<div class="m-top-title">
				<div class="m-top-h" id="m-title">DAILY</div>
				<button type="button" class="m-branch" id="m-branch" aria-label="Cambiar sucursal"><i class="fa-solid fa-store"></i> <span id="m-branch-name">Toda la red</span> <i class="fa-solid fa-chevron-down m-xs"></i></button>
			</div>
			<button type="button" class="m-icon-btn m-bell" id="m-notif-btn" aria-label="Notificaciones" hidden><i class="fa-solid fa-bell"></i><b class="m-badge" id="m-notif-badge" hidden></b></button>
			<button type="button" class="m-icon-btn" id="m-search-btn" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
		</header>
		<nav class="m-crumbs" id="m-crumbs" aria-label="Ruta" hidden></nav>
		<div class="m-offline" id="m-offline" role="status" hidden></div>
		<main class="m-main" id="m-main" tabindex="-1"></main>
		<nav class="m-tabs" id="m-tabs" aria-label="Secciones">
			<a href="#/home" data-tab="home"><i class="fa-solid fa-house"></i><span>Inicio</span></a>
			<a href="#/tasks" data-tab="tasks"><i class="fa-solid fa-list-check"></i><span>Tareas</span><b class="m-badge" id="m-inbox-badge" hidden></b></a>
			<a href="#/scan" data-tab="scan" class="m-tab-scan"><span class="ring"><i class="fa-solid fa-barcode"></i></span><span>Escanear</span></a>
			<a href="#/notifications" data-tab="notifications"><i class="fa-solid fa-bell"></i><span>Alertas</span><b class="m-badge" id="m-alerts-badge" hidden></b></a>
			<a href="#/menu" data-tab="menu"><i class="fa-solid fa-bars"></i><span>Menú</span></a>
		</nav>
	</div>
	<div class="m-sheet-wrap" id="m-sheet" hidden>
		<div class="m-sheet-bg" data-close></div>
		<div class="m-sheet" role="dialog" aria-modal="true" aria-labelledby="m-sheet-title">
			<div class="m-sheet-grip"></div>
			<div class="m-sheet-head"><h2 id="m-sheet-title"></h2><button type="button" class="m-icon-btn" data-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
			<div class="m-sheet-body" id="m-sheet-body"></div>
		</div>
	</div>
	<div class="m-toast" id="m-toast" role="status" hidden></div>
	<script>
		window.DAIRY_M = {
			base: '{{ rtrim($U('/'), '/') }}',
			userId: {{ GROCY_USER_ID }},
			webUrl: '{{ $U('/today') }}',
			zxingUrl: '{{ $U('/packages/@zxing/library/umd/index.min.js?v=', true) }}{{ $version }}',
			swUrl: '{{ $U('/sw.js', true) }}'
		};
	</script>
	<script src="{{ $U('/js/dairy_m.js?v=', true) }}{{ $version }}-{{ $assetV }}"></script>
</body>

</html>
