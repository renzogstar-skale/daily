{{-- ==========================================================================
     DAILY — STOCK & MOVIMIENTOS · ENCABEZADO, FILTROS CHIPS Y PESTAÑAS
     Réplica exacta de «Stock y Movimientos · Daily.html»
     ========================================================================== --}}
@php
	$sf = $sharedFilters;
	$fo = $filterOptions;
	$fd = \Grocy\Helpers\DailyFilters::Describe($sf, $fo);
	$smvMonths = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$smvRangeLabel = date('d', strtotime($sf['start_date'])) . ' ' . $smvMonths[intval(date('n', strtotime($sf['start_date'])))]
		. ' – ' . date('d', strtotime($sf['end_date'])) . ' ' . $smvMonths[intval(date('n', strtotime($sf['end_date'])))] . ' ' . date('Y', strtotime($sf['end_date']));
	$smvUser = defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : 'usuario';
	$smvTab = $smvTab ?? 'resumen';
	$smvFiltered = $sf['branch_id'] !== null || $sf['department'] !== null || $sf['product'] !== null || $sf['group_id'] !== null || $sf['subcategory'] !== null
		|| $sf['end_date'] !== date('Y-m-d') || $sf['start_date'] !== date('Y-m-d', strtotime('-29 days'));
@endphp

