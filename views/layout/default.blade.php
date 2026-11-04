@php global $GROCY_REQUIRED_FRONTEND_PACKAGES; @endphp

<!DOCTYPE html>
<html lang="{{ GROCY_LOCALE }}"
	dir="{{ $dir }}">

<head>
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

	<link href="{{ $U('/css/grocy_menu_layout.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/css/grocy.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/css/dairy_enterprise_theme.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/css/dairy_glass.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/css/daily_theme.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/css/daily_design_system.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	<link href="{{ $U('/css/daily_process_flow.css?v=', true) }}{{ $version }}"
		rel="stylesheet">


	@if(boolval($userSettings['night_mode_enabled_internal']))
	<link id="night-mode-stylesheet"
		href="{{ $U('/css/grocy_night_mode.css?v=', true) }}{{ $version }}"
		rel="stylesheet">
	@endif

	@stack('pageStyles')

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
</head>

<body class="fixed-nav @if(boolval($userSettings['night_mode_enabled_internal'])) night-mode @endif @if($embedded) embedded @endif">
	<script>
		// Estado del sidebar antes del primer pintado (sin saltos de layout ni parpadeo del logo)
		try { var dnW = window.innerWidth, dnS = localStorage.getItem('dairy_sidebar_state') || localStorage.getItem('sidebar_state'); if (dnW >= 768 && (dnW < 1024 ? dnS !== 'expanded' : dnS === 'collapsed')) { document.body.classList.add('sidenav-toggled'); } } catch (e) { }
		document.body.classList.add('dnav-preload');
	</script>
	@if(!$embedded)
	<nav id="mainNav"
		class="navbar navbar-expand-md navbar-light fixed-top">
		<div class="header-brand-zone">
			<a class="navbar-brand py-0 mr-0 d-flex align-items-center"
				href="{{ $U('/') }}"
				aria-label="DAILY">
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
				<li class="nav-item dropdown mr-2 d-none d-md-flex align-items-center">
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

				<!-- Selector de Rol Simulado (Demostración) -->
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
					'empleado' => 'fa-user text-success'
				];
				@endphp
				<li class="nav-item dropdown mr-2 d-none @if($simRole !== 'demo') d-sm-flex @endif align-items-center">
					<button class="dairy-hbtn dropdown-toggle" type="button" data-toggle="dropdown" aria-label="Rol simulado" title="Perfil funcional simulado: cambia los permisos visibles sin iniciar sesión">
						<i class="fa-solid {{ $simRoleIcons[$simRole] ?? 'fa-id-badge' }}"></i>
						<span class="d-none d-lg-inline text-muted mr-1" style="opacity: 0.75;">Rol:</span>
						<strong>{{ $simRoleLabels[$simRole] ?? 'Manager' }}</strong>
					</button>
					<div class="dropdown-menu dropdown-menu-right" style="min-width: 250px;">
						<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: var(--dairy-fs-xs);">
							<i class="fa-solid fa-masks-theater mr-1"></i>Perfil simulado
						</div>
						<a class="dropdown-item @if($simRole === 'manager') active font-weight-bold @endif" href="{{ $U('/daily/set-role?role=manager') }}">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-user-tie mr-2 text-primary" style="width: 16px;"></i>
								<div>
									<div>Manager / Administrador</div>
									<small class="text-muted d-block font-weight-normal" style="font-size: 10.5px;">Toda la red, KPIs estratégicos y aprobaciones</small>
								</div>
							</div>
						</a>
						<a class="dropdown-item @if($simRole === 'supervisor') active font-weight-bold @endif" href="{{ $U('/daily/set-role?role=supervisor') }}">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-user-shield mr-2 text-warning" style="width: 16px;"></i>
								<div>
									<div>Supervisor de Tienda</div>
									<small class="text-muted d-block font-weight-normal" style="font-size: 10.5px;">Supervisión de piso, equipo, incidencias y alertas</small>
								</div>
							</div>
						</a>
						<a class="dropdown-item @if($simRole === 'empleado') active font-weight-bold @endif" href="{{ $U('/daily/set-role?role=empleado') }}">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-user mr-2 text-success" style="width: 16px;"></i>
								<div>
									<div>Empleado / Asociado</div>
									<small class="text-muted d-block font-weight-normal" style="font-size: 10.5px;">Consulta de turnos, fichaje y solicitudes</small>
								</div>
							</div>
						</a>
						<div class="dropdown-divider"></div>
						<div class="px-3 py-1 text-muted" style="font-size: 10px; line-height: 1.35;">
							<i class="fa-solid fa-circle-info mr-1 text-info"></i>Roles simulados sin autenticación real: solo cambian los permisos visibles.
						</div>
					</div>
				</li>

				<!-- Notificaciones activas -->
				<li class="nav-item mr-1 position-relative">
					<button class="dairy-hicon" type="button" id="headerAlertsButton" aria-label="Alertas" title="Alertas de merma y cadena de frío" onclick="var t = $('#tab-alertas-link, [href=\'#tab-prevencion\']').first(); if (t.length) { t.click(); } else { window.location.href = '{{ $U(\Grocy\Helpers\DairyAccess::CanOpen('/today') ? '/today' : '/ai-advisor') }}'; }">
						<i class="fa-solid fa-bell"></i>
						<span id="headerAlertsBadge" class="dairy-hicon-badge d-none"></span>
					</button>
				</li>

				<!-- Perfil de Usuario -->
				@php
				$hdrRole = '';
				try { if (defined('GROCY_USER_ID')) { $hdrRole = (string) \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ' . intval(GROCY_USER_ID) . ' LIMIT 1')->fetchColumn(); } } catch (\Throwable $e) { }
				@endphp
				<li class="nav-item dropdown mr-1">
					<a class="nav-link dropdown-toggle discrete-link py-0 d-flex align-items-center dairy-hprofile" href="#" data-toggle="dropdown">
						<div class="dairy-havatar mr-2">
							{{ defined('GROCY_USER_USERNAME') ? mb_strtoupper(mb_substr(GROCY_USER_USERNAME, 0, 2)) : '' }}
						</div>
						<div class="d-none d-xl-block text-left mr-1">
							<div class="dairy-hprofile-name">{{ defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : '' }}</div>
							<div class="dairy-hprofile-role">{{ $simRole === 'demo' ? 'Recorrido guiado' : (($hdrRole ?: ($simRoleLabels[$simRole] ?? 'Manager')) . ' · Simulado') }}</div>
						</div>
					</a>
					<div class="dropdown-menu dropdown-menu-right">
						<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: var(--dairy-fs-xs);">Sesión Activa</div>
						@if($simRole !== 'demo')
						<a class="dropdown-item discrete-link" href="{{ $U('/usersettings') }}"><i class="fa-solid fa-user-gear mr-2 text-primary"></i>Perfil de Usuario</a>
						<div class="dropdown-divider"></div>
						<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: var(--dairy-text-muted);">Rol Simulado</div>
						<a class="dropdown-item @if($simRole === 'manager') font-weight-bold text-primary @endif" href="{{ $U('/daily/set-role?role=manager') }}"><i class="fa-solid fa-user-tie mr-2"></i>Manager / Admin</a>
						<a class="dropdown-item @if($simRole === 'supervisor') font-weight-bold text-warning @endif" href="{{ $U('/daily/set-role?role=supervisor') }}"><i class="fa-solid fa-user-shield mr-2"></i>Supervisor</a>
						<a class="dropdown-item @if($simRole === 'empleado') font-weight-bold text-success @endif" href="{{ $U('/daily/set-role?role=empleado') }}"><i class="fa-solid fa-user mr-2"></i>Empleado</a>
						<div class="dropdown-divider"></div>
						@endif
						<div class="px-3 py-1">
							<div class="small font-weight-bold text-muted mb-1">Tema de Identidad Visual</div>
							<div class="btn-group btn-group-toggle btn-group-sm w-100" data-toggle="buttons" id="dailyQuickThemePicker">
								<label class="btn btn-outline-secondary py-1 px-2" id="daily-btn-green" title="DAILY Verde (Predeterminado)" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="green"> <i class="fa-solid fa-circle text-success mr-1"></i>Verde
								</label>
								<label class="btn btn-outline-secondary py-1 px-2" id="daily-btn-red" title="DAILY Rojo" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="red"> <i class="fa-solid fa-circle text-danger mr-1"></i>Rojo
								</label>
								<label class="btn btn-outline-secondary py-1 px-2" id="daily-btn-yellow" title="DAILY Amarillo" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="yellow"> <i class="fa-solid fa-circle text-warning mr-1"></i>Amarillo
								</label>
							</div>
						</div>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item logout-button discrete-link text-danger" href="{{ $U('/logout') }}"><i class="fa-solid fa-sign-out-alt mr-2"></i>Cerrar Sesión</a>
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

	<div class="@if(GROCY_AUTHENTICATED) content-wrapper @endif pt-0">
		<div class="container-fluid @if(GROCY_AUTHENTICATED && !$embedded) dashboard-container px-0 @endif @if($embedded) px-1 @endif">
			<div class="row mb-0 mx-0">
				<div id="page-content"
					class="col-12 px-0 content-text fa-width-auto" style="min-width: 0;">
					@yield('content')
					@if(GROCY_AUTHENTICATED && !$embedded)
					@include('components.dairy_related')
					@endif
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
					<a href="{{ $U('/pos') }}" class="dairy-command-item" data-tags="punto de venta pos caja cobrar ticket fefo ventas venta rapida">
						<div class="dairy-command-item-icon" style="background: var(--dairy-success-bg); color: var(--dairy-success-dark);"><i class="fa-solid fa-cash-register"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Punto de Venta (POS)</div>
							<div class="dairy-command-item-sub">Emisión rápida de tickets, deducción FEFO y cómputo de CMV</div>
						</div>
						<span class="dairy-command-badge">Caja</span>
					</a>
					<a href="{{ $U('/ai-advisor') }}" class="dairy-command-item" data-tags="arturito ia asesor inteligencia artificial alertas prediccion recomendacion mrp simulador">
						<div class="dairy-command-item-icon" style="background: var(--dairy-purple-bg); color: var(--dairy-purple);"><i class="fa-solid fa-robot"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Arturito IA — Asesor Ejecutivo</div>
							<div class="dairy-command-item-sub">Diagnósticos en tiempo real, alertas de cadena de frío y reducción de pérdidas</div>
						</div>
						<span class="dairy-command-badge" style="background: var(--dairy-purple-bg); color: var(--dairy-purple);">IA Agent</span>
					</a>
					<a href="{{ $U('/stockoverview') }}" class="dairy-command-item" data-tags="stock inventario lotes ubicaciones transferencias articulos productos">
						<div class="dairy-command-item-icon" style="background: var(--dairy-border-subtle); color: var(--dairy-text-secondary);"><i class="fa-solid fa-boxes-stacked"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Inventario & Stock General</div>
							<div class="dairy-command-item-sub">Existencias por ubicación, valorización contable y rotación</div>
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
					<a href="{{ $U('/quality') }}" class="dairy-command-item" data-tags="calidad vencimientos fefo semaforo lote bloqueo caducidad frio qm">
						<div class="dairy-command-item-icon" style="background: var(--dairy-danger-bg); color: var(--dairy-danger-dark);"><i class="fa-solid fa-calendar-xmark"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Calidad & Vencimientos FEFO</div>
							<div class="dairy-command-item-sub">Semáforo de caducidad, auditoría de cadena de frío y bloqueos QM</div>
						</div>
						<span class="dairy-command-badge">QM</span>
					</a>
					<a href="{{ $U('/ai-advisor') }}#tab-simulator" class="dairy-command-item" data-tags="simulador what if proyeccion margen descuentos decisiones">
						<div class="dairy-command-item-icon" style="background: var(--dairy-primary-subtle); color: var(--dairy-primary);"><i class="fa-solid fa-calculator"></i></div>
						<div class="dairy-command-item-content">
							<div class="dairy-command-item-title">Simulador de Decisiones (What-If)</div>
							<div class="dairy-command-item-sub">Modelado de descuentos vs. descarte y optimización de márgenes</div>
						</div>
						<span class="dairy-command-badge">What-If</span>
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
	<script src="{{ $U('/packages/chartjs-plugin-colorschemes/dist/chartjs-plugin-colorschemes.min.js?v=', true) }}{{ $version}}"></script>
	<script src="{{ $U('/packages/chartjs-plugin-doughnutlabel/dist/chartjs-plugin-doughnutlabel.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/packages/chartjs-plugin-piechart-outlabels/dist/chartjs-plugin-piechart-outlabels.min.js?v=', true) }}{{ $version}}"></script>
	<script src="{{ $U('/packages/chartjs-plugin-trendline/dist/chartjs-plugin-trendline.min.js?v=', true) }}{{ $version}}"></script>
	@endif

	<script src="{{ $U('/js/extensions.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/grocy_menu_layout.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/grocy.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/daily_process_flow.js?v=', true) }}{{ $version }}"></script>
	@if(GROCY_AUTHENTICATED && !$embedded)
	<script src="{{ $U('/js/dairy_mobile.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/dairy_offline.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/dairy_ui.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/dairy_scan.js?v=', true) }}{{ $version }}"></script>
	@endif
	<script src="{{ $U('/js/grocy_dbchangedhandling.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/grocy_wakelockhandling.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/grocy_nightmode.js?v=', true) }}{{ $version }}"></script>
	<script src="{{ $U('/js/grocy_clock.js?v=', true) }}{{ $version }}"></script>

	@if(in_array('datatables', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/js/grocy_datatables.js?v=', true) }}{{ $version }}"></script>
	@endif
	@if(in_array('summernote', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/js/grocy_summernote.js?v=', true) }}{{ $version }}"></script>
	@endif

	@if(in_array('chartjs', $GROCY_REQUIRED_FRONTEND_PACKAGES))
	<script src="{{ $U('/js/dairy_charts.js?v=', true) }}{{ $version }}"></script>
	@endif

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

	// Contador real de alertas (merma, vencimiento, frío) en el header
	$(document).ready(function() {
		var params = new URLSearchParams(window.location.search);
		var url = Grocy.BaseUrl + '/api/ai/alerts' + (params.get('branch_id') ? '?branch_id=' + encodeURIComponent(params.get('branch_id')) : '');
		$.getJSON(url, function(alerts) {
			var n = Array.isArray(alerts) ? alerts.filter(function(a) { return a.severity === 'danger'; }).length : 0;
			if (n > 0) {
				$('#headerAlertsBadge').text(n).removeClass('d-none');
				if (window.DairyNav) { DairyNav.setBadge('alerts', n); }
				$('#headerAlertsButton').attr('title', n + ' alertas críticas de merma y cadena de frío');
			}
			if (window.DairyMobile && DairyMobile.notifyAlerts) { DairyMobile.notifyAlerts(alerts); }
		});
	});

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
