<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	@include('components.daily_i18n_head')
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
	<meta name="theme-color" content="#ffffff" id="mMetaThemeColor">
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
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/all.min.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/css/dairy_m.css?v=', true) }}{{ daily_asset_version('/css/dairy_m.css') }}-{{ $assetV }}" rel="stylesheet">
	<link href="{{ $U('/css/daily_theme.css?v=', true) }}{{ daily_asset_version('/css/daily_theme.css') }}-{{ $assetV }}" rel="stylesheet">
</head>

<body>
	<div id="m-app" class="m-app" aria-live="polite">
		<!-- Barra de estado estilo iPhone / Dynamic Island -->
		<div class="m-ios-status-bar" aria-hidden="true">
			<span class="m-status-time" id="m-status-time">11:11</span>
			<div class="m-dynamic-island"></div>
			<div class="m-status-icons">
				<svg width="17" height="11" viewBox="0 0 17 11" fill="currentColor">
					<rect x="0" y="7" width="3" height="4" rx="1"/>
					<rect x="4.5" y="5" width="3" height="6" rx="1"/>
					<rect x="9" y="2.5" width="3" height="8.5" rx="1"/>
					<rect x="13.5" y="0" width="3" height="11" rx="1"/>
				</svg>
				<svg width="15" height="11" viewBox="0 0 16 12" fill="currentColor">
					<path d="M8 9.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-4.24-2.83a6 6 0 0 1 8.48 0 .8.8 0 1 1-1.13 1.13 4.4 4.4 0 0 0-6.22 0 .8.8 0 0 1-1.13-1.13ZM.93 3.84a10.5 10.5 0 0 1 14.14 0 .8.8 0 0 1-1.07 1.19 9 9 0 0 0-12 0 .8.8 0 0 1-1.07-1.19Z"/>
				</svg>
				<div class="m-ico-battery">
					<div class="m-battery-fill"></div>
				</div>
			</div>
		</div>

		<!-- Header superior profesional blanco/verde -->
		<header class="m-top" id="m-top">
			<button type="button" class="m-icon-btn m-back-btn" id="m-back" aria-label="Volver" hidden>
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
			</button>
			<div class="m-top-brand" id="m-top-brand">
				<a href="#/home" class="m-brand-link">
					<img src="{{ $U('/img/logo.svg') }}" alt="DAILY" class="m-brand-logo" height="28">
				</a>
			</div>
			<div class="m-top-title" id="m-top-title" hidden>
				<div class="m-top-h" id="m-title">DAILY</div>
				<button type="button" class="m-branch" id="m-branch" aria-label="Cambiar sucursal"><i class="fa-solid fa-store"></i> <span id="m-branch-name">Toda la red</span> <i class="fa-solid fa-chevron-down m-xs"></i></button>
			</div>
			<div class="m-top-actions">
				<button type="button" class="m-icon-btn m-lang-pill notranslate" id="m-lang-btn" aria-label="Idioma / Language" onclick="DailyI18n.set(DailyI18n.lang === 'en' ? 'es' : 'en')">
					<span data-daily-lang-current>{{ strtoupper($dailyLang ?? 'ES') }}</span>
				</button>
				<button type="button" class="m-icon-btn m-bell-btn" id="m-notif-btn" aria-label="Notificaciones">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
						<path d="M13.73 21a2 2 0 0 1-3.46 0"/>
					</svg>
					<b class="m-badge" id="m-notif-badge">1</b>
				</button>
				<button type="button" class="m-icon-btn m-opts-btn" id="m-opts-btn" aria-label="Opciones">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="1.2"/>
						<circle cx="12" cy="5" r="1.2"/>
						<circle cx="12" cy="19" r="1.2"/>
					</svg>
				</button>
				<button type="button" class="m-icon-btn" id="m-search-btn" aria-label="Buscar" hidden>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
				</button>
			</div>
		</header>

		<nav class="m-crumbs" id="m-crumbs" aria-label="Ruta" hidden></nav>
		<div class="m-offline" id="m-offline" role="status" hidden></div>
		
		<!-- Contenedor principal de pantallas -->
		<main class="m-main" id="m-main" tabindex="-1"></main>

		<!-- Barra de navegación fija inferior -->
		<nav class="m-tabs" id="m-tabs" aria-label="Secciones">
			<a href="#/home" data-tab="home" class="m-tab-item on">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="m-tab-icon">
					<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
					<polyline points="9 22 9 12 15 12 15 22" fill="#ffffff" stroke="#ffffff" stroke-width="1.5"/>
				</svg>
				<span class="m-tab-label" data-i18n-es="Inicio" data-i18n-en="Home">Inicio</span>
			</a>
			<a href="#/tasks" data-tab="tasks" class="m-tab-item">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="m-tab-icon">
					<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
					<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
					<path d="m9 14 2 2 4-4"/>
				</svg>
				<span class="m-tab-label" data-i18n-es="Tareas" data-i18n-en="Tasks">Tareas</span>
				<b class="m-badge" id="m-inbox-badge" hidden></b>
			</a>
			<a href="#/scan" data-tab="scan" class="m-tab-item m-tab-scan-fab" aria-label="Escanear">
				<span class="m-scan-ring">
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M3 7V5a2 2 0 0 1 2-2h2"/>
						<path d="M17 3h2a2 2 0 0 1 2 2v2"/>
						<path d="M21 17v2a2 2 0 0 1-2 2h-2"/>
						<path d="M7 21H5a2 2 0 0 1-2-2v-2"/>
						<line x1="7" y1="8" x2="7" y2="16"/>
						<line x1="10" y1="8" x2="10" y2="16"/>
						<line x1="14" y1="8" x2="14" y2="16"/>
						<line x1="17" y1="8" x2="17" y2="16"/>
					</svg>
				</span>
			</a>
			<a href="#/notifications" data-tab="notifications" class="m-tab-item">
				<div class="m-tab-icon-wrap">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="m-tab-icon">
						<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
						<path d="M13.73 21a2 2 0 0 1-3.46 0"/>
					</svg>
					<b class="m-badge" id="m-alerts-badge">3</b>
				</div>
				<span class="m-tab-label" data-i18n-es="Alertas" data-i18n-en="Alerts">Alertas</span>
			</a>
			<a href="#/menu" data-tab="menu" class="m-tab-item">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="m-tab-icon">
					<line x1="4" y1="6" x2="20" y2="6"/>
					<line x1="4" y1="12" x2="20" y2="12"/>
					<line x1="4" y1="18" x2="20" y2="18"/>
				</svg>
				<span class="m-tab-label" data-i18n-es="Menú" data-i18n-en="Menu">Menú</span>
			</a>
		</nav>

		<!-- Home indicator bar para estilo nativo iOS -->
		<div class="m-home-indicator" aria-hidden="true"></div>
	</div>

	<!-- Modal tipo Sheet inferior para detalles y acciones -->
	<div class="m-sheet-wrap" id="m-sheet" hidden>
		<div class="m-sheet-bg" data-close></div>
		<div class="m-sheet" role="dialog" aria-modal="true" aria-labelledby="m-sheet-title">
			<div class="m-sheet-grip"></div>
			<div class="m-sheet-head"><h2 id="m-sheet-title"></h2><button type="button" class="m-icon-btn" data-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
			<div class="m-sheet-body" id="m-sheet-body"></div>
		</div>
	</div>

	<!-- Escáner Inteligente a Pantalla Completa -->
	<div class="m-scanner-modal" id="m-scanner-modal" hidden>
		<div class="m-scanner-header">
			<button type="button" class="m-scanner-close" id="m-scanner-close-btn" aria-label="Cerrar escáner">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
			</button>
			<div class="m-scanner-title">Escáner Inteligente</div>
			<div class="m-scanner-actions">
				<button type="button" class="m-scanner-ctrl" id="m-scanner-torch-btn" aria-label="Linterna">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				</button>
			</div>
		</div>
		<div class="m-scanner-viewport">
			<video id="m-scanner-video" class="m-scanner-video" playsinline muted></video>
			<div class="m-scanner-overlay">
				<div class="m-scanner-reticle">
					<div class="m-reticle-corner m-rc-tl"></div>
					<div class="m-reticle-corner m-rc-tr"></div>
					<div class="m-reticle-corner m-rc-bl"></div>
					<div class="m-reticle-corner m-rc-br"></div>
					<div class="m-scanner-laser"></div>
				</div>
				<p class="m-scanner-tip">Apunta el código de barras dentro del marco</p>
			</div>
		</div>
		<div class="m-scanner-bottom">
			<div class="m-quick-sim-bar">
				<span class="m-sim-lbl">Códigos rápidos de prueba:</span>
				<div class="m-sim-pills">
					<button type="button" class="m-sim-pill" data-code="7791234567890">Yogur Frutilla</button>
					<button type="button" class="m-sim-pill" data-code="041570054341">Leche Descremada</button>
					<button type="button" class="m-sim-pill" data-code="070560000454">Queso Gouda</button>
				</div>
			</div>
			<form id="m-scanner-input-form" class="m-scanner-input-bar">
				<input type="text" id="m-scanner-code-input" class="m-scanner-input" placeholder="Ingresar código manual (EAN, UPC, Lote)..." autocomplete="off">
				<button type="submit" class="m-scanner-input-submit" aria-label="Buscar código">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
				</button>
			</form>
		</div>
	</div>

	<!-- Modal de Producto Escaneado -->
	<div class="m-scan-result-sheet" id="m-scan-result-sheet" hidden>
		<div class="m-scan-result-bg" id="m-scan-result-bg"></div>
		<div class="m-scan-result-panel">
			<div class="m-scan-result-grip"></div>
			<div class="m-scan-result-content" id="m-scan-result-content"></div>
		</div>
	</div>

	<!-- Notificación Toast con animación -->
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
	<script src="{{ $U('/js/dairy_m.js?v=', true) }}{{ daily_asset_version('/js/dairy_m.js') }}-{{ $assetV }}"></script>
</body>

</html>
