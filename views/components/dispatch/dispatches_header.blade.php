{{-- ==========================================================================
     DAILY — DESPACHOS Y DEVOLUCIONES · ENCABEZADO Y PESTAÑAS (RÉPLICA EXACTA)
     ========================================================================== --}}
@php
	$sf = $sharedFilters ?? [];
	$fo = $filterOptions ?? [];
	$fd = \Grocy\Helpers\DailyFilters::Describe($sf, $fo);
	$dpMonths = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	$startDateStr = $sf['start_date'] ?? '2026-10-15';
	$endDateStr = $sf['end_date'] ?? '2026-11-13';
	$dpRangeLabel = date('d', strtotime($startDateStr)) . ' ' . $dpMonths[intval(date('n', strtotime($startDateStr)))]
		. ' - ' . date('d', strtotime($endDateStr)) . ' ' . $dpMonths[intval(date('n', strtotime($endDateStr)))] . ' ' . date('Y', strtotime($endDateStr));
	$dpUser = defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : 'rgiovine';
	$dpTab = $dpTab ?? 'tablero';
	$branchLabel = $fd['branch'] ?? 'Todas las sucursales';
	$deptLabel = !empty($fd['department']) && $fd['department'] !== 'Todos los departamentos' ? mb_strimwidth($fd['department'], 0, 22, '…') : 'Todos los departamentos...';
@endphp

<!-- ====================================================================
     ENCABEZADO: TÍTULO, SUBTÍTULO Y SELECTORES SUPERIORES DERECHOS
     ==================================================================== -->
