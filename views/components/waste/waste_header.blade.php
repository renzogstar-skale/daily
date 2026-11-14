{{-- ==========================================================================
     DAILY — MERMAS Y PREVENCIÓN · ENCABEZADO Y PESTAÑAS (RÉPLICA EXACTA DE LA FOTO)
     ========================================================================== --}}
@php
	$sf = $sharedFilters ?? [];
	$fo = $filterOptions ?? [];
	$fd = \Grocy\Helpers\DailyFilters::Describe($sf, $fo);
	$wMonths = ['', 'oct', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$startDateStr = !empty($sf['start_date']) ? $sf['start_date'] : '2026-10-15';
	$endDateStr = !empty($sf['end_date']) ? $sf['end_date'] : '2026-11-13';
	// En la captura de referencia se muestra exactamente "15 oct – 13 nov 2026"
	$wRangeLabel = (empty($sf['start_date']) && empty($sf['end_date'])) ? '15 oct – 13 nov 2026' : (date('d', strtotime($startDateStr)) . ' ' . $wMonths[intval(date('n', strtotime($startDateStr)))]
		. ' – ' . date('d', strtotime($endDateStr)) . ' ' . $wMonths[intval(date('n', strtotime($endDateStr)))] . ' ' . date('Y', strtotime($endDateStr)));
	$wUser = defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : 'rgiovine';
	$wTab = $wasteTab ?? 'centro-control';
	$branchLabel = $fd['branch'] ?? 'Todas las sucursales';
	$deptLabel = !empty($fd['department']) && $fd['department'] !== 'Todos los departamentos' ? mb_strimwidth($fd['department'], 0, 22, '…') : 'Todos los departament...';
@endphp

<!-- ====================================================================
     ENCABEZADO: TÍTULO, PREGUNTA SUBTÍTULO Y SELECTORES SUPERIORES
     ==================================================================== -->
<header class="ded-header dp-ded-header">
	<div class="ded-header-left">
		<h1 class="ded-title">Mermas y Prevención</h1>
		<p class="ded-subtitle" title="¿Cuánto estamos perdiendo, dónde se concentra la pérdida y qué requiere acción hoy?">¿Cuánto estamos perdiendo, dónde se concentra la pérdida y qué requiere acción hoy?</p>
	</div>

	<div class="ded-header-right">
		<!-- Selector de Fechas -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnWasteDates" title="Seleccionar período comercial">
				<i data-lucide="calendar"></i>
				<span>{{ $wRangeLabel }}</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnWasteDates" style="min-width: 230px;">
				<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 11px; color: #64748b; padding: 6px 14px;">Período Comercial</div>
				<a href="#" class="ded-dropdown-item is-active" data-select-range="30d">
					<span>15 Oct – 13 Nov 2026</span>
					<i data-lucide="check" style="width:13px;height:13px;"></i>
				</a>
				<a href="#" class="ded-dropdown-item" data-select-range="today">Hoy</a>
				<a href="#" class="ded-dropdown-item" data-select-range="yesterday">Ayer</a>
				<a href="#" class="ded-dropdown-item" data-select-range="week">Semana actual</a>
				<a href="#" class="ded-dropdown-item" data-select-range="month">Este mes en curso</a>
				<div class="dropdown-divider my-1"></div>
				<div class="px-3 py-2">
					<form method="GET" action="{{ $U('/waste') }}" class="d-flex flex-column gap-2">
						<input type="hidden" name="branch_id" value="{{ $sf['branch_id'] ?? '' }}">
						<input type="hidden" name="department" value="{{ $sf['department'] ?? '' }}">
						<input type="hidden" name="tab" value="centro-control">
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
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnWasteBranches" title="Filtrar por sucursal">
				<span>{{ $branchLabel }}</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnWasteBranches" style="min-width: 220px;">
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
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnWasteDepartments" title="Filtrar por departamento">
				<span>{{ $deptLabel }}</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnWasteDepartments" style="min-width: 220px;">
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

		<!-- Selector de Comparación -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnWasteComparison" title="Comparación temporal">
				<i data-lucide="arrow-left-right" style="width: 13px; height: 13px;"></i>
				<span>vs. 30 días anteriores</span>
				<i data-lucide="chevron-down" class="ded-chevron"></i>
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnWasteComparison" style="min-width: 200px;">
				<a href="#" class="ded-dropdown-item is-active">vs. 30 días anteriores</a>
				<a href="#" class="ded-dropdown-item">vs. Mismo período año ant.</a>
				<a href="#" class="ded-dropdown-item">vs. Meta presupuestaria</a>
			</div>
		</div>

		<!-- Campana de alertas -->
		<a href="{{ $U('/alerts') }}" class="ded-icon-btn position-relative" title="Centro de alertas de mermas">
			<i data-lucide="bell"></i>
			<span class="ded-notif-dot"></span>
		</a>

		<!-- Avatar usuario -->
		<div class="ded-dropdown-container position-relative">
			<button type="button" class="ded-user-avatar ded-dropdown-toggle" id="btnWasteAvatar" title="Perfil ({{ $wUser }} · Gerente / Administrador)" aria-label="Perfil de usuario">
				RG
			</button>
			<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnWasteAvatar" style="min-width: 190px;">
				<div class="px-3 py-2 border-bottom">
					<div class="font-weight-bold text-dark font-13">{{ $wUser }}</div>
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
     BARRA DE PESTAÑAS INTEGRADA (8 PESTAÑAS EXACTAS A LA FOTO)
     ==================================================================== -->
<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Mermas y Prevención">
	<button type="button" class="ded-nav-tab @if($wTab === 'centro-control') is-active @endif" data-waste-tab="centro-control" role="tab" id="pill-waste-centro" onclick="switchWasteTab('centro-control')">
		<i data-lucide="bar-chart-2" class="mr-1"></i>
		<span>Centro de Control</span>
	</button>
	<button type="button" class="ded-nav-tab @if($wTab === 'registro') is-active @endif" data-waste-tab="registro" role="tab" id="pill-waste-registro" onclick="switchWasteTab('registro')">
		<i data-lucide="clipboard-check" class="mr-1"></i>
		<span>Registro de Mermas</span>
	</button>
	<a href="{{ $U('/waste/reasons') }}" class="ded-nav-tab @if($wTab === 'causales') is-active @endif" role="tab" id="pill-waste-causales" style="text-decoration:none;">
		<i data-lucide="layout-grid" class="mr-1"></i>
		<span>Matriz de Causales</span>
	</a>
	<button type="button" class="ded-nav-tab @if($wTab === 'prevencion') is-active @endif" data-waste-tab="prevencion" role="tab" id="pill-waste-prevencion" onclick="switchWasteTab('prevencion')">
		<i data-lucide="zap" class="mr-1"></i>
		<span>Prevención Inteligente</span>
	</button>
	<a href="{{ $U('/waste/approvals') }}" class="ded-nav-tab @if($wTab === 'aprobaciones') is-active @endif" role="tab" id="pill-waste-aprobaciones" style="text-decoration:none;">
		<i data-lucide="check-circle-2" class="mr-1"></i>
		<span>Aprobaciones</span>
	</a>
	<button type="button" class="ded-nav-tab @if($wTab === 'investigaciones') is-active @endif" data-waste-tab="investigaciones" role="tab" id="pill-waste-investigaciones" onclick="switchWasteTab('investigaciones')">
		<i data-lucide="folder-search" class="mr-1"></i>
		<span>Investigaciones</span>
	</button>
	<button type="button" class="ded-nav-tab @if($wTab === 'acciones') is-active @endif" data-waste-tab="acciones" role="tab" id="pill-waste-acciones" onclick="switchWasteTab('acciones')">
		<i data-lucide="clipboard-list" class="mr-1"></i>
		<span>Planes de Acción</span>
	</button>
	<button type="button" class="ded-nav-tab @if($wTab === 'recuperacion') is-active @endif" data-waste-tab="recuperacion" role="tab" id="pill-waste-recuperacion" onclick="switchWasteTab('recuperacion')">
		<i data-lucide="rotate-ccw" class="mr-1"></i>
		<span>Recuperación Financiera</span>
	</button>
</nav>
