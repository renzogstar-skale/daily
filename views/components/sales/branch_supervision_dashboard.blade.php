{{-- ==========================================================================
     DAILY — SUCURSALES: encabezado, filtros y pestañas.
     Indicadores y gráficos comerciales en components/sales/sales_reports (section = branches), con datos reales de
     SalesReportsService. Inventarios, conteos, traslados, robos, mermas, horarios e incidencias viven en sus propios tableros.
     ========================================================================== --}}
@php
	// Rango de fechas legible
	$mNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$dateLabel = intval(substr($startDate, 8, 2)) . ' ' . $mNames[intval(substr($startDate, 5, 2)) - 1] . ' – '
		. intval(substr($endDate, 8, 2)) . ' ' . $mNames[intval(substr($endDate, 5, 2)) - 1] . ' ' . substr($endDate, 0, 4);
	$is30d = $endDate === date('Y-m-d') && $startDate === date('Y-m-d', strtotime('-29 days'));

	// Sucursal actual
	$branchLabel = 'Todas las sucursales';
	if (!empty($selectedBranchId)) {
		foreach ($branches as $b) {
			if ($b->id == $selectedBranchId) {
				$branchLabel = $b->name;
				break;
			}
		}
	}

	// Departamento actual
	$deptLabel = 'Todos los departamentos';
	if (!empty($sharedFilters['department'])) {
		foreach ($filterOptions['departments'] as $d) {
			if ($d['key'] === $sharedFilters['department']) {
				$deptLabel = $d['name'];
				break;
			}
		}
	}
@endphp

