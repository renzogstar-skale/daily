{{-- ==========================================================================
     DAILY — RENDIMIENTO POR DEPARTAMENTO (RÉPLICA EXACTA DE LA FOTO)
     Dashboard Ejecutivo de Rentabilidad, Productividad y Oportunidades
     ========================================================================== --}}
@php
	$de = $deptExec ?? [];
	$k = $de['kpis'] ?? [];
	$depts = $de['departments'] ?? [];
	$dri = $de['dri'] ?? [];
	$rep = $de['replenishment'] ?? [];
	$heatmap = $de['heatmap'] ?? [];
	$opps = $de['opportunities'] ?? [];

	// Rango de fechas legible
	$dateLabel = '8 oct – 6 nov 2026';
	if (!empty($startDate) && !empty($endDate)) {
		$mNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
		$sP = explode('-', $startDate);
		$eP = explode('-', $endDate);
		if (count($sP) === 3 && count($eP) === 3) {
			$sM = $mNames[intval($sP[1]) - 1] ?? 'oct';
			$eM = $mNames[intval($eP[1]) - 1] ?? 'nov';
			$dateLabel = intval($sP[2]) . ' ' . $sM . ' – ' . intval($eP[2]) . ' ' . $eM . ' ' . $eP[0];
		}
	}

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

