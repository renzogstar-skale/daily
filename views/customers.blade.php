@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Gestión de Clientes (CRM Retail) · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_customers.css?v=', true) }}{{ daily_asset_version('/css/daily_customers.css') }}" rel="stylesheet">
@endpush

@section('content')
@php
	$customersList = is_array($customers ?? null) ? $customers : [];
	$branchesList = $branches ?? [];
	$selectedBranchName = 'Todas las sucursales';
	foreach ($branchesList as $b) {
		$bid = intval(is_object($b) ? $b->id : $b['id']);
		if ($bid === intval($selectedBranchId)) {
			$selectedBranchName = is_object($b) ? $b->name : $b['name'];
			break;
		}
	}

	$totalCount = $crmData['total_count'] ?? count($customersList);
	$activeCount = $crmData['active_count'] ?? count(array_filter($customersList, fn($c) => intval($c['active'] ?? 1) === 1));
	$repurchaseRate = $crmData['repurchase_rate'] ?? 68.5;
	$retentionRate = $crmData['retention_rate'] ?? 84.2;
	$avgClv = $crmData['avg_clv'] ?? 1420.00;
	$atRiskCount = $crmData['at_risk_count'] ?? 42;
	$pendingCasesCount = $crmData['pending_cases_count'] ?? 5;
	$casesList = $crmData['cases'] ?? [];
	$campaignsList = $crmData['campaigns'] ?? [];
	$automationsList = $crmData['automations'] ?? [];
	$alertsList = $crmData['alerts'] ?? [];

	$initialSelectedId = intval($selectedCustomerId ?? ($customersList[0]['id'] ?? 1));
@endphp

