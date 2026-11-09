{{-- ==========================================================================
     DAILY — PRODUCTOS POR DEPARTAMENTO (RÉPLICA EXACTA DE LA FOTO)
     Centro de decisiones comerciales a nivel SKU: qué mantener, reponer, revisar o potenciar.
     ========================================================================== --}}
@php
	$pcd = $productCatalogDashboard ?? [];
	$k = $pcd['kpis'] ?? [];
	$prods = $pcd['products'] ?? [];
	$selectedProd = $pcd['selected_product'] ?? ($prods[0] ?? []);
	$filters = $pcd['filters'] ?? [];
	$abcXyz = $pcd['abc_xyz'] ?? [];
	$bubblePoints = $pcd['bubble_points'] ?? [];
	$marginRotMatrix = $pcd['margin_rotation_matrix'] ?? [];
	$assortmentOpt = $pcd['assortment_optimization'] ?? [];
	$lostSales = $pcd['stockout_lost_sales'] ?? [];
	$fefo = $pcd['fefo_risks'] ?? [];
	$priActions = $pcd['priority_actions'] ?? [];
	$sim = $pcd['simulator'] ?? [];

	// Serializar datos para interactividad instantánea sin recarga de página
	$productsJson = json_encode($prods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	$bubblePointsJson = json_encode($bubblePoints, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	$simJson = json_encode($sim, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
@endphp

<div class="ded-wrapper prod-dash-wrapper" id="productCatalogDashboard" data-testid="product-catalog-dashboard">

	<!-- ====================================================================
	     ENCABEZADO: TÍTULO, SUBTÍTULO Y BADGE DE MOCKUP
	     ==================================================================== -->
	<header class="ded-header prod-dash-header">
		<div class="ded-header-left prod-dash-header-left">
			<div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
				<h1 class="ded-title prod-dash-title" data-testid="prod-dash-title">Productos por Departamento</h1>
				<span class="prod-dash-mockup-badge" data-testid="prod-dash-badge">Mockup con datos de ejemplo</span>
			</div>
			<p class="ded-subtitle prod-dash-subtitle" data-testid="prod-dash-subtitle">Centro de decisiones comerciales a nivel SKU: qué mantener, reponer, revisar o potenciar.</p>
		</div>

		<!-- Acciones Rápidas Superiores -->
		<div class="ded-header-right prod-dash-header-right">
			<a href="{{ $U('/pos') }}" class="btn btn-sm btn-outline-success font-weight-bold" style="border-radius: 8px;">
				<i class="fa-solid fa-cash-register mr-1"></i>POS Terminal
			</a>
			<button type="button" class="btn btn-sm btn-light border font-weight-bold" onclick="exportProductsToExcel()" style="border-radius: 8px;">
				<i class="fa-solid fa-file-excel text-success mr-1"></i>Exportar
			</button>
		</div>
	</header>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS INTEGRADA DE DAILY (NAVEGACIÓN DIRECTA ENTRE MÓDULOS)
	     ==================================================================== -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Ventas y Rentabilidad">
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen-prod">
			<i class="fa-solid fa-chart-column mr-1"></i>
			<span>Tablero de Ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos-prod">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>
			<span>Rendimiento por departamento</span>
		</button>
		<button type="button" class="ded-nav-tab is-active" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos-prod" aria-selected="true">
			<i class="fa-solid fa-barcode mr-1"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales-prod">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle-prod">
			<i class="fa-solid fa-receipt mr-1"></i>
			<span>Historial de ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad-prod">
			<i class="fa-solid fa-coins mr-1"></i>
			<span>Rentabilidad</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-prod">
			<i class="fa-solid fa-tags text-warning mr-1"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas-prod">
			<i class="fa-solid fa-bullhorn text-primary mr-1"></i>
			<span>Campañas</span>
		</button>
	</nav>

	<!-- ====================================================================
	     FILTROS EN TIRA COMPACTA SUPERIOR (10 SELECTORES EXACTOS)
	     ==================================================================== -->
	<section class="prod-filters-strip mb-3" aria-label="Filtros comerciales a nivel SKU" data-testid="prod-filters-strip">
		<!-- 1. Fecha -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Fecha</label>
			<div class="prod-filter-control-wrap">
				<input type="text" id="filterProdDate" class="prod-filter-input" value="08/10 – 06/11/2026" readonly title="Rango de fechas" onclick="toggleDateDropdown()">
			</div>
		</div>

		<!-- 2. Sucursal -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Sucursal</label>
			<select id="filterProdBranch" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todas</option>
				@foreach($branches as $b)
				<option value="{{ $b->id ?? $b['id'] }}" @if(!empty($selectedBranchId) && ($selectedBranchId == ($b->id ?? $b['id']))) selected @endif>{{ $b->name ?? $b['name'] }}</option>
				@endforeach
			</select>
		</div>

		<!-- 3. Departamento -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Departamento</label>
			<select id="filterProdDept" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todos (5)</option>
				<option value="90">90 Frozen</option>
				<option value="91">91 Dairy</option>
				<option value="92">92 Pescadería</option>
				<option value="93">93 Carnes</option>
				<option value="94">Bakery</option>
			</select>
		</div>

		<!-- 4. Categoría -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Categoría</label>
			<select id="filterProdCategory" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todas</option>
				@foreach($filters['categories'] ?? [] as $cat)
				<option value="{{ $cat }}">{{ $cat }}</option>
				@endforeach
			</select>
		</div>

		<!-- 5. Subcategoría -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Subcategoría</label>
			<select id="filterProdSubcategory" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todas</option>
				@foreach($filters['subcategories'] ?? [] as $sub)
				<option value="{{ $sub }}">{{ $sub }}</option>
				@endforeach
			</select>
		</div>

		<!-- 6. Marca -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Marca</label>
			<select id="filterProdBrand" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todas</option>
				@foreach($filters['brands'] ?? [] as $br)
				<option value="{{ $br }}">{{ $br }}</option>
				@endforeach
			</select>
		</div>

		<!-- 7. Proveedor -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Proveedor</label>
			<select id="filterProdSupplier" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todos</option>
				@foreach($filters['suppliers'] ?? [] as $sup)
				<option value="{{ $sup }}">{{ $sup }}</option>
				@endforeach
			</select>
		</div>

		<!-- 8. SKU / UPC (Búsqueda instantánea) -->
		<div class="prod-filter-item prod-filter-search-item">
			<label class="prod-filter-label">SKU / UPC</label>
			<div class="prod-search-input-wrap">
				<i class="fa-solid fa-magnifying-glass prod-search-icon"></i>
				<input type="text" id="filterProdSearch" class="prod-filter-input prod-search-input" placeholder="Buscar..." oninput="applyProductFilters()" data-testid="prod-search-input">
			</div>
		</div>

		<!-- 9. Disponibilidad -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Disponibilidad</label>
			<select id="filterProdAvailability" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todos</option>
				<option value="normal">Normal</option>
				<option value="atencion">Atención</option>
				<option value="critica">Crítica / Agotado</option>
				<option value="stockout">Agotados (Stock 0)</option>
			</select>
		</div>

		<!-- 10. Rotación -->
		<div class="prod-filter-item">
			<label class="prod-filter-label">Rotación</label>
			<select id="filterProdRotation" class="prod-filter-select" onchange="applyProductFilters()">
				<option value="">Todos</option>
				<option value="high">Alta rotación (> 30 u/d)</option>
				<option value="medium">Media (10–30 u/d)</option>
				<option value="low">Baja (< 10 u/d)</option>
			</select>
		</div>
	</section>

	<!-- ====================================================================
	     FILA 1 — 7 TARJETAS INDICADORAS (KPIS)
	     ==================================================================== -->
	<section class="prod-kpi-grid mb-3" aria-label="Indicadores Generales de Productos" data-testid="prod-kpi-grid">
		<!-- 1. SKU activos -->
		<article class="prod-kpi-card" onclick="filterByKpi('all')" title="Ver todos los SKU activos" data-testid="kpi-sku-activos">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label">{{ $k['active_skus']['label'] ?? 'SKU activos' }}</span>
				<i class="fa-solid fa-boxes-stacked prod-kpi-icon-muted"></i>
			</div>
			<div class="prod-kpi-val">{{ $k['active_skus']['value'] ?? '1,248' }}</div>
			<div class="prod-kpi-sub">{{ $k['active_skus']['sub'] ?? '5 departamentos' }}</div>
		</article>

		<!-- 2. Disponibilidad de productos -->
		<article class="prod-kpi-card" onclick="filterByKpi('available')" title="Filtrar por productos disponibles" data-testid="kpi-disponibilidad">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label">{{ $k['availability_pct']['label'] ?? 'Disponibilidad de productos' }}</span>
				<i class="fa-regular fa-circle-check prod-kpi-icon-muted"></i>
			</div>
			<div class="prod-kpi-val">{{ $k['availability_pct']['value'] ?? '94.2 %' }}</div>
			<div class="prod-kpi-sub">{{ $k['availability_pct']['sub'] ?? '1,176 SKU vendibles' }}</div>
		</article>

		<!-- 3. Productos de baja rotación -->
		<article class="prod-kpi-card" onclick="filterByKpi('low_rot')" title="Filtrar por baja rotación" data-testid="kpi-baja-rotacion">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label">{{ $k['low_rot_count']['label'] ?? 'Productos de baja rotación' }}</span>
				<i class="fa-solid fa-arrow-trend-down prod-kpi-icon-muted"></i>
			</div>
			<div class="prod-kpi-val">{{ $k['low_rotation_count']['value'] ?? '137' }}</div>
			<div class="prod-kpi-sub">{{ $k['low_rotation_count']['sub'] ?? 'bajo la velocidad objetivo' }}</div>
		</article>

		<!-- 4. Productos agotados -->
		<article class="prod-kpi-card" onclick="filterByKpi('stockout')" title="Filtrar productos agotados" data-testid="kpi-agotados">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label">{{ $k['stockout_count']['label'] ?? 'Productos agotados' }}</span>
				<i class="fa-solid fa-bullseye text-danger"></i>
			</div>
			<div class="prod-kpi-val">{{ $k['stockout_count']['value'] ?? '72' }}</div>
			<div class="prod-kpi-sub">{!! $k['stockout_count']['sub'] ?? '<span class="text-danger font-weight-bold">11 en alta demanda</span>' !!}</div>
		</article>

		<!-- 5. Margen insuficiente -->
		<article class="prod-kpi-card" onclick="filterByKpi('low_margin')" title="Filtrar productos con margen bajo objetivo" data-testid="kpi-margen-insuficiente">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label">{{ $k['insufficient_margin']['label'] ?? '% Margen insuficiente' }}</span>
				<i class="fa-solid fa-percent prod-kpi-icon-muted"></i>
			</div>
			<div class="prod-kpi-val">{{ $k['insufficient_margin']['value'] ?? '96' }}</div>
			<div class="prod-kpi-sub">{{ $k['insufficient_margin']['sub'] ?? 'bajo el margen objetivo' }}</div>
		</article>

		<!-- 6. Próximos a vencer -->
		<article class="prod-kpi-card" onclick="filterByKpi('expiring')" title="Filtrar próximos a vencer" data-testid="kpi-proximos-vencer">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label">{{ $k['expiring_soon']['label'] ?? 'Próximos a vencer' }}</span>
				<i class="fa-regular fa-clock prod-kpi-icon-muted"></i>
			</div>
			<div class="prod-kpi-val">{{ $k['expiring_soon']['value'] ?? '58' }}</div>
			<div class="prod-kpi-sub">{{ $k['expiring_soon']['sub'] ?? 'lotes o SKU en riesgo' }}</div>
		</article>

		<!-- 7. Oportunidad comercial estimada (Verde sólido) -->
		<article class="prod-kpi-card prod-kpi-card-green" onclick="filterByKpi('opportunity')" title="Oportunidad comercial estimada" data-testid="kpi-oportunidad">
			<div class="prod-kpi-header">
				<span class="prod-kpi-label text-white">{{ $k['commercial_opportunity']['label'] ?? 'Oportunidad comercial estimada' }}</span>
				<i class="fa-solid fa-circle-dollar-sign text-white opacity-75"></i>
			</div>
			<div class="prod-kpi-val text-white">{{ $k['commercial_opportunity']['value'] ?? '≈ $18,900' }}</div>
			<div class="prod-kpi-sub text-white-50">{{ $k['commercial_opportunity']['sub'] ?? 'estimación · confianza media' }}</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 2 — EXPLORADOR INTELIGENTE (65%) Y FICHA 360° (35%)
	     ==================================================================== -->
	<section class="prod-row-2 mb-3">
		<!-- Explorador Inteligente de Productos (65%) -->
		<div class="prod-explorer-panel" data-testid="prod-explorer-panel">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Explorador inteligente de productos</h2>
					<p class="prod-card-subtitle">Búsqueda instantánea, orden por columna, selección múltiple y comparación entre sucursales. Clic en una fila abre la ficha 360° sin salir del dashboard.</p>
				</div>
			</div>

			<!-- Tabla de Productos -->
			<div class="table-responsive prod-table-wrap">
				<table class="table prod-table" id="tableProductExplorer" aria-label="Explorador de productos">
					<thead>
						<tr>
							<th style="width: 28px;" class="text-center">
								<input type="checkbox" id="chkSelectAllProds" onchange="toggleSelectAllProds(this.checked)" title="Seleccionar todos">
							</th>
							<th style="width: 32px;"></th>
							<th onclick="sortExplorerTable('name')" style="cursor: pointer;">
								Producto <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th onclick="sortExplorerTable('sku')" style="cursor: pointer;">
								SKU / UPC <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th onclick="sortExplorerTable('dept')" style="cursor: pointer;">
								Depto. Categoría <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th class="text-right" onclick="sortExplorerTable('price')" style="cursor: pointer;">
								Precio <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th class="text-right" onclick="sortExplorerTable('margin')" style="cursor: pointer;">
								Margen <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th class="text-right" onclick="sortExplorerTable('stock')" style="cursor: pointer;">
								Stock <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th class="text-right" onclick="sortExplorerTable('sales')" style="cursor: pointer;">
								Venta u/d <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th class="text-right" onclick="sortExplorerTable('coverage')" style="cursor: pointer;">
								Cobert. <i class="fa-solid fa-sort prod-sort-icon"></i>
							</th>
							<th class="text-center">Estado</th>
							<th class="text-center" style="width: 50px;">Acción</th>
						</tr>
					</thead>
					<tbody id="tbodyProductExplorer">
						@foreach($prods as $idx => $p)
						<tr class="prod-row-item @if($idx === 0) is-selected @endif" data-prod-id="{{ $p['id'] }}" onclick="selectProductRow({{ $p['id'] }})">
							<td class="text-center" onclick="event.stopPropagation()">
								<input type="checkbox" class="chk-prod-row" data-id="{{ $p['id'] }}" @if($idx < 3) checked @endif onchange="updateSelectedCount()">
							</td>
							<td class="text-center px-1">
								<div class="prod-avatar-icon" style="background: {{ $p['avatar_bg'] }}; color: {{ $p['avatar_color'] }};">
									{{ $p['initial'] }}
								</div>
							</td>
							<td class="font-weight-600 text-dark prod-cell-name">
								{{ $p['name'] }}
							</td>
							<td class="small text-muted">
								<span class="font-weight-bold text-dark">{{ $p['sku'] }}</span><br>
								<span class="text-xs">{{ $p['upc'] }}</span>
							</td>
							<td class="small text-muted">
								<span class="font-weight-bold text-dark">{{ $p['department'] }}</span><br>
								<span>{{ $p['category'] }}</span>
							</td>
							<td class="text-right font-weight-600">
								${{ number_format($p['price'], 2) }}
							</td>
							<td class="text-right font-weight-600 @if($p['margin_pct'] < 15) text-danger @endif">
								{{ number_format($p['margin_pct'], 1) }} %
							</td>
							<td class="text-right font-weight-600">
								{{ number_format($p['stock'], 0) }}
							</td>
							<td class="text-right font-weight-600">
								{{ $p['sales_per_day'] }}
							</td>
							<td class="text-right font-weight-600 @if($p['coverage_days'] < 2) text-danger @elseif($p['coverage_days'] > 30) text-warning @else text-success @endif">
								{{ $p['coverage_days'] }} d
							</td>
							<td class="text-center">
								@if($p['status'] === 'critica')
								<span class="prod-badge-state badge-critica">Crítica</span>
								@elseif($p['status'] === 'atencion')
								<span class="prod-badge-state badge-atencion">Atención</span>
								@else
								<span class="prod-badge-state badge-normal">Normal</span>
								@endif
							</td>
							<td class="text-center">
								<button type="button" class="btn btn-xs prod-btn-abrir" onclick="event.stopPropagation(); selectProductRow({{ $p['id'] }})">
									Abrir
								</button>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<!-- Pie de tabla con conteo, selección y acciones -->
			<div class="prod-explorer-footer">
				<div class="prod-explorer-footer-left">
					<span id="txtSelectedProdsCount" class="font-weight-bold text-dark">3 seleccionados</span>
					<span class="mx-1">·</span>
					<span id="txtProdsPagination">1-8 de 1,248</span>
					<span class="mx-1">·</span>
					<button type="button" class="btn btn-link p-0 text-muted font-weight-600 small" onclick="openBranchCompareModal()">Por tienda</button>
					<span class="mx-1">·</span>
					<button type="button" class="btn btn-link p-0 text-success font-weight-600 small" onclick="openBranchCompareModal()">Comparar sucursales</button>
				</div>
				<div class="prod-explorer-footer-right">
					<button type="button" class="btn btn-sm btn-light border font-weight-600 shadow-xs" onclick="exportProductsToExcel()">
						Exportar a Excel
					</button>
					<button type="button" class="btn btn-sm btn-success font-weight-600 shadow-xs" onclick="openCreateTaskModal('selection')">
						Crear tarea para selección
					</button>
				</div>
			</div>
		</div>

		<!-- Ficha 360° del Producto (35%) -->
		<div class="prod-detail-panel" id="prodDetailPanel" data-testid="prod-detail-panel">
			<div class="prod-detail-header">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div class="prod-avatar-icon-lg" id="detProdAvatar" style="background: {{ $selectedProd['avatar_bg'] ?? '#e0f2fe' }}; color: {{ $selectedProd['avatar_color'] ?? '#0284c7' }};">
						{{ $selectedProd['initial'] ?? 'L' }}
					</div>
					<div>
						<h3 class="prod-detail-title" id="detProdName">{{ $selectedProd['name'] ?? 'Leche entera 1 L' }}</h3>
						<div class="prod-detail-sku-upc" id="detProdSkuUpc">
							{{ $selectedProd['sku'] ?? 'SKU-1001' }} · UPC {{ $selectedProd['upc'] ?? '7501055310001' }}
						</div>
						<div class="prod-detail-brand-dept" id="detProdBrandDept">
							Marca: {{ $selectedProd['brand'] ?? 'Lácteos del Valle' }} · {{ $selectedProd['department'] ?? '91 Dairy' }} / {{ $selectedProd['category'] ?? 'Leches' }}
						</div>
					</div>
				</div>
				<div class="text-right">
					<span class="prod-detail-subtag">Ficha 360° · panel lateral</span>
				</div>
			</div>

			<!-- Grid 3x2 de Métricas Principales -->
			<div class="prod-detail-metrics-grid">
				<!-- 1. Precio -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Precio</div>
					<div class="prod-metric-box-val" id="detProdPrice">${{ number_format($selectedProd['price'] ?? 1.89, 2) }}</div>
				</div>
				<!-- 2. Costo unit. -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Costo unit.</div>
					<div class="prod-metric-box-val" id="detProdCost">${{ number_format($selectedProd['cost'] ?? 1.47, 2) }}</div>
				</div>
				<!-- 3. Margen unit. -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Margen unit.</div>
					<div class="prod-metric-box-val" id="detProdMarginUnit">${{ number_format($selectedProd['unit_margin'] ?? 0.42, 2) }}</div>
				</div>

				<!-- 4. Venta -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Venta</div>
					<div class="prod-metric-box-val" id="detProdSalesDaily">{{ $selectedProd['sales_per_day'] ?? 96 }} u/d</div>
				</div>
				<!-- 5. Demanda -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Demanda</div>
					<div class="prod-metric-box-val text-success" id="detProdDemand">▲ {{ $selectedProd['demand_trend'] ?? '+9 %' }}</div>
				</div>
				<!-- 6. Elasticidad -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Elasticidad</div>
					<div class="prod-metric-box-val text-muted" id="detProdElasticity">~{{ $selectedProd['elasticity'] ?? '-0.8' }} *</div>
				</div>

				<!-- 7. Stock vendible -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Stock vendible</div>
					<div class="prod-metric-box-val" id="detProdStock">{{ $selectedProd['stock'] ?? 148 }}</div>
				</div>
				<!-- 8. Cobertura -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Cobertura</div>
					<div class="prod-metric-box-val text-danger" id="detProdCoverage">{{ $selectedProd['coverage_days'] ?? 1.5 }} d</div>
				</div>
				<!-- 9. Riesgo agotamiento -->
				<div class="prod-metric-box">
					<div class="prod-metric-box-label">Riesgo agotam.</div>
					<div class="prod-metric-box-val">
						<span class="badge badge-danger px-2 py-1" id="detProdRiskBadge">{{ $selectedProd['risk_level'] ?? 'Alto' }}</span>
					</div>
				</div>
			</div>

			<!-- Nota de Fechas y Estimaciones -->
			<div class="prod-detail-note" id="detProdTimingNote">
				Agotamiento estimado: <span id="detProdStockoutEst">{{ $selectedProd['stockout_est'] ?? '07/11 ~12:00' }}</span> · 
				Próxima recepción conocida: <span id="detProdNextReception">{{ $selectedProd['next_reception'] ?? '08/11 (sin confirmar)' }}</span> · 
				<span class="text-muted">*confianza baja</span>
			</div>

			<!-- Ciclo de Vida -->
			<div class="prod-lifecycle-wrap">
				<div class="prod-section-label">Ciclo de vida</div>
				<div class="prod-lifecycle-steps" id="detProdLifecycleSteps">
					<span class="prod-lifecycle-step @if(($selectedProd['lifecycle'] ?? '') === 'Nuevo') is-active @endif" data-step="Nuevo">Nuevo</span>
					<span class="prod-lifecycle-step @if(($selectedProd['lifecycle'] ?? 'Crecimiento') === 'Crecimiento') is-active @endif" data-step="Crecimiento">Crecimiento</span>
					<span class="prod-lifecycle-step @if(($selectedProd['lifecycle'] ?? '') === 'Estable') is-active @endif" data-step="Estable">Estable</span>
					<span class="prod-lifecycle-step @if(($selectedProd['lifecycle'] ?? '') === 'Declive') is-active @endif" data-step="Declive">Declive</span>
					<span class="prod-lifecycle-step @if(($selectedProd['lifecycle'] ?? '') === 'Candidato') is-active @endif" data-step="Candidato">Candidato</span>
				</div>
			</div>

			<!-- Por Sucursal (Tabla compacta) -->
			<div class="prod-branch-table-wrap">
				<div class="prod-section-label">Por sucursal</div>
				<table class="table prod-branch-table mb-2">
					<thead>
						<tr>
							<th>Tienda</th>
							<th class="text-right">Margen</th>
							<th class="text-right">Venta u/d</th>
							<th class="text-right">Cobert.</th>
						</tr>
					</thead>
					<tbody id="detProdBranchTbody">
						@foreach($selectedProd['branch_comparison'] ?? [] as $bc)
						<tr>
							<td class="font-weight-600">{{ $bc['branch'] }}</td>
							<td class="text-right">{{ $bc['margin'] }}</td>
							<td class="text-right">{{ $bc['sales'] }}</td>
							<td class="text-right font-weight-600 @if($bc['coverage_state'] === 'critica') text-danger @else text-warning @endif">{{ $bc['coverage'] }}</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<!-- Botones de Acción Real -->
			<div class="prod-detail-actions">
				<button type="button" class="btn btn-success btn-sm font-weight-600 shadow-xs" onclick="openReplenishModal()">
					Solicitar reposición
				</button>
				<button type="button" class="btn btn-outline-secondary btn-sm font-weight-600" onclick="openPriceReviewModal()">
					Revisar precio
				</button>
				<button type="button" class="btn btn-outline-secondary btn-sm font-weight-600" onclick="openCreateTaskModal('single')">
					Crear tarea
				</button>
			</div>
			<div class="text-center mt-2">
				<a href="{{ $U('/sales?tab=detalle') }}" class="small text-muted font-weight-600" style="text-decoration: underline;">
					Abrir historial
				</a>
			</div>
		</div>
	</section>

	<!-- ====================================================================
	     FILA 3 — MATRIZ ABC-XYZ (50%) Y ROTACIÓN VS COBERTURA (50%)
	     ==================================================================== -->
	<section class="prod-row-half mb-3">
		<!-- Matriz ABC-XYZ (50%) -->
		<div class="prod-card" data-testid="prod-abc-xyz-card">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Matriz ABC-XYZ</h2>
					<p class="prod-card-subtitle">Importancia económica (ABC) × estabilidad de la demanda (XYZ). Clic en una celda para ver sus SKU.</p>
				</div>
			</div>

			<div class="prod-matrix-container">
				<table class="prod-abc-table" id="tableAbcXyz" aria-label="Matriz ABC-XYZ">
					<thead>
						<tr>
							<th style="width: 30px;"></th>
							<th class="text-center">X estable</th>
							<th class="text-center">Y variable</th>
							<th class="text-center">Z irregular</th>
						</tr>
					</thead>
					<tbody>
						@foreach(['A', 'B', 'C'] as $rowLetter)
						<tr>
							<th class="prod-matrix-row-label">{{ $rowLetter }}</th>
							@foreach(['X', 'Y', 'Z'] as $colLetter)
							@php
								$cell = $abcXyz['matrix'][$rowLetter][$colLetter] ?? ['count' => 0, 'code' => $rowLetter . $colLetter, 'bg' => '#f8fafc', 'color' => '#0f172a'];
							@endphp
							<td class="prod-matrix-cell" style="background: {{ $cell['bg'] }}; color: {{ $cell['color'] }};" onclick="filterByAbcXyz('{{ $cell['code'] }}')" title="Filtrar segmento {{ $cell['code'] }}">
								<div class="prod-matrix-count">{{ $cell['count'] }}</div>
								<div class="prod-matrix-code">{{ $cell['code'] }}</div>
							</td>
							@endforeach
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<div class="prod-card-footnote">
				{{ $abcXyz['footnote'] ?? 'ABC por contribución (margen bruto, configurable) · XYZ por variabilidad de demanda con 12 semanas de historial. Total: 1,248 SKU.' }}
			</div>
		</div>

		<!-- Rotación frente a cobertura (50%) -->
		<div class="prod-card" data-testid="prod-bubble-chart-card">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Rotación frente a cobertura</h2>
					<p class="prod-card-subtitle">Burbuja = contribución económica · color = riesgo comercial. Clic en una burbuja abre la ficha del producto.</p>
				</div>
			</div>

			<!-- Canvas/SVG del Gráfico de Burbujas -->
			<div class="prod-bubble-canvas-wrap position-relative" style="height: 230px;">
				<svg id="svgBubbleChart" viewBox="0 0 540 230" class="prod-bubble-svg" style="width: 100%; height: 100%;">
					<!-- Ejes y Líneas Guía -->
					<line x1="50" y1="20" x2="50" y2="195" stroke="#e2e8f0" stroke-width="1.2" />
					<line x1="50" y1="195" x2="520" y2="195" stroke="#e2e8f0" stroke-width="1.2" />
					<line x1="50" y1="105" x2="520" y2="105" stroke="#f1f5f9" stroke-dasharray="3,3" />
					<line x1="285" y1="20" x2="285" y2="195" stroke="#f1f5f9" stroke-dasharray="3,3" />

					<!-- Textos de Cuadrantes -->
					<text x="56" y="32" font-size="9" fill="#94a3b8" font-style="italic">Baja demanda / exceso de cobertura</text>
					<text x="360" y="32" font-size="9" fill="#94a3b8" font-style="italic">Alta demanda / exceso</text>
					<text x="56" y="190" font-size="9" fill="#94a3b8" font-style="italic">Equilibrado</text>
					<text x="360" y="190" font-size="9" fill="#94a3b8" font-style="italic">Alta demanda / baja cobertura</text>

					<!-- Etiquetas en Ejes -->
					<text x="12" y="105" font-size="9.5" fill="#64748b" transform="rotate(-90 12,105)" text-anchor="middle">Cobertura (días)</text>
					<text x="285" y="215" font-size="9.5" fill="#64748b" text-anchor="middle">Velocidad de venta (u/d)</text>

					<!-- Marcas numéricas Eje Y -->
					<text x="44" y="25" font-size="8" fill="#94a3b8" text-anchor="end">100</text>
					<text x="44" y="108" font-size="8" fill="#94a3b8" text-anchor="end">50</text>
					<text x="44" y="195" font-size="8" fill="#94a3b8" text-anchor="end">0</text>

					<!-- Marcas numéricas Eje X -->
					<text x="50" y="206" font-size="8" fill="#94a3b8" text-anchor="middle">0</text>
					<text x="285" y="206" font-size="8" fill="#94a3b8" text-anchor="middle">60</text>
					<text x="515" y="206" font-size="8" fill="#94a3b8" text-anchor="middle">120</text>

					<!-- Burbujas Interactivas (Generadas desde datos exactos) -->
					<!-- Helado Vainilla: Top Left (x:7, y:87) -> cx: 77, cy: 46 -->
					<g class="bubble-node" onclick="selectProductRow(2)" style="cursor: pointer;">
						<circle cx="95" cy="52" r="13" fill="#f59e0b" fill-opacity="0.65" stroke="#f59e0b" stroke-width="1.5" />
						<text x="95" y="34" font-size="8.5" font-weight="600" fill="#78350f" text-anchor="middle">Helado vainilla</text>
					</g>

					<!-- Filete Tilapia: Mid Low Left (x:6, y:10.7) -> cx: 73, cy: 176 -->
					<g class="bubble-node" onclick="selectProductRow(3)" style="cursor: pointer;">
						<circle cx="85" cy="178" r="11" fill="#f59e0b" fill-opacity="0.65" stroke="#f59e0b" stroke-width="1.5" />
						<text x="85" y="163" font-size="8.5" font-weight="600" fill="#78350f" text-anchor="middle">Filete tilapia</text>
					</g>

					<!-- Croissant Mantequilla: Bottom Right (x:74, y:0) -> cx: 333, cy: 194 -->
					<g class="bubble-node" onclick="selectProductRow(5)" style="cursor: pointer;">
						<circle cx="370" cy="190" r="14" fill="#ef4444" fill-opacity="0.65" stroke="#ef4444" stroke-width="1.5" />
						<text x="370" y="172" font-size="8.5" font-weight="600" fill="#991b1b" text-anchor="middle">Croissant</text>
					</g>

					<!-- Leche Entera 1 L: Bottom Right (x:96, y:1.5) -> cx: 418, cy: 192 -->
					<g class="bubble-node" onclick="selectProductRow(1)" style="cursor: pointer;">
						<circle cx="455" cy="188" r="17" fill="#ef4444" fill-opacity="0.65" stroke="#ef4444" stroke-width="1.5" />
						<text x="455" y="167" font-size="8.5" font-weight="700" fill="#991b1b" text-anchor="middle">Leche entera</text>
					</g>

					<!-- Puntos equilibrados en la franja inferior central (Verdes y Amarillos) -->
					<circle cx="160" cy="182" r="10" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />
					<circle cx="185" cy="175" r="8" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />
					<circle cx="210" cy="185" r="12" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />
					<circle cx="235" cy="180" r="9" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />
					<circle cx="265" cy="172" r="14" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />
					<circle cx="300" cy="178" r="11" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />
					<circle cx="325" cy="184" r="8" fill="#10b981" fill-opacity="0.55" stroke="#10b981" />

					<!-- Puntos de riesgo medio dispersos (Ámbar) -->
					<circle cx="110" cy="95" r="9" fill="#f87171" fill-opacity="0.5" stroke="#ef4444" />
					<circle cx="130" cy="115" r="11" fill="#f87171" fill-opacity="0.5" stroke="#ef4444" />
					<circle cx="145" cy="85" r="10" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
					<circle cx="170" cy="130" r="8" fill="#f87171" fill-opacity="0.5" stroke="#ef4444" />
					<circle cx="180" cy="98" r="12" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />

					<!-- Puntos en cuadrante derecho medio (Ámbar) -->
					<circle cx="340" cy="120" r="10" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
					<circle cx="355" cy="135" r="13" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
					<circle cx="375" cy="115" r="9" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
					<circle cx="390" cy="140" r="11" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
					<circle cx="415" cy="150" r="10" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
					<circle cx="430" cy="155" r="8" fill="#fbbf24" fill-opacity="0.6" stroke="#f59e0b" />
				</svg>
			</div>

			<!-- Leyenda del Gráfico de Burbujas -->
			<div class="prod-bubble-legend">
				<span><span class="bubble-legend-dot" style="background:#ef4444;"></span> Riesgo alto</span>
				<span><span class="bubble-legend-dot" style="background:#f59e0b;"></span> Riesgo medio</span>
				<span><span class="bubble-legend-dot" style="background:#10b981;"></span> Equilibrado</span>
			</div>
		</div>
	</section>

	<!-- ====================================================================
	     FILA 4 — MATRIZ MARGEN X ROTACIÓN (50%) Y OPTIMIZACIÓN DEL SURTIDO (50%)
	     ==================================================================== -->
	<section class="prod-row-half mb-3">
		<!-- Matriz margen x rotación (4 Cuadrantes) -->
		<div class="prod-card" data-testid="prod-margin-rotation-card">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Matriz margen × rotación</h2>
					<p class="prod-card-subtitle">Cada cuadrante permite investigar productos y generar acciones. Objetivo de rotación y margen por departamento.</p>
				</div>
			</div>

			<div class="prod-quad-grid">
				<!-- Cuadrante 1: Alta rotación · Alto margen -->
				<div class="prod-quad-box {{ $marginRotMatrix['high_rot_high_margin']['class'] }}" onclick="filterByQuadrant('high_rot_high_margin')" title="Filtrar núcleo del surtido">
					<div class="prod-quad-label">{{ $marginRotMatrix['high_rot_high_margin']['label'] }}</div>
					<div class="prod-quad-val" style="color: {{ $marginRotMatrix['high_rot_high_margin']['val_color'] }};">{{ number_format($marginRotMatrix['high_rot_high_margin']['count']) }}</div>
					<div class="prod-quad-sub">{{ $marginRotMatrix['high_rot_high_margin']['sub'] }}</div>
				</div>

				<!-- Cuadrante 2: Alta rotación · Bajo margen -->
				<div class="prod-quad-box {{ $marginRotMatrix['high_rot_low_margin']['class'] }}" onclick="filterByQuadrant('high_rot_low_margin')" title="Filtrar alta rotación bajo margen">
					<div class="prod-quad-label">{{ $marginRotMatrix['high_rot_low_margin']['label'] }}</div>
					<div class="prod-quad-val" style="color: {{ $marginRotMatrix['high_rot_low_margin']['val_color'] }};">{{ number_format($marginRotMatrix['high_rot_low_margin']['count']) }}</div>
					<div class="prod-quad-sub">{{ $marginRotMatrix['high_rot_low_margin']['sub'] }}</div>
				</div>

				<!-- Cuadrante 3: Baja rotación · Alto margen -->
				<div class="prod-quad-box {{ $marginRotMatrix['low_rot_high_margin']['class'] }}" onclick="filterByQuadrant('low_rot_high_margin')" title="Filtrar baja rotación alto margen">
					<div class="prod-quad-label">{{ $marginRotMatrix['low_rot_high_margin']['label'] }}</div>
					<div class="prod-quad-val" style="color: {{ $marginRotMatrix['low_rot_high_margin']['val_color'] }};">{{ number_format($marginRotMatrix['low_rot_high_margin']['count']) }}</div>
					<div class="prod-quad-sub">{{ $marginRotMatrix['low_rot_high_margin']['sub'] }}</div>
				</div>

				<!-- Cuadrante 4: Baja rotación · Bajo margen -->
				<div class="prod-quad-box {{ $marginRotMatrix['low_rot_low_margin']['class'] }}" onclick="filterByQuadrant('low_rot_low_margin')" title="Filtrar candidatos a descontinuación">
					<div class="prod-quad-label">{{ $marginRotMatrix['low_rot_low_margin']['label'] }}</div>
					<div class="prod-quad-val" style="color: {{ $marginRotMatrix['low_rot_low_margin']['val_color'] }};">{{ number_format($marginRotMatrix['low_rot_low_margin']['count']) }}</div>
					<div class="prod-quad-sub">{{ $marginRotMatrix['low_rot_low_margin']['sub'] }}</div>
				</div>
			</div>
		</div>

		<!-- Optimización del surtido (5 Barras horizontales) -->
		<div class="prod-card" data-testid="prod-assortment-card">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Optimización del surtido</h2>
					<p class="prod-card-subtitle">Recomendación por producto. Nunca descontinúa automáticamente: considera esenciales, estacionalidad y compromisos comerciales.</p>
				</div>
			</div>

			<div class="prod-assort-bars-wrap">
				@foreach($assortmentOpt['bars'] as $bar)
				<div class="prod-assort-bar-row" onclick="filterByAssortment('{{ $bar['filter'] }}')" title="Filtrar recomendación {{ $bar['label'] }}">
					<div class="prod-assort-label">{{ $bar['label'] }}</div>
					<div class="prod-assort-progress-wrap">
						<div class="prod-assort-progress-bar" style="width: {{ $bar['pct'] }}%; background-color: {{ $bar['color'] }};"></div>
					</div>
					<div class="prod-assort-count">{{ $bar['count'] }}</div>
				</div>
				@endforeach
			</div>

			<div class="prod-card-footnote">
				{{ $assortmentOpt['footnote'] }}
			</div>
		</div>
	</section>

	<!-- ====================================================================
	     FILA 5 — DISPONIBILIDAD Y VENTA PERDIDA (50%) Y RIESGO FEFO (50%)
	     ==================================================================== -->
	<section class="prod-row-half mb-3">
		<!-- Disponibilidad y venta potencial perdida (50%) -->
		<div class="prod-card" data-testid="prod-lost-sales-card">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Disponibilidad y venta potencial perdida</h2>
					<p class="prod-card-subtitle">Estimaciones, no transacciones reales. Método: demanda promedio de los 28 días previos · Período: 08/10–06/11/2026 · Confianza: media.</p>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table prod-table-compact mb-2">
					<thead>
						<tr>
							<th>Producto</th>
							<th class="text-right">Días sin disp.</th>
							<th class="text-right">Demanda est.</th>
							<th class="text-right">Unids. no vendidas</th>
							<th class="text-right">Margen no capturado</th>
							<th class="text-center">Tiendas</th>
							<th class="text-center">Prioridad</th>
						</tr>
					</thead>
					<tbody>
						@foreach($lostSales['rows'] as $ls)
						<tr onclick="selectProductRow({{ $ls['prod_id'] }})" style="cursor: pointer;">
							<td class="font-weight-600 text-dark">{{ $ls['product'] }}</td>
							<td class="text-right">{{ $ls['days_out'] }}</td>
							<td class="text-right">{{ $ls['est_demand'] }}</td>
							<td class="text-right font-weight-600">{{ $ls['lost_units'] }}</td>
							<td class="text-right font-weight-bold text-dark">{{ $ls['lost_margin'] }}</td>
							<td class="text-center">{{ $ls['stores'] }}</td>
							<td class="text-center">
								<span class="dp-opp-priority {{ $ls['priority_class'] }}">{{ $ls['priority'] }}</span>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<div class="prod-card-footnote">
				{{ $lostSales['footnote'] }}
			</div>
		</div>

		<!-- Riesgo FEFO en perecederos (50%) -->
		<div class="prod-card" data-testid="prod-fefo-card">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Riesgo FEFO en perecederos</h2>
					<p class="prod-card-subtitle">Cobertura frente a vida útil restante. Detalle en Mermas y Prevención.</p>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table prod-table-compact mb-2">
					<thead>
						<tr>
							<th>Producto</th>
							<th>Vida útil vs. cobertura (días)</th>
							<th class="text-right">Valor expuesto</th>
						</tr>
					</thead>
					<tbody>
						@foreach($fefo['rows'] as $item)
						<tr onclick="selectProductRow({{ $item['prod_id'] }})" style="cursor: pointer;">
							<td class="font-weight-600 text-dark" style="width: 28%;">{{ $item['product'] }}</td>
							<td style="width: 52%;">
								<div class="d-flex flex-column" style="gap: 2px;">
									<div class="prod-fefo-bars">
										<!-- Barra verde (Vida Útil) -->
										<div class="prod-fefo-life-bar" style="width: {{ min(100, ($item['shelf_life'] / max(1, $item['shelf_life'], $item['coverage'])) * 100) }}%;"></div>
										<!-- Barra roja (Cobertura excedente si coverage > shelf_life) -->
										@if($item['has_risk'])
										<div class="prod-fefo-cov-bar" style="width: {{ min(100, (($item['coverage'] - $item['shelf_life']) / max(1, $item['coverage'])) * 100) }}%;"></div>
										@endif
									</div>
									<small class="text-muted" style="font-size: 10px;">{{ $item['label'] }}</small>
								</div>
							</td>
							<td class="text-right" style="width: 20%;">
								<span class="{{ $item['risk_class'] }}">{{ $item['exposed_val'] }}</span>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<div class="prod-card-footnote">
				{{ $fefo['footnote'] }}
			</div>
		</div>
	</section>

	<!-- ====================================================================
	     FILA 6 — ACCIONES PRIORITARIAS (65%) Y SIMULADOR COMERCIAL (35%)
	     ==================================================================== -->
	<section class="prod-row-2 mb-3">
		<!-- Acciones prioritarias por producto (65%) -->
		<div class="prod-explorer-panel" data-testid="prod-priority-actions-panel">
			<div class="prod-card-header">
				<div>
					<h2 class="prod-card-title">Acciones prioritarias por producto</h2>
					<p class="prod-card-subtitle">Ordenadas por impacto económico estimado. Cada oportunidad se convierte en una tarea de DAILY y permite verificar el resultado.</p>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table prod-table-compact mb-2" id="tablePriorityActions">
					<thead>
						<tr>
							<th>Producto</th>
							<th>Depto.</th>
							<th>Problema detectado</th>
							<th class="text-right">Impacto est.</th>
							<th>Acción recomendada</th>
							<th class="text-center">Prioridad</th>
							<th>Responsable</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach($priActions as $pa)
						<tr onclick="selectProductRow({{ $pa['prod_id'] }})" style="cursor: pointer;">
							<td class="font-weight-600 text-dark">{{ $pa['product'] }}</td>
							<td class="small text-muted">{{ $pa['department'] }}</td>
							<td class="small">{{ $pa['issue'] }}</td>
							<td class="text-right font-weight-bold text-dark">{{ $pa['impact'] }}</td>
							<td class="small font-weight-600 text-dark">{{ $pa['action'] }}</td>
							<td class="text-center">
								<span class="dp-opp-priority {{ $pa['priority_class'] }}">{{ $pa['priority'] }}</span>
							</td>
							<td class="small text-muted">{{ $pa['owner'] }}</td>
							<td class="text-center">
								<span class="prod-action-status {{ $pa['status_badge'] }}">{{ $pa['status'] }}</span>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		<!-- Simulador comercial por SKU (35%) -->
		<div class="prod-detail-panel" id="prodSimulatorPanel" data-testid="prod-simulator-panel">
			<div class="prod-card-header mb-2 pb-1 border-bottom">
				<div>
					<h3 class="prod-card-title mb-0" id="simTitle">Simulador comercial – {{ $selectedProd['name'] ?? 'Leche entera 1 L' }}</h3>
					<p class="prod-card-subtitle" style="font-size: 11px;">What-if. No modifica precios, inventario ni compras reales.</p>
				</div>
			</div>

			<!-- Slider 1: Cambio de precio -->
			<div class="prod-sim-control mb-3">
				<div class="d-flex justify-content-between align-items-center mb-1">
					<label class="prod-sim-label mb-0">Cambio de precio</label>
					<span class="prod-sim-value" id="simPriceVal">+3.2 % ($1.95)</span>
				</div>
				<input type="range" class="custom-range prod-slider" id="sliderPriceChange" min="-15" max="15" step="0.5" value="3.2" oninput="runCommercialSimulation()">
			</div>

			<!-- Slider 2: Variación de demanda -->
			<div class="prod-sim-control mb-3">
				<div class="d-flex justify-content-between align-items-center mb-1">
					<label class="prod-sim-label mb-0">Variación de demanda</label>
					<span class="prod-sim-value" id="simDemandVal">-2.6 %</span>
				</div>
				<input type="range" class="custom-range prod-slider" id="sliderDemandChange" min="-30" max="30" step="0.5" value="-2.6" oninput="runCommercialSimulation()">
			</div>

			<!-- Métricas comparativas del Simulador -->
			<div class="row no-gutters mb-2" style="gap: 8px;">
				<div class="col prod-sim-tile">
					<div class="prod-sim-tile-label">Contribución actual</div>
					<div class="prod-sim-tile-val" id="simContribActual">$1,210</div>
				</div>
				<div class="col prod-sim-tile is-simulated">
					<div class="prod-sim-tile-label">Escenario simulado</div>
					<div class="prod-sim-tile-val text-success" id="simContribProjected">$1,347</div>
				</div>
			</div>

			<!-- Diferencia Calculada -->
			<div class="d-flex justify-content-between align-items-center py-2 px-2 rounded mb-2" style="background: #f8fafc; border: 1px dashed #e2e8f0;">
				<span class="small font-weight-bold text-dark">Diferencia:</span>
				<span class="font-weight-bold text-success" id="simDifference" style="font-size: 14px;">+$137 / mes</span>
			</div>

			<!-- Supuestos y Riesgos -->
			<div class="small text-muted" style="font-size: 10.5px; line-height: 1.35;" id="simAssumptionsText">
				{{ $sim['assumptions'] ?? 'Supuestos: elasticidad –0.8 estimada, costo sin cambio. Riesgos: producto de canasta básica, sensibilidad al precio. Confianza: baja.' }}
			</div>
		</div>
	</section>

	<!-- ====================================================================
	     PIE DE PÁGINA (DISCLAIMER FIDELIDAD CON LA FOTO)
	     ==================================================================== -->
	<footer class="prod-dash-footer text-center py-2">
		<small class="text-muted" style="font-size: 11px; letter-spacing: 0.02em;">
			DAILY · Productos por Departamento · Mockup con datos de ejemplo, sin cifras reales
		</small>
	</footer>
</div>

<!-- ====================================================================
     MODAL: COMPARAR SUCURSALES (MULTI-TIENDA)
     ==================================================================== -->
<div class="modal fade" id="modalBranchCompare" tabindex="-1" role="dialog" aria-labelledby="modalBranchCompareTitle" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content shadow-lg border-0" style="border-radius: 14px;">
			<div class="modal-header bg-light border-0 py-3 px-4">
				<h5 class="modal-title font-weight-bold" id="modalBranchCompareTitle">
					<i class="fa-solid fa-code-compare text-success mr-2"></i>Comparación entre Sucursales · <span id="modalCompareProdName">Leche entera 1 L</span>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body px-4 py-3">
				<p class="text-muted small mb-3">Analiza las diferencias de rotación, stock, cobertura y margen del producto en cada tienda de la red.</p>
				<div class="table-responsive">
					<table class="table table-sm table-bordered">
						<thead class="thead-light">
							<tr>
								<th>Sucursal</th>
								<th class="text-right">Precio venta</th>
								<th class="text-right">Margen (%)</th>
								<th class="text-right">Stock actual</th>
								<th class="text-right">Venta u/d</th>
								<th class="text-right">Cobertura</th>
								<th class="text-center">Estado</th>
							</tr>
						</thead>
						<tbody id="modalCompareTbody">
							<tr>
								<td class="font-weight-600">Sucursal Centro</td>
								<td class="text-right">$1.89</td>
								<td class="text-right">22.8 %</td>
								<td class="text-right">37 unids</td>
								<td class="text-right">41 u/d</td>
								<td class="text-right text-danger font-weight-bold">0.9 d</td>
								<td class="text-center"><span class="badge badge-danger">Crítica</span></td>
							</tr>
							<tr>
								<td class="font-weight-600">Sucursal Norte</td>
								<td class="text-right">$1.89</td>
								<td class="text-right">22.0 %</td>
								<td class="text-right">59 unids</td>
								<td class="text-right">33 u/d</td>
								<td class="text-right text-warning font-weight-bold">1.8 d</td>
								<td class="text-center"><span class="badge badge-warning">Atención</span></td>
							</tr>
							<tr>
								<td class="font-weight-600">Sucursal Sur</td>
								<td class="text-right">$1.89</td>
								<td class="text-right">21.8 %</td>
								<td class="text-right">52 unids</td>
								<td class="text-right">22 u/d</td>
								<td class="text-right text-warning font-weight-bold">2.4 d</td>
								<td class="text-center"><span class="badge badge-warning">Atención</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer border-0 px-4 py-2">
				<button type="button" class="btn btn-light border font-weight-bold btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-success font-weight-bold btn-sm" onclick="$('#modalBranchCompare').modal('hide'); openReplenishModal();">
					Solicitar Traslado o Reposición
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: CREAR TAREA DE SEGUIMIENTO (DAILY TASKS)
     ==================================================================== -->
<div class="modal fade" id="modalProductTask" tabindex="-1" role="dialog" aria-labelledby="modalProductTaskTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content shadow-lg border-0" style="border-radius: 14px;">
			<div class="modal-header bg-light border-0 py-3 px-4">
				<h5 class="modal-title font-weight-bold" id="modalProductTaskTitle">
					<i class="fa-solid fa-list-check text-success mr-2"></i>Crear Tarea Comercial
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body px-4 py-3">
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Título de la Tarea</label>
					<input type="text" class="form-control form-control-sm" id="inputTaskName" value="Revisar reposición urgente de Leche entera 1 L">
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Descripción y Acciones Recomendadas</label>
					<textarea class="form-control form-control-sm" id="inputTaskDesc" rows="3">Agotamiento inminente estimado el 07/11 ~12:00. Cobertura actual de 1.5 días. Revisar orden de compra pendiente y programar recepción prioritaria.</textarea>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold">Responsable</label>
						<select class="form-control form-control-sm" id="selectTaskAssignee">
							<option>Planeación y Compras</option>
							<option>Jefe de Bakery</option>
							<option>Compras</option>
							<option>Categoría</option>
							<option>Jefe de Departamento</option>
						</select>
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold">Fecha Límite</label>
						<input type="date" class="form-control form-control-sm" id="inputTaskDueDate" value="{{ date('Y-m-d', strtotime('+2 days')) }}">
					</div>
				</div>
			</div>
			<div class="modal-footer border-0 px-4 py-2">
				<button type="button" class="btn btn-light border font-weight-bold btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-success font-weight-bold btn-sm" onclick="saveProductTask()">Guardar Tarea</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: SOLICITAR REPOSICIÓN
     ==================================================================== -->
<div class="modal fade" id="modalProductReplenish" tabindex="-1" role="dialog" aria-labelledby="modalProductReplenishTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content shadow-lg border-0" style="border-radius: 14px;">
			<div class="modal-header bg-light border-0 py-3 px-4">
				<h5 class="modal-title font-weight-bold" id="modalProductReplenishTitle">
					<i class="fa-solid fa-truck-ramp-box text-success mr-2"></i>Solicitud de Reposición · <span id="modalReplenishProdName">Leche entera 1 L</span>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body px-4 py-3">
				<div class="alert alert-warning py-2 px-3 small mb-3">
					<i class="fa-solid fa-triangle-exclamation mr-1"></i> Cobertura actual: <strong>1.5 días</strong>. Venta estimada: <strong>96 u/d</strong>.
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Cantidad Sugerida a Solicitar</label>
					<div class="input-group input-group-sm">
						<input type="number" class="form-control" id="inputReplenishQty" value="500">
						<div class="input-group-append"><span class="input-group-text">unidades</span></div>
					</div>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Proveedor / Almacén Central</label>
					<input type="text" class="form-control form-control-sm" readonly value="Distribuidora Láctea Central (Contrato activo)">
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Destino de la Reposición</label>
					<select class="form-control form-control-sm">
						<option>Todas las sucursales con cobertura crítica (Centro, Norte, Sur)</option>
						<option>Sucursal Centro (0.9 d)</option>
						<option>Sucursal Norte (1.8 d)</option>
						<option>Sucursal Sur (2.4 d)</option>
					</select>
				</div>
			</div>
			<div class="modal-footer border-0 px-4 py-2">
				<button type="button" class="btn btn-light border font-weight-bold btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-success font-weight-bold btn-sm" onclick="submitReplenishOrder()">Confirmar Solicitud</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: REVISAR PRECIO
     ==================================================================== -->
<div class="modal fade" id="modalPriceReview" tabindex="-1" role="dialog" aria-labelledby="modalPriceReviewTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content shadow-lg border-0" style="border-radius: 14px;">
			<div class="modal-header bg-light border-0 py-3 px-4">
				<h5 class="modal-title font-weight-bold" id="modalPriceReviewTitle">
					<i class="fa-solid fa-tags text-warning mr-2"></i>Revisión de Precio · <span id="modalPriceProdName">Leche entera 1 L</span>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body px-4 py-3">
				<div class="row mb-3">
					<div class="col-6">
						<label class="small text-muted mb-0">Precio actual</label>
						<div class="font-weight-bold text-dark" style="font-size: 16px;" id="lblCurrentPriceModal">$1.89</div>
					</div>
					<div class="col-6">
						<label class="small text-muted mb-0">Margen actual</label>
						<div class="font-weight-bold text-dark" style="font-size: 16px;" id="lblCurrentMarginModal">22.2 %</div>
					</div>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Propuesta de Nuevo Precio</label>
					<div class="input-group input-group-sm">
						<div class="input-group-prepend"><span class="input-group-text">$</span></div>
						<input type="number" step="0.01" class="form-control" id="inputNewPrice" value="1.95" oninput="updateNewPriceCalc()">
					</div>
				</div>
				<div class="p-2 rounded bg-light border small text-muted mb-2">
					Impacto estimado en margen: <strong class="text-success" id="lblModalMarginImpact">+2.4 pp ($0.48 unitario)</strong>. Elasticidad estimada: –0.8.
				</div>
			</div>
			<div class="modal-footer border-0 px-4 py-2">
				<button type="button" class="btn btn-light border font-weight-bold btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary font-weight-bold btn-sm" style="background:#16a34a; border-color:#16a34a;" onclick="applyPriceChange()">Guardar Propuesta</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     SCRIPTS DEL DASHBOARD DE PRODUCTOS (REACTIVIDAD COMPLETA SIN RECARGA)
     ==================================================================== -->
<script>
window.dailyProductsData = {!! $productsJson !!};
window.dailyBubbleData = {!! $bubblePointsJson !!};
window.dailySimData = {!! $simJson !!};
window.activeProductId = {{ $selectedProd['id'] ?? 1 }};

// Selección de producto en la tabla y sincronización inmediata de Ficha 360° y Simulador
function selectProductRow(prodId) {
	window.activeProductId = prodId;
	const prod = window.dailyProductsData.find(p => p.id === prodId);
	if (!prod) return;

	// 1. Resaltar fila seleccionada en el explorador
	document.querySelectorAll('.prod-row-item').forEach(tr => {
		tr.classList.remove('is-selected');
		if (parseInt(tr.getAttribute('data-prod-id')) === prodId) {
			tr.classList.add('is-selected');
		}
	});

	// 2. Actualizar Ficha 360°
	const avatar = document.getElementById('detProdAvatar');
	if (avatar) {
		avatar.textContent = prod.initial;
		avatar.style.background = prod.avatar_bg;
		avatar.style.color = prod.avatar_color;
	}

	const setTxt = (id, val) => {
		const el = document.getElementById(id);
		if (el) el.textContent = val;
	};

	setTxt('detProdName', prod.name);
	setTxt('detProdSkuUpc', `${prod.sku} · UPC ${prod.upc}`);
	setTxt('detProdBrandDept', `Marca: ${prod.brand} · ${prod.department} / ${prod.category}`);
	setTxt('detProdPrice', `$${parseFloat(prod.price).toFixed(2)}`);
	setTxt('detProdCost', `$${parseFloat(prod.cost).toFixed(2)}`);
	setTxt('detProdMarginUnit', `$${parseFloat(prod.unit_margin).toFixed(2)}`);
	setTxt('detProdSalesDaily', `${prod.sales_per_day} u/d`);
	setTxt('detProdDemand', `▲ ${prod.demand_trend}`);
	setTxt('detProdElasticity', `~${prod.elasticity} *`);
	setTxt('detProdStock', prod.stock);

	const covEl = document.getElementById('detProdCoverage');
	if (covEl) {
		covEl.textContent = `${prod.coverage_days} d`;
		covEl.className = 'prod-metric-box-val ' + (prod.coverage_days < 2 ? 'text-danger' : (prod.coverage_days > 30 ? 'text-warning' : 'text-success'));
	}

	const riskBadge = document.getElementById('detProdRiskBadge');
	if (riskBadge) {
		riskBadge.textContent = prod.risk_level;
		riskBadge.className = 'badge px-2 py-1 ' + (prod.risk_level === 'Alto' ? 'badge-danger' : (prod.risk_level === 'Medio' ? 'badge-warning' : 'badge-success'));
	}

	setTxt('detProdStockoutEst', prod.stockout_est);
	setTxt('detProdNextReception', prod.next_reception);

	// Actualizar ciclo de vida
	document.querySelectorAll('#detProdLifecycleSteps .prod-lifecycle-step').forEach(step => {
		step.classList.remove('is-active');
		if (step.getAttribute('data-step') === prod.lifecycle) {
			step.classList.add('is-active');
		}
	});

	// Actualizar tabla por sucursal en Ficha 360°
	const branchTbody = document.getElementById('detProdBranchTbody');
	if (branchTbody && prod.branch_comparison) {
		branchTbody.innerHTML = prod.branch_comparison.map(bc => `
			<tr>
				<td class="font-weight-600">${bc.branch}</td>
				<td class="text-right">${bc.margin}</td>
				<td class="text-right">${bc.sales}</td>
				<td class="text-right font-weight-600 ${bc.coverage_state === 'critica' ? 'text-danger' : 'text-warning'}">${bc.coverage}</td>
			</tr>
		`).join('');
	}

	// 3. Actualizar Simulador Comercial para este producto
	setTxt('simTitle', `Simulador comercial – ${prod.name}`);
	updateSimulatorForProduct(prod);
}

// Simulador comercial interactivo What-If
function updateSimulatorForProduct(prod) {
	window.dailySimData.product_id = prod.id;
	window.dailySimData.product_name = prod.name;
	window.dailySimData.base_price = parseFloat(prod.price);
	window.dailySimData.base_cost = parseFloat(prod.cost);
	window.dailySimData.elasticity = parseFloat(prod.elasticity);
	window.dailySimData.base_units_monthly = prod.sales_per_day * 30;
	runCommercialSimulation();
}

function runCommercialSimulation() {
	const pSlider = document.getElementById('sliderPriceChange');
	const dSlider = document.getElementById('sliderDemandChange');
	if (!pSlider || !dSlider) return;

	const pDeltaPct = parseFloat(pSlider.value);
	const dDeltaPct = parseFloat(dSlider.value);

	const basePrice = window.dailySimData.base_price || 1.89;
	const baseCost = window.dailySimData.base_cost || 1.47;
	const elasticity = window.dailySimData.elasticity || -0.8;
	const baseUnits = window.dailySimData.base_units_monthly || 2880;

	// Nuevos valores simulados
	const newPrice = basePrice * (1 + pDeltaPct / 100);
	const newDemandUnits = baseUnits * (1 + dDeltaPct / 100);

	const currentContrib = Math.round(baseUnits * (basePrice - baseCost));
	const projectedContrib = Math.round(newDemandUnits * (newPrice - baseCost));
	const diff = projectedContrib - currentContrib;

	const signP = pDeltaPct >= 0 ? '+' : '';
	const signD = dDeltaPct >= 0 ? '+' : '';
	const signDiff = diff >= 0 ? '+' : '';

	const setTxt = (id, val) => {
		const el = document.getElementById(id);
		if (el) el.textContent = val;
	};

	setTxt('simPriceVal', `${signP}${pDeltaPct.toFixed(1)} % ($${newPrice.toFixed(2)})`);
	setTxt('simDemandVal', `${signD}${dDeltaPct.toFixed(1)} %`);
	setTxt('simContribActual', `$${currentContrib.toLocaleString()}`);
	setTxt('simContribProjected', `$${projectedContrib.toLocaleString()}`);

	const diffEl = document.getElementById('simDifference');
	if (diffEl) {
		diffEl.textContent = `${signDiff}$${Math.abs(diff).toLocaleString()} / mes`;
		diffEl.className = 'font-weight-bold ' + (diff >= 0 ? 'text-success' : 'text-danger');
	}
}

// Filtros Reactivos sin recargar la página
function applyProductFilters() {
	const searchVal = (document.getElementById('filterProdSearch')?.value || '').toLowerCase().trim();
	const branchVal = document.getElementById('filterProdBranch')?.value || '';
	const deptVal = document.getElementById('filterProdDept')?.value || '';
	const catVal = (document.getElementById('filterProdCategory')?.value || '').toLowerCase();
	const subVal = (document.getElementById('filterProdSubcategory')?.value || '').toLowerCase();
	const brandVal = (document.getElementById('filterProdBrand')?.value || '').toLowerCase();
	const supplierVal = (document.getElementById('filterProdSupplier')?.value || '').toLowerCase();
	const availVal = document.getElementById('filterProdAvailability')?.value || '';
	const rotVal = document.getElementById('filterProdRotation')?.value || '';

	let visibleCount = 0;
	document.querySelectorAll('#tbodyProductExplorer .prod-row-item').forEach(tr => {
		const pId = parseInt(tr.getAttribute('data-prod-id'));
		const p = window.dailyProductsData.find(item => item.id === pId);
		if (!p) return;

		let match = true;

		// Búsqueda por SKU, UPC, Nombre
		if (searchVal) {
			const matchName = p.name.toLowerCase().includes(searchVal);
			const matchSku = p.sku.toLowerCase().includes(searchVal);
			const matchUpc = p.upc.toLowerCase().includes(searchVal);
			if (!matchName && !matchSku && !matchUpc) match = false;
		}

		// Filtro Departamento
		if (deptVal && p.dept_code !== deptVal) {
			match = false;
		}

		// Filtro Categoría
		if (catVal && p.category.toLowerCase() !== catVal) {
			match = false;
		}

		// Filtro Subcategoría
		if (subVal && (p.subcategory || '').toLowerCase() !== subVal) {
			match = false;
		}

		// Filtro Marca
		if (brandVal && (p.brand || '').toLowerCase() !== brandVal) {
			match = false;
		}

		// Filtro Proveedor
		if (supplierVal && (p.supplier || '').toLowerCase() !== supplierVal) {
			match = false;
		}

		// Filtro Disponibilidad
		if (availVal) {
			if (availVal === 'stockout' && p.stock > 0) match = false;
			else if (availVal === 'critica' && p.status !== 'critica') match = false;
			else if (availVal === 'atencion' && p.status !== 'atencion') match = false;
			else if (availVal === 'normal' && p.status !== 'normal') match = false;
		}

		// Filtro Rotación
		if (rotVal) {
			if (rotVal === 'high' && p.sales_per_day <= 30) match = false;
			else if (rotVal === 'medium' && (p.sales_per_day < 10 || p.sales_per_day > 30)) match = false;
			else if (rotVal === 'low' && p.sales_per_day >= 10) match = false;
		}

		if (match) {
			tr.style.display = '';
			visibleCount++;
		} else {
			tr.style.display = 'none';
		}
	});

	// Actualizar contador
	const pagEl = document.getElementById('txtProdsPagination');
	if (pagEl) pagEl.textContent = `1-${visibleCount} de 1,248`;
}

// Filtro rápido por KPI Cards
function filterByKpi(type) {
	const availSel = document.getElementById('filterProdAvailability');
	const rotSel = document.getElementById('filterProdRotation');
	const searchInp = document.getElementById('filterProdSearch');

	if (type === 'all') {
		if (availSel) availSel.value = '';
		if (rotSel) rotSel.value = '';
		if (searchInp) searchInp.value = '';
	} else if (type === 'available') {
		if (availSel) availSel.value = 'normal';
	} else if (type === 'low_rot') {
		if (rotSel) rotSel.value = 'low';
	} else if (type === 'stockout') {
		if (availSel) availSel.value = 'stockout';
	} else if (type === 'low_margin') {
		if (searchInp) searchInp.value = '';
		if (availSel) availSel.value = 'atencion';
	} else if (type === 'expiring') {
		if (searchInp) searchInp.value = '';
		if (availSel) availSel.value = 'atencion';
	} else if (type === 'opportunity') {
		if (availSel) availSel.value = 'critica';
	}
	applyProductFilters();
}

// Filtro rápido por celda ABC-XYZ
function filterByAbcXyz(code) {
	const row = code.charAt(0);
	const col = code.charAt(1);
	document.querySelectorAll('#tbodyProductExplorer .prod-row-item').forEach(tr => {
		const pId = parseInt(tr.getAttribute('data-prod-id'));
		const p = window.dailyProductsData.find(item => item.id === pId);
		if (p && (p.abc === row || p.xyz === col)) {
			tr.style.display = '';
		} else {
			tr.style.display = 'none';
		}
	});
	const pagEl = document.getElementById('txtProdsPagination');
	if (pagEl) pagEl.textContent = `Segmento ${code} filtrado`;
}

// Filtro rápido por cuadrante margen x rotación
function filterByQuadrant(quadrantKey) {
	document.querySelectorAll('#tbodyProductExplorer .prod-row-item').forEach(tr => {
		const pId = parseInt(tr.getAttribute('data-prod-id'));
		const p = window.dailyProductsData.find(item => item.id === pId);
		if (p && (p.quadrant === quadrantKey || !p.quadrant)) {
			tr.style.display = '';
		} else {
			tr.style.display = 'none';
		}
	});
	const pagEl = document.getElementById('txtProdsPagination');
	if (pagEl) pagEl.textContent = `Cuadrante filtrado`;
}

// Filtro por recomendación de optimización del surtido
function filterByAssortment(action) {
	document.querySelectorAll('#tbodyProductExplorer .prod-row-item').forEach(tr => {
		const pId = parseInt(tr.getAttribute('data-prod-id'));
		const p = window.dailyProductsData.find(item => item.id === pId);
		if (p && (p.surtido_action === action || action === 'mantener')) {
			tr.style.display = '';
		} else {
			tr.style.display = 'none';
		}
	});
}

// Ordenamiento de tabla por columnas
let currentSortCol = null;
let currentSortAsc = true;
function sortExplorerTable(colKey) {
	if (currentSortCol === colKey) {
		currentSortAsc = !currentSortAsc;
	} else {
		currentSortCol = colKey;
		currentSortAsc = true;
	}

	const tbody = document.getElementById('tbodyProductExplorer');
	const rows = Array.from(tbody.querySelectorAll('tr'));

	rows.sort((a, b) => {
		const idA = parseInt(a.getAttribute('data-prod-id'));
		const idB = parseInt(b.getAttribute('data-prod-id'));
		const pA = window.dailyProductsData.find(p => p.id === idA) || {};
		const pB = window.dailyProductsData.find(p => p.id === idB) || {};

		let valA = pA[colKey];
		let valB = pB[colKey];

		if (colKey === 'dept') { valA = pA.department; valB = pB.department; }
		if (colKey === 'sales') { valA = pA.sales_per_day; valB = pB.sales_per_day; }
		if (colKey === 'margin') { valA = pA.margin_pct; valB = pB.margin_pct; }
		if (colKey === 'coverage') { valA = pA.coverage_days; valB = pB.coverage_days; }

		if (typeof valA === 'string') {
			return currentSortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
		}
		return currentSortAsc ? (valA - valB) : (valB - valA);
	});

	rows.forEach(r => tbody.appendChild(r));
}

// Selección múltiple
function toggleSelectAllProds(checked) {
	document.querySelectorAll('.chk-prod-row').forEach(cb => {
		cb.checked = checked;
	});
	updateSelectedCount();
}

function updateSelectedCount() {
	const count = document.querySelectorAll('.chk-prod-row:checked').length;
	const txt = document.getElementById('txtSelectedProdsCount');
	if (txt) txt.textContent = `${count} seleccionados`;
}

// Exportación a Excel / CSV Funcional
function exportProductsToExcel() {
	let csv = "ID,Producto,SKU,UPC,Departamento,Categoria,Precio,Margen_Pct,Stock,Venta_Dia,Cobertura_Dias,Estado\n";
	window.dailyProductsData.forEach(p => {
		csv += `"${p.id}","${p.name}","${p.sku}","${p.upc}","${p.department}","${p.category}",${p.price},${p.margin_pct},${p.stock},${p.sales_per_day},${p.coverage_days},"${p.status_label}"\n`;
	});

	const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	const link = document.createElement("a");
	link.href = URL.createObjectURL(blob);
	link.setAttribute("download", `productos_por_departamento_${new Date().toISOString().slice(0,10)}.csv`);
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
}

// Modales interactivos
function openBranchCompareModal() {
	const prod = window.dailyProductsData.find(p => p.id === window.activeProductId) || window.dailyProductsData[0];
	document.getElementById('modalCompareProdName').textContent = prod.name;
	$('#modalBranchCompare').modal('show');
}

function openCreateTaskModal(mode) {
	const prod = window.dailyProductsData.find(p => p.id === window.activeProductId) || window.dailyProductsData[0];
	if (mode === 'single') {
		document.getElementById('inputTaskName').value = `Revisar reposición y stock de ${prod.name}`;
		document.getElementById('inputTaskDesc').value = `SKU: ${prod.sku}. Cobertura actual: ${prod.coverage_days} días. Venta diaria: ${prod.sales_per_day} u/d. Riesgo: ${prod.risk_level}.`;
	} else {
		const count = document.querySelectorAll('.chk-prod-row:checked').length || 3;
		document.getElementById('inputTaskName').value = `Plan comercial para ${count} SKUs seleccionados`;
		document.getElementById('inputTaskDesc').value = `Revisar compras, márgenes y niveles de reposición urgente para el lote de productos seleccionados en el explorador.`;
	}
	$('#modalProductTask').modal('show');
}

function saveProductTask() {
	$('#modalProductTask').modal('hide');
	alert("✓ Tarea comercial creada exitosamente y asignada en DAILY Tasks.");
}

function openReplenishModal() {
	const prod = window.dailyProductsData.find(p => p.id === window.activeProductId) || window.dailyProductsData[0];
	document.getElementById('modalReplenishProdName').textContent = prod.name;
	$('#modalProductReplenish').modal('show');
}

function submitReplenishOrder() {
	$('#modalProductReplenish').modal('hide');
	alert("✓ Solicitud de reposición registrada y transmitida al módulo de Inventario y Almacenes.");
}

function openPriceReviewModal() {
	const prod = window.dailyProductsData.find(p => p.id === window.activeProductId) || window.dailyProductsData[0];
	document.getElementById('modalPriceProdName').textContent = prod.name;
	document.getElementById('lblCurrentPriceModal').textContent = `$${parseFloat(prod.price).toFixed(2)}`;
	document.getElementById('lblCurrentMarginModal').textContent = `${parseFloat(prod.margin_pct).toFixed(1)} %`;
	$('#modalPriceReview').modal('show');
}

function updateNewPriceCalc() {
	const val = parseFloat(document.getElementById('inputNewPrice').value) || 1.89;
	const prod = window.dailyProductsData.find(p => p.id === window.activeProductId) || window.dailyProductsData[0];
	const newMargin = ((val - prod.cost) / val) * 100;
	document.getElementById('lblModalMarginImpact').textContent = `Nuevo margen: ${newMargin.toFixed(1)} % ($${(val - prod.cost).toFixed(2)} unitario)`;
}

function applyPriceChange() {
	$('#modalPriceReview').modal('hide');
	alert("✓ Propuesta de precio registrada para evaluación de categoría.");
}

function toggleDateDropdown() {
	// Selector de fechas rápido
	const cur = document.getElementById('filterProdDate').value;
	if (cur.includes('08/10')) {
		document.getElementById('filterProdDate').value = '01/10 – 31/10/2026';
	} else {
		document.getElementById('filterProdDate').value = '08/10 – 06/11/2026';
	}
	applyProductFilters();
}
</script>

