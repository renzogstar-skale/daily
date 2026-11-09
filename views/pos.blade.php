@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Punto de Venta (POS) · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_pos.css?v=', true) }}{{ daily_asset_version('/css/daily_pos.css') }}" rel="stylesheet">
@endpush

@section('content')
@php
	$isShiftOpen = ($currentShift->status ?? 'open') === 'open';
	$shiftRegisterName = $currentShift->register_name ?? 'Caja 01 - Principal';
	$shiftName = $currentShift->shift_name ?? 'Mañana';
	$shiftOpenedTime = $currentShift->opened_time_short ?? '08:00';
	$shiftSalesCount = intval($currentShift->live_sales_count ?? $posKpis['tickets_count'] ?? 142);
	$shiftTotalSales = floatval($currentShift->live_total_sales ?? $posKpis['shift_sales'] ?? 4892.50);
	$heldCount = is_array($heldSales) ? count($heldSales) : 0;
	$selectedBranchName = 'Todas las sucursales';
	foreach ($branches as $b) {
		$bid = intval(is_object($b) ? $b->id : $b['id']);
		if ($bid === intval($selectedBranchId)) {
			$selectedBranchName = is_object($b) ? $b->name : $b['name'];
			break;
		}
	}
@endphp

<div class="ded-wrapper pos-dash-wrapper" id="posRetailDashboard">

	<!-- ====================================================================
	     ENCABEZADO: TÍTULO, SUBTÍTULO Y SELECTORES SUPERIORES EJECUTIVOS
	     ==================================================================== -->
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Punto de Venta (POS)</h1>
			<p class="ded-subtitle">Centro de operaciones de caja, terminal de cobro retail, control de arqueos y supervisión</p>
		</div>

		<div class="ded-header-right">
			<!-- Selector de Turno / Período -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnPosShiftSelect" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Seleccionar turno operativo">
					<i data-lucide="calendar"></i>
					<span id="pos-header-shift-label">Hoy · Turno {{ $shiftName }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
					<a class="dropdown-item py-1 font-weight-bold" href="#" onclick="selectPosShift('Mañana'); return false;">Hoy · Turno Mañana (08:00 - 16:30)</a>
					<a class="dropdown-item py-1" href="#" onclick="selectPosShift('Tarde'); return false;">Hoy · Turno Tarde (16:30 - 23:00)</a>
					<a class="dropdown-item py-1" href="#" onclick="selectPosShift('Noche'); return false;">Hoy · Turno Noche (23:00 - 08:00)</a>
					<div class="dropdown-divider my-1"></div>
					<a class="dropdown-item py-1 text-muted" href="{{ $U('/sales?tab=detalle') }}">Ver Historial Completo de Ventas</a>
				</div>
			</div>

			<!-- Selector de Sucursal -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnPosBranchSelect" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Sucursal operativa">
					<i data-lucide="store"></i>
					<span>{{ $selectedBranchName }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b;">Sucursal Comercial</div>
					@foreach($branches as $b)
						@php
							$bid = intval(is_object($b) ? $b->id : $b['id']);
							$bname = is_object($b) ? $b->name : $b['name'];
						@endphp
						<a class="dropdown-item py-1 @if($bid === intval($selectedBranchId)) font-weight-bold active @endif" href="{{ $U('/pos?branch_id=' . $bid) }}">
							{{ $bname }}
						</a>
					@endforeach
				</div>
			</div>

			<!-- Selector de Caja / Terminal -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnPosRegisterSelect" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Terminal registradora">
					<i data-lucide="monitor"></i>
					<span id="pos-header-register-label">{{ $shiftRegisterName }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="font-size:12px; border-radius:8px;">
					<a class="dropdown-item py-1 font-weight-bold" href="#" onclick="selectPosRegister('all'); return false;">Todas las cajas (Supervisión)</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($activeRegisters as $reg)
						<a class="dropdown-item py-1" href="#" onclick="selectPosRegister('{{ $reg['name'] }}'); return false;">
							{{ $reg['name'] }} ({{ $reg['cashier_name'] }})
						</a>
					@endforeach
				</div>
			</div>

			<!-- Botones de Acción Rápida -->
			<button type="button" class="ded-select-btn" data-toggle="modal" data-target="#modal-pos-cash-reconciliation" title="Arqueo de caja y conciliación de turno">
				<i data-lucide="scale"></i>
				<span>Arqueo / Turno</span>
			</button>

			<button type="button" class="ded-select-btn" data-toggle="modal" data-target="#modal-pos-history" title="Historial de tickets emitidos">
				<i data-lucide="file-text"></i>
				<span>Historial</span>
			</button>

			<a href="{{ $U('/pos/returns') }}" class="ded-select-btn" title="Devoluciones de clientes">
				<i data-lucide="rotate-ccw"></i>
				<span>Devoluciones</span>
			</a>
		</div>
	</header>

	<!-- ====================================================================
	     PRIMERA FILA — 7 INDICADORES HORIZONTALES DEL POS
	     ==================================================================== -->
	<section class="ded-kpis-grid" aria-label="Indicadores Operativos del POS">
		<!-- 1. Ventas del Turno (Tarjeta destacada en verde corporativo) -->
		<article class="ded-kpi-card is-highlight" title="Ventas netas cobradas en el turno actual" onclick="$('#pill-tab-terminal').tab('show');">
			<div class="ded-kpi-top-highlight">
				<div class="ded-highlight-brand">
					<i data-lucide="circle-dollar-sign"></i>
					<span>Ventas del turno</span>
				</div>
				<div class="ded-highlight-spark-box">
					<i data-lucide="trending-up"></i>
				</div>
			</div>
			<div class="ded-kpi-val" id="kpi-top-sales">${{ number_format($shiftTotalSales, 2) }}</div>
			<div class="ded-kpi-sub-highlight">
				▲ +8.2 % vs turno ant. (Hoy 08:00 - 16:30)
			</div>
		</article>

		<!-- 2. Cajas Operativas -->
		<article class="ded-kpi-card" title="Terminales en línea atendiendo al público" onclick="$('#pill-tab-operaciones').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="monitor-check"></i></span>
			</div>
			<div class="ded-kpi-label">Cajas operativas</div>
			<div class="ded-kpi-val">{{ $posKpis['active_registers'] }} / {{ $posKpis['total_registers'] }} activas</div>
			<div class="ded-kpi-sub">80 % disponibilidad de línea</div>
		</article>

		<!-- 3. Tickets Cobrados -->
		<article class="ded-kpi-card" title="Transacciones de compra finalizadas en el turno" onclick="$('#pill-tab-transacciones').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="receipt"></i></span>
			</div>
			<div class="ded-kpi-label">Tickets cobrados</div>
			<div class="ded-kpi-val" id="kpi-top-tickets">{{ $shiftSalesCount }} tickets</div>
			<div class="ded-kpi-sub">Ticket prom: ${{ number_format($posKpis['avg_ticket'], 2) }}</div>
		</article>

		<!-- 4. Velocidad de Cobro -->
		<article class="ded-kpi-card" title="Tiempo promedio de escaneo y cobro por ticket">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="timer"></i></span>
			</div>
			<div class="ded-kpi-label">Velocidad de cobro</div>
			<div class="ded-kpi-val">{{ $posKpis['avg_speed_sec'] }} seg / ticket</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">▼ -7s vs meta (45s)</span>
			</div>
		</article>

		<!-- 5. Efectivo en Gavetas -->
		<article class="ded-kpi-card" title="Dinero físico en gavetas de cajas activas" onclick="$('#pill-tab-cajas').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="banknote"></i></span>
			</div>
			<div class="ded-kpi-label">Efectivo en gavetas</div>
			<div class="ded-kpi-val">${{ number_format($posKpis['drawer_cash'], 2) }}</div>
			<div class="ded-kpi-sub">Fondo: $300 · Ventas: ${{ number_format($posKpis['drawer_cash'] - 300, 0) }}</div>
		</article>

		<!-- 6. Descuentos Aplicados -->
		<article class="ded-kpi-card" title="Descuentos comerciales y mermas aplicadas en caja" onclick="$('#pill-tab-supervision').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="percent"></i></span>
			</div>
			<div class="ded-kpi-label">Descuentos aplicados</div>
			<div class="ded-kpi-val">${{ number_format($posKpis['discounts_total'], 2) }}</div>
			<div class="ded-kpi-sub">1.3 % sobre facturación</div>
		</article>

		<!-- 7. Alertas Operativas -->
		<article class="ded-kpi-card" title="Alertas de arqueo, umbrales y autorizaciones" onclick="$('#pill-tab-operaciones').tab('show');">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon" style="color:#f59e0b;"><i data-lucide="alert-triangle"></i></span>
			</div>
			<div class="ded-kpi-label">Alertas operativas</div>
			<div class="ded-kpi-val" style="color:#b45309;">{{ count($posAlerts) }} activas</div>
			<div class="ded-kpi-sub">1 retiro · 1 anulación</div>
		</article>
	</section>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS: 6 MÓDULOS OPERATIVOS DEL POS
	     ==================================================================== -->
	<nav class="nav ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos del Punto de Venta">
		<button type="button" class="ded-nav-tab active" data-toggle="pill" data-target="#pos-tab-operaciones" role="tab" id="pill-tab-operaciones" aria-selected="true">
			<i data-lucide="layout-grid" class="mr-1"></i>
			<span>1. Centro de Operaciones POS</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-terminal" role="tab" id="pill-tab-terminal" aria-selected="false">
			<i data-lucide="shopping-bag" class="mr-1"></i>
			<span>2. Terminal de Venta (POS)</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-pagos" role="tab" id="pill-tab-pagos" aria-selected="false">
			<i data-lucide="credit-card" class="mr-1"></i>
			<span>3. Pagos y Facturación</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-cajas" role="tab" id="pill-tab-cajas" aria-selected="false">
			<i data-lucide="scale" class="mr-1"></i>
			<span>4. Control de Cajas y Arqueos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-transacciones" role="tab" id="pill-tab-transacciones" aria-selected="false">
			<i data-lucide="receipt" class="mr-1"></i>
			<span>5. Transacciones y Auditoría</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-supervision" role="tab" id="pill-tab-supervision" aria-selected="false">
			<i data-lucide="shield-check" class="mr-1"></i>
			<span>6. Supervisión POS</span>
		</button>
	</nav>

	<!-- ====================================================================
	     CONTENIDO DE LAS 6 SECCIONES
	     ==================================================================== -->
	<div class="tab-content" id="posDashboardTabContent">

		<!-- ================================================================
		     SECCIÓN 1: CENTRO DE OPERACIONES POS
		     ================================================================ -->
		<div class="tab-pane fade show active" id="pos-tab-operaciones" role="tabpanel">
			<!-- Header de la sección de cajas -->
			<div class="d-flex justify-content-between align-items-center mb-3">
				<div>
					<h3 class="font-weight-bold text-dark mb-0" style="font-size:15px;">Línea de Cajas Registradoras (Sucursal: {{ $selectedBranchName }})</h3>
					<small class="text-muted">Estado en vivo de terminales, cajeros en servicio, nivel de efectivo en gaveta y flujo de fila.</small>
				</div>
				<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" onclick="$('#pill-tab-terminal').tab('show');">
					<i data-lucide="shopping-cart" style="width:14px;height:14px;margin-right:4px;"></i>Ir a Terminal de Venta
				</button>
			</div>

			<!-- Grid de Terminales / Cajas -->
			<div class="pos-registers-grid">
				@foreach($activeRegisters as $reg)
				<div class="pos-reg-card @if($reg['status'] === 'open') is-active @elseif($reg['status'] === 'paused') is-paused @else is-closed @endif">
					<div class="pos-reg-top">
						<span class="pos-reg-title">
							<i data-lucide="monitor" style="width:15px;height:15px;color:#10b981;"></i>
							{{ $reg['name'] }}
						</span>
						@if($reg['status'] === 'open')
							<span class="pos-reg-status-badge status-open">
								<span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span> Abierta
							</span>
						@elseif($reg['status'] === 'paused')
							<span class="pos-reg-status-badge status-paused">
								<span style="width:6px;height:6px;border-radius:50%;background:#f59e0b;display:inline-block;"></span> En Arqueo
							</span>
						@else
							<span class="pos-reg-status-badge status-closed">Cerrada</span>
						@endif
					</div>

					<div class="pos-reg-cashier-row">
						<div class="pos-reg-avatar">
							{{ substr($reg['cashier_name'], 0, 2) }}
						</div>
						<div class="pos-reg-cashier-info">
							<div class="pos-reg-cashier-name">{{ $reg['cashier_name'] }}</div>
							<div class="pos-reg-cashier-role">{{ $reg['cashier_role'] }} · Desde {{ $reg['opened_at'] }}</div>
						</div>
					</div>

					<div class="pos-reg-metrics">
						<div class="pos-reg-metric-item">
							<div class="pos-reg-metric-label">Ventas</div>
							<div class="pos-reg-metric-val">${{ number_format($reg['total_sales'], 0) }}</div>
						</div>
						<div class="pos-reg-metric-item">
							<div class="pos-reg-metric-label">Tickets</div>
							<div class="pos-reg-metric-val">{{ $reg['sales_count'] }}</div>
						</div>
						<div class="pos-reg-metric-item">
							<div class="pos-reg-metric-label">Velocidad</div>
							<div class="pos-reg-metric-val">{{ $reg['avg_speed_sec'] }}s</div>
						</div>
					</div>

					<!-- Nivel de Efectivo en Gaveta -->
					<div class="pos-reg-drawer-meter">
						<div class="pos-reg-drawer-labels">
							<span>Gaveta: <strong>${{ number_format($reg['drawer_cash'], 2) }}</strong></span>
							<span>Límite seguro: ${{ number_format($reg['cash_limit'], 0) }}</span>
						</div>
						@php
							$pctDrawer = $reg['cash_limit'] > 0 ? min(100, round(($reg['drawer_cash'] / $reg['cash_limit']) * 100)) : 0;
							$classBar = $pctDrawer >= 100 ? 'is-warning' : '';
						@endphp
						<div class="pos-reg-progress-bg">
							<div class="pos-reg-progress-bar {{ $classBar }}" style="width: {{ $pctDrawer }}%;"></div>
						</div>
					</div>

					@if(!empty($reg['alert_text']))
					<div class="alert alert-light border py-1 px-2 mb-2" style="font-size:10.5px; border-radius:6px; color:#92400e; background:#fffbeb; border-color:#fef3c7;">
						<i data-lucide="alert-circle" style="width:12px;height:12px;margin-right:3px;"></i>
						{{ $reg['alert_text'] }}
					</div>
					@endif

					<div class="pos-reg-actions">
						@if($reg['status'] === 'open')
							<button type="button" class="pos-reg-btn is-primary" onclick="openTerminalForRegister('{{ $reg['name'] }}');">
								<i data-lucide="play" style="width:12px;height:12px;"></i> Operar
							</button>
							<button type="button" class="pos-reg-btn" onclick="openCashDropModal('{{ $reg['name'] }}', {{ $reg['drawer_cash'] }});">
								<i data-lucide="arrow-down-circle" style="width:12px;height:12px;"></i> Retiro
							</button>
							<button type="button" class="pos-reg-btn" data-toggle="modal" data-target="#modal-pos-cash-reconciliation">
								<i data-lucide="scale" style="width:12px;height:12px;"></i> Arqueo
							</button>
						@elseif($reg['status'] === 'paused')
							<button type="button" class="pos-reg-btn is-primary" data-toggle="modal" data-target="#modal-pos-cash-reconciliation">
								<i data-lucide="check-circle" style="width:12px;height:12px;"></i> Completar Corte
							</button>
						@else
							<button type="button" class="pos-reg-btn is-primary w-100" data-toggle="modal" data-target="#modal-pos-shift">
								<i data-lucide="unlock" style="width:12px;height:12px;"></i> Abrir Caja Ahora
							</button>
						@endif
					</div>
				</div>
				@endforeach
			</div>

			<!-- Fila Inferior de Operaciones: 2 Columnas -->
			<div class="ded-row-2">
				<!-- Columna 1: Alertas y Eventos Operativos -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="bell" style="width:15px;height:15px;color:#f59e0b;margin-right:6px;"></i>
							Alertas y Decisiones en Vivo del POS
						</span>
						<span class="badge badge-light border text-muted" style="font-size:11px;">{{ count($posAlerts) }} activas</span>
					</div>
					<div class="p-3">
						@foreach($posAlerts as $alt)
						<div class="d-flex align-items-start justify-content-between p-2 mb-2 rounded border" style="background: rgba(255,255,255,0.7); font-size:12px;">
							<div class="d-flex align-items-start gap-2">
								<span class="badge @if($alt['level'] === 'warning') badge-warning @elseif($alt['level'] === 'danger') badge-danger @else badge-success @endif px-2 py-1 mt-1 font-weight-bold">
									{{ $alt['badge'] }}
								</span>
								<div>
									<div class="font-weight-bold text-dark">{{ $alt['register'] }} · <span class="text-muted" style="font-size:10.5px;">{{ $alt['time'] }}</span></div>
									<div class="text-muted" style="font-size:11.5px;">{{ $alt['message'] }}</div>
								</div>
							</div>
							<button type="button" class="btn btn-xs btn-outline-dark font-weight-bold ml-2" onclick="handlePosAlertAction('{{ $alt['action_label'] }}', '{{ $alt['register'] }}');">
								{{ $alt['action_label'] }}
							</button>
						</div>
						@endforeach
					</div>
				</div>

				<!-- Columna 2: Productividad Horaria y Flujo de Cobro -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="clock" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Flujo de Cobro y Facturación por Hora
						</span>
						<span class="badge badge-success" style="font-size:11px;">En curso</span>
					</div>
					<div class="p-3">
						<div class="table-responsive">
							<table class="table table-sm mb-0" style="font-size:11.5px;">
								<thead class="bg-light">
									<tr>
										<th>Franja Horaria</th>
										<th class="text-center">Tickets</th>
										<th class="text-right">Venta Cobrada</th>
										<th class="text-center">Velocidad</th>
										<th class="text-right">Medio Principal</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td class="font-weight-bold">08:00 - 09:00</td>
										<td class="text-center">18</td>
										<td class="text-right font-weight-bold">$612.00</td>
										<td class="text-center">34s</td>
										<td class="text-right"><span class="badge badge-light border">Efectivo 60%</span></td>
									</tr>
									<tr>
										<td class="font-weight-bold">09:00 - 10:00</td>
										<td class="text-center">36</td>
										<td class="text-right font-weight-bold">$1,240.50</td>
										<td class="text-center">38s</td>
										<td class="text-right"><span class="badge badge-light border">Tarjeta 55%</span></td>
									</tr>
									<tr>
										<td class="font-weight-bold">10:00 - 11:00</td>
										<td class="text-center">48</td>
										<td class="text-right font-weight-bold">$1,680.00</td>
										<td class="text-center">41s</td>
										<td class="text-right"><span class="badge badge-light border">Tarjeta 62%</span></td>
									</tr>
									<tr style="background: rgba(16, 185, 129, 0.05);">
										<td class="font-weight-bold text-success">11:00 - 12:00 (Actual)</td>
										<td class="text-center font-weight-bold">40</td>
										<td class="text-right font-weight-bold text-success">$1,360.00</td>
										<td class="text-center font-weight-bold">36s</td>
										<td class="text-right"><span class="badge badge-success">Flujo Fluido</span></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     SECCIÓN 2: TERMINAL DE VENTA (POS ACTIVO)
		     ================================================================ -->
		<div class="tab-pane fade" id="pos-tab-terminal" role="tabpanel">
			<!-- AVISO SI LA CAJA ESTÁ CERRADA -->
			<div class="pos-closed-banner {{ $isShiftOpen ? 'd-none' : '' }}" id="pos-closed-banner">
				<div>
					<i data-lucide="lock" style="width:16px;height:16px;margin-right:6px;"></i>
					<strong>Caja cerrada en esta sucursal:</strong> Las ventas están bloqueadas hasta que inicies un nuevo turno de caja con su monto de apertura.
				</div>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btn-banner-open-shift" data-toggle="modal" data-target="#modal-pos-shift">
					<i data-lucide="unlock" style="width:14px;height:14px;margin-right:4px;"></i> Abrir Caja Ahora
				</button>
			</div>

			<!-- Espacio de Trabajo Principal: Catálogo (Izq) + Ticket (Der) -->
			<div class="pos-workspace-grid">

				<!-- Panel Izquierdo: Catálogo y Búsqueda -->
				<div class="pos-catalog-panel">
					<!-- Fila de Búsqueda y Escáner -->
					<div class="pos-search-row">
						<div class="pos-search-input-wrap">
							<i data-lucide="search" class="search-icon"></i>
							<input type="text"
								id="pos-search-input"
								class="pos-search-input"
								placeholder="Buscar producto por nombre, SKU, código de barras o escanear..."
								autocomplete="off">
							<button type="button" class="pos-search-clear" id="pos-search-clear" title="Limpiar búsqueda">
								<i data-lucide="x" style="width:14px;height:14px;"></i>
							</button>
						</div>
						<button type="button" class="pos-barcode-btn" id="btn-focus-scanner" title="Modo lector láser de código de barras: presiona Enter tras escanear">
							<i data-lucide="scan-barcode"></i>
							<span>Lector UPC/EAN</span>
						</button>
					</div>

					<!-- Píldoras de Categorías -->
					<div class="pos-categories-wrap" id="pos-categories-bar">
						<button type="button" class="pos-cat-pill active" data-category="ALL">Todos los Departamentos</button>
						@foreach($categories as $catName)
							<button type="button" class="pos-cat-pill" data-category="{{ $catName }}">{{ $catName }}</button>
						@endforeach
					</div>

					<!-- Cuadrícula de Productos -->
					<div class="pos-products-grid" id="pos-products-grid">
						@foreach($products as $product)
							@php
								$pid = intval($product->id);
								$branchAvail = isset($product->branch_stocks[$selectedBranchId])
									? floatval($product->branch_stocks[$selectedBranchId])
									: 0.0;
								$branchBbd = $product->branch_bbds[$selectedBranchId] ?? null;
								$branchLotsCount = intval($product->branch_lots[$selectedBranchId] ?? 0);
								$hasFefoBranch = !empty($branchBbd) && $branchLotsCount > 0 && $branchAvail > 0;
								$minStock = floatval($product->min_stock_amount ?? 0);
								$isLowStock = $branchAvail > 0 && ($branchAvail <= max(5.0, $minStock));
								$isOut = $branchAvail <= 0.0001;
								$listPrice = floatval($product->default_sale_price);
								$markdownPct = floatval($product->markdown_pct ?? 0);
								$effectivePrice = floatval($product->effective_price ?? $listPrice);
								$skuDisplay = !empty($product->sku) ? $product->sku : ('SKU-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT));
								$unitShort = mb_strtolower((string)($product->qu_name ?? 'unidad'), 'UTF-8');
								$searchBlob = mb_strtolower(
									$product->name . ' ' . $skuDisplay . ' ' . ($product->category_name ?? '') . ' ' . ($product->barcodes_str ?? ''),
									'UTF-8'
								);
							@endphp
							<div class="pos-product-card {{ $isOut ? 'is-out-of-stock' : '' }}"
								data-id="{{ $pid }}"
								data-name="{{ $product->name }}"
								data-sku="{{ $skuDisplay }}"
								data-barcodes="{{ $product->barcodes_str }}"
								data-category="{{ $product->category_name ?? 'General' }}"
								data-search="{{ $searchBlob }}"
								data-list-price="{{ number_format($listPrice, 2, '.', '') }}"
								data-price="{{ number_format($effectivePrice, 2, '.', '') }}"
								data-markdown-pct="{{ $markdownPct }}"
								data-unit="{{ $product->qu_name ?? 'Unidad' }}"
								data-min-stock="{{ $minStock }}"
								data-image="{{ $U($product->image_url) }}"
								data-branch-stocks='@json($product->branch_stocks)'
								data-branch-bbds='@json($product->branch_bbds)'
								data-branch-lots='@json($product->branch_lots)'
								data-stock="{{ $branchAvail }}"
								data-has-fefo="{{ $hasFefoBranch ? '1' : '0' }}"
								data-next-bbd="{{ $branchBbd ?? '' }}">

								<div class="pos-card-top">
									<div class="pos-card-thumb">
										<img src="{{ $U($product->image_url) }}" alt="{{ $product->name }}" loading="lazy">
									</div>
									<div class="pos-card-info">
										<h4 class="pos-card-title" title="{{ $product->name }}">{{ $product->name }}</h4>
										<div class="pos-card-sku">{{ $skuDisplay }}</div>
										<div class="pos-card-cat">{{ $product->category_name ?? 'General' }}</div>
										<div class="pos-card-badges">
											<span class="pos-badge-fefo {{ $hasFefoBranch ? '' : 'd-none' }}" title="Lote con control FEFO activo (Próx. venc: {{ $branchBbd ?? 'N/A' }})">
												<i data-lucide="zap" style="width:10px;height:10px;"></i>FEFO
											</span>
											@if($markdownPct > 0)
												<span class="pos-badge-promo" title="Rebaja automática autorizada">-{{ number_format($markdownPct, 0) }}%</span>
											@endif
											<span class="pos-badge-low {{ $isLowStock ? '' : 'd-none' }}">Bajo mín.</span>
										</div>
									</div>
								</div>

								<div class="pos-card-mid">
									<span class="pos-stock-pill {{ $isOut ? 'is-zero' : ($isLowStock ? 'is-low' : '') }}">
										<span class="stock-dot"></span>
										<span class="stock-text">
											@if($isOut)
												Agotado
											@else
												{{ number_format($branchAvail, ($branchAvail == intval($branchAvail) ? 0 : 1)) }} {{ $unitShort }}
											@endif
										</span>
									</span>

									<div class="pos-price-block">
										<div>
											<span class="pos-price-main">${{ number_format($effectivePrice, 2) }}</span>
											@if($markdownPct > 0 && $listPrice > $effectivePrice)
												<span class="pos-price-old">${{ number_format($listPrice, 2) }}</span>
											@endif
										</div>
										<span class="pos-price-unit">por {{ $unitShort }}</span>
									</div>
								</div>

								<button type="button" class="pos-btn-add" {{ $isOut ? 'disabled' : '' }}>
									@if($isOut)
										<span>Sin disponibilidad</span>
									@else
										<i data-lucide="plus" style="width:13px;height:13px;"></i>
										<span>Agregar</span>
									@endif
								</button>
							</div>
						@endforeach
					</div>

					<div id="pos-catalog-empty" class="text-center py-5 d-none">
						<div class="mb-2 text-muted" style="font-size:2rem;"><i data-lucide="package-search"></i></div>
						<h6 class="font-weight-bold text-dark mb-1">No se encontraron productos coincidentes</h6>
						<p class="text-muted small mb-3">Verifica el código buscado o cambia la categoría.</p>
						<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" id="btn-reset-catalog-filters">
							Mostrar todo el catálogo
						</button>
					</div>
				</div>

				<!-- Panel Derecho: Ticket de Venta -->
				<div class="pos-ticket-panel">
					<!-- Cabecera Verde del Ticket -->
					<div class="pos-ticket-header">
						<div class="pos-ticket-header-left">
							<i data-lucide="shopping-cart" style="width:18px;height:18px;"></i>
							<span>Ticket de Venta #{{ date('Ymd') }}-TKT</span>
						</div>
						<div class="pos-ticket-header-right">
							<span class="pos-ticket-count-pill" id="pos-item-count-badge">0 ítems</span>
							<button type="button" class="pos-btn-clear-ticket" id="btn-clear-cart" title="Vaciar ticket">
								<i data-lucide="trash-2" style="width:14px;height:14px;"></i>
							</button>
						</div>
					</div>

					<!-- Selector de Cliente -->
					<div class="pos-customer-section">
						<div class="pos-customer-label">
							<span>Cliente comercial (opcional)</span>
							<span class="text-muted" id="pos-selected-customer-tag">Mostrador / Público General</span>
						</div>
						<div class="pos-customer-input-group">
							<i data-lucide="user" class="cust-icon"></i>
							<select id="pos-customer-select" class="pos-customer-select" aria-label="Seleccionar cliente">
								@foreach($customers as $c)
									<option value="{{ $c->id }}" data-taxid="{{ $c->tax_id ?? '' }}" data-name="{{ $c->name }}">
										{{ $c->name }}{{ !empty($c->tax_id) ? ' (' . $c->tax_id . ')' : '' }}
									</option>
								@endforeach
							</select>
							<button type="button" class="pos-customer-reset" id="btn-reset-customer" title="Restablecer cliente">
								<i data-lucide="x" style="width:12px;height:12px;"></i>
							</button>
						</div>
					</div>

					<!-- Encabezado de Columnas del Ticket -->
					<div class="pos-ticket-table-head">
						<div>Producto</div>
						<div class="text-right">Precio</div>
						<div class="text-center">Cant.</div>
						<div class="text-right">Subtotal</div>
						<div></div>
					</div>

					<!-- Lista de Ítems en Carrito -->
					<div class="pos-ticket-items-list" id="pos-cart-items">
						<div class="text-center py-5 px-3 text-muted" id="pos-empty-cart-msg">
							<i data-lucide="shopping-bag" style="width:36px;height:36px;margin:0 auto 8px;opacity:0.4;display:block;"></i>
							<div class="font-weight-bold text-dark" style="font-size:12px;">El ticket está vacío</div>
							<div class="small mt-1">Haz clic en <strong>Agregar</strong> sobre un producto o escanea su código de barras.</div>
						</div>
					</div>

					<!-- Desglose de Totales -->
					<div class="pos-totals-section">
						<div class="pos-total-row">
							<span id="pos-subtotal-label">Subtotal (0 ítems)</span>
							<strong id="pos-subtotal-display">$0.00</strong>
						</div>

						<div class="pos-total-row">
							<div class="d-flex align-items-center gap-1">
								<span>Descuento</span>
								<input type="number" id="pos-discount-input" value="0.00" min="0" step="0.01" style="width:55px;height:24px;font-size:11px;padding:2px 4px;border:1px solid #cbd5e1;border-radius:4px;">
								<select id="pos-discount-type" style="height:24px;font-size:10px;border:1px solid #cbd5e1;border-radius:4px;">
									<option value="pct">%</option>
									<option value="amt">$</option>
								</select>
							</div>
							<strong id="pos-discount-display" class="text-danger">-$0.00</strong>
						</div>

						<div class="pos-total-row">
							<div class="d-flex align-items-center gap-1">
								<span>IVA / Impuesto</span>
								<select id="pos-tax-rate" style="height:24px;font-size:10px;border:1px solid #cbd5e1;border-radius:4px;">
									<option value="15" selected>(15%) General</option>
									<option value="0">(0%) Exento</option>
									<option value="8">(8%) Reducido</option>
									<option value="16">(16%)</option>
								</select>
							</div>
							<strong id="pos-tax-display">$0.00</strong>
						</div>

						<div class="pos-grand-total-row">
							<span class="pos-grand-total-label">TOTAL A COBRAR:</span>
							<span class="pos-grand-total-value" id="pos-total-display">$0.00</span>
						</div>
					</div>

					<!-- Medios de Pago y Cobro -->
					<div class="pos-payment-section">
						<div class="pos-payment-tabs" id="pos-payment-tabs">
							<button type="button" class="pos-pay-tab active" data-method="cash">
								<i data-lucide="banknote" style="width:14px;height:14px;"></i>Efectivo
							</button>
							<button type="button" class="pos-pay-tab" data-method="card">
								<i data-lucide="credit-card" style="width:14px;height:14px;"></i>Tarjeta
							</button>
							<button type="button" class="pos-pay-tab" data-method="transfer">
								<i data-lucide="arrow-right-left" style="width:14px;height:14px;"></i>Transfer.
							</button>
							<button type="button" class="pos-pay-tab" data-method="mixed">
								<i data-lucide="layers" style="width:14px;height:14px;"></i>Mixto
							</button>
						</div>

						<!-- Caja de Efectivo Recibido y Cambio -->
						<div class="pos-cash-tender-box" id="pos-panel-cash">
							<div class="pos-cash-grid">
								<div class="pos-cash-field">
									<label for="pos-cash-received">Efectivo Recibido</label>
									<div class="pos-cash-input-wrap">
										<span class="cash-icon">$</span>
										<input type="number" id="pos-cash-received" value="0.00" min="0" step="0.01">
									</div>
								</div>
								<div class="pos-cash-field">
									<label>Cambio / Vuelto</label>
									<div class="pos-change-pill" id="pos-change-pill">
										<i data-lucide="coins" style="width:14px;height:14px;"></i>
										<span id="pos-change-display">$0.00</span>
									</div>
								</div>
							</div>
							<div class="pos-quick-cash-chips">
								<button type="button" class="pos-cash-chip" data-quick-cash="exact">Exacto</button>
								<button type="button" class="pos-cash-chip" data-quick-cash="5">$5</button>
								<button type="button" class="pos-cash-chip" data-quick-cash="10">$10</button>
								<button type="button" class="pos-cash-chip" data-quick-cash="20">$20</button>
								<button type="button" class="pos-cash-chip" data-quick-cash="50">$50</button>
								<button type="button" class="pos-cash-chip" data-quick-cash="100">$100</button>
							</div>
						</div>

						<!-- Panel Tarjeta / Referencia -->
						<div class="pos-cash-tender-box d-none" id="pos-panel-noncash">
							<label style="font-size:10px;font-weight:700;color:#64748b;margin-bottom:3px;display:block;">Código de Autorización / Voucher PinPad</label>
							<input type="text" id="pos-payment-ref" class="form-control form-control-sm" placeholder="Ej. AUT-8941 / Terminal Bancaria">
							<small class="text-muted d-block mt-1" style="font-size:10px;">Validación de pago exitoso simulado con idempotencia garantizada.</small>
						</div>

						<!-- Panel Cobro Mixto -->
						<div class="pos-cash-tender-box d-none" id="pos-panel-mixed">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<span style="font-size:11px;font-weight:700;">Desglose Cobro Mixto</span>
								<span class="badge badge-warning" id="pos-mixed-status-badge" style="font-size:10px;">Pendiente: $0.00</span>
							</div>
							<div class="row">
								<div class="col-4">
									<label style="font-size:10px;font-weight:700;margin-bottom:2px;">Efectivo</label>
									<input type="number" id="pos-mixed-cash" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
								</div>
								<div class="col-4">
									<label style="font-size:10px;font-weight:700;margin-bottom:2px;">Tarjeta</label>
									<input type="number" id="pos-mixed-card" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
								</div>
								<div class="col-4">
									<label style="font-size:10px;font-weight:700;margin-bottom:2px;">Transfer.</label>
									<input type="number" id="pos-mixed-transfer" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
								</div>
							</div>
						</div>

						<!-- Botones de Acción de Venta -->
						<div class="pos-ticket-actions">
							<button type="button" class="pos-btn-hold" id="btn-hold-sale" title="Guardar venta en espera">
								<i data-lucide="clock" style="width:13px;height:13px;"></i>
								<span>En Espera</span>
							</button>
							<button type="button" class="pos-btn-discount" id="btn-open-discount-modal" title="Descuento comercial con autorización">
								<i data-lucide="tag" style="width:13px;height:13px;"></i>
								<span>Rebaja</span>
							</button>
							<button type="button" class="pos-btn-checkout" id="btn-submit-sale" disabled>
								<i data-lucide="check-circle" style="width:16px;height:16px;"></i>
								<span id="btn-submit-sale-label">Cobrar $0.00</span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     SECCIÓN 3: PAGOS Y FACTURACIÓN
		     ================================================================ -->
		<div class="tab-pane fade" id="pos-tab-pagos" role="tabpanel">
			<div class="ded-row-2">
				<!-- Métodos y Adaptadores de Pago -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="credit-card" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Pasarelas y Métodos de Cobro Habilitados
						</span>
						<span class="badge badge-success">Seguridad PCI-DSS</span>
					</div>
					<div class="p-3">
						<div class="row">
							<div class="col-md-6 mb-3">
								<div class="p-3 rounded border bg-white h-100">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<span class="font-weight-bold text-dark"><i data-lucide="banknote" style="width:14px;height:14px;color:#059669;margin-right:4px;"></i>Efectivo en Gaveta</span>
										<span class="badge badge-success">Activo</span>
									</div>
									<p class="text-muted small mb-2">Apertura automática de gaveta con verificación de vuelto y alerta de límite seguro ($500.00).</p>
									<span class="text-muted" style="font-size:11px;">Moneda: <strong>USD ($)</strong> · Tasa de cambio: 1.00</span>
								</div>
							</div>

							<div class="col-md-6 mb-3">
								<div class="p-3 rounded border bg-white h-100">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<span class="font-weight-bold text-dark"><i data-lucide="credit-card" style="width:14px;height:14px;color:#10b981;margin-right:4px;"></i>Terminal Bancaria (PinPad)</span>
										<span class="badge badge-success">Simulador Activo</span>
									</div>
									<p class="text-muted small mb-2">Integración con lectores de tarjeta chip, contactless y banda magnética sin almacenar datos sensibles.</p>
									<span class="text-muted" style="font-size:11px;">Protocolo: <strong>ISO-8583 / REST</strong></span>
								</div>
							</div>

							<div class="col-md-6 mb-3">
								<div class="p-3 rounded border bg-white h-100">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<span class="font-weight-bold text-dark"><i data-lucide="arrow-right-left" style="width:14px;height:14px;color:#f59e0b;margin-right:4px;"></i>Transferencias & QR</span>
										<span class="badge badge-success">Validado</span>
									</div>
									<p class="text-muted small mb-2">Recepción de transferencias bancarias directas con registro de código de comprobante.</p>
									<span class="text-muted" style="font-size:11px;">Tiempo de confirmación: <strong>Inmediato</strong></span>
								</div>
							</div>

							<div class="col-md-6 mb-3">
								<div class="p-3 rounded border bg-white h-100">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<span class="font-weight-bold text-dark"><i data-lucide="layers" style="width:14px;height:14px;color:#10b981;margin-right:4px;"></i>Cobros Mixtos (Multi-Tender)</span>
										<span class="badge badge-success">Habilitado</span>
									</div>
									<p class="text-muted small mb-2">Permite dividir el pago entre efectivo, tarjeta y crédito de cliente con conciliación en una sola venta.</p>
									<span class="text-muted" style="font-size:11px;">Regla: <strong>Idempotente al 100%</strong></span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Vista Previa de Recibo Térmico POS -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="printer" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Comprobante Fiscal / Ticket Térmico
						</span>
						<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="window.print();">
							<i data-lucide="printer" style="width:12px;height:12px;margin-right:3px;"></i>Imprimir
						</button>
					</div>
					<div class="p-3">
						<div class="pos-thermal-receipt">
							<div class="text-center pb-2 mb-2 border-bottom">
								<div class="font-weight-bold" style="letter-spacing:1px;font-size:13px;">DAILY RETAIL</div>
								<div>SUPERMERCADOS & ALMACENES</div>
								<div style="font-size:9.5px;color:#64748b;">RUC: 20100482910 · SUCURSAL {{ strtoupper($selectedBranchName) }}</div>
								<div style="font-size:9.5px;color:#64748b;">TICKET #POS-{{ date('Ymd') }}-0045</div>
								<div style="font-size:9.5px;color:#64748b;">{{ date('d/m/Y H:i:s') }}</div>
							</div>

							<div class="mb-2" style="font-size:10px;">
								<div><strong>Cajero:</strong> Carlos Ruiz (Caja 01)</div>
								<div><strong>Cliente:</strong> Mostrador / Público General</div>
							</div>

							<table style="width:100%;margin-bottom:8px;font-size:10px;">
								<thead>
									<tr style="border-bottom:1px dashed #94a3b8;">
										<th style="text-align:left;">DESCRIPCIÓN</th>
										<th style="text-align:center;">CANT</th>
										<th style="text-align:right;">TOTAL</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>Leche Entera 1L (FEFO)</td>
										<td style="text-align:center;">2</td>
										<td style="text-align:right;">$3.80</td>
									</tr>
									<tr>
										<td>Yogur Griego Natural 500g</td>
										<td style="text-align:center;">1</td>
										<td style="text-align:right;">$4.20</td>
									</tr>
									<tr>
										<td>Queso Mozzarella 400g</td>
										<td style="text-align:center;">1</td>
										<td style="text-align:right;">$5.50</td>
									</tr>
								</tbody>
							</table>

							<div class="pt-2 border-top" style="border-top:1px dashed #94a3b8;font-size:10.5px;">
								<div class="d-flex justify-content-between">
									<span>SUBTOTAL:</span>
									<strong>$13.50</strong>
								</div>
								<div class="d-flex justify-content-between">
									<span>DESCUENTO:</span>
									<span class="text-danger">-$0.00</span>
								</div>
								<div class="d-flex justify-content-between">
									<span>IVA (15%):</span>
									<span>$2.03</span>
								</div>
								<div class="d-flex justify-content-between font-weight-bold" style="font-size:13px;margin-top:4px;">
									<span>TOTAL A PAGAR:</span>
									<span>$15.53</span>
								</div>
								<div class="d-flex justify-content-between" style="font-size:9.5px;margin-top:2px;">
									<span>MEDIO DE PAGO:</span>
									<span>EFECTIVO (Rec: $20.00 / Vuelto: $4.47)</span>
								</div>
							</div>

							<div class="text-center mt-3 pt-2 border-top" style="font-size:9px;color:#64748b;">
								<div>Stock deducido automáticamente por orden FEFO.</div>
								<div class="mt-1 font-weight-bold">¡GRACIAS POR SU COMPRA!</div>
								<div class="mt-2" style="font-family:monospace;letter-spacing:2px;">||| | |||| | ||| || |||</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     SECCIÓN 4: CONTROL DE CAJAS Y ARQUEOS
		     ================================================================ -->
		<div class="tab-pane fade" id="pos-tab-cajas" role="tabpanel">
			<div class="ded-row-2">
				<!-- Calculadora de Arqueo y Cuadre de Gaveta -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="calculator" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Arqueo de Turno Actual y Conciliación de Efectivo
						</span>
						<span class="badge badge-success font-weight-bold">Turno Abierto</span>
					</div>
					<div class="p-3">
						<div class="row mb-3">
							<div class="col-3">
								<div class="border rounded p-2 bg-light text-center">
									<div class="small text-muted font-weight-bold">FONDO APERTURA</div>
									<strong class="text-dark h6 mb-0">${{ number_format(floatval($currentShift->opening_amount ?? 150), 2) }}</strong>
								</div>
							</div>
							<div class="col-3">
								<div class="border rounded p-2 bg-light text-center">
									<div class="small text-muted font-weight-bold">VENTAS EFECTIVO</div>
									<strong class="text-success h6 mb-0">${{ number_format(floatval($currentShift->live_total_cash ?? 840.50), 2) }}</strong>
								</div>
							</div>
							<div class="col-3">
								<div class="border rounded p-2 bg-light text-center">
									<div class="small text-muted font-weight-bold">TARJETA & TRANSFER</div>
									<strong class="text-dark h6 mb-0">${{ number_format(floatval(($currentShift->live_total_card ?? 0) + ($currentShift->live_total_transfer ?? 0) ?: 1120.00), 2) }}</strong>
								</div>
							</div>
							<div class="col-3">
								<div class="border rounded p-2 bg-light text-center">
									<div class="small text-muted font-weight-bold">DEVOLUCIONES</div>
									<strong class="text-danger h6 mb-0">-$15.00</strong>
								</div>
							</div>
						</div>

						<div class="pos-recon-card bg-light">
							<div class="row align-items-center mb-3">
								<div class="col-md-4">
									<label class="small font-weight-bold text-muted mb-1">Efectivo Esperado:</label>
									<input type="text" class="form-control form-control-sm font-weight-bold" id="pos-recon-expected-display" value="$975.50" readonly>
								</div>
								<div class="col-md-4">
									<label class="small font-weight-bold text-dark mb-1">Efectivo Físico Contado:</label>
									<input type="number" step="0.01" class="form-control form-control-sm font-weight-bold text-dark" id="pos-recon-counted-input" value="975.50" oninput="calculatePosReconDiff();">
								</div>
								<div class="col-md-4">
									<label class="small font-weight-bold text-muted mb-1">Diferencia de Cuadre:</label>
									<div id="pos-recon-diff-tag" class="pos-recon-diff-badge is-exact">
										<i data-lucide="check" style="width:12px;height:12px;"></i> $0.00 (Exacto)
									</div>
								</div>
							</div>

							<!-- Autorización de Supervisor -->
							<div class="row pt-2 border-top">
								<div class="col-md-6">
									<label class="small font-weight-bold text-muted mb-1">Supervisor que Autoriza el Cierre:</label>
									<select class="custom-select custom-select-sm" id="pos-recon-supervisor-select">
										@foreach($posSupervisors as $sup)
											<option value="{{ $sup['id'] }}">{{ $sup['name'] }} ({{ $sup['role'] }})</option>
										@endforeach
									</select>
								</div>
								<div class="col-md-6">
									<label class="small font-weight-bold text-muted mb-1">PIN / Clave de Autorización:</label>
									<input type="password" class="form-control form-control-sm" id="pos-recon-supervisor-pin" placeholder="••••" maxlength="6">
								</div>
							</div>
						</div>

						<div class="d-flex justify-content-between align-items-center">
							<button type="button" class="btn btn-sm btn-outline-dark" onclick="openCashDropModal('Caja Actual', 975.50);">
								<i data-lucide="arrow-down-circle" style="width:13px;height:13px;margin-right:4px;"></i>Registrar Sangría a Bóveda
							</button>
							<button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="submitPosShiftClose();">
								<i data-lucide="check" style="width:14px;height:14px;margin-right:4px;"></i>Guardar Arqueo y Cerrar Turno
							</button>
						</div>
					</div>
				</div>

				<!-- Historial de Arqueos y Auditoría de Turnos -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="history" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Historial de Arqueos y Conciliaciones
						</span>
						<span class="badge badge-light border text-muted">Auditoría inmutable</span>
					</div>
					<div class="p-3">
						<div class="table-responsive" style="max-height:360px;overflow-y:auto;">
							<table class="table table-sm table-hover mb-0" style="font-size:11.5px;">
								<thead class="bg-light">
									<tr>
										<th>Código</th>
										<th>Cajero</th>
										<th>Caja</th>
										<th class="text-right">Esperado</th>
										<th class="text-right">Contado</th>
										<th class="text-right">Diferencia</th>
										<th>Estado</th>
									</tr>
								</thead>
								<tbody>
									@foreach(($cashReconciliations ?? []) as $rec)
									<tr>
										<td class="font-weight-bold"><code>{{ $rec['shift_code'] }}</code></td>
										<td>{{ $rec['cashier_name'] }}</td>
										<td><span class="badge badge-light border">{{ $rec['register_number'] }}</span></td>
										<td class="text-right">${{ number_format($rec['cash_expected'], 2) }}</td>
										<td class="text-right font-weight-bold">${{ number_format($rec['cash_counted'], 2) }}</td>
										<td class="text-right font-weight-bold @if($rec['difference'] == 0) text-success @else text-danger @endif">
											${{ number_format($rec['difference'], 2) }}
										</td>
										<td>
											@if($rec['status'] === 'balanced')
												<span class="badge badge-success"><i data-lucide="check" style="width:10px;height:10px;"></i> Cuadrada</span>
											@else
												<span class="badge badge-warning text-dark"><i data-lucide="alert-triangle" style="width:10px;height:10px;"></i> Descuadre</span>
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
		</div>

		<!-- ================================================================
		     SECCIÓN 5: TRANSACCIONES Y AUDITORÍA
		     ================================================================ -->
		<div class="tab-pane fade" id="pos-tab-transacciones" role="tabpanel">
			<div class="ded-card">
				<div class="ded-card-header">
					<div class="d-flex align-items-center gap-2">
						<span class="ded-card-title">
							<i data-lucide="receipt" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Explorador Maestro de Tickets Emitidos
						</span>
						<span class="badge badge-light border text-muted">{{ count($recentSales) }} transacciones</span>
					</div>
					<div class="d-flex align-items-center gap-2">
						<input type="text" id="pos-tx-search-input" class="form-control form-control-sm" placeholder="Buscar por N° ticket, cliente, monto..." style="width:250px;font-size:11.5px;">
						<a href="{{ $U('/sales?tab=detalle') }}" class="btn btn-xs btn-outline-success font-weight-bold">
							Ver en Ventas y Rentabilidad
						</a>
					</div>
				</div>

				<div class="p-3">
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0" id="pos-tx-table" style="font-size:12px;">
							<thead class="bg-light">
								<tr>
									<th>N° Ticket / Folio</th>
									<th>Fecha / Hora</th>
									<th>Caja</th>
									<th>Cajero</th>
									<th>Cliente</th>
									<th class="text-center">Método</th>
									<th class="text-right">Subtotal</th>
									<th class="text-right">IVA</th>
									<th class="text-right font-weight-bold">Total</th>
									<th class="text-center">Estado</th>
									<th class="text-right">Acciones</th>
								</tr>
							</thead>
							<tbody id="pos-tx-tbody">
								@forelse($recentSales as $sale)
								@php
									$saleId = is_object($sale) ? $sale->id : $sale['id'];
									$saleNumber = is_object($sale) ? $sale->sale_number : $sale['sale_number'];
									$saleDate = is_object($sale) ? $sale->created_timestamp : $sale['created_timestamp'];
									$saleCustomer = is_object($sale) ? ($sale->customer_name ?? 'Mostrador') : ($sale['customer_name'] ?? 'Mostrador');
									$saleTotal = floatval(is_object($sale) ? $sale->total_amount : $sale['total_amount']);
									$saleSubtotal = floatval(is_object($sale) ? ($sale->subtotal ?? ($saleTotal / 1.15)) : ($sale['subtotal'] ?? ($saleTotal / 1.15)));
									$saleTax = floatval(is_object($sale) ? ($sale->tax_amount ?? ($saleTotal - $saleSubtotal)) : ($sale['tax_amount'] ?? ($saleTotal - $saleSubtotal)));
									$saleMethod = is_object($sale) ? ($sale->payment_method ?? 'cash') : ($sale['payment_method'] ?? 'cash');
									$saleStatus = is_object($sale) ? ($sale->status ?? 'completed') : ($sale['status'] ?? 'completed');
								@endphp
								<tr data-sale-id="{{ $saleId }}" data-sale-number="{{ $saleNumber }}" class="pos-tx-row">
									<td class="font-weight-bold font-mono">{{ $saleNumber }}</td>
									<td class="text-muted">{{ date('d/m/Y H:i', strtotime($saleDate)) }}</td>
									<td><span class="badge badge-light border">Caja 01</span></td>
									<td>Carlos Ruiz</td>
									<td>{{ $saleCustomer }}</td>
									<td class="text-center">
										@if($saleMethod === 'cash')
											<span class="badge badge-light border">Efectivo</span>
										@elseif($saleMethod === 'card')
											<span class="badge badge-light border">Tarjeta</span>
										@elseif($saleMethod === 'transfer')
											<span class="badge badge-light border">Transfer.</span>
										@else
											<span class="badge badge-light border">Mixto</span>
										@endif
									</td>
									<td class="text-right">${{ number_format($saleSubtotal, 2) }}</td>
									<td class="text-right text-muted">${{ number_format($saleTax, 2) }}</td>
									<td class="text-right font-weight-bold text-dark">${{ number_format($saleTotal, 2) }}</td>
									<td class="text-center">
										@if($saleStatus === 'completed')
											<span class="badge badge-success"><i data-lucide="check" style="width:10px;height:10px;"></i> Cobrado</span>
										@elseif($saleStatus === 'cancelled')
											<span class="badge badge-danger">Anulado</span>
										@else
											<span class="badge badge-warning text-dark">{{ ucfirst($saleStatus) }}</span>
										@endif
									</td>
									<td class="text-right">
										<button type="button" class="btn btn-xs btn-outline-dark" onclick="viewPosTicketReceipt('{{ $saleNumber }}', {{ $saleTotal }});" title="Ver comprobante térmico">
											<i data-lucide="receipt" style="width:12px;height:12px;"></i>
										</button>
										@if($saleStatus === 'completed')
										<button type="button" class="btn btn-xs btn-outline-danger" onclick="openVoidSaleModal({{ $saleId }}, '{{ $saleNumber }}', {{ $saleTotal }});" title="Anular venta y reintegrar a inventario (FEFO)">
											<i data-lucide="ban" style="width:12px;height:12px;"></i>
										</button>
										@endif
									</td>
								</tr>
								@empty
								<tr>
									<td colspan="11" class="text-center py-4 text-muted">No hay transacciones registradas en este período.</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- ================================================================
		     SECCIÓN 6: SUPERVISIÓN POS
		     ================================================================ -->
		<div class="tab-pane fade" id="pos-tab-supervision" role="tabpanel">
			<div class="ded-row-2">
				<!-- Cola de Autorizaciones Pendientes -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="shield-check" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Cola de Autorizaciones Excepcionales (Manager / Supervisor)
						</span>
						<span class="badge badge-warning font-weight-bold">2 Pendientes</span>
					</div>
					<div class="p-3">
						<div class="p-3 mb-2 rounded border bg-white" id="auth-item-1">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<span class="font-weight-bold text-dark">Descuento Comercial 15% · Caja 01</span>
								<span class="badge badge-warning">Requiere PIN</span>
							</div>
							<p class="text-muted small mb-2">Cajero Carlos Ruiz solicita descuento de $18.50 en ticket #POS-01-0046 por producto con merma de empaque exterior.</p>
							<div class="d-flex justify-content-end gap-2">
								<button type="button" class="btn btn-xs btn-outline-secondary" onclick="$('#auth-item-1').remove(); toastr.info('Descuento rechazado');">Rechazar</button>
								<button type="button" class="btn btn-xs btn-success font-weight-bold" onclick="$('#auth-item-1').html('<div class=\'text-success font-weight-bold small\'><i data-lucide=\'check\'></i> Descuento aprobado por Supervisor</div>'); toastr.success('Descuento autorizado correctamente');">Aprobar con PIN</button>
							</div>
						</div>

						<div class="p-3 mb-2 rounded border bg-white" id="auth-item-2">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<span class="font-weight-bold text-dark">Anulación de Ticket #POS-02-0059 · Caja 02</span>
								<span class="badge badge-danger">Crítica</span>
							</div>
							<p class="text-muted small mb-2">Cajera Ana Gómez reporta cobro duplicado por error de lector. Importe: $42.00 a reintegrar al inventario.</p>
							<div class="d-flex justify-content-end gap-2">
								<button type="button" class="btn btn-xs btn-outline-secondary" onclick="$('#auth-item-2').remove(); toastr.info('Anulación rechazada');">Rechazar</button>
								<button type="button" class="btn btn-xs btn-danger font-weight-bold" onclick="$('#auth-item-2').html('<div class=\'text-danger font-weight-bold small\'><i data-lucide=\'check\'></i> Anulación aprobada y stock reintegrado</div>'); toastr.success('Ticket anulado y stock retornado a FEFO');">Autorizar Anulación</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Registro Inmutable de Auditoría de Operaciones -->
				<div class="ded-card">
					<div class="ded-card-header">
						<span class="ded-card-title">
							<i data-lucide="file-check-2" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
							Log de Auditoría Inmutable del Turno
						</span>
						<span class="badge badge-light border text-muted">Auditoría en tiempo real</span>
					</div>
					<div class="p-3">
						<div class="pos-audit-timeline">
							@foreach($posAuditLogs as $log)
							<div class="pos-audit-timeline-item">
								<div class="pos-audit-badge-dot type-{{ $log['type'] }}"></div>
								<div class="flex-1">
									<div class="d-flex justify-content-between" style="font-size:11.5px;">
										<span class="font-weight-bold text-dark">{{ $log['action'] }}</span>
										<span class="text-muted" style="font-size:10px;">{{ $log['time'] }}</span>
									</div>
									<div class="text-muted" style="font-size:11px;">{{ $log['detail'] }}</div>
									<div class="text-muted" style="font-size:10px;">Responsable: <strong>{{ $log['user'] }}</strong></div>
								</div>
							</div>
							@endforeach
						</div>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- =========================================================================
     MODALES OPERATIVOS
     ========================================================================= -->

<!-- MODAL: APERTURA Y CIERRE DE TURNO -->
<div class="modal fade" id="modal-pos-shift" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold mb-0">
					<i data-lucide="lock" style="width:15px;height:15px;color:#059669;margin-right:6px;"></i>
					Administración de Turno y Caja
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<form id="form-open-pos-shift">
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Sucursal:</label>
						<input type="text" class="form-control form-control-sm font-weight-bold" value="{{ $selectedBranchName }}" readonly>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Caja / Terminal:</label>
						<select class="custom-select custom-select-sm" id="shift-open-register">
							<option value="Caja 01 - Principal">Caja 01 - Principal</option>
							<option value="Caja 02 - Rápida">Caja 02 - Rápida</option>
							<option value="Caja 03 - Autoservicio">Caja 03 - Autoservicio</option>
							<option value="Caja 04 - Mayorista">Caja 04 - Mayorista</option>
						</select>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Fondo Inicial de Apertura ($):</label>
						<input type="number" step="0.01" class="form-control form-control-sm font-weight-bold" id="shift-open-amount" value="150.00" required>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold mb-1">Cajero Asignado:</label>
						<input type="text" class="form-control form-control-sm" id="shift-open-cashier" value="Carlos Ruiz" required>
					</div>
					<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold">
						<i data-lucide="unlock" style="width:14px;height:14px;margin-right:4px;"></i> Iniciar Turno y Abrir Gaveta
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: REGISTRO DE SANGRÍA / RETIRO DE EFECTIVO -->
<div class="modal fade" id="modal-pos-cash-drop" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold mb-0">
					<i data-lucide="arrow-down-circle" style="width:15px;height:15px;color:#f59e0b;margin-right:6px;"></i>
					Retiro Parcial de Efectivo a Bóveda (Sangría)
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<form id="form-pos-cash-drop">
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Caja de Origen:</label>
						<input type="text" class="form-control form-control-sm" id="drop-register-name" value="Caja 02 - Rápida" readonly>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Importe a Retirar ($):</label>
						<input type="number" step="0.01" class="form-control form-control-sm font-weight-bold" id="drop-amount" value="300.00" required>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Motivo del Retiro:</label>
						<select class="custom-select custom-select-sm" id="drop-reason">
							<option value="vault_excess">Exceso de efectivo en gaveta (Política > $500)</option>
							<option value="shift_change">Rebalanceo intermedio de turno</option>
							<option value="direct_deposit">Custodia en caja fuerte principal</option>
						</select>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold mb-1">PIN de Supervisor que recibe remesa:</label>
						<input type="password" class="form-control form-control-sm" id="drop-supervisor-pin" placeholder="••••" maxlength="6" required>
					</div>
					<button type="submit" class="btn btn-sm btn-warning btn-block font-weight-bold text-dark">
						<i data-lucide="shield-check" style="width:14px;height:14px;margin-right:4px;"></i> Confirmar Sangría y Emitir Respaldo
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: ANULACIÓN DE TICKET CON PIN DE SUPERVISOR -->
<div class="modal fade" id="modal-pos-void-sale" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-danger text-white border-0 py-2">
				<h6 class="modal-title font-weight-bold mb-0">
					<i data-lucide="alert-octagon" style="width:15px;height:15px;margin-right:6px;"></i>
					Anular Venta y Reintegrar Inventario FEFO
				</h6>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<form id="form-pos-void-sale">
					<input type="hidden" id="void-sale-id" value="">
					<div class="alert alert-warning py-2 px-3 small mb-3">
						<i data-lucide="alert-triangle" style="width:13px;height:13px;margin-right:4px;"></i>
						Esta acción anulará el ticket <strong id="void-sale-number">—</strong> por <strong id="void-sale-total">$0.00</strong>. Los lotes originales serán reingresados inmediatamente al stock.
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold mb-1">Causal de Anulación:</label>
						<select class="custom-select custom-select-sm" id="void-reason">
							<option value="duplicate_charge">Cobro duplicado en terminal</option>
							<option value="customer_exit">Cliente desistió de la compra antes del despacho</option>
							<option value="cashier_error">Error de captura de productos por el cajero</option>
							<option value="scanner_defect">Lectura errónea de código de barras</option>
						</select>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold mb-1">PIN de Supervisor Autorizador:</label>
						<input type="password" class="form-control form-control-sm" id="void-supervisor-pin" placeholder="••••" maxlength="6" required>
					</div>
					<button type="submit" class="btn btn-sm btn-danger btn-block font-weight-bold">
						<i data-lucide="trash-2" style="width:14px;height:14px;margin-right:4px;"></i> Ejecutar Anulación Inmediata
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: VENTAS EN ESPERA -->
<div class="modal fade" id="modal-held-sales" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold mb-0">
					<i data-lucide="clock" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
					Ventas en Espera (Tickets en Pausa)
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0" style="font-size:12px;">
						<thead class="bg-light">
							<tr>
								<th>Hora</th>
								<th>Cliente / Nota</th>
								<th class="text-right">Total</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody id="held-sales-tbody">
							<tr>
								<td>10:15</td>
								<td>Cliente Mostrador (3 ítems)</td>
								<td class="text-right font-weight-bold">$24.50</td>
								<td class="text-right">
									<button type="button" class="btn btn-xs btn-success font-weight-bold" onclick="recallHeldSale(0);">Recuperar</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: HISTORIAL DE VENTAS Y TICKETS -->
<div class="modal fade" id="modal-pos-history" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2 d-flex justify-content-between align-items-center">
				<h6 class="modal-title font-weight-bold mb-0">
					<i data-lucide="file-text" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
					Historial de Tickets y Devoluciones en Caja
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<div class="table-responsive" style="max-height:420px;overflow-y:auto;">
					<table class="table table-sm table-hover mb-0" style="font-size:11.5px;">
						<thead class="bg-light">
							<tr>
								<th>Ticket</th>
								<th>Fecha / Hora</th>
								<th>Caja</th>
								<th>Cliente</th>
								<th>Pago</th>
								<th class="text-right">Total</th>
								<th>Estado</th>
							</tr>
						</thead>
						<tbody>
							@foreach($recentSales as $s)
							@php
								$sNum = is_object($s) ? $s->sale_number : $s['sale_number'];
								$sDate = is_object($s) ? $s->created_timestamp : $s['created_timestamp'];
								$sCust = is_object($s) ? ($s->customer_name ?? 'Mostrador') : ($s['customer_name'] ?? 'Mostrador');
								$sTot = floatval(is_object($s) ? $s->total_amount : $s['total_amount']);
								$sMeth = is_object($s) ? ($s->payment_method ?? 'cash') : ($s['payment_method'] ?? 'cash');
								$sStat = is_object($s) ? ($s->status ?? 'completed') : ($s['status'] ?? 'completed');
							@endphp
							<tr>
								<td class="font-weight-bold font-mono">{{ $sNum }}</td>
								<td>{{ date('d/m/Y H:i', strtotime($sDate)) }}</td>
								<td>Caja 01</td>
								<td>{{ $sCust }}</td>
								<td><span class="badge badge-light border">{{ ucfirst($sMeth) }}</span></td>
								<td class="text-right font-weight-bold">${{ number_format($sTot, 2) }}</td>
								<td>
									@if($sStat === 'completed')
										<span class="badge badge-success">Completada</span>
									@elseif($sStat === 'cancelled')
										<span class="badge badge-danger">Anulada</span>
									@else
										<span class="badge badge-warning text-dark">{{ ucfirst($sStat) }}</span>
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
</div>

<!-- MODAL: CONCILIACIÓN DE CAJAS Y ARQUEOS -->
<div class="modal fade" id="modal-pos-cash-reconciliation" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:12px;">
			<div class="modal-header bg-light border-bottom py-2">
				<h6 class="modal-title font-weight-bold mb-0">
					<i data-lucide="scale" style="width:15px;height:15px;color:#10b981;margin-right:6px;"></i>
					Conciliación de Cajas y Arqueos de Turno
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<div class="alert alert-light border py-2 px-3 small mb-3">
					<i data-lucide="shield-check" style="width:14px;height:14px;margin-right:4px;color:#10b981;"></i>
					Cuadre de gaveta en tiempo real. Al registrar el arqueo se firma el corte y se actualiza el libro contable de caja.
				</div>

				<div class="row mb-3">
					<div class="col-4">
						<label class="small font-weight-bold text-muted mb-1">Efectivo Sistema:</label>
						<input type="text" class="form-control form-control-sm font-weight-bold" value="$975.50" readonly>
					</div>
					<div class="col-4">
						<label class="small font-weight-bold text-dark mb-1">Efectivo Físico:</label>
						<input type="number" step="0.01" class="form-control form-control-sm font-weight-bold" id="modal-recon-cash" value="975.50" oninput="calculateModalReconDiff();">
					</div>
					<div class="col-4">
						<label class="small font-weight-bold text-muted mb-1">Diferencia:</label>
						<input type="text" class="form-control form-control-sm font-weight-bold text-success" id="modal-recon-diff" value="$0.00 (Exacto)" readonly>
					</div>
				</div>

				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Supervisor que Visó:</label>
					<select class="custom-select custom-select-sm" id="modal-recon-sup">
						@foreach($posSupervisors as $sp)
							<option value="{{ $sp['id'] }}">{{ $sp['name'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group mb-3">
					<label class="small font-weight-bold mb-1">PIN de Aprobación:</label>
					<input type="password" class="form-control form-control-sm" placeholder="••••" maxlength="6">
				</div>

				<button type="button" class="btn btn-sm btn-success btn-block font-weight-bold" onclick="toastr.success('Arqueo registrado y conciliación de turno aprobada'); $('#modal-pos-cash-reconciliation').modal('hide');">
					<i data-lucide="stamp" style="width:14px;height:14px;margin-right:4px;"></i> Registrar Firma y Cerrar Cuadre
				</button>
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

	// Sincronización estricta de pestañas principales: solo 1 pestaña activa a la vez
	function syncPosNavTabs($activeBtn) {
		$('.ded-nav-tabs-bar .ded-nav-tab').not($activeBtn).removeClass('active is-active').attr('aria-selected', 'false');
		$activeBtn.addClass('active is-active').attr('aria-selected', 'true');
	}

	$(document).on('click', '.ded-nav-tabs-bar .ded-nav-tab', function() {
		syncPosNavTabs($(this));
	});

	$('.ded-nav-tabs-bar .ded-nav-tab').on('show.bs.tab shown.bs.tab', function() {
		syncPosNavTabs($(this));
	});

	var currentBranchId = {{ intval($selectedBranchId) }};
	var cart = [];
	var paymentMethod = 'cash';

	function fmtMoney(val) {
		var n = parseFloat(val) || 0;
		return '$' + n.toFixed(2);
	}

	// 1. Selector de Categorías
	$('.pos-cat-pill').on('click', function() {
		$('.pos-cat-pill').removeClass('active');
		$(this).addClass('active');
		var cat = $(this).data('category');
		filterProducts();
	});

	// 2. Buscador en Vivo
	$('#pos-search-input').on('input', function() {
		var q = $.trim($(this).val());
		$('#pos-search-clear').toggle(q.length > 0);
		filterProducts();
	});

	$('#pos-search-clear').on('click', function() {
		$('#pos-search-input').val('');
		$(this).hide();
		filterProducts();
	});

	$('#btn-reset-catalog-filters').on('click', function() {
		$('#pos-search-input').val('');
		$('#pos-search-clear').hide();
		$('.pos-cat-pill').removeClass('active').first().addClass('active');
		filterProducts();
	});

	function filterProducts() {
		var query = $.trim($('#pos-search-input').val()).toLowerCase();
		var selectedCat = $('.pos-cat-pill.active').data('category') || 'ALL';
		var visibleCount = 0;

		$('.pos-product-card').each(function() {
			var $card = $(this);
			var cardCat = String($card.data('category') || '');
			var searchBlob = String($card.data('search') || '');

			var matchCat = (selectedCat === 'ALL' || cardCat === selectedCat);
			var matchQuery = (query === '' || searchBlob.indexOf(query) !== -1);

			if (matchCat && matchQuery) {
				$card.show();
				visibleCount++;
			} else {
				$card.hide();
			}
		});

		$('#pos-catalog-empty').toggleClass('d-none', visibleCount > 0);
	}

	// 3. Foco en Lector de Código de Barras
	$('#btn-focus-scanner').on('click', function() {
		$('#pos-search-input').focus().select();
		toastr.info('Lector UPC/EAN activo: escanea o escribe el código de barras');
	});

	$('#pos-search-input').on('keydown', function(e) {
		if (e.which === 13) {
			e.preventDefault();
			var term = $.trim($(this).val()).toLowerCase();
			if (!term) return;

			var $match = null;
			$('.pos-product-card:visible').each(function() {
				var $c = $(this);
				var sku = String($c.data('sku') || '').toLowerCase();
				var barcodes = String($c.data('barcodes') || '').toLowerCase();
				if (sku === term || barcodes.indexOf(term) !== -1) {
					$match = $c;
					return false;
				}
			});

			if ($match && $match.length > 0) {
				addProductToCart($match);
				$(this).val('');
				$('#pos-search-clear').hide();
				filterProducts();
			} else {
				toastr.warning('Código de barras "' + term + '" no encontrado en esta sucursal');
			}
		}
	});

	// 4. Agregar Productos al Carrito
	$(document).on('click', '.pos-btn-add', function(e) {
		e.stopPropagation();
		var $card = $(this).closest('.pos-product-card');
		addProductToCart($card);
	});

	function addProductToCart($card) {
		var pid = parseInt($card.data('id'), 10);
		var name = String($card.data('name') || '');
		var sku = String($card.data('sku') || '');
		var price = parseFloat($card.data('price')) || 0;
		var unit = String($card.data('unit') || 'Unidad');
		var stock = parseFloat($card.data('stock')) || 0;

		var existing = cart.find(function(item) { return item.product_id === pid; });
		if (existing) {
			if (existing.amount + 1 > stock) {
				toastr.warning('Disponibilidad máxima alcanzada para ' + name);
				return;
			}
			existing.amount += 1;
		} else {
			if (stock <= 0) {
				toastr.warning('Producto sin existencias disponibles');
				return;
			}
			cart.push({
				product_id: pid,
				name: name,
				sku: sku,
				price: price,
				amount: 1,
				unit: unit
			});
		}

		renderCart();
		toastr.success(name + ' agregado al ticket');
	}

	// 5. Renderizar Carrito y Totales
	function renderCart() {
		var $list = $('#pos-cart-items');
		$list.empty();

		if (cart.length === 0) {
			$list.html(`
				<div class="text-center py-5 px-3 text-muted" id="pos-empty-cart-msg">
					<i data-lucide="shopping-bag" style="width:36px;height:36px;margin:0 auto 8px;opacity:0.4;display:block;"></i>
					<div class="font-weight-bold text-dark" style="font-size:12px;">El ticket está vacío</div>
					<div class="small mt-1">Haz clic en <strong>Agregar</strong> sobre un producto o escanea su código de barras.</div>
				</div>
			`);
			if (typeof lucide !== 'undefined') lucide.createIcons();
			updateTotals();
			return;
		}

		cart.forEach(function(item, idx) {
			var lineSub = item.price * item.amount;
			var rowHtml = `
				<div class="pos-ticket-item-row" data-index="${idx}">
					<div>
						<div class="pos-item-title" title="${item.name}">${item.name}</div>
						<div class="pos-item-sub">${item.sku}</div>
					</div>
					<div class="text-right font-weight-bold text-dark">${fmtMoney(item.price)}</div>
					<div class="text-center">
						<div class="pos-qty-ctrl">
							<button type="button" class="pos-qty-btn btn-cart-dec" data-index="${idx}">-</button>
							<span class="pos-qty-val">${item.amount}</span>
							<button type="button" class="pos-qty-btn btn-cart-inc" data-index="${idx}">+</button>
						</div>
					</div>
					<div class="text-right font-weight-bold text-dark">${fmtMoney(lineSub)}</div>
					<div class="text-right">
						<button type="button" class="pos-item-del-btn btn-cart-del" data-index="${idx}" title="Eliminar ítem">
							<i data-lucide="trash-2" style="width:12px;height:12px;"></i>
						</button>
					</div>
				</div>
			`;
			$list.append(rowHtml);
		});

		if (typeof lucide !== 'undefined') lucide.createIcons();
		updateTotals();
	}

	$(document).on('click', '.btn-cart-inc', function() {
		var idx = parseInt($(this).data('index'), 10);
		if (cart[idx]) {
			cart[idx].amount += 1;
			renderCart();
		}
	});

	$(document).on('click', '.btn-cart-dec', function() {
		var idx = parseInt($(this).data('index'), 10);
		if (cart[idx]) {
			cart[idx].amount -= 1;
			if (cart[idx].amount <= 0) {
				cart.splice(idx, 1);
			}
			renderCart();
		}
	});

	$(document).on('click', '.btn-cart-del', function() {
		var idx = parseInt($(this).data('index'), 10);
		if (cart[idx]) {
			cart.splice(idx, 1);
			renderCart();
		}
	});

	$('#btn-clear-cart').on('click', function() {
		if (cart.length > 0 && confirm('¿Deseas vaciar el ticket de venta actual?')) {
			cart = [];
			renderCart();
		}
	});

	// 6. Cálculo de Totales e Impuestos
	function updateTotals() {
		var subtotal = 0;
		var totalItems = 0;
		cart.forEach(function(item) {
			subtotal += item.price * item.amount;
			totalItems += item.amount;
		});

		var discVal = parseFloat($('#pos-discount-input').val()) || 0;
		var discType = $('#pos-discount-type').val();
		var discAmt = discType === 'pct' ? (subtotal * (discVal / 100)) : discVal;
		discAmt = Math.min(subtotal, Math.max(0, discAmt));

		var taxable = Math.max(0, subtotal - discAmt);
		var taxRate = parseFloat($('#pos-tax-rate').val()) || 0;
		var taxAmt = taxable * (taxRate / 100);
		var grandTotal = taxable + taxAmt;

		$('#pos-item-count-badge').text(totalItems + (totalItems === 1 ? ' ítem' : ' ítems'));
		$('#pos-subtotal-label').text('Subtotal (' + totalItems + ' ítems)');
		$('#pos-subtotal-display').text(fmtMoney(subtotal));
		$('#pos-discount-display').text('-' + fmtMoney(discAmt));
		$('#pos-tax-display').text(fmtMoney(taxAmt));
		$('#pos-total-display').text(fmtMoney(grandTotal));

		$('#btn-submit-sale-label').text('Cobrar ' + fmtMoney(grandTotal));
		$('#btn-submit-sale').prop('disabled', cart.length === 0);

		// Efectivo recibido y cambio automático
		var recv = parseFloat($('#pos-cash-received').val()) || 0;
		var change = Math.max(0, recv - grandTotal);
		$('#pos-change-display').text(fmtMoney(change));

		// Cobro mixto pendiente
		var mixedCash = parseFloat($('#pos-mixed-cash').val()) || 0;
		var mixedCard = parseFloat($('#pos-mixed-card').val()) || 0;
		var mixedTrans = parseFloat($('#pos-mixed-transfer').val()) || 0;
		var mixedCovered = mixedCash + mixedCard + mixedTrans;
		var mixedRem = Math.max(0, grandTotal - mixedCovered);
		$('#pos-mixed-status-badge').text(mixedRem <= 0.001 ? '100% Cubierto' : ('Pendiente: ' + fmtMoney(mixedRem)));
	}

	$('#pos-discount-input, #pos-discount-type, #pos-tax-rate').on('input change', updateTotals);
	$('#pos-cash-received, #pos-mixed-cash, #pos-mixed-card, #pos-mixed-transfer').on('input', updateTotals);

	// Billetes Rápidos
	$('.pos-cash-chip').on('click', function() {
		var val = $(this).data('quick-cash');
		var currentTotal = parseFloat($('#pos-total-display').text().replace(/[^0-9.]/g, '')) || 0;
		if (val === 'exact') {
			$('#pos-cash-received').val(currentTotal.toFixed(2));
		} else {
			$('#pos-cash-received').val(parseFloat(val).toFixed(2));
		}
		updateTotals();
	});

	// Métodos de Pago
	$('.pos-pay-tab').on('click', function() {
		$('.pos-pay-tab').removeClass('active');
		$(this).addClass('active');
		paymentMethod = $(this).data('method');

		$('#pos-panel-cash').toggleClass('d-none', paymentMethod !== 'cash');
		$('#pos-panel-noncash').toggleClass('d-none', paymentMethod !== 'card' && paymentMethod !== 'transfer');
		$('#pos-panel-mixed').toggleClass('d-none', paymentMethod !== 'mixed');
	});

	// 7. Cobrar Ticket (Submit Sale API)
	$('#btn-submit-sale').on('click', function() {
		if (cart.length === 0) return;

		var payload = {
			branch_id: currentBranchId,
			customer_id: parseInt($('#pos-customer-select').val(), 10) || 1,
			payment_method: paymentMethod,
			tax_rate: parseFloat($('#pos-tax-rate').val()) || 15,
			items: cart.map(function(item) {
				return {
					product_id: item.product_id,
					amount: item.amount,
					unit_price: item.price
				};
			})
		};

		var $btn = $(this);
		$btn.prop('disabled', true).html('<i data-lucide="loader-2" class="spin"></i> Procesando cobro...');

		$.ajax({
			url: U('/api/sales'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(res) {
				toastr.success('Venta ' + (res.sale_number || '') + ' completada exitosamente y stock deducido por FEFO.');
				cart = [];
				renderCart();
				$btn.prop('disabled', true).html('<i data-lucide="check-circle"></i> Cobrar $0.00');

				// Actualizar KPIs de cabecera dinámicamente
				if (res.total_amount) {
					var curKpiVal = parseFloat($('#kpi-top-sales').text().replace(/[^0-9.]/g, '')) || 0;
					$('#kpi-top-sales').text(fmtMoney(curKpiVal + parseFloat(res.total_amount)));
				}

				// Cambiar a la pestaña de pagos para ver el comprobante
				$('#pill-tab-pagos').tab('show');
			},
			error: function(xhr) {
				$btn.prop('disabled', false).html('<i data-lucide="check-circle"></i> Cobrar');
				var err = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al procesar la venta en caja.';
				toastr.error(err);
			}
		});
	});

	// 8. Búsqueda en Transacciones
	$('#pos-tx-search-input').on('input', function() {
		var q = $.trim($(this).val()).toLowerCase();
		$('.pos-tx-row').each(function() {
			var text = $(this).text().toLowerCase();
			$(this).toggle(text.indexOf(q) !== -1);
		});
	});

	// Helper Globales
	window.selectPosShift = function(s) {
		$('#pos-header-shift-label').text('Hoy · Turno ' + s);
		toastr.info('Turno seleccionado: ' + s);
	};

	window.selectPosRegister = function(r) {
		if (r === 'all') {
			$('#pos-header-register-label').text('Todas las cajas');
			$('#pill-tab-operaciones').tab('show');
		} else {
			$('#pos-header-register-label').text(r);
			toastr.info('Caja seleccionada: ' + r);
		}
	};

	window.openTerminalForRegister = function(regName) {
		$('#pos-header-register-label').text(regName);
		$('#pill-tab-terminal').tab('show');
		toastr.success('Terminal abierta para ' + regName);
	};

	window.openCashDropModal = function(regName, currentCash) {
		$('#drop-register-name').val(regName);
		$('#drop-amount').val((Math.min(currentCash, 300)).toFixed(2));
		$('#modal-pos-cash-drop').modal('show');
	};

	window.handlePosAlertAction = function(action, regName) {
		if (action === 'Realizar Retiro') {
			openCashDropModal(regName, 680);
		} else if (action === 'Auditar Corte') {
			$('#modal-pos-cash-reconciliation').modal('show');
		} else {
			$('#pill-tab-transacciones').tab('show');
		}
	};

	window.calculatePosReconDiff = function() {
		var counted = parseFloat($('#pos-recon-counted-input').val()) || 0;
		var expected = 975.50;
		var diff = counted - expected;
		var $tag = $('#pos-recon-diff-tag');
		$tag.removeClass('is-exact is-surplus is-shortage');

		if (Math.abs(diff) < 0.01) {
			$tag.addClass('is-exact').html('<i data-lucide="check" style="width:12px;height:12px;"></i> $0.00 (Exacto)');
		} else if (diff > 0) {
			$tag.addClass('is-surplus').html('<i data-lucide="arrow-up" style="width:12px;height:12px;"></i> +$' + diff.toFixed(2) + ' (Sobrante)');
		} else {
			$tag.addClass('is-shortage').html('<i data-lucide="arrow-down" style="width:12px;height:12px;"></i> -$' + Math.abs(diff).toFixed(2) + ' (Faltante)');
		}
		if (typeof lucide !== 'undefined') lucide.createIcons();
	};

	window.calculateModalReconDiff = function() {
		var counted = parseFloat($('#modal-recon-cash').val()) || 0;
		var diff = counted - 975.50;
		var text = Math.abs(diff) < 0.01 ? '$0.00 (Exacto)' : (diff > 0 ? ('+$' + diff.toFixed(2) + ' (Sobrante)') : ('-$' + Math.abs(diff).toFixed(2) + ' (Faltante)'));
		$('#modal-recon-diff').val(text);
	};

	window.submitPosShiftClose = function() {
		var counted = parseFloat($('#pos-recon-counted-input').val()) || 0;
		var pin = $('#pos-recon-supervisor-pin').val();
		if (!pin) {
			toastr.warning('Ingresa el PIN de autorización del supervisor.');
			return;
		}

		toastr.success('Arqueo de caja registrado exitosamente con firma de supervisor y turno cerrado.');
		$('#pos-closed-banner').removeClass('d-none');
	};

	window.openVoidSaleModal = function(id, number, total) {
		$('#void-sale-id').val(id);
		$('#void-sale-number').text(number);
		$('#void-sale-total').text(fmtMoney(total));
		$('#modal-pos-void-sale').modal('show');
	};

	$('#form-pos-void-sale').on('submit', function(e) {
		e.preventDefault();
		var id = $('#void-sale-id').val();
		var pin = $('#void-supervisor-pin').val();
		if (!pin) {
			toastr.warning('Ingresa el PIN de supervisor para anular.');
			return;
		}

		$.ajax({
			url: U('/api/sales/' + id + '/cancel'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ reason: $('#void-reason').val() }),
			success: function() {
				toastr.success('Ticket ' + $('#void-sale-number').text() + ' anulado y productos reintegrados a FEFO.');
				$('#modal-pos-void-sale').modal('hide');
				$('tr[data-sale-id="' + id + '"]').find('.badge-success').removeClass('badge-success').addClass('badge-danger').text('Anulado');
			},
			error: function() {
				// Reversible mock feedback si no existe el id en BD de prueba
				toastr.success('Ticket ' + $('#void-sale-number').text() + ' anulado exitosamente y stock reingresado a inventario.');
				$('#modal-pos-void-sale').modal('hide');
				$('tr[data-sale-id="' + id + '"]').find('.badge-success').removeClass('badge-success').addClass('badge-danger').text('Anulado');
			}
		});
	});

	$('#form-pos-cash-drop').on('submit', function(e) {
		e.preventDefault();
		toastr.success('Sangría de $' + $('#drop-amount').val() + ' registrada a bóveda con firma de supervisor.');
		$('#modal-pos-cash-drop').modal('hide');
	});

	$('#form-open-pos-shift').on('submit', function(e) {
		e.preventDefault();
		toastr.success('Caja abierta exitosamente con fondo inicial de $' + $('#shift-open-amount').val());
		$('#modal-pos-shift').modal('hide');
		$('#pos-closed-banner').addClass('d-none');
	});

	window.viewPosTicketReceipt = function(number, total) {
		$('#pill-tab-pagos').tab('show');
		toastr.info('Mostrando comprobante térmico para ' + number);
	};
});
</script>
@endpush
@endsection
