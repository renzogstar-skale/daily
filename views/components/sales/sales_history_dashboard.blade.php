{{-- ==========================================================================
     DAILY — HISTORIAL DE VENTAS: encabezado y pestañas.
     Análisis temporal y auditoría comercial en components/sales/sales_reports (section = history), con datos reales de
     SalesReportsService y SalesHistoryAuditService. Kardex, conteos, mermas, asistencia, expedientes y bodega viven en sus tableros.
     ========================================================================== --}}
<div class="sales-hist-wrapper">
	<!-- ====================================================================
	     ENCABEZADO COMERCIAL — EXACTAMENTE EQUILIBRADO CON LOS DEMÁS MÓDULOS
	     ==================================================================== -->
	<header class="sales-hist-header">
		<div class="sales-hist-header-left">
			<h1 class="sales-hist-title">Historial de Ventas</h1>
			<p class="sales-hist-subtitle">Análisis temporal y auditoría comercial: evolución diaria, semanal, mensual y anual, cierres y correcciones autorizadas.</p>
		</div>
	</header>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS INTEGRADA DE DAILY (NAVEGACIÓN DIRECTA ENTRE MÓDULOS)
	     ==================================================================== -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Ventas y Rentabilidad">
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen-hist">
			<i class="fa-solid fa-chart-column mr-1"></i>
			<span>Tablero de Ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos-hist">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>
			<span>Ventas por departamento</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos-hist">
			<i class="fa-solid fa-barcode mr-1"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales-hist">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab is-active" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle-hist" aria-selected="true">
			<i class="fa-solid fa-list-ul mr-1"></i>
			<span>Historial de ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad-hist">
			<i class="fa-solid fa-coins mr-1"></i>
			<span>Rentabilidad</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-hist">
			<i class="fa-solid fa-tags mr-1"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas-hist">
			<i class="fa-solid fa-bullhorn mr-1"></i>
			<span>Campañas</span>
		</button>
	</nav>
</div>
