@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Prevención de Pérdidas y Seguridad · Centro de Control')

@push('pageStyles')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@endpush

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$s = $data['summary'] ?? [];
	$k = $data['kpis'] ?? [];
	$dev = $data['devices_summary'] ?? [];
	$cases = $data['cases'] ?? [];
	$alertsList = $data['security_alerts'] ?? [];
	$posData = $data['pos_anomalies'] ?? ['anomalies' => [], 'patterns' => []];
	$anomaliesList = $posData['anomalies'] ?? [];
	$patternsList = $posData['patterns'] ?? [];
	$cctvLogs = $data['cctv_logs'] ?? [];
	$auditEvents = $data['audit_events'] ?? [];
	$caseActions = $data['case_actions'] ?? [];
	$preventivePlans = $data['preventive_plans'] ?? [];
	$recoverySummary = $data['recovery_summary'] ?? ['total_recovered' => 0, 'entries' => []];
	$recoveryEntries = $recoverySummary['entries'] ?? [];
	$byBranch = $data['by_branch'] ?? [];
	$byProduct = $data['by_product'] ?? [];
	$byDept = $data['by_department'] ?? [];
	$trendData = $data['trend'] ?? [];

	$f = $filters ?? [];
	$branches = $branches ?? [];
	$departments = $departments ?? [];
	$usersList = $usersList ?? [];
	$productsList = $productsList ?? [];
	$devicesList = $devicesList ?? [];
	$selectedBranchId = $selectedBranchId ?? null;
	$canSupervise = $canSupervise ?? false;
	$canApprove = $canApprove ?? false;
	$canRevealImei = $canRevealImei ?? false;
	$chainOk = !empty($data['chain_ok']) && !empty($dev['chain_ok']);

	$ctxUrl = function(string $path) use ($U, $selectedBranchId) {
		$sep = (strpos($path, '?') !== false) ? '&' : '?';
		return $selectedBranchId ? $U($path . $sep . 'branch_id=' . $selectedBranchId) : $U($path);
	};

	// Conteos reales del ciclo de 7 etapas
	$stageAlertsCount = count($alertsList);
	$stageValidacionCount = 0;
	$stageInvestigacionCount = 0;
	$stageEvidenciaOkCount = 0;
	$stageResolucionCount = 0;
	$stageCierreCount = intval($k['closed_total'] ?? 0);
	foreach ($cases as $cr) {
		$ws = $cr['workflow_status'] ?? '';
		if (in_array($ws, ['detectado', 'reconteo'], true)) $stageValidacionCount++;
		if (in_array($ws, ['en_investigacion', 'in_progress'], true)) $stageInvestigacionCount++;
		if (intval($cr['evidence_count'] ?? 0) > 0) $stageEvidenciaOkCount++;
		if (in_array($ws, ['pendiente_aprobacion', 'pending_approval'], true)) $stageResolucionCount++;
	}
	$stageAccionCount = count($caseActions) + count($preventivePlans);

	$maxBranchVal = 1.0;
	foreach ($byBranch as $bb) {
		if (floatval($bb['value'] ?? 0) > $maxBranchVal) $maxBranchVal = floatval($bb['value']);
	}
	$deptTotalVal = array_sum(array_map(fn($d) => floatval($d['value'] ?? 0), $byDept));

	$prioLabels = ['urgent' => 'Urgente', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
	$statusClasses = [
		'detectado' => 'status-reconteo',
		'reconteo' => 'status-reconteo',
		'en_investigacion' => 'status-investigacion',
		'in_progress' => 'status-investigacion',
		'pendiente_aprobacion' => 'status-investigacion',
		'pending_approval' => 'status-investigacion',
		'resuelto' => 'status-resuelto',
		'no_resuelto' => 'status-resuelto',
	];

	// Labels y series reales para Chart.js
	$chartTrendLabels = array_map(fn($t) => $t['period'], $trendData);
	$chartTrendValues = array_map(fn($t) => round(floatval($t['value']), 2), $trendData);
	$chartProdLabels = array_map(fn($p) => mb_strimwidth((string)$p['name'], 0, 18, '…'), $byProduct);
	$chartProdValues = array_map(fn($p) => round(floatval($p['value']), 2), $byProduct);
	$chartDeptLabels = array_map(fn($d) => $d['name'], array_slice($byDept, 0, 6));
	$chartDeptValues = array_map(fn($d) => round(floatval($d['value']), 2), array_slice($byDept, 0, 6));
@endphp

@section('content')
<div class="lp-page-wrapper">

	<!-- Toast no bloqueante para retroalimentación auditada -->
	<div id="lpToastBox" class="alert alert-success shadow-sm d-none mb-2" role="alert" style="border-radius:10px; font-size:0.84rem;"></div>

	<!-- ====================================================================
	     1. HERO BANNER EJECUTIVO DE SEGURIDAD Y PREVENCIÓN
	     ==================================================================== -->
	<header class="lp-hero-card">
		<div class="lp-hero-left daily-head">
			<div class="dairy-page-icon is-primary"><i class="fa-solid fa-shield-halved"></i></div>
			<h1 class="lp-hero-title">Centro de Control de Prevención de Pérdidas</h1>
			<p class="lp-hero-subtitle">Alertas de Seguridad · Expedientes · Custodia IMEI y Activos · Anomalías POS · CCTV y Cierre Auditado</p>
		</div>

		<div class="lp-hero-banner-image" style="background-image: url('{{ $U('/img/control_center/daily_scan_phone.jpg') }}');"></div>

		<div class="lp-hero-stats-stack">
			<div class="lp-hero-stat-pill">
				<div class="lp-hero-stat-icon icon-green">
					<i class="fa-solid fa-microchip"></i>
				</div>
				<div class="lp-hero-stat-text">
					<span class="lp-hero-stat-label">Activos e IMEI en custodia</span>
					<span class="lp-hero-stat-value">{{ intval($dev['total'] ?? 0) }} eq. / {{ intval($k['monitored'] ?? 0) }} SKU</span>
				</div>
			</div>
			<div class="lp-hero-stat-pill">
				<div class="lp-hero-stat-icon icon-blue">
					<i class="fa-solid fa-bell"></i>
				</div>
				<div class="lp-hero-stat-text">
					<span class="lp-hero-stat-label">Alertas y anomalías</span>
					<span class="lp-hero-stat-value">{{ $stageAlertsCount }} al. · {{ count($anomaliesList) }} POS</span>
				</div>
			</div>
			<div class="lp-hero-stat-pill">
				<div class="lp-hero-stat-icon icon-green">
					<i class="fa-solid fa-hand-holding-dollar"></i>
				</div>
				<div class="lp-hero-stat-text">
					<span class="lp-hero-stat-label">Recuperado auditado</span>
					<span class="lp-hero-stat-value">{{ $money($k['recovered_total'] ?? 0) }} ({{ number_format(floatval($k['recovery_rate_pct'] ?? 0), 1) }}%)</span>
				</div>
			</div>
		</div>
	</header>

	<!-- ====================================================================
	     1B. CICLO OPERATIVO DE 7 ETAPAS
	     ==================================================================== -->
	<section class="lp-lifecycle-card" data-testid="lp-lifecycle-stepper" aria-label="Ciclo de prevención de pérdidas">
		<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
			<span class="font-weight-bold" style="font-size:0.84rem;">
				<i class="fa-solid fa-diagram-project text-success mr-1"></i>
				Ciclo: Alerta → Validación → Investigación → Evidencia → Resolución → Acción Correctiva → Cierre
			</span>
			<span class="badge {{ $chainOk ? 'badge-success' : 'badge-danger' }} px-2 py-1">
				<i class="fa-solid fa-link mr-1"></i>Cadena de Custodia SHA-256: {{ $chainOk ? 'Íntegra' : 'Revisar alerta' }}
			</span>
		</div>
		<div class="lp-lifecycle-steps">
			<div class="lp-lifecycle-step {{ $stageAlertsCount > 0 ? 'is-alert' : '' }}" onclick="switchLpTab('alertas')">
				<span class="lp-lifecycle-num">1</span>
				<div><div class="small font-weight-bold">Alerta</div><div class="text-muted" style="font-size:0.72rem;">{{ $stageAlertsCount }} activas</div></div>
			</div>
			<div class="lp-lifecycle-step" onclick="filterCasesTable('reconteo')">
				<span class="lp-lifecycle-num">2</span>
				<div><div class="small font-weight-bold">Validación</div><div class="text-muted" style="font-size:0.72rem;">{{ $stageValidacionCount }} por validar</div></div>
			</div>
			<div class="lp-lifecycle-step" onclick="filterCasesTable('investigacion')">
				<span class="lp-lifecycle-num">3</span>
				<div><div class="small font-weight-bold">Investigación</div><div class="text-muted" style="font-size:0.72rem;">{{ $stageInvestigacionCount }} activas</div></div>
			</div>
			<div class="lp-lifecycle-step" onclick="switchLpTab('accesos')">
				<span class="lp-lifecycle-num">4</span>
				<div><div class="small font-weight-bold">Evidencia</div><div class="text-muted" style="font-size:0.72rem;">{{ number_format(floatval($k['evidence_compliance_pct'] ?? 100), 0) }}% respaldo</div></div>
			</div>
			<div class="lp-lifecycle-step" onclick="switchLpTab('expedientes')">
				<span class="lp-lifecycle-num">5</span>
				<div><div class="small font-weight-bold">Resolución</div><div class="text-muted" style="font-size:0.72rem;">{{ $stageResolucionCount }} por aprobar</div></div>
			</div>
			<div class="lp-lifecycle-step" onclick="switchLpTab('acciones')">
				<span class="lp-lifecycle-num">6</span>
				<div><div class="small font-weight-bold">Acción Correctiva</div><div class="text-muted" style="font-size:0.72rem;">{{ $stageAccionCount }} planes/acc.</div></div>
			</div>
			<div class="lp-lifecycle-step" onclick="filterCasesTable('closed')">
				<span class="lp-lifecycle-num">7</span>
				<div><div class="small font-weight-bold">Cierre</div><div class="text-muted" style="font-size:0.72rem;">{{ $stageCierreCount }} documentados</div></div>
			</div>
		</div>
	</section>

	<!-- ====================================================================
	     2. BARRA DE ACCIONES PRINCIPALES (SIN MERMAS/HORARIOS/DESPACHOS)
	     ==================================================================== -->
	<div class="lp-action-bar">
		<button type="button" class="lp-btn-action-primary" onclick="openNewIncidentModal()">
			<i class="fa-solid fa-bolt"></i> Registrar Incidente
		</button>
		<button type="button" class="lp-btn-action-outline" onclick="switchLpTab('alertas')">
			<i class="fa-solid fa-bell text-danger"></i> Alertas de Seguridad
		</button>
		<a href="{{ $ctxUrl('/loss-prevention/devices') }}" class="lp-btn-action-outline">
			<i class="fa-solid fa-mobile-screen-button text-primary"></i> Control IMEI y Serial
		</a>
		<a href="{{ $ctxUrl('/merchandise') }}" class="lp-btn-action-outline">
			<i class="fa-solid fa-boxes-stacked text-success"></i> Control de Activos
		</a>
		<a href="{{ $ctxUrl('/loss-prevention/counts') }}" class="lp-btn-action-outline">
			<i class="fa-solid fa-barcode"></i> Conteos Sensibles
		</a>
		<button type="button" class="lp-btn-action-outline" onclick="openCctvModal()">
			<i class="fa-solid fa-video text-info"></i> Consulta CCTV Auditada
		</button>
		<div class="dropdown d-inline-block">
			<button type="button" class="lp-btn-dots" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Herramientas de Prevención de Pérdidas">
				<i class="fa-solid fa-ellipsis"></i>
			</button>
			<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.81rem;">
				<a class="dropdown-item py-2" href="{{ $ctxUrl('/alerts') }}"><i class="fa-solid fa-triangle-exclamation mr-2 text-danger"></i>Centro Global de Alertas</a>
				<a class="dropdown-item py-2" href="{{ $ctxUrl('/audits') }}"><i class="fa-solid fa-clipboard-check mr-2 text-success"></i>Auditorías de Seguridad</a>
				<a class="dropdown-item py-2" href="{{ $ctxUrl('/reports/loss-executive') }}"><i class="fa-solid fa-file-invoice-dollar mr-2 text-primary"></i>Reporte Ejecutivo de Pérdidas</a>
				<a class="dropdown-item py-2" href="#" onclick="switchLpTab('accesos'); return false;"><i class="fa-solid fa-user-lock mr-2 text-warning"></i>Accesos, Roles y Auditoría CCTV</a>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     3. BARRA DE FILTROS SUPERIOR SINCRONIZADA
	     ==================================================================== -->
	<form method="GET" action="{{ $U('/loss-prevention') }}" id="lp-filters-form" class="lp-filters-bar">
		<div class="d-flex align-items-center text-muted mr-1" style="font-size:0.85rem;">
			<i class="fa-solid fa-filter"></i>
		</div>

		<div class="lp-filter-pill">
			<select name="branch_id" onchange="this.form.submit()">
				<option value="">Todas las sucursales</option>
				@foreach($branches as $b)
					<option value="{{ $b->id }}" @if((int)$selectedBranchId === (int)$b->id) selected @endif>{{ $b->name }}</option>
				@endforeach
			</select>
		</div>

		<div class="lp-filter-pill">
			<select name="department" onchange="this.form.submit()">
				<option value="">Todos los deptos</option>
				@foreach($departments as $dept)
					<option value="{{ $dept }}" @if(($f['department'] ?? '') === $dept) selected @endif>{{ $dept }}</option>
				@endforeach
			</select>
		</div>

		<div class="lp-filter-pill">
			<i class="fa-regular fa-calendar text-muted" style="font-size:0.75rem;"></i>
			<select name="period" onchange="this.form.submit()">
				<option value="all" @if(($f['period'] ?? 'all') === 'all') selected @endif>Todo el historial</option>
				<option value="today" @if(($f['period'] ?? '') === 'today') selected @endif>Hoy</option>
				<option value="7days" @if(($f['period'] ?? '') === '7days') selected @endif>Últimos 7 días</option>
				<option value="30days" @if(($f['period'] ?? '') === '30days') selected @endif>Últimos 30 días</option>
				<option value="90days" @if(($f['period'] ?? '') === '90days') selected @endif>Últimos 90 días</option>
			</select>
		</div>

		<div class="lp-filter-pill">
			<select name="assigned_user_id" onchange="this.form.submit()">
				<option value="">Responsable (Todos)</option>
				@foreach($usersList as $u)
					<option value="{{ $u['id'] }}" @if(intval($f['assigned_user_id'] ?? 0) === intval($u['id'])) selected @endif>{{ $u['username'] }}</option>
				@endforeach
			</select>
		</div>

		<div class="lp-filter-pill">
			<select name="status" onchange="this.form.submit()">
				<option value="">Todos los estados</option>
				@foreach($statuses as $stCode => $stLabel)
					<option value="{{ $stCode }}" @if(($f['status'] ?? '') === $stCode) selected @endif>{{ $stLabel }}</option>
				@endforeach
			</select>
		</div>
	</form>

	<!-- ====================================================================
	     4. 8 TARJETAS KPI EJECUTIVAS (100% BASE DE DATOS REAL)
	     ==================================================================== -->
	<section class="lp-kpis-grid-8" data-testid="lp-kpis-8" aria-label="Indicadores ejecutivos de seguridad">
		<!-- 1. Alertas de Seguridad -->
		<a href="#alertas" class="lp-kpi-card" onclick="switchLpTab('alertas'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-red"><i class="fa-solid fa-bell"></i></div>
				<span class="lp-kpi-title">1. Alertas Seguridad</span>
			</div>
			<div class="lp-kpi-value">{{ $stageAlertsCount }}</div>
			<span class="lp-kpi-delta {{ intval($k['critical_alerts'] ?? 0) > 0 ? 'delta-red' : 'delta-green' }}">
				{{ intval($k['critical_alerts'] ?? 0) }} casos críticos
			</span>
		</a>

		<!-- 2. Custodia IMEI / Activos -->
		<a href="#custodia-imei" class="lp-kpi-card" onclick="switchLpTab('custodia-imei'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green"><i class="fa-solid fa-mobile-screen"></i></div>
				<span class="lp-kpi-title">2. Custodia IMEI</span>
			</div>
			<div class="lp-kpi-value">{{ intval($dev['active_custody'] ?? 0) }} / {{ intval($dev['total'] ?? 0) }}</div>
			<span class="lp-kpi-delta delta-green">
				{{ intval($dev['blocked'] ?? 0) }} bloq. · {{ intval($dev['pending_holds'] ?? 0) }} ret.
			</span>
		</a>

		<!-- 3. Expedientes Abiertos -->
		<a href="#expedientes" class="lp-kpi-card" onclick="filterCasesTable('open'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-amber"><i class="fa-solid fa-folder-open"></i></div>
				<span class="lp-kpi-title">3. Exp. Abiertos</span>
			</div>
			<div class="lp-kpi-value">{{ intval($k['active_incidents'] ?? 0) }}</div>
			<span class="lp-kpi-delta delta-red">
				Riesgo: {{ $money($k['value_at_risk'] ?? 0) }}
			</span>
		</a>

		<!-- 4. Investigaciones Sin Cierre / Responsable -->
		<a href="#expedientes" class="lp-kpi-card" onclick="filterCasesTable('investigacion'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-red"><i class="fa-solid fa-hourglass-half"></i></div>
				<span class="lp-kpi-title">4. Sin Cierre / Venc.</span>
			</div>
			<div class="lp-kpi-value">{{ intval($k['overdue_cases'] ?? 0) }}</div>
			<span class="lp-kpi-delta {{ intval($k['unassigned_cases'] ?? 0) === 0 ? 'delta-green' : 'delta-red' }}">
				{{ intval($k['unassigned_cases'] ?? 0) }} sin responsable
			</span>
		</a>

		<!-- 5. Anomalías POS y Patrones -->
		<a href="#anomalias" class="lp-kpi-card" onclick="switchLpTab('anomalias'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-amber"><i class="fa-solid fa-cash-register"></i></div>
				<span class="lp-kpi-title">5. Anomalías POS</span>
			</div>
			<div class="lp-kpi-value">{{ count($anomaliesList) }}</div>
			<span class="lp-kpi-delta delta-green">
				{{ count($patternsList) }} patrones sospechosos
			</span>
		</a>

		<!-- 6. Cumplimiento de Evidencia -->
		<a href="#accesos" class="lp-kpi-card" onclick="switchLpTab('accesos'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green"><i class="fa-solid fa-camera"></i></div>
				<span class="lp-kpi-title">6. Evidencia Auditada</span>
			</div>
			<div class="lp-kpi-value">{{ number_format(floatval($k['evidence_compliance_pct'] ?? 100), 1) }}%</div>
			<span class="lp-kpi-delta delta-green">
				{{ count($cctvLogs) }} accesos CCTV log.
			</span>
		</a>

		<!-- 7. Recuperación Financiera -->
		<a href="#acciones" class="lp-kpi-card" onclick="switchLpTab('acciones'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green"><i class="fa-solid fa-hand-holding-dollar"></i></div>
				<span class="lp-kpi-title">7. Recuperado Real</span>
			</div>
			<div class="lp-kpi-value">{{ $money($k['recovered_total'] ?? 0) }}</div>
			<span class="lp-kpi-delta delta-green">
				{{ count($recoveryEntries) }} folios sin duplicar
			</span>
		</a>

		<!-- 8. Cierres Documentados y Cadena -->
		<a href="#auditoria" class="lp-kpi-card" onclick="switchLpTab('auditoria'); return false;">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green"><i class="fa-solid fa-file-shield"></i></div>
				<span class="lp-kpi-title">8. Cierres / Cadena</span>
			</div>
			<div class="lp-kpi-value">{{ $stageCierreCount }} cerr.</div>
			<span class="lp-kpi-delta {{ $chainOk ? 'delta-green' : 'delta-red' }}">
				{{ $chainOk ? 'SHA-256 Íntegra' : 'Integridad por revisar' }}
			</span>
		</a>
	</section>

	<!-- ====================================================================
	     5. PESTAÑAS SEGMENTADAS
	     ==================================================================== -->
	<nav class="lp-tabs-bar" aria-label="Secciones de Prevención de Pérdidas">
		<button type="button" class="lp-tab-item active" id="tab-btn-expedientes" onclick="switchLpTab('expedientes')">
			<i class="fa-regular fa-clipboard"></i> Expedientes y Seguimiento ({{ count($cases) }})
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-alertas" onclick="switchLpTab('alertas')">
			<i class="fa-solid fa-bell text-danger"></i> Alertas Seguridad ({{ $stageAlertsCount }})
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-anomalias" onclick="switchLpTab('anomalias')">
			<i class="fa-solid fa-triangle-exclamation text-warning"></i> Anomalías POS y Patrones ({{ count($anomaliesList) }})
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-custodia-imei" onclick="switchLpTab('custodia-imei')">
			<i class="fa-solid fa-mobile-screen-button text-primary"></i> Custodia IMEI / Activos ({{ intval($dev['total'] ?? 0) }})
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-accesos" onclick="switchLpTab('accesos')">
			<i class="fa-solid fa-user-shield text-info"></i> Accesos, CCTV y Evidencias ({{ count($cctvLogs) }})
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-acciones" onclick="switchLpTab('acciones')">
			<i class="fa-solid fa-list-check text-success"></i> Planes, Recuperación y Cierre ({{ $stageAccionCount }})
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-auditoria" onclick="switchLpTab('auditoria')">
			<i class="fa-solid fa-link"></i> Auditoría y Sucursales
		</button>
	</nav>

	<!-- ====================================================================
	     6. PANE 1: EXPEDIENTES Y ANALÍTICA REAL
	     ==================================================================== -->
	<div class="lp-tab-pane" id="pane-expedientes">
		<div class="lp-table-card">
			<div class="lp-table-header">
				<div class="lp-table-title-group">
					<h2>Expedientes de Investigación e Incidentes</h2>
					<p>Diferencias nacen «Sin clasificar» · Toda resolución exige responsable y evidencia documentada</p>
				</div>
				<button type="button" class="lp-btn-new-incident" onclick="openNewIncidentModal()">
					<i class="fa-solid fa-plus"></i> Nuevo Incidente
				</button>
			</div>

			<div class="table-responsive">
				<table class="lp-cases-table">
					<thead>
						<tr>
							<th>Caso / Código</th>
							<th>Producto / Activo</th>
							<th>Sucursal</th>
							<th>Zona / Depto</th>
							<th>Diferencia</th>
							<th>Valor en Riesgo</th>
							<th>Evidencias</th>
							<th>Prioridad</th>
							<th>Estado</th>
							<th>Responsable</th>
							<th style="text-align:right;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@forelse($cases as $c)
							@php
								$ws = $c['workflow_status'] ?? 'reconteo';
								$isClosed = in_array($ws, ['resuelto', 'no_resuelto', 'closed', 'resolved'], true);
								$evCnt = intval($c['evidence_count'] ?? 0);
								$prioText = $prioLabels[$c['priority'] ?? 'medium'] ?? 'Media';
								$stClass = $statusClasses[$ws] ?? 'status-investigacion';
								$thumb = !empty($c['device_id']) ? '/img/control_center/prod_galaxy_s24.svg' : '/img/control_center/depto_electronica.jpg';
								$drawerPayload = [
									'id' => intval($c['id']),
									'case_code' => $c['case_code'],
									'product_name' => $c['product_name'],
									'branch_name' => $c['branch_name'] ?? '—',
									'branch_id' => intval($c['branch_id'] ?? 1),
									'zone' => ($c['zone'] ?: 'Bodega') . ' · ' . ($c['department'] ?: 'General'),
									'difference' => floatval($c['difference_qty']),
									'value_at_risk' => floatval($c['financial_impact']),
									'responsible' => $c['assigned_name'] ?: 'Sin asignar',
									'evidence_count' => $evCnt,
									'workflow_status' => $ws,
								];
							@endphp
							<tr id="case-row-{{ $c['id'] }}" data-closed="{{ $isClosed ? '1' : '0' }}" data-status="{{ $ws }}">
								<td class="lp-case-code">
									<a href="{{ $U('/loss-prevention/case/' . $c['id']) }}" class="font-weight-bold" style="color:#0f172a; text-decoration:none;">
										{{ $c['case_code'] }} <i class="fa-solid fa-arrow-up-right-from-square ml-1" style="font-size:0.6875rem; color:#64748b;"></i>
									</a>
								</td>
								<td>
									<div class="lp-product-cell">
										<img src="{{ $U($thumb) }}" class="lp-product-thumb" alt="" onerror="this.src='{{ $U('/img/control_center/prod_galaxy_s24.svg') }}'">
										<span class="lp-product-name">{{ $c['product_name'] }}</span>
									</div>
								</td>
								<td>{{ $c['branch_name'] ?? '—' }}</td>
								<td>{{ $c['zone'] ?: 'Bodega' }} · {{ $c['department'] ?: 'General' }}</td>
								<td class="lp-diff-red">{{ floatval($c['difference_qty']) }}</td>
								<td class="lp-value-red">{{ $money($c['financial_impact']) }}</td>
								<td>
									<span class="badge {{ $evCnt > 0 ? 'badge-success' : 'badge-warning text-dark' }} px-2 py-1">
										<i class="fa-solid {{ $evCnt > 0 ? 'fa-file-shield' : 'fa-triangle-exclamation' }} mr-1"></i>{{ $evCnt }}
									</span>
								</td>
								<td><span class="lp-badge-priority">{{ $prioText }}</span></td>
								<td><span class="lp-badge-status {{ $stClass }}">{{ $c['status_label'] ?? $ws }}</span></td>
								<td>{{ $c['assigned_name'] ?: 'Sin asignar' }}</td>
								<td>
									<div class="lp-action-cell">
										<button type="button" class="lp-btn-resolve-table" onclick="openResolveDrawer({{ json_encode($drawerPayload) }})">
											{{ $isClosed ? 'Ver Cierre' : 'Gestionar' }}
										</button>
										<a href="{{ $U('/loss-prevention/case/' . $c['id']) }}" class="lp-btn-dots-row" title="Abrir expediente completo 360°">
											<i class="fa-solid fa-ellipsis-vertical"></i>
										</a>
									</div>
								</td>
							</tr>
						@empty
							<tr><td colspan="11" class="text-center text-muted py-4">Sin expedientes que coincidan con los filtros seleccionados.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- 4 TARJETAS DE ANALÍTICA REAL -->
		<div class="lp-analytics-grid-4">
			<!-- Tendencia -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Tendencia de diferencias</h3>
						<p>Valor detectado en conteos sensibles (últimos períodos)</p>
					</div>
					<span class="lp-badge-delta-red">{{ count($trendData) }} pts</span>
				</div>
				<div class="lp-chart-box-220">
					<canvas id="lp-trend-chart"></canvas>
				</div>
			</div>

			<!-- Productos con mayor exposición -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Activos con mayor exposición</h3>
						<p>Valor acumulado en expedientes (USD)</p>
					</div>
				</div>
				<div class="lp-chart-box-220">
					<canvas id="lp-products-chart"></canvas>
				</div>
			</div>

			<!-- Sucursales con mayor exposición -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Sucursales con mayor exposición</h3>
						<p>Casos abiertos por tienda (BD real)</p>
					</div>
				</div>
				<div class="lp-branch-bars-stack">
					@forelse(array_slice($byBranch, 0, 5) as $idx => $bb)
						@php
							$pctW = $maxBranchVal > 0 ? min(100, round(floatval($bb['value']) / $maxBranchVal * 100)) : 0;
							$barCls = $idx === 0 ? 'bar-red' : ($idx <= 2 ? 'bar-yellow' : 'bar-green');
						@endphp
						<div class="lp-branch-bar-row">
							<span class="lp-branch-bar-name">{{ $bb['name'] }}</span>
							<div class="lp-branch-bar-track"><div class="lp-branch-bar-fill {{ $barCls }}" style="width: {{ $pctW }}%;"></div></div>
							<span class="lp-branch-bar-val">{{ $money($bb['value']) }}</span>
						</div>
					@empty
						<p class="text-muted small mb-0">Sin sucursales con expedientes abiertos.</p>
					@endforelse
				</div>
			</div>

			<!-- Exposición por departamento -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Exposición por departamento</h3>
						<p>Participación real sobre casos abiertos</p>
					</div>
				</div>
				<div class="lp-donut-split">
					<div class="lp-donut-canvas-wrap">
						<canvas id="lp-department-chart"></canvas>
						<div class="lp-donut-center-info">
							<div class="lp-donut-center-num">{{ $money($deptTotalVal) }}</div>
							<div class="lp-donut-center-label">Total abierto</div>
						</div>
					</div>
					<div class="lp-donut-legend-stack">
						@php $dotPalette = ['dot-red', 'dot-yellow', 'dot-green', 'dot-red', 'dot-yellow', 'dot-green']; @endphp
						@forelse(array_slice($byDept, 0, 4) as $di => $drow)
							@php $dp = $deptTotalVal > 0 ? round(floatval($drow['value']) / $deptTotalVal * 100, 1) : 0; @endphp
							<div class="lp-donut-legend-item">
								<div class="lp-donut-legend-left">
									<span class="lp-legend-dot {{ $dotPalette[$di] ?? 'dot-green' }}"></span>
									<span>{{ $drow['name'] }}</span>
								</div>
								<span class="lp-donut-legend-pct">{{ $dp }}%</span>
							</div>
						@empty
							<div class="text-muted small">Sin exposición abierta</div>
						@endforelse
					</div>
				</div>
			</div>
		</div>

		<!-- Acciones rápidas inferiores -->
		<section class="lp-quick-actions-section" aria-label="Acciones rápidas de seguridad">
			<div class="lp-quick-header"><h3><i class="fa-solid fa-bolt text-success"></i> Acciones rápidas</h3></div>
			<div class="lp-quick-cards-grid">
				<a href="{{ $ctxUrl('/loss-prevention/devices') }}" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
					<span class="lp-quick-tile-label">Control de Celulares</span>
				</a>
				<a href="{{ $ctxUrl('/merchandise') }}" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
					<span class="lp-quick-tile-label">Control de Activos</span>
				</a>
				<a href="#" onclick="openCctvModal(); return false;" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-video"></i></div>
					<span class="lp-quick-tile-label">CCTV Auditado</span>
				</a>
				<a href="#" onclick="switchLpTab('anomalias'); return false;" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-cash-register"></i></div>
					<span class="lp-quick-tile-label">Anomalías POS</span>
				</a>
				<a href="#" onclick="switchLpTab('acciones'); return false;" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-bullseye"></i></div>
					<span class="lp-quick-tile-label">Planes y Recuperación</span>
				</a>
				<a href="{{ $ctxUrl('/reports/loss-executive') }}" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-chart-column"></i></div>
					<span class="lp-quick-tile-label">Reporte Ejecutivo</span>
				</a>
			</div>
		</section>
	</div>

	<!-- ====================================================================
	     PANE 2: CENTRO DE ALERTAS DE SEGURIDAD
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-alertas">
		<div class="lp-table-card p-4">
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-bell text-danger mr-2"></i>Centro de Alertas de Seguridad e Irregularidades</h3>
					<p class="text-muted small m-0">Alertas activas generadas por stock fantasma, conteos vencidos, diferencias sensibles y conciliación POS (sin presunción automática de robo).</p>
				</div>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-sm btn-danger font-weight-bold" onclick="runSecurityAlertScan()">
						<i class="fa-solid fa-radar mr-1"></i> Ejecutar Barrido de Seguridad
					</button>
					<a href="{{ $ctxUrl('/alerts') }}" class="btn btn-sm btn-outline-secondary">Ver Centro Completo</a>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm border mb-0">
					<thead class="thead-light">
						<tr>
							<th>Regla</th>
							<th>Alerta</th>
							<th>Sucursal</th>
							<th>Severidad</th>
							<th class="text-right">Impacto</th>
							<th>Estado</th>
							<th>Fecha</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						@forelse($alertsList as $al)
							<tr>
								<td><span class="badge badge-light border">{{ $al['rule_code'] }}</span></td>
								<td>
									<strong>{{ $al['title'] }}</strong><br>
									<small class="text-muted">{{ mb_strimwidth((string)($al['detail'] ?? ''), 0, 110, '…') }}</small>
								</td>
								<td>{{ $al['branch_name'] ?? 'Red' }}</td>
								<td><span class="badge {{ $al['severity'] === 'danger' ? 'badge-danger' : 'badge-warning text-dark' }}">{{ strtoupper($al['severity']) }}</span></td>
								<td class="text-right font-weight-bold">{{ $money($al['value'] ?? 0) }}</td>
								<td><span class="badge badge-info">{{ $al['status'] }}</span></td>
								<td class="small text-muted">{{ substr((string)$al['created_at'], 0, 16) }}</td>
								<td class="text-center">
									@if(!empty($al['href']))
										<a href="{{ $U($al['href']) }}" class="btn btn-xs btn-outline-primary">Validar</a>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="8" class="text-center text-muted py-4">Sin alertas de seguridad activas en el filtro seleccionado.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANE 3: ANOMALÍAS POS Y PATRONES SOSPECHOSOS (BD REAL)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-anomalias">
		<div class="lp-table-card p-4">
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-triangle-exclamation text-warning mr-2"></i>Anomalías POS y Detección de Patrones Sospechosos</h3>
					<p class="text-muted small m-0">Descuentos manuales elevados, reembolsos/devoluciones POS, autorizaciones manuales y reincidencias por SKU o cajero.</p>
				</div>
				<button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" onclick="openNewIncidentModal()">
					<i class="fa-solid fa-plus mr-1"></i> Escalar a Expediente
				</button>
			</div>

			@if(!empty($patternsList))
				<div class="alert alert-warning border mb-3" style="border-radius:10px;">
					<strong class="d-block mb-1"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i>Patrones Sospechosos Detectados ({{ count($patternsList) }})</strong>
					<div class="row">
						@foreach($patternsList as $pt)
							<div class="col-md-4 mb-2">
								<div class="p-2 bg-white border rounded small">
									<div class="font-weight-bold text-dark">{{ $pt['pattern'] }}</div>
									<div>{{ $pt['entity'] }} · <strong>{{ $pt['count'] }} eventos</strong> ({{ $money($pt['impact']) }})</div>
									<div class="text-muted" style="font-size:0.74rem;">{{ $pt['recommendation'] }}</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			@endif

			<div class="table-responsive">
				<table class="table table-hover table-sm border mb-0">
					<thead class="thead-light">
						<tr>
							<th>Tipo</th>
							<th>Documento</th>
							<th>Sucursal</th>
							<th>Detalle de Anomalía</th>
							<th class="text-right">Monto</th>
							<th>Riesgo</th>
							<th>Fecha</th>
						</tr>
					</thead>
					<tbody>
						@forelse($anomaliesList as $an)
							<tr>
								<td><span class="badge {{ $an['severity'] === 'danger' ? 'badge-danger' : 'badge-warning text-dark' }}">{{ $an['type'] }}</span></td>
								<td><strong>{{ $an['document'] }}</strong></td>
								<td>{{ $an['branch_name'] }}</td>
								<td>{{ $an['detail'] }}</td>
								<td class="text-right font-weight-bold">{{ $money($an['amount']) }}</td>
								<td><span class="badge {{ $an['severity'] === 'danger' ? 'badge-danger' : 'badge-warning text-dark' }}">{{ $an['severity'] === 'danger' ? 'CRÍTICO' : 'REVISAR' }}</span></td>
								<td class="small text-muted">{{ substr((string)$an['at'], 0, 16) }}</td>
							</tr>
						@empty
							<tr><td colspan="7" class="text-center text-muted py-4">Sin anomalías POS registradas en el período.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANE 4: CONTROL DE DISPOSITIVOS (IMEI/SERIAL) Y ACTIVOS
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-custodia-imei">
		<div class="lp-table-card p-4">
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-mobile-screen-button text-primary mr-2"></i>Control de Dispositivos (IMEI / Serial) y Cadena de Custodia</h3>
					<p class="text-muted small m-0">Identificadores enmascarados por defecto · Revelación y trazabilidad auditadas con cadena SHA-256.</p>
				</div>
				<div class="d-flex gap-2">
					<a href="{{ $ctxUrl('/loss-prevention/devices') }}" class="btn btn-sm btn-primary font-weight-bold">Consola Completa de Celulares</a>
					<a href="{{ $ctxUrl('/merchandise') }}" class="btn btn-sm btn-outline-success font-weight-bold">Control de Activos</a>
				</div>
			</div>

			<div class="p-3 bg-light border rounded mb-3">
				<label class="small font-weight-bold text-dark mb-1">Búsqueda en vivo por IMEI completo o Número de Serie:</label>
				<div class="input-group">
					<input type="text" class="form-control font-weight-bold" id="txtImeiSearch" placeholder="Escanee o escriba IMEI / serial del equipo...">
					<div class="input-group-append">
						<button class="btn btn-primary font-weight-bold" type="button" id="btnLookupImei" onclick="lookupDeviceReal()">
							<i class="fa-solid fa-magnifying-glass mr-1"></i> Consultar Custodia Real
						</button>
					</div>
				</div>
				<div id="imeiLookupResult" class="mt-2 d-none"></div>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border mb-0">
					<thead class="thead-light">
						<tr>
							<th>Producto</th>
							<th>IMEI 1 (Enmascarado)</th>
							<th>Serial</th>
							<th>Sucursal</th>
							<th>Zona / Estado</th>
							<th class="text-right">Costo</th>
							<th class="text-center">Custodia / Revelar</th>
						</tr>
					</thead>
					<tbody>
						@forelse($devicesList as $dv)
							<tr>
								<td><strong>{{ $dv['product_name'] ?? 'Dispositivo' }}</strong></td>
								<td><code>{{ $dv['imei1_masked'] ?? '—' }}</code></td>
								<td><code>{{ $dv['serial_masked'] ?? '—' }}</code></td>
								<td>{{ $dv['branch_name'] ?? '—' }}</td>
								<td><span class="badge badge-light border">{{ $dv['zone'] ?? 'vault' }} · {{ $dv['state'] ?? 'available' }}</span></td>
								<td class="text-right">{{ $money($dv['unit_cost'] ?? 0) }}</td>
								<td class="text-center">
									@if($canRevealImei)
										<button type="button" class="btn btn-xs btn-outline-primary font-weight-bold" onclick="revealImeiAudited({{ intval($dv['id']) }})">
											<i class="fa-solid fa-eye mr-1"></i> Revelar (Auditado)
										</button>
									@else
										<span class="badge badge-secondary">Sin permiso LOSS_PREVENTION</span>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="7" class="text-center text-muted py-4">No hay dispositivos registrados.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANE 5: ACCESOS, AUTORIZACIONES, EVIDENCIAS Y AUDITORÍA CCTV
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-accesos">
		<div class="lp-table-card p-4">
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-user-lock text-info mr-2"></i>Accesos, Autorizaciones Sensibles y Auditoría CCTV</h3>
					<p class="text-muted small m-0">Control estricto de permisos para consultar CCTV, revelar IMEI, aprobar ajustes y cerrar expedientes (sin horarios operativos).</p>
				</div>
				<button type="button" class="btn btn-sm btn-info font-weight-bold" onclick="openCctvModal()">
					<i class="fa-solid fa-video mr-1"></i> Registrar Consulta CCTV
				</button>
			</div>

			<div class="row mb-4">
				<div class="col-lg-6 mb-3">
					<h6 class="font-weight-bold mb-2">Matriz de Responsables y Autorizaciones de Seguridad</h6>
					<div class="table-responsive">
						<table class="table table-sm border mb-0">
							<thead class="thead-light">
								<tr><th>Usuario</th><th>Permisos Activos de Prevención / Aprobación</th></tr>
							</thead>
							<tbody>
								@foreach($usersList as $u)
									@php
										$pList = array_filter(explode(',', (string)($u['perms'] ?? '')));
										$secPerms = array_intersect($pList, ['ADMIN', 'LOSS_PREVENTION', 'INVENTORY_ADJUST_APPROVE', 'ALERTS_SUPERVISE', 'SALES_SUPERVISE']);
									@endphp
									@if(!empty($secPerms))
									<tr>
										<td><strong>{{ $u['username'] }}</strong></td>
										<td>
											@foreach($secPerms as $sp)
												<span class="badge badge-light border text-dark mr-1">{{ $sp }}</span>
											@endforeach
										</td>
									</tr>
									@endif
								@endforeach
							</tbody>
						</table>
					</div>
				</div>

				<div class="col-lg-6 mb-3">
					<h6 class="font-weight-bold mb-2">Bitácora Estricta de Accesos a CCTV, Evidencias e IMEI</h6>
					<div class="table-responsive">
						<table class="table table-sm border mb-0">
							<thead class="thead-light">
								<tr><th>Fecha</th><th>Usuario</th><th>Tipo</th><th>Recurso / Autorizó</th></tr>
							</thead>
							<tbody>
								@forelse(array_slice($cctvLogs, 0, 15) as $cl)
									<tr>
										<td class="small text-muted">{{ substr((string)$cl['created_at'], 0, 16) }}</td>
										<td><strong>{{ $cl['username'] ?? ('U#' . $cl['user_id']) }}</strong></td>
										<td><span class="badge badge-info">{{ $cl['access_type'] }}</span></td>
										<td class="small">{{ $cl['resource_ref'] ?: '—' }} @if(!empty($cl['authorized_by']))<span class="text-muted">({{ $cl['authorized_by'] }})</span>@endif</td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center text-muted py-3">Sin consultas CCTV/IMEI registradas aún.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANE 6: PLANES CORRECTIVOS, RECUPERACIÓN FINANCIERA Y CIERRE
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-acciones">
		<div class="lp-table-card p-4">
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-list-check text-success mr-2"></i>Planes Correctivos y Recuperación Financiera Auditada</h3>
					<p class="text-muted small m-0">Seguimiento de acciones correctivas, planes preventivos y recuperaciones sin duplicidad de folios.</p>
				</div>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="openRecoveryModal()">
					<i class="fa-solid fa-hand-holding-dollar mr-1"></i> Registrar Recuperación Financiera
				</button>
			</div>

			<div class="row">
				<div class="col-lg-6 mb-3">
					<h6 class="font-weight-bold mb-2">Recuperaciones Financieras por Expediente ({{ $money($recoverySummary['total_recovered'] ?? 0) }})</h6>
					<div class="table-responsive">
						<table class="table table-sm border mb-0">
							<thead class="thead-light">
								<tr><th>Caso</th><th>Folios / Soporte</th><th class="text-right">Exposición</th><th class="text-right">Recuperado</th></tr>
							</thead>
							<tbody>
								@forelse($recoveryEntries as $re)
									<tr>
										<td><a href="{{ $U('/loss-prevention/case/' . $re['case_id']) }}"><strong>{{ $re['case_code'] }}</strong></a></td>
										<td class="small">{{ implode(', ', $re['document_refs']) ?: ($re['notes'] ?: '—') }}</td>
										<td class="text-right">{{ $money($re['impact']) }}</td>
										<td class="text-right font-weight-bold text-success">{{ $money($re['total_recovered']) }}</td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center text-muted py-3">Sin recuperaciones financieras registradas todavía.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>

				<div class="col-lg-6 mb-3">
					<h6 class="font-weight-bold mb-2">Planes Preventivos Activos ({{ count($preventivePlans) }})</h6>
					<div class="table-responsive">
						<table class="table table-sm border mb-0">
							<thead class="thead-light">
								<tr><th>Título del Plan</th><th>Tipo</th><th>Meta / Frec.</th><th>Estado</th></tr>
							</thead>
							<tbody>
								@forelse($preventivePlans as $pp)
									<tr>
										<td><strong>{{ $pp['title'] ?? 'Plan preventivo' }}</strong></td>
										<td class="small">{{ $pp['plan_type'] ?? 'control' }}</td>
										<td class="small">{{ intval($pp['frequency_days'] ?? 1) }} d.</td>
										<td><span class="badge badge-success">{{ $pp['status'] ?? 'active' }}</span></td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center text-muted py-3">Sin planes preventivos registrados.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANE 7: AUDITORÍA DE ACCIONES Y SUCURSALES
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-auditoria">
		<div class="lp-table-card p-4">
			<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-link text-primary mr-2"></i>Bitácora Encadenada SHA-256 y Exposición por Sucursal</h3>
					<p class="text-muted small m-0">Registro inalterable de cambios de estado, asignaciones, evidencias y cierres documentados.</p>
				</div>
				<span class="badge {{ $chainOk ? 'badge-success' : 'badge-danger' }} px-3 py-2">
					Integridad Criptográfica: {{ $chainOk ? 'VERIFICADA (0 alteraciones)' : 'REVISAR CADENA' }}
				</span>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border mb-0">
					<thead class="thead-light">
						<tr><th>Fecha</th><th>Expediente</th><th>Acción</th><th>Transición</th><th>Usuario</th><th>Detalle</th></tr>
					</thead>
					<tbody>
						@forelse(array_slice($auditEvents, 0, 30) as $ae)
							<tr>
								<td class="small text-muted">{{ substr((string)$ae['created_at'], 0, 16) }}</td>
								<td><strong>{{ $ae['case_code'] }}</strong></td>
								<td><span class="badge badge-light border">{{ $ae['action'] }}</span></td>
								<td class="small">{{ $ae['from_status'] ?: '—' }} → {{ $ae['to_status'] ?: '—' }}</td>
								<td>{{ $ae['username'] ?? ('U#' . $ae['user_id']) }}</td>
								<td class="small">{{ $ae['note'] ?: '—' }}</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-3">Sin eventos de auditoría.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     9. SLIDE-OVER DRAWER: GESTIÓN Y CIERRE DOCUMENTADO DE EXPEDIENTE
	     ==================================================================== -->
	<div class="lp-slideover-overlay" id="drawerResolveOverlay" onclick="closeResolveDrawer()">
		<div class="lp-slideover-panel" onclick="event.stopPropagation()">
			<div class="lp-drawer-header">
				<div>
					<h3 id="drawerCaseTitle">Expediente</h3>
					<span class="text-muted small" id="drawerCaseSubtitle">—</span>
				</div>
				<button type="button" class="lp-drawer-close" onclick="closeResolveDrawer()">&times;</button>
			</div>

			<div class="lp-flow-stepper">
				<div class="lp-flow-step active"><span class="lp-flow-circle">1</span><span>Detectar</span></div>
				<div class="lp-flow-step active"><span class="lp-flow-circle">2</span><span>Validar</span></div>
				<div class="lp-flow-step active"><span class="lp-flow-circle">3</span><span>Investigar</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">4</span><span>Evidencia</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">5</span><span>Resolución</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">6</span><span>Corregir</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">7</span><span>Cerrar</span></div>
			</div>

			<div class="lp-drawer-body">
				<div class="p-3 border rounded" style="background:#f8fafc;">
					<div class="row text-center mb-2">
						<div class="col-3"><small class="text-muted d-block font-weight-bold">Diferencia</small><span class="text-danger font-weight-bold" id="drawerDiff">0</span></div>
						<div class="col-3"><small class="text-muted d-block font-weight-bold">En Riesgo</small><span class="text-danger font-weight-bold" id="drawerValue">$0.00</span></div>
						<div class="col-3"><small class="text-muted d-block font-weight-bold">Evidencias</small><span class="font-weight-bold" id="drawerEvCount">0</span></div>
						<div class="col-3"><small class="text-muted d-block font-weight-bold">Zona</small><span class="text-dark font-weight-bold small" id="drawerLoc">—</span></div>
					</div>
					<div class="small text-muted" id="drawerCustody"><i class="fa-solid fa-user-shield mr-1 text-success"></i> Responsable asignado: <strong>—</strong></div>
				</div>

				<div class="mt-2 mb-2">
					<a href="#" id="drawerFullCaseLink" class="btn btn-sm btn-block text-white font-weight-bold shadow-sm" style="background:#16a34a; border-radius:8px; height:38px; display:flex; align-items:center; justify-content:center; gap:8px;">
						<i class="fa-solid fa-folder-open"></i> Abrir Expediente Central 360°
					</a>
				</div>

				<div class="lp-drawer-actions-grid">
					<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerCheckin()"><i class="fa-solid fa-location-crosshairs text-info"></i> Check-in</button>
					<button type="button" class="lp-btn-drawer-action" onclick="openDrawerEvidenceForm()"><i class="fa-solid fa-camera text-success"></i> Evidencia</button>
					<button type="button" class="lp-btn-drawer-action" onclick="openCctvForCurrentCase()"><i class="fa-solid fa-video text-secondary"></i> CCTV Auditado</button>
					<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerEscalate()"><i class="fa-solid fa-triangle-exclamation text-danger"></i> Escalar</button>
				</div>

				<div class="p-3 border rounded bg-white">
					<h5 class="font-weight-bold mb-2" style="font-size:0.88rem;">Cierre Documentado de Expediente</h5>
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Justificación y Evidencia de Cierre</label>
						<textarea class="form-control form-control-sm" id="drawerResolutionNote" rows="2" placeholder="Documente la investigación, evidencia validada y acción correctiva..."></textarea>
					</div>
					<div class="d-flex gap-2">
						<button type="button" class="btn btn-sm btn-success font-weight-bold flex-grow-1" onclick="submitDrawerResolution('resuelto')">
							<i class="fa-solid fa-check mr-1"></i> Cerrar como Resuelto
						</button>
						<button type="button" class="btn btn-sm btn-outline-danger font-weight-bold" onclick="submitDrawerResolution('no_resuelto')">
							No Resuelto
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     10. MODALES OPERATIVOS REALES
	     ==================================================================== -->
	@include('components.loss_strip')

	<!-- Modal Registrar Nuevo Incidente (Responsable Obligatorio + Causal Indeterminada) -->
	<div class="modal fade" id="modalNewIncident" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" style="border-radius:14px;">
				<div class="modal-header py-3 px-4">
					<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-plus-circle text-success mr-2"></i> Registrar Nuevo Incidente</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body px-4 py-3">
					<div class="alert alert-info py-2 px-3 small mb-2">
						Ingresa en investigación con causal <strong>Sin clasificar (indeterminada)</strong>; nunca se asume robo automáticamente.
					</div>
					<form id="formNewIncident" onsubmit="submitNewIncident(event)">
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Producto / Activo *</label>
							<select class="form-control form-control-sm" id="newIncProduct" required>
								@foreach($productsList as $pr)
									<option value="{{ $pr['id'] }}">{{ $pr['name'] }} @if($pr['sku'])({{ $pr['sku'] }})@endif</option>
								@endforeach
							</select>
						</div>
						<div class="form-row mb-2">
							<div class="col-6">
								<label class="small font-weight-bold">Sucursal *</label>
								<select class="form-control form-control-sm" id="newIncBranch" required>
									@foreach($branches as $b)
										<option value="{{ $b->id }}">{{ $b->name }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-6">
								<label class="small font-weight-bold">Responsable Obligatorio *</label>
								<select class="form-control form-control-sm" id="newIncResponsible" required>
									<option value="">-- Seleccionar investigador --</option>
									@foreach($usersList as $u)
										<option value="{{ $u['id'] }}" @if(intval($u['id']) === intval(GROCY_USER_ID)) selected @endif>{{ $u['username'] }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="form-row mb-2">
							<div class="col-6">
								<label class="small font-weight-bold">Cantidad / Diferencia (u.) *</label>
								<input type="number" step="0.01" min="0.01" class="form-control form-control-sm" id="newIncQty" value="1" required>
							</div>
							<div class="col-6">
								<label class="small font-weight-bold">Prioridad</label>
								<select class="form-control form-control-sm" id="newIncPriority">
									<option value="urgent">Urgente (P1)</option>
									<option value="high">Alta (P2)</option>
									<option value="medium" selected>Media (P3)</option>
								</select>
							</div>
						</div>
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Zona / Ubicación</label>
							<input type="text" class="form-control form-control-sm" id="newIncZone" value="Vitrinas de Seguridad">
						</div>
						<div class="form-group mb-3">
							<label class="small font-weight-bold">Hallazgo Inicial *</label>
							<textarea class="form-control form-control-sm" id="newIncNote" rows="2" placeholder="Detalle el hallazgo observado (mínimo 5 caracteres)..." required minlength="5"></textarea>
						</div>
						<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold" style="height:38px; border-radius:8px;">
							Registrar Incidente y Abrir Expediente
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Consulta CCTV Auditada -->
	<div class="modal fade" id="modalCctvAccess" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" style="border-radius:14px;">
				<div class="modal-header py-3 px-4">
					<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-video text-info mr-2"></i> Consulta Auditada de CCTV</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body px-4 py-3">
					<form id="formCctvAccess" onsubmit="submitCctvAccess(event)">
						<input type="hidden" id="cctvCaseId" value="">
						<div class="form-row mb-2">
							<div class="col-6">
								<label class="small font-weight-bold">Sucursal *</label>
								<select class="form-control form-control-sm" id="cctvBranchId" required>
									@foreach($branches as $b)
										<option value="{{ $b->id }}">{{ $b->name }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-6">
								<label class="small font-weight-bold">Autorizado por *</label>
								<input type="text" class="form-control form-control-sm" id="cctvAuthorizedBy" placeholder="Nombre del gerente" required>
							</div>
						</div>
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Referencia de Cámara y Rango Horario *</label>
							<input type="text" class="form-control form-control-sm" id="cctvCameraRef" placeholder="Ej. CAM-04-VITRINA 14:00-14:45" required>
						</div>
						<div class="form-group mb-3">
							<label class="small font-weight-bold">Motivo de la Revisión *</label>
							<textarea class="form-control form-control-sm" id="cctvNotes" rows="2" placeholder="Justificación vinculada al incidente o auditoría..." required minlength="4"></textarea>
						</div>
						<button type="submit" class="btn btn-sm btn-info btn-block font-weight-bold">Registrar Acceso Auditado a CCTV</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Registrar Recuperación Financiera -->
	<div class="modal fade" id="modalFinancialRecovery" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" style="border-radius:14px;">
				<div class="modal-header py-3 px-4">
					<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-hand-holding-dollar text-success mr-2"></i> Registrar Recuperación Financiera</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body px-4 py-3">
					<form id="formRecovery" onsubmit="submitFinancialRecovery(event)">
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Expediente *</label>
							<select class="form-control form-control-sm" id="recCaseId" required>
								@foreach($cases as $c)
									<option value="{{ $c['id'] }}">{{ $c['case_code'] }} · {{ $c['product_name'] }} ({{ $money($c['financial_impact']) }})</option>
								@endforeach
							</select>
						</div>
						<div class="form-row mb-2">
							<div class="col-4"><label class="small font-weight-bold">Mercancía ($)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="recMerch" value="0"></div>
							<div class="col-4"><label class="small font-weight-bold">Proveedor ($)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="recSupp" value="0"></div>
							<div class="col-4"><label class="small font-weight-bold">Seguro ($)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="recIns" value="0"></div>
						</div>
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Folio Único de Soporte / Nota Crédito *</label>
							<input type="text" class="form-control form-control-sm" id="recRef" placeholder="Ej. NC-PROV-2026-0091" required>
						</div>
						<div class="form-group mb-3">
							<label class="small font-weight-bold">Observaciones</label>
							<textarea class="form-control form-control-sm" id="recNotes" rows="2" required></textarea>
						</div>
						<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold">Guardar Recuperación Auditada</button>
					</form>
				</div>
			</div>
		</div>
	</div>

</div>

@push('pageScripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
	var trendLabels = @json($chartTrendLabels);
	var trendValues = @json($chartTrendValues);
	var prodLabels = @json($chartProdLabels);
	var prodValues = @json($chartProdValues);
	var deptLabels = @json($chartDeptLabels);
	var deptValues = @json($chartDeptValues);

	var ctxTrend = document.getElementById('lp-trend-chart');
	if (ctxTrend) {
		var chartTrend = ctxTrend.getContext('2d');
		var gradRed = chartTrend.createLinearGradient(0, 0, 0, 200);
		gradRed.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
		gradRed.addColorStop(1, 'rgba(239, 68, 68, 0.00)');
		new Chart(chartTrend, {
			type: 'line',
			data: {
				labels: trendLabels.length ? trendLabels : ['Sin datos'],
				datasets: [{ label: 'Valor ($)', data: trendValues.length ? trendValues : [0], borderColor: '#ef4444', backgroundColor: gradRed, fill: true, tension: 0.35, pointRadius: 4 }]
			},
			options: { responsive: true, maintainAspectRatio: false, legend: { display: false } }
		});
	}

	var ctxProd = document.getElementById('lp-products-chart');
	if (ctxProd) {
		new Chart(ctxProd.getContext('2d'), {
			type: 'bar',
			data: {
				labels: prodLabels.length ? prodLabels : ['Sin datos'],
				datasets: [{ label: 'Valor (USD)', data: prodValues.length ? prodValues : [0], backgroundColor: '#22c55e', borderRadius: 4 }]
			},
			options: { responsive: true, maintainAspectRatio: false, legend: { display: false } }
		});
	}

	var ctxDept = document.getElementById('lp-department-chart');
	if (ctxDept) {
		new Chart(ctxDept.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: deptLabels.length ? deptLabels : ['Sin datos'],
				datasets: [{ data: deptValues.length ? deptValues : [1], backgroundColor: ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#8b5cf6', '#64748b'], borderWidth: 2, borderColor: '#ffffff' }]
			},
			options: { responsive: true, maintainAspectRatio: false, legend: { display: false } }
		});
	}
});

function lpNotify(msg, ok) {
	var box = document.getElementById('lpToastBox');
	if (!box) return;
	box.className = 'alert shadow-sm mb-2 ' + (ok ? 'alert-success' : 'alert-danger');
	box.textContent = msg;
	box.classList.remove('d-none');
	setTimeout(function() { box.classList.add('d-none'); }, 6000);
}

function switchLpTab(tabName) {
	document.querySelectorAll('.lp-tab-item').forEach(function(b) { b.classList.remove('active'); });
	var activeBtn = document.getElementById('tab-btn-' + tabName);
	if (activeBtn) activeBtn.classList.add('active');
	document.querySelectorAll('.lp-tab-pane').forEach(function(p) { p.classList.add('d-none'); });
	var targetPane = document.getElementById('pane-' + tabName);
	if (targetPane) targetPane.classList.remove('d-none');
}

var currentDrawerCase = null;
function openResolveDrawer(caseData) {
	currentDrawerCase = caseData;
	document.getElementById('drawerCaseTitle').textContent = 'Expediente ' + caseData.case_code;
	document.getElementById('drawerCaseSubtitle').textContent = caseData.product_name + ' · ' + caseData.branch_name;
	document.getElementById('drawerDiff').textContent = caseData.difference + ' u.';
	document.getElementById('drawerValue').textContent = '$' + Number(caseData.value_at_risk).toFixed(2);
	document.getElementById('drawerEvCount').textContent = (caseData.evidence_count || 0) + ' adj.';
	document.getElementById('drawerLoc').textContent = caseData.zone;
	document.getElementById('drawerCustody').innerHTML = '<i class="fa-solid fa-user-shield mr-1 text-success"></i> Responsable asignado: <strong>' + (caseData.responsible || 'Sin asignar') + '</strong>';
	document.getElementById('drawerFullCaseLink').href = '{{ $U('/loss-prevention/case/') }}' + caseData.id;
	document.getElementById('drawerResolveOverlay').classList.add('open');
}
function closeResolveDrawer() { document.getElementById('drawerResolveOverlay').classList.remove('open'); }

function triggerDrawerCheckin() {
	if (!currentDrawerCase) return;
	fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/checkin', {
		method: 'POST', headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ zone_name: currentDrawerCase.zone })
	}).then(r => r.json()).then(res => lpNotify(res.error_message ? res.error_message : 'Check-in registrado en ' + currentDrawerCase.zone, !res.error_message));
}
function openDrawerEvidenceForm() {
	if (!currentDrawerCase) return;
	window.location.href = '{{ $U('/loss-prevention/case/') }}' + currentDrawerCase.id;
}
function openCctvForCurrentCase() {
	document.getElementById('cctvCaseId').value = currentDrawerCase ? currentDrawerCase.id : '';
	if (currentDrawerCase && currentDrawerCase.branch_id) document.getElementById('cctvBranchId').value = currentDrawerCase.branch_id;
	$('#modalCctvAccess').modal('show');
}
function triggerDrawerEscalate() {
	if (!currentDrawerCase) return;
	fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/escalate', {
		method: 'POST', headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ target_role: 'manager', reason: 'Escalamiento desde Centro de Control de Seguridad' })
	}).then(r => r.json()).then(res => lpNotify(res.error_message ? res.error_message : 'Expediente escalado a Gerencia.', !res.error_message));
}
function submitDrawerResolution(finalStatus) {
	if (!currentDrawerCase) return;
	var note = document.getElementById('drawerResolutionNote').value.trim();
	if (note.length < 5) { lpNotify('Redacte una justificación de cierre de al menos 5 caracteres.', false); return; }
	fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/close', {
		method: 'POST', headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ outcome: finalStatus, justification: note })
	}).then(r => r.json()).then(res => {
		if (res.error_message) { lpNotify(res.error_message, false); return; }
		lpNotify(res.message || 'Expediente cerrado correctamente.', true);
		setTimeout(function() { window.location.reload(); }, 600);
	});
}