<div class="smv-top-wrapper">
	<div class="top smv-top-bar">
		<div>
			<h1 class="smv-main-title">Stock y Movimientos</h1>
			<div class="mut smv-main-subtitle">Resumen ejecutivo del inventario, rotación y flujo de mercadería</div>
		</div>

		<div class="chips smv-chips-bar">
			<!-- Chip 1: Rango de Fechas -->
			<div class="dropdown d-inline-block">
				<button type="button" class="chip smv-chip-btn dropdown-toggle" id="btnSmvDates" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Filtrar por período comercial">
					<i class="fa-regular fa-calendar mr-1 text-muted"></i>
					<span>{{ $smvRangeLabel }}</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right p-3 shadow-lg border-0 smv-dropdown-box" aria-labelledby="btnSmvDates" style="min-width: 250px; border-radius: 14px;">
					<div class="dropdown-header text-uppercase font-weight-bold px-0 pt-0 pb-2" style="font-size: 11px; color: #64748b;">Período Comercial</div>
					<a href="#" class="dropdown-item py-2 px-2 rounded" data-select-range="30d">Últimos 30 días</a>
					<a href="#" class="dropdown-item py-2 px-2 rounded" data-select-range="today">Hoy</a>
					<a href="#" class="dropdown-item py-2 px-2 rounded" data-select-range="yesterday">Ayer</a>
					<a href="#" class="dropdown-item py-2 px-2 rounded" data-select-range="week">Semana actual</a>
					<a href="#" class="dropdown-item py-2 px-2 rounded" data-select-range="month">Mes en curso</a>
					<a href="#" class="dropdown-item py-2 px-2 rounded" data-select-range="prev_month">Mes anterior</a>
					<div class="dropdown-divider my-2"></div>
					<form method="GET" action="{{ $U('/stockoverview') }}" class="d-flex flex-column gap-2 mt-2">
						@foreach(['branch_id', 'department', 'group_id', 'subcategory', 'product'] as $k)
							@if($sf[$k] !== null)<input type="hidden" name="{{ $k }}" value="{{ $sf[$k] }}">@endif
						@endforeach
						<div class="d-flex align-items-center gap-2 mb-2">
							<input type="date" name="start_date" value="{{ $sf['start_date'] }}" class="form-control form-control-sm" style="font-size: 11.5px;">
							<span style="font-size: 11px; color:#64748b;">–</span>
							<input type="date" name="end_date" value="{{ $sf['end_date'] }}" class="form-control form-control-sm" style="font-size: 11.5px;">
						</div>
						<button type="submit" class="btn btn-sm btn-primary w-100" style="background:#10b26c; border-color:#10b26c; font-size:12px; font-weight:600; border-radius: 8px;">Aplicar fechas</button>
					</form>
				</div>
			</div>

			<!-- Chip 2: Sucursales -->
			<div class="dropdown d-inline-block">
				<button type="button" class="chip smv-chip-btn dropdown-toggle" id="btnSmvBranches" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Filtrar por sucursal">
					<i class="fa-solid fa-store mr-1 text-muted"></i>
					<span>{{ $fd['branch'] }}</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right p-2 shadow-lg border-0 smv-dropdown-box" aria-labelledby="btnSmvBranches" style="min-width: 220px; border-radius: 14px;">
					<div class="dropdown-header text-uppercase font-weight-bold px-2 pt-1 pb-2" style="font-size: 11px; color: #64748b;">Centro Operativo</div>
					<a href="#" class="dropdown-item py-2 px-2 rounded @if($sf['branch_id'] === null) active font-weight-bold @endif" data-select-branch="">
						Todas las sucursales
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($fo['branches'] as $b)
					<a href="#" class="dropdown-item py-2 px-2 rounded @if($sf['branch_id'] == $b['id']) active font-weight-bold @endif" data-select-branch="{{ $b['id'] }}">
						{{ $b['name'] }}
					</a>
					@endforeach
				</div>
			</div>

			<!-- Chip 3: Departamentos -->
			<div class="dropdown d-inline-block">
				<button type="button" class="chip smv-chip-btn dropdown-toggle" id="btnSmvDepartments" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Filtrar por departamento">
					<i class="fa-solid fa-tags mr-1 text-muted"></i>
					<span>{{ mb_strimwidth($fd['department'], 0, 24, '…') }}</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right p-2 shadow-lg border-0 smv-dropdown-box" aria-labelledby="btnSmvDepartments" style="min-width: 220px; border-radius: 14px;">
					<div class="dropdown-header text-uppercase font-weight-bold px-2 pt-1 pb-2" style="font-size: 11px; color: #64748b;">Departamento Comercial</div>
					<a href="#" class="dropdown-item py-2 px-2 rounded @if($sf['department'] === null) active font-weight-bold @endif" data-select-dept="">
						Todos los departamentos
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($fo['departments'] as $d)
					<a href="#" class="dropdown-item py-2 px-2 rounded @if($sf['department'] === $d['key']) active font-weight-bold @endif" data-select-dept="{{ $d['key'] }}">
						{{ $d['name'] }}
					</a>
					@endforeach
				</div>
			</div>

			<!-- Notificaciones -->
			<a href="{{ $U('/alerts') }}" class="chip smv-icon-chip" title="Centro de Alertas" aria-label="Notificaciones">
				<i class="fa-regular fa-bell"></i>
				<span class="smv-chip-dot"></span>
			</a>

			<!-- Avatar usuario -->
			<div class="dropdown d-inline-block">
				<button type="button" class="chip smv-avatar-chip dropdown-toggle" id="btnSmvUser" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Perfil ({{ $smvUser }})">
					{{ mb_strtoupper(mb_substr($smvUser, 0, 2)) }}
				</button>
				<div class="dropdown-menu dropdown-menu-right p-2 shadow-lg border-0 smv-dropdown-box" aria-labelledby="btnSmvUser" style="min-width: 180px; border-radius: 14px;">
					<div class="px-2 py-2 border-bottom font-weight-bold text-dark font-13">{{ $smvUser }}</div>
					<a href="{{ $U('/usersettings') }}" class="dropdown-item py-2 px-2 rounded font-12"><i class="fa-solid fa-gear mr-2 text-muted"></i>Configuración</a>
					<a href="{{ $U('/alerts') }}" class="dropdown-item py-2 px-2 rounded font-12"><i class="fa-solid fa-bell mr-2 text-muted"></i>Alertas</a>
					<div class="dropdown-divider my-1"></div>
					<a href="{{ $U('/logout') }}" class="dropdown-item py-2 px-2 rounded font-12 text-danger"><i class="fa-solid fa-arrow-right-from-bracket mr-2"></i>Cerrar sesión</a>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     LAS 5 PESTAÑAS (ORDEN OBLIGATORIO)
	     1. Tablero de Stock | 2. Movimientos | 3. Lotes y vencimientos | 4. Almacenes | 5. Conteos y ajustes
	     ====================================================================== -->
	<div class="tabs smv-main-tabs" role="tablist" aria-label="Stock y Movimientos">
		<button type="button" class="smv-top-tab @if($smvTab === 'resumen') on @endif" data-smv-tab="resumen" role="tab" id="smv-tab-resumen">
			Tablero de Stock
		</button>
		<button type="button" class="smv-top-tab @if($smvTab === 'movimientos' || ($smvTab === 'detalle' && ($smvDetail ?? '') === 'movements')) on @endif" data-smv-tab="movimientos" role="tab" id="smv-tab-movimientos">
			Movimientos
		</button>
		<button type="button" class="smv-top-tab @if($smvTab === 'lotes' || $smvTab === 'existencias') on @endif" data-smv-tab="lotes" role="tab" id="smv-tab-lotes">
			Lotes y vencimientos
		</button>
		<button type="button" class="smv-top-tab @if($smvTab === 'almacenes' || $smvTab === 'operaciones') on @endif" data-smv-tab="almacenes" role="tab" id="smv-tab-almacenes">
			Almacenes
		</button>
		<button type="button" class="smv-top-tab @if($smvTab === 'conteos' || ($smvTab === 'detalle' && ($smvDetail ?? '') === 'counts')) on @endif" data-smv-tab="conteos" role="tab" id="smv-tab-conteos">
			Conteos y ajustes
		</button>
	</div>
</div>