<header class="ded-header dp-ded-header">
	<div class="ded-header-left">
		<h1 class="ded-title">Despachos y Devoluciones</h1>
		<p class="ded-subtitle">Seguimiento de pedidos, entregas y devoluciones</p>
	</div>

	<div class="ded-header-right">
		<!-- Selector de Fechas -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnDpDates" title="Seleccionar período comercial">
				<i data-lucide="calendar"></i>
				<span>{{ $dpRangeLabel }}</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpDates" style="min-width: 230px;">
				<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 11px; color: #64748b; padding: 6px 14px;">Período Comercial</div>
				<a href="#" class="ded-dropdown-item is-active" data-select-range="30d">
					<span>15 Oct – 13 Nov 2026</span>
					<i data-lucide="check" style="width:13px;height:13px;"></i>
				</a>
				<a href="#" class="ded-dropdown-item" data-select-range="today">Hoy</a>
				<a href="#" class="ded-dropdown-item" data-select-range="yesterday">Ayer</a>
				<a href="#" class="ded-dropdown-item" data-select-range="week">Semana actual</a>
				<a href="#" class="ded-dropdown-item" data-select-range="month">Este mes en curso</a>
				<a href="#" class="ded-dropdown-item" data-select-range="prev_month">Mes anterior</a>
				<div class="dropdown-divider my-1"></div>
				<div class="px-3 py-2">
					<form method="GET" action="{{ $U('/dispatches') }}" class="d-flex flex-column gap-2">
						<input type="hidden" name="branch_id" value="{{ $sf['branch_id'] ?? '' }}">
						<input type="hidden" name="department" value="{{ $sf['department'] ?? '' }}">
						<input type="hidden" name="tab" value="tablero">
						<div class="d-flex align-items-center gap-2">
							<input type="date" name="start_date" value="{{ $startDateStr }}" class="form-control form-control-sm" style="font-size: 11px;">
							<span style="font-size: 11px; color:#64748b;">–</span>
							<input type="date" name="end_date" value="{{ $endDateStr }}" class="form-control form-control-sm" style="font-size: 11px;">
						</div>
						<button type="submit" class="btn btn-sm btn-primary w-100 mt-1" style="background:#10b26c; border-color:#10b26c; font-size:11px; font-weight:600;">Aplicar fechas</button>
					</form>
				</div>
			</div>
		</div>

		<!-- Selector de Sucursales -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnDpBranches" title="Filtrar por sucursal">
				<span>{{ $branchLabel }}</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpBranches" style="min-width: 220px;">
				<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 11px; color: #64748b; padding: 6px 14px;">Centro Operativo</div>
				<a href="#" class="ded-dropdown-item @if(empty($sf['branch_id'])) is-active @endif" data-select-branch="">
					<span>Todas las sucursales</span>
					@if(empty($sf['branch_id']))<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
				</a>
				<div class="dropdown-divider my-1"></div>
				@foreach(($fo['branches'] ?? []) as $b)
				<a href="#" class="ded-dropdown-item @if(($sf['branch_id'] ?? null) == $b['id']) is-active @endif" data-select-branch="{{ $b['id'] }}">
					<span>{{ $b['name'] }}</span>
					@if(($sf['branch_id'] ?? null) == $b['id'])<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
				</a>
				@endforeach
			</div>
		</div>

		<!-- Selector de Departamentos -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnDpDepartments" title="Filtrar por departamento">
				<span>{{ $deptLabel }}</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpDepartments" style="min-width: 220px;">
				<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 11px; color: #64748b; padding: 6px 14px;">Departamento Comercial</div>
				<a href="#" class="ded-dropdown-item @if(empty($sf['department'])) is-active @endif" data-select-dept="">
					<span>Todos los departamentos</span>
					@if(empty($sf['department']))<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
				</a>
				<div class="dropdown-divider my-1"></div>
				@foreach(($fo['departments'] ?? []) as $d)
				<a href="#" class="ded-dropdown-item @if(($sf['department'] ?? null) === $d['key']) is-active @endif" data-select-dept="{{ $d['key'] }}">
					<span>{{ $d['name'] }}</span>
					@if(($sf['department'] ?? null) === $d['key'])<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
				</a>
				@endforeach
			</div>
		</div>

		<!-- Campana de alertas -->
		<a href="{{ $U('/alerts') }}" class="ded-icon-btn position-relative" title="Centro de alertas de despachos">
			<i data-lucide="bell"></i>
			<span class="ded-notif-dot"></span>
		</a>

		<!-- Avatar usuario -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-user-avatar ded-dropdown-toggle" id="btnDpAvatar" title="Perfil ({{ $dpUser }} · Gerente / Administrador)" aria-label="Perfil de usuario">
				RG
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpAvatar" style="min-width: 190px;">
				<div class="px-3 py-2 border-bottom">
					<div class="font-weight-bold text-dark font-13">{{ $dpUser }}</div>
					<small class="text-muted">Gerente / Administrador</small>
				</div>
				<a href="{{ $U('/usersettings') }}" class="ded-dropdown-item"><i data-lucide="settings" style="width:14px;height:14px;margin-right:6px;"></i>Configuración</a>
				<a href="{{ $U('/alerts') }}" class="ded-dropdown-item"><i data-lucide="bell" style="width:14px;height:14px;margin-right:6px;"></i>Alertas</a>
				<div class="dropdown-divider my-1"></div>
				<a href="{{ $U('/logout') }}" class="ded-dropdown-item text-danger"><i data-lucide="log-out" style="width:14px;height:14px;margin-right:6px;"></i>Cerrar sesión</a>
			</div>
		</div>
	</div>
</header>

<!-- ====================================================================
     BARRA DE PESTAÑAS INTEGRADA (3 PESTAÑAS EXACTAS A LA FOTO)
     ==================================================================== -->
<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Despachos y Devoluciones">
	<button type="button" class="ded-nav-tab @if($dpTab === 'tablero') is-active @endif" data-dp-tab="tablero" role="tab" id="pill-dp-tablero" onclick="switchDispatchTab('tablero')">
		<i data-lucide="bar-chart-2" class="mr-1"></i>
		<span>Tablero de Despachos</span>
	</button>
	<button type="button" class="ded-nav-tab @if($dpTab === 'pedidos') is-active @endif" data-dp-tab="pedidos" role="tab" id="pill-dp-pedidos" onclick="switchDispatchTab('pedidos')">
		<i data-lucide="truck" class="mr-1"></i>
		<span>Pedidos y Entregas</span>
	</button>
	<a href="{{ $U('/returns') }}" class="ded-nav-tab @if($dpTab === 'devoluciones') is-active @endif" role="tab" id="pill-dp-devoluciones" style="text-decoration:none;">
		<i data-lucide="rotate-ccw" class="mr-1"></i>
		<span>Devoluciones</span>
	</a>
</nav>