function openNewIncidentModal() { $('#modalNewIncident').modal('show'); }
function submitNewIncident(e) {
	e.preventDefault();
	fetch('{{ $U('/api/loss-control/cases') }}', {
		method: 'POST', headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			product_id: document.getElementById('newIncProduct').value,
			branch_id: document.getElementById('newIncBranch').value,
			assigned_user_id: document.getElementById('newIncResponsible').value,
			difference_qty: document.getElementById('newIncQty').value,
			priority: document.getElementById('newIncPriority').value,
			zone: document.getElementById('newIncZone').value,
			notes: document.getElementById('newIncNote').value
		})
	}).then(r => r.json()).then(res => {
		if (res.error_message) { lpNotify(res.error_message, false); return; }
		$('#modalNewIncident').modal('hide');
		lpNotify('Incidente ' + (res.case_code || '') + ' abierto sin presunción automática de robo.', true);
		setTimeout(function() { window.location.reload(); }, 600);
	});
}

function openCctvModal() {
	document.getElementById('cctvCaseId').value = '';
	$('#modalCctvAccess').modal('show');
}
function submitCctvAccess(e) {
	e.preventDefault();
	fetch('{{ $U('/api/loss-control/cctv/access') }}', {
		method: 'POST', headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			case_id: document.getElementById('cctvCaseId').value || null,
			branch_id: document.getElementById('cctvBranchId').value,
			authorized_by: document.getElementById('cctvAuthorizedBy').value,
			camera_ref: document.getElementById('cctvCameraRef').value,
			notes: document.getElementById('cctvNotes').value
		})
	}).then(r => r.json()).then(res => {
		if (res.error_message) { lpNotify(res.error_message, false); return; }
		$('#modalCctvAccess').modal('hide');
		lpNotify('Consulta CCTV registrada y auditada (' + res.camera_ref + ').', true);
		setTimeout(function() { window.location.reload(); }, 600);
	});
}

