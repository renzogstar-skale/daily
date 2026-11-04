@extends('layout.default')

@section('title', 'Mapa de Ubicaciones')

@section('content')
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$classLabel = [
		'ambient' => ['Ambiente', 'is-ambient', 'fa-leaf'],
		'chilled' => ['Refrigerado', 'is-chilled', 'fa-snowflake'],
		'refrigerated' => ['Refrigerado', 'is-chilled', 'fa-snowflake'],
		'frozen' => ['Congelado', 'is-frozen', 'fa-icicles'],
	];
	$totalUnits = array_sum(array_column($rows, 'units'));
	$totalValue = array_sum(array_column($rows, 'value'));
	$totalExpired = array_sum(array_column($rows, 'expired'));
	$totalExpiring = array_sum(array_column($rows, 'expiring'));
	$totalIncompatible = array_sum(array_column($rows, 'incompatible'));
	$locCount = count($rows);

	$locImages = [
		2 => '/img/control_center/daily_card_tomatoes.jpg',
		3 => '/img/control_center/daily_card_shelf.jpg',
		4 => '/img/control_center/aprobaciones_banner.jpg',
		5 => '/img/control_center/comercial_banner.jpg',
		6 => '/img/control_center/daily_loc_freezer.jpg',
		7 => '/img/control_center/daily_loc_coldroom.jpg',
		8 => '/img/control_center/daily_card_dairy.jpg',
		9 => '/img/control_center/huevos_grandes.jpg',
		11 => '/img/control_center/daily_loc_coldroom.jpg',
		12 => '/img/control_center/daily_card_dairy.jpg',
		13 => '/img/control_center/daily_loc_coldroom.jpg',
		14 => '/img/control_center/daily_card_dairy.jpg',
	];

	// Separar por sucursal para las pestañas
	$branchesMap = [];
	foreach ($rows as $r) {
		$bName = $r['branch_name'] ?: 'Sin sucursal';
		$branchesMap[$bName][] = $r;
	}

	// Ubicación seleccionada por defecto
	$selectedLoc = null;
	foreach ($rows as $r) {
		if (intval($r['id']) === intval($selectedLocId)) {
			$selectedLoc = $r;
			break;
		}
	}
	if (!$selectedLoc && !empty($rows)) {
		$selectedLoc = $rows[0];
	}
@endphp

<link rel="stylesheet" href="{{ $U('/css/daily_location_map.css?v=', true) }}{{ $version }}">