<div class="ded-wrapper" id="deptExecutiveDashboard" data-testid="dept-executive-dashboard">

	<!-- ====================================================================
	     ENCABEZADO: TÍTULO, SUBTÍTULO Y SELECTORES SUPERIORES DERECHOS
	     ==================================================================== -->
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Ventas por departamento</h1>
			<p class="ded-subtitle">Análisis ejecutivo de rentabilidad, productividad y eficiencia por departamento comercial</p>
		</div>

		<div class="ded-header-right">
			<!-- Selector de Fechas -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnDpDates" title="Seleccionar período">
					<i data-lucide="calendar"></i>
					<span>{{ $dateLabel }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpDates" style="min-width: 230px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Período Comercial</div>
					<a href="#" class="ded-dropdown-item @if($startDate === '2026-10-08' && $endDate === '2026-11-06') is-active @endif" data-select-range="30d">
						<span>Últimos 30 días (8 oct – 6 nov)</span>
						@if($startDate === '2026-10-08' && $endDate === '2026-11-06')<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					<a href="#" class="ded-dropdown-item" data-select-range="month">Este mes en curso</a>
					<a href="#" class="ded-dropdown-item" data-select-range="prev_month">Mes anterior completo</a>
					<a href="#" class="ded-dropdown-item" data-select-range="year">Año acumulado 2026</a>
					<div class="dropdown-divider my-1"></div>
					<div class="px-3 py-2">
						<form method="GET" action="{{ $U('/sales') }}" class="d-flex flex-column gap-2">
							<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
							<input type="hidden" name="department" value="{{ $sharedFilters['department'] }}">
							<input type="hidden" name="tab" value="departamentos">
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
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnDpBranches" title="Filtrar por sucursal">
					<span>{{ $branchLabel }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpBranches" style="min-width: 220px;">
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
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnDpDepartments" title="Filtrar por departamento">
					<span>{{ $deptLabel }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpDepartments" style="min-width: 220px;">
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
				<button type="button" class="ded-user-avatar ded-dropdown-toggle" id="btnDpUserAvatar" title="Perfil de usuario (RG)" aria-label="Perfil de usuario">
					RG
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnDpUserAvatar" style="min-width: 210px;">
					<div class="px-3 py-2 border-bottom">
						<div class="font-weight-bold text-dark" style="font-size: 13px;">Renzo G.</div>
						<small class="text-muted" style="font-size: 11px;">Manager / Director Comercial</small>
					</div>
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 4px 14px 2px;">Vistas de Ventas</div>
					<a href="{{ $U('/sales') }}" class="ded-dropdown-item"><i data-lucide="layout-dashboard" style="width:13px;height:13px;margin-right:6px;"></i>Tablero de Ventas</a>
					<a href="{{ $U('/sales?tab=departamentos') }}" class="ded-dropdown-item is-active"><i data-lucide="layers" style="width:13px;height:13px;margin-right:6px;"></i>Ventas por departamento</a>
					<a href="{{ $U('/sales?tab=productos') }}" class="ded-dropdown-item"><i data-lucide="package" style="width:13px;height:13px;margin-right:6px;"></i>Productos</a>
					<a href="{{ $U('/sales?tab=sucursales') }}" class="ded-dropdown-item"><i data-lucide="store" style="width:13px;height:13px;margin-right:6px;"></i>Sucursales</a>
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
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen-dp">
			<i class="fa-solid fa-chart-column mr-1"></i>
			<span>Tablero de Ventas</span>
		</button>
		<button type="button" class="ded-nav-tab is-active" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos-dp" aria-selected="true">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>
			<span>Ventas por departamento</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos-dp">
			<i class="fa-solid fa-barcode mr-1"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales-dp">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle-dp">
			<i class="fa-solid fa-receipt mr-1"></i>
			<span>Historial de ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad-dp">
			<i class="fa-solid fa-coins mr-1"></i>
			<span>Rentabilidad</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-dp">
			<i class="fa-solid fa-tags text-warning mr-1"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas-dp">
			<i class="fa-solid fa-bullhorn text-primary mr-1"></i>
			<span>Campañas</span>
		</button>
	</nav>

	<!-- ====================================================================
	     PRIMERA FILA — 7 INDICADORES EJECUTIVOS (EXACTAMENTE EN ESTE ORDEN)
	     ==================================================================== -->
	<section class="ded-kpis-grid" aria-label="Indicadores Ejecutivos Departamentales">
		<!-- 1. Margen de contribución (Degradado verde oscuro) -->
		<article class="ded-kpi-card is-highlight" title="Margen de contribución del período">
			<div class="ded-kpi-top-highlight">
				<div class="ded-highlight-brand">
					<i data-lucide="coins"></i>
					<span>Margen de contribución</span>
				</div>
				<div class="ded-highlight-spark-box">
					<i data-lucide="trending-up"></i>
				</div>
			</div>
			<div class="ded-kpi-val">{{ number_format($k['contribution_margin']['val'] ?? 28.4, 1) }} %</div>
			<div class="ded-kpi-sub-highlight">
				{{ $k['contribution_margin']['delta'] ?? '▲ +2.6 pp vs. periodo anterior' }}
			</div>
		</article>

		<!-- 2. Utilidad operativa -->
		<article class="ded-kpi-card" title="Utilidad operativa generada">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="circle-dollar-sign"></i></span>
			</div>
			<div class="ded-kpi-label">Utilidad operativa</div>
			<div class="ded-kpi-val">${{ number_format($k['operating_profit']['val'] ?? 189920, 0) }}</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">{{ $k['operating_profit']['delta'] ?? '▲ +5.1 %' }}</span>
			</div>
		</article>

		<!-- 3. Productividad laboral -->
		<article class="ded-kpi-card" title="Ventas o contribución por hora de trabajo">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="users"></i></span>
			</div>
			<div class="ded-kpi-label">Productividad laboral</div>
			<div class="ded-kpi-val">${{ number_format($k['labor_productivity']['val'] ?? 42.6, 1) }} / h</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">{{ $k['labor_productivity']['delta'] ?? '▲ +6.8 %' }}</span>
			</div>
		</article>

		<!-- 4. Costo laboral / ventas -->
		<article class="ded-kpi-card" title="Proporción del gasto de personal sobre ventas">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="clock"></i></span>
			</div>
			<div class="ded-kpi-label">Costo laboral / ventas</div>
			<div class="ded-kpi-val">{{ number_format($k['labor_cost_pct']['val'] ?? 11.9, 1) }} %</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green" style="background:#ecfdf5; color:#059669;">{{ $k['labor_cost_pct']['delta'] ?? '▼ -0.8 pp' }}</span>
			</div>
		</article>

		<!-- 5. Eficiencia operativa -->
		<article class="ded-kpi-card" title="Cumplimiento de tareas y procesos comerciales">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="target"></i></span>
			</div>
			<div class="ded-kpi-label">Eficiencia operativa</div>
			<div class="ded-kpi-val">{{ number_format($k['operating_efficiency']['val'] ?? 92.3, 1) }} %</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">{{ $k['operating_efficiency']['delta'] ?? '▲ +3.2 pp' }}</span>
			</div>
		</article>

		<!-- 6. Rentabilidad por m² -->
		<article class="ded-kpi-card" title="Rendimiento económico por superficie de sala de venta">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="layout-grid"></i></span>
			</div>
			<div class="ded-kpi-label">Rentabilidad por m²</div>
			<div class="ded-kpi-val">${{ number_format($k['revenue_per_sqm']['val'] ?? 412.8, 1) }}</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">{{ $k['revenue_per_sqm']['delta'] ?? '▲ +7.4 pp' }}</span>
			</div>
		</article>

		<!-- 7. Oportunidad de mejora (Fondo amarillo suave) -->
		<article class="ded-kpi-card is-warning-card" title="Potencial de captura económica por optimización" onclick="openActionPlanModal(1);">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="lightbulb"></i></span>
			</div>
			<div class="ded-kpi-label">Oportunidad de mejora</div>
			<div class="ded-kpi-val">${{ number_format($k['improvement_opportunity']['val'] ?? 27530, 0) }}</div>
			<div class="ded-kpi-sub text-muted font-weight-500">
				{{ $k['improvement_opportunity']['sub'] ?? 'Impacto estimado' }}
			</div>
		</article>
	</section>

	@include('components.sales.ty_ly_comparison')

	<!-- ====================================================================
	     SEGUNDA FILA — DESEMPEÑO POR DEPARTAMENTO + DRI
	     ==================================================================== -->
	<section class="ded-row-2" style="grid-template-columns: 1.45fr 0.95fr;">
		<!-- Izquierda: Desempeño por departamento -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Desempeño por departamento</h2>
				<div class="ded-card-tools">
					<button type="button" class="btn btn-xs btn-outline-secondary" onclick="openWhatIfModal();" title="Abrir simulador What-If" style="font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px;">
						<i data-lucide="sliders" style="width:12px;height:12px;margin-right:3px;"></i>Simulador What-If
					</button>
				</div>
			</header>
			<div class="table-responsive">
				<table class="dp-table-depts" id="tableDeptPerformance" aria-label="Tabla de desempeño departamental">
					<thead>
						<tr>
							<th style="min-width: 130px;">Departamento</th>
							<th class="text-right" onclick="sortDeptTable(1);" style="cursor: pointer;">Margen % <i data-lucide="arrow-up-down" style="width:10px;height:10px;"></i></th>
							<th class="text-right" onclick="sortDeptTable(2);" style="cursor: pointer;">Utilidad <i data-lucide="arrow-up-down" style="width:10px;height:10px;"></i></th>
							<th class="text-right">Costo laboral %</th>
							<th class="text-right">Utilidad / h</th>
							<th class="text-right">Merma %</th>
							<th class="text-right">Disponibilidad</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach($depts as $d)
						<tr class="is-clickable" data-dept-key="{{ $d['key'] }}" onclick="openDeptDetailModal('{{ $d['key'] }}');" title="Clic para ver análisis detallado de {{ $d['name'] }}">
							<td>
								<div class="dp-dept-name-cell">
									<span class="dp-dept-icon-box is-{{ $d['key'] }}">
										<i data-lucide="{{ $d['icon'] }}" style="width:13px;height:13px;"></i>
									</span>
									<span>{{ $d['full_name'] }}</span>
								</div>
							</td>
							<td class="text-right font-weight-600">{{ number_format($d['margin_pct'], 1) }} %</td>
							<td class="text-right font-weight-700">${{ number_format($d['profit'], 0) }}</td>
							<td class="text-right">{{ number_format($d['labor_cost_pct'], 1) }} %</td>
							<td class="text-right font-weight-600">${{ number_format($d['profit_per_hour'], 1) }}</td>
							<td class="text-right">{{ number_format($d['waste_pct'], 1) }} %</td>
							<td class="text-right font-weight-600">{{ number_format($d['availability_pct'], 1) }} %</td>
							<td class="text-center">
								<span class="dp-status-pill {{ $d['status_pill'] }}">{{ $d['status'] }}</span>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>

		<!-- Derecha: DRI – Índice de Rendimiento Departamental -->
		<article class="ded-card">
			<header class="ded-card-header mb-2">
				<div class="d-flex align-items-center justify-content-between w-100" style="gap: 8px;">
					<h2 class="ded-card-title mb-0" style="font-size: 13px; font-weight: 700; letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="DRI – Índice de Rendimiento Departamental">DRI – Índice de Rendimiento Departamental</h2>
					<button type="button" class="btn p-0 text-muted border-0 bg-transparent flex-shrink-0" onclick="openDriMethodologyModal();" title="Ver metodología y ponderaciones del DRI" style="line-height:1; display:flex; align-items:center;">
						<i data-lucide="info" style="width:14px;height:14px;"></i>
					</button>
				</div>
			</header>

			<div class="dp-dri-container">
				<!-- Semicírculo Gauge Medidor de 0 a 100 -->
				<div class="dp-dri-gauge-wrap">
					@php
						$scoreVal = floatval($dri['score'] ?? 82);
						$scoreClamped = max(0, min(100, $scoreVal));
						$rad = deg2rad(180 - ($scoreClamped / 100.0) * 180);
						$dotX = round(100 + 74 * cos($rad), 1);
						$dotY = round(108 - 74 * sin($rad), 1);
					@endphp
					<svg class="dp-dri-svg" viewBox="0 0 200 128" aria-label="Medidor de DRI">
						<!-- Arco de fondo / Colores de escala -->
						<defs>
							<linearGradient id="driGradient" x1="0%" y1="0%" x2="100%" y2="0%">
								<stop offset="0%" stop-color="#ef4444" />
								<stop offset="30%" stop-color="#f97316" />
								<stop offset="60%" stop-color="#eab308" />
								<stop offset="100%" stop-color="#10b981" />
							</linearGradient>
							<filter id="driDotShadow" x="-30%" y="-30%" width="160%" height="160%">
								<feDropShadow dx="0" dy="1.5" stdDeviation="2" flood-opacity="0.25" />
							</filter>
						</defs>
						<!-- Pista de fondo gris -->
						<path d="M 26 108 A 74 74 0 0 1 174 108" fill="none" stroke="#e2e8f0" stroke-width="12" stroke-linecap="round" />
						<!-- Pista coloreada con degradado -->
						<path d="M 26 108 A 74 74 0 0 1 174 108" fill="none" stroke="url(#driGradient)" stroke-width="12" stroke-linecap="round" />
						<!-- Etiquetas 0 y 100 en los extremos -->
						<text x="24" y="124" text-anchor="middle" class="dp-dri-axis-label">0</text>
						<text x="176" y="124" text-anchor="middle" class="dp-dri-axis-label">100</text>
						<!-- Puntero indicador en el valor del score -->
						<circle cx="{{ $dotX }}" cy="{{ $dotY }}" r="5.5" fill="#ffffff" stroke="#10b981" stroke-width="3" filter="url(#driDotShadow)" />
					</svg>

					<div class="dp-dri-gauge-center">
						<div class="dp-dri-score-num">{{ $dri['score'] ?? 82 }}</div>
						<div class="dp-dri-score-total">/ 100</div>
						<div class="dp-dri-status-badge {{ $dri['badge_class'] ?? 'is-bueno' }}">
							<span class="dp-dri-status-dot"></span>
							<span>{{ $dri['label'] ?? 'Bueno' }}</span>
						</div>
					</div>
				</div>

				<!-- Ranking DRI -->
				<div class="dp-dri-ranking-box">
					<div class="dp-dri-ranking-header">
						<i data-lucide="trophy" style="width:13px;height:13px;color:#f59e0b;"></i>
						<span>Ranking DRI</span>
					</div>
					<div class="dp-dri-ranking-list">
						@foreach(($dri['ranking'] ?? []) as $rk)
						<div class="dp-dri-ranking-item @if(!empty($rk['is_highlight'])) is-highlight @endif" title="{{ $rk['name'] }}: {{ $rk['score'] }} pts DRI">
							<div class="dp-dri-rank-left">
								<span class="dp-dri-rank-num">{{ $rk['rank'] }}</span>
								<span class="dp-dri-rank-name">{{ $rk['name'] }}</span>
							</div>
							<span class="dp-dri-rank-val">{{ $rk['score'] }}</span>
						</div>
						@endforeach
					</div>
				</div>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     TERCERA FILA — ANÁLISIS OPERATIVO (3 PANELES)
	     ==================================================================== -->
	<section class="dp-row-3">
		<!-- Panel 1: Rentabilidad por departamento (Barras Horizontales) -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Rentabilidad por departamento</h2>
				<div class="btn-group btn-group-sm" role="group" aria-label="Escala de visualización">
					<button type="button" class="btn btn-xs btn-light border is-active" id="btnToggleRentDollar" onclick="setRentabilityMode('dollar');" style="font-size:10px;font-weight:700;">$</button>
					<button type="button" class="btn btn-xs btn-light border" id="btnToggleRentPct" onclick="setRentabilityMode('pct');" style="font-size:10px;font-weight:700;">%</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box" style="height: 195px;">
				<canvas id="chartDeptProfitability" aria-label="Gráfico de barras de rentabilidad"></canvas>
			</div>
			<div class="d-flex align-items-center justify-content-center gap-3 mt-1" style="font-size: 11px; color: #64748b;">
				<span class="d-inline-flex align-items-center gap-1">
					<span style="display:inline-block;width:10px;height:10px;background:#059669;border-radius:2px;"></span>
					<span>Utilidad operativa</span>
				</span>
				<span class="d-inline-flex align-items-center gap-1">
					<span style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:2px;"></span>
					<span>Margen</span>
				</span>
			</div>
		</article>

		<!-- Panel 2: Productividad del personal por hora (Gráfico Combinado) -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Productividad del personal por hora</h2>
				<div class="d-flex align-items-center gap-2" style="font-size: 10.5px; color: #64748b;">
					<span class="d-inline-flex align-items-center gap-1">
						<span style="display:inline-block;width:8px;height:8px;background:rgba(16,185,129,0.3);border-radius:2px;"></span>
						<span>Ventas/hora</span>
					</span>
					<span class="d-inline-flex align-items-center gap-1">
						<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#10b981;"></span>
						<span>Utilidad/hora</span>
					</span>
					<span class="d-inline-flex align-items-center gap-1">
						<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#f59e0b;"></span>
						<span>Personal</span>
					</span>
				</div>
			</header>
			<div class="ded-chart-canvas-box" style="height: 215px;">
				<canvas id="chartDeptProductivityHourly" aria-label="Gráfico de productividad horaria"></canvas>
			</div>
		</article>

		<!-- Panel 3: Eficiencia de reposición y disponibilidad (4 Tarjetas) -->
		<article class="ded-card">
			<header class="ded-card-header mb-1">
				<h2 class="ded-card-title">Eficiencia de reposición y disponibilidad</h2>
			</header>
			<div class="dp-replenish-grid">
				<!-- 1. Disponibilidad -->
				<div class="dp-rep-card" onclick="openReplenishAuditModal('availability');" title="Disponibilidad en góndola">
					<div class="dp-rep-icon is-green"><i data-lucide="shopping-cart" style="width:15px;height:15px;"></i></div>
					<div class="dp-rep-label">Disponibilidad</div>
					<div class="dp-rep-val">{{ $rep['availability']['val'] ?? '94.1 %' }}</div>
					<div class="dp-rep-delta is-good">{{ $rep['availability']['delta'] ?? '▲ +1.8 pp' }}</div>
				</div>

				<!-- 2. Tiempo de reposición -->
				<div class="dp-rep-card" onclick="openReplenishAuditModal('time');" title="Tiempo medio de reposición">
					<div class="dp-rep-icon is-green"><i data-lucide="package" style="width:15px;height:15px;"></i></div>
					<div class="dp-rep-label">Tiempo de reposición</div>
					<div class="dp-rep-val">{{ $rep['replenish_time']['val'] ?? '18 min' }}</div>
					<div class="dp-rep-delta is-bad">{{ $rep['replenish_time']['delta'] ?? '▲ +2 min' }}</div>
				</div>

				<!-- 3. Cumplimiento -->
				<div class="dp-rep-card" onclick="openReplenishAuditModal('compliance');" title="Cumplimiento de reposición">
					<div class="dp-rep-icon is-green"><i data-lucide="refresh-cw" style="width:15px;height:15px;"></i></div>
					<div class="dp-rep-label">Cumplimiento</div>
					<div class="dp-rep-val">{{ $rep['compliance']['val'] ?? '91.6 %' }}</div>
					<div class="dp-rep-delta is-good">{{ $rep['compliance']['delta'] ?? '▲ +3.4 pp' }}</div>
				</div>

				<!-- 4. Agotados -->
				<div class="dp-rep-card" onclick="openReplenishAuditModal('stockouts');" title="SKUs agotados en sala">
					<div class="dp-rep-icon is-red"><i data-lucide="triangle-alert" style="width:15px;height:15px;"></i></div>
					<div class="dp-rep-label">Agotados</div>
					<div class="dp-rep-val">{{ $rep['stockouts']['val'] ?? '37' }}</div>
					<div class="dp-rep-delta is-bad">{{ $rep['stockouts']['delta'] ?? '▲ +12' }}</div>
				</div>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     CUARTA FILA — MAPA DE CALOR + OPORTUNIDADES PRIORITARIAS
	     ==================================================================== -->
	<section class="dp-row-4">
		<!-- Izquierda: Mapa de calor de rendimiento -->
		<article class="ded-card">
			<header class="ded-card-header mb-1">
				<div class="d-flex align-items-center gap-1">
					<h2 class="ded-card-title">Mapa de calor de rendimiento</h2>
					<button type="button" class="btn p-0 text-muted border-0 bg-transparent" onclick="openHeatmapInfoModal();" title="Información sobre objetivos y desviaciones" style="line-height:1;">
						<i data-lucide="info" style="width:14px;height:14px;"></i>
					</button>
				</div>
			</header>
			<div class="table-responsive">
				<table class="dp-heatmap-table" aria-label="Mapa de calor de rendimiento departamental">
					<thead>
						<tr>
							@foreach(($heatmap['headers'] ?? ['Departamento', 'Rentabilidad', 'Productividad', 'Disponibilidad', 'Merma', 'Costo laboral', 'Cumplimiento']) as $h)
							<th>{{ $h }}</th>
							@endforeach
						</tr>
					</thead>
					<tbody>
						@foreach(($heatmap['rows'] ?? []) as $row)
						<tr>
							<td class="dp-hm-dept">{{ $row['dept'] }}</td>
							@foreach($row['metrics'] as $m)
							<td>
								<span class="dp-hm-cell {{ $m['class'] }}" onclick="openHeatmapCellModal('{{ $row['dept'] }}', '{{ $m['label'] }}', '{{ $m['val'] }}', '{{ $m['target'] }}', '{{ $m['dev'] }}', '{{ addslashes($m['cause']) }}');" title="{{ $row['dept'] }} - {{ $m['label'] }}: {{ $m['val'] }} (Meta: {{ $m['target'] }})">
									{{ $m['val'] }}
								</span>
							</td>
							@endforeach
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>

		<!-- Derecha: Oportunidades de mejora prioritarias -->
		<article class="ded-card">
			<header class="ded-card-header mb-1">
				<div class="d-flex align-items-center gap-2">
					<i data-lucide="target" style="width:16px;height:16px;color:#059669;"></i>
					<h2 class="ded-card-title">Oportunidades de mejora prioritarias</h2>
				</div>
			</header>
			<div class="table-responsive">
				<table class="dp-opps-table" aria-label="Oportunidades de mejora económica">
					<thead>
						<tr>
							<th style="width: 20px;">#</th>
							<th>Departamento</th>
							<th>Problema detectado</th>
							<th class="text-right">Impacto estimado</th>
							<th>Acción recomendada</th>
							<th class="text-center">Prioridad</th>
						</tr>
					</thead>
					<tbody>
						@foreach($opps as $op)
						<tr class="is-clickable" onclick="openActionPlanModal({{ $op['id'] }});" title="Clic para gestionar plan de acción: {{ $op['problem'] }}">
							<td class="dp-opp-rank">{{ $op['id'] }}</td>
							<td class="font-weight-600">{{ $op['dept'] }}</td>
							<td>{{ $op['problem'] }}</td>
							<td class="dp-opp-impact">{{ $op['impact'] }}</td>
							<td style="color: #475569;">{{ $op['action'] }}</td>
							<td class="text-center">
								<span class="dp-opp-priority {{ $op['priority_class'] }}">{{ $op['priority'] }}</span>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>
	</section>

</div>

<!-- Payload JSON para gráficos y modales interactivos -->
<script type="application/json" id="deptExecutiveData">{!! json_encode([
	'departments' => $depts,
	'profitability' => $de['profitability_bars'] ?? [],
	'productivity' => $de['productivity_hourly'] ?? [],
	'dri' => $dri,
	'opportunities' => $opps
], JSON_HEX_TAG) !!}</script>

<!-- ====================================================================
     MODALES INTERACTIVOS DE DECISIÓN Y GESTIÓN
     ==================================================================== -->

<!-- 1. Modal: Análisis Detallado por Departamento -->
<div class="modal fade" id="modalDeptDetail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
			<div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
				<div class="d-flex align-items-center gap-2">
					<i data-lucide="layers" style="width:20px;height:20px;"></i>
					<h5 class="modal-title font-weight-bold mb-0" id="deptDetailTitle" style="font-size: 16px;">Análisis Departamental</h5>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" style="background: #f8fafc;" id="deptDetailBody">
				<!-- Inyectado dinámicamente -->
			</div>
			<div class="modal-footer bg-white py-2 px-4 border-top d-flex justify-content-between">
				<button type="button" class="btn btn-sm btn-outline-success font-weight-600" id="btnLaunchWhatIfFromDept">
					<i data-lucide="sliders" style="width:13px;height:13px;margin-right:4px;"></i>Simular What-If
				</button>
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- 2. Modal: Simulador What-If -->
<div class="modal fade" id="modalWhatIf" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
			<div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);">
				<div class="d-flex align-items-center gap-2">
					<i data-lucide="sliders" style="width:20px;height:20px;"></i>
					<h5 class="modal-title font-weight-bold mb-0" style="font-size: 16px;">Simulador Comercial What-If de Impacto Económico</h5>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4 bg-white">
				<p class="text-muted" style="font-size: 12.5px;">Ajusta las variables operativas para proyectar la captura de margen y la mejora en el DRI global de la tienda.</p>
				<div class="row">
					<div class="col-md-6 border-right pr-4">
						<div class="form-group mb-3">
							<label class="font-weight-600" style="font-size: 12px; color: #334155;">Reducción de Mermas</label>
							<div class="d-flex align-items-center gap-2">
								<input type="range" class="form-control-range" id="simWasteRange" min="0" max="2" step="0.2" value="0.5" oninput="runWhatIfCalculation();">
								<span class="font-weight-bold text-success" id="simWasteVal" style="font-size: 13px; min-width: 50px;">-0.5 %</span>
							</div>
							<small class="text-muted">Afecta Pescadería, Carnes y Bakery mediante control FEFO.</small>
						</div>

						<div class="form-group mb-3">
							<label class="font-weight-600" style="font-size: 12px; color: #334155;">Mejora en Disponibilidad de Góndola</label>
							<div class="d-flex align-items-center gap-2">
								<input type="range" class="form-control-range" id="simAvailRange" min="0" max="5" step="0.5" value="1.5" oninput="runWhatIfCalculation();">
								<span class="font-weight-bold text-success" id="simAvailVal" style="font-size: 13px; min-width: 50px;">+1.5 %</span>
							</div>
							<small class="text-muted">Recupera ventas perdidas por agotados en horas pico.</small>
						</div>

						<div class="form-group mb-3">
							<label class="font-weight-600" style="font-size: 12px; color: #334155;">Optimización de Costo Laboral</label>
							<div class="d-flex align-items-center gap-2">
								<input type="range" class="form-control-range" id="simLaborRange" min="0" max="3" step="0.2" value="0.8" oninput="runWhatIfCalculation();">
								<span class="font-weight-bold text-success" id="simLaborVal" style="font-size: 13px; min-width: 50px;">-0.8 %</span>
							</div>
							<small class="text-muted">Ajuste de distribución de turnos sin reducir plantilla.</small>
						</div>
					</div>

					<div class="col-md-6 pl-4 d-flex flex-column justify-content-center">
						<div class="p-3 rounded border text-center mb-3" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
							<span class="text-muted font-weight-600 text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Impacto Económico Estimado</span>
							<div class="font-weight-800 text-success my-1" id="simResultAmount" style="font-size: 28px;">+$14,850</div>
							<span class="badge badge-success font-weight-normal px-2 py-1" style="font-size: 11px;">+7.8% sobre Utilidad Operativa</span>
						</div>

						<div class="p-3 rounded border text-center" style="background: #f8fafc;">
							<span class="text-muted font-weight-600 text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Nuevo DRI Proyectado</span>
							<div class="font-weight-800 text-dark my-1" style="font-size: 26px;">
								<span id="simResultDri">86</span> <span class="text-muted font-weight-normal" style="font-size: 14px;">/ 100</span>
							</div>
							<span class="badge badge-success px-2 py-1" id="simResultDriBadge" style="font-size: 11px;">Sobresaliente</span>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4 border-top">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- 3. Modal: Plan de Acción y Oportunidad de Mejora -->
<div class="modal fade" id="modalActionPlan" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
			<div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
				<div class="d-flex align-items-center gap-2">
					<i data-lucide="target" style="width:20px;height:20px;"></i>
					<h5 class="modal-title font-weight-bold mb-0" id="planModalTitle" style="font-size: 16px;">Plan de Acción Operativo</h5>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4 bg-white">
				<form id="formPlanAccion">
					<input type="hidden" id="planOppId" value="1">
					<div class="form-group mb-2">
						<label class="font-weight-600 text-dark" style="font-size: 12px;">Problema Detectado</label>
						<input type="text" class="form-control form-control-sm" id="planProblem" readonly style="background:#f8fafc;font-weight:600;">
					</div>
					<div class="row">
						<div class="col-6 form-group mb-2">
							<label class="font-weight-600 text-dark" style="font-size: 12px;">Departamento</label>
							<input type="text" class="form-control form-control-sm" id="planDept" readonly style="background:#f8fafc;">
						</div>
						<div class="col-6 form-group mb-2">
							<label class="font-weight-600 text-dark" style="font-size: 12px;">Impacto Económico</label>
							<input type="text" class="form-control form-control-sm font-weight-bold text-success" id="planImpact" readonly style="background:#f8fafc;">
						</div>
					</div>
					<div class="form-group mb-2">
						<label class="font-weight-600 text-dark" style="font-size: 12px;">Acción Correctiva Recomendada</label>
						<textarea class="form-control form-control-sm" id="planAction" rows="2"></textarea>
					</div>
					<div class="row">
						<div class="col-6 form-group mb-2">
							<label class="font-weight-600 text-dark" style="font-size: 12px;">Responsable Asignado</label>
							<select class="form-control form-control-sm" id="planAssignee">
								<option value="Encargado de Pescadería">Encargado de Pescadería</option>
								<option value="Supervisor de Turno Bakery">Supervisor de Turno Bakery</option>
								<option value="Jefe de Operaciones Tienda">Jefe de Operaciones Tienda</option>
								<option value="Maestro Carnicero / Repositor">Maestro Carnicero / Repositor</option>
								<option value="Gerente de Turno">Gerente de Turno</option>
							</select>
						</div>
						<div class="col-6 form-group mb-2">
							<label class="font-weight-600 text-dark" style="font-size: 12px;">Prioridad</label>
							<select class="form-control form-control-sm" id="planPriority">
								<option value="Alta">Alta (P1 - Inmediato)</option>
								<option value="Media">Media (P2 - 7 días)</option>
								<option value="Baja">Baja (P3 - Rutina)</option>
							</select>
						</div>
					</div>
					<div class="form-group mb-3">
						<label class="font-weight-600 text-dark" style="font-size: 12px;">Fecha Límite de Ejecución</label>
						<input type="date" class="form-control form-control-sm" id="planDeadline" value="2026-11-15">
					</div>
					<button type="button" class="btn btn-sm btn-primary w-100 font-weight-600" onclick="savePlanAction();" style="background:#10b981; border-color:#10b981;">
						<i data-lucide="check-circle" style="width:13px;height:13px;margin-right:4px;"></i>Confirmar y Enviar a Centro de Alertas
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- 4. Modal: Metodología DRI -->
<div class="modal fade" id="modalDriMethodology" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
			<div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
				<div class="d-flex align-items-center gap-2">
					<i data-lucide="info" style="width:20px;height:20px;"></i>
					<h5 class="modal-title font-weight-bold mb-0" style="font-size: 16px;">Metodología del DRI (Índice de Rendimiento)</h5>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4 bg-white" style="font-size: 13px; line-height: 1.5; color: #334155;">
				<p>El <strong>Índice de Rendimiento Departamental (DRI)</strong> consolida el desempeño comercial y operativo en una escala normalizada de 0 a 100 mediante 5 pilares estratégicos:</p>
				<ul class="list-unstyled d-flex flex-column gap-2 mb-3">
					<li class="d-flex justify-content-between p-2 rounded bg-light border">
						<span><strong>Rentabilidad y Margen:</strong></span>
						<span class="badge badge-success px-2 py-1">30 %</span>
					</li>
					<li class="d-flex justify-content-between p-2 rounded bg-light border">
						<span><strong>Productividad Laboral ($/h):</strong></span>
						<span class="badge badge-primary px-2 py-1">25 %</span>
					</li>
					<li class="d-flex justify-content-between p-2 rounded bg-light border">
						<span><strong>Disponibilidad en Góndola:</strong></span>
						<span class="badge badge-info px-2 py-1">20 %</span>
					</li>
					<li class="d-flex justify-content-between p-2 rounded bg-light border">
						<span><strong>Control de Mermas y Descarte:</strong></span>
						<span class="badge badge-warning px-2 py-1">15 %</span>
					</li>
					<li class="d-flex justify-content-between p-2 rounded bg-light border">
						<span><strong>Cumplimiento de Estándares Operativos:</strong></span>
						<span class="badge badge-secondary px-2 py-1">10 %</span>
					</li>
				</ul>
				<p class="mb-0 text-muted" style="font-size: 11.5px;">Calificación: 90–100 Sobresaliente | 80–89 Bueno | 70–79 Atención | &lt;70 Crítico.</p>
			</div>
			<div class="modal-footer bg-light py-2 px-4 border-top">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- 5. Modal: Celda del Mapa de Calor -->
<div class="modal fade" id="modalHeatmapCell" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-light py-2 px-3 border-bottom">
				<h6 class="modal-title font-weight-bold text-dark mb-0" id="hmCellTitle" style="font-size: 13px;">Detalle de Métrica</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3 bg-white" style="font-size: 12px;">
				<div class="d-flex justify-content-between mb-1">
					<span class="text-muted">Valor Registrado:</span>
					<strong class="text-dark" id="hmCellVal">--</strong>
				</div>
				<div class="d-flex justify-content-between mb-1">
					<span class="text-muted">Meta del Período:</span>
					<strong class="text-dark" id="hmCellTarget">--</strong>
				</div>
				<div class="d-flex justify-content-between mb-2">
					<span class="text-muted">Desviación:</span>
					<span class="badge" id="hmCellDev">--</span>
				</div>
				<hr class="my-2">
				<div>
					<span class="text-muted d-block mb-1 font-weight-600">Causa / Diagnóstico:</span>
					<p class="mb-0 text-dark" id="hmCellCause" style="line-height: 1.4;"></p>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- 6. Modal: Auditoría de Reposición y Agotados -->
<div class="modal fade" id="modalReplenishAudit" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
			<div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
				<div class="d-flex align-items-center gap-2">
					<i data-lucide="package-search" style="width:20px;height:20px;"></i>
					<h5 class="modal-title font-weight-bold mb-0" id="repModalTitle" style="font-size: 16px;">Auditoría de Reposición y Quiebres de Stock</h5>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4 bg-white" id="repModalBody">
				<!-- Inyectado dinámicamente -->
			</div>
			<div class="modal-footer bg-light py-2 px-4 border-top">
				<a href="{{ $U('/inventory_stockouts') }}" class="btn btn-sm btn-outline-primary font-weight-600">Ir al Módulo de Agotados</a>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