function openRecoveryModal() { $('#modalFinancialRecovery').modal('show'); }
function submitFinancialRecovery(e) {
	e.preventDefault();
	var cid = document.getElementById('recCaseId').value;
	fetch('{{ $U('/api/loss-control/cases/') }}' + cid + '/financial-recovery', {
		method: 'POST', headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			recovered_merchandise: document.getElementById('recMerch').value,
			supplier_claim: document.getElementById('recSupp').value,
			insurance_claim: document.getElementById('recIns').value,
			document_refs: [document.getElementById('recRef').value],
			notes: document.getElementById('recNotes').value
		})
	}).then(r => r.json()).then(res => {
		if (res.error_message) { lpNotify(res.error_message, false); return; }
		$('#modalFinancialRecovery').modal('hide');
		lpNotify('Recuperación financiera registrada sin duplicados.', true);
		setTimeout(function() { window.location.reload(); }, 600);
	});
}

function runSecurityAlertScan() {
	fetch('{{ $U('/api/alerts/scan') }}', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })
		.then(r => r.json()).then(res => {
			lpNotify('Barrido completado: ' + (res.created ?? 0) + ' nuevas alertas.', true);
			setTimeout(function() { window.location.reload(); }, 600);
		});
}

function lookupDeviceReal() {
	var code = document.getElementById('txtImeiSearch').value.trim();
	var box = document.getElementById('imeiLookupResult');
	if (!code) { lpNotify('Ingrese un IMEI o serial para consultar.', false); return; }
	fetch('{{ $U('/api/devices/lookup/') }}' + encodeURIComponent(code))
		.then(r => r.json()).then(res => {
			var u = (res && res.unit) ? res.unit : res;
			box.classList.remove('d-none');
			if (!res || res.error_message || !u || !u.id) {
				box.innerHTML = '<div class="alert alert-warning mb-0 py-2">No se encontró equipo con ese IMEI/Serial.</div>';
				return;
			}
			box.innerHTML = '<div class="alert alert-info mb-0 py-2"><strong>' + (u.product_name || 'Dispositivo') + '</strong> · IMEI: <code>' + (u.imei1_masked || u.serial_masked || '—') + '</code> · Estado: <strong>' + (u.state || 'vault') + '</strong> · Zona: ' + (u.zone || 'vault') + ' · <a href="{{ $U('/loss-prevention/devices/') }}' + u.id + '" class="font-weight-bold">Ver Cadena Completa</a></div>';
		});
}

function revealImeiAudited(deviceId) {
	fetch('{{ $U('/api/devices/') }}' + deviceId + '/reveal')
		.then(r => r.json()).then(res => {
			if (res.error_message) { lpNotify(res.error_message, false); return; }
			lpNotify('Consulta auditada — IMEI1: ' + (res.imei1 || '—') + ' | Serial: ' + (res.serial_number || '—'), true);
		});
}

function filterCasesTable(criteria) {
	switchLpTab('expedientes');
	document.querySelectorAll('.lp-cases-table tbody tr').forEach(function(r) {
		var isClosed = r.getAttribute('data-closed') === '1';
		var st = (r.getAttribute('data-status') || '').toLowerCase();
		var show = true;
		if (criteria === 'open') show = !isClosed;
		else if (criteria === 'closed') show = isClosed;
		else if (criteria === 'investigacion') show = st.indexOf('investigaci') !== -1 || st === 'in_progress';
		else if (criteria === 'reconteo') show = st === 'reconteo' || st === 'detectado';
		r.style.display = show ? '' : 'none';
	});
}
</script>
@endpush
@endsection