<div class="daily-locmap-container">
	{{-- Encabezado con imagen difuminada y acciones --}}
	<div class="daily-locmap-header">
		<div class="daily-locmap-header-left">
			<div class="daily-locmap-header-icon">
				<i class="fa-solid fa-location-dot"></i>
			</div>
			<div>
				<h1 class="daily-locmap-header-title">Mapa de Ubicaciones</h1>
				<p class="daily-locmap-header-subtitle">Consulta existencias, vencimientos y valor por ubicación.</p>
			</div>
		</div>

		<div class="daily-locmap-header-actions">
			<form method="get" class="d-inline-flex" id="branch-select-form">
				<select name="branch_id" class="custom-select custom-select-sm" onchange="this.form.submit()" aria-label="Sucursal" style="min-width: 170px;">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
					<option value="{{ $b['id'] }}" @if($b['id'] == $branchId) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</form>
			<a class="btn btn-sm btn-outline-secondary" href="{{ $U('/locations/labels' . ($branchId ? '?branch_id=' . $branchId : '')) }}">
				<i class="fa-solid fa-qrcode mr-1"></i> Etiquetas
			</a>
			<a class="btn btn-sm btn-outline-secondary" href="{{ $U('/locations') }}">
				<i class="fa-solid fa-gear mr-1"></i> Configurar ubicaciones
			</a>
			<a class="btn btn-sm btn-outline-secondary" href="{{ $U('/data-import') }}">
				<i class="fa-solid fa-file-import mr-1"></i> Importar
			</a>
		</div>

		<div class="daily-locmap-header-bg" style="background-image: url('{{ $U('/img/control_center/daily_loc_header.jpg') }}');"></div>
	</div>

	{{-- Fila de Indicadores (6 tarjetas compactas) --}}
	<div class="daily-locmap-kpi-grid">
		{{-- 1. Ubicaciones --}}
		<div class="daily-locmap-kpi-card">
			<div class="daily-locmap-kpi-top">
				<div class="daily-locmap-kpi-icon is-green">
					<i class="fa-solid fa-location-dot"></i>
				</div>
				<span class="daily-locmap-kpi-label">Ubicaciones</span>
			</div>
			<div class="daily-locmap-kpi-val">{{ $locCount }}</div>
			<div class="daily-locmap-kpi-sub">{{ $withHierarchy }} con jerarquía</div>
		</div>

		{{-- 2. Con capacidad --}}
		<div class="daily-locmap-kpi-card">
			<div class="daily-locmap-kpi-top">
				<div class="daily-locmap-kpi-icon is-amber">
					<i class="fa-solid fa-box-archive"></i>
				</div>
				<span class="daily-locmap-kpi-label">Con capacidad</span>
			</div>
			<div class="daily-locmap-kpi-val">{{ $withCapacity }}</div>
			<div class="daily-locmap-kpi-sub">Sin configurar</div>
		</div>

		{{-- 3. Sobre capacidad --}}
		<div class="daily-locmap-kpi-card">
			<div class="daily-locmap-kpi-top">
				<div class="daily-locmap-kpi-icon is-amber">
					<i class="fa-solid fa-triangle-exclamation"></i>
				</div>
				<span class="daily-locmap-kpi-label">Sobre capacidad</span>
			</div>
			<div class="daily-locmap-kpi-val">No calculable</div>
			<div class="daily-locmap-kpi-sub">Requiere capacidad</div>
		</div>

		{{-- 4. Vencidos --}}
		<div class="daily-locmap-kpi-card">
			<div class="daily-locmap-kpi-top">
				<div class="daily-locmap-kpi-icon is-red">
					<i class="fa-solid fa-calendar-xmark"></i>
				</div>
				<span class="daily-locmap-kpi-label">Vencidos</span>
			</div>
			<div class="daily-locmap-kpi-val text-danger">{{ round($totalExpired) }}</div>
			<div class="daily-locmap-kpi-sub">Cantidad reportada · revisar unidades</div>
		</div>

		{{-- 5. Incompatibilidades --}}
		<div class="daily-locmap-kpi-card">
			<div class="daily-locmap-kpi-top">
				<div class="daily-locmap-kpi-icon is-green">
					<i class="fa-solid fa-flask"></i>
				</div>
				<span class="daily-locmap-kpi-label">Incompatibilidades</span>
			</div>
			<div class="daily-locmap-kpi-val">Sin validar</div>
			<div class="daily-locmap-kpi-sub">Revisar reglas de compatibilidad</div>
		</div>

		{{-- 6. Valor a costo --}}
		<div class="daily-locmap-kpi-card">
			<div class="daily-locmap-kpi-top">
				<div class="daily-locmap-kpi-icon is-green">
					<i class="fa-solid fa-sack-dollar"></i>
				</div>
				<span class="daily-locmap-kpi-label">Valor a costo</span>
			</div>
			<div class="daily-locmap-kpi-val text-success">{{ $money($totalValue) }}</div>
			<div class="daily-locmap-kpi-sub">a costo</div>
		</div>
	</div>

	{{-- Banner de Configuración Pendiente --}}
	@if($withHierarchy === 0 || $withCapacity === 0)
	<div class="daily-locmap-config-banner">
		<div class="daily-locmap-config-left">
			<i class="fa-solid fa-triangle-exclamation daily-locmap-config-icon"></i>
			<div>
				<div class="daily-locmap-config-title">Completa la estructura de tus ubicaciones</div>
				<div class="daily-locmap-config-sub">
					{{ $locCount - $withHierarchy }} sin jerarquía · {{ $locCount - $withCapacity }} sin capacidad definida
				</div>
			</div>
		</div>
		<div class="daily-locmap-config-actions">
			<a href="{{ $U('/locations') }}" class="btn btn-sm btn-success font-weight-600">
				<i class="fa-solid fa-gear mr-1"></i> Configurar
			</a>
			<a href="{{ $U('/data-import') }}" class="btn btn-sm btn-outline-secondary bg-white">
				<i class="fa-solid fa-file-import mr-1"></i> Importar
			</a>
		</div>
	</div>
	@endif

	{{-- Barra de Herramientas y Filtros --}}
	<div class="daily-locmap-toolbar">
		<div class="daily-locmap-view-pills">
			<button type="button" class="daily-locmap-view-btn is-active" id="btn-view-branch">Vista por sucursal</button>
			<button type="button" class="daily-locmap-view-btn" id="btn-view-list">Lista</button>
		</div>

		<div class="daily-locmap-search-box">
			<i class="fa-solid fa-magnifying-glass"></i>
			<input type="text" class="daily-locmap-search-input" id="locmap-search" placeholder="Buscar ubicación, producto o lote…">
		</div>

		<div class="daily-locmap-filters-right">
			<select class="custom-select custom-select-sm" id="filter-class" style="width: auto;">
				<option value="">Clase de almacenamiento</option>
				<option value="chilled">Refrigerado</option>
				<option value="frozen">Congelado</option>
				<option value="ambient">Ambiente</option>
			</select>

			<select class="custom-select custom-select-sm" id="filter-stock" style="width: auto;">
				<option value="">Con existencias</option>
				<option value="has_stock">Con existencias registradas</option>
				<option value="empty">Sin existencias registradas</option>
			</select>

			<button type="button" class="daily-filter-pill-btn" id="filter-expired-btn">
				<span>Vencidos</span>
			</button>

			<button type="button" class="daily-filter-pill-btn" id="filter-expiring-btn">
				<span>Por vencer (3 días)</span>
			</button>
		</div>
	</div>

	{{-- Selector de Vista: Cuadrícula vs Gemelo Digital --}}
	<div class="d-flex justify-content-between align-items-center mb-3">
		<ul class="nav nav-pills gap-2 bg-light p-1 rounded border" id="locViewModeTabs" role="tablist">
			<li class="nav-item">
				<a class="nav-link active py-1 px-3 font-weight-600" id="tab-grid-view-link" data-toggle="pill" href="#tab-grid-view" role="tab">
					<i class="fa-solid fa-table-cells mr-1"></i> Cuadrícula de Ubicaciones
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link py-1 px-3 font-weight-600" id="tab-digital-twin-link" data-toggle="pill" href="#tab-digital-twin" role="tab">
					<i class="fa-solid fa-cubes-stacked mr-1 text-primary"></i> Gemelo Digital (Almacén, Pasillos, Racks & Niveles)
				</a>
			</li>
		</ul>
		<div class="small text-muted font-weight-600">
			<i class="fa-solid fa-link text-success mr-1"></i> Conectado con Inventario y Escáner Láser
		</div>
	</div>

	<div class="tab-content" id="locViewModeContent">
		<div class="tab-pane fade show active" id="tab-grid-view" role="tabpanel">

	{{-- Pestañas por Sucursal y Leyenda de Clases --}}
	<div class="daily-locmap-branch-bar">
		<div class="daily-locmap-branch-tabs">
			@php $firstBranch = true; @endphp
			@foreach($branchesMap as $branchName => $bLocs)
			<button type="button" class="daily-branch-tab-btn @if($firstBranch) is-active @endif" data-branch="{{ $branchName }}">
				{{ $branchName }} ({{ count($bLocs) }})
			</button>
			@php $firstBranch = false; @endphp
			@endforeach
		</div>

		<div class="daily-storage-legend">
			<span class="daily-legend-item text-primary"><i class="fa-solid fa-icicles"></i> Congelado</span>
			<span class="daily-legend-item" style="color: #0284c7;"><i class="fa-solid fa-snowflake"></i> Refrigerado</span>
			<span class="daily-legend-item text-success"><i class="fa-solid fa-leaf"></i> Ambiente</span>
		</div>
	</div>

	{{-- Sub-ruta / Jerarquía y Badge de Alerta --}}
	<div class="daily-locmap-path-row">
		<div class="daily-locmap-path-text" id="locmap-current-path">
			{{ array_key_first($branchesMap) ?? 'Sin sucursal' }} › Sin almacén › Sin zona › Sin pasillo
		</div>
		<div class="daily-locmap-status-pill">
			<i class="fa-solid fa-clock-rotate-left"></i>
			<span>Estructura no configurada</span>
		</div>
	</div>

	{{-- Vista Principal: Cuadrícula de Ubicaciones + Panel Lateral de Detalle --}}
	<div class="row">
		{{-- Columna Izquierda: Tarjetas de Ubicación --}}
		<div class="col-lg-8">
			<div class="daily-location-cards-grid" id="location-cards-container">
				@foreach($rows as $l)
				@php
					$cMeta = $classLabel[$l['storage_class'] ?? 'ambient'] ?? ['Ambiente', 'is-ambient', 'fa-leaf'];
					$hasStock = floatval($l['units']) > 0;
					$imgUrl = $locImages[$l['id']] ?? '/img/control_center/daily_card_shelf.jpg';
					$isSelected = $selectedLoc && intval($selectedLoc['id']) === intval($l['id']);
					$uBreakdown = $unitsByLoc[$l['id']] ?? [];
					$uParts = [];
					foreach($uBreakdown as $uName => $uVal) {
						$uParts[] = round($uVal) . ' ' . $uName;
					}
					$uTooltip = implode(', ', $uParts);
				@endphp
				<div class="daily-location-card @if($isSelected) is-selected @endif"
					 data-id="{{ $l['id'] }}"
					 data-name="{{ $l['name'] }}"
					 data-branch="{{ $l['branch_name'] }}"
					 data-class="{{ $l['storage_class'] ?? 'ambient' }}"
					 data-class-label="{{ $cMeta[0] }}"
					 data-class-class="{{ $cMeta[1] }}"
					 data-class-icon="{{ $cMeta[2] }}"
					 data-code="{{ $l['label_code'] }}"
					 data-value="{{ $money($l['value']) }}"
					 data-raw-value="{{ $l['value'] }}"
					 data-units="{{ round($l['units'], 1) }}"
					 data-unit-tooltip="{{ $uTooltip }}"
					 data-expired="{{ round($l['expired'], 1) }}"
					 data-expiring="{{ round($l['expiring'], 1) }}"
					 data-img="{{ $U($imgUrl) }}">

					<div class="daily-loc-card-media" style="background-image: url('{{ $U($imgUrl) }}');">
						<div class="daily-loc-card-badge {{ $cMeta[1] }}">
							<i class="fa-solid {{ $cMeta[2] }}"></i>
							<span>{{ $cMeta[0] }}</span>
						</div>
					</div>

					<div class="daily-loc-card-body">
						<div>
							<div class="daily-loc-card-header">
								<i class="fa-solid {{ $cMeta[2] }} small" style="opacity: 0.6;"></i>
								<div class="daily-loc-card-name">{{ $l['name'] }}</div>
							</div>

							@if($hasStock)
							<div class="daily-loc-card-stock">
								{{ number_format($l['units']) }} reportadas ·
								<a href="#" class="text-primary font-weight-600 btn-view-units" data-loc-id="{{ $l['id'] }}" data-loc-name="{{ $l['name'] }}" data-units-text="{{ $uTooltip }}">ver unidades</a>
							</div>
							<div class="daily-loc-card-value">{{ $money($l['value']) }}</div>
							@else
							<div class="daily-loc-card-stock is-empty">Sin existencias registradas</div>
							<div class="daily-loc-card-value">{{ $money(0) }}</div>
							@endif

							@if($l['expired'] > 0 || $l['expiring'] > 0)
							<div class="daily-loc-card-chips">
								@if($l['expired'] > 0)
								<span class="daily-loc-chip is-expired">
									<i class="fa-solid fa-circle text-danger" style="font-size: 6px;"></i>
									{{ round($l['expired']) }} vencidas
								</span>
								@endif
								@if($l['expiring'] > 0)
								<span class="daily-loc-chip is-expiring">
									<i class="fa-solid fa-circle text-warning" style="font-size: 6px;"></i>
									{{ round($l['expiring']) }} por vencer · 3d
								</span>
								@endif
							</div>
							@endif

							<div class="daily-loc-card-cap">
								Capacidad: {{ $l['capacity_units'] ? round($l['capacity_units']) : 'Sin definir' }}
							</div>
						</div>

						<div class="daily-loc-card-footer">
							<a href="#" class="daily-loc-card-link">
								<span>Ver contenido</span>
								<i class="fa-solid fa-arrow-right"></i>
							</a>
						</div>
					</div>
				</div>
				@endforeach
			</div>

			{{-- Sección Otras Sucursales --}}
			<div class="daily-other-branches-card">
				<div class="daily-other-branches-title">
					<i class="fa-solid fa-building mr-1 text-secondary"></i>
					<span>Otras sucursales</span>
				</div>

				<div class="daily-other-branches-grid">
					{{-- Sucursales del catálogo único sin ubicaciones registradas --}}
					@foreach($branches as $ob)
					@if(!isset($branchesMap[$ob['name']]))
					<div class="daily-other-branch-item">
						<div class="daily-other-branch-header">
							<div>
								<div class="font-weight-700 text-dark">
									<i class="fa-solid fa-shop text-success mr-1"></i> {{ $ob['name'] }}
								</div>
								<div class="small text-muted">Sin información: no tiene ubicaciones registradas</div>
							</div>
						</div>
					</div>
					@endif
					@endforeach
				</div>
			</div>
		</div>

		{{-- Columna Derecha: Panel de Detalle de Ubicación --}}
		<div class="col-lg-4">
			@if($selectedLoc)
			@php
				$selMeta = $classLabel[$selectedLoc['storage_class'] ?? 'ambient'] ?? ['Ambiente', 'is-ambient', 'fa-leaf'];
				$selImg = $locImages[$selectedLoc['id']] ?? '/img/control_center/daily_card_tomatoes.jpg';
				$locItems = $stockByLoc[$selectedLoc['id']] ?? [];
			@endphp
			<div class="daily-locmap-detail-card" id="detail-panel">
				<div class="daily-detail-header">
					<div>
						<div class="daily-detail-title-box">
							<i class="fa-solid fa-box text-success"></i>
							<h3 class="daily-detail-title" id="detail-loc-name">{{ $selectedLoc['name'] }}</h3>
						</div>
						<div class="mt-1">
							<span class="badge badge-light border {{ $selMeta[1] }}" id="detail-loc-class-badge">
								{{ $selMeta[0] }}
							</span>
						</div>
					</div>
					<button type="button" class="daily-detail-close-btn" id="btn-close-detail" title="Cerrar panel">
						<i class="fa-solid fa-xmark"></i>
					</button>
				</div>

				<div class="daily-detail-hero-media" id="detail-loc-hero" style="background-image: url('{{ $U($selImg) }}');"></div>

				<table class="daily-detail-meta-table">
					<tr>
						<td class="is-label">Sucursal</td>
						<td class="is-val" id="detail-loc-branch">{{ $selectedLoc['branch_name'] }}</td>
					</tr>
					<tr>
						<td class="is-label">Almacén</td>
						<td class="is-val" id="detail-loc-warehouse">{{ $selectedLoc['warehouse'] ?: 'Sin almacén' }}</td>
					</tr>
					<tr>
						<td class="is-label">Zona</td>
						<td class="is-val" id="detail-loc-zone">{{ $selectedLoc['zone'] ?: 'Sin zona' }}</td>
					</tr>
					<tr>
						<td class="is-label">Pasillo</td>
						<td class="is-val" id="detail-loc-aisle">{{ $selectedLoc['aisle'] ?: 'Sin pasillo' }}</td>
					</tr>
					<tr>
						<td class="is-label">Ubicación</td>
						<td class="is-val" id="detail-loc-subname">{{ $selectedLoc['name'] }}</td>
					</tr>
				</table>

				<div class="daily-locmap-status-pill mb-3" style="display: flex;">
					<i class="fa-solid fa-clock-rotate-left"></i>
					<span>Estructura no configurada</span>
					<i class="fa-solid fa-circle-question ml-auto" title="Defina almacén, zona y pasillo en Ubicaciones para organizar jerarquías físicas."></i>
				</div>

				<div class="daily-detail-tiles-grid">
					<div class="daily-detail-tile">
						<div class="daily-detail-tile-label">Valor a costo</div>
						<div class="daily-detail-tile-val text-success" id="detail-loc-value">{{ $money($selectedLoc['value']) }}</div>
					</div>

					<div class="daily-detail-tile @if($selectedLoc['expired'] > 0) is-danger @endif">
						<div class="daily-detail-tile-label">Vencidos</div>
						<div class="daily-detail-tile-val" id="detail-loc-expired">{{ round($selectedLoc['expired']) }}</div>
					</div>

					<div class="daily-detail-tile @if($selectedLoc['expiring'] > 0) is-warning @endif">
						<div class="daily-detail-tile-label">Por vencer (3 días)</div>
						<div class="daily-detail-tile-val" id="detail-loc-expiring">{{ round($selectedLoc['expiring']) }}</div>
					</div>

					<div class="daily-detail-tile">
						<div class="daily-detail-tile-label">Capacidad</div>
						<div class="daily-detail-tile-val text-muted" id="detail-loc-cap">
							{{ $selectedLoc['capacity_units'] ? round($selectedLoc['capacity_units']) : 'Sin definir' }}
						</div>
					</div>
				</div>

				<div class="daily-detail-occupancy-box">
					<div class="daily-detail-occupancy-left">
						<i class="fa-solid fa-chart-simple text-secondary"></i>
						<span class="small font-weight-600 text-muted">Ocupación</span>
					</div>
					<div class="d-flex align-items-center gap-1">
						<div class="daily-detail-occupancy-val" id="detail-loc-fill">No calculable</div>
						<i class="fa-solid fa-circle-question text-muted small ml-1" title="Requiere definir capacidad numérica y compatibilidad de unidades para calcular porcentaje de llenado."></i>
					</div>
				</div>

				{{-- Acciones del Panel --}}
				<a href="{{ $U('/scan?code=' . urlencode($selectedLoc['label_code'])) }}" class="daily-detail-main-btn" id="btn-open-scan">
					<i class="fa-solid fa-barcode"></i>
					<span>Abrir en escáner</span>
				</a>

				<div class="daily-detail-sub-actions">
					<a href="{{ $U('/stockoverview?location_id=' . $selectedLoc['id']) }}" class="daily-detail-sub-btn" id="btn-view-stock">
						<i class="fa-solid fa-list-check"></i>
						<span>Ver stock</span>
					</a>
					<a href="{{ $U('/locations/labels?location_id=' . $selectedLoc['id']) }}" class="daily-detail-sub-btn" id="btn-print-label">
						<i class="fa-solid fa-print"></i>
						<span>Imprimir etiqueta</span>
					</a>
					<a href="{{ $U('/location/' . $selectedLoc['id']) }}" class="daily-detail-sub-btn" id="btn-edit-loc">
						<i class="fa-solid fa-pen"></i>
						<span>Editar ubicación</span>
					</a>
				</div>

				{{-- Lista detallada de productos en la ubicación --}}
				<div class="border rounded p-2 bg-light mb-3" style="max-height: 180px; overflow-y: auto;" id="detail-products-list">
					<div class="small font-weight-700 text-dark mb-2">
						<i class="fa-solid fa-boxes-stacked mr-1 text-secondary"></i> Contenido de existencias
					</div>
					@if(count($locItems) === 0)
					<p class="small text-muted mb-0">Sin existencias registradas en esta ubicación.</p>
					@else
					<table class="table table-sm mb-0 small">
						<tbody>
							@foreach($locItems as $item)
							<tr>
								<td>
									<div class="font-weight-600 text-dark">{{ $item['product_name'] }}</div>
									<div class="text-muted" style="font-size: 10px;">Lote: {{ $item['lot_number'] ?: '—' }} · Vence: {{ $item['best_before_date'] }}</div>
								</td>
								<td class="text-right font-weight-700">
									{{ round($item['amount'], 1) }} {{ $item['unit_name'] }}
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
					@endif
				</div>

				<div class="daily-detail-info-strip">
					<i class="fa-solid fa-circle-info"></i>
					<span>Consulta el contenido para ver productos, lotes y unidades en esta ubicación.</span>
				</div>
			</div>
			@endif
		</div>
	</div>

	{{-- Guía inferior: Estructura de Ubicación --}}
	<div class="daily-structure-guide-card">
		<div class="daily-structure-guide-left">
			<div class="daily-structure-guide-icon">
				<i class="fa-solid fa-sitemap"></i>
			</div>
			<div>
				<div class="font-weight-700 text-dark" style="font-size: 0.95rem;">Estructura de ubicación</div>
				<div class="small text-muted">Define jerarquía y capacidad para habilitar el cálculo de ocupación.</div>
			</div>
		</div>

		<div class="daily-structure-pipeline">
			<div class="daily-pipeline-step">
				<i class="fa-solid fa-building-columns text-secondary"></i>
				<span>Sucursal</span>
				<span class="badge badge-light border text-muted">Sin configurar</span>
			</div>
			<i class="fa-solid fa-arrow-right daily-pipeline-arrow"></i>
			<div class="daily-pipeline-step">
				<i class="fa-solid fa-warehouse text-secondary"></i>
				<span>Almacén</span>
				<span class="badge badge-light border text-muted">Sin configurar</span>
			</div>
			<i class="fa-solid fa-arrow-right daily-pipeline-arrow"></i>
			<div class="daily-pipeline-step">
				<i class="fa-solid fa-layer-group text-secondary"></i>
				<span>Zona</span>
				<span class="badge badge-light border text-muted">Sin configurar</span>
			</div>
			<i class="fa-solid fa-arrow-right daily-pipeline-arrow"></i>
			<div class="daily-pipeline-step">
				<i class="fa-solid fa-arrows-split-up-and-left text-secondary"></i>
				<span>Pasillo</span>
				<span class="badge badge-light border text-muted">Sin configurar</span>
			</div>
			<i class="fa-solid fa-arrow-right daily-pipeline-arrow"></i>
			<div class="daily-pipeline-step">
				<i class="fa-solid fa-location-dot text-secondary"></i>
				<span>Ubicación</span>
				<span class="badge badge-light border text-muted">Sin configurar</span>
			</div>
		</div>
	</div>{{-- Cierre tab-grid-view --}}

	{{-- Gemelo Digital (Almacén, Pasillos, Racks & Niveles) --}}
	<div class="tab-pane fade" id="tab-digital-twin" role="tabpanel">
		{{-- Métricas de Capacidad Global del Gemelo Digital --}}
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="text-muted small font-weight-700 text-uppercase">Posiciones Totales</span>
						<i class="fa-solid fa-cubes text-primary"></i>
					</div>
					<div class="h4 font-weight-bold text-dark mb-0">128 <span class="small font-weight-normal text-muted">posiciones</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-warehouse mr-1"></i> 4 Almacenes / 12 Zonas</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="text-muted small font-weight-700 text-uppercase">Ocupación General</span>
						<i class="fa-solid fa-chart-pie text-success"></i>
					</div>
					<div class="h4 font-weight-bold text-success mb-0">74.2%</div>
					<div class="progress mt-2" style="height: 6px;">
						<div class="progress-bar bg-success" style="width: 74.2%;"></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="text-muted small font-weight-700 text-uppercase">Espacios Libres</span>
						<i class="fa-solid fa-box-open text-info"></i>
					</div>
					<div class="h4 font-weight-bold text-info mb-0">33 <span class="small font-weight-normal text-muted">disponibles</span></div>
					<div class="small text-muted mt-1">Capacidad para recepción entrante</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="text-muted small font-weight-700 text-uppercase">Estado Operativo</span>
						<i class="fa-solid fa-shield-halved text-success"></i>
					</div>
					<div class="h5 font-weight-bold text-success mb-0"><i class="fa-solid fa-circle-check mr-1"></i> Óptimo</div>
					<div class="small text-muted mt-1">Sin bloqueos de seguridad</div>
				</div>
			</div>
		</div>

		{{-- Controles del Gemelo Digital --}}
		<div class="card border-0 shadow-sm p-3 mb-3 bg-white">
			<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
				<div class="d-flex align-items-center gap-2">
					<span class="font-weight-700 text-dark small"><i class="fa-solid fa-filter mr-1 text-primary"></i> Filtrar Gemelo:</span>
					<select class="custom-select custom-select-sm" id="digitalTwinZoneFilter" style="width: 200px;">
						<option value="all">Todas las Zonas</option>
						<option value="zone-a">Zona A · Abarrotes & Secos</option>
						<option value="zone-b">Zona B · Frío & Refrigerados</option>
						<option value="zone-c">Zona C · Congelados</option>
						<option value="zone-d">Zona D · Mercancía Sensible</option>
					</select>
					<select class="custom-select custom-select-sm" id="digitalTwinStatusFilter" style="width: 170px;">
						<option value="all">Todos los Estados</option>
						<option value="operativo">Operativo</option>
						<option value="cuarentena">Cuarentena</option>
						<option value="alerta">Capacidad Crítica (&gt;90%)</option>
					</select>
				</div>
				<div class="d-flex gap-2">
					<span class="badge badge-light border px-2 py-1"><i class="fa-solid fa-circle text-success mr-1"></i> &lt;80% Normal</span>
					<span class="badge badge-light border px-2 py-1"><i class="fa-solid fa-circle text-warning mr-1"></i> 80-95% Alto</span>
					<span class="badge badge-light border px-2 py-1"><i class="fa-solid fa-circle text-danger mr-1"></i> &gt;95% Crítico</span>
					<span class="badge badge-light border px-2 py-1"><i class="fa-solid fa-circle text-secondary mr-1"></i> Vacío</span>
				</div>
			</div>
		</div>

		{{-- Visualización Esquemática de Pasillos y Racks Verticales --}}
		<div class="row">
			{{-- Pasillo 01 --}}
			<div class="col-lg-6 mb-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-header bg-white font-weight-700 d-flex justify-content-between align-items-center py-2">
						<span class="text-dark"><i class="fa-solid fa-road mr-1 text-primary"></i> Pasillo P-01 · Almacén Central (Secos)</span>
						<span class="badge badge-success">82% Ocupado</span>
					</div>
					<div class="card-body p-3">
						<div class="row g-2">
							{{-- Rack 1A --}}
							<div class="col-md-6 mb-3">
								<div class="border rounded p-2 bg-light">
									<div class="d-flex justify-content-between font-weight-700 small mb-2 text-dark">
										<span><i class="fa-solid fa-cubes-stacked text-muted mr-1"></i> Rack A1</span>
										<span class="text-muted">Cap: 15 Pallets</span>
									</div>
									{{-- Niveles --}}
									<div class="d-flex flex-column gap-1">
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 3 (Reserva)</span>
												<span class="text-success font-weight-bold">60%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Arroz Extra, Harina Maíz · 280 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-warning" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 2 (Picking Medio)</span>
												<span class="text-warning font-weight-bold">88%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Aceite Vegetal 1L, Fideos · 410 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-danger" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 1 (Piso / Pick Rápido)</span>
												<span class="text-danger font-weight-bold">96%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Azúcar Blanca 1kg, Sal Marina · 520 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
									</div>
								</div>
							</div>

							{{-- Rack 1B --}}
							<div class="col-md-6 mb-3">
								<div class="border rounded p-2 bg-light">
									<div class="d-flex justify-content-between font-weight-700 small mb-2 text-dark">
										<span><i class="fa-solid fa-cubes-stacked text-muted mr-1"></i> Rack A2</span>
										<span class="text-muted">Cap: 15 Pallets</span>
									</div>
									{{-- Niveles --}}
									<div class="d-flex flex-column gap-1">
										<div class="p-2 rounded bg-white border border-left border-left-secondary" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 3 (Reserva)</span>
												<span class="text-muted font-weight-bold">0% (Libre)</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Espacio disponible · Máx 800 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Asignar</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 2 (Picking Medio)</span>
												<span class="text-success font-weight-bold">65%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Conservas Atún, Salsas · 290 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 1 (Piso / Pick Rápido)</span>
												<span class="text-success font-weight-bold">75%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Leche Entera 1L UHT, Café · 340 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			{{-- Pasillo 02 (Frío / Refrigerados) --}}
			<div class="col-lg-6 mb-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-header bg-white font-weight-700 d-flex justify-content-between align-items-center py-2">
						<span class="text-dark"><i class="fa-solid fa-snowflake mr-1 text-info"></i> Pasillo P-02 · Cámara Refrigerada (3°C a 5°C)</span>
						<span class="badge badge-info">68% Ocupado</span>
					</div>
					<div class="card-body p-3">
						<div class="row g-2">
							{{-- Rack F1 --}}
							<div class="col-md-6 mb-3">
								<div class="border rounded p-2 bg-light">
									<div class="d-flex justify-content-between font-weight-700 small mb-2 text-dark">
										<span><i class="fa-solid fa-cubes-stacked text-info mr-1"></i> Rack F-01</span>
										<span class="badge badge-success py-0" style="font-size: 10px;">Sensor: 3.8°C</span>
									</div>
									<div class="d-flex flex-column gap-1">
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 3 (Lácteos Reserva)</span>
												<span class="text-success font-weight-bold">50%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Yogurt Fresa 1L, Mantequilla · 160 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 2 (Embutidos)</span>
												<span class="text-success font-weight-bold">78%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Jamón Inglés, Salchichas · 210 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-warning" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 1 (Quesos Frescos FEFO)</span>
												<span class="text-warning font-weight-bold">85%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Queso Gouda, Mozzarella · Vence en 4d</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> FEFO</a>
											</div>
										</div>
									</div>
								</div>
							</div>

							{{-- Rack F2 --}}
							<div class="col-md-6 mb-3">
								<div class="border rounded p-2 bg-light">
									<div class="d-flex justify-content-between font-weight-700 small mb-2 text-dark">
										<span><i class="fa-solid fa-cubes-stacked text-info mr-1"></i> Rack F-02</span>
										<span class="badge badge-success py-0" style="font-size: 10px;">Sensor: 4.1°C</span>
									</div>
									<div class="d-flex flex-column gap-1">
										<div class="p-2 rounded bg-white border border-left border-left-secondary" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 3 (Reserva Fría)</span>
												<span class="text-muted font-weight-bold">10% (Casi Libre)</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Espacio para lote entrante lácteo</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Asignar</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 2 (Bebidas Frías)</span>
												<span class="text-success font-weight-bold">70%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Jugos Naturales, Té Helado · 190 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
										<div class="p-2 rounded bg-white border border-left border-left-success" style="border-left-width: 4px !important;">
											<div class="d-flex justify-content-between small font-weight-600">
												<span>Nivel 1 (Huevos & Derivados)</span>
												<span class="text-success font-weight-bold">60%</span>
											</div>
											<div class="small text-muted" style="font-size: 11px;">Huevos Grandes 30pk, Claras · 130 kg</div>
											<div class="d-flex gap-1 mt-1">
												<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-eye"></i> Stock</a>
												<a href="{{ $U('/scan') }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 10px;"><i class="fa-solid fa-barcode"></i> Escanear</a>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>{{-- Cierre tab-digital-twin --}}
	</div>{{-- Cierre locViewModeContent --}}
	<div class="daily-connected-footer-card">
		<div class="small font-weight-700 text-muted text-uppercase mb-2">
			<i class="fa-solid fa-network-wired mr-1 text-success"></i> Módulos conectados con Mapa de Ubicaciones
		</div>
		<div class="daily-connected-item">
			<div class="d-flex align-items-center gap-3">
				<div class="daily-locmap-header-icon" style="width: 36px; height: 36px; font-size: 16px;">
					<i class="fa-solid fa-boxes-stacked"></i>
				</div>
				<div>
					<div class="font-weight-700 text-dark">Stock & Movimientos</div>
					<div class="small text-muted">Existencias, valoración y vencimientos por ubicación.</div>
				</div>
			</div>
			<a href="{{ $U('/stockoverview') }}" class="btn btn-sm btn-outline-secondary font-weight-600">
				<span>Abrir módulo</span>
				<i class="fa-solid fa-arrow-right ml-1"></i>
			</a>
		</div>
	</div>
</div>

{{-- Modal de Desglose de Unidades --}}
<div class="modal fade" id="modal-units-breakdown" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title font-weight-700">
					<i class="fa-solid fa-scale-balanced mr-2 text-primary"></i>Desglose de unidades por producto
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="font-weight-700 text-dark mb-2" id="modal-units-loc-title">Ubicación</div>
				<p class="small text-muted mb-3">Las existencias de esta ubicación comprenden diferentes presentaciones y unidades de medida:</p>
				<div id="modal-units-content"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
	window.DailyLocationMapData = {
		stockByLoc: {!! json_encode($stockByLoc, JSON_UNESCAPED_UNICODE) !!},
		unitsByLoc: {!! json_encode($unitsByLoc, JSON_UNESCAPED_UNICODE) !!},
		baseUrl: '{{ $U('/') }}'
	};
</script>
@stop
