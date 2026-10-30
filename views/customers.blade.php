@extends('layout.default')

@section('title', 'Directorio de Clientes')

@push('pageStyles')
<link href="{{ $U('/css/daily_customers.css?v=', true) }}{{ $version }}" rel="stylesheet">
@endpush

@php
	$customersList = is_array($customers ?? null) ? $customers : [];
	$recentOrdersList = is_array($recentOrdersAll ?? null) ? $recentOrdersAll : [];
	$branchesList = $branches ?? [];

	$totalCount = count($customersList);
	$activeCount = 0;
	$openOrdersCount = 0;
	$pendingDataCount = 0;

	foreach ($customersList as $c)
	{
		$cArr = (array)$c;
		if ((int)($cArr['active'] ?? 1) === 1)
		{
			$activeCount++;
		}
		if (!empty($cArr['has_open_orders']) || (int)($cArr['open_orders_count'] ?? 0) > 0)
		{
			$openOrdersCount++;
		}
		if (!empty($cArr['has_pending_data']) || !empty($cArr['has_incomplete_data']))
		{
			$pendingDataCount++;
		}
	}

	$activePct = $totalCount > 0 ? round(($activeCount / $totalCount) * 100) : 0;
	$openOrdersPct = $totalCount > 0 ? round(($openOrdersCount / $totalCount) * 100) : 0;
	$pendingDataPct = $totalCount > 0 ? round(($pendingDataCount / $totalCount) * 100) : 0;

	$initialSelectedId = (int)($selectedCustomerId ?? 0);
	if ($initialSelectedId <= 0 && !empty($customersList))
	{
		$firstCust = (array)$customersList[0];
		$initialSelectedId = (int)($firstCust['id'] ?? 1);
	}
@endphp

