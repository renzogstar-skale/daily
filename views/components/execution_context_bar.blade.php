@php
	use Grocy\Services\UnifiedInventoryEventService;
	use Grocy\Services\DatabaseService;

	$currentUserId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;
	$eventSvc = UnifiedInventoryEventService::GetInstance();
	
	// Cargar contexto guardado en DB para este usuario
	$activeCtx = [];
	try {
		$activeCtx = $eventSvc->GetContext($currentUserId);
	} catch (\Throwable $e) {}

	// Sobrescribir con parámetros de la URL actual si vienen en el request
	$reqBranch = $_GET['branch_id'] ?? null;
	$reqProduct = $_GET['product_id'] ?? null;
	$reqCase = $_GET['case_id'] ?? null;
	$reqDevice = $_GET['device_id'] ?? null;

	if ($reqBranch || $reqProduct || $reqCase || $reqDevice) {
		$newParams = [];
		if ($reqBranch) $newParams['branch_id'] = intval($reqBranch);
		if ($reqProduct) $newParams['product_id'] = intval($reqProduct);
		if ($reqCase) $newParams['case_id'] = intval($reqCase);
		if ($reqDevice) $newParams['device_id'] = intval($reqDevice);
		try {
			$eventSvc->SetContext($currentUserId, $newParams);
			$activeCtx = $eventSvc->GetContext($currentUserId);
		} catch (\Throwable $e) {}
	}

	$branchId = $activeCtx['branch_id'] ?? null;
	$productId = $activeCtx['product_id'] ?? null;
	$caseId = $activeCtx['case_id'] ?? null;
	$deviceId = $activeCtx['device_id'] ?? null;

	$links = $eventSvc->GetCrossLinks($branchId, $productId, $caseId);

	// Determinar ruta activa
	$currentUri = $_SERVER['REQUEST_URI'] ?? '';
	$currentPath = parse_url($currentUri, PHP_URL_PATH) ?? '';
@endphp

<div class="daily-unified-context-strip" data-testid="unified-context-bar">
	<div class="context-pill-header">
		<i class="fa-solid fa-arrows-split-up-and-left text-success"></i>
		<span class="context-brand">DAILY 360°</span>
		<span class="context-label">Contexto Operativo Activo:</span>
	</div>

	<div class="context-items-row">
		@if(!empty($activeCtx['branch_name']))
			<div class="context-tag tag-branch" title="Sucursal de trabajo">
				<i class="fa-solid fa-store"></i>
				<span class="tag-title">Sucursal:</span>
				<strong>{{ $activeCtx['branch_name'] }}</strong>
			</div>
		@endif

		@if(!empty($activeCtx['product_name']))
			<div class="context-tag tag-product" title="Producto seleccionado">
				<i class="fa-solid fa-box"></i>
				<span class="tag-title">SKU:</span>
				<strong>{{ $activeCtx['product_sku'] ?? $activeCtx['product_id'] }}</strong> — {{ $activeCtx['product_name'] }}
			</div>
		@endif

		@if(!empty($activeCtx['device_imei']))
			<div class="context-tag tag-device {{ ($activeCtx['device_state'] ?? '') === 'not_located' ? 'tag-danger' : '' }}" title="Dispositivo serializado">
				<i class="fa-solid fa-mobile-screen-button"></i>
				<span class="tag-title">IMEI:</span>
				<strong>{{ $activeCtx['device_imei'] }}</strong>
				@if(($activeCtx['device_state'] ?? '') === 'not_located')
					<span class="badge-status-danger">No Localizado</span>
				@endif
			</div>
		@endif

		@if(!empty($activeCtx['case_code']))
			<div class="context-tag tag-case" title="Expediente de investigación">
				<i class="fa-solid fa-folder-open"></i>
				<span class="tag-title">Expediente:</span>
				<strong>{{ $activeCtx['case_code'] }}</strong>
				@if(!empty($activeCtx['case_value']))
					<span class="tag-value">${{ number_format($activeCtx['case_value'], 2, '.', ',') }}</span>
				@endif
			</div>
		@endif

		@if(empty($activeCtx['branch_name']) && empty($activeCtx['product_name']) && empty($activeCtx['case_code']))
			<div class="context-tag tag-empty">
				<i class="fa-solid fa-circle-info"></i>
				<span>Red global completa · Seleccione un expediente, sucursal o SKU para sincronizar módulos</span>
			</div>
		@endif
	</div>

	<div class="context-nav-modules">
		<span class="context-nav-label">Navegar 7 Módulos:</span>
		<div class="context-nav-chips">
			<a href="{{ $links['reconciliation'] }}" class="context-chip {{ strpos($currentPath, '/reconciliation') !== false ? 'active' : '' }}" title="Conciliación y Auditoría Sensible">
				<i class="fa-solid fa-scale-balanced"></i> Conciliación
			</a>
			<a href="{{ $links['merchandise'] }}" class="context-chip {{ strpos($currentPath, '/merchandise') !== false ? 'active' : '' }}" title="Control de Mercancías y Entradas/Salidas">
				<i class="fa-solid fa-truck-ramp-box"></i> Mercancías
			</a>
			<a href="{{ $links['loss_prevention'] }}" class="context-chip {{ (strpos($currentPath, '/loss-prevention') !== false && strpos($currentPath, '/devices') === false) ? 'active' : '' }}" title="Centro de Control Prevención de Pérdidas 360°">
				<i class="fa-solid fa-shield-halved"></i> Prevención
			</a>
			<a href="{{ $links['devices'] }}" class="context-chip {{ strpos($currentPath, '/loss-prevention/devices') !== false ? 'active' : '' }}" title="Custodia y Trazabilidad de Celulares por IMEI">
				<i class="fa-solid fa-mobile-screen"></i> Celulares
			</a>
			<a href="{{ $links['inventory_control'] }}" class="context-chip {{ strpos($currentPath, '/inventory-control') !== false ? 'active' : '' }}" title="Control de Inventario y Mermas">
				<i class="fa-solid fa-cubes-stacked"></i> Control Inventario
			</a>
			<a href="{{ $links['inventory'] }}" class="context-chip {{ strpos($currentPath, '/inventory') !== false && strpos($currentPath, '/inventory-control') === false ? 'active' : '' }}" title="Conteos Físicos de Inventario">
				<i class="fa-solid fa-clipboard-check"></i> Conteos Físicos
			</a>
			<a href="{{ $links['branches'] }}" class="context-chip {{ strpos($currentPath, '/branches') !== false ? 'active' : '' }}" title="Sucursales y Almacenes de la Cadena">
				<i class="fa-solid fa-building-circle-check"></i> Sucursales
			</a>
		</div>
	</div>
