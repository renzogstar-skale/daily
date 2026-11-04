@extends('layout.default')

@section('title', 'Kardex valorizado')

@section('content')
<link href="{{ $U('/css/daily_kardex.css?v=', true) }}{{ daily_asset_version('/css/daily_kardex.css') }}" rel="stylesheet">

@php
	$fmtQty = fn($v) => rtrim(rtrim(number_format(floatval($v), 2, '.', ','), '0'), '.');
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$selectedProduct = null;
	if (!empty($filters['product_id'])) {
		foreach ($products as $p) {
			if ($p['id'] == $filters['product_id']) {
				$selectedProduct = $p;
				break;
			}
		}
	}
@endphp

<div class="kx-container">

	<!-- ====================================================================
	     1. ENCABEZADO DE PÁGINA (BANNER CON FOTOGRAFÍA DIFUMINADA)
	     ==================================================================== -->
	<div class="kx-header-banner">
		<div class="kx-header-flex">
			<div class="kx-header-left">
				<div class="kx-header-icon">
					<i class="fa-solid fa-book-open"></i>
				</div>
				<div>
					<h1 class="kx-header-title">Kardex valorizado</h1>
					<p class="kx-header-desc">Entradas, salidas y saldo con acceso al documento de origen.</p>
				</div>
			</div>
			@if($kardex)
			<div class="d-none d-md-flex align-items-center gap-2">
				<a href="{{ $U('/reconciliation' . (!empty($filters['product_id']) ? '?product_id=' . $filters['product_id'] : '')) }}" class="btn btn-sm btn-outline-primary font-weight-600 bg-white" title="Ver centro de conciliación e inventario sensible">
					<i class="fa-solid fa-arrows-rotate mr-1 text-primary"></i>Conciliación & Sensible
				</a>
				<button type="button" id="kx-btn-csv" class="btn btn-sm btn-outline-secondary font-weight-600 bg-white">
					<i class="fa-solid fa-file-csv mr-1 text-success"></i>Exportar CSV
				</button>
				<button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary font-weight-600 bg-white">
					<i class="fa-solid fa-print mr-1 text-muted"></i>Imprimir
				</button>
			</div>
			@endif
		</div>
	</div>

	<!-- ====================================================================
	     2. FILTROS DEL KARDEX
	     ==================================================================== -->
	<div class="kx-filters-card">
		<div class="kx-filters-header">
			<i class="fa-solid fa-sliders text-success mr-2"></i>
			<span>Filtros del kardex</span>
		</div>
		<form method="get" id="form-kardex-filters" class="m-0">
			<div class="kx-filters-grid">
				<div class="kx-field-group">
					<label class="kx-label" for="kx-product">Producto</label>
					<select id="kx-product" name="product_id" class="kx-select" onchange="document.getElementById('kx-lot').value=''; this.form.submit()">
						<option value="">Selecciona un producto...</option>
						@foreach($products as $p)
						<option value="{{ $p['id'] }}" @if($filters['product_id'] == $p['id']) selected @endif>
							{{ $p['name'] }}@if(!$p['active']) (inactivo)@endif
						</option>
						@endforeach
					</select>
				</div>
				<div class="kx-field-group">
					<label class="kx-label" for="kx-branch">Sucursal</label>
					<select id="kx-branch" name="branch_id" class="kx-select" onchange="document.getElementById('kx-lot').value=''; this.form.submit()">
						<option value="">Red consolidada</option>
						@foreach($branches as $b)
						<option value="{{ $b['id'] }}" @if($filters['branch_id'] == $b['id']) selected @endif>{{ $b['name'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="kx-field-group">
					<label class="kx-label" for="kx-lot">Lote</label>
					<select id="kx-lot" name="stock_id" class="kx-select" onchange="this.form.submit()">
						<option value="">Todos</option>
						@foreach($kardex['lots'] ?? [] as $lot)
						<option value="{{ $lot['stock_id'] }}" @if($filters['stock_id'] === $lot['stock_id']) selected @endif>
							@if($lot['lot_number'])Lote {{ $lot['lot_number'] }} · @endif{{ $lot['stock_id'] }} · vence {{ $lot['best_before_date'] ?? '—' }} ({{ $fmtQty($lot['current_amount']) }})
						</option>
						@endforeach
					</select>
				</div>
				<div class="kx-field-group">
					<label class="kx-label" for="kx-from">Desde</label>
					<input id="kx-from" type="date" name="from" class="kx-input" value="{{ $filters['from'] }}">
				</div>
				<div class="kx-field-group">
					<label class="kx-label" for="kx-to">Hasta</label>
					<input id="kx-to" type="date" name="to" class="kx-input" value="{{ $filters['to'] }}">
				</div>
				<div class="kx-field-group">
					<button type="submit" class="kx-btn-consultar" title="Consultar kardex">
						<i class="fa-solid fa-magnifying-glass"></i>
						<span>Consultar</span>
					</button>
				</div>
			</div>
		</form>
	</div>

	<!-- ====================================================================
	     3. RESUMEN DE HALLAZGOS E INTEGRIDAD (4 TARJETAS)
	     ==================================================================== -->
	<div class="kx-summary-grid">
		<!-- 1. Merma sin caso vinculado -->
		<div class="kx-summary-card" role="button" onclick="filterByPill('waste_without_case')" title="Filtrar por Merma sin caso">
			<div class="kx-summary-icon is-amber">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
			<div class="kx-summary-info">
				<span class="kx-summary-title">Merma sin caso vinculado</span>
				<span class="kx-summary-val">{{ $anomalies['counts']['waste_without_case'] ?? 0 }}</span>
				<span class="kx-summary-sub">Movimientos por revisar</span>
			</div>
		</div>

		<!-- 2. Posibles duplicados -->
		<div class="kx-summary-card" role="button" onclick="filterByPill('duplicate')" title="Filtrar por Posibles duplicados">
			<div class="kx-summary-icon is-red">
				<i class="fa-solid fa-copy"></i>
			</div>
			<div class="kx-summary-info">
				<span class="kx-summary-title">Posibles duplicados</span>
				<span class="kx-summary-val">{{ $anomalies['counts']['duplicate'] ?? 0 }}</span>
				<span class="kx-summary-sub">Movimientos por revisar</span>
			</div>
		</div>

		<!-- 3. Fuera del horario configurado -->
		<div class="kx-summary-card" role="button" onclick="filterByPill('off_hours')" title="Filtrar por Fuera de horario">
			<div class="kx-summary-icon is-slate">
				<i class="fa-solid fa-clock"></i>
			</div>
			<div class="kx-summary-info">
				<span class="kx-summary-title">Fuera del horario configurado</span>
				<span class="kx-summary-val">{{ $anomalies['counts']['off_hours'] ?? 0 }}</span>
				<span class="kx-summary-sub">Movimientos por revisar</span>
			</div>
		</div>

		<!-- 4. Integridad del historial -->
		<div class="kx-integrity-card" id="card-integrity">
			<div>
				<div class="kx-integrity-top">
					<div class="kx-integrity-left">
						<i class="fa-solid fa-shield-halved text-success" style="font-size: 1.25rem;"></i>
						<span class="kx-integrity-title">Integridad del historial</span>
					</div>
					<span id="kx-integrity-badge" class="kx-integrity-badge is-unverified">
						<i class="fa-solid fa-circle" style="font-size: 6px;"></i> Sin verificar
					</span>
				</div>
				<div class="kx-integrity-sub">Verificación disponible bajo demanda.</div>
				<p class="kx-integrity-desc" id="kx-integrity-desc">
					El historial se sella con un hash SHA-256; se verifica a pedido.
				</p>
			</div>
			<div class="kx-integrity-actions">
				<button type="button" id="kx-verify" class="kx-btn-verify">
					<i class="fa-solid fa-fingerprint text-muted"></i>
					<span>Verificar integridad</span>
				</button>
				<a href="#" id="kx-open-sha-modal" class="kx-link-tech" data-toggle="modal" data-target="#modalShaDetails">
					▾ Detalles técnicos (SHA-256)
				</a>
			</div>
		</div>
	</div>

	<!-- Info Banner de hallazgos -->
	<div class="kx-info-banner">
		<i class="fa-solid fa-circle-info"></i>
		<span>Hallazgos por revisar; un movimiento puede tener varias señales.</span>
	</div>

	<!-- ====================================================================
	     4. BANDEJA DE CONTROL & PANEL DE DETALLE
	     ==================================================================== -->
	<div class="kx-main-grid">

		<!-- BANDEJA DE CONTROL DE MOVIMIENTOS -->
		<div class="kx-control-panel">
			<div class="kx-panel-header">
				<div class="kx-panel-title-wrap">
					<div class="kx-panel-icon">
						<i class="fa-solid fa-list-check"></i>
					</div>
					<div>
						<h2 class="kx-panel-title">Control de movimientos</h2>
						<p class="kx-panel-subtitle">Últimos 30 días · {{ $filters['branch_id'] ? 'Sucursal seleccionada' : 'Red consolidada' }}</p>
					</div>
				</div>
			</div>

			<div class="kx-control-toolbar">
				<div class="kx-search-row">
					<div class="kx-search-input-wrap">
						<i class="fa-solid fa-magnifying-glass kx-search-icon"></i>
						<input type="text" id="kx-search-input" class="kx-search-input" placeholder="Buscar por producto, documento o usuario..." aria-label="Buscar hallazgos">
					</div>
					<div>
						<select id="kx-type-select" class="kx-select" style="height:36px;font-size:0.825rem;" aria-label="Filtrar por tipo de hallazgo">
							<option value="all">Todos los tipos de hallazgo</option>
							<option value="waste_without_case">Merma sin caso vinculado</option>
							<option value="duplicate">Posibles duplicados</option>
							<option value="off_hours">Fuera del horario configurado</option>
						</select>
					</div>
				</div>

				<div class="kx-pills-row">
					<button type="button" class="kx-filter-pill is-active" data-filter="all" id="pill-all">
						Todos ({{ $anomalies['count'] }})
					</button>
					<button type="button" class="kx-filter-pill pill-amber" data-filter="waste_without_case" id="pill-waste">
						<i class="fa-solid fa-triangle-exclamation"></i> Merma sin caso ({{ $anomalies['counts']['waste_without_case'] ?? 0 }})
					</button>
					<button type="button" class="kx-filter-pill pill-red" data-filter="duplicate" id="pill-duplicate">
						<i class="fa-solid fa-copy"></i> Posible duplicado ({{ $anomalies['counts']['duplicate'] ?? 0 }})
					</button>
					<button type="button" class="kx-filter-pill pill-slate" data-filter="off_hours" id="pill-offhours">
						<i class="fa-solid fa-clock"></i> Fuera del horario ({{ $anomalies['counts']['off_hours'] ?? 0 }})
					</button>
				</div>
			</div>

			<!-- Tabla de Hallazgos -->
			<div class="kx-table-wrap">
				<table class="kx-table" id="kx-findings-table">
					<thead>
						<tr>
							<th>SEÑAL</th>
							<th>PRODUCTO</th>
							<th class="sortable" id="kx-sort-date">FECHA ↓</th>
							<th>USUARIO</th>
							<th class="text-right">VALOR</th>
							<th class="text-center">ACCIONES</th>
						</tr>
					</thead>
					<tbody id="kx-findings-tbody">
						@foreach($anomalies['items'] as $idx => $a)
						<tr class="kx-row-item @if($idx === 0) is-selected @endif"
							data-index="{{ $idx }}"
							data-type="{{ $a['type'] }}"
							data-product="{{ strtolower($a['product_name']) }}"
							data-user="{{ strtolower($a['user']) }}"
							data-id="{{ $a['stock_log_id'] }}">
							<td>
								<span class="kx-pill-badge {{ $a['badge_class'] }}">
									{{ $a['label'] }}
								</span>
							</td>
							<td class="font-weight-600 text-dark">{{ $a['product_name'] }}</td>
							<td class="text-nowrap text-muted">{{ $a['formatted_date'] }}</td>
							<td>{{ $a['user'] }}</td>
							<td class="text-right font-weight-600 text-dark">{{ $money($a['value']) }}</td>
							<td class="text-center">
								<button type="button" class="kx-btn-action-row kx-select-finding-btn" data-index="{{ $idx }}">
									<i class="fa-solid fa-eye"></i> Ver movimiento
								</button>
							</td>
						</tr>
						@endforeach
						@if(empty($anomalies['items']))
						<tr>
							<td colspan="6" class="text-center py-4 text-muted">
								<i class="fa-solid fa-check-circle text-success mr-1"></i>No se encontraron hallazgos en los últimos 30 días.
							</td>
						</tr>
						@endif
					</tbody>
				</table>
			</div>

			<div class="kx-panel-footer">
				<span id="kx-showing-text">Mostrando {{ min(6, count($anomalies['items'])) }} de {{ count($anomalies['items']) }} hallazgos</span>
				@if(count($anomalies['items']) > 6)
				<button type="button" id="kx-btn-toggle-all" class="kx-btn-show-all">
					<span id="kx-toggle-all-label">Ver todos los hallazgos</span>
					<i class="fa-solid fa-arrow-right"></i>
				</button>
				@endif
			</div>
		</div>

		<!-- PANEL DE DETALLE DEL HALLAZGO -->
		<div class="kx-detail-panel" id="kx-detail-sidebar">
			<div class="kx-detail-header">
				<div class="kx-detail-header-left">
					<i class="fa-solid fa-shield-halved"></i>
					<span>Detalle del hallazgo</span>
				</div>
				<div class="kx-detail-nav">
					<button type="button" id="kx-nav-prev" class="kx-nav-btn" title="Anterior hallazgo" aria-label="Anterior">
						<i class="fa-solid fa-chevron-left"></i>
					</button>
					<button type="button" id="kx-nav-next" class="kx-nav-btn" title="Siguiente hallazgo" aria-label="Siguiente">
						<i class="fa-solid fa-chevron-right"></i>
					</button>
					<button type="button" id="kx-close-detail" class="kx-nav-btn d-lg-none" title="Cerrar detalle" aria-label="Cerrar">
						<i class="fa-solid fa-xmark"></i>
					</button>
				</div>
			</div>

			<div class="kx-detail-body" id="kx-detail-body">
				<!-- Tarjeta de producto -->
				<div class="kx-detail-prod-card">
					<img src="/img/control_center/leche_entera_galon.jpg" alt="Producto" class="kx-detail-prod-thumb" id="kx-d-img">
					<div class="kx-detail-prod-info">
						<div>
							<span class="kx-pill-badge kx-badge-amber" id="kx-d-badge">Merma sin caso vinculado</span>
						</div>
						<h3 class="kx-detail-prod-name" id="kx-d-title">Leche entera 1 galón</h3>
						<span class="kx-detail-prod-sub" id="kx-d-sub">Producto</span>
						<div class="kx-detail-meta-list">
							<div class="kx-detail-meta-item">
								<i class="fa-solid fa-calendar"></i>
								<span>Fecha: <strong id="kx-d-date">2026-10-13 17:29</strong></span>
							</div>
							<div class="kx-detail-meta-item">
								<i class="fa-solid fa-user"></i>
								<span>Usuario: <strong id="kx-d-user">Renzo Giovine</strong></span>
							</div>
							<div class="kx-detail-meta-item">
								<i class="fa-solid fa-dollar-sign"></i>
								<span>Valor: <strong id="kx-d-value">$15.50</strong></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Descripción del hallazgo -->
				<div class="kx-detail-section">
					<h4 class="kx-detail-section-title">Descripción del hallazgo</h4>
					<p class="kx-detail-desc-text" id="kx-d-desc">
						Consumo registrado como vencido o dañado sin vínculo visible a un caso de merma. Revisar causal, autorización y evidencia.
					</p>
				</div>

				<!-- Acciones sugeridas -->
				<div class="kx-detail-section">
					<h4 class="kx-detail-section-title">Acciones sugeridas</h4>
					<div class="kx-actions-list">
						<button type="button" class="kx-action-btn is-primary" id="kx-act-view-movement">
							<span><i class="fa-solid fa-eye mr-2"></i>Ver movimiento</span>
							<i class="fa-solid fa-arrow-right"></i>
						</button>
						<button type="button" class="kx-action-btn" id="kx-act-doc-origin" data-toggle="modal" data-target="#modalReviewMovement">
							<span><i class="fa-solid fa-file-lines mr-2"></i>Consultar documento de origen</span>
							<i class="fa-solid fa-arrow-right"></i>
						</button>
						<button type="button" class="kx-action-btn" id="kx-act-case-link" data-toggle="modal" data-target="#modalReviewMovement">
							<span><i class="fa-solid fa-folder-open mr-2"></i>Revisar caso vinculado</span>
							<i class="fa-solid fa-arrow-right"></i>
						</button>
					</div>
				</div>

				<!-- Documento de merma / origen -->
				<div class="kx-detail-section">
					<h4 class="kx-detail-section-title" id="kx-d-doc-section-title">Documento de merma</h4>
					<div class="kx-doc-card">
						<div class="kx-doc-icon" id="kx-d-doc-icon">
							<i class="fa-regular fa-file-circle-question"></i>
						</div>
						<div class="kx-doc-info">
							<div class="kx-doc-title" id="kx-d-doc-title">Sin vínculo disponible</div>
							<div class="kx-doc-desc" id="kx-d-doc-desc">No se encontró un caso de merma asociado a este movimiento.</div>
							<a href="#" class="kx-doc-link" id="kx-d-doc-link" data-toggle="modal" data-target="#modalReviewMovement">
								<i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>
								<span id="kx-d-doc-link-text">Abrir registro de merma</span>
								<i class="fa-solid fa-arrow-right ml-1"></i>
							</a>
						</div>
					</div>
				</div>

				<!-- Callout de advertencia -->
				<div class="kx-warning-box">
					<i class="fa-solid fa-circle-exclamation"></i>
					<span>Revisar el vínculo no debe generar otro movimiento de stock.</span>
				</div>
			</div>
		</div>

	</div>

	<!-- ====================================================================
	     5. SECCIÓN KARDEX POR PRODUCTO
	     ==================================================================== -->
	<div class="kx-kardex-panel" id="section-kardex-producto">
		<div class="kx-kardex-header">
			<div class="d-flex align-items-center gap-2">
				<div class="kx-panel-icon" style="background:#ecfdf5;color:#10b981;">
					<i class="fa-solid fa-book-open"></i>
				</div>
				<div>
					<h2 class="kx-panel-title">
						@if($kardex)
						Kardex por producto: {{ $kardex['product']['name'] }}
						@else
						Kardex por producto
						@endif
					</h2>
					<p class="kx-panel-subtitle">
						@if($kardex)
						{{ count($kardex['rows']) }} movimientos · {{ $filters['from'] }} al {{ $filters['to'] }}@if($filters['stock_id']) · Lote {{ $filters['stock_id'] }}@endif
						@else
						Selecciona un producto para consultar su kardex
						@endif
					</p>
				</div>
			</div>

			@if($kardex)
			<div class="d-flex align-items-center gap-2">
				@if($filters['stock_id'])
				<a class="btn btn-xs btn-outline-primary" href="{{ $U('/traceability?q=' . urlencode($filters['stock_id'])) }}">
					<i class="fa-solid fa-route mr-1"></i>Ver trazabilidad del lote
				</a>
				@endif
				<button type="button" id="kx-btn-csv-table" class="btn btn-sm btn-outline-secondary font-weight-600 bg-white">
					<i class="fa-solid fa-file-csv mr-1 text-success"></i>Exportar CSV
				</button>
			</div>
			@endif
		</div>

		@if(!$kardex)
		<!-- ESTADO VACÍO (SIN PRODUCTO SELECCIONADO) -->
		<div class="kx-empty-state-grid">
			<!-- Hero central con ilustración difuminada a la izquierda -->
			<div class="kx-empty-hero">
				<div class="kx-empty-icon-wrap">
					<i class="fa-solid fa-clipboard-list"></i>
				</div>
				<h3 class="kx-empty-hero-title">Selecciona un producto para consultar su kardex</h3>
				<p class="kx-empty-hero-desc">
					Consulta entradas, salidas, costos y saldo por sucursal y lote.
				</p>
				<button type="button" class="kx-btn-empty-select" onclick="document.getElementById('kx-product').focus(); document.getElementById('kx-product').scrollIntoView({behavior: 'smooth', block: 'center'});">
					<i class="fa-solid fa-magnifying-glass"></i>
					<span>Seleccionar producto</span>
				</button>
			</div>

			<!-- Flujo explicativo: Del movimiento al origen -->
			<div class="kx-flow-card">
				<div class="kx-flow-title">
					<i class="fa-solid fa-circle-nodes text-success"></i>
					<span>Del movimiento al origen</span>
				</div>
				<div class="kx-flow-steps">
					<div class="kx-flow-step">
						<div class="kx-flow-step-icon is-green">
							<i class="fa-solid fa-truck-ramp-box"></i>
						</div>
						<div class="kx-flow-step-text">
							<span class="kx-flow-step-name">Recepción</span>
							<a href="{{ $U('/orders') }}" class="kx-flow-step-link">Ver documento</a>
						</div>
					</div>
					<div class="kx-flow-arrow">
						<i class="fa-solid fa-arrow-right"></i>
					</div>
					<div class="kx-flow-step">
						<div class="kx-flow-step-icon is-blue">
							<i class="fa-solid fa-dolly"></i>
						</div>
						<div class="kx-flow-step-text">
							<span class="kx-flow-step-name">Traslado</span>
							<a href="{{ $U('/transfers') }}" class="kx-flow-step-link">Ver documento</a>
						</div>
					</div>
					<div class="kx-flow-arrow">
						<i class="fa-solid fa-arrow-right"></i>
					</div>
					<div class="kx-flow-step">
						<div class="kx-flow-step-icon is-amber">
							<i class="fa-solid fa-file-invoice"></i>
						</div>
						<div class="kx-flow-step-text">
							<span class="kx-flow-step-name">Venta o merma</span>
							<a href="{{ $U('/waste') }}" class="kx-flow-step-link">Ver documento</a>
						</div>
					</div>
				</div>
				<p class="kx-flow-footer-text">
					Accede al documento que originó cada movimiento.
				</p>
			</div>
		</div>

		<!-- Métricas vacías con guion -->
		<div class="kx-metrics-grid">
			<div class="kx-metric-box">
				<div class="kx-metric-icon is-green">
					<i class="fa-solid fa-arrow-down"></i>
				</div>
				<div class="kx-metric-info">
					<span class="kx-metric-label">Entradas</span>
					<span class="kx-metric-val">—</span>
				</div>
			</div>
			<div class="kx-metric-box">
				<div class="kx-metric-icon is-red">
					<i class="fa-solid fa-arrow-up"></i>
				</div>
				<div class="kx-metric-info">
					<span class="kx-metric-label">Salidas</span>
					<span class="kx-metric-val">—</span>
				</div>
			</div>
			<div class="kx-metric-box">
				<div class="kx-metric-icon is-slate">
					<i class="fa-solid fa-database"></i>
				</div>
				<div class="kx-metric-info">
					<span class="kx-metric-label">Saldo</span>
					<span class="kx-metric-val">—</span>
				</div>
			</div>
		</div>

		@else
		<!-- KARDEX POBLADO (PRODUCTO SELECCIONADO) -->
		@php
			$s = $kardex['summary'];
			$qu = $kardex['product']['qu_name'] ?? 'u';
		@endphp

		<!-- Fila de Indicadores Clave -->
		<div class="px-4 pt-3 pb-2">
			@include('components.dairy_kpi_row', ['kpis' => [
				['label' => 'Saldo Inicial', 'value' => $fmtQty($s['opening']) . ' ' . $qu, 'sub' => $money($s['opening_value']) . ' al ' . $filters['from'], 'icon' => 'fa-flag', 'tone' => 'secondary'],
				['label' => 'Entradas', 'value' => '+' . $fmtQty($s['in']) . ' ' . $qu, 'sub' => '+' . $money($s['in_value']) . ' compras, traslados', 'icon' => 'fa-arrow-down', 'tone' => 'success'],
				['label' => 'Salidas', 'value' => '-' . $fmtQty($s['out']) . ' ' . $qu, 'sub' => '-' . $money($s['out_value']) . ' ventas, ajustes', 'icon' => 'fa-arrow-up', 'tone' => 'primary'],
				['label' => 'Merma', 'value' => $fmtQty($s['waste_qty']) . ' ' . $qu, 'sub' => $money($s['waste_cost']) . ' en el período', 'icon' => 'fa-trash-can', 'tone' => $s['waste_qty'] > 0 ? 'danger' : 'success', 'alert' => $s['waste_qty'] > 0],
				['label' => 'Saldo Final', 'value' => $fmtQty($s['closing']) . ' ' . $qu, 'sub' => $money($s['closing_value']) . ' valorizado', 'icon' => 'fa-scale-balanced', 'tone' => 'info'],
			]])
		</div>

		<!-- Barra de Conciliación Matemática -->
		<div class="kx-reconcile-bar">
			<div class="d-flex align-items-center gap-2">
				<i class="fa-solid fa-circle-check text-success"></i>
				<span>
					<strong>Conciliación del período:</strong>
					Saldo inicial ({{ $fmtQty($s['opening']) }}) + Entradas ({{ $fmtQty($s['in']) }}) − Salidas ({{ $fmtQty($s['out']) }}) = Saldo final ({{ $fmtQty($s['closing']) }} {{ $qu }}) · Valor: {{ $money($s['closing_value']) }}
				</span>
			</div>
			<span class="small font-mono text-muted">Método: Costo Contable Histórico</span>
		</div>

		<!-- Tabla Grouped Kardex -->
		<div class="kx-table-wrap">
			<table class="kx-kardex-table" id="kx-table">
				<thead>
					<tr class="group-header">
						<th colspan="6" class="th-id">IDENTIFICACIÓN DEL MOVIMIENTO</th>
						<th colspan="3" class="th-in">ENTRADAS</th>
						<th colspan="3" class="th-out">SALIDAS</th>
						<th colspan="2" class="th-bal">SALDO</th>
						<th class="th-id">AUDITORÍA</th>
					</tr>
					<tr class="cols-header">
						<th>Fecha</th>
						<th>Movimiento</th>
						<th>Documento</th>
						<th>Ubicación</th>
						<th>Lote</th>
						<th>Vence</th>
						<th class="text-right">Cantidad</th>
						<th class="text-right">Costo unit.</th>
						<th class="text-right">Valor</th>
						<th class="text-right">Cantidad</th>
						<th class="text-right">Costo unit.</th>
						<th class="text-right">Valor</th>
						<th class="text-right">Cantidad</th>
						<th class="text-right">Valor</th>
						<th>Usuario</th>
					</tr>
				</thead>
				<tbody>
					<!-- Fila de saldo inicial anterior al período -->
					<tr class="kx-initial-row">
						<td>{{ $filters['from'] }}</td>
						<td><span class="badge badge-light border">Saldo inicial</span></td>
						<td class="font-mono small text-muted">Saldo anterior al período</td>
						<td>—</td>
						<td>—</td>
						<td>—</td>
						<td colspan="6" class="text-center text-muted small">Acumulado histórico</td>
						<td class="text-right font-weight-700 text-dark">{{ $fmtQty($s['opening']) }}</td>
						<td class="text-right font-weight-700 text-dark">{{ $money($s['opening_value']) }}</td>
						<td>—</td>
					</tr>

					@foreach($kardex['rows'] as $r)
					@php
						$q = floatval($r['qty']);
						$d = $r['document'];
						$price = floatval($r['price']);
						$lineVal = round(abs($q * $price), 2);
					@endphp
					<tr id="kx-row-log-{{ $r['id'] }}">
						<td class="small text-nowrap font-mono">{{ substr($r['row_created_timestamp'], 0, 16) }}</td>
						<td>
							<span class="badge badge-{{ $r['spoiled'] ? 'danger' : ($q > 0 ? 'success' : ($q < 0 ? 'primary' : 'light')) }}">
								{{ $r['type_label'] }}
							</span>
						</td>
						<td class="font-mono small">
							@if($d && $d['type'] === 'sale')
								<a href="{{ $U('/sales') }}">{{ $d['folio'] }}</a>
							@elseif($d && $d['type'] === 'waste')
								<a href="{{ $U('/waste-approvals') }}" class="text-danger font-weight-600">{{ $d['folio'] }}</a>
							@elseif($d && $d['type'] === 'transfer')
								<a href="{{ $U('/transfers') }}">{{ $d['folio'] }}</a>
							@elseif($d && $d['type'] === 'receipt')
								<a href="{{ $U('/orders') }}" class="text-success font-weight-600">{{ $d['folio'] }}</a>
							@elseif($d)
								<a href="{{ $U('/document/' . $d['type'] . '/' . $d['id']) }}">{{ $d['folio'] }}</a>
							@else
								<span class="text-muted" title="{{ $r['note'] ?: 'Movimiento directo en sistema' }}">
									{{ $r['note'] ? substr($r['note'], 0, 24) : '—' }}
								</span>
							@endif
						</td>
						<td class="small" title="{{ $r['branch_name'] }}">{{ $r['location_name'] ?? '—' }}</td>
						<td class="font-mono small">
							@if($r['stock_id'])
							<a href="?{{ http_build_query(array_merge($filters, ['stock_id' => $r['stock_id']])) }}" title="Filtrar solo este lote">
								{{ $r['stock_id'] }}
							</a>
							@else
							—
							@endif
						</td>
						<td class="small text-nowrap font-mono">{{ $r['best_before_date'] ?? '—' }}</td>

						<!-- ENTRADAS -->
						<td class="text-right text-success font-weight-600 font-mono">{{ $q > 0 ? $fmtQty($q) : '' }}</td>
						<td class="text-right font-mono small text-muted">{{ $q > 0 ? $money($price) : '' }}</td>
						<td class="text-right text-success font-mono">{{ $q > 0 ? $money($lineVal) : '' }}</td>

						<!-- SALIDAS -->
						<td class="text-right @if($r['spoiled']) text-danger @else text-primary @endif font-weight-600 font-mono">
							{{ $q < 0 ? $fmtQty(-$q) : '' }}
						</td>
						<td class="text-right font-mono small text-muted">{{ $q < 0 ? $money($price) : '' }}</td>
						<td class="text-right @if($r['spoiled']) text-danger @else text-primary @endif font-mono">
							{{ $q < 0 ? $money($lineVal) : '' }}
						</td>

						<!-- SALDO -->
						<td class="text-right font-weight-700 text-dark font-mono">{{ $fmtQty($r['balance']) }}</td>
						<td class="text-right font-weight-700 text-dark font-mono">{{ $money($r['balance_value']) }}</td>

						<!-- USUARIO -->
						<td class="small text-nowrap text-muted">{{ $r['user_name'] ?? '—' }}</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		@endif
	</div>

	<!-- ====================================================================
	     6. MÓDULOS CONECTADOS CON KARDEX
	     ==================================================================== -->
	<div class="kx-connected-card">
		<div class="kx-connected-header">
			<div class="kx-connected-title">
				<i class="fa-solid fa-network-wired text-success"></i>
				<span>Módulos conectados con Kardex</span>
			</div>
			<a href="{{ $U('/stockoverview') }}" class="small text-muted">Ver catálogo de inventario <i class="fa-solid fa-arrow-right ml-1"></i></a>
		</div>
		<div class="kx-connected-grid">
			<a href="{{ $U('/stockoverview') }}" class="kx-module-item">
				<div class="kx-module-icon">
					<i class="fa-solid fa-boxes-stacked"></i>
				</div>
				<div class="kx-module-info">
					<span class="kx-module-name">Stock & Movimientos</span>
					<span class="kx-module-desc">Kardex valoriza cada movimiento de stock.</span>
				</div>
				<i class="fa-solid fa-arrow-right kx-module-arrow"></i>
			</a>

			<a href="{{ $U('/traceability') }}" class="kx-module-item">
				<div class="kx-module-icon">
					<i class="fa-solid fa-route"></i>
				</div>
				<div class="kx-module-info">
					<span class="kx-module-name">Trazabilidad</span>
					<span class="kx-module-desc">Trazabilidad usa el kardex del lote.</span>
				</div>
				<i class="fa-solid fa-arrow-right kx-module-arrow"></i>
			</a>

			<a href="{{ $U('/inventory') }}" class="kx-module-item">
				<div class="kx-module-icon">
					<i class="fa-solid fa-clipboard-check"></i>
				</div>
				<div class="kx-module-info">
					<span class="kx-module-name">Conteos Físicos</span>
					<span class="kx-module-desc">El conteo físico ajusta stock con transacción de inventario.</span>
				</div>
				<i class="fa-solid fa-arrow-right kx-module-arrow"></i>
			</a>
		</div>
	</div>

</div>

<!-- ====================================================================
     MODAL 1: DETALLES TÉCNICOS SHA-256 (INTEGRIDAD DEL HISTORIAL)
     ==================================================================== -->
<div class="modal fade" id="modalShaDetails" tabindex="-1" role="dialog" aria-labelledby="modalShaTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-700" id="modalShaTitle">
					<i class="fa-solid fa-fingerprint text-success mr-2"></i>Integridad Criptográfica del Historial (SHA-256)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="alert alert-info border-0 mb-3 small">
					<i class="fa-solid fa-circle-info mr-1"></i>
					El libro inalterable de movimientos (<code>dairy_movement_ledger</code>) sella cada transacción con una función hash SHA-256 encadenada que incluye los campos clave del movimiento.
				</div>

				<h6 class="font-weight-700 text-dark mb-2">¿Qué verifica efectivamente este mecanismo?</h6>
				<ul class="small text-muted pl-3 mb-3">
					<li><strong>Inmutabilidad retrospectiva:</strong> Detecta si alguna fila de <code>stock_log</code> fue modificada directamente en el archivo SQLite fuera del sistema.</li>
					<li><strong>Detección de eliminaciones:</strong> Alerta si se eliminó físicamente un registro del historial (operaciones no reversadas).</li>
					<li><strong>Integridad de la secuencia:</strong> Comprueba que el hash previo (<code>prev_hash</code>) enlace ininterrumpidamente cada movimiento con el anterior.</li>
					<li><strong>Movimientos omitidos:</strong> Identifica registros que no fueron procesados por el libro diario inalterable.</li>
				</ul>

				<h6 class="font-weight-700 text-dark mb-2">Alcance y consideraciones técnicas</h6>
				<div class="p-3 bg-light rounded small text-muted mb-3 border">
					<p class="mb-1"><strong>Algoritmo:</strong> <code>SHA-256(prev_hash | json_encode([stock_log_id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, row_created_timestamp]))</code></p>
					<p class="mb-0"><strong>Limitación clave:</strong> Un hash válido certifica que el registro no ha sido alterado con posterioridad en la base de datos; no garantiza por sí solo que la cantidad física real en estantería coincida con el sistema sin un conteo físico respaldatorio.</p>
				</div>

				<div id="kx-sha-verify-result" class="d-none">
					<!-- Se llena dinámicamente al verificar -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-success btn-sm font-weight-600" id="kx-btn-modal-verify">
					<i class="fa-solid fa-fingerprint mr-1"></i>Ejecutar verificación ahora
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 2: GESTIÓN Y REVISIÓN DE HALLAZGO
     ==================================================================== -->
<div class="modal fade" id="modalReviewMovement" tabindex="-1" role="dialog" aria-labelledby="modalReviewTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-700" id="modalReviewTitle">
					<i class="fa-solid fa-shield-halved text-success mr-2"></i>Gestión de Hallazgo de Movimiento
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="p-3 bg-light rounded border mb-3">
					<div class="d-flex align-items-center justify-content-between mb-1">
						<span class="small font-weight-700" id="m-modal-prod">Leche entera 1 galón</span>
						<span class="badge badge-warning" id="m-modal-badge">Merma sin caso</span>
					</div>
					<div class="small text-muted">
						Movimiento #<span id="m-modal-log-id">193</span> · Fecha: <span id="m-modal-date">2026-10-13 17:29</span> · Valor: <span id="m-modal-val">$15.50</span>
					</div>
				</div>

				<!-- Opciones según el tipo de hallazgo -->
				<div class="form-group mb-3">
					<label class="small font-weight-700" for="m-modal-action-type">Acción de revisión:</label>
					<select id="m-modal-action-type" class="custom-select custom-select-sm">
						<option value="link_waste">Vincular a caso de merma existente (sin descontar stock)</option>
						<option value="justify">Registrar justificación operativa</option>
						<option value="investigate">Derivar a investigación de Pérdida / Calidad</option>
						<option value="revert">Revertir movimiento (ajuste trazable)</option>
					</select>
				</div>

				<!-- Selector de casos de merma candidatos (dinámico) -->
				<div class="form-group mb-3" id="m-modal-waste-group">
					<label class="small font-weight-700" for="m-modal-waste-select">Caso de merma a vincular:</label>
					<select id="m-modal-waste-select" class="custom-select custom-select-sm">
						<option value="">Selecciona un caso registrado...</option>
					</select>
					<small class="form-text text-muted">
						Al vincular el movimiento se asocia su transacción sin volver a rebajar inventario.
					</small>
				</div>

				<div class="form-group mb-3">
					<label class="small font-weight-700" for="m-modal-notes">Observación o justificación:</label>
					<textarea id="m-modal-notes" class="form-control form-control-sm" rows="3" placeholder="Indique causal, folio o motivo de la resolución..."></textarea>
				</div>

				<div class="alert alert-warning border-0 small mb-0">
					<i class="fa-solid fa-triangle-exclamation mr-1"></i>
					<strong>Importante:</strong> Las resoluciones quedan selladas con trazabilidad. No se eliminan movimientos de forma automática.
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-success btn-sm font-weight-600" id="m-modal-btn-confirm">
					<i class="fa-solid fa-check mr-1"></i>Aplicar resolución
				</button>
			</div>
		</div>
	</div>
</div>

@stop

@push('pageScripts')
<script>
	// Anomalías JSON serializado desde backend
	var kxAnomalies = {!! $anomaliesJson !!} || [];
	var currentFindingIndex = 0;
	var currentFilter = 'all';
	var showingAll = false;

	$(document).ready(function() {
		initFindingsTable();
		renderFindingDetail(0);

		// Filtro de búsqueda
		$('#kx-search-input').on('input', function() {
			applyFilters();
		});

		// Filtro de tipo dropdown
		$('#kx-type-select').on('change', function() {
			var val = $(this).val();
			filterByPill(val);
		});

		// Filtro de pills
		$('.kx-filter-pill').on('click', function() {
			var f = $(this).data('filter');
			filterByPill(f);
		});

		// Ver todos los hallazgos
		$('#kx-btn-toggle-all').on('click', function() {
			showingAll = !showingAll;
			$('#kx-toggle-all-label').text(showingAll ? 'Ver menos hallazgos' : 'Ver todos los hallazgos');
			applyFilters();
		});

		// Navegación en detalle
		$('#kx-nav-prev').on('click', function() {
			navigateFinding(-1);
		});
		$('#kx-nav-next').on('click', function() {
			navigateFinding(1);
		});

		// Selección de fila al hacer click
		$(document).on('click', '.kx-row-item', function(e) {
			if ($(e.target).closest('a').length) return;
			var idx = parseInt($(this).data('index'));
			selectFinding(idx);
		});

		// Botón ver movimiento en fila
		$(document).on('click', '.kx-select-finding-btn', function(e) {
			e.stopPropagation();
			var idx = parseInt($(this).data('index'));
			selectFinding(idx);
		});

		// Botón de acción: Ver movimiento
		$('#kx-act-view-movement').on('click', function() {
			var item = kxAnomalies[currentFindingIndex];
			if (!item) return;
			var logId = item.stock_log_id;
			var row = $('#kx-row-log-' + logId);
			if (row.length) {
				$('html, body').animate({
					scrollTop: row.offset().top - 120
				}, 400);
				row.addClass('table-warning');
				setTimeout(function() { row.removeClass('table-warning'); }, 3000);
			} else {
				// Cargar kardex de este producto
				window.location.href = Grocy.BaseUrl + '/kardex?product_id=' + item.product_id;
			}
		});

		// Verificación de integridad
		$('#kx-verify, #kx-btn-modal-verify').on('click', function() {
			runIntegrityVerification();
		});

		// Exportar CSV
		$('#kx-btn-csv, #kx-btn-csv-table').on('click', function() {
			exportKardexCsv();
		});

		// Cambio de tipo de acción en modal
		$('#m-modal-action-type').on('change', function() {
			var val = $(this).val();
			if (val === 'link_waste') {
				$('#m-modal-waste-group').show();
			} else {
				$('#m-modal-waste-group').hide();
			}
		});

		// Confirmar resolución en modal
		$('#m-modal-btn-confirm').on('click', function() {
			submitMovementResolution();
		});
	});

	function initFindingsTable() {
		applyFilters();
	}

	function filterByPill(type) {
		currentFilter = type;
		$('.kx-filter-pill').removeClass('is-active');
		$('.kx-filter-pill[data-filter="' + type + '"]').addClass('is-active');
		$('#kx-type-select').val(type);
		applyFilters();
	}

	function applyFilters() {
		var search = ($('#kx-search-input').val() || '').toLowerCase().trim();
		var visibleCount = 0;
		var maxVisible = showingAll ? 9999 : 6;

		$('.kx-row-item').each(function() {
			var $row = $(this);
			var rowType = $row.data('type');
			var prod = ($row.data('product') || '').toString();
			var user = ($row.data('user') || '').toString();
			var logId = ($row.data('id') || '').toString();

			var matchType = (currentFilter === 'all' || rowType === currentFilter);
			var matchSearch = (!search || prod.indexOf(search) > -1 || user.indexOf(search) > -1 || logId.indexOf(search) > -1);

			if (matchType && matchSearch) {
				visibleCount++;
				if (visibleCount <= maxVisible) {
					$row.show();
				} else {
					$row.hide();
				}
			} else {
				$row.hide();
			}
		});

		var totalMatching = $('.kx-row-item').filter(function() {
			var $row = $(this);
			var rowType = $row.data('type');
			var prod = ($row.data('product') || '').toString();
			var user = ($row.data('user') || '').toString();
			var logId = ($row.data('id') || '').toString();
			return (currentFilter === 'all' || rowType === currentFilter) && (!search || prod.indexOf(search) > -1 || user.indexOf(search) > -1 || logId.indexOf(search) > -1);
		}).length;

		$('#kx-showing-text').text('Mostrando ' + Math.min(maxVisible, totalMatching) + ' de ' + totalMatching + ' hallazgos');
	}

	function selectFinding(index) {
		if (index < 0 || index >= kxAnomalies.length) return;
		currentFindingIndex = index;
		$('.kx-row-item').removeClass('is-selected');
		$('.kx-row-item[data-index="' + index + '"]').addClass('is-selected');
		renderFindingDetail(index);
	}

	function navigateFinding(direction) {
		var newIndex = currentFindingIndex + direction;
		if (newIndex >= 0 && newIndex < kxAnomalies.length) {
			selectFinding(newIndex);
		}
	}

	function renderFindingDetail(index) {
		var item = kxAnomalies[index];
		if (!item) return;

		$('#kx-d-img').attr('src', item.product_image || '/img/control_center/leche_entera_galon.jpg');
		$('#kx-d-title').text(item.product_name);
		$('#kx-d-badge').attr('class', 'kx-pill-badge ' + item.badge_class).text(item.long_label || item.label);
		$('#kx-d-date').text(item.formatted_date || item.timestamp);
		$('#kx-d-user').text(item.user);
		$('#kx-d-value').text('$' + parseFloat(item.value).toFixed(2));
		$('#kx-d-desc').text(item.description || item.detail);

		// Configurar sección de documento
		if (item.type === 'waste_without_case') {
			$('#kx-d-doc-section-title').text('Documento de merma');
			$('#kx-d-doc-icon').html('<i class="fa-regular fa-file-circle-question text-muted"></i>');
			$('#kx-d-doc-title').text('Sin vínculo disponible');
			$('#kx-d-doc-desc').text('No se encontró un caso de merma asociado a este movimiento.');
			$('#kx-d-doc-link-text').text('Abrir registro de merma');
			$('#kx-d-doc-link').attr('href', '#').attr('data-toggle', 'modal').attr('data-target', '#modalReviewMovement');
		} else if (item.type === 'duplicate') {
			$('#kx-d-doc-section-title').text('Verificación de registro');
			$('#kx-d-doc-icon').html('<i class="fa-solid fa-copy text-danger"></i>');
			$('#kx-d-doc-title').text('Posible registro duplicado');
			$('#kx-d-doc-desc').text('Mismo producto y cantidad ingresado con segundos de diferencia. Verifique antes de anular.');
			$('#kx-d-doc-link-text').text('Gestionar revisión');
			$('#kx-d-doc-link').attr('href', '#').attr('data-toggle', 'modal').attr('data-target', '#modalReviewMovement');
		} else {
			$('#kx-d-doc-section-title').text('Control de horario');
			$('#kx-d-doc-icon').html('<i class="fa-solid fa-clock text-slate"></i>');
			$('#kx-d-doc-title').text('Horario de tienda: 06:00–22:00');
			$('#kx-d-doc-desc').text('Movimiento registrado en horario no operativo. Compruebe autorización o proceso nocturno.');
			$('#kx-d-doc-link-text').text('Revisar agenda de turnos');
			$('#kx-d-doc-link').attr('href', Grocy.BaseUrl + '/schedule').removeAttr('data-toggle').removeAttr('data-target');
		}

		// Preparar modal de resolución
		$('#m-modal-prod').text(item.product_name);
		$('#m-modal-badge').attr('class', 'badge ' + (item.type === 'duplicate' ? 'badge-danger' : 'badge-warning')).text(item.label);
		$('#m-modal-log-id').text(item.stock_log_id);
		$('#m-modal-date').text(item.formatted_date || item.timestamp);
		$('#m-modal-val').text('$' + parseFloat(item.value).toFixed(2));

		var $wSelect = $('#m-modal-waste-select').empty().append('<option value="">Selecciona un caso registrado...</option>');
		if (item.candidate_waste_cases && item.candidate_waste_cases.length > 0) {
			item.candidate_waste_cases.forEach(function(c) {
				$wSelect.append('<option value="' + c.id + '">' + c.case_number + ' (' + c.created_timestamp.substring(0, 10) + ' · $' + parseFloat(c.total_cost).toFixed(2) + ')</option>');
			});
		} else {
			$wSelect.append('<option value="1">MRM-001 (Caso preventivo general)</option>');
		}
	}

	function runIntegrityVerification() {
		var $btn = $('#kx-verify, #kx-btn-modal-verify').prop('disabled', true);
		var $badge = $('#kx-integrity-badge').removeClass('is-unverified is-verified is-alert').addClass('is-unverified').html('<i class="fa-solid fa-spinner fa-spin"></i> Verificando…');
		var $desc = $('#kx-integrity-desc').text('Comprobando sellos criptográficos SHA-256 en dairy_movement_ledger…');

		$.getJSON(Grocy.BaseUrl + '/api/stock/ledger/verify')
			.done(function(r) {
				if (r.ok) {
					$badge.removeClass('is-unverified is-alert').addClass('is-verified')
						.html('<i class="fa-solid fa-circle-check"></i> Verificado');
					$desc.html('Historial íntegro: <strong>' + r.sealed + '</strong> movimientos sellados con SHA-256 encadenado. Última comprobación: ' + r.checked_at);
					
					var resHtml = '<div class="alert alert-success border-0 small mb-0"><i class="fa-solid fa-circle-check mr-2"></i>' +
						'<strong>Verificación exitosa:</strong> ' + r.sealed + ' movimientos sellados sin alteraciones. Cadena intacta y sin eliminaciones. (' + r.checked_at + ')</div>';
					$('#kx-sha-verify-result').removeClass('d-none').html(resHtml);
				} else {
					$badge.removeClass('is-unverified is-verified').addClass('is-alert')
						.html('<i class="fa-solid fa-triangle-exclamation"></i> Diferencia detectada');
					$desc.text('Se detectaron diferencias en el libro de movimientos. Ver detalles técnicos.');
					
					var issuesList = (r.issues || []).map(function(i) {
						return '<li>#' + i.stock_log_id + ': ' + $('<div>').text(i.detail).html() + '</li>';
					}).join('');
					var resHtml = '<div class="alert alert-danger border-0 small mb-0"><i class="fa-solid fa-triangle-exclamation mr-2"></i>' +
						'<strong>Diferencias detectadas:</strong><ul class="mt-2 mb-0 pl-3">' + issuesList + '</ul></div>';
					$('#kx-sha-verify-result').removeClass('d-none').html(resHtml);
				}
			})
			.fail(function(xhr) {
				var msg = 'No se pudo verificar el historial';
				try { msg = JSON.parse(xhr.responseText).error_message || msg; } catch(e) {}
				$badge.removeClass('is-unverified is-verified').addClass('is-alert').html('<i class="fa-solid fa-circle-xmark"></i> Error');
				$desc.text(msg);
			})
			.always(function() {
				$btn.prop('disabled', false);
			});
	}

	function submitMovementResolution() {
		var item = kxAnomalies[currentFindingIndex];
		if (!item) return;

		var logId = item.stock_log_id;
		var actionType = $('#m-modal-action-type').val();
		var notes = $('#m-modal-notes').val();
		var wasteId = $('#m-modal-waste-select').val();

		if (actionType === 'revert') {
			if (!confirm('¿Confirma que desea revertir este movimiento mediante un ajuste de reversa trazable? No se borrará del historial.')) {
				return;
			}
			$.post(Grocy.BaseUrl + '/api/stock/bookings/' + logId + '/undo', {})
				.done(function() {
					bootbox.alert('Movimiento revertido correctamente de manera trazable.');
					$('#modalReviewMovement').modal('hide');
					setTimeout(function() { location.reload(); }, 1200);
				})
				.fail(function(xhr) {
					var err = 'Error al revertir';
					try { err = JSON.parse(xhr.responseText).error_message || err; } catch(e) {}
					bootbox.alert(err);
				});
			return;
		}

		if (actionType === 'link_waste') {
			if (!wasteId) {
				alert('Por favor seleccione un caso de merma.');
				return;
			}
			$.ajax({
				url: Grocy.BaseUrl + '/api/stock/movement/' + logId + '/link-waste',
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({ waste_record_id: parseInt(wasteId) })
			}).done(function(r) {
				bootbox.alert(r.message || 'Movimiento vinculado exitosamente al caso de merma sin duplicar stock.');
				$('#modalReviewMovement').modal('hide');
				setTimeout(function() { location.reload(); }, 1200);
			}).fail(function(xhr) {
				var err = 'Error al vincular caso';
				try { err = JSON.parse(xhr.responseText).error_message || err; } catch(e) {}
				bootbox.alert(err);
			});
			return;
		}

		// Justify / Investigate
		if (!notes) {
			alert('Por favor ingrese una justificación u observación.');
			return;
		}
		$.ajax({
			url: Grocy.BaseUrl + '/api/stock/movement/' + logId + '/justify',
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ reason: notes, action_type: actionType })
		}).done(function(r) {
			bootbox.alert(r.message || 'Observación registrada exitosamente.');
			$('#modalReviewMovement').modal('hide');
			setTimeout(function() { location.reload(); }, 1200);
		}).fail(function(xhr) {
			var err = 'Error al registrar observación';
			try { err = JSON.parse(xhr.responseText).error_message || err; } catch(e) {}
			bootbox.alert(err);
		});
	}

	function exportKardexCsv() {
		var table = document.getElementById('kx-table');
		if (!table) {
			alert('Selecciona un producto para exportar su kardex.');
			return;
		}
		var rows = table.querySelectorAll('tr');
		var csv = [];
		for (var i = 0; i < rows.length; i++) {
			var row = [], cols = rows[i].querySelectorAll('td, th');
			for (var j = 0; j < cols.length; j++) {
				var text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').trim();
				text = text.replace(/"/g, '""');
				row.push('"' + text + '"');
			}
			csv.push(row.join(';'));
		}
		var blob = new Blob(['\ufeff' + csv.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.setAttribute('download', 'kardex_{{ $filters['product_id'] }}_{{ $filters['from'] }}_{{ $filters['to'] }}.csv');
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}
</script>
@endpush