@section('content')
<div class="customers-page-wrapper" id="customers-app">
	<!-- CABECERA PRINCIPAL -->
	<div class="customers-top-header">
		<div class="customers-title-group">
			<div class="customers-title-icon">
				<i class="fa-solid fa-users"></i>
			</div>
			<div class="customers-title-text">
				<h1>Directorio de Clientes</h1>
				<p>Catálogo de clientes para emisión de comprobantes, control de crédito y análisis comercial.</p>
			</div>
		</div>

		<div class="customers-header-actions">
			<button type="button" class="customers-btn-white" id="btn-new-customer">
				<i class="fa-solid fa-circle-plus text-success"></i>
				<span>Nuevo cliente</span>
			</button>

			<div class="dropdown d-inline-block">
				<button type="button" class="customers-btn-white dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa-solid fa-download text-success"></i>
					<span>Exportar</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.82rem;">
					<a class="dropdown-item py-2" href="#" id="btn-export-customers-csv">
						<i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar vista filtrada (CSV)
					</a>
					<a class="dropdown-item py-2" href="#" id="btn-export-customers-tsv">
						<i class="fa-solid fa-file-excel text-primary mr-2"></i>Exportar para Excel (TSV)
					</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item py-2" href="#" id="btn-export-customers-print">
						<i class="fa-solid fa-print text-secondary mr-2"></i>Imprimir directorio / PDF
					</a>
				</div>
			</div>

			<a href="{{ $U('/pos') }}" class="customers-btn-pos" id="btn-header-goto-pos">
				<i class="fa-solid fa-store"></i>
				<span>Ir a Punto de Venta</span>
			</a>
		</div>
	</div>

	<!-- 4 TARJETAS DE RESUMEN DEL DIRECTORIO -->
	<div class="customers-kpi-grid">
		<div class="customers-kpi-card is-active-kpi" data-kpi-filter="all" title="Mostrar todos los clientes del directorio">
			<div class="customers-kpi-icon tone-blue">
				<i class="fa-solid fa-users"></i>
			</div>
			<div>
				<div class="customers-kpi-value" id="kpi-total-count">{{ $totalCount }}</div>
				<div class="customers-kpi-label">Clientes totales</div>
				<div class="customers-kpi-sub" id="kpi-total-sub">En el directorio</div>
			</div>
		</div>

		<div class="customers-kpi-card" data-kpi-filter="active" title="Filtrar clientes activos">
			<div class="customers-kpi-icon tone-green">
				<i class="fa-solid fa-circle-check"></i>
			</div>
			<div>
				<div class="customers-kpi-value" id="kpi-active-count">{{ $activeCount }}</div>
				<div class="customers-kpi-label">Clientes activos</div>
				<div class="customers-kpi-sub" id="kpi-active-sub">{{ $activePct }}% del total</div>
			</div>
		</div>

		<div class="customers-kpi-card" data-kpi-filter="open_orders" title="Filtrar clientes con pedidos abiertos sin despachar">
			<div class="customers-kpi-icon tone-amber">
				<i class="fa-solid fa-cart-shopping"></i>
			</div>
			<div>
				<div class="customers-kpi-value" id="kpi-open-orders-count">{{ $openOrdersCount }}</div>
				<div class="customers-kpi-label">Con pedidos abiertos</div>
				<div class="customers-kpi-sub" id="kpi-open-orders-sub">{{ $openOrdersPct }}% del total</div>
			</div>
		</div>

		<div class="customers-kpi-card" data-kpi-filter="pending_data" title="Filtrar clientes con datos fiscales o de contacto pendientes">
			<div class="customers-kpi-icon tone-red">
				<i class="fa-solid fa-file-circle-exclamation"></i>
			</div>
			<div>
				<div class="customers-kpi-value" id="kpi-pending-data-count">{{ $pendingDataCount }}</div>
				<div class="customers-kpi-label">Con datos pendientes</div>
				<div class="customers-kpi-sub" id="kpi-pending-data-sub">{{ $pendingDataPct }}% del total</div>
			</div>
		</div>
	</div>

	<!-- BARRA DE BÚSQUEDA Y FILTROS -->
	<div class="customers-filter-bar">
		<div class="customers-search-wrap">
			<i class="fa-solid fa-magnifying-glass"></i>
			<input type="text" id="cust-filter-search" class="customers-search-input" placeholder="Buscar por nombre, identificación, correo o teléfono...">
		</div>

		<div class="customers-filter-group">
			<label for="cust-filter-status">Estado</label>
			<select id="cust-filter-status">
				<option value="all">Todos</option>
				<option value="active">Activo</option>
				<option value="inactive">Inactivo</option>
				<option value="open_orders">Con pedidos abiertos</option>
				<option value="pending_data">Con datos pendientes</option>
			</select>
		</div>

		<div class="customers-filter-group">
			<label for="cust-filter-type">Tipo de cliente</label>
			<select id="cust-filter-type">
				<option value="all">Todos</option>
				<option value="Comercial">Comercial</option>
				<option value="Institucional">Institucional</option>
				<option value="Minorista">Minorista</option>
				<option value="Corporativo">Corporativo</option>
			</select>
		</div>

		<div class="customers-filter-group">
			<label for="cust-filter-branch">Sucursal</label>
			<select id="cust-filter-branch">
				<option value="all">Todas</option>
				@foreach($branchesList as $br)
					<option value="{{ $br->id }}">{{ $br->name }}</option>
				@endforeach
			</select>
		</div>

		<div class="customers-filter-group">
			<label for="cust-filter-sort">Ordenar por</label>
			<select id="cust-filter-sort">
				<option value="code_asc">Código (# ID)</option>
				<option value="name_asc">Nombre (A-Z)</option>
				<option value="last_purchase_desc">Última compra (reciente)</option>
				<option value="total_amount_desc">Mayor monto comprado</option>
				<option value="created_desc">Fecha de registro</option>
			</select>
		</div>

		<button type="button" class="customers-btn-clear" id="btn-clear-customer-filters">
			<i class="fa-solid fa-filter-circle-xmark"></i>
			<span>Limpiar filtros</span>
		</button>
	</div>

	<!-- SECCIÓN PRINCIPAL: LISTA DE CLIENTES (IZQUIERDA) + FICHA DEL CLIENTE (DERECHA) -->
	<div class="customers-main-grid">
		<!-- Columna Izquierda: Lista de Clientes -->
		<div class="customers-card">
			<div class="customers-table-wrap">
				<table class="customers-table" id="customers-directory-table">
					<thead>
						<tr>
							<th style="width:68px;"># ID</th>
							<th>CLIENTE</th>
							<th>IDENTIFICACIÓN</th>
							<th>TIPO</th>
							<th>ESTADO</th>
							<th class="text-right">ÚLTIMA COMPRA</th>
							<th style="width:28px;"></th>
						</tr>
					</thead>
					<tbody id="customers-tbody">
						<!-- Renderizado dinámico por JS con respaldo inicial -->
					</tbody>
				</table>
				<div id="customers-empty-state" class="text-center py-5 d-none">
					<div class="mb-2 text-muted"><i class="fa-solid fa-users-slash fa-2x"></i></div>
					<div class="font-weight-bold text-dark">No se encontraron clientes con los filtros aplicados</div>
					<div class="text-muted small mt-1">Prueba ajustando el texto de búsqueda o limpia los filtros activos.</div>
					<button type="button" class="btn btn-sm btn-outline-success mt-3" id="btn-empty-reset-filters">
						<i class="fa-solid fa-rotate-left mr-1"></i>Mostrar todos los clientes
					</button>
				</div>
			</div>
		</div>

		<!-- Columna Derecha: Ficha del Cliente Seleccionado -->
		<div class="customers-card" id="customer-detail-card">
			<div class="customers-profile-body" id="customer-profile-content">
				<!-- Encabezado de la ficha -->
				<div class="customers-profile-top">
					<div class="customers-profile-identity">
						<div class="customers-profile-avatar" id="det-cust-avatar">
							<i class="fa-solid fa-users"></i>
						</div>
						<div>
							<div class="d-flex align-items-center flex-wrap" style="gap:8px;">
								<h2 class="customers-profile-name" id="det-cust-name">Selecciona un cliente</h2>
								<span class="cust-status-badge is-active" id="det-cust-status-badge">● Activo</span>
							</div>
							<div class="mt-1 d-flex align-items-center flex-wrap" style="gap:6px;">
								<span class="cust-type-badge type-comercial" id="det-cust-type-badge">Comercial</span>
								<span class="text-muted small font-weight-bold" id="det-cust-code">#101</span>
							</div>
						</div>
					</div>

					<div class="dropdown">
						<button class="btn btn-sm btn-light border" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Más acciones del cliente" style="border-radius:8px; width:32px; height:32px;">
							<i class="fa-solid fa-ellipsis-vertical text-secondary"></i>
						</button>
						<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.81rem;">
							<a class="dropdown-item py-2" href="#" id="menu-edit-customer">
								<i class="fa-solid fa-pen-to-square text-primary mr-2"></i>Editar datos del cliente
							</a>
							<a class="dropdown-item py-2" href="#" id="menu-edit-notes">
								<i class="fa-solid fa-note-sticky text-warning mr-2"></i>Editar notas y etiquetas
							</a>
							<a class="dropdown-item py-2" href="#" id="menu-toggle-active">
								<i class="fa-solid fa-power-off text-secondary mr-2"></i><span id="menu-toggle-active-label">Cambiar a Inactivo</span>
							</a>
							<div class="dropdown-divider"></div>
							<a class="dropdown-item py-2" href="#" id="menu-new-order-customer">
								<i class="fa-solid fa-Clipboard-list text-success mr-2"></i>Ver / crear pedido en Pedidos
							</a>
							<a class="dropdown-item py-2" href="#" id="menu-new-pos-sale">
								<i class="fa-solid fa-cart-plus text-success mr-2"></i>Iniciar venta en POS
							</a>
						</div>
					</div>
				</div>

				<!-- Datos fiscales, contacto y clasificación -->
				<div class="customers-meta-grid">
					<div>
						<div class="customers-meta-row">
							<i class="fa-solid fa-id-card"></i>
							<span class="customers-meta-label">Identificación fiscal</span>
							<span class="customers-meta-val" id="det-cust-tax-id">—</span>
						</div>
						<div class="customers-meta-row">
							<i class="fa-solid fa-phone"></i>
							<span class="customers-meta-label">Teléfono</span>
							<span class="customers-meta-val" id="det-cust-phone">—</span>
						</div>
						<div class="customers-meta-row">
							<i class="fa-solid fa-envelope"></i>
							<span class="customers-meta-label">Correo electrónico</span>
							<span class="customers-meta-val" id="det-cust-email">—</span>
						</div>
						<div class="customers-meta-row">
							<i class="fa-solid fa-location-dot"></i>
							<span class="customers-meta-label">Dirección</span>
							<span class="customers-meta-val" id="det-cust-address">—</span>
						</div>
					</div>

					<div>
						<div class="customers-meta-row">
							<i class="fa-solid fa-store"></i>
							<span class="customers-meta-label">Sucursal principal</span>
							<span class="customers-meta-val" id="det-cust-branch">—</span>
						</div>
						<div class="customers-meta-row">
							<i class="fa-solid fa-tag"></i>
							<span class="customers-meta-label">Etiquetas</span>
							<span class="customers-meta-val" id="det-cust-tags">—</span>
						</div>
						<div class="customers-meta-row">
							<i class="fa-regular fa-calendar"></i>
							<span class="customers-meta-label">Cliente desde</span>
							<span class="customers-meta-val" id="det-cust-since">—</span>
						</div>
						<div class="customers-meta-row" id="det-cust-missing-row" style="display:none;">
							<i class="fa-solid fa-triangle-exclamation text-danger"></i>
							<span class="customers-meta-label text-danger font-weight-bold">Pendiente</span>
							<span class="customers-meta-val text-danger" id="det-cust-missing-text">—</span>
						</div>
					</div>
				</div>

				<!-- 4 Mini KPIs Comerciales del Cliente -->
				<div class="customers-mini-kpis">
					<div class="customers-mini-kpi">
						<div class="mk-icon" style="background:#eff6ff; color:#2563eb;">
							<i class="fa-solid fa-cart-shopping"></i>
						</div>
						<div>
							<div class="mk-label">Total de compras</div>
							<div class="mk-val" id="det-kpi-purchases">0</div>
							<div class="mk-sub" id="det-kpi-purchases-sub">Operaciones registradas</div>
						</div>
					</div>

					<div class="customers-mini-kpi">
						<div class="mk-icon" style="background:#dcfce7; color:#16a34a;">
							<i class="fa-solid fa-dollar-sign"></i>
						</div>
						<div>
							<div class="mk-label">Monto total</div>
							<div class="mk-val" id="det-kpi-amount">$0.00</div>
							<div class="mk-sub" id="det-kpi-amount-sub">Acumulado histórico</div>
						</div>
					</div>

					<div class="customers-mini-kpi">
						<div class="mk-icon" style="background:#eff6ff; color:#2563eb;">
							<i class="fa-regular fa-calendar-check"></i>
						</div>
						<div>
							<div class="mk-label">Última compra</div>
							<div class="mk-val" id="det-kpi-last-date">Sin información</div>
							<div class="mk-sub" id="det-kpi-last-sub">Sin compras previas</div>
						</div>
					</div>

					<div class="customers-mini-kpi">
						<div class="mk-icon" style="background:#eff6ff; color:#2563eb;">
							<i class="fa-regular fa-credit-card"></i>
						</div>
						<div>
							<div class="mk-label">Crédito disponible</div>
							<div class="mk-val" id="det-kpi-credit">Sin información</div>
							<div class="mk-sub" id="det-kpi-credit-sub">Sin línea configurada</div>
						</div>
					</div>
				</div>

				<!-- Barra de Notas Internas -->
				<div class="customers-notes-bar">
					<i class="fa-regular fa-file-lines text-secondary"></i>
					<strong class="text-secondary" style="font-size:0.74rem;">Notas</strong>
					<div class="notes-text text-truncate" id="det-cust-notes">Sin notas internas registradas.</div>
					<button type="button" class="customers-notes-edit-btn" id="btn-quick-edit-notes" title="Editar notas y etiquetas internas">
						<i class="fa-solid fa-pen" style="font-size:0.72rem;"></i>
					</button>
				</div>

				<!-- Acciones Principales del Cliente -->
				<div class="customers-profile-actions">
					<a href="{{ $U('/orders') }}" class="customers-act-btn" id="btn-det-view-orders">
						<i class="fa-solid fa-clipboard-list text-secondary"></i>
						<span>Ver pedidos</span>
					</a>
					<button type="button" class="customers-act-btn" id="btn-det-edit-customer">
						<i class="fa-solid fa-pen text-secondary"></i>
						<span>Editar cliente</span>
					</button>
					<a href="{{ $U('/pos') }}" class="customers-act-btn is-primary" id="btn-det-new-sale">
						<i class="fa-solid fa-cart-shopping"></i>
						<span>Nueva venta</span>
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- SECCIÓN INFERIOR COMPACTA: PEDIDOS RECIENTES (IZQUIERDA) + DATOS INCOMPLETOS (DERECHA) -->
	<div class="customers-bottom-grid">
		<!-- Pedidos recientes -->
		<div class="customers-bottom-card">
			<div class="customers-bottom-head">
				<h3 class="customers-bottom-title">
					<i class="fa-solid fa-cart-shopping text-success"></i>
					<span id="recent-orders-card-title">Pedidos y ventas recientes</span>
				</h3>
				<a href="{{ $U('/orders') }}" id="link-view-all-orders" class="small font-weight-bold text-primary" style="text-decoration:none;">Ver todos</a>
			</div>

			<div class="table-responsive">
				<table class="table table-sm table-borderless mb-0" style="font-size:0.79rem;">
					<thead>
						<tr class="text-muted border-bottom" style="font-size:0.67rem; text-transform:uppercase;">
							<th class="py-1 pl-1"># PEDIDO / FOLIO</th>
							<th class="py-1">CLIENTE</th>
							<th class="py-1">FECHA</th>
							<th class="py-1">ESTADO</th>
							<th class="py-1 text-right pr-1">MONTO</th>
							<th style="width:24px;"></th>
						</tr>
					</thead>
					<tbody id="cust-recent-orders-tbody">
						<!-- Renderizado dinámico por JS -->
					</tbody>
				</table>
			</div>
		</div>

		<!-- Datos incompletos -->
		<div class="customers-bottom-card">
			<div class="customers-bottom-head">
				<h3 class="customers-bottom-title">
					<i class="fa-solid fa-file-circle-exclamation text-danger"></i>
					<span>Datos incompletos</span>
				</h3>
				<a href="#" id="link-filter-incomplete" class="small font-weight-bold text-primary" style="text-decoration:none;">Ver todos</a>
			</div>

			<div id="cust-incomplete-list">
				<!-- Renderizado dinámico por JS -->
			</div>
		</div>
	</div>
</div>

<!-- MODAL: CREAR / EDITAR CLIENTE -->
<div class="modal fade" id="modal-customer-form" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header border-bottom py-3 px-4">
				<h5 class="modal-title font-weight-bold text-dark" id="modal-customer-title">
					<i class="fa-solid fa-user-plus text-success mr-2"></i>Nuevo cliente
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="form-customer">
				<div class="modal-body px-4 py-3">
					<input type="hidden" id="cust-form-id" value="">
					<input type="hidden" id="cust-form-confirm-duplicate" value="0">

					<!-- Alerta de posibles duplicados -->
					<div class="alert alert-warning d-none" id="cust-duplicate-warning" style="border-radius:10px; font-size:0.82rem;">
						<div class="font-weight-bold mb-1">
							<i class="fa-solid fa-triangle-exclamation mr-1"></i>Se detectaron posibles registros duplicados en el directorio:
						</div>
						<ul class="mb-2 pl-3" id="cust-duplicate-list"></ul>
						<div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
							<span class="small text-dark">Revisa si se trata del mismo cliente antes de crear un registro adicional.</span>
							<button type="button" class="btn btn-sm btn-warning font-weight-bold" id="btn-confirm-save-duplicate">
								Guardar de todos modos
							</button>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-7">
							<label class="small font-weight-bold text-secondary mb-1">Nombre o razón social <span class="text-danger">*</span></label>
							<input type="text" class="form-control form-control-sm" id="cust-form-name" required placeholder="Ej. Comercializadora del Norte S.A.">
						</div>
						<div class="form-group col-md-5">
							<label class="small font-weight-bold text-secondary mb-1">Identificación fiscal (RFC / NIF / RUC)</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-tax-id" placeholder="Ej. CFA-0101-9">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-secondary mb-1">Tipo de cliente</label>
							<select class="form-control form-control-sm" id="cust-form-type">
								<option value="Comercial">Comercial</option>
								<option value="Institucional">Institucional</option>
								<option value="Minorista">Minorista</option>
								<option value="Corporativo">Corporativo</option>
							</select>
						</div>
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-secondary mb-1">Sucursal principal</label>
							<select class="form-control form-control-sm" id="cust-form-branch">
								@foreach($branchesList as $br)
									<option value="{{ $br->id }}">{{ $br->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-secondary mb-1">Estado</label>
							<select class="form-control form-control-sm" id="cust-form-active">
								<option value="1">Activo</option>
								<option value="0">Inactivo</option>
							</select>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="small font-weight-bold text-secondary mb-1">Teléfono</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-phone" placeholder="Ej. 555-0101">
						</div>
						<div class="form-group col-md-6">
							<label class="small font-weight-bold text-secondary mb-1">Correo electrónico</label>
							<input type="email" class="form-control form-control-sm" id="cust-form-email" placeholder="contacto@empresa.com">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-8">
							<label class="small font-weight-bold text-secondary mb-1">Dirección fiscal / entrega</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-address" placeholder="Av. Principal 123, Zona Centro">
						</div>
						<div class="form-group col-md-4">
							<label class="small font-weight-bold text-secondary mb-1">Límite de crédito autorizado ($)</label>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm" id="cust-form-credit-limit" placeholder="Sin línea configurada">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-5 mb-2">
							<label class="small font-weight-bold text-secondary mb-1">Etiquetas (separadas por coma)</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-tags" placeholder="Mayorista, Crédito, Frecuente">
						</div>
						<div class="form-group col-md-7 mb-2">
							<label class="small font-weight-bold text-secondary mb-1">Notas internas</label>
							<input type="text" class="form-control form-control-sm" id="cust-form-notes" placeholder="Preferencias de entrega, horarios o condiciones comerciales...">
						</div>
					</div>
				</div>
				<div class="modal-footer border-top py-2 px-4">
					<button type="button" class="btn btn-sm btn-light border font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success font-weight-bold px-3" id="btn-save-customer-submit">
						<i class="fa-solid fa-check mr-1"></i>Guardar cliente
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL RÁPIDO: NOTAS Y ETIQUETAS INTERNAS -->
<div class="modal fade" id="modal-customer-notes" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header border-bottom py-3 px-4">
				<h6 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-note-sticky text-success mr-2"></i>Notas internas y etiquetas
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="form-customer-notes">
				<div class="modal-body px-4 py-3">
					<input type="hidden" id="notes-cust-id" value="">
					<div class="form-group">
						<label class="small font-weight-bold text-secondary mb-1">Cliente</label>
						<div class="font-weight-bold text-dark" id="notes-cust-name">—</div>
					</div>
					<div class="form-group">
						<label class="small font-weight-bold text-secondary mb-1">Etiquetas comerciales (separadas por coma)</label>
						<input type="text" class="form-control form-control-sm" id="notes-cust-tags" placeholder="Ej. Mayorista, Crédito, Horario matutino">
					</div>
					<div class="form-group mb-0">
						<label class="small font-weight-bold text-secondary mb-1">Notas operativas internas</label>
						<textarea class="form-control form-control-sm" id="notes-cust-text" rows="3" placeholder="Escribe observaciones internas sobre atención, entregas o facturación..."></textarea>
					</div>
				</div>
				<div class="modal-footer border-top py-2 px-4">
					<button type="button" class="btn btn-sm btn-light border font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success font-weight-bold px-3">
						<i class="fa-solid fa-floppy-disk mr-1"></i>Guardar notas
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
window.DAILY_CUSTOMERS_INITIAL = {
	customers: @json($customersList),
	recentOrdersAll: @json($recentOrdersList),
	selectedCustomerId: {{ (int)$initialSelectedId }},
	posUrl: "{{ $U('/pos') }}",
	ordersUrl: "{{ $U('/orders') }}",
	apiCustomersUrl: "{{ $U('/api/customers') }}"
};
</script>
@stop

@push('pageScripts')
<script>
(function() {
	const state = {
		customers: Array.isArray(window.DAILY_CUSTOMERS_INITIAL?.customers) ? window.DAILY_CUSTOMERS_INITIAL.customers : [],
		recentOrdersAll: Array.isArray(window.DAILY_CUSTOMERS_INITIAL?.recentOrdersAll) ? window.DAILY_CUSTOMERS_INITIAL.recentOrdersAll : [],
		selectedId: Number(window.DAILY_CUSTOMERS_INITIAL?.selectedCustomerId || 0),
		filters: {
			search: '',
			status: 'all',
			type: 'all',
			branch: 'all',
			sort: 'code_asc'
		}
	};

	const MONTHS_ES = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];

	function escapeHtml(value) {
		return String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatCurrency(num) {
		const n = Number(num || 0);
		return '$ ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function formatDateEs(dateStr) {
		if (!dateStr) return 'Sin información';
		const normalized = String(dateStr).trim().replace(' ', 'T');
		const d = new Date(normalized);
		if (isNaN(d.getTime())) {
			return String(dateStr).slice(0, 10);
		}
		const day = String(d.getDate()).padStart(2, '0');
		const month = MONTHS_ES[d.getMonth()] || '';
		const year = d.getFullYear();
		return `${day} ${month} ${year}`;
	}

	function formatRelativeDays(dateStr) {
		if (!dateStr) return 'Sin compras registradas';
		const normalized = String(dateStr).trim().replace(' ', 'T');
		const d = new Date(normalized);
		if (isNaN(d.getTime())) return 'Registro histórico';
		const diffMs = Date.now() - d.getTime();
		const days = Math.max(0, Math.floor(diffMs / 86400000));
		if (days === 0) return 'Hoy';
		if (days === 1) return 'Hace 1 día';
		return `Hace ${days} días`;
	}

	function getTypeBadgeClass(type) {
		const t = String(type || 'Comercial').toLowerCase();
		if (t.includes('institucional')) return 'type-institucional';
		if (t.includes('minorista')) return 'type-minorista';
		if (t.includes('corporativo')) return 'type-corporativo';
		return 'type-comercial';
	}

	function getFilteredCustomers() {
		const q = state.filters.search.trim().toLowerCase();
		const st = state.filters.status;
		const tp = state.filters.type;
		const br = state.filters.branch;
		const sort = state.filters.sort;

		const list = state.customers.filter(c => {
			if (q) {
				const haystack = [
					c.code,
					c.name,
					c.tax_id,
					c.phone,
					c.email,
					c.address,
					c.tags
				].map(v => String(v || '').toLowerCase()).join(' ');
				if (!haystack.includes(q)) return false;
			}

			if (st === 'active' && Number(c.active) !== 1) return false;
			if (st === 'inactive' && Number(c.active) !== 0) return false;
			if (st === 'open_orders' && !c.has_open_orders) return false;
			if (st === 'pending_data' && !c.has_pending_data) return false;

			if (tp !== 'all' && String(c.customer_type || 'Comercial') !== tp) return false;
			if (br !== 'all' && String(c.branch_id || '') !== String(br)) return false;

			return true;
		});

		list.sort((a, b) => {
			if (sort === 'name_asc') {
				return String(a.name || '').localeCompare(String(b.name || ''), 'es');
			}
			if (sort === 'last_purchase_desc') {
				const da = a.last_purchase_date ? new Date(String(a.last_purchase_date).replace(' ', 'T')).getTime() : 0;
				const db = b.last_purchase_date ? new Date(String(b.last_purchase_date).replace(' ', 'T')).getTime() : 0;
				return db - da;
			}
			if (sort === 'total_amount_desc') {
				return Number(b.total_amount || 0) - Number(a.total_amount || 0);
			}
			if (sort === 'created_desc') {
				const ca = a.row_created_timestamp ? new Date(String(a.row_created_timestamp).replace(' ', 'T')).getTime() : 0;
				const cb = b.row_created_timestamp ? new Date(String(b.row_created_timestamp).replace(' ', 'T')).getTime() : 0;
				return cb - ca;
			}
			return Number(a.id || 0) - Number(b.id || 0);
		});

		return list;
	}

	function renderKpiCards() {
		const total = state.customers.length;
		const active = state.customers.filter(c => Number(c.active) === 1).length;
		const openOrd = state.customers.filter(c => Boolean(c.has_open_orders)).length;
		const pending = state.customers.filter(c => Boolean(c.has_pending_data)).length;

		const pct = n => total > 0 ? Math.round((n / total) * 100) : 0;

		document.getElementById('kpi-total-count').textContent = total;
		document.getElementById('kpi-active-count').textContent = active;
		document.getElementById('kpi-active-sub').textContent = `${pct(active)}% del total`;
		document.getElementById('kpi-open-orders-count').textContent = openOrd;
		document.getElementById('kpi-open-orders-sub').textContent = `${pct(openOrd)}% del total`;
		document.getElementById('kpi-pending-data-count').textContent = pending;
		document.getElementById('kpi-pending-data-sub').textContent = `${pct(pending)}% del total`;

		document.querySelectorAll('.customers-kpi-card').forEach(card => {
			const f = card.getAttribute('data-kpi-filter');
			card.classList.toggle('is-active-kpi', f === state.filters.status);
		});
	}

	function renderTable() {
		const filtered = getFilteredCustomers();
		const tbody = document.getElementById('customers-tbody');
		const emptyEl = document.getElementById('customers-empty-state');

		if (filtered.length > 0 && !filtered.some(c => Number(c.id) === Number(state.selectedId))) {
			state.selectedId = Number(filtered[0].id);
		}

		if (filtered.length === 0) {
			tbody.innerHTML = '';
			emptyEl.classList.remove('d-none');
			renderSelectedCustomer();
			renderBottomSections();
			return;
		}

		emptyEl.classList.add('d-none');

		tbody.innerHTML = filtered.map(c => {
			const isSelected = Number(c.id) === Number(state.selectedId);
			const typeLabel = c.customer_type || 'Comercial';
			const typeClass = getTypeBadgeClass(typeLabel);
			const isActive = Number(c.active) === 1;
			const hasLastPurchase = Boolean(c.last_purchase_date);

			return `
				<tr class="customer-row ${isSelected ? 'is-selected' : ''}" data-customer-id="${c.id}">
					<td class="cust-id-cell">${escapeHtml(c.code || ('#' + (100 + Number(c.id))))}</td>
					<td>
						<div class="cust-name-main">${escapeHtml(c.name)}</div>
						<div class="cust-address-sub">${escapeHtml(c.address || 'Sin dirección registrada')}</div>
					</td>
					<td>
						<span class="cust-tax-pill">${escapeHtml(c.tax_id || 'Sin ID fiscal')}</span>
					</td>
					<td>
						<span class="cust-type-badge ${typeClass}">${escapeHtml(typeLabel)}</span>
					</td>
					<td>
						<span class="cust-status-badge ${isActive ? 'is-active' : 'is-inactive'}">
							● ${isActive ? 'Activo' : 'Inactivo'}
						</span>
					</td>
					<td class="text-right">
						${hasLastPurchase ? `
							<div class="font-weight-bold text-dark" style="font-size:0.79rem;">${escapeHtml(formatDateEs(c.last_purchase_date))}</div>
							<div class="text-muted" style="font-size:0.72rem;">${escapeHtml(formatCurrency(c.last_purchase_amount))}</div>
						` : `
							<div class="text-muted" style="font-size:0.76rem;">Sin información</div>
						`}
					</td>
					<td class="text-right text-muted pr-3">
						<i class="fa-solid fa-chevron-right" style="font-size:0.72rem;"></i>
					</td>
				</tr>
			`;
		}).join('');

		tbody.querySelectorAll('tr.customer-row').forEach(row => {
			row.addEventListener('click', function() {
				state.selectedId = Number(this.getAttribute('data-customer-id'));
				renderTable();
				renderSelectedCustomer();
				renderBottomSections();
			});
		});

		renderSelectedCustomer();
		renderBottomSections();
	}

	function renderSelectedCustomer() {
		const customer = state.customers.find(c => Number(c.id) === Number(state.selectedId)) || state.customers[0];
		if (!customer) return;

		const isActive = Number(customer.active) === 1;
		const typeLabel = customer.customer_type || 'Comercial';
		const typeClass = getTypeBadgeClass(typeLabel);

		document.getElementById('det-cust-name').textContent = customer.name || 'Sin nombre';
		document.getElementById('det-cust-code').textContent = customer.code || ('#' + (100 + Number(customer.id)));

		const statusBadge = document.getElementById('det-cust-status-badge');
		statusBadge.className = `cust-status-badge ${isActive ? 'is-active' : 'is-inactive'}`;
		statusBadge.textContent = isActive ? '● Activo' : '● Inactivo';

		const typeBadge = document.getElementById('det-cust-type-badge');
		typeBadge.className = `cust-type-badge ${typeClass}`;
		typeBadge.textContent = typeLabel;

		document.getElementById('det-cust-tax-id').textContent = customer.tax_id || 'Sin información';
		document.getElementById('det-cust-phone').textContent = customer.phone || 'Sin información';
		document.getElementById('det-cust-email').textContent = customer.email || 'Sin información';
		document.getElementById('det-cust-address').textContent = customer.address || 'Sin información';
		document.getElementById('det-cust-branch').textContent = customer.branch_name || 'Sucursal Centro';
		document.getElementById('det-cust-since').textContent = formatDateEs(customer.row_created_timestamp);

		const tagsContainer = document.getElementById('det-cust-tags');
		const tagsList = String(customer.tags || '')
			.split(',')
			.map(t => t.trim())
			.filter(Boolean);
		if (tagsList.length > 0) {
			tagsContainer.innerHTML = tagsList.map(t => `<span class="customers-tag-pill">${escapeHtml(t)}</span>`).join('');
		} else {
			tagsContainer.innerHTML = '<span class="text-muted font-weight-normal">Sin etiquetas</span>';
		}

		const missingRow = document.getElementById('det-cust-missing-row');
		const missingText = document.getElementById('det-cust-missing-text');
		if (customer.has_pending_data && Array.isArray(customer.missing_fields) && customer.missing_fields.length > 0) {
			missingRow.style.display = 'flex';
			missingText.textContent = 'Falta: ' + customer.missing_fields.join(', ');
		} else {
			missingRow.style.display = 'none';
		}

		// Mini KPIs comerciales
		document.getElementById('det-kpi-purchases').textContent = Number(customer.total_purchases || 0);
		document.getElementById('det-kpi-purchases-sub').textContent =
			Number(customer.open_orders_count || 0) > 0
				? `${customer.open_orders_count} pedido(s) abierto(s)`
				: 'Pedidos completados';

		document.getElementById('det-kpi-amount').textContent = formatCurrency(customer.total_amount || 0);
		document.getElementById('det-kpi-amount-sub').textContent = 'Últimos 12 meses';

		if (customer.last_purchase_date) {
			document.getElementById('det-kpi-last-date').textContent = formatDateEs(customer.last_purchase_date);
			document.getElementById('det-kpi-last-sub').textContent = formatRelativeDays(customer.last_purchase_date);
		} else {
			document.getElementById('det-kpi-last-date').textContent = 'Sin información';
			document.getElementById('det-kpi-last-sub').textContent = 'Sin compras registradas';
		}

		if (customer.has_credit_configured && customer.credit_limit !== null) {
			document.getElementById('det-kpi-credit').textContent = formatCurrency(customer.credit_available || 0);
			document.getElementById('det-kpi-credit-sub').textContent = `Límite: ${formatCurrency(customer.credit_limit)}`;
		} else {
			document.getElementById('det-kpi-credit').textContent = 'Sin información';
			document.getElementById('det-kpi-credit-sub').textContent = 'Sin línea configurada';
		}

		document.getElementById('det-cust-notes').textContent = customer.notes || 'Sin notas internas registradas.';
		document.getElementById('menu-toggle-active-label').textContent = isActive ? 'Cambiar a Inactivo' : 'Cambiar a Activo';

		// Enlaces directos hacia POS y Pedidos con el cliente seleccionado
		const posBase = window.DAILY_CUSTOMERS_INITIAL.posUrl || '/pos';
		const ordersBase = window.DAILY_CUSTOMERS_INITIAL.ordersUrl || '/orders';
		const posHref = `${posBase}?customer_id=${encodeURIComponent(customer.id)}`;
		const ordersHref = `${ordersBase}?customer_id=${encodeURIComponent(customer.id)}`;

		document.getElementById('btn-det-new-sale').setAttribute('href', posHref);
		document.getElementById('menu-new-pos-sale').setAttribute('href', posHref);
		document.getElementById('btn-header-goto-pos').setAttribute('href', posHref);

		document.getElementById('btn-det-view-orders').setAttribute('href', ordersHref);
		document.getElementById('menu-new-order-customer').setAttribute('href', ordersHref);
	}

	function renderBottomSections() {
		const customer = state.customers.find(c => Number(c.id) === Number(state.selectedId));
		const ordersTbody = document.getElementById('cust-recent-orders-tbody');
		const ordersTitle = document.getElementById('recent-orders-card-title');
		const ordersBase = window.DAILY_CUSTOMERS_INITIAL.ordersUrl || '/orders';

		let ordersToShow = [];
		if (customer && Array.isArray(customer.recent_orders) && customer.recent_orders.length > 0) {
			ordersToShow = customer.recent_orders.map(o => ({
				...o,
				customer_name: customer.name
			}));
			ordersTitle.textContent = `Pedidos recientes · ${customer.name}`;
		} else {
			ordersToShow = state.recentOrdersAll.slice(0, 5);
			ordersTitle.textContent = 'Pedidos recientes del directorio';
		}

		if (ordersToShow.length === 0) {
			ordersTbody.innerHTML = `
				<tr>
					<td colspan="6" class="text-center text-muted py-3">Sin pedidos o ventas recientes registradas.</td>
				</tr>
			`;
		} else {
			ordersTbody.innerHTML = ordersToShow.map(o => {
				const st = String(o.status || 'Completado');
				const isCompleted = st.toLowerCase().includes('complet') || st.toLowerCase().includes('despach');
				const badgeBg = isCompleted ? '#dcfce7' : '#ffedd5';
				const badgeColor = isCompleted ? '#15803d' : '#c2410c';
				const orderHref = o.source === 'order'
					? `${ordersBase}?id=${encodeURIComponent(o.ref_id)}`
					: `${ordersBase}?customer_id=${encodeURIComponent(o.customer_id || (customer ? customer.id : ''))}`;

				return `
					<tr class="border-bottom" style="cursor:pointer;" onclick="window.location.href='${escapeHtml(orderHref)}'">
						<td class="py-2 pl-1 font-weight-bold text-dark">${escapeHtml(o.code)}</td>
						<td class="py-2 text-secondary">${escapeHtml(o.customer_name || (customer ? customer.name : 'Cliente'))}</td>
						<td class="py-2 text-muted">${escapeHtml(formatDateEs(o.date))}</td>
						<td class="py-2">
							<span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.69rem; font-weight:700; background:${badgeBg}; color:${badgeColor};">
								● ${escapeHtml(st)}
							</span>
						</td>
						<td class="py-2 text-right font-weight-bold text-dark pr-1">${escapeHtml(formatCurrency(o.amount))}</td>
						<td class="py-2 text-right text-muted"><i class="fa-solid fa-chevron-right" style="font-size:0.68rem;"></i></td>
					</tr>
				`;
			}).join('');
		}

		// Datos incompletos
		const incompleteListEl = document.getElementById('cust-incomplete-list');
		const incompleteCustomers = state.customers.filter(c => Boolean(c.has_pending_data));

		if (incompleteCustomers.length === 0) {
			incompleteListEl.innerHTML = `
				<div class="text-center py-3 text-muted small">
					<i class="fa-solid fa-circle-check text-success mr-1"></i>Todos los clientes tienen sus datos fiscales y de contacto completos.
				</div>
			`;
		} else {
			incompleteListEl.innerHTML = incompleteCustomers.map(c => {
				const missingDesc = Array.isArray(c.missing_fields) && c.missing_fields.length > 0
					? 'Faltan datos de ' + c.missing_fields.join(', ') + '.'
					: 'Faltan datos de contacto y dirección fiscal.';
				return `
					<div class="customers-incomplete-item">
						<div class="d-flex align-items-center" style="gap:10px; min-width:0;">
							<i class="fa-solid fa-triangle-exclamation text-danger"></i>
							<div style="min-width:0;">
								<div class="font-weight-bold text-dark" style="font-size:0.81rem;">${escapeHtml(c.name)}</div>
								<div class="text-muted" style="font-size:0.71rem;">${escapeHtml(c.code || ('#' + (100 + Number(c.id))))} · ${escapeHtml(missingDesc)}</div>
							</div>
						</div>
						<div class="d-flex align-items-center" style="gap:8px;">
							<button type="button" class="customers-btn-complete btn-complete-customer" data-customer-id="${c.id}">
								Completar datos
							</button>
							<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.72rem;"></i>
						</div>
					</div>
				`;
			}).join('');

			incompleteListEl.querySelectorAll('.btn-complete-customer').forEach(btn => {
				btn.addEventListener('click', function() {
					const cid = Number(this.getAttribute('data-customer-id'));
					state.selectedId = cid;
					renderTable();
					openCustomerModal(cid);
				});
			});
		}
	}

	function openCustomerModal(customerId = null) {
		const isEdit = Boolean(customerId);
		const customer = isEdit ? state.customers.find(c => Number(c.id) === Number(customerId)) : null;

		document.getElementById('cust-duplicate-warning').classList.add('d-none');
		document.getElementById('cust-form-confirm-duplicate').value = '0';

		document.getElementById('modal-customer-title').innerHTML = isEdit
			? '<i class="fa-solid fa-user-pen text-success mr-2"></i>Editar cliente'
			: '<i class="fa-solid fa-user-plus text-success mr-2"></i>Nuevo cliente';

		document.getElementById('cust-form-id').value = customer ? customer.id : '';
		document.getElementById('cust-form-name').value = customer ? (customer.name || '') : '';
		document.getElementById('cust-form-tax-id').value = customer ? (customer.tax_id || '') : '';
		document.getElementById('cust-form-type').value = customer ? (customer.customer_type || 'Comercial') : 'Comercial';
		document.getElementById('cust-form-branch').value = customer ? (customer.branch_id || 1) : 1;
		document.getElementById('cust-form-active').value = customer ? String(Number(customer.active ?? 1)) : '1';
		document.getElementById('cust-form-phone').value = customer ? (customer.phone || '') : '';
		document.getElementById('cust-form-email').value = customer ? (customer.email || '') : '';
		document.getElementById('cust-form-address').value = customer ? (customer.address || '') : '';
		document.getElementById('cust-form-credit-limit').value =
			customer && customer.credit_limit !== null && customer.credit_limit !== '' ? customer.credit_limit : '';
		document.getElementById('cust-form-tags').value = customer ? (customer.tags || '') : '';
		document.getElementById('cust-form-notes').value = customer ? (customer.notes || '') : '';

		$('#modal-customer-form').modal('show');
	}

	async function submitCustomerForm(forceDuplicate = false) {
		const payload = {
			id: document.getElementById('cust-form-id').value || null,
			name: document.getElementById('cust-form-name').value.trim(),
			tax_id: document.getElementById('cust-form-tax-id').value.trim(),
			customer_type: document.getElementById('cust-form-type').value,
			branch_id: Number(document.getElementById('cust-form-branch').value || 1),
			active: Number(document.getElementById('cust-form-active').value || 1),
			phone: document.getElementById('cust-form-phone').value.trim(),
			email: document.getElementById('cust-form-email').value.trim(),
			address: document.getElementById('cust-form-address').value.trim(),
			credit_limit: document.getElementById('cust-form-credit-limit').value,
			tags: document.getElementById('cust-form-tags').value.trim(),
			notes: document.getElementById('cust-form-notes').value.trim(),
			confirm_duplicate: forceDuplicate || document.getElementById('cust-form-confirm-duplicate').value === '1'
		};

		try {
			const res = await fetch(window.DAILY_CUSTOMERS_INITIAL.apiCustomersUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			});
			const data = await res.json();

			if (data && data.duplicate_warning && Array.isArray(data.duplicates)) {
				const listEl = document.getElementById('cust-duplicate-list');
				listEl.innerHTML = data.duplicates.map(d =>
					`<li><strong>${escapeHtml(d.code)} - ${escapeHtml(d.name)}</strong> (coincide por: ${escapeHtml((d.reasons || []).join(', '))})</li>`
				).join('');
				document.getElementById('cust-duplicate-warning').classList.remove('d-none');
				return;
			}

			if (!res.ok || !data.success) {
				alert((data && data.error_message) ? data.error_message : 'No fue posible guardar el cliente.');
				return;
			}

			const saved = data.customer;
			if (saved) {
				const idx = state.customers.findIndex(c => Number(c.id) === Number(saved.id));
				if (idx >= 0) {
					state.customers[idx] = saved;
				} else {
					state.customers.push(saved);
				}
				state.selectedId = Number(saved.id);
			}

			$('#modal-customer-form').modal('hide');
			renderKpiCards();
			renderTable();
			if (typeof toastr !== 'undefined') {
				toastr.success('Datos del cliente guardados correctamente.');
			}
		} catch (err) {
			alert('Error de red al guardar el cliente: ' + err.message);
		}
	}

	function exportCustomers(sep, ext) {
		const rows = getFilteredCustomers();
		const headers = ['ID', 'Nombre / Razon Social', 'Identificacion Fiscal', 'Tipo', 'Estado', 'Telefono', 'Correo', 'Direccion', 'Sucursal', 'Total Compras', 'Monto Total', 'Ultima Compra', 'Credito Disponible'];
		const lines = [headers.join(sep)];

		rows.forEach(c => {
			const vals = [
				c.code || ('#' + (100 + Number(c.id))),
				c.name || '',
				c.tax_id || '',
				c.customer_type || '',
				Number(c.active) === 1 ? 'Activo' : 'Inactivo',
				c.phone || '',
				c.email || '',
				c.address || '',
				c.branch_name || '',
				Number(c.total_purchases || 0),
				Number(c.total_amount || 0).toFixed(2),
				c.last_purchase_date || 'Sin informacion',
				c.has_credit_configured ? Number(c.credit_available || 0).toFixed(2) : 'Sin linea configurada'
			].map(v => `"${String(v).replace(/"/g, '""')}"`);
			lines.push(vals.join(sep));
		});

		const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `directorio_clientes_daily.${ext}`;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	// Eventos de UI
	document.getElementById('cust-filter-search').addEventListener('input', function() {
		state.filters.search = this.value;
		renderTable();
	});

	document.getElementById('cust-filter-status').addEventListener('change', function() {
		state.filters.status = this.value;
		renderKpiCards();
		renderTable();
	});

	document.getElementById('cust-filter-type').addEventListener('change', function() {
		state.filters.type = this.value;
		renderTable();
	});

	document.getElementById('cust-filter-branch').addEventListener('change', function() {
		state.filters.branch = this.value;
		renderTable();
	});

	document.getElementById('cust-filter-sort').addEventListener('change', function() {
		state.filters.sort = this.value;
		renderTable();
	});

	function resetAllFilters() {
		state.filters = { search: '', status: 'all', type: 'all', branch: 'all', sort: 'code_asc' };
		document.getElementById('cust-filter-search').value = '';
		document.getElementById('cust-filter-status').value = 'all';
		document.getElementById('cust-filter-type').value = 'all';
		document.getElementById('cust-filter-branch').value = 'all';
		document.getElementById('cust-filter-sort').value = 'code_asc';
		renderKpiCards();
		renderTable();
	}

	document.getElementById('btn-clear-customer-filters').addEventListener('click', resetAllFilters);
	document.getElementById('btn-empty-reset-filters').addEventListener('click', resetAllFilters);

	document.querySelectorAll('.customers-kpi-card').forEach(card => {
		card.addEventListener('click', function() {
			const filterVal = this.getAttribute('data-kpi-filter') || 'all';
			state.filters.status = filterVal;
			document.getElementById('cust-filter-status').value = filterVal;
			renderKpiCards();
			renderTable();
		});
	});

	document.getElementById('link-filter-incomplete').addEventListener('click', function(e) {
		e.preventDefault();
		state.filters.status = 'pending_data';
		document.getElementById('cust-filter-status').value = 'pending_data';
		renderKpiCards();
		renderTable();
	});

	document.getElementById('btn-new-customer').addEventListener('click', function() {
		openCustomerModal(null);
	});

	document.getElementById('btn-det-edit-customer').addEventListener('click', function() {
		openCustomerModal(state.selectedId);
	});

	document.getElementById('menu-edit-customer').addEventListener('click', function(e) {
		e.preventDefault();
		openCustomerModal(state.selectedId);
	});

	document.getElementById('form-customer').addEventListener('submit', function(e) {
		e.preventDefault();
		submitCustomerForm(false);
	});

	document.getElementById('btn-confirm-save-duplicate').addEventListener('click', function() {
		document.getElementById('cust-form-confirm-duplicate').value = '1';
		submitCustomerForm(true);
	});

	// Notas rápidas
	function openQuickNotesModal() {
		const customer = state.customers.find(c => Number(c.id) === Number(state.selectedId));
		if (!customer) return;
		document.getElementById('notes-cust-id').value = customer.id;
		document.getElementById('notes-cust-name').textContent = `${customer.code} · ${customer.name}`;
		document.getElementById('notes-cust-tags').value = customer.tags || '';
		document.getElementById('notes-cust-text').value = customer.notes || '';
		$('#modal-customer-notes').modal('show');
	}

	document.getElementById('btn-quick-edit-notes').addEventListener('click', openQuickNotesModal);
	document.getElementById('menu-edit-notes').addEventListener('click', function(e) {
		e.preventDefault();
		openQuickNotesModal();
	});

	document.getElementById('form-customer-notes').addEventListener('submit', async function(e) {
		e.preventDefault();
		const cid = Number(document.getElementById('notes-cust-id').value || 0);
		if (!cid) return;

		try {
			const res = await fetch(`${window.DAILY_CUSTOMERS_INITIAL.apiCustomersUrl}/${cid}/notes`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					tags: document.getElementById('notes-cust-tags').value.trim(),
					notes: document.getElementById('notes-cust-text').value.trim()
				})
			});
			const data = await res.json();
			if (data && data.success && data.customer) {
				const idx = state.customers.findIndex(c => Number(c.id) === Number(cid));
				if (idx >= 0) state.customers[idx] = data.customer;
				$('#modal-customer-notes').modal('hide');
				renderTable();
				if (typeof toastr !== 'undefined') toastr.success('Notas actualizadas.');
			}
		} catch (err) {
			alert('No se pudieron guardar las notas: ' + err.message);
		}
	});

	// Cambiar estado Activo / Inactivo
	document.getElementById('menu-toggle-active').addEventListener('click', async function(e) {
		e.preventDefault();
		const customer = state.customers.find(c => Number(c.id) === Number(state.selectedId));
		if (!customer) return;
		const nextActive = Number(customer.active) === 1 ? 0 : 1;

		try {
			const res = await fetch(`${window.DAILY_CUSTOMERS_INITIAL.apiCustomersUrl}/${customer.id}/toggle-active`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ active: nextActive })
			});
			const data = await res.json();
			if (data && data.success && data.customer) {
				const idx = state.customers.findIndex(c => Number(c.id) === Number(customer.id));
				if (idx >= 0) state.customers[idx] = data.customer;
				renderKpiCards();
				renderTable();
				if (typeof toastr !== 'undefined') {
					toastr.info(`Estado del cliente actualizado a ${nextActive === 1 ? 'Activo' : 'Inactivo'}.`);
				}
			}
		} catch (err) {
			alert('Error al cambiar el estado del cliente: ' + err.message);
		}
	});

	// Exportación
	document.getElementById('btn-export-customers-csv').addEventListener('click', function(e) {
		e.preventDefault();
		exportCustomers(',', 'csv');
	});
	document.getElementById('btn-export-customers-tsv').addEventListener('click', function(e) {
		e.preventDefault();
		exportCustomers('\t', 'tsv');
	});
	document.getElementById('btn-export-customers-print').addEventListener('click', function(e) {
		e.preventDefault();
		window.print();
	});

	// Inicialización
	renderKpiCards();
	renderTable();
})();
</script>
@endpush
