@php global $GROCY_REQUIRED_FRONTEND_PACKAGES; @endphp

<!DOCTYPE html>
@php
$dailyLang = \Grocy\Helpers\DailyI18n::Lang();
// Modo Día/Noche: cookie daily_mode (la escribe DailyTheme) o el ajuste de usuario night_mode (otros dispositivos)
$dailyModePref = in_array($_COOKIE['daily_mode'] ?? '', ['light', 'dark', 'system'], true) ? $_COOKIE['daily_mode'] : ['on' => 'dark', 'off' => 'light'][$userSettings['night_mode'] ?? ''] ?? 'system';
$dailyDark = $dailyModePref === 'dark';
@endphp
<html lang="{{ $dailyLang }}"
	dir="{{ $dir }}"@if($dailyDark) class="daily-dark daily-night" style="color-scheme: dark"@endif>

<head>
	<!-- Cero flash blanco: fondo ambiental de DAILY aplicado inmediatamente antes de cualquier pintado -->
	<style>
		html, body {
			background-color: #f7fbf8 !important;
			background: #f7fbf8 !important;
		}
		html.daily-dark, html.daily-dark body {
			background-color: #0b1220 !important;
			background: #0b1220 !important;
		}
	</style>
	<!-- Modo Día/Noche antes del primer pintado (preferencia guardada o la del sistema) -->
	<script>window.DAILY_MODE_SERVER = '{{ $dailyModePref }}';</script>
	<script src="{{ $U('/js/daily_mode.js?v=', true) }}{{ daily_asset_version('/js/daily_mode.js') }}"></script>
	<link href="{{ $U('/css/daily_nav.css?v=', true) }}{{ daily_asset_version('/css/daily_nav.css') }}" rel="stylesheet">
	<link href="{{ $U('/css/daily_filters.css?v=', true) }}{{ daily_asset_version('/css/daily_filters.css') }}" rel="stylesheet">
	<script src="{{ $U('/js/daily_nav.js?v=', true) }}{{ daily_asset_version('/js/daily_nav.js') }}"></script>
	<script src="{{ $U('/js/daily_errors.js?v=', true) }}{{ daily_asset_version('/js/daily_errors.js') }}"></script>
	<!-- Iconos listos en el primer cuadro de cada dashboard (font-display: block los ocultaba al llegar) -->
	<link rel="preload" href="{{ $U('/packages/@fortawesome/fontawesome-free/webfonts/fa-solid-900.woff2', true) }}" as="font" type="font/woff2" crossorigin>
	<!-- Inter: display=fallback espera hasta 100 ms la fuente (en caché llega a tiempo) y así el texto no salta de ancho al cargar -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=fallback" rel="stylesheet">

	<script>window.DAILY_I18N_SRC = '{{ $U('/i18n/daily_en.js?v=', true) }}{{ daily_asset_version('/i18n/daily_en.js') }}';</script>
	@if($dailyLang === 'en')
	<script src="{{ $U('/i18n/daily_en.js?v=', true) }}{{ daily_asset_version('/i18n/daily_en.js') }}"></script>
	@endif
	<script src="{{ $U('/js/daily_i18n.js?v=', true) }}{{ daily_asset_version('/js/daily_i18n.js') }}"></script>
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

	<meta charset="utf-8">
	<meta name="viewport"
		content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color"
		content="#00C83B"
		id="metaThemeColor">
	<meta name="mobile-web-app-capable"
		content="yes">
	<meta name="apple-mobile-web-app-capable"
		content="yes">
	<meta name="apple-mobile-web-app-status-bar-style"
		content="default">
	<meta name="apple-mobile-web-app-title"
		content="DAILY">
	<meta name="format-detection"
		content="telephone=no">
	<link rel="apple-touch-icon"
		href="{{ $U('/img/icon-1024.png?v=', true) }}{{ $version }}">
	<meta name="robots"
		content="noindex,nofollow">

	<link rel="icon"
		type="image/svg+xml"
		href="{{ $U('/img/icon.svg?v=', true) }}{{ $version }}">
	<link rel="icon"
		type="image/png"
		sizes="32x32"
		href="{{ $U('/img/icon-32.png?v=', true) }}{{ $version }}">

	@if (GROCY_AUTHENTICATED)
	<link rel="manifest"
		crossorigin="use-credentials"
		href="{{ $U('/manifest') . '?data=' . base64_encode($__env->yieldContent('title') . '#' . $U($_SERVER['REQUEST_URI'])) }}">
	@endif

	<title>@yield('title') | DAILY</title>

	<link href="{{ $U('/packages/@fontsource/roboto/400.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fontsource/roboto/500.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fontsource/roboto/700.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/bootstrap/dist/css/bootstrap.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/fontawesome.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/solid.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/toastr/build/toastr.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">

	@if(in_array('bootstrap-combobox', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/@danielfarrell/bootstrap-combobox/css/bootstrap-combobox.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('bootstrap-select', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/bootstrap-select/dist/css/bootstrap-select.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('datatables', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/datatables.net-bs4/css/dataTables.bootstrap4.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/datatables.net-colreorder-bs4/css/colReorder.bootstrap4.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/datatables.net-rowgroup-bs4/css/rowGroup.bootstrap4.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/packages/datatables.net-select-bs4/css/select.bootstrap4.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('tempusdominus', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/tempusdominus-bootstrap-4/build/css/tempusdominus-bootstrap-4.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('summernote', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/summernote/dist/summernote-bs4.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('animatecss', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/animate.css/animate.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('fullcalendar', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/fullcalendar/dist/fullcalendar.min.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif
	@if(in_array('daterangepicker', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<link href="{{ $U('/packages/daterangepicker/daterangepicker.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif

	<link href="{{ $U('/css/grocy_menu_layout.css?v=', true) }}{{ daily_asset_version('/css/grocy_menu_layout.css') }}"
		rel="stylesheet">
	<link href="{{ $U('/css/grocy.css?v=', true) }}{{ daily_asset_version('/css/grocy.css') }}"
		rel="stylesheet">
	<link href="{{ $U('/css/dairy_enterprise_theme.css?v=', true) }}{{ daily_asset_version('/css/dairy_enterprise_theme.css') }}"
		rel="stylesheet">
	<link href="{{ $U('/css/dairy_glass.css?v=', true) }}{{ daily_asset_version('/css/dairy_glass.css') }}"
		rel="stylesheet">
	<link href="{{ $U('/css/daily_theme.css?v=', true) }}{{ daily_asset_version('/css/daily_theme.css') }}"
		rel="stylesheet">
	<link href="{{ $U('/css/daily_design_system.css?v=', true) }}{{ daily_asset_version('/css/daily_design_system.css') }}"
		rel="stylesheet">
	<link href="{{ $U('/css/daily_process_flow.css?v=', true) }}{{ daily_asset_version('/css/daily_process_flow.css') }}"
		rel="stylesheet">


	@stack('pageStyles')
	<link href="{{ $U('/css/daily_subscription.css?v=', true) }}{{ daily_asset_version('/css/daily_subscription.css') }}"
		rel="stylesheet">

	<link href="{{ $U('/css/daily_crystal.css?v=', true) }}{{ daily_asset_version('/css/daily_crystal.css') }}"
		rel="stylesheet">
	<!-- Modo Noche global (tokens + componentes + adaptador de colores fijos) -->
	<link href="{{ $U('/css/daily_dark.css?v=', true) }}{{ daily_asset_version('/css/daily_dark.css') }}"
		rel="stylesheet">
	<!-- Efecto Hover interactivo verde en todas las tarjetas KPI de DAILY -->
	<link href="{{ $U('/css/daily_kpi_hover.css?v=', true) }}{{ daily_asset_version('/css/daily_kpi_hover.css') }}"
		rel="stylesheet">
	<!-- Capa móvil: áreas seguras, filtros compactos e indicadores esenciales (solo ≤ 991 px) -->
	<link href="{{ $U('/css/daily_mobile.css?v=', true) }}{{ daily_asset_version('/css/daily_mobile.css') }}"
		rel="stylesheet">

	@if(file_exists(GROCY_DATAPATH . '/custom_css.html'))
	@php include GROCY_DATAPATH . '/custom_css.html' @endphp
	@endif
	<script>
		var Grocy = { };
		Grocy.Components = { };
		Grocy.Mode = '{{ GROCY_MODE }}';
		Grocy.BaseUrl = '{{ $U('/') }}';
		Grocy.CurrentUrlRelative = "/" + window.location.href.split('?')[0].replace(Grocy.BaseUrl, "");
		Grocy.View = '{{ $viewName }}';
		Grocy.Currency = '{{ GROCY_CURRENCY }}';
		Grocy.EnergyUnit = '{{ GROCY_ENERGY_UNIT }}';
		Grocy.CalendarFirstDayOfWeek = '{{ GROCY_CALENDAR_FIRST_DAY_OF_WEEK }}';
		Grocy.CalendarShowWeekNumbers = {{ BoolToString(GROCY_CALENDAR_SHOW_WEEK_OF_YEAR) }};
		Grocy.LocalizationStrings = {!! $LocalizationStrings !!};
		Grocy.LocalizationStringsQu = {!! $LocalizationStringsQu !!};
		Grocy.FeatureFlags = {!! json_encode($featureFlags) !!};
		Grocy.Webhooks = {
		@if(GROCY_FEATURE_FLAG_LABEL_PRINTER && !GROCY_LABEL_PRINTER_RUN_SERVER)
			"labelprinter" : {
				"hook": "{{ GROCY_LABEL_PRINTER_WEBHOOK }}",
				"extra_data": {!! json_encode(GROCY_LABEL_PRINTER_PARAMS) !!},
				"json": {{ BoolToString(GROCY_LABEL_PRINTER_HOOK_JSON) }}
			}
		@endif
		};

		@if (GROCY_AUTHENTICATED)
		Grocy.UserId = {{ GROCY_USER_ID }};
		Grocy.UserSettings = {!! json_encode($userSettings) !!};
		Grocy.UserPermissions = {!! json_encode($permissions) !!};
		@else
		Grocy.UserId = -1;
		Grocy.UserSettings = { };
		@endif
	</script>
	@if (GROCY_AUTHENTICATED)
	@php $dailySub = \Grocy\Helpers\DailySubscription::ClientState(); @endphp
	<script>window.DAILY_SUBSCRIPTION = {!! json_encode($dailySub) !!};</script>
	@if(count($dailySub['lockedSelectors']) > 0)
	{{-- Herramientas internas fuera del plan: ocultas antes del primer pintado (sin parpadeo) --}}
	<style id="daily-plan-locks">{{ implode(',', $dailySub['lockedSelectors']) }}{display:none!important}</style>
	@endif
	@endif
</head>

<body class="fixed-nav crystal-ui @if($dailyDark) night-mode @endif @if($embedded) embedded @endif @if(($viewName ?? '') === 'login') daily-login-body @endif">
	<script>
		// Estado del sidebar antes del primer pintado (sin saltos de layout ni parpadeo del logo)
		try { var dnW = window.innerWidth, dnS = localStorage.getItem('dairy_sidebar_state') || localStorage.getItem('sidebar_state'); if (dnW >= 768 && (dnW < 1024 ? dnS !== 'expanded' : dnS === 'collapsed')) { document.body.classList.add('sidenav-toggled'); } } catch (e) { }
		document.body.classList.add('dnav-preload');
		document.body.classList.toggle('night-mode', document.documentElement.classList.contains('daily-dark'));
	</script>
	@if(!$embedded && ($viewName ?? '') !== 'login')
	<nav id="mainNav"
		class="navbar navbar-expand-md navbar-light fixed-top">
		<div class="header-brand-zone">
			<a class="navbar-brand py-0 mr-0 d-flex align-items-center"
				href="{{ $U('/app') }}"
				aria-label="DAILY — Gestión, Control y Beneficios" title="DAILY — Gestión, Control y Beneficios">
				<svg class="daily-logo-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 152 40" width="114" height="30" role="img" aria-label="DAILY">
					<defs>
						<radialGradient id="dailyBadgeGrad" cx="50%" cy="50%" r="50%">
							<stop offset="0%" stop-color="var(--daily-light, #39F500)"/>
							<stop offset="48%" stop-color="var(--daily-primary, #00C83B)"/>
							<stop offset="100%" stop-color="var(--daily-dark, #008E48)"/>
						</radialGradient>
						<linearGradient id="dailyDLetterGrad" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stop-color="#ffffff"/>
							<stop offset="100%" stop-color="#f0fdf4"/>
						</linearGradient>
						<filter id="dailyBadgeShadow" x="-20%" y="-20%" width="140%" height="140%">
							<feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#091e42" flood-opacity="0.15"/>
						</filter>
					</defs>
					<g filter="url(#dailyBadgeShadow)">
						<rect x="2" y="2" width="36" height="36" rx="9" fill="url(#dailyBadgeGrad)"/>
						<rect x="2.5" y="2.5" width="35" height="35" rx="8.5" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1"/>
						<path d="M 148 116 L 262 116 C 336 116 376 172 376 256 C 376 340 336 396 262 396 L 148 396 C 142 396 138 392 138 386 L 138 126 C 138 120 142 116 148 116 Z M 210 178 L 254 178 C 298 178 306 214 306 256 C 306 298 298 334 254 334 L 210 334 C 206 334 204 332 204 328 L 204 184 C 204 180 206 178 210 178 Z"
							fill="url(#dailyDLetterGrad)"
							fill-rule="evenodd"
							transform="translate(5.8, 5.8) scale(0.055)"/>
					</g>
					<text x="46" y="27"
						font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
						font-size="24"
						font-weight="800"
						letter-spacing="-0.01em"
						fill="url(#dailyBadgeGrad)"
						class="daily-brand-text">DAILY</text>
				</svg>
			</a>
			@if(GROCY_AUTHENTICATED)
			<button type="button"
				id="headerCollapseBtn"
				class="sidebar-toggle-btn d-none d-md-inline-flex"
				title="Contraer menú lateral"
				aria-label="Contraer menú lateral">
				<i class="fa-solid fa-chevron-left"></i>
			</button>
			@endif
		</div>
		<span id="clock-container"
			class="text-muted font-italic d-none"
			style="display: none !important;">
			<i class="fa-solid fa-clock"></i>
			<span id="clock-small"
				class="d-inline d-sm-none"></span>
			<span id="clock-big"
				class="d-none d-sm-inline"></span>
		</span>

		@if(GROCY_AUTHENTICATED)
		<button class="navbar-toggler navbar-toggler-right"
			type="button"
			data-toggle="collapse"
			data-target="#sidebarResponsive">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div id="sidebarResponsive"
			class="collapse navbar-collapse">
			@include('components.dairy_sidebar')


			<!-- BUSCADOR GLOBAL ENTERPRISE CON SHORTCUT CTRL+K -->
			<div class="header-search-container d-none d-lg-flex align-items-center ml-3 mr-auto">
				<button type="button" id="globalCommandSearch" class="dairy-hsearch" onclick="$('#commandPaletteModal').modal('show');" aria-label="Buscar productos, lotes, casos y proveedores (Ctrl + K)">
					<i class="fa-solid fa-magnifying-glass"></i>
					<span class="dairy-hsearch-text">Buscar productos, lotes, casos, proveedores…</span>
					<kbd>Ctrl K</kbd>
				</button>
			</div>

			<ul class="navbar-nav ml-auto align-items-center">
				<!-- Selector de Sucursal -->
				@php
				$hdrBranches = [];
				try { $hdrBranches = \Grocy\Helpers\DairyAccess::FilterBranches(\Grocy\Services\BranchService::GetInstance()->GetBranches()); } catch (\Throwable $e) { }
				$hdrRestricted = \Grocy\Helpers\DairyAccess::AllowedBranches() !== null;
				$hdrBranchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? intval($_GET['branch_id']) : null;
				$hdrBranchName = 'Red Consolidada';
				foreach ($hdrBranches as $hb) { if ($hdrBranchId !== null && intval($hb->id) === $hdrBranchId) { $hdrBranchName = $hb->name; } }
				@endphp
				<li class="nav-item dropdown mr-2 d-none align-items-center">
					<button class="dairy-hbtn dropdown-toggle" type="button" data-toggle="dropdown" aria-label="Seleccionar sucursal">
						<i class="fa-solid fa-store"></i>
						<span id="headerCurrentBranchName">{{ $hdrBranchName }}</span>
					</button>
					<div class="dropdown-menu dropdown-menu-right" style="min-width: 220px;">
						<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: var(--dairy-fs-xs);">Centro Operativo</div>
						@foreach($hdrBranches as $hb)
						<a class="dropdown-item @if($hdrBranchId === intval($hb->id)) active font-weight-bold @endif" href="?branch_id={{ $hb->id }}">@if($hdrBranchId === intval($hb->id))<i class="fa-solid fa-check text-primary mr-2"></i>@endif{{ $hb->name }}</a>
						@endforeach
						@unless($hdrRestricted)
						<div class="dropdown-divider"></div>
						<a class="dropdown-item @if($hdrBranchId === null) active font-weight-bold @endif" href="?branch_id="><i class="fa-solid fa-network-wired text-muted mr-2"></i>Red Consolidada (Todas)</a>
						@endunless
					</div>
				</li>

				<!-- Variables de Rol y Suscripción para el Menú Integrado -->
				@php
				$simRole = \Grocy\Helpers\DairyAccess::GetSimulatedRole();
				$simRoleLabels = [
					'manager' => 'Manager',
					'supervisor' => 'Supervisor',
					'empleado' => 'Empleado',
					'demo' => 'Recorrido guiado'
				];
				$simRoleIcons = [
					'manager' => 'fa-user-tie text-primary',
					'supervisor' => 'fa-user-shield text-warning',
					'empleado' => 'fa-user text-success',
					'demo' => 'fa-id-badge text-info'
				];
				@endphp

				<!-- 1. Botón Independiente: Idioma global ES / EN -->
				<li class="nav-item dropdown mr-2 d-flex align-items-center daily-lang-switch">
					<button class="dairy-hbtn dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Idioma" title="Idioma de la plataforma">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -3px;"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
						<strong class="ml-1 notranslate" data-daily-lang-current>{{ strtoupper($dailyLang) }}</strong>
					</button>
					<div class="dropdown-menu dropdown-menu-right" role="menu" style="min-width: 190px;">
						<a class="dropdown-item d-flex align-items-center notranslate @if($dailyLang === 'es') active @endif" href="#" role="menuitemradio" data-daily-lang="es" aria-checked="{{ $dailyLang === 'es' ? 'true' : 'false' }}">
							<span class="mr-2" aria-hidden="true">🇪🇸</span><span class="flex-grow-1">Español (ES)</span><i class="fa-solid fa-check ml-2 daily-lang-check"></i>
						</a>
						<a class="dropdown-item d-flex align-items-center notranslate @if($dailyLang === 'en') active @endif" href="#" role="menuitemradio" data-daily-lang="en" aria-checked="{{ $dailyLang === 'en' ? 'true' : 'false' }}">
							<span class="mr-2" aria-hidden="true">🇺🇸</span><span class="flex-grow-1">English (EN)</span><i class="fa-solid fa-check ml-2 daily-lang-check"></i>
						</a>
					</div>
				</li>
				<style>.daily-lang-switch .daily-lang-check { visibility: hidden; } .daily-lang-switch .dropdown-item.active .daily-lang-check { visibility: visible; }</style>

				<!-- 1b. Modo Día / Noche global (DailyTheme, daily_mode.js) -->
				<li class="nav-item mr-2 d-flex align-items-center daily-mode-switch">
					<button type="button" class="dairy-hbtn daily-mode-toggle" id="dailyModeToggle" data-daily-mode-toggle aria-pressed="{{ $dailyDark ? 'true' : 'false' }}" aria-label="{{ $dailyDark ? 'Cambiar a modo día' : 'Cambiar a modo noche' }}" title="Modo Día / Noche">
						<span class="daily-mode-thumb" aria-hidden="true"></span>
						<span class="daily-mode-ico daily-mode-ico--sun" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg></span>
						<span class="daily-mode-ico daily-mode-ico--moon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></span>
					</button>
				</li>

				<!-- 2. Botón Independiente: Notificaciones activas -->
				<li class="nav-item mr-2 position-relative d-flex align-items-center">
					<button class="dairy-hicon" type="button" id="headerAlertsButton" aria-label="Notificaciones" title="Notificaciones y alertas activas" onclick="var t = $('#tab-alertas-link, [href=\'#tab-prevencion\']').first(); if (t.length) { t.click(); } else { window.location.href = '{{ $U(\Grocy\Helpers\DairyAccess::CanOpen('/today') ? '/today' : '/alerts') }}'; }">
						<i class="fa-solid fa-bell"></i>
						<span id="headerAlertsBadge" class="dairy-hicon-badge d-none"></span>
					</button>
				</li>

				<!-- 3. Perfil de Usuario Optimizado: [RG — rgiovine ▼] con Menú Organizado Completo -->
				@php
				$hdrRole = '';
				try {
					if (defined('GROCY_USER_ID')) {
						$hdrRole = (string) \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ' . intval(GROCY_USER_ID) . ' LIMIT 1')->fetchColumn();
					}
				} catch (\Throwable $e) { }
				$userInitials = defined('GROCY_USER_USERNAME') ? mb_strtoupper(mb_substr(GROCY_USER_USERNAME, 0, 2)) : 'RG';
				$userName = defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : 'rgiovine';
				$userTitle = $simRole === 'demo' ? 'Recorrido guiado' : 'Gerente / Administrador';
				@endphp
				<li class="nav-item dropdown mr-1 daily-user-profile-item">
					<a class="nav-link dropdown-toggle discrete-link py-1 px-2 d-flex align-items-center daily-profile-trigger"
						href="#"
						id="dailyUserProfileDropdown"
						data-toggle="dropdown"
						aria-haspopup="true"
						aria-expanded="false"
						title="Perfil de {{ $userName }}">
						<div class="daily-profile-avatar mr-2">
							{{ $userInitials }}
						</div>
						<div class="d-none d-sm-flex flex-column text-left mr-1.5" style="line-height: 1.15;">
							<span class="daily-profile-name font-weight-bold">{{ $userName }}</span>
							<span class="daily-profile-role-sub text-muted">{{ $userTitle }}</span>
						</div>
						<i class="fa-solid fa-chevron-down daily-profile-caret"></i>
					</a>

					<div class="dropdown-menu dropdown-menu-right daily-profile-dropdown-menu shadow-lg"
						aria-labelledby="dailyUserProfileDropdown">
						
						<!-- Encabezado de Perfil: RG — rgiovine / Gerente / Administrador -->
						<div class="daily-profile-header-card px-3 py-3 border-bottom d-flex align-items-center">
							<div class="daily-profile-avatar-lg mr-3">
								{{ $userInitials }}
							</div>
							<div class="flex-grow-1 overflow-hidden">
								<div class="d-flex align-items-center">
									<strong class="daily-profile-header-name text-truncate">{{ $userName }}</strong>
									<span class="daily-status-dot ml-2" title="Usuario activo"></span>
								</div>
								<div class="daily-profile-header-role text-muted text-truncate">
									{{ $userTitle }}
								</div>
							</div>
						</div>

						<!-- SECCIÓN 1: CUENTA Y ACCESO -->
						<div class="daily-menu-section-header px-3 pt-2.5 pb-1 text-uppercase">
							Cuenta y acceso
						</div>

						<!-- Mi perfil -->
						<a class="dropdown-item daily-profile-menu-item px-3 py-2 d-flex align-items-center" href="{{ $U('/usersettings') }}">
							<i class="fa-solid fa-user-circle mr-2.5 text-muted" style="width: 16px;"></i>
							<span class="flex-grow-1">Mi perfil</span>
						</a>

						<!-- Rol actual: Manager (Selector desplegable) -->
						<div class="daily-profile-control-box px-3 py-2">
							<div class="d-flex align-items-center justify-content-between mb-1.5">
								<span class="daily-profile-control-title">
									<i class="fa-solid {{ $simRoleIcons[$simRole] ?? 'fa-user-tie text-primary' }} mr-2" style="width: 16px;"></i>Rol actual:
								</span>
								<span class="badge badge-pill badge-primary-subtle font-weight-bold">
									{{ $simRoleLabels[$simRole] ?? 'Manager' }}
								</span>
							</div>
							<div class="daily-select-wrap">
								<select class="form-control form-control-sm daily-profile-select"
									id="dailyProfileRoleSelect"
									onchange="if(this.value) window.location.href='{{ $U('/daily/set-role?role=') }}' + this.value;"
									aria-label="Seleccionar rol">
									<option value="manager" @if($simRole === 'manager') selected @endif>🛡️ Manager (Toda la red y aprobaciones)</option>
									<option value="supervisor" @if($simRole === 'supervisor') selected @endif>👔 Supervisor (Piso, equipo e incidencias)</option>
									<option value="empleado" @if($simRole === 'empleado') selected @endif>👤 Empleado (Turnos y solicitudes)</option>
								</select>
							</div>
						</div>

						<!-- Plan actual: Enterprise (Selector / información del plan) -->
						<div class="daily-profile-control-box px-3 py-2">
							<div class="d-flex align-items-center justify-content-between mb-1.5">
								<span class="daily-profile-control-title">
									<i class="fa-solid {{ \Grocy\Helpers\DailySubscription::PLAN_INFO[$dailySub['plan']]['icon'] ?? 'fa-crown' }} text-warning mr-2" style="width: 16px;"></i>Plan actual:
								</span>
								<span class="badge badge-pill badge-success-subtle font-weight-bold">
									{{ $dailySub['planName'] }} @if($dailySub['preview'])· Vista previa @else · Activo @endif
								</span>
							</div>
							<div class="d-flex align-items-center" style="gap: 6px;">
								<select class="form-control form-control-sm daily-profile-select flex-grow-1"
									id="dailyProfilePlanSelect"
									onchange="window.location.href='{{ $U('/daily/set-plan?plan=') }}' + this.value;"
									aria-label="Seleccionar plan">
									@foreach(\Grocy\Helpers\DailySubscription::PLANS as $pp)
										@if(\Grocy\Helpers\DailySubscription::PlanRank($pp) <= \Grocy\Helpers\DailySubscription::PlanRank($dailySub['contractedPlan']))
											<option value="{{ $pp === $dailySub['contractedPlan'] ? '' : $pp }}" @if($pp === $dailySub['plan']) selected @endif>
												{{ \Grocy\Helpers\DailySubscription::PlanName($pp) }} @if($pp === $dailySub['contractedPlan'])(Contratado)@else(Vista previa)@endif
											</option>
										@endif
									@endforeach
								</select>
								<a href="{{ $U('/subscription') }}" class="btn btn-sm btn-outline-success daily-plan-link-btn" title="Gestionar planes y suscripción">
									<i class="fa-solid fa-arrow-up-right-from-square"></i>
								</a>
							</div>
						</div>

						<!-- Centro Operativo / Sucursal (Integrado en el perfil) -->
						<div class="daily-profile-control-box px-3 py-2">
							<div class="d-flex align-items-center justify-content-between mb-1.5">
								<span class="daily-profile-control-title">
									<i class="fa-solid fa-store text-muted mr-2" style="width: 16px;"></i>Centro Operativo:
								</span>
								<span class="badge badge-pill badge-light border text-muted font-weight-bold" style="font-size: 11px;">
									{{ $hdrBranchName }}
								</span>
							</div>
							<div class="daily-select-wrap">
								<select class="form-control form-control-sm daily-profile-select"
									onchange="window.location.href='?branch_id=' + this.value;"
									aria-label="Seleccionar centro operativo">
									@foreach($hdrBranches as $hb)
										<option value="{{ $hb->id }}" @if($hdrBranchId === intval($hb->id)) selected @endif>{{ $hb->name }}{{ !empty($hb->code) && !str_contains($hb->name, $hb->code) ? ' · ' . $hb->code : '' }}</option>
									@endforeach
									@unless($hdrRestricted)
										<option value="" @if($hdrBranchId === null) selected @endif>Red Consolidada (Todas)</option>
									@endunless
								</select>
							</div>
						</div>

						<!-- Configuración de cuenta -->
						<a class="dropdown-item daily-profile-menu-item px-3 py-2 d-flex align-items-center" href="{{ $U('/usersettings') }}">
							<i class="fa-solid fa-sliders mr-2.5 text-muted" style="width: 16px;"></i>
							<span class="flex-grow-1">Configuración de cuenta</span>
						</a>

						<div class="dropdown-divider my-1.5"></div>

						<!-- SECCIÓN 2: PREFERENCIAS -->
						<div class="daily-menu-section-header px-3 pt-2 pb-1 text-uppercase">
							Preferencias
						</div>

						<!-- Idioma -->
						<div class="daily-profile-control-box px-3 py-2">
							<div class="d-flex align-items-center justify-content-between mb-1.5">
								<span class="daily-profile-control-title">
									<i class="fa-solid fa-globe text-info mr-2" style="width: 16px;"></i>Idioma
								</span>
							</div>
							<div class="btn-group btn-group-sm w-100 daily-pref-lang-group" role="group">
								<button type="button" class="btn btn-outline-secondary py-1 @if($dailyLang === 'es') active font-weight-bold @endif" data-daily-lang="es">
									🇪🇸 Español (ES)
								</button>
								<button type="button" class="btn btn-outline-secondary py-1 @if($dailyLang === 'en') active font-weight-bold @endif" data-daily-lang="en">
									🇺🇸 English (EN)
								</button>
							</div>
						</div>

						<!-- Apariencia -->
						<div class="daily-profile-control-box px-3 py-2">
							<div class="d-flex align-items-center justify-content-between mb-1.5">
								<span class="daily-profile-control-title">
									<i class="fa-solid fa-palette text-success mr-2" style="width: 16px;"></i>Apariencia
								</span>
								<button type="button" class="btn btn-sm btn-light border py-0 px-2 daily-theme-mode-btn" id="dailyMenuThemeToggle" data-daily-mode-toggle title="Modo Día / Noche">
									<i class="fa-solid fa-circle-half-stroke mr-1"></i>Tema
								</button>
							</div>
							<!-- Selector de paleta de color de marca -->
							<div class="btn-group btn-group-toggle btn-group-sm w-100" data-toggle="buttons" id="dailyQuickThemePicker">
								<label class="btn btn-outline-secondary py-1 px-1 text-truncate @if(($userSettings['daily_theme_color'] ?? 'green') === 'green') active @endif" id="daily-btn-green" title="DAILY Verde" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="green"> <i class="fa-solid fa-circle text-success mr-1"></i>Verde
								</label>
								<label class="btn btn-outline-secondary py-1 px-1 text-truncate @if(($userSettings['daily_theme_color'] ?? '') === 'red') active @endif" id="daily-btn-red" title="DAILY Rojo" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="red"> <i class="fa-solid fa-circle text-danger mr-1"></i>Rojo
								</label>
								<label class="btn btn-outline-secondary py-1 px-1 text-truncate @if(($userSettings['daily_theme_color'] ?? '') === 'yellow') active @endif" id="daily-btn-yellow" title="DAILY Amarillo" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="yellow"> <i class="fa-solid fa-circle text-warning mr-1"></i>Amarillo
								</label>
							</div>
						</div>

						<div class="dropdown-divider my-1.5"></div>

						<!-- SECCIÓN 3: CERRAR SESIÓN -->
						<a class="dropdown-item daily-profile-menu-item daily-logout-item px-3 py-2.5 d-flex align-items-center text-danger font-weight-500" href="{{ $U('/logout') }}">
							<i class="fa-solid fa-arrow-right-from-bracket mr-2.5" style="width: 16px;"></i>
							<span>Cerrar sesión</span>
						</a>

					</div>
				</li>

				<li class="nav-item mr-2 d-none">
					<a class="btn-workforce-tienda discrete-link"
						href="{{ $U('/workforce') }}"
						data-toggle="tooltip"
						data-placement="bottom"
						title="Workforce Operations — Modo Piso de Tienda">
						<i class="fa-solid fa-mobile-screen-button mr-1"></i>
						<span class="d-none d-md-inline">Workforce Tienda</span>
					</a>
				</li>

				@if(GROCY_AUTHENTICATED)
				<li class="nav-item dropdown d-none">
					<a class="nav-link dropdown-toggle discrete-link"
						href="#"
						data-toggle="dropdown"><i class="fa-solid fa-sliders-h"></i> <span class="d-inline d-lg-none">{{ $__t('View settings') }}</span></a>

					<div class="dropdown-menu dropdown-menu-right">
						<div class="dropdown-item">
							<div class="form-check">
								<input class="form-check-input user-setting-control"
									type="checkbox"
									id="auto-reload-enabled"
									data-setting-key="auto_reload_on_db_change">
								<label class="form-check-label"
									for="auto-reload-enabled">
									{{ $__t('Auto reload on external changes') }}
								</label>
							</div>
						</div>
						<div class="dropdown-item">
							<div class="form-check">
								<input class="form-check-input user-setting-control"
									type="checkbox"
									id="show-clock-in-header"
									data-setting-key="show_clock_in_header">
								<label class="form-check-label"
									for="show-clock-in-header">
									{{ $__t('Show clock in header') }}
								</label>
							</div>
						</div>
						<div class="dropdown-divider"></div>
						<div class="dropdown-item pt-0">
							<div>
								{{ $__t('Night mode') }}
							</div>
							<div class="custom-control custom-radio custom-control-inline">
								<input class="custom-control-input user-setting-control"
									type="radio"
									name="night-mode"
									id="night-mode-on"
									value="on"
									data-setting-key="night_mode">
								<label class="custom-control-label"
									for="night-mode-on">{{ $__t('On') }}</label>
							</div>
							<div class="custom-control custom-radio custom-control-inline">
								<input class="custom-control-input user-setting-control"
									type="radio"
									name="night-mode"
									id="night-mode-follow-system"
									value="follow-system"
									data-setting-key="night_mode">
								<label class="custom-control-label"
									for="night-mode-follow-system">{{ $__t('Use system setting') }}</label>
							</div>
							<div class="custom-control custom-radio custom-control-inline">
								<input class="custom-control-input user-setting-control"
									type="radio"
									name="night-mode"
									id="night-mode-off"
									value="off"
									data-setting-key="night_mode">
								<label class="custom-control-label"
									for="night-mode-off">{{ $__t('Off') }}</label>
							</div>
						</div>
						<div class="dropdown-item">
							<div class="form-check">
								<input class="form-check-input user-setting-control"
									type="checkbox"
									id="auto-night-mode-enabled"
									data-setting-key="auto_night_mode_enabled">
								<label class="form-check-label"
									for="auto-night-mode-enabled">
									{{ $__t('Auto enable in time range') }}
								</label>
							</div>
							<div class="form-inline">
								<input type="text"
									class="form-control my-1 user-setting-control"
									readonly
									id="auto-night-mode-time-range-from"
									placeholder="{{ $__t('From') }} ({{ $__t('in format') }} HH:mm)"
									data-setting-key="auto_night_mode_time_range_from">
								<input type="text"
									class="form-control user-setting-control"
									readonly
									id="auto-night-mode-time-range-to"
									placeholder="{{ $__t('To') }} ({{ $__t('in format') }} HH:mm)"
									data-setting-key="auto_night_mode_time_range_to">
							</div>
							<div class="form-check mt-1">
								<input class="form-check-input user-setting-control"
									type="checkbox"
									id="auto-night-mode-time-range-goes-over-midgnight"
									data-setting-key="auto_night_mode_time_range_goes_over_midnight">
								<label class="form-check-label"
									for="auto-night-mode-time-range-goes-over-midgnight">
									{{ $__t('Time range goes over midnight') }}
								</label>
							</div>
						</div>
						<div class="dropdown-divider"></div>
						<div class="dropdown-item">
							<div class="form-check">
								<input class="form-check-input user-setting-control"
									type="checkbox"
									id="keep_screen_on"
									data-setting-key="keep_screen_on">
								<label class="form-check-label"
									for="keep_screen_on">
									{{ $__t('Keep screen on') }}
								</label>
							</div>
						</div>
						<div class="dropdown-item">
							<div class="form-check">
								<input class="form-check-input user-setting-control"
									type="checkbox"
									id="keep_screen_on_when_fullscreen_card"
									data-setting-key="keep_screen_on_when_fullscreen_card">
								<label class="form-check-label"
									for="keep_screen_on_when_fullscreen_card">
									{{ $__t('Keep screen on while displaying a "fullscreen-card"') }}
								</label>
							</div>
						</div>
					</div>
				</li>
				@endif

				<li class="nav-item dropdown d-none">
					<a class="nav-link dropdown-toggle discrete-link"
						href="#"
						data-toggle="dropdown"><i class="fa-solid fa-wrench"></i> <span class="d-inline d-lg-none">{{ $__t('Settings') }}</span></a>

					<div class="dropdown-menu dropdown-menu-right">
						<a class="dropdown-item discrete-link"
							href="{{ $U('/stocksettings') }}"><i class="fa-solid fa-box"></i>&nbsp;{{ $__t('Stock settings') }}</a>
						@if(GROCY_FEATURE_FLAG_SHOPPINGLIST)
						<a class="dropdown-item discrete-link permission-SHOPPINGLIST"
							href="{{ $U('/shoppinglistsettings') }}"><i class="fa-solid fa-shopping-cart"></i>&nbsp;{{ $__t('Shopping list settings') }}</a>
						@endif
						@if(GROCY_FEATURE_FLAG_RECIPES)
						<a class="dropdown-item discrete-link permission-RECIPES"
							href="{{ $U('/recipessettings') }}"><i class="fa-solid fa-pizza-slice"></i>&nbsp;{{ $__t('Recipes settings') }}</a>
						@endif
						@if(GROCY_FEATURE_FLAG_CHORES)
						<a class="dropdown-item discrete-link permission-CHORES"
							href="{{ $U('/choressettings') }}"><i class="fa-solid fa-home"></i>&nbsp;{{ $__t('Chores settings') }}</a>
						@endif
						@if(GROCY_FEATURE_FLAG_TASKS)
						<a class="dropdown-item discrete-link permission-TASKS"
							href="{{ $U('/taskssettings') }}"><i class="fa-solid fa-tasks"></i>&nbsp;{{ $__t('Tasks settings') }}</a>
						@endif
						@if(GROCY_FEATURE_FLAG_BATTERIES)
						<a class="dropdown-item discrete-link permission-BATTERIES"
							href="{{ $U('/batteriessettings') }}"><i class="fa-solid fa-battery-half"></i>&nbsp;{{ $__t('Batteries settings') }}</a>
						@endif
						<div class="dropdown-divider"></div>
						<a data-href="{{ $U('/usersettings') }}"
							class="dropdown-item discrete-link link-return">
							<i class="fa-solid fa-user-cog"></i> {{ $__t('User settings') }}
						</a>
						<a class="dropdown-item discrete-link permission-USERS_READ"
							href="{{ $U('/users') }}"><i class="fa-solid fa-users"></i>&nbsp;{{ $__t('Manage users') }}</a>
						<div class="dropdown-divider"></div>
						@if(!GROCY_DISABLE_AUTH)
						<a class="dropdown-item discrete-link"
							href="{{ $U('/manageapikeys') }}"><i class="fa-solid fa-handshake"></i>&nbsp;{{ $__t('Manage API keys') }}</a>
						@endif
						<a class="dropdown-item discrete-link"
							target="_blank"
							href="{{ $U('/api') }}"><i class="fa-solid fa-book"></i>&nbsp;{{ $__t('REST API browser') }}</a>
						<a class="dropdown-item discrete-link"
							href="{{ $U('/barcodescannertesting') }}"><i class="fa-solid fa-barcode"></i>&nbsp;{{ $__t('Barcode scanner testing') }}</a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item discrete-link show-as-dialog-link"
							data-dialog-type="wider"
							href="{{ $U('/about?embedded') }}"><i class="fa-solid fa-info"></i>&nbsp;Acerca de DAILY</a>
					</div>
				</li>
			</ul>
		</div>@endif
	</nav>
	@if(GROCY_AUTHENTICATED)
	@include('components.dairy_mobile_nav')
	@endif
	@endif

	<div class="@if(GROCY_AUTHENTICATED) content-wrapper @endif pt-0 @if(($viewName ?? '') === 'login') p-0 m-0 @endif">
		<div class="container-fluid @if(GROCY_AUTHENTICATED && !$embedded) dashboard-container px-0 @endif @if($embedded) px-1 @endif @if(($viewName ?? '') === 'login') p-0 @endif">
			<div class="row mb-0 mx-0">
				<div id="page-content"
					class="col-12 px-0 content-text fa-width-auto" style="min-width: 0;">
					@if(GROCY_AUTHENTICATED && !$embedded)
					@include('components.demo_case_strip')
					@endif
					@yield('content')
					@if(GROCY_AUTHENTICATED && !$embedded)
					@include('components.dairy_related')
					@endif
					{{-- Modo noche: adapta los colores fijos antes del primer cuadro (aún bloqueado por rel=expect) --}}
					<script>window.DailyTheme && DailyTheme.boot();</script>
					<span id="page-content-end" hidden></span>
				</div>
			</div>
		</div>
	</div>

	<!-- GLOBAL ENTERPRISE COMMAND PALETTE MODAL (Ctrl + K) -->
	<div class="modal fade" id="commandPaletteModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content dairy-command-modal">
				<div class="dairy-command-header">
					<i class="fa-solid fa-magnifying-glass dairy-command-search-icon"></i>
					<input type="text" id="commandPaletteInput" class="dairy-command-input" placeholder="Escribe un comando o busca módulos, acciones, productos..." autocomplete="off">
					<kbd class="dairy-command-kbd">ESC</kbd>
				</div>
				<div class="dairy-command-body" id="commandPaletteResults">
					<!-- Módulos Principales -->
					<div class="dairy-command-group-title">MÓDULOS DE GESTIÓN EMPRESARIAL</div>
					<a href="{{ $U('/kpis') }}" class="dairy-command-item" data-tags="panel ejecutivo kpis tablero rentabilidad ventas merma sap financiero">
						<div class="dairy-command-item-icon" style="background: var(--dairy-primary-subtle); color: var(--dairy-primary);"><i class="fa-solid fa-chart-line"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Panel Ejecutivo & KPIs</div>
							<div class="dairy-command-item-sub">Métricas de rentabilidad, margen bruto, impacto de merma y forecast</div>
						</div>
						<span class="dairy-command-badge">Finanzas</span>
					</a>
					<a href="{{ $U('/waste') }}" class="dairy-command-item" data-tags="mermas pérdidas desperdicio akcal 551 causas registro auditoria fmcg">
						<div class="dairy-command-item-icon" style="background: var(--dairy-danger-bg); color: var(--dairy-danger-dark);"><i class="fa-solid fa-trash-can"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Control y Prevención de Mermas</div>
							<div class="dairy-command-item-sub">Centro de control, matriz de causales, investigaciones y recuperaciones</div>
						</div>
						<span class="dairy-command-badge" style="background: var(--dairy-danger-bg); color: var(--dairy-danger-dark); border-color: var(--dairy-danger-border);">Núcleo</span>
					</a>
					<a href="{{ $U('/workforce') }}" class="dairy-command-item" data-tags="workforce piso tienda tareas operativas checklists averia express móvil camara">
						<div class="dairy-command-item-icon" style="background: var(--dairy-success-bg); color: var(--dairy-success-dark);"><i class="fa-solid fa-mobile-screen-button"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Workforce Operations (Piso de Tienda)</div>
							<div class="dairy-command-item-sub">Checklists preventivos, control de frío y reporte exprés con cámara</div>
						</div>
						<span class="dairy-command-badge">Móvil</span>
					</a>
					<a href="{{ $U('/hr') }}" class="dairy-command-item" data-tags="recursos humanos hr hcm personal colaboradores contratacion nomina horarios asistencia">
						<div class="dairy-command-item-icon" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;"><i class="fa-solid fa-users-gear"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Recursos Humanos (HCM)</div>
							<div class="dairy-command-item-sub">Colaboradores, contratación, turnos, nómina, productividad y capacitación</div>
						</div>
						<span class="dairy-command-badge">HCM</span>
					</a>
					<a href="{{ $U('/pos') }}" class="dairy-command-item" data-tags="punto de venta pos caja cobrar ticket fefo ventas venta rapida">
						<div class="dairy-command-item-icon" style="background: var(--dairy-success-bg); color: var(--dairy-success-dark);"><i class="fa-solid fa-cash-register"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Punto de Venta (POS)</div>
							<div class="dairy-command-item-sub">Emisión rápida de tickets, deducción FEFO y cómputo de CMV</div>
						</div>
						<span class="dairy-command-badge">Caja</span>
					</a>
					<a href="{{ $U('/stockoverview') }}" class="dairy-command-item" data-tags="stock inventario lotes ubicaciones transferencias articulos productos">
						<div class="dairy-command-item-icon" style="background: var(--dairy-border-subtle); color: var(--dairy-text-secondary);"><i class="fa-solid fa-boxes-stacked"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Inventario & Stock General</div>
							<div class="dairy-command-item-sub">Existencias por ubicación, valorización contable y rotación</div>
						</div>
						<span class="dairy-command-badge">Stock</span>
					</a>
					<a href="{{ $U('/warehouse') }}" class="dairy-command-item" data-tags="almacen bodega wms recepcion muelles putaway racks picking devoluciones logistica">
						<div class="dairy-command-item-icon" style="background: rgba(14, 165, 233, 0.12); color: #0284c7;"><i class="fa-solid fa-warehouse"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Almacén y Bodega (WMS)</div>
							<div class="dairy-command-item-sub">Recepción, muelles, inspección, putaway en racks, preparación y devoluciones</div>
						</div>
						<span class="dairy-command-badge">WMS</span>
					</a>
					<a href="{{ $U('/transfers') }}" class="dairy-command-item" data-tags="traslado transferencia sucursal envio transito recepcion">
						<div class="dairy-command-item-icon" style="background: var(--dairy-border-subtle); color: var(--dairy-text-secondary);"><i class="fa-solid fa-right-left"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Traslados entre sucursales</div>
							<div class="dairy-command-item-sub">Envío, mercancía en tránsito y recepción con diferencias</div>
						</div>
					</a>
					<a href="{{ $U('/mrp') }}" class="dairy-command-item" data-tags="mrp compras planeacion abastecimiento orden sugerida stock seguridad">
						<div class="dairy-command-item-icon" style="background: var(--dairy-warning-bg); color: var(--dairy-warning-dark);"><i class="fa-solid fa-boxes-packing"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Planificación MRP & Compras</div>
							<div class="dairy-command-item-sub">Cálculo de pedidos sugeridos según lead time y consumo ponderado</div>
						</div>
						<span class="dairy-command-badge">MRP</span>
					</a>
					<a href="{{ $U('/suppliers') }}" class="dairy-command-item" data-tags="proveedores procurement compras evaluacion otif scorecard reclamos">
						<div class="dairy-command-item-icon" style="background: var(--dairy-purple-bg); color: var(--dairy-purple-dark);"><i class="fa-solid fa-truck-ramp-box"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Proveedores & Procurement</div>
							<div class="dairy-command-item-sub">Scorecards de cumplimiento OTIF y reclamos por merma atribuible</div>
						</div>
						<span class="dairy-command-badge">Compras</span>
					</a>

					<!-- Acciones Rápidas -->
					<div class="dairy-command-group-title mt-2">ACCIONES OPERATIVAS DIRECTAS</div>
					<a href="{{ $U('/waste') }}#tab-registro" class="dairy-command-item" data-tags="nueva merma registrar descarte baja averia 551">
						<div class="dairy-command-item-icon" style="background: var(--dairy-danger-bg); color: var(--dairy-danger-dark);"><i class="fa-solid fa-plus-circle"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Registrar Nueva Merma (Mov. 551)</div>
							<div class="dairy-command-item-sub">Ingresar caso con causal, lote y justificación contable</div>
						</div>
						<span class="dairy-command-badge">Acción</span>
					</a>
					<a href="{{ $U('/pos') }}" class="dairy-command-item" data-tags="cobrar venta nueva vender caja ticket">
						<div class="dairy-command-item-icon" style="background: var(--dairy-success-bg); color: var(--dairy-success-dark);"><i class="fa-solid fa-cart-shopping"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Nueva Venta en POS</div>
							<div class="dairy-command-item-sub">Abrir terminal de caja y escanear productos</div>
						</div>
						<span class="dairy-command-badge">Acción</span>
					</a>
					<a href="{{ $U('/inventory') }}" class="dairy-command-item" data-tags="conteo fisico inventario ciclico conciliacion diferencias">
						<div class="dairy-command-item-icon" style="background: var(--dairy-warning-bg); color: var(--dairy-warning-dark);"><i class="fa-solid fa-clipboard-check"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Iniciar Conteo Físico Cíclico</div>
							<div class="dairy-command-item-sub">Auditar existencias y conciliar diferencias de inventario</div>
						</div>
						<span class="dairy-command-badge">Acción</span>
					</a>
				</div>
				<div class="dairy-command-footer">
					<span><kbd class="shadow-xs mr-1">↑</kbd><kbd class="shadow-xs mr-1">↓</kbd> Navegar</span>
					<span><kbd class="shadow-xs mr-1">Enter</kbd> Seleccionar</span>
					<span><kbd class="shadow-xs mr-1">ESC</kbd> Cerrar</span>
				</div>
			</div>
		</div>
	</div>


	<script src="{{ $U('/packages/jquery/dist/jquery.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/bootstrap/dist/js/bootstrap.bundle.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/bootbox/dist/bootbox.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/jquery-serializejson/jquery.serializejson.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/moment/min/moment.min.js?v=', true) }}{{ $version }}"></script>
	@if(!empty($__t('moment_locale') && $__t('moment_locale') != 'x'))<script src="{{ $U('/packages', true) }}/moment/locale/{{ $__t('moment_locale') }}.js?v={{ $version }}"></script>@endif
	<script src="{{ $U('/packages/toastr/build/toastr.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/sprintf-js/dist/sprintf.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/gettext-translator/dist/translator.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/nosleep.js/dist/NoSleep.min.js?v=', true) }}{{ $version }}"></script>

	@if(in_array('bootstrap-combobox', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/@danielfarrell/bootstrap-combobox/js/bootstrap-combobox.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('datatables', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/datatables.net/js/jquery.dataTables.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-bs4/js/dataTables.bootstrap4.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-colreorder/js/dataTables.colReorder.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-colreorder-bs4/js/colReorder.bootstrap4.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-rowgroup/js/dataTables.rowGroup.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-rowgroup-bs4/js/rowGroup.bootstrap4.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-select/js/dataTables.select.min.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/datatables.net-select-bs4/js/select.bootstrap4.min.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('tempusdominus', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/tempusdominus-bootstrap-4/build/js/tempusdominus-bootstrap-4.min.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('summernote', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/summernote/dist/summernote-bs4.min.js?v=', true) }}{{ $version }}"></script>
	@if(!empty($__t('summernote_locale') && $__t('summernote_locale') != 'x'))<script src="{{ $U('/packages', true) }}/summernote/dist/lang/summernote-{{ $__t('summernote_locale') }}.js?v={{ $version }}"></script>@endif
	@endif
	@if(in_array('bootstrap-select', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/bootstrap-select/dist/js/bootstrap-select.min.js?v=', true) }}{{ $version }}"></script>
	@if(!empty($__t('bootstrap-select_locale') && $__t('bootstrap-select_locale') != 'x'))<script src="{{ $U('/packages', true) }}/bootstrap-select/dist/js/i18n/defaults-{{ $__t('bootstrap-select_locale') }}.js?v={{ $version }}"></script>@endif
	@endif
	@if(in_array('fullcalendar', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/fullcalendar/dist/fullcalendar.min.js?v=', true) }}{{ $version }}"></script>
	@if(!empty($__t('fullcalendar_locale') && $__t('fullcalendar_locale') != 'x'))<script src="{{ $U('/packages', true) }}/fullcalendar/dist/locale/{{ $__t('fullcalendar_locale') }}.js?v={{ $version }}"></script>@endif
	@endif
	@if(in_array('daterangepicker', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/daterangepicker/daterangepicker.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('zxing', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/@zxing/library/umd/index.min.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('bwipjs', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/bwip-js/dist/bwip-js-min.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('chartjs', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/packages/chart.js/dist/Chart.min.js?v=', true) }}{{ $version }}"></script>
	<script>window.DailyI18n && DailyI18n.hookCharts();</script>
	<script src="{{ $U('/packages/chartjs-plugin-colorschemes/dist/chartjs-plugin-colorschemes.min.js?v=', true) }}{{ $version}}"></script>
	<script src="{{ $U('/packages/chartjs-plugin-doughnutlabel/dist/chartjs-plugin-doughnutlabel.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/chartjs-plugin-piechart-outlabels/dist/chartjs-plugin-piechart-outlabels.min.js?v=', true) }}{{ $version}}"></script>
	<script src="{{ $U('/packages/chartjs-plugin-trendline/dist/chartjs-plugin-trendline.min.js?v=', true) }}{{ $version}}"></script>
	@endif

	<script src="{{ $U('/js/extensions.js?v=', true) }}{{ daily_asset_version('/js/extensions.js') }}"></script>
	<script src="{{ $U('/js/grocy_menu_layout.js?v=', true) }}{{ daily_asset_version('/js/grocy_menu_layout.js') }}"></script>
	<script src="{{ $U('/js/grocy.js?v=', true) }}{{ daily_asset_version('/js/grocy.js') }}"></script>
	<script src="{{ $U('/js/daily_process_flow.js?v=', true) }}{{ daily_asset_version('/js/daily_process_flow.js') }}"></script>
	@if(GROCY_AUTHENTICATED && !$embedded)
	<script src="{{ $U('/js/dairy_mobile.js?v=', true) }}{{ daily_asset_version('/js/dairy_mobile.js') }}"></script>
	<script src="{{ $U('/js/daily_mobile.js?v=', true) }}{{ daily_asset_version('/js/daily_mobile.js') }}"></script>
	<script src="{{ $U('/js/dairy_offline.js?v=', true) }}{{ daily_asset_version('/js/dairy_offline.js') }}"></script>
	<script src="{{ $U('/js/dairy_ui.js?v=', true) }}{{ daily_asset_version('/js/dairy_ui.js') }}"></script>
	<script src="{{ $U('/js/dairy_scan.js?v=', true) }}{{ daily_asset_version('/js/dairy_scan.js') }}"></script>
	@endif
	<script src="{{ $U('/js/grocy_dbchangedhandling.js?v=', true) }}{{ daily_asset_version('/js/grocy_dbchangedhandling.js') }}"></script>
	<script src="{{ $U('/js/grocy_wakelockhandling.js?v=', true) }}{{ daily_asset_version('/js/grocy_wakelockhandling.js') }}"></script>
	<script src="{{ $U('/js/grocy_nightmode.js?v=', true) }}{{ daily_asset_version('/js/grocy_nightmode.js') }}"></script>
	<script src="{{ $U('/js/grocy_clock.js?v=', true) }}{{ daily_asset_version('/js/grocy_clock.js') }}"></script>

	@if(in_array('datatables', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/js/grocy_datatables.js?v=', true) }}{{ daily_asset_version('/js/grocy_datatables.js') }}"></script>
	@endif
	@if(in_array('summernote', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/js/grocy_summernote.js?v=', true) }}{{ daily_asset_version('/js/grocy_summernote.js') }}"></script>
	@endif

	@if(in_array('chartjs', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/js/dairy_charts.js?v=', true) }}{{ daily_asset_version('/js/dairy_charts.js') }}"></script>
	@endif

	<script src="{{ $U('/js/daily_crystal.js?v=', true) }}{{ daily_asset_version('/js/daily_crystal.js') }}"></script>
	@if (GROCY_AUTHENTICATED)
	<script src="{{ $U('/js/daily_subscription.js?v=', true) }}{{ daily_asset_version('/js/daily_subscription.js') }}"></script>
	@endif
	<script type="application/json" id="dailyFilterRoutes" data-base="{{ $U('/') }}">{!! json_encode(\Grocy\Helpers\DailyFilters::ROUTES) !!}</script>
	<script src="{{ $U('/js/daily_filters.js?v=', true) }}{{ daily_asset_version('/js/daily_filters.js') }}"></script>
	@stack('pageScripts')
	@stack('componentScripts')
	@if($viewJsExists ?? true)
	<script src="{{ $U('/viewjs/' . $viewName . '.js?v=', true) }}{{ $version }}-{{ @filemtime(dirname(GROCY_DATAPATH) . '/public/viewjs/' . $viewName . '.js') }}"></script>
	@endif

	<!-- Anticipa la página al pasar por un enlace del menú; quedan fuera las pantallas operativas, que siempre se piden al servidor -->
	<script type="speculationrules">
	{"prefetch":[{"where":{"and":[{"selector_matches":"a.dnav-link"},{"not":{"href_matches":"/(pos|scan|purchase|consume|inventory|transfer|transfers|m)(/*)?"}}]},"eagerness":"moderate"}]}
	</script>

	<!-- Command Palette Script -->
	<script>
	document.addEventListener('keydown', function(e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
			e.preventDefault();
			$('#commandPaletteModal').modal('toggle');
		}
	});

	// Contexto compartido HOY ↔ Panel ejecutivo: la fecha de referencia viaja entre ambas páginas,
	// y desde HOY el Panel abre en el mismo día para mostrar las mismas cifras
	$(document).ready(function() {
		var cur = new URLSearchParams(window.location.search);
		var path = window.location.pathname.replace(/\/+$/, '');
		var ctxDate = cur.get('date');
		var onToday = /\/today$/.test(path);
		var onPanel = /\/kpis$/.test(path);
		// El selector de sucursal del encabezado conserva los demás filtros de la página
		$('.dairy-hbtn').next('.dropdown-menu').find('a.dropdown-item[href^="?branch_id="]').each(function() {
			var p = new URLSearchParams(window.location.search);
			p.set('branch_id', new URLSearchParams($(this).attr('href').slice(1)).get('branch_id') || '');
			$(this).attr('href', '?' + p.toString());
		});
		if (!onToday && !onPanel) return;
		$('a[href]').each(function() {
			var href = $(this).attr('href');
			if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
			var u = new URL(href, window.location.origin);
			if (u.origin !== window.location.origin) return;
			var toToday = /\/today\/?$/.test(u.pathname), toPanel = /\/kpis\/?$/.test(u.pathname);
			if (!(toToday && onPanel) && !(toPanel && onToday)) return;
			if (ctxDate && !u.searchParams.has('date')) u.searchParams.set('date', ctxDate);
			if (toPanel && !u.searchParams.has('period')) u.searchParams.set('period', 'day');
			if (cur.get('branch_id') && !u.searchParams.has('branch_id')) u.searchParams.set('branch_id', cur.get('branch_id'));
			$(this).attr('href', u.pathname + u.search + u.hash);
		});
	});

	// Conserva la sucursal elegida en el selector del encabezado al navegar por el menú lateral
	$(document).ready(function() {
		var ctxBranch = new URLSearchParams(window.location.search).get('branch_id');
		if (!ctxBranch) return;
		$('#sidebarResponsive a[href]').each(function() {
			var href = $(this).attr('href');
			if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('branch_id=') !== -1) return;
			var u = new URL(href, window.location.origin);
			if (u.origin !== window.location.origin) return;
			u.searchParams.set('branch_id', ctxBranch);
			$(this).attr('href', u.pathname + u.search + u.hash);
		});
	});

	// El contador del header dependía de /api/ai/alerts, eliminado junto con el asesor IA: la petición fallaba en
	// cada página. El badge queda oculto hasta conectarlo a una fuente de alertas vigente.

	$(document).ready(function() {
		var modal = $('#commandPaletteModal');
		var input = $('#commandPaletteInput');
		var selectedIndex = -1;

		modal.on('shown.bs.modal', function() {
			input.val('').focus();
			$('.dairy-command-item').show().removeClass('is-selected');
			$('.dairy-command-group-title').show();
			selectedIndex = -1;
			if (dataBox) { dataBox.empty(); }
		});

		input.on('input', function() {
			var q = $(this).val().toLowerCase().trim();
			var visibleCount = 0;
			selectedIndex = -1;

			$('.dairy-command-item').not('#commandPaletteData .dairy-command-item').each(function() {
				var item = $(this);
				var tags = (item.data('tags') || '').toLowerCase();
				var text = item.text().toLowerCase();
				if (!q || tags.indexOf(q) !== -1 || text.indexOf(q) !== -1) {
					item.show();
					visibleCount++;
				} else {
					item.hide().removeClass('is-selected');
				}
			});

			$('.dairy-command-body').each(function() {
				var hasVisible = $(this).find('.dairy-command-item:visible').length > 0;
				$(this).find('.dairy-command-group-title').toggle(hasVisible);
			});
			searchData(q);
		});

		// Datos reales: productos, lotes, casos de merma y proveedores (solo los que el usuario puede abrir)
		var dataBox = $('<div id="commandPaletteData"></div>').prependTo('#commandPaletteResults');
		var searchTimer = null, searchSeq = 0;
		var TYPES = { product: ['fa-box', 'Producto'], lot: ['fa-barcode', 'Lote'], case: ['fa-triangle-exclamation', 'Caso'], supplier: ['fa-truck', 'Proveedor'] };
		function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
		function searchData(q) {
			clearTimeout(searchTimer);
			if (q.length < 2) { dataBox.empty(); return; }
			var seq = ++searchSeq;
			dataBox.html('<div class="dairy-command-group-title">RESULTADOS</div><div class="dairy-command-loading"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Buscando…</div>');
			searchTimer = setTimeout(function() {
				$.getJSON(Grocy.BaseUrl + '/api/dairy/search?q=' + encodeURIComponent(q)).done(function(rows) {
					if (seq !== searchSeq) return;
					if (!rows.length) { dataBox.html('<div class="dairy-command-group-title">RESULTADOS</div><div class="dairy-command-loading">Sin coincidencias en productos, lotes, casos ni proveedores.</div>'); return; }
					dataBox.html('<div class="dairy-command-group-title">RESULTADOS</div>' + rows.map(function(r) {
						var t = TYPES[r.type] || ['fa-circle', ''];
						return '<a href="' + Grocy.BaseUrl + r.href.replace(/^\//, '') + '" class="dairy-command-item" data-tags="">'
							+ '<div class="dairy-command-item-icon"><i class="fa-solid ' + t[0] + '"></i></div>'
							+ '<div class="dairy-command-item-content"><div class="dairy-command-item-title">' + esc(r.title) + '</div><div class="dairy-command-item-sub">' + esc(r.sub) + '</div></div>'
							+ '<span class="dairy-command-badge">' + t[1] + '</span></a>';
					}).join(''));
				}).fail(function() {
					if (seq === searchSeq) dataBox.html('<div class="dairy-command-loading text-danger">No se pudo buscar. Intente de nuevo.</div>');
				});
			}, 220);
		}

		input.on('keydown', function(e) {
			var visibleItems = $('.dairy-command-item:visible');
			if (!visibleItems.length) return;

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				selectedIndex = (selectedIndex + 1) % visibleItems.length;
				visibleItems.removeClass('is-selected');
				$(visibleItems[selectedIndex]).addClass('is-selected')[0].scrollIntoView({ block: 'nearest' });
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				selectedIndex = (selectedIndex - 1 + visibleItems.length) % visibleItems.length;
				visibleItems.removeClass('is-selected');
				$(visibleItems[selectedIndex]).addClass('is-selected')[0].scrollIntoView({ block: 'nearest' });
			} else if (e.key === 'Enter') {
				e.preventDefault();
				if (selectedIndex >= 0 && selectedIndex < visibleItems.length) {
					visibleItems[selectedIndex].click();
				} else if (visibleItems.length > 0) {
					visibleItems[0].click();
				}
			}
		});
	});
	</script>

	@if(file_exists(GROCY_DATAPATH . '/custom_js.html'))
	@php include GROCY_DATAPATH . '/custom_js.html' @endphp
	@endif

	<script>
	window.setDailyTheme = function(theme, persist) {
		if (theme !== 'red' && theme !== 'yellow') {
			theme = 'green';
		}
		if (theme === 'red' || theme === 'yellow') {
			document.documentElement.setAttribute('data-theme', theme);
		} else {
			document.documentElement.removeAttribute('data-theme');
		}
		try {
			localStorage.setItem('daily_theme', theme);
		} catch (e) {}

		var colorMap = { green: '#00C83B', red: '#DC2626', yellow: '#FFB800' };
		var metaColor = document.getElementById('metaThemeColor');
		if (metaColor) {
			metaColor.setAttribute('content', colorMap[theme] || '#00C83B');
		}

		if (window.DailyUpdatePalette) {
			window.DailyUpdatePalette();
		}

		$('#dailyQuickThemePicker label').removeClass('active');
		$('#daily-btn-' + theme).addClass('active').find('input').prop('checked', true);
		$('#daily_theme').val(theme);

		if (persist !== false && window.Grocy && Grocy.FrontendHelpers && Grocy.FrontendHelpers.SaveUserSetting) {
			Grocy.FrontendHelpers.SaveUserSetting('daily_theme', theme);
		}
	};

	$(document).ready(function() {
		var curTheme = localStorage.getItem('daily_theme') || (window.Grocy && Grocy.UserSettings && Grocy.UserSettings.daily_theme) || 'green';
		window.setDailyTheme(curTheme, false);

		$(document).on('change', 'input[name="daily_quick_theme_opt"]', function() {
			window.setDailyTheme($(this).val(), true);
		});
		$(document).on('change', '#daily_theme', function() {
			window.setDailyTheme($(this).val(), true);
		});
	});
	</script>

	@if(\Grocy\Helpers\DairyAccess::GetSimulatedRole() === 'demo')
	<script>
	// Demostración controlada: la paleta de comandos solo ofrece las páginas del guion
	(function() {
		var allowed = {!! json_encode(\Grocy\Helpers\DairyAccess::DEMO_PAGES) !!};
		document.querySelectorAll('a.dairy-command-item').forEach(function(a) {
			var path = new URL(a.href, location.href).pathname.replace(/\/+$/, '') || '/';
			if (allowed.indexOf(path) === -1) a.remove();
		});
		document.querySelectorAll('.dairy-command-group-title').forEach(function(t) {
			var next = t.nextElementSibling;
			if (!next || !next.classList.contains('dairy-command-item')) t.remove();
		});
	})();
	</script>
	@endif

</body>

</html>