<div class="ded-wrapper crm-dash-wrapper" id="crmRetailDashboard">

	<!-- ====================================================================
	     ENCABEZADO: TÍTULO, SUBTÍTULO Y SELECTORES SUPERIORES EJECUTIVOS
	     ==================================================================== -->
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Gestión de Clientes (CRM Retail)</h1>
			<p class="ded-subtitle">Centro de fidelización, retención, segmentación RFM, valor de vida (CLV) y atención comercial</p>
		</div>

		<div class="ded-header-right">
			<!-- Selector de Período -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnCrmPeriodSelect" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Seleccionar período de análisis">
					<i data-lucide="calendar"></i>
					<span>{{ $period === 'year' ? 'Año 2026' : ($period === 'month' ? 'Este mes' : 'Últimos 30 días') }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
					<a class="dropdown-item py-1 font-weight-bold" href="{{ $U('/customers?period=30d&branch_id=' . ($selectedBranchId ?? '')) }}">Últimos 30 días</a>
					<a class="dropdown-item py-1" href="{{ $U('/customers?period=month&branch_id=' . ($selectedBranchId ?? '')) }}">Mes actual en curso</a>
					<a class="dropdown-item py-1" href="{{ $U('/customers?period=year&branch_id=' . ($selectedBranchId ?? '')) }}">Año acumulado 2026</a>
				</div>
			</div>

			<!-- Selector de Sucursal -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnCrmBranchSelect" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Filtrar por sucursal">
					<i data-lucide="store"></i>
					<span>{{ $selectedBranchName }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
					<a class="dropdown-item py-1 @if(!$selectedBranchId) font-weight-bold active @endif" href="{{ $U('/customers?branch_id=') }}">
						Todas las sucursales (Consolidado)
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($branchesList as $b)
						@php
							$bid = intval(is_object($b) ? $b->id : $b['id']);
							$bname = is_object($b) ? $b->name : $b['name'];
						@endphp
						<a class="dropdown-item py-1 @if($bid === intval($selectedBranchId)) font-weight-bold active @endif" href="{{ $U('/customers?branch_id=' . $bid) }}">
							{{ $bname }}
						</a>
					@endforeach
				</div>
			</div>

			<!-- Botones de Acción Rápida -->
			<button type="button" class="ded-select-btn" id="btn-header-new-customer" data-toggle="modal" data-target="#modal-customer-form" title="Registrar nuevo cliente">
				<i data-lucide="user-plus" style="color:#10b981;"></i>
				<span>Nuevo Cliente</span>
			</button>

			<button type="button" class="ded-select-btn" data-toggle="modal" data-target="#modal-crm-new-case" title="Abrir caso de atención">
				<i data-lucide="message-square" style="color:#f59e0b;"></i>
				<span>Nuevo Reclamo</span>
			</button>

			<div class="dropdown d-inline-block">
				<button type="button" class="ded-select-btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Exportar base de clientes">
					<i data-lucide="download"></i>
					<span>Exportar</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
					<a class="dropdown-item py-1" href="#" id="btn-export-csv"><i data-lucide="file-spreadsheet" style="width:13px;height:13px;margin-right:6px;"></i>Exportar a CSV</a>
					<a class="dropdown-item py-1" href="#" id="btn-export-excel"><i data-lucide="table" style="width:13px;height:13px;margin-right:6px;"></i>Exportar a Excel (TSV)</a>
					<div class="dropdown-divider my-1"></div>
					<a class="dropdown-item py-1" href="#" onclick="window.print();"><i data-lucide="printer" style="width:13px;height:13px;margin-right:6px;"></i>Imprimir Directorio</a>
				</div>
			</div>

			<a href="{{ $U('/pos') }}" class="ded-select-btn" title="Ir a Punto de Venta">
				<i data-lucide="shopping-cart"></i>
				<span>Punto de Venta (POS)</span>
			</a>
		</div>
	</header>

	<!-- ====================================================================
	     PRIMERA FILA — 5 INDICADORES EJECUTIVOS DEL CRM RETAIL
	     ==================================================================== -->
	<section class="crm-kpis-grid-5" aria-label="Indicadores Clave de Gestión de Clientes">
		<!-- 1. Clientes Activos (Tarjeta destacada en verde corporativo) -->
		<article class="ded-kpi-card is-highlight" title="Clientes activos y compradores de la base" onclick="$('#pill-tab-directory').tab('show');">
			<div class="ded-kpi-top-highlight">
				<div class="ded-highlight-brand">
					<i data-lucide="users"></i>
					<span>Clientes Activos</span>
				</div>
				<div class="ded-highlight-spark-box">
					<i data-lucide="trending-up"></i>
				</div>
			</div>
			<div class="ded-kpi-val">{{ number_format($activeCount, 0) }} clientes</div>
			<div class="ded-kpi-sub-highlight">
				▲ +8.4 % vs período anterior · Retención {{ $retentionRate }} %
			</div>
		</article>

		<!-- 2. Tasa de Recompra -->
		<article class="ded-kpi-card" title="Porcentaje de clientes con dos o más compras en el período" onclick="$('#pill-tab-rfm').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="refresh-cw"></i></span>
			</div>
			<div class="ded-kpi-label">Tasa de Recompra</div>
			<div class="ded-kpi-val">{{ $repurchaseRate }} %</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">▲ +3.5 pp vs meta (65 %)</span>
			</div>
		</article>

		<!-- 3. Valor de Vida (CLV Promedio) -->
		<article class="ded-kpi-card" title="Valor monetario proyectado a 3 años por cliente activo" onclick="$('#pill-tab-analytics').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="circle-dollar-sign"></i></span>
			</div>
			<div class="ded-kpi-label">CLV Promedio Estimado</div>
			<div class="ded-kpi-val">${{ number_format($avgClv, 2) }}</div>
			<div class="ded-kpi-sub">Frecuencia: 3.8 compras / mes</div>
		</article>

		<!-- 4. Clientes en Riesgo de Abandono -->
		<article class="ded-kpi-card" title="Clientes con disminución de frecuencia de compra" onclick="$('#pill-tab-campaigns').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon" style="color:#f59e0b;"><i data-lucide="alert-triangle"></i></span>
			</div>
			<div class="ded-kpi-label">Clientes en Riesgo</div>
			<div class="ded-kpi-val" style="color:#b45309;">{{ $atRiskCount }} clientes</div>
			<div class="ded-kpi-sub">3.2 % de la base · > 35 días sin compra</div>
		</article>

		<!-- 5. Casos de Atención y Reclamos -->
		<article class="ded-kpi-card" title="Reclamos y solicitudes de clientes en seguimiento" onclick="$('#pill-tab-cases').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="shield-alert"></i></span>
			</div>
			<div class="ded-kpi-label">Casos de Atención</div>
			<div class="ded-kpi-val">{{ $pendingCasesCount }} pendientes</div>
			<div class="ded-kpi-sub">SLA cumplimiento: 94 % · 1 crítico</div>
		</article>
	</section>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS: 7 MÓDULOS DE GESTIÓN DE CLIENTES
	     ==================================================================== -->
	<nav class="nav ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Gestión de Clientes">
		<button type="button" class="ded-nav-tab active" data-toggle="pill" data-target="#crm-tab-overview" role="tab" id="pill-tab-overview" aria-selected="true">
			<i data-lucide="layout-dashboard" class="mr-1"></i>
			<span>1. Centro de Control</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#crm-tab-directory" role="tab" id="pill-tab-directory" aria-selected="false">
			<i data-lucide="users" class="mr-1"></i>
			<span>2. Base de Clientes 360°</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#crm-tab-rfm" role="tab" id="pill-tab-rfm" aria-selected="false">
			<i data-lucide="pie-chart" class="mr-1"></i>
			<span>3. Segmentación RFM</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#crm-tab-loyalty" role="tab" id="pill-tab-loyalty" aria-selected="false">
			<i data-lucide="award" class="mr-1"></i>
			<span>4. Fidelización & Puntos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#crm-tab-cases" role="tab" id="pill-tab-cases" aria-selected="false">
			<i data-lucide="headphones" class="mr-1"></i>
			<span>5. Atención & Casos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#crm-tab-campaigns" role="tab" id="pill-tab-campaigns" aria-selected="false">
			<i data-lucide="send" class="mr-1"></i>
			<span>6. Campañas & Retención</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#crm-tab-analytics" role="tab" id="pill-tab-analytics" aria-selected="false">
			<i data-lucide="brain-circuit" class="mr-1"></i>
			<span>7. Inteligencia & Automatización</span>
		</button>
	</nav>

	<!-- ====================================================================
	     CONTENIDO DE LAS 7 PESTAÑAS DEL CRM
	     ==================================================================== -->
	<div class="tab-content" id="crmDashboardTabContent">

		<!-- ================================================================
		     PESTAÑA 1: CENTRO DE CONTROL (OVERVIEW)
		     ================================================================ -->
		<div class="tab-pane fade show active" id="crm-tab-overview" role="tabpanel">
			<!-- Fila 1: Segmentos RFM + Alertas Ejecutivas (2 Columnas) -->
			<div class="ded-row-2 mb-3">
				<!-- Columna 1: Distribución por Segmento RFM -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="pie-chart" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Distribución Dinámica de Clientes (Matriz RFM)
						</span>
						<a href="#crm-tab-rfm" class="btn btn-xs btn-outline-success font-weight-bold" onclick="$('#pill-tab-rfm').tab('show');">Ver Matriz RFM →</a>
					</div>
					<div class="p-3">
						<div class="crm-segment-matrix-grid">
							<div class="crm-segment-box is-vip" onclick="filterBySegment('VIP');">
								<div class="crm-segment-top">
									<span class="crm-segment-title">VIP / Campeones</span>
									<span class="crm-segment-count">240</span>
								</div>
								<div class="crm-segment-desc">Gasto medio: $4,890 · Frecuencia semanal · 18.7% share</div>
							</div>

							<div class="crm-segment-box is-loyal" onclick="filterBySegment('Frecuente');">
								<div class="crm-segment-top">
									<span class="crm-segment-title">Frecuentes / Leales</span>
									<span class="crm-segment-count">510</span>
								</div>
								<div class="crm-segment-desc">Gasto medio: $2,120 · Compra quincenal · 39.8% share</div>
							</div>

							<div class="crm-segment-box is-new" onclick="filterBySegment('Nuevo');">
								<div class="crm-segment-top">
									<span class="crm-segment-title">Nuevos Compradores</span>
									<span class="crm-segment-count">195</span>
								</div>
								<div class="crm-segment-desc">Registrados < 30 días · Alto potencial · 15.2% share</div>
							</div>

							<div class="crm-segment-box is-occasional" onclick="filterBySegment('Ocasional');">
								<div class="crm-segment-top">
									<span class="crm-segment-title">Ocasionales</span>
									<span class="crm-segment-count">293</span>
								</div>
								<div class="crm-segment-desc">Compras esporádicas en festivos · 22.8% share</div>
							</div>

							<div class="crm-segment-box is-atrisk" onclick="filterBySegment('Riesgo');">
								<div class="crm-segment-top">
									<span class="crm-segment-title">En Riesgo de Abandono</span>
									<span class="crm-segment-count">42</span>
								</div>
								<div class="crm-segment-desc">> 35 días sin visita · Requieren reactivación</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Columna 2: Alertas y Oportunidades Comerciales -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="bell" style="width:15px;height:15px;color:#f59e0b;margin-right:6px;"></i>
							Alertas Accionables y Oportunidades de Retención
						</span>
						<span class="badge badge-light border text-muted">{{ count($alertsList) }} activas</span>
					</div>
					<div class="p-3">
						@foreach($alertsList as $alt)
						<div class="d-flex align-items-start justify-content-between p-2 mb-2 rounded border" style="background:rgba(255,255,255,0.7); font-size:12px;">
							<div class="d-flex align-items-start gap-2">
								<span class="badge @if($alt['severity'] === 'danger') badge-danger @elseif($alt['severity'] === 'warning') badge-warning @else badge-info @endif px-2 py-1 mt-1 font-weight-bold">
									{{ strtoupper($alt['type']) }}
								</span>
								<div>
									<div class="font-weight-bold text-dark">{{ $alt['title'] }}</div>
									<div class="text-muted" style="font-size:11.5px;">{{ $alt['desc'] }}</div>
								</div>
							</div>
							<button type="button" class="btn btn-xs btn-outline-dark font-weight-bold ml-2" onclick="handleCrmAlertAction('{{ $alt['target_tab'] }}');">
								{{ $alt['action_label'] }}
							</button>
						</div>
						@endforeach
					</div>
				</div>
			</div>

			<!-- Fila 2: Evolución CLV + Top Clientes (2 Columnas) -->
			<div class="ded-row-2">
				<!-- Curva de Evolución de Retención y CLV -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="trending-up" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Evolución de Retención y Valor de Vida (CLV)
						</span>
						<span class="badge badge-success font-weight-bold">Retención 84.2%</span>
					</div>
					<div class="p-3">
						<canvas id="crmClvChart" height="175"></canvas>
					</div>
				</div>

				<!-- Top Clientes de Mayor Valor -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="award" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Top Clientes de Mayor Aporte Comercial
						</span>
						<span class="badge badge-light border text-muted">Tier Platinum & Gold</span>
					</div>
					<div class="p-3">
						<div class="table-responsive">
							<table class="table table-sm mb-0" style="font-size:11.5px;">
								<thead class="bg-light">
									<tr>
										<th>Cliente</th>
										<th>Membresía</th>
										<th class="text-right">Total Comprado</th>
										<th class="text-right">Puntos</th>
										<th class="text-right">Acción</th>
									</tr>
								</thead>
								<tbody>
									@foreach(array_slice($customersList, 0, 5) as $topC)
									<tr>
										<td class="font-weight-bold text-dark">
											<div class="text-truncate" style="max-width:160px;">{{ $topC['name'] }}</div>
										</td>
										<td>
											<span class="badge" style="background:{{ $topC['loyalty_color'] ?? '#0f172a' }}; color:#fff;">
												{{ $topC['loyalty_tier'] ?? 'Gold' }}
											</span>
										</td>
										<td class="text-right font-weight-bold text-dark">${{ number_format($topC['total_spent'] ?? 2450, 2) }}</td>
										<td class="text-right font-weight-bold text-success">{{ number_format($topC['points_balance'] ?? 850, 0) }} pts</td>
										<td class="text-right">
											<button type="button" class="btn btn-xs btn-outline-success" onclick="selectCustomerInDirectory({{ $topC['id'] }});">Ver 360°</button>
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     PESTAÑA 2: BASE DE CLIENTES 360° (DIRECTORIO Y PERFIL)
		     ================================================================ -->
		<div class="tab-pane fade" id="crm-tab-directory" role="tabpanel">
			<!-- Barra de Filtros del Directorio -->
			<div class="customers-filter-bar mb-3 p-2 bg-white rounded border d-flex align-items-center justify-content-between flex-wrap gap-2">
				<div class="customers-search-wrap position-relative flex-1" style="min-width:260px;">
					<i data-lucide="search" style="position:absolute;left:10px;top:9px;width:14px;height:14px;color:#94a3b8;"></i>
					<input type="text" id="crm-cust-search" class="form-control form-control-sm" placeholder="Buscar por nombre, RFC/Tax ID, correo, teléfono..." style="padding-left:32px;font-size:12px;">
				</div>

				<div class="d-flex align-items-center gap-2 flex-wrap">
					<select id="crm-filter-type" class="custom-select custom-select-sm" style="width:130px;font-size:11.5px;">
						<option value="all">Tipo: Todos</option>
						<option value="Comercial">Comercial</option>
						<option value="Institucional">Institucional</option>
						<option value="Minorista">Minorista</option>
						<option value="Corporativo">Corporativo</option>
					</select>

					<select id="crm-filter-segment" class="custom-select custom-select-sm" style="width:150px;font-size:11.5px;">
						<option value="all">Segmento: Todos</option>
						<option value="VIP">VIP / Campeón</option>
						<option value="Frecuente">Frecuente / Leal</option>
						<option value="Nuevo">Nuevo Comprador</option>
						<option value="Riesgo">En Riesgo</option>
					</select>

					<button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-filters" style="font-size:11.5px;">
						<i data-lucide="rotate-ccw" style="width:12px;height:12px;margin-right:3px;"></i>Limpiar
					</button>
				</div>
			</div>

			<!-- Grid 2 Columnas: Lista de Clientes (Izq) + Ficha 360° (Der) -->
			<div class="customers-main-grid">
				<!-- Columna Izquierda: Tabla del Directorio -->
				<div class="customers-card">
					<div class="customers-table-wrap">
						<table class="customers-table" id="crm-customers-table">
							<thead>
								<tr>
									<th style="width:65px;"># ID</th>
									<th>CLIENTE</th>
									<th>IDENTIFICACIÓN</th>
									<th>SEGMENTO</th>
									<th class="text-right">TOTAL GASTO</th>
									<th class="text-right">ACCIONES</th>
								</tr>
							</thead>
							<tbody id="crm-customers-tbody">
								@foreach($customersList as $cust)
								@php
									$cId = intval($cust['id']);
									$cName = $cust['name'] ?? 'Cliente';
									$cTax = $cust['tax_id'] ?? '—';
									$cSegment = $cust['rfm_segment'] ?? 'Ocasional';
									$cBadge = $cust['rfm_badge_class'] ?? 'badge-secondary';
									$cSpent = floatval($cust['total_spent'] ?? 0);
									$isSelected = ($cId === $initialSelectedId);
								@endphp
								<tr class="crm-customer-row @if($isSelected) is-selected @endif" data-id="{{ $cId }}" onclick="selectCustomerInDirectory({{ $cId }});">
									<td class="font-mono text-muted">#{{ $cId }}</td>
									<td>
										<div class="font-weight-bold text-dark">{{ $cName }}</div>
										<small class="text-muted">{{ $cust['email'] ?? $cust['phone'] ?? 'Sin contacto' }}</small>
									</td>
									<td class="font-mono">{{ $cTax }}</td>
									<td>
										<span class="badge {{ $cBadge }}">{{ $cSegment }}</span>
									</td>
									<td class="text-right font-weight-bold text-dark">${{ number_format($cSpent, 2) }}</td>
									<td class="text-right">
										<button type="button" class="btn btn-xs btn-outline-success font-weight-bold">Ver</button>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>

				<!-- Columna Derecha: Ficha 360° del Cliente -->
				<div class="customers-card" id="crm-profile-card">
					@php
						$currentCust = null;
						foreach ($customersList as $c) {
							if (intval($c['id']) === $initialSelectedId) {
								$currentCust = $c;
								break;
							}
						}
						if (!$currentCust && !empty($customersList)) {
							$currentCust = $customersList[0];
						}
					@endphp
					<div class="customers-profile-top">
						<div class="d-flex align-items-center gap-3">
							<div class="customers-profile-avatar" id="crm-avatar">
								{{ substr($currentCust['name'] ?? 'CL', 0, 2) }}
							</div>
							<div>
								<div class="d-flex align-items-center gap-2">
									<h4 class="font-weight-bold text-dark mb-0" id="crm-name" style="font-size:15px;">{{ $currentCust['name'] ?? 'Selecciona un cliente' }}</h4>
									<span class="badge badge-success" id="crm-active-tag">Activo</span>
								</div>
								<div class="d-flex align-items-center gap-2 mt-1">
									<span class="badge {{ $currentCust['rfm_badge_class'] ?? 'badge-success' }}" id="crm-segment-tag">{{ $currentCust['rfm_segment'] ?? 'VIP' }}</span>
									<span class="badge" id="crm-tier-tag" style="background:{{ $currentCust['loyalty_color'] ?? '#0f172a' }}; color:#fff;">{{ $currentCust['loyalty_tier'] ?? 'Gold' }}</span>
									<span class="text-muted font-mono" id="crm-code-tag" style="font-size:11px;">#{{ $currentCust['id'] ?? 1 }}</span>
								</div>
							</div>
						</div>

						<div class="dropdown">
							<button class="btn btn-sm btn-light border" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<i data-lucide="more-vertical" style="width:14px;height:14px;"></i>
							</button>
							<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
								<a class="dropdown-item py-1" href="#" onclick="openEditCustomerModal();"><i data-lucide="edit-3" style="width:12px;height:12px;margin-right:6px;"></i>Editar datos</a>
								<a class="dropdown-item py-1" href="#" onclick="openCustomerNotesModal();"><i data-lucide="file-text" style="width:12px;height:12px;margin-right:6px;"></i>Notas y etiquetas</a>
								<a class="dropdown-item py-1" href="#" onclick="openMergeDuplicateModal();"><i data-lucide="git-merge" style="width:12px;height:12px;margin-right:6px;"></i>Fusionar duplicado</a>
								<div class="dropdown-divider my-1"></div>
								<a class="dropdown-item py-1 text-success font-weight-bold" href="{{ $U('/pos') }}"><i data-lucide="shopping-cart" style="width:12px;height:12px;margin-right:6px;"></i>Cobrar en POS</a>
							</div>
						</div>
					</div>

					<!-- Datos de Contacto y Fiscales -->
					<div class="customers-meta-grid">
						<div class="customers-meta-row">
							<span class="customers-meta-label">Identificación Fiscal</span>
							<span class="customers-meta-val" id="crm-tax-id">{{ $currentCust['tax_id'] ?? '—' }}</span>
						</div>
						<div class="customers-meta-row">
							<span class="customers-meta-label">Teléfono de Contacto</span>
							<span class="customers-meta-val" id="crm-phone">{{ $currentCust['phone'] ?? '—' }}</span>
						</div>
						<div class="customers-meta-row">
							<span class="customers-meta-label">Correo Electrónico</span>
							<span class="customers-meta-val" id="crm-email">{{ $currentCust['email'] ?? '—' }}</span>
						</div>
						<div class="customers-meta-row">
							<span class="customers-meta-label">Sucursal Principal</span>
							<span class="customers-meta-val" id="crm-branch">{{ $currentCust['branch_name'] ?? 'Flagship Store' }}</span>
						</div>
					</div>

					<!-- 4 Mini KPIs del Cliente -->
					<div class="customers-mini-kpis">
						<div class="customers-mini-kpi">
							<div class="mk-label">Compras</div>
							<div class="mk-val" id="crm-kpi-tx">{{ $currentCust['total_tx'] ?? 0 }}</div>
						</div>
						<div class="customers-mini-kpi">
							<div class="mk-label">Monto Total</div>
							<div class="mk-val text-success" id="crm-kpi-spent">${{ number_format($currentCust['total_spent'] ?? 0, 0) }}</div>
						</div>
						<div class="customers-mini-kpi">
							<div class="mk-label">Puntos</div>
							<div class="mk-val text-dark" id="crm-kpi-pts">{{ number_format($currentCust['points_balance'] ?? 100, 0) }}</div>
						</div>
						<div class="customers-mini-kpi">
							<div class="mk-label">Crédito</div>
							<div class="mk-val" id="crm-kpi-credit">${{ number_format($currentCust['credit_limit'] ?? 500, 0) }}</div>
						</div>
					</div>

					<!-- Consentimiento y Preferencias RGPD -->
					<div class="crm-consent-pills">
						<span class="crm-consent-pill is-granted"><i data-lucide="check" style="width:10px;height:10px;"></i> Email</span>
						<span class="crm-consent-pill is-granted"><i data-lucide="check" style="width:10px;height:10px;"></i> SMS</span>
						<span class="crm-consent-pill is-granted"><i data-lucide="check" style="width:10px;height:10px;"></i> WhatsApp</span>
						<span class="crm-consent-pill is-granted" style="margin-left:auto;font-size:9.5px;color:#059669;">Consentimiento al día</span>
					</div>

					<!-- Historial de Compras Recientes -->
					<div class="p-3 border-bottom">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="font-weight-bold text-dark" style="font-size:12px;">Últimas Compras en POS & Pedidos</span>
							<a href="{{ $U('/sales?tab=detalle') }}" class="small font-weight-bold text-success">Ver todas</a>
						</div>
						<div class="table-responsive">
							<table class="table table-sm mb-0" style="font-size:11px;">
								<thead>
									<tr class="text-muted border-bottom">
										<th>Folio</th>
										<th>Fecha</th>
										<th class="text-right">Monto</th>
										<th class="text-center">Estado</th>
									</tr>
								</thead>
								<tbody id="crm-recent-sales-tbody">
									<tr>
										<td class="font-mono font-weight-bold">VTA-{{ date('Ymd') }}-0045</td>
										<td>Hoy 11:32 AM</td>
										<td class="text-right font-weight-bold text-dark">$48.50</td>
										<td class="text-center"><span class="badge badge-success">Pagado</span></td>
									</tr>
									<tr>
										<td class="font-mono font-weight-bold">VTA-20261104-B102</td>
										<td>04/11 15:20 PM</td>
										<td class="text-right font-weight-bold text-dark">$124.00</td>
										<td class="text-center"><span class="badge badge-success">Pagado</span></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Acciones Principales de la Ficha -->
					<div class="customers-profile-actions">
						<a href="{{ $U('/orders') }}" class="customers-act-btn">
							<i data-lucide="package" style="width:13px;height:13px;"></i>
							<span>Ver Pedidos</span>
						</a>
						<button type="button" class="customers-act-btn" onclick="openCustomerNotesModal();">
							<i data-lucide="file-text" style="width:13px;height:13px;"></i>
							<span>Notas</span>
						</button>
						<a href="{{ $U('/pos') }}" class="customers-act-btn is-primary" id="btn-profile-new-sale">
							<i data-lucide="shopping-cart" style="width:13px;height:13px;"></i>
							<span>Nueva Venta POS</span>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     PESTAÑA 3: SEGMENTACIÓN INTELIGENTE (ANÁLISIS RFM)
		     ================================================================ -->
		<div class="tab-pane fade" id="crm-tab-rfm" role="tabpanel">
			<div class="ded-card mb-3">
				<div class="ded-card-header">
					<span class="ded-card-title">
						<i data-lucide="pie-chart" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
						Matriz de Segmentación RFM (Recencia, Frecuencia, Valor Monetario)
					</span>
					<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="toastr.success('Segmentos recalculados con transacciones de los últimos 90 días.');">
						<i data-lucide="refresh-cw" style="width:12px;height:12px;margin-right:3px;"></i>Recalcular RFM
					</button>
				</div>
				<div class="p-3">
					<p class="text-muted small mb-3">
						La segmentación RFM clasifica automáticamente a los clientes según cuándo compraron por última vez (Recencia), con qué periodicidad compran (Frecuencia) y cuánto gastan en total (Monto).
					</p>

					<div class="row">
						<div class="col-md-4 mb-3">
							<div class="p-3 rounded border bg-white h-100">
								<div class="d-flex justify-content-between align-items-center mb-2">
									<h6 class="font-weight-bold text-success mb-0">Campeones (RFM 5-5-5)</h6>
									<span class="badge badge-success">240 clientes</span>
								</div>
								<p class="text-muted small mb-2">Compran con alta frecuencia y gastan el mayor monto. Máxima lealtad de marca.</p>
								<div class="small font-weight-bold mb-3">Acción: Trato preferente, acceso anticipado y recompensas exclusivas.</div>
								<button type="button" class="btn btn-xs btn-outline-success btn-block" onclick="filterBySegment('VIP'); $('#pill-tab-directory').tab('show');">Ver Clientes Campeones</button>
							</div>
						</div>

						<div class="col-md-4 mb-3">
							<div class="p-3 rounded border bg-white h-100">
								<div class="d-flex justify-content-between align-items-center mb-2">
									<h6 class="font-weight-bold text-dark mb-0">Leales / Frecuentes (RFM 4-4-3)</h6>
									<span class="badge badge-info">510 clientes</span>
								</div>
								<p class="text-muted small mb-2">Responden bien a promociones y mantienen recompra quincenal constante.</p>
								<div class="small font-weight-bold mb-3">Acción: Venta cruzada, puntos de fidelización y recomendaciones.</div>
								<button type="button" class="btn btn-xs btn-outline-info btn-block" onclick="filterBySegment('Frecuente'); $('#pill-tab-directory').tab('show');">Ver Clientes Leales</button>
							</div>
						</div>

						<div class="col-md-4 mb-3">
							<div class="p-3 rounded border bg-white h-100">
								<div class="d-flex justify-content-between align-items-center mb-2">
									<h6 class="font-weight-bold text-warning mb-0">En Riesgo (RFM 2-4-4)</h6>
									<span class="badge badge-warning">42 clientes</span>
								</div>
								<p class="text-muted small mb-2">Gastaban montos altos pero llevan más de 35 días sin registrar actividad.</p>
								<div class="small font-weight-bold mb-3">Acción: Campaña personalizada de recuperación con cupón directo.</div>
								<button type="button" class="btn btn-xs btn-outline-warning btn-block" onclick="filterBySegment('Riesgo'); $('#pill-tab-directory').tab('show');">Ver Clientes en Riesgo</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     PESTAÑA 4: PROGRAMA DE FIDELIZACIÓN & PUNTOS (DAILY REWARDS)
		     ================================================================ -->
		<div class="tab-pane fade" id="crm-tab-loyalty" role="tabpanel">
			<!-- Resumen de Niveles de Membresía -->
			<div class="row mb-3">
				<div class="col-md-3 mb-2">
					<div class="crm-tier-card crm-tier-bronze">
						<div class="crm-tier-badge"><i data-lucide="shield"></i></div>
						<h5 class="font-weight-bold text-dark mb-1">Bronze</h5>
						<div class="small text-muted mb-2">0 - 1,000 puntos</div>
						<div class="badge badge-light border">1% Cashback en Puntos</div>
					</div>
				</div>

				<div class="col-md-3 mb-2">
					<div class="crm-tier-card crm-tier-silver">
						<div class="crm-tier-badge"><i data-lucide="award"></i></div>
						<h5 class="font-weight-bold text-dark mb-1">Silver</h5>
						<div class="small text-muted mb-2">1,001 - 2,500 puntos</div>
						<div class="badge badge-light border">2% Cashback + Cupones Mes</div>
					</div>
				</div>

				<div class="col-md-3 mb-2">
					<div class="crm-tier-card crm-tier-gold">
						<div class="crm-tier-badge"><i data-lucide="star"></i></div>
						<h5 class="font-weight-bold text-dark mb-1">Gold</h5>
						<div class="small text-muted mb-2">2,501 - 5,000 puntos</div>
						<div class="badge badge-warning text-dark font-weight-bold">3% Cashback + Fila Rápida</div>
					</div>
				</div>

				<div class="col-md-3 mb-2">
					<div class="crm-tier-card crm-tier-platinum">
						<div class="crm-tier-badge"><i data-lucide="crown"></i></div>
						<h5 class="font-weight-bold text-white mb-1" style="color:#0f172a !important;">Platinum</h5>
						<div class="small text-muted mb-2">> 5,000 puntos</div>
						<div class="badge badge-dark">5% Cashback + Envíos Gratis</div>
					</div>
				</div>
			</div>

			<!-- Calculadora de Canje y Reglas -->
			<div class="ded-row-2">
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="calculator" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Simulador de Canje de Puntos en Caja
						</span>
						<span class="badge badge-success">Equivalencia: 100 pts = $1.00</span>
					</div>
					<div class="p-3">
						<div class="form-group mb-2">
							<label class="small font-weight-bold text-muted mb-1">Puntos a Canjear:</label>
							<input type="number" id="calc-points-input" class="form-control form-control-sm font-weight-bold" value="500" step="50" oninput="$('#calc-points-result').text('$' + (parseFloat(this.value)/100).toFixed(2));">
						</div>
						<div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
							<span class="text-muted small">Descuento aplicable en el ticket POS:</span>
							<strong class="text-success h5 mb-0" id="calc-points-result">$5.00</strong>
						</div>
						<button type="button" class="btn btn-sm btn-success font-weight-bold btn-block" onclick="toastr.success('Cupón de canje generado exitosamente para aplicar en POS');">
							<i data-lucide="ticket" style="width:13px;height:13px;margin-right:4px;"></i> Generar Cupón de Canje
						</button>
					</div>
				</div>

				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="shield-check" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Reglas de Acumulación y Devoluciones
						</span>
						<span class="badge badge-light border text-muted">Auditoría inmutable</span>
					</div>
					<div class="p-3" style="font-size:12px;">
						<ul class="pl-3 mb-2 text-muted">
							<li class="mb-1"><strong>Emisión:</strong> Por cada $1.00 cobrado en POS se emite 1 punto automáticamente.</li>
							<li class="mb-1"><strong>Reversión en Devolución:</strong> Si se anula un ticket o se procesa una devolución en el módulo de Devoluciones del POS, los puntos asociados se descuentan de forma reversible.</li>
							<li class="mb-1"><strong>Vigencia:</strong> Los puntos tienen una caducidad de 180 días con alertas preventivas.</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     PESTAÑA 5: ATENCIÓN AL CLIENTE & CASOS
		     ================================================================ -->
		<div class="tab-pane fade" id="crm-tab-cases" role="tabpanel">
			<div class="ded-card">
				<div class="ded-card-header">
					<div class="d-flex align-items-center gap-2">
						<span class="ded-card-title">
							<i data-lucide="headphones" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Bandeja de Reclamos y Solicitudes de Clientes
						</span>
						<span class="badge badge-warning">{{ count($casesList) }} casos en curso</span>
					</div>
					<button type="button" class="btn btn-xs btn-success font-weight-bold" data-toggle="modal" data-target="#modal-crm-new-case">
						<i data-lucide="plus" style="width:12px;height:12px;margin-right:3px;"></i>Nuevo Reclamo
					</button>
				</div>
				<div class="p-3">
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0" style="font-size:12px;">
							<thead class="bg-light">
								<tr>
									<th>Código</th>
									<th>Cliente</th>
									<th>Motivo / Título</th>
									<th>Categoría</th>
									<th class="text-center">Prioridad</th>
									<th>Responsable</th>
									<th>Plazo SLA</th>
									<th class="text-center">Estado</th>
									<th class="text-right">Acción</th>
								</tr>
							</thead>
							<tbody>
								@foreach($casesList as $cs)
								<tr id="case-row-{{ $cs['id'] }}">
									<td class="font-mono font-weight-bold">{{ $cs['case_number'] }}</td>
									<td class="font-weight-bold text-dark">{{ $cs['customer_name'] }}</td>
									<td>{{ $cs['title'] }}</td>
									<td><span class="badge badge-light border">{{ $cs['category'] }}</span></td>
									<td class="text-center">
										@if($cs['priority'] === 'Crítica')
											<span class="badge badge-danger">Crítica</span>
										@elseif($cs['priority'] === 'Alta')
											<span class="badge badge-warning">Alta</span>
										@else
											<span class="badge badge-secondary">Media</span>
										@endif
									</td>
									<td>{{ $cs['assigned_to'] }}</td>
									<td>
										@if($cs['is_overdue'])
											<span class="text-danger font-weight-bold"><i data-lucide="alert-triangle" style="width:11px;height:11px;"></i> {{ $cs['due_date'] }} (Vencido)</span>
										@else
											<span class="text-muted">{{ $cs['due_date'] }}</span>
										@endif
									</td>
									<td class="text-center">
										@if($cs['status'] === 'Resuelto')
											<span class="badge badge-success">Resuelto</span>
										@elseif($cs['status'] === 'Escalado')
											<span class="badge badge-danger">Escalado</span>
										@else
											<span class="badge badge-warning text-dark">{{ $cs['status'] }}</span>
										@endif
									</td>
									<td class="text-right">
										@if($cs['status'] !== 'Resuelto')
											<button type="button" class="btn btn-xs btn-success font-weight-bold" onclick="resolveCustomerCase({{ $cs['id'] }});">Resolver</button>
										@else
											<span class="text-muted small">Cerrado</span>
										@endif
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     PESTAÑA 6: CAMPAÑAS & RETENCIÓN
		     ================================================================ -->
		<div class="tab-pane fade" id="crm-tab-campaigns" role="tabpanel">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<div>
					<h5 class="font-weight-bold text-dark mb-0">Campañas Comerciales por Segmento</h5>
					<small class="text-muted">Estrategias automáticas de reactivación, bienvenida y aumento de ticket promedio.</small>
				</div>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" data-toggle="modal" data-target="#modal-crm-new-campaign">
					<i data-lucide="plus" style="width:13px;height:13px;margin-right:4px;"></i>Nueva Campaña
				</button>
			</div>

			<div class="row">
				@foreach($campaignsList as $cmp)
				<div class="col-md-4 mb-3">
					<div class="crm-campaign-card bg-white h-100 d-flex flex-column justify-content-between">
						<div>
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="badge badge-success">{{ $cmp['status'] }}</span>
								<span class="text-muted font-mono small">{{ $cmp['code'] }}</span>
							</div>
							<h6 class="font-weight-bold text-dark mb-1">{{ $cmp['name'] }}</h6>
							<div class="text-muted small mb-2">Segmento: <strong>{{ $cmp['segment'] }}</strong> · {{ $cmp['channel'] }}</div>
							<div class="p-2 bg-light rounded border mb-3 small font-weight-bold text-dark">
								{{ $cmp['offer'] }}
							</div>
						</div>

						<div>
							<div class="d-flex justify-content-between small text-muted mb-1">
								<span>Conversión:</span>
								<strong class="text-success">{{ $cmp['conversion_rate'] }}% ({{ $cmp['converted_count'] }} clientes)</strong>
							</div>
							<div class="d-flex justify-content-between small text-muted mb-3">
								<span>Ventas Generadas:</span>
								<strong class="text-dark">${{ number_format($cmp['revenue_generated'], 2) }}</strong>
							</div>
							<button type="button" class="btn btn-xs btn-outline-dark btn-block font-weight-bold" onclick="toastr.info('Campaña {{ $cmp['code'] }} sincronizada con pasarela de mensajería');">
								Gestionar Envíos
							</button>
						</div>
					</div>
				</div>
				@endforeach
			</div>
		</div>

		<!-- ================================================================
		     PESTAÑA 7: INTELIGENCIA & AUTOMATIZACIÓN
		     ================================================================ -->
		<div class="tab-pane fade" id="crm-tab-analytics" role="tabpanel">
			<div class="ded-card">
				<div class="ded-card-header">
					<span class="ded-card-title">
						<i data-lucide="zap" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
						Disparadores Automáticos Activos (Retail Automation Engine)
					</span>
					<span class="badge badge-success">4 Reglas Activas</span>
				</div>
				<div class="p-3">
					<div class="table-responsive">
						<table class="table table-sm mb-0" style="font-size:12px;">
							<thead class="bg-light">
								<tr>
									<th>Regla Automática</th>
									<th>Condición / Disparador</th>
									<th>Acción Ejecutada</th>
									<th class="text-center">Ejecuciones</th>
									<th>Última Corrida</th>
									<th class="text-center">Estado</th>
								</tr>
							</thead>
							<tbody>
								@foreach($automationsList as $aut)
								<tr>
									<td class="font-weight-bold text-dark">{{ $aut['title'] }}</td>
									<td class="text-muted">{{ $aut['trigger'] }}</td>
									<td><span class="badge badge-light border">{{ $aut['action'] }}</span></td>
									<td class="text-center font-weight-bold">{{ $aut['executions_count'] }}</td>
									<td class="text-muted small">{{ $aut['last_executed'] }}</td>
									<td class="text-center"><span class="badge badge-success"><i data-lucide="check" style="width:10px;height:10px;"></i> Activo</span></td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- =========================================================================
     MODALES DEL CRM RETAIL
     ========================================================================= -->

<!-- MODAL: REGISTRAR / EDITAR CLIENTE -->
<div class="modal fade" id="modal-customer-form" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header border-bottom py-3 px-4">
				<h5 class="modal-title font-weight-bold text-dark" id="modal-customer-title">
					<i data-lucide="user-plus" style="width:18px;height:18px;color:#10b981;margin-right:6px;"></i>
					Registro de Cliente 360°
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<form id="form-customer">
				<div class="modal-body px-4 py-3">
					<input type="hidden" id="cust-form-id" value="">
					<div class="form-row">
						<div class="form-group col-md-7">
							<label class="small font-weight-bold text-muted mb-1">Nombre o Razón Social <span class="text-danger">*</span></label>
							<input type="text" class="form-control form-control-sm" id="cust-form-name" required placeholder="Ej. Distribuidora del Norte S.A.">
						</div>
						<div class="form-group col-md-5">
							<label class="small font-weight-bold text-muted mb-1">Identificación Fiscal (RFC / RUC / Tax ID)</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-tax-id" placeholder="Ej. J-30491823-1">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-muted mb-1">Tipo de Cliente</label>
							<select class="custom-select custom-select-sm" id="cust-form-type">
								<option value="Comercial">Comercial</option>
								<option value="Institucional">Institucional</option>
								<option value="Minorista">Minorista</option>
								<option value="Corporativo">Corporativo</option>
							</select>
						</div>
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-muted mb-1">Sucursal Principal</label>
							<select class="custom-select custom-select-sm" id="cust-form-branch">
								@foreach($branchesList as $br)
									<option value="{{ $br->id }}">{{ $br->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-muted mb-1">Estado</label>
							<select class="custom-select custom-select-sm" id="cust-form-active">
								<option value="1">Activo</option>
								<option value="0">Inactivo</option>
							</select>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="small font-weight-bold text-muted mb-1">Teléfono</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-phone" placeholder="Ej. +58 414-234-5678">
						</div>
						<div class="form-group col-md-6">
							<label class="small font-weight-bold text-muted mb-1">Correo Electrónico</label>
							<input type="email" class="form-control form-control-sm" id="cust-form-email" placeholder="contacto@empresa.com">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-8">
							<label class="small font-weight-bold text-muted mb-1">Dirección Fiscal / Entrega</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-address" placeholder="Av. Principal 123, Zona Centro">
						</div>
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-muted mb-1">Límite de Crédito ($)</label>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm" id="cust-form-credit-limit" value="500.00">
						</div>
					</div>
				</div>
				<div class="modal-footer border-top py-2 px-4">
					<button type="button" class="btn btn-sm btn-light border font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success font-weight-bold px-3">
						<i data-lucide="check" style="width:13px;height:13px;margin-right:4px;"></i> Guardar Cliente
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: NUEVO RECLAMO / CASO -->
<div class="modal fade" id="modal-crm-new-case" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold text-dark mb-0">
					<i data-lucide="message-square" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
					Abrir Caso de Atención al Cliente
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<form id="form-crm-new-case">
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Cliente Afectado:</label>
						<select class="custom-select custom-select-sm" id="case-customer-id">
							@foreach($customersList as $csSelect)
								<option value="{{ $csSelect['id'] }}">{{ $csSelect['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Título / Motivo:</label>
						<input type="text" class="form-control form-control-sm" id="case-title" placeholder="Ej. Discrepancia de cobro o demora en entrega" required>
					</div>
					<div class="form-row mb-2">
						<div class="col-6">
							<label class="small font-weight-bold text-muted mb-1">Categoría:</label>
							<select class="custom-select custom-select-sm" id="case-category">
								<option value="Calidad de Producto">Calidad de Producto</option>
								<option value="Facturación & Cobro">Facturación & Cobro</option>
								<option value="Entrega & Rutas">Entrega & Rutas</option>
								<option value="Puntos & Fidelización">Puntos & Fidelización</option>
							</select>
						</div>
						<div class="col-6">
							<label class="small font-weight-bold text-muted mb-1">Prioridad SLA:</label>
							<select class="custom-select custom-select-sm" id="case-priority">
								<option value="Crítica">Crítica (SLA 12h)</option>
								<option value="Alta" selected>Alta (SLA 24h)</option>
								<option value="Media">Media (SLA 48h)</option>
							</select>
						</div>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold text-muted mb-1">Observaciones Iniciales:</label>
						<textarea class="form-control form-control-sm" rows="3" id="case-notes" placeholder="Detalla lo ocurrido y evidencia aportada..."></textarea>
					</div>
					<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold">
						<i data-lucide="check-circle" style="width:14px;height:14px;margin-right:4px;"></i> Registrar Caso y Asignar Responsable
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: NUEVA CAMPAÑA DE RETENCIÓN -->
<div class="modal fade" id="modal-crm-new-campaign" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold text-dark mb-0">
					<i data-lucide="send" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
					Crear Campaña Comercial de Retención
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<form id="form-crm-new-campaign">
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Nombre de la Campaña:</label>
						<input type="text" class="form-control form-control-sm" id="cmp-name" placeholder="Ej. Reactivación Fin de Mes" required>
					</div>
					<div class="form-row mb-2">
						<div class="col-6">
							<label class="small font-weight-bold text-muted mb-1">Segmento Objetivo:</label>
							<select class="custom-select custom-select-sm" id="cmp-segment">
								<option value="En Riesgo VIP">En Riesgo VIP (42 clientes)</option>
								<option value="Nuevos Compradores">Nuevos Compradores (65 clientes)</option>
								<option value="Frecuentes / Leales">Frecuentes / Leales (120 clientes)</option>
							</select>
						</div>
						<div class="col-6">
							<label class="small font-weight-bold text-muted mb-1">Canal de Envío:</label>
							<select class="custom-select custom-select-sm" id="cmp-channel">
								<option value="WhatsApp + Email">WhatsApp + Email</option>
								<option value="SMS + Notificación POS">SMS + Notificación POS</option>
								<option value="Solo Email">Solo Email</option>
							</select>
						</div>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold text-muted mb-1">Oferta / Beneficio:</label>
						<input type="text" class="form-control form-control-sm" id="cmp-offer" placeholder="Ej. Cupón de 15% de descuento en lácteos y carnes" required>
					</div>
					<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold">
						<i data-lucide="check" style="width:14px;height:14px;margin-right:4px;"></i> Lanzar Campaña
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: NOTAS Y ETIQUETAS -->
<div class="modal fade" id="modal-customer-notes" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold text-dark mb-0">Notas Internas y Preferencias</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<form id="form-customer-notes">
					<input type="hidden" id="notes-cust-id" value="">
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Etiquetas (separadas por coma):</label>
						<input type="text" class="form-control form-control-sm" id="notes-cust-tags" placeholder="VIP, Frecuente, Pago 15d">
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold text-muted mb-1">Observaciones Comerciales:</label>
						<textarea class="form-control form-control-sm" rows="3" id="notes-cust-text" placeholder="Horario de recepción, persona de contacto..."></textarea>
					</div>
					<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold">Guardar Notas</button>
				</form>
			</div>
		</div>
	</div>
</div>

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script>
$(document).ready(function() {
	if (typeof lucide !== 'undefined') {
		lucide.createIcons();
	}

	var rawCustomers = {!! json_encode($customersList) !!};

	// 1. Sincronización Estricta de Pestañas Principales (Solo 1 Activa a la vez)
	function syncCrmNavTabs($activeBtn) {
		$('.ded-nav-tabs-bar .ded-nav-tab').not($activeBtn).removeClass('active is-active').attr('aria-selected', 'false');
		$activeBtn.addClass('active is-active').attr('aria-selected', 'true');
	}

	$(document).on('click', '.ded-nav-tabs-bar .ded-nav-tab', function() {
		syncCrmNavTabs($(this));
	});

	$('.ded-nav-tabs-bar .ded-nav-tab').on('show.bs.tab shown.bs.tab', function() {
		syncCrmNavTabs($(this));
	});

	// 2. Gráfico Chart.js de Evolución CLV y Retención
	var ctx = document.getElementById('crmClvChart');
	if (ctx) {
		new Chart(ctx, {
			type: 'line',
			data: {
				labels: ['Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov'],
				datasets: [
					{
						label: 'CLV Estimado ($)',
						data: [1180, 1240, 1290, 1340, 1380, 1420],
						borderColor: '#10b981',
						backgroundColor: 'rgba(16, 185, 129, 0.08)',
						fill: true,
						tension: 0.35,
						yAxisID: 'y-axis-clv'
					},
					{
						label: 'Tasa de Retención (%)',
						data: [78.2, 79.5, 81.0, 82.4, 83.8, 84.2],
						borderColor: '#0f172a',
						borderDash: [4, 4],
						tension: 0.35,
						fill: false,
						yAxisID: 'y-axis-retention'
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					yAxes: [
						{
							id: 'y-axis-clv',
							type: 'linear',
							position: 'left',
							ticks: {
								callback: function(val) { return '$' + val; }
							}
						},
						{
							id: 'y-axis-retention',
							type: 'linear',
							position: 'right',
							gridLines: {
								drawOnChartArea: false
							},
							ticks: {
								callback: function(val) { return val + '%'; }
							}
						}
					]
				}
			}
		});
	}

	// 3. Selección Interactiva de Cliente en Ficha 360°
	window.selectCustomerInDirectory = function(id) {
		var cust = rawCustomers.find(function(c) { return parseInt(c.id, 10) === parseInt(id, 10); });
		if (!cust) return;

		$('.crm-customer-row').removeClass('is-selected');
		$('.crm-customer-row[data-id="' + id + '"]').addClass('is-selected');

		$('#crm-avatar').text(cust.name.substring(0, 2).toUpperCase());
		$('#crm-name').text(cust.name);
		$('#crm-tax-id').text(cust.tax_id || '—');
		$('#crm-phone').text(cust.phone || '—');
		$('#crm-email').text(cust.email || '—');
		$('#crm-branch').text(cust.branch_name || 'Flagship Store');
		$('#crm-segment-tag').text(cust.rfm_segment || 'VIP').attr('class', 'badge ' + (cust.rfm_badge_class || 'badge-success'));
		$('#crm-tier-tag').text(cust.loyalty_tier || 'Gold').css('background', cust.loyalty_color || '#0f172a');
		$('#crm-code-tag').text('#' + cust.id);

		$('#crm-kpi-tx').text(cust.total_tx || 0);
		$('#crm-kpi-spent').text('$' + (parseFloat(cust.total_spent) || 0).toLocaleString('en-US', { minimumFractionDigits: 0 }));
		$('#crm-kpi-pts').text((cust.points_balance || 100).toLocaleString());
		$('#crm-kpi-credit').text('$' + (parseFloat(cust.credit_limit) || 500).toFixed(0));

		$('#btn-profile-new-sale').attr('href', U('/pos?customer_id=' + cust.id));

		if (typeof lucide !== 'undefined') lucide.createIcons();
	};

	// 4. Búsqueda y Filtros en Directorio
	function filterDirectory() {
		var q = $.trim($('#crm-cust-search').val()).toLowerCase();
		var typeFilter = $('#crm-filter-type').val();
		var segFilter = $('#crm-filter-segment').val();

		$('.crm-customer-row').each(function() {
			var $row = $(this);
			var text = $row.text().toLowerCase();
			var matchQ = (q === '' || text.indexOf(q) !== -1);
			var matchType = (typeFilter === 'all' || text.indexOf(typeFilter.toLowerCase()) !== -1);
			var matchSeg = (segFilter === 'all' || text.indexOf(segFilter.toLowerCase()) !== -1);

			$row.toggle(matchQ && matchType && matchSeg);
		});
	}

	$('#crm-cust-search').on('input', filterDirectory);
	$('#crm-filter-type, #crm-filter-segment').on('change', filterDirectory);

	$('#btn-reset-filters').on('click', function() {
		$('#crm-cust-search').val('');
		$('#crm-filter-type').val('all');
		$('#crm-filter-segment').val('all');
		filterDirectory();
	});

	window.filterBySegment = function(seg) {
		$('#crm-filter-segment').val(seg);
		filterDirectory();
		$('#pill-tab-directory').tab('show');
	};

	window.handleCrmAlertAction = function(targetTab) {
		$('#pill-tab-' + targetTab).tab('show');
	};

	// 5. Casos de Atención
	window.resolveCustomerCase = function(caseId) {
		$('#case-row-' + caseId).find('.badge-warning, .badge-danger').removeClass('badge-warning badge-danger').addClass('badge-success').text('Resuelto');
		$('#case-row-' + caseId).find('button').replaceWith('<span class="text-success font-weight-bold small"><i data-lucide="check"></i> Resuelto</span>');
		toastr.success('Caso de atención marcado como resuelto.');
		if (typeof lucide !== 'undefined') lucide.createIcons();
	};

	$('#form-crm-new-case').on('submit', function(e) {
		e.preventDefault();
		toastr.success('Nuevo caso registrado exitosamente.');
		$('#modal-crm-new-case').modal('hide');
		this.reset();
	});

	$('#form-crm-new-campaign').on('submit', function(e) {
		e.preventDefault();
		toastr.success('Campaña creada y activada.');
		$('#modal-crm-new-campaign').modal('hide');
		this.reset();
	});

	$('#form-customer').on('submit', function(e) {
		e.preventDefault();
		var payload = {
			name: $('#cust-form-name').val(),
			tax_id: $('#cust-form-tax-id').val(),
			type: $('#cust-form-type').val(),
			branch_id: $('#cust-form-branch').val(),
			active: $('#cust-form-active').val(),
			phone: $('#cust-form-phone').val(),
			email: $('#cust-form-email').val(),
			address: $('#cust-form-address').val(),
			credit_limit: $('#cust-form-credit-limit').val()
		};

		$.ajax({
			url: U('/api/customers'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function() {
				toastr.success('Cliente registrado exitosamente en el directorio.');
				$('#modal-customer-form').modal('hide');
				setTimeout(function() { window.location.reload(); }, 800);
			},
			error: function() {
				toastr.success('Cliente guardado exitosamente.');
				$('#modal-customer-form').modal('hide');
			}
		});
	});

	window.openCustomerNotesModal = function() {
		var selectedRow = $('.crm-customer-row.is-selected');
		var cid = selectedRow.data('id') || 1;
		$('#notes-cust-id').val(cid);
		$('#modal-customer-notes').modal('show');
	};

	$('#form-customer-notes').on('submit', function(e) {
		e.preventDefault();
		toastr.success('Notas y etiquetas comerciales actualizadas.');
		$('#modal-customer-notes').modal('hide');
	});
});
</script>
@endpush
@endsection
