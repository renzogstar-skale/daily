@extends('layout.default')

@section('title', 'Mapa del Sistema')

@push('pageStyles')
<link href="{{ $U('/css/dairy_system_map.css?v=', true) }}{{ $version }}" rel="stylesheet">
@endpush

@section('content')
<div class="row align-items-center mb-3 dairy-page-header d-print-none">
	<div class="col-lg-6 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-sitemap"></i></div>
		<div>
			<h4 class="dairy-page-title">DAILY — Mapa del Sistema</h4>
			<p class="dairy-page-subtitle">Todo lo construido, cómo se conecta, qué está verificado y qué falta. Generado desde el código y la evidencia de pruebas, no a mano.</p>
		</div>
	</div>
	<div class="col-lg-6 dairy-page-actions">
		<button type="button" id="sm-refresh" class="btn btn-primary" title="Re-analiza rutas, vistas, APIs, permisos y conexiones (solo lectura del repositorio)">
			<i class="fa-solid fa-rotate"></i> Actualizar análisis
		</button>
		<div class="dropdown d-inline-block">
			<button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-download"></i> Exportar</button>
			<div class="dropdown-menu dropdown-menu-right">
				<h6 class="dropdown-header">Vista actual</h6>
				<button type="button" class="dropdown-item sm-export" data-format="png"><i class="fa-solid fa-image mr-2"></i>PNG</button>
				<button type="button" class="dropdown-item sm-export" data-format="svg"><i class="fa-solid fa-bezier-curve mr-2"></i>SVG</button>
				<button type="button" class="dropdown-item sm-export" data-format="pdf"><i class="fa-solid fa-file-pdf mr-2"></i>PDF (imprimir)</button>
				<div class="dropdown-divider"></div>
				<h6 class="dropdown-header">Datos estructurados</h6>
				<a class="dropdown-item" href="{{ $U('/api/system-map/export/json') }}"><i class="fa-solid fa-code mr-2"></i>JSON completo</a>
				<a class="dropdown-item" href="{{ $U('/api/system-map/export/mermaid-mindmap') }}"><i class="fa-solid fa-diagram-project mr-2"></i>Mermaid — mapa mental</a>
				<a class="dropdown-item" href="{{ $U('/api/system-map/export/mermaid-flowchart') }}"><i class="fa-solid fa-share-nodes mr-2"></i>Mermaid — conexiones</a>
				<a class="dropdown-item" href="{{ $U('/api/system-map/export/markdown') }}"><i class="fa-solid fa-file-lines mr-2"></i>Informe de auditoría (.md)</a>
			</div>
		</div>
	</div>
</div>

<div id="sm-meta" class="sm-meta d-print-none"></div>
<div id="sm-kpis" class="row dairy-kpi-row d-print-none"></div>

<div class="card sm-card">
	<div class="card-header sm-toolbar d-print-none">
		<ul class="nav dairy-subnav-pills sm-views" role="tablist">
			<li class="nav-item"><a class="nav-link active" href="#" data-view="mind" role="tab"><i class="fa-solid fa-brain mr-1"></i>Mapa Mental</a></li>
			<li class="nav-item"><a class="nav-link" href="#" data-view="conn" role="tab"><i class="fa-solid fa-share-nodes mr-1"></i>Mapa de Conexiones</a></li>
			<li class="nav-item"><a class="nav-link" href="#" data-view="audit" role="tab"><i class="fa-solid fa-clipboard-check mr-1"></i>Mapa de Auditoría</a></li>
		</ul>
		<div class="sm-controls">
			<div class="sm-search">
				<i class="fa-solid fa-magnifying-glass"></i>
				<input type="search" id="sm-search" class="form-control form-control-sm" placeholder="Buscar módulo, ruta, API, tabla…" aria-label="Buscar en el mapa" autocomplete="off">
			</div>
			<div class="btn-group btn-group-sm sm-mode" role="group" aria-label="Nivel de detalle">
				<button type="button" class="btn btn-outline-secondary active" data-mode="general">General</button>
				<button type="button" class="btn btn-outline-secondary" data-mode="tech">Técnica</button>
			</div>
			<div class="dropdown">
				<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-filter"></i> Filtros <span id="sm-filter-count" class="badge badge-primary d-none"></span></button>
				<div class="dropdown-menu dropdown-menu-right sm-filter-menu" id="sm-filters"></div>
			</div>
		</div>
	</div>
	<div class="sm-legend" id="sm-legend"></div>
	<div class="sm-body">
		<div class="sm-stage" id="sm-stage">
			<div class="sm-loading" id="sm-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando análisis…</div>
			<svg id="sm-svg" class="sm-svg" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mapa del sistema DAILY"></svg>
			<div id="sm-audit" class="sm-audit d-none"></div>
			<div class="sm-zoom d-print-none" id="sm-zoom">
				<button type="button" class="btn btn-light btn-sm" data-zoom="in" aria-label="Acercar"><i class="fa-solid fa-plus"></i></button>
				<button type="button" class="btn btn-light btn-sm" data-zoom="out" aria-label="Alejar"><i class="fa-solid fa-minus"></i></button>
				<button type="button" class="btn btn-light btn-sm" data-zoom="fit" aria-label="Ajustar a la pantalla"><i class="fa-solid fa-expand"></i></button>
				<button type="button" class="btn btn-light btn-sm" data-zoom="expand" title="Expandir módulos visibles"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
				<button type="button" class="btn btn-light btn-sm" data-zoom="collapse" title="Contraer a categorías"><i class="fa-solid fa-down-left-and-up-right-to-center"></i></button>
			</div>
		</div>
		<aside class="sm-panel" id="sm-panel" aria-live="polite">
			<div class="sm-panel-empty">
				<i class="fa-solid fa-hand-pointer"></i>
				<p>Seleccione un nodo para ver ruta, funciones, endpoints, tablas, permisos, pruebas, conexiones y problemas.</p>
			</div>
		</aside>
	</div>
</div>

<script>
	window.SystemMapConfig = { root: {!! json_encode($repoRoot) !!} };
</script>
@stop