<div class="ded-wrapper" id="branchCommercialDashboard" data-testid="branch-commercial-dashboard">

	<!-- ====================================================================
	     ENCABEZADO: TÍTULO, SUBTÍTULO Y SELECTORES SUPERIORES DERECHOS
	     ==================================================================== -->
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Sucursales</h1>
			<p class="ded-subtitle">Rendimiento comercial de cada tienda: ventas, metas, crecimiento, ticket, margen y participación en la red</p>
		</div>

		<div class="ded-header-right">
			<!-- Selector de Fechas -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnBrDates" title="Seleccionar período">
					<i data-lucide="calendar"></i>
					<span>{{ $dateLabel }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnBrDates" style="min-width: 230px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Período Comercial</div>
					<a href="#" class="ded-dropdown-item @if($is30d) is-active @endif" data-select-range="30d">
						<span>Últimos 30 días</span>
						@if($is30d)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					<a href="#" class="ded-dropdown-item" data-select-range="today">Hoy</a>
					<a href="#" class="ded-dropdown-item" data-select-range="yesterday">Ayer</a>
					<a href="#" class="ded-dropdown-item" data-select-range="week">Semana actual</a>
					<a href="#" class="ded-dropdown-item" data-select-range="month">Este mes en curso</a>
					<a href="#" class="ded-dropdown-item" data-select-range="quarter">Trimestre actual</a>
					<a href="#" class="ded-dropdown-item" data-select-range="prev_month">Mes anterior completo</a>
					<a href="#" class="ded-dropdown-item" data-select-range="year">Año acumulado 2026</a>
					<div class="dropdown-divider my-1"></div>
					<div class="px-3 py-2">
						<form method="GET" action="{{ $U('/sales') }}" class="d-flex flex-column gap-2">
							<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
							<input type="hidden" name="department" value="{{ $sharedFilters['department'] }}">
							<input type="hidden" name="tab" value="sucursales">
							<div class="d-flex align-items-center gap-2">
								<input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm" style="font-size: 11px;">
								<span style="font-size: 11px; color:#64748b;">–</span>
								<input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm" style="font-size: 11px;">
							</div>
							<button type="submit" class="btn btn-sm btn-primary w-100 mt-1" style="background:#10b981; border-color:#10b981; font-size:11px; font-weight:600;">Aplicar fechas</button>
						</form>
					</div>
				</div>
			</div>

			<!-- Selector de Sucursales -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnBrBranches" title="Filtrar por sucursal">
					<span>{{ $branchLabel }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnBrBranches" style="min-width: 220px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Centro Operativo</div>
					<a href="#" class="ded-dropdown-item @if(!$selectedBranchId) is-active @endif" data-select-branch="">
						<span>Todas las sucursales (Consolidado)</span>
						@if(!$selectedBranchId)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($branches as $b)
					<a href="#" class="ded-dropdown-item @if($selectedBranchId == $b->id) is-active @endif" data-select-branch="{{ $b->id }}">
						<span>{{ $b->name }}</span>
						@if($selectedBranchId == $b->id)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					@endforeach
				</div>
			</div>

			<!-- Selector de Departamentos -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnBrDepartments" title="Filtrar por departamento">
					<span>{{ $deptLabel }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnBrDepartments" style="min-width: 220px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Departamento Comercial</div>
					<a href="#" class="ded-dropdown-item @if(!$sharedFilters['department']) is-active @endif" data-select-dept="">
						<span>Todos los departamentos</span>
						@if(!$sharedFilters['department'])<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($filterOptions['departments'] as $d)
					<a href="#" class="ded-dropdown-item @if($sharedFilters['department'] === $d['key']) is-active @endif" data-select-dept="{{ $d['key'] }}">
						<span>{{ $d['name'] }}</span>
						@if($sharedFilters['department'] === $d['key'])<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					@endforeach
				</div>
			</div>

			<!-- Notificaciones -->
			<a href="{{ $U('/alerts') }}" class="ded-icon-btn" title="Notificaciones y Alertas activas" aria-label="Notificaciones">
				<i data-lucide="bell"></i>
				<span class="ded-notif-dot"></span>
			</a>

			<!-- Perfil del Usuario: Círculo verde con iniciales blancas -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-user-avatar ded-dropdown-toggle" id="btnBrUserAvatar" title="Perfil de usuario (RG)" aria-label="Perfil de usuario">
					RG
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnBrUserAvatar" style="min-width: 210px;">
					<div class="px-3 py-2 border-bottom">
						<div class="font-weight-bold text-dark" style="font-size: 13px;">Renzo G.</div>
						<small class="text-muted" style="font-size: 11px;">Manager / Director Comercial</small>
					</div>
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 4px 14px 2px;">Vistas de Ventas</div>
					<a href="{{ $U('/sales') }}" class="ded-dropdown-item"><i data-lucide="layout-dashboard" style="width:13px;height:13px;margin-right:6px;"></i>Tablero de Ventas</a>
					<a href="{{ $U('/sales?tab=departamentos') }}" class="ded-dropdown-item"><i data-lucide="layers" style="width:13px;height:13px;margin-right:6px;"></i>Ventas por departamento</a>
					<a href="{{ $U('/sales?tab=productos') }}" class="ded-dropdown-item"><i data-lucide="package" style="width:13px;height:13px;margin-right:6px;"></i>Productos</a>
					<a href="{{ $U('/sales?tab=sucursales') }}" class="ded-dropdown-item is-active"><i data-lucide="store" style="width:13px;height:13px;margin-right:6px;"></i>Sucursales</a>
					<a href="{{ $U('/sales?tab=detalle') }}" class="ded-dropdown-item"><i data-lucide="receipt" style="width:13px;height:13px;margin-right:6px;"></i>Historial de ventas</a>
					<a href="{{ $U('/sales?tab=rentabilidad') }}" class="ded-dropdown-item"><i data-lucide="coins" style="width:13px;height:13px;margin-right:6px;"></i>Rentabilidad</a>
					<div class="dropdown-divider my-1"></div>
					<a href="{{ $U('/usersettings') }}" class="ded-dropdown-item"><i data-lucide="user-cog" style="width:14px;height:14px;margin-right:6px;"></i>Configuración</a>
					<a href="{{ $U('/alerts') }}" class="ded-dropdown-item"><i data-lucide="bell" style="width:14px;height:14px;margin-right:6px;"></i>Centro de Alertas</a>
					<div class="dropdown-divider my-1"></div>
					<a href="{{ $U('/logout') }}" class="ded-dropdown-item text-danger"><i data-lucide="log-out" style="width:14px;height:14px;margin-right:6px;"></i>Cerrar sesión</a>
				</div>
			</div>
		</div>
	</header>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS INTEGRADA (CONSERVA TODAS LAS HERRAMIENTAS DE DAILY)
	     ==================================================================== -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Ventas y Rentabilidad">
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen-branch">
			<i class="fa-solid fa-chart-column mr-1"></i>
			<span>Tablero de Ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos-branch">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>
			<span>Ventas por departamento</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos-branch">
			<i class="fa-solid fa-barcode mr-1"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="ded-nav-tab is-active" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales-branch" aria-selected="true">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle-branch">
			<i class="fa-solid fa-receipt mr-1"></i>
			<span>Historial de ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad-branch">
			<i class="fa-solid fa-coins mr-1"></i>
			<span>Rentabilidad</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-branch">
			<i class="fa-solid fa-tags text-warning mr-1"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas-branch">
			<i class="fa-solid fa-bullhorn text-primary mr-1"></i>
			<span>Campañas</span>
		</button>
	</nav>

</div>