</div>

<style>
.daily-unified-context-strip {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 16px;
	margin: 0 0 16px 0;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px;
	font-family: inherit;
	font-size: 0.85rem;
}
.context-pill-header {
	display: flex;
	align-items: center;
	gap: 6px;
	padding-right: 10px;
	border-right: 1px solid #e2e8f0;
}
.context-brand {
	font-weight: 800;
	color: #008E48;
	font-size: 0.82rem;
	letter-spacing: 0.5px;
}
.context-label {
	color: #64748b;
	font-size: 0.78rem;
}
.context-items-row {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
	flex: 1;
}
.context-tag {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	padding: 4px 10px;
	border-radius: 6px;
	color: #1e293b;
	font-size: 0.8rem;
}
.context-tag i {
	color: #008E48;
	font-size: 0.82rem;
}
.context-tag .tag-title {
	color: #64748b;
	font-weight: 500;
}
.context-tag.tag-danger {
	background: #fef2f2;
	border-color: #fecaca;
	color: #b91c1c;
}
.context-tag.tag-danger i {
	color: #dc2626;
}
.badge-status-danger {
	background: #dc2626;
	color: #ffffff;
	font-size: 0.68rem;
	padding: 1px 6px;
	border-radius: 4px;
	font-weight: 700;
	margin-left: 4px;
}
.context-tag .tag-value {
	color: #dc2626;
	font-weight: 700;
	margin-left: 4px;
}
.context-tag.tag-empty {
	color: #64748b;
	background: #f8fafc;
}
.context-tag.tag-empty i {
	color: #94a3b8;
}
.context-nav-modules {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-left: auto;
}
.context-nav-label {
	color: #64748b;
	font-size: 0.75rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.4px;
}
.context-nav-chips {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.context-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: #ffffff;
	border: 1px solid #cbd5e1;
	color: #334155;
	padding: 3px 9px;
	border-radius: 6px;
	font-size: 0.78rem;
	font-weight: 600;
	text-decoration: none;
	transition: all 0.15s ease-in-out;
}
.context-chip:hover {
	background: #f1f5f9;
	color: #008E48;
	border-color: #008E48;
	text-decoration: none;
}
.context-chip.active {
	background: #eafaf1;
	color: #008E48;
	border-color: #008E48;
	box-shadow: 0 0 0 1px #008E48;
}
.context-chip i {
	font-size: 0.75rem;
}
@media (max-width: 991px) {
	.daily-unified-context-strip {
		flex-direction: column;
		align-items: flex-start;
	}
	.context-pill-header {
		border-right: none;
		border-bottom: 1px solid #e2e8f0;
		padding-bottom: 6px;
		width: 100%;
	}
	.context-nav-modules {
		margin-left: 0;
		width: 100%;
		flex-direction: column;
		align-items: flex-start;
		border-top: 1px solid #e2e8f0;
		padding-top: 8px;
	}
}
</style>

