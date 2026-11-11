@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Gestión de Clientes (CRM Retail) · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_customers.css?v=', true) }}{{ daily_asset_version('/css/daily_customers.css') }}" rel="stylesheet">
@endpush

@section('content')
@php
	$CRM = \Grocy\Services\CustomerCrmService::class;
	$o = $overview;
	$s = $o['settings'];
	$reg = $o['regional'];
	$sym = $reg['currency_symbol'];
	$money = fn($v) => $sym . number_format((float)$v, 2);
	$baseUrl = fn(array $add = []) => $U('/customers') . '?' . http_build_query(array_filter(array_merge([
		'branch_id' => $selectedBranchId,
		'days' => $days,
		'tab' => $tab
	], $add), fn($v) => $v !== null && $v !== ''));
	$selCustomer = null;
	foreach ($customers as $item)
	{
		if (intval($item['id']) === intval($selectedProfileId))
		{
			$selCustomer = $item;
			break;
		}
	}
	if (!$selCustomer && !empty($customers))
	{
		$selCustomer = $customers[0];
	}
	$segTone = ['champions' => 'good', 'loyal' => 'good', 'potential' => 'info', 'attention' => 'warn', 'at_risk' => 'warn', 'hibernating' => 'bad', 'none' => 'muted'];
@endphp

<div class="ded-wrapper crm" id="crmApp" data-active-tab="{{ $tab }}">
	<header class="ded-header">
		<div class="ded-header-left">
			<div class="ded-eyebrow">CRM Retail · Ciclo de Vida &amp; Fidelización</div>
			<div class="crm-title-row">
				<h1 class="ded-title mb-0">Gestión de Clientes</h1>
				<span class="crm-region-pill" title="Moneda, identificación fiscal ({{ $reg['tax_id_label'] }}) y teléfonos adaptados a {{ $reg['country_label'] }}">
					{{ $reg['country'] }} · {{ $reg['currency_code'] }} {{ $sym }}
				</span>
				<button type="button" class="crm-info-tip" title="CRM exclusivo para clientes identificados: registro, segmentación RFM, fidelización, casos y retención. No incluye inventario, logística ni RR.HH.">?</button>
			</div>
		</div>
		<div class="ded-header-right">
			<form method="get" action="{{ $U('/customers') }}" class="ded-controls-row" id="crm-global-filters">
				<input type="hidden" name="tab" id="crm-filter-tab" value="{{ $tab }}">
				<select name="branch_id" class="ded-select-btn" aria-label="Sucursal" onchange="this.form.submit()">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
						@php $bid = is_object($b) ? $b->id : $b['id']; $bn = is_object($b) ? $b->name : $b['name']; @endphp
						<option value="{{ $bid }}" @selected($selectedBranchId == $bid)>{{ $bn }}</option>
					@endforeach
				</select>
				<select name="days" class="ded-select-btn" aria-label="Período" onchange="this.form.submit()">
					<option value="30" @selected($days === 30)>Últimos 30 días</option>
					<option value="90" @selected($days === 90)>Últimos 90 días</option>
					<option value="365" @selected($days === 365)>Últimos 12 meses</option>
				</select>
			</form>
			<a class="ded-select-btn" href="{{ $U('/customers/export') . '?' . http_build_query(array_filter($filters + ['branch_id' => $selectedBranchId, 'days' => $days])) }}" title="Exportar directorio filtrado a CSV (auditado)">
				<i data-lucide="download"></i><span>Exportar</span>
			</a>
			@if($canManage)
				<button type="button" class="ded-select-btn crm-btn-primary" data-toggle="modal" data-target="#crm-new-customer">
					<i data-lucide="user-plus"></i><span>Nuevo Cliente</span>
				</button>
			@endif
		</div>
	</header>

	{{-- 7 Pestañas del CRM Retail (estilo nativo DAILY .ded-nav-tabs-bar) --}}
	<nav class="ded-nav-tabs-bar crm-tabs-bar" aria-label="Módulos del CRM">
		@foreach([
			'overview'  => ['1. Centro de Control', 'layout-dashboard', null],
			'directory' => ['2. Base de Clientes 360°', 'users', $o['registered']],
			'rfm'       => ['3. Segmentación RFM', 'pie-chart', null],
			'loyalty'   => ['4. Fidelización & Puntos', 'award', null],
			'cases'     => ['5. Atención & Casos', 'inbox', $o['open_requests'] > 0 ? $o['open_requests'] : null],
			'campaigns' => ['6. Campañas & Retención', 'megaphone', count($campaigns)],
			'analytics' => ['7. Inteligencia & Automatización', 'cpu', count($automations)]
		] as $k => [$label, $icon, $badge])
			<a class="ded-nav-tab crm-tab-pill @if($tab === $k) active @endif" data-crm-tab="{{ $k }}" href="{{ $baseUrl(['tab' => $k]) }}">
				<i data-lucide="{{ $icon }}"></i>
				<span>{{ $label }}</span>
				@if($badge !== null)<span class="crm-count">{{ $badge }}</span>@endif
			</a>
		@endforeach
	</nav>

	{{-- 1. CENTRO DE CONTROL: Solo 5 KPI prioritarios, 1 gráfica evolución, 1 resumen RFM y alertas accionables --}}
	<section class="crm-tab-panel @if($tab === 'overview') active @endif" id="panel-overview" data-crm-panel="overview">
		<div class="crm-kpis-grid-5" aria-label="Indicadores prioritarios CRM">
			<article class="ded-kpi-card is-highlight" title="Clientes registrados activos (excluye Público General / Mostrador y fichas fusionadas)">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Clientes Activos</span><i data-lucide="users" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ number_format($o['active_registered']) }}</div>
				<div class="ded-kpi-sub">{{ $o['registered'] }} registrados · {{ $o['buyers_period'] }} compraron en {{ $days }}d ({{ $o['new_period'] }} nuevos)</div>
			</article>

			<article class="ded-kpi-card" title="Porcentaje de compradores del período con al menos 2 compras netas en el período seleccionado">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Tasa de Recompra</span><i data-lucide="repeat" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ number_format($o['repurchase_pct'], 1) }} %</div>
				<div class="ded-kpi-sub">
					Meta {{ number_format($o['repurchase_goal_pct'], 0) }} %
					(<strong>{{ ($o['repurchase_diff_pp'] >= 0 ? '+' : '') . number_format($o['repurchase_diff_pp'], 1) }} pp</strong>) · {{ $o['repurchase_buyers'] }}/{{ $o['buyers_period'] }}
				</div>
			</article>

			<article class="ded-kpi-card" title="Valor anual promedio proyectado entre compradores reales identificados (sin Público General)">
				<div class="ded-kpi-header"><span class="ded-kpi-label">CLV Promedio Anual</span><i data-lucide="gem" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ $money($o['avg_clv']) }}</div>
				<div class="ded-kpi-sub">Ticket prom. {{ $money($o['avg_ticket']) }} · {{ number_format($o['avg_freq_month'], 2) }} compras/mes</div>
			</article>

			<a href="{{ $baseUrl(['tab' => 'rfm']) }}" class="ded-kpi-card crm-kpi-clickable" data-jump-tab="rfm" title="Clientes segmentados como En Riesgo (RFM). Clic para ver matriz RFM">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Clientes en Riesgo</span><i data-lucide="alert-triangle" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ number_format($o['at_risk']) }}</div>
				<div class="ded-kpi-sub">{{ $o['inactive'] }} inactivos (&gt;{{ $s['inactive_days'] }}d) · {{ $o['segments']['champions']['count'] }} Champions</div>
			</a>

			<a href="{{ $baseUrl(['tab' => 'cases']) }}" class="ded-kpi-card crm-kpi-clickable" data-jump-tab="cases" title="Cumplimiento de SLA y casos pendientes en Atención & Casos">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Atención y SLA</span><i data-lucide="shield-check" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ number_format($o['sla_compliance_pct'], 1) }} %</div>
				<div class="ded-kpi-sub">{{ $o['open_requests'] }} casos abiertos · {{ $o['overdue_requests'] }} vencidos</div>
			</a>
		</div>

		<div class="crm-grid-2">
			<article class="ded-card">
				<div class="ded-card-header">
					<div>
						<span class="ded-card-title">Evolución de Clientes Identificados (12 meses)</span>
						<span class="crm-meta d-block">Nuevos vs recurrentes e ingreso neto identificado (Mostrador sigue contabilizándose en POS)</span>
					</div>
					<span class="crm-pill crm-info">Cobertura POS: {{ $o['identified_share_pct'] !== null ? number_format($o['identified_share_pct'], 1) . ' %' : '—' }}</span>
				</div>
				<div class="crm-chart"><canvas id="crmEvolutionChart" role="img" aria-label="Evolución mensual de clientes"></canvas></div>
			</article>

			<article class="ded-card">
				<div class="ded-card-header">
					<div>
						<span class="ded-card-title">Resumen Segmentación RFM</span>
						<span class="crm-meta d-block">Total segmentados = {{ $o['registered'] }} clientes reales</span>
					</div>
					<a href="{{ $baseUrl(['tab' => 'rfm']) }}" class="crm-link" data-jump-tab="rfm">Detalle RFM →</a>
				</div>
				<ul class="crm-bars">
					@php $totRfm = max(1, $o['registered']); @endphp
					@foreach($o['segments'] as $k => $seg)
						<li>
							<span>
								<a href="{{ $baseUrl(['tab' => 'directory', 'segment' => $k]) }}" class="crm-link">{{ $seg['label'] }}</a>
							</span>
							<span class="crm-bar"><span class="crm-bar-fill crm-{{ $segTone[$k] ?? 'muted' }}" style="width: {{ round($seg['count'] / $totRfm * 100) }}%"></span></span>
							<strong class="text-right">{{ $seg['count'] }} <span class="crm-meta">({{ number_format($seg['share_pct'], 0) }}%)</span></strong>
						</li>
					@endforeach
				</ul>
				<div class="crm-foot">Ingreso neto acumulado de clientes identificados: <strong>{{ $money($o['identified_revenue']) }}</strong> en los últimos {{ $days }} días.</div>
			</article>
		</div>

		<article class="ded-card">
			<div class="ded-card-header">
				<span class="ded-card-title">Alertas Accionables</span>
				<span class="crm-meta">{{ count($o['alerts']) }} prioridad(es) detectadas automáticamente</span>
			</div>
			@if(empty($o['alerts']))
				<div class="crm-notice"><span class="crm-notice-left"><i data-lucide="check-circle-2"></i> Base saludable: sin casos vencidos, duplicados críticos ni riesgos urgentes en el período.</span></div>
			@else
				<div class="crm-alerts-list">
					@foreach($o['alerts'] as $al)
						<div class="crm-alert-item is-{{ $al['severity'] }}">
							<div>
								<strong class="d-block" style="font-size:13px;">{{ $al['title'] }}</strong>
								<span class="crm-meta">{{ $al['detail'] }}</span>
							</div>
							@if(!empty($al['target_url']))
								<a href="{{ $U($al['target_url']) }}" class="ded-select-btn" style="white-space:nowrap;">{{ $al['action_label'] }}</a>
							@else
								<a href="{{ $baseUrl(array_filter(['tab' => $al['target_tab'], ($al['filter_key'] ?? '') => ($al['filter_val'] ?? '')])) }}" class="ded-select-btn" style="white-space:nowrap;">{{ $al['action_label'] }}</a>
							@endif
						</div>
					@endforeach
				</div>
			@endif
		</article>
	</section>

	{{-- 2. BASE DE CLIENTES 360°: Tabla unificada + Ficha 360° interactiva + Control de duplicados --}}
	<section class="crm-tab-panel @if($tab === 'directory') active @endif" id="panel-directory" data-crm-panel="directory">
		<article class="ded-card">
			<form method="get" action="{{ $U('/customers') }}" class="crm-filters">
				<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
				<input type="hidden" name="days" value="{{ $days }}">
				<input type="hidden" name="tab" value="directory">
				<label class="crm-search"><i data-lucide="search"></i><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por nombre, {{ $reg['tax_id_label'] }}, correo o teléfono…"></label>
				<select name="segment" aria-label="Segmento RFM">
					<option value="">Todos los segmentos</option>
					@foreach($CRM::SEGMENTS as $k => $l)<option value="{{ $k }}" @selected(($filters['segment'] ?? '') === $k)>{{ $l }}</option>@endforeach
				</select>
				<select name="lifecycle" aria-label="Ciclo de vida">
					<option value="">Ciclo de vida</option>
					@foreach($CRM::LIFECYCLE as $k => $m)<option value="{{ $k }}" @selected(($filters['lifecycle'] ?? '') === $k)>{{ $m['label'] }}</option>@endforeach
				</select>
				<select name="quality" aria-label="Calidad">
					<option value="">Identificación</option>
					<option value="complete" @selected(($filters['quality'] ?? '') === 'complete')>Completa</option>
					<option value="basic" @selected(($filters['quality'] ?? '') === 'basic')>Básica</option>
					<option value="insufficient" @selected(($filters['quality'] ?? '') === 'insufficient')>Sin identificar</option>
				</select>
				<select name="sort" aria-label="Orden">
					<option value="revenue" @selected(($filters['sort'] ?? 'revenue') === 'revenue')>Mayor venta neta</option>
					<option value="tx" @selected(($filters['sort'] ?? '') === 'tx')>Más compras</option>
					<option value="recency" @selected(($filters['sort'] ?? '') === 'recency')>Compra más reciente</option>
					<option value="ticket" @selected(($filters['sort'] ?? '') === 'ticket')>Mayor ticket</option>
					<option value="points" @selected(($filters['sort'] ?? '') === 'points')>Más puntos</option>
					<option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nombre A–Z</option>
				</select>
				<button type="submit" class="ded-select-btn">Aplicar</button>
				@if(array_filter(array_diff_key($filters, ['days' => 1])))<a class="crm-link" href="{{ $baseUrl(['tab' => 'directory']) }}">Limpiar</a>@endif
			</form>

			<div class="crm-grid-360">
				<div class="table-responsive crm-scroll">
					<table class="table crm-table" id="crm-directory-table">
						<thead><tr>
							<th>Cliente</th>
							<th>Contacto</th>
							<th>Segmento / Nivel</th>
							<th class="text-right">Compras ({{ $days }}d)</th>
							<th class="text-right">Venta neta</th>
							<th class="text-right">Pts</th>
						</tr></thead>
						<tbody>
						@forelse($customers as $c)
							@php $life = $CRM::LIFECYCLE[$c['lifecycle']]; $isSel = $selCustomer && intval($selCustomer['id']) === intval($c['id']); @endphp
							<tr class="crm-row-selectable @if($isSel) is-selected @endif" data-select-customer="{{ intval($c['id']) }}">
								<td>
									<a class="crm-name" href="{{ $U('/customers/' . $c['id']) }}">{{ $c['name'] }}</a>
									@if(!intval($c['active']))<span class="crm-pill crm-bad">Inactivo</span>@endif
									<div class="crm-meta">{{ $c['customer_type'] }} · {{ $c['branch_name'] ?: 'Sin sucursal' }}</div>
								</td>
								<td>
									<div>{{ $c['tax_id'] ?: ($c['quality']['key'] === 'insufficient' ? 'Sin identificación' : '—') }}</div>
									<div class="crm-meta">{{ $c['phone'] ?: ($c['email'] ?: '—') }}</div>
								</td>
								<td>
									<span class="crm-pill crm-{{ $segTone[$c['segment']] ?? 'muted' }}">{{ $CRM::SEGMENTS[$c['segment']] }}</span>
									<span class="crm-tier crm-tier-{{ $c['value_tier'] ?: 'C' }}" title="Pareto Valor">{{ $c['value_tier'] ?: 'C' }}</span>
									<div class="crm-meta mt-1">{{ $life['label'] }} · {{ $c['loyalty_tier'] }}</div>
								</td>
								<td class="text-right">
									<div><strong>{{ $c['tx'] }}</strong> <span class="crm-meta">({{ $c['tx_period'] }})</span></div>
									<div class="crm-meta">{{ $c['last_purchase'] ? substr($c['last_purchase'], 0, 10) : 'Sin compras' }}</div>
								</td>
								<td class="text-right"><strong>{{ $money($c['revenue']) }}</strong><div class="crm-meta">{{ $money($c['avg_ticket']) }} tkt</div></td>
								<td class="text-right">{{ number_format($c['points_balance']) }}</td>
							</tr>
						@empty
							<tr><td colspan="6" class="crm-empty">Sin clientes identificados para estos filtros. Público General / Mostrador se gestiona en POS.</td></tr>
						@endforelse
						</tbody>
					</table>
				</div>

				{{-- Vista rápida 360° sincronizada con /api/customers/{id} --}}
				<aside class="ded-card crm-sticky-card" id="crm-360-preview" aria-label="Vista rápida 360 del cliente">
					@if($selCustomer)
						@php $selLife = $CRM::LIFECYCLE[$selCustomer['lifecycle']]; @endphp
						<div class="ded-card-header">
							<div>
								<span class="crm-meta d-block">#{{ $selCustomer['id'] }} · {{ $selCustomer['customer_type'] }}</span>
								<strong class="ded-card-title" id="crm-360-name">{{ $selCustomer['name'] }}</strong>
							</div>
							<span class="crm-pill crm-{{ $segTone[$selCustomer['segment']] ?? 'muted' }}" id="crm-360-segment">{{ $CRM::SEGMENTS[$selCustomer['segment']] }}</span>
						</div>
						<dl class="crm-dl mb-2" id="crm-360-dl">
							<dt>{{ $reg['tax_id_label'] }}</dt><dd>{{ $selCustomer['tax_id'] ?: '—' }}</dd>
							<dt>Teléfono</dt><dd>{{ $selCustomer['phone'] ?: '—' }}</dd>
							<dt>Correo</dt><dd>{{ $selCustomer['email'] ?: '—' }}</dd>
							<dt>Ciclo / Nivel</dt><dd>{{ $selLife['label'] }} · {{ $selCustomer['loyalty_tier'] }} ({{ number_format($selCustomer['points_balance']) }} pts)</dd>
							<dt>Compras netas</dt><dd>{{ $selCustomer['tx'] }} tickets · {{ $money($selCustomer['revenue']) }}</dd>
							<dt>Frecuencia</dt><dd>{{ $selCustomer['frequency_days'] !== null ? 'cada ' . $selCustomer['frequency_days'] . ' días' : 'Compra única / sin compras' }}</dd>
							<dt>CLV anual est.</dt><dd><strong>{{ $money($selCustomer['annual_value']) }}</strong></dd>
						</dl>
						<div class="d-flex flex-wrap" style="gap:6px;" id="crm-360-actions">
							<a href="{{ $U('/customers/' . $selCustomer['id']) }}" class="ded-select-btn crm-btn-primary">Abrir ficha 360°</a>
							<a href="{{ $U('/pos?customer_id=' . $selCustomer['id']) }}" class="ded-select-btn">Venta en POS</a>
						</div>
					@else
						<p class="crm-empty">Seleccione un cliente del listado para consultar su resumen 360°.</p>
					@endif
				</aside>
			</div>

			@if(!$canPii)<p class="crm-foot">Datos personales enmascarados para su rol (requiere permiso CUSTOMERS_PII). La búsqueda exacta por documento, correo o teléfono sigue operativa.</p>@endif
		</article>

		@if($duplicates)
		<article class="ded-card">
			<div class="ded-card-header">
				<span class="ded-card-title">Higiene de Datos · Posibles Duplicados ({{ count($duplicates) }})</span>
				<span class="crm-meta">Unifique fichas con el mismo documento, teléfono o correo para conservar un historial limpio.</span>
			</div>
			@foreach($duplicates as $idx => $g)
				<div class="crm-dup">
					<div class="crm-dup-head">
						<span class="crm-pill crm-{{ $g['confidence'] === 'alta' ? 'warn' : 'info' }}">Confianza {{ $g['confidence'] }}</span>
						Coincide por <strong>{{ implode(', ', $g['reasons']) }}</strong>
					</div>
					<div class="table-responsive"><table class="table crm-table crm-table-compact">
						<thead><tr><th>Conservar</th><th>Cliente</th><th>{{ $reg['tax_id_label'] }}</th><th>Teléfono</th><th>Correo</th><th class="text-right">Compras</th><th class="text-right">Venta neta</th></tr></thead>
						<tbody>
						@foreach($g['customers'] as $i => $c)
							<tr>
								<td><input type="radio" name="keep-{{ $idx }}" value="{{ $c['id'] }}" @checked($i === 0)></td>
								<td><a href="{{ $U('/customers/' . $c['id']) }}">#{{ $c['id'] }} {{ $c['name'] }}</a></td>
								<td>{{ $c['tax_id'] ?: '—' }}</td><td>{{ $c['phone'] ?: '—' }}</td><td>{{ $c['email'] ?: '—' }}</td>
								<td class="text-right">{{ $c['tx'] }}</td><td class="text-right">{{ $money($c['revenue']) }}</td>
							</tr>
						@endforeach
						</tbody>
					</table></div>
					@if($canManage)
						<button type="button" class="ded-select-btn mt-2 crm-merge" data-group="{{ $idx }}" data-ids="{{ implode(',', array_column($g['customers'], 'id')) }}">Fusionar en el seleccionado</button>
					@endif
				</div>
			@endforeach
		</article>
		@endif
	</section>

	{{-- 3. SEGMENTACIÓN RFM --}}
	<section class="crm-tab-panel @if($tab === 'rfm') active @endif" id="panel-rfm" data-crm-panel="rfm">
		<article class="ded-card">
			<div class="ded-card-header">
				<div class="d-flex align-items-center" style="gap:8px;">
					<span class="ded-card-title">Matriz RFM (Recencia · Frecuencia · Monto)</span>
					<button type="button" class="crm-info-tip" title="Recencia evalúa días desde la última compra frente al umbral de inactividad ({{ $s['inactive_days'] }}d). Frecuencia y Monto usan quintiles entre compradores reales. La suma coincide 1:1 con {{ $o['registered'] }} clientes registrados.">?</button>
				</div>
				<span class="crm-meta">Total segmentados: <strong>{{ $o['registered'] }}</strong> · En riesgo: <strong>{{ $o['at_risk'] }}</strong></span>
			</div>
			<div class="crm-kpis-grid-4 mb-3">
				@foreach(['champions', 'loyal', 'at_risk', 'hibernating'] as $rk)
					@php $rseg = $o['segments'][$rk]; @endphp
					<a href="{{ $baseUrl(['tab' => 'directory', 'segment' => $rk]) }}" class="ded-kpi-card crm-kpi-clickable">
						<div class="ded-kpi-header"><span class="ded-kpi-label">{{ $rseg['label'] }}</span><span class="crm-pill crm-{{ $segTone[$rk] }}">{{ number_format($rseg['share_pct'], 1) }} %</span></div>
						<div class="ded-kpi-value">{{ $rseg['count'] }}</div>
						<div class="ded-kpi-sub">{{ $money($rseg['revenue']) }} histórico · Ticket {{ $money($rseg['avg_ticket']) }}</div>
					</a>
				@endforeach
			</div>
			<div class="crm-grid-2">
				<div class="table-responsive">
					<table class="table crm-table crm-table-compact">
						<thead><tr><th>Segmento RFM</th><th class="text-right">Clientes</th><th class="text-right">% Base</th><th class="text-right">Venta histórica</th><th class="text-right">Venta ({{ $days }}d)</th><th></th></tr></thead>
						<tbody>
						@foreach($o['segments'] as $k => $seg)
							<tr>
								<td><span class="crm-pill crm-{{ $segTone[$k] ?? 'muted' }}">{{ $seg['label'] }}</span></td>
								<td class="text-right"><strong>{{ $seg['count'] }}</strong></td>
								<td class="text-right">{{ number_format($seg['share_pct'], 1) }} %</td>
								<td class="text-right">{{ $money($seg['revenue']) }}</td>
								<td class="text-right">{{ $money($seg['revenue_period']) }}</td>
								<td class="text-right"><a href="{{ $baseUrl(['tab' => 'directory', 'segment' => $k]) }}" class="crm-link">Ver →</a></td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
				<div>
					<h3 class="crm-h3 mt-0">Ciclo de vida operativo</h3>
					<ul class="crm-bars">
						@php $totLife = max(1, $o['registered']); @endphp
						@foreach($o['lifecycle'] as $k => $m)
							<li>
								<a href="{{ $baseUrl(['tab' => 'directory', 'lifecycle' => $k]) }}" class="crm-link">{{ $m['label'] }}</a>
								<span class="crm-bar"><span class="crm-bar-fill crm-{{ $m['tone'] }}" style="width: {{ round($m['count'] / $totLife * 100) }}%"></span></span>
								<strong>{{ $m['count'] }}</strong>
							</li>
						@endforeach
					</ul>
				</div>
			</div>
		</article>
	</section>

	{{-- 4. FIDELIZACIÓN & PUNTOS: Configuración, saldos e historial; el canje operativo ocurre en POS --}}
	<section class="crm-tab-panel @if($tab === 'loyalty') active @endif" id="panel-loyalty" data-crm-panel="loyalty">
		<div class="crm-notice">
			<span class="crm-notice-left">
				<i data-lucide="credit-card"></i>
				<span><strong>Operación de canje centralizada en caja:</strong> la redención de puntos durante una compra se ejecuta en el Punto de Venta (POS). Desde CRM se administran las reglas, niveles, saldos e historial.</span>
			</span>
			<a href="{{ $U('/pos') }}" class="ded-select-btn crm-btn-primary">Ir al POS para canjear →</a>
		</div>

		<div class="crm-kpis-grid-4">
			<article class="ded-kpi-card is-highlight">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Puntos Circulantes</span><i data-lucide="award" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ number_format($loyalty['total_points']) }} pts</div>
				<div class="ded-kpi-sub">Pasivo en puntos: {{ $money($loyalty['points_liability']) }}</div>
			</article>
			<article class="ded-kpi-card">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Emitidos ({{ $days }}d)</span><i data-lucide="trending-up" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">+{{ number_format($loyalty['earned_period']) }}</div>
				<div class="ded-kpi-sub">Canjeados en período: −{{ number_format($loyalty['redeemed_period']) }} pts</div>
			</article>
			<article class="ded-kpi-card">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Niveles Gold &amp; Platinum</span><i data-lucide="crown" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ ($loyalty['tiers']['Gold'] ?? 0) + ($loyalty['tiers']['Platinum'] ?? 0) }}</div>
				<div class="ded-kpi-sub">Platinum {{ $loyalty['tiers']['Platinum'] ?? 0 }} · Gold {{ $loyalty['tiers']['Gold'] ?? 0 }}</div>
			</article>
			<article class="ded-kpi-card">
				<div class="ded-kpi-header"><span class="ded-kpi-label">Silver &amp; Bronze</span><i data-lucide="medal" class="ded-kpi-icon"></i></div>
				<div class="ded-kpi-value">{{ ($loyalty['tiers']['Silver'] ?? 0) + ($loyalty['tiers']['Bronze'] ?? 0) }}</div>
				<div class="ded-kpi-sub">Silver {{ $loyalty['tiers']['Silver'] ?? 0 }} · Bronze {{ $loyalty['tiers']['Bronze'] ?? 0 }}</div>
			</article>
		</div>

		<div class="crm-grid-2">
			<article class="ded-card">
				<div class="ded-card-header"><span class="ded-card-title">Saldos de Puntos por Cliente</span></div>
				<div class="table-responsive crm-scroll">
					<table class="table crm-table crm-table-compact">
						<thead><tr><th>Cliente</th><th>Nivel</th><th class="text-right">Saldo pts</th><th class="text-right">Valor</th><th></th></tr></thead>
						<tbody>
						@forelse($loyalty['balances'] as $bc)
							<tr>
								<td><a href="{{ $U('/customers/' . $bc['id']) }}" class="crm-name">{{ $bc['name'] }}</a></td>
								<td><span class="crm-pill crm-muted">{{ $bc['loyalty_tier'] }}</span></td>
								<td class="text-right"><strong>{{ number_format($bc['points_balance']) }}</strong></td>
								<td class="text-right">{{ $money($bc['points_balance'] * $s['point_value']) }}</td>
								<td class="text-right"><a href="{{ $U('/customers/' . $bc['id'] . '#puntos') }}" class="crm-link">Ajuste / ver</a></td>
							</tr>
						@empty
							<tr><td colspan="5" class="crm-empty">{{ $loyalty['enabled'] ? 'Aún no hay movimientos de puntos en esta selección.' : 'El programa de puntos está desactivado.' }}</td></tr>
						@endforelse
						</tbody>
					</table>
				</div>
			</article>

			<article class="ded-card">
				<div class="ded-card-header"><span class="ded-card-title">Historial de Movimientos ({{ $days }}d)</span></div>
				<div class="table-responsive crm-scroll">
					<table class="table crm-table crm-table-compact">
						<thead><tr><th>Fecha</th><th>Cliente</th><th>Motivo</th><th class="text-right">Puntos</th></tr></thead>
						<tbody>
						@forelse($loyalty['ledger'] as $ld)
							<tr>
								<td>{{ substr($ld['created_at'], 0, 10) }}</td>
								<td><a href="{{ $U('/customers/' . $ld['customer_id']) }}">{{ $ld['customer_name'] }}</a></td>
								<td>{{ $ld['reason'] }}</td>
								<td class="text-right {{ intval($ld['points']) < 0 ? 'crm-bad' : 'crm-good' }}"><strong>{{ intval($ld['points']) > 0 ? '+' : '' }}{{ $ld['points'] }}</strong></td>
							</tr>
						@empty
							<tr><td colspan="4" class="crm-empty">Sin movimientos en los últimos {{ $days }} días.</td></tr>
						@endforelse
						</tbody>
					</table>
				</div>
			</article>
		</div>

		<article class="ded-card">
			<div class="ded-card-header">
				<span class="ded-card-title">Parámetros del Programa, Ciclo de Vida y Región</span>
				<button type="button" class="crm-info-tip" title="Configura tasa de acumulación/valor de punto (usados en POS), umbrales de recencia y región fiscal.">?</button>
			</div>
			<form id="crm-settings" class="crm-form-grid">
				<label class="crm-check crm-span-2">
					<input type="checkbox" name="loyalty_enabled" value="1" @checked($s['loyalty_enabled']) @disabled(!$canManage)>
					<span>Programa de puntos activo en POS</span>
				</label>
				<label>Cada {{ $sym }} de compra = 1 punto<input type="number" name="amount_per_point" value="{{ $s['amount_per_point'] }}" min="0.01" step="0.01" @disabled(!$canManage)></label>
				<label>Valor de cada punto ({{ $sym }})<input type="number" name="point_value" value="{{ $s['point_value'] }}" min="0" step="0.0001" @disabled(!$canManage)></label>
				<label>Acumula compras desde<input type="date" name="loyalty_start" value="{{ $s['loyalty_start'] }}" @disabled(!$canManage)></label>
				<label>Días cliente nuevo<input type="number" name="new_days" value="{{ $s['new_days'] }}" min="7" max="180" @disabled(!$canManage)></label>
				<label>Días cliente inactivo<input type="number" name="inactive_days" value="{{ $s['inactive_days'] }}" min="7" max="365" @disabled(!$canManage)></label>
				<label>Meta de recompra (%)<input type="number" name="repurchase_goal_pct" value="{{ $s['repurchase_goal_pct'] }}" min="5" max="95" step="0.5" @disabled(!$canManage)></label>
				<label>País / formato fiscal
					<select name="country" @disabled(!$canManage)>
						@foreach($CRM::REGIONS as $rk => $rm)<option value="{{ $rk }}" @selected($s['country'] === $rk)>{{ $rm['country_name'] }} ({{ $rm['tax_id_label'] }})</option>@endforeach
					</select>
				</label>
				@if($canManage)<div class="crm-span-2"><button type="submit" class="ded-select-btn crm-btn-primary">Guardar parámetros</button></div>@endif
			</form>
		</article>
	</section>

	{{-- 5. ATENCIÓN & CASOS: Reclamos de entrega enlazados a Despachos y crédito con aprobación financiera separada --}}
	<section class="crm-tab-panel @if($tab === 'cases') active @endif" id="panel-cases" data-crm-panel="cases">
		<article class="ded-card">
			<div class="crm-filters">
				<button type="button" class="ded-select-btn crm-btn-primary" data-toggle="modal" data-target="#crm-new-request">
					<i data-lucide="message-square-plus"></i><span>Nuevo Caso</span>
				</button>
				<div class="crm-seg" aria-label="Filtrar estado de casos">
					@foreach(['pending' => 'Pendientes', 'resolved' => 'Resueltos', 'cancelled' => 'Anulados', 'all' => 'Todos'] as $sk => $sl)
						<a href="{{ $baseUrl(['tab' => 'cases', 'req_status' => $sk, 'req_category' => $reqCategory]) }}" class="@if(($reqStatus ?: 'all') === $sk) is-active @endif">{{ $sl }}</a>
					@endforeach
				</div>
				<form method="get" action="{{ $U('/customers') }}" class="d-inline-flex align-items-center" style="gap:6px;">
					<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
					<input type="hidden" name="days" value="{{ $days }}">
					<input type="hidden" name="tab" value="cases">
					<input type="hidden" name="req_status" value="{{ $reqStatus ?: 'all' }}">
					<select name="req_category" aria-label="Filtrar categoría" onchange="this.form.submit()">
						<option value="">Todas las categorías</option>
						@foreach($CRM::REQUEST_CATEGORIES as $ck => $cl)<option value="{{ $ck }}" @selected($reqCategory === $ck)>{{ $cl }}</option>@endforeach
					</select>
				</form>
			</div>

			<div class="table-responsive">
				<table class="table crm-table">
					<thead><tr><th>Código</th><th>Cliente</th><th>Categoría</th><th>Asunto y detalle</th><th>Prioridad</th><th>Responsable</th><th>SLA / Vence</th><th>Estado</th><th></th></tr></thead>
					<tbody>
					@forelse($requests as $r)
						<tr @if($r['overdue']) class="crm-row-overdue" @endif>
							<td><strong>{{ $r['code'] }}</strong></td>
							<td><a href="{{ $U('/customers/' . $r['customer_id']) }}">{{ $r['customer_name'] }}</a></td>
							<td>
								<span class="crm-chip">{{ $CRM::REQUEST_CATEGORIES[$r['category']] ?? $r['category'] }}</span>
								@if($r['category'] === 'credit')
									@php $fs = $r['financial_status'] ?? 'pending_finance'; $fTone = ['approved_finance' => 'good', 'rejected_finance' => 'bad', 'pending_finance' => 'warn'][$fs] ?? 'muted'; @endphp
									<span class="crm-pill crm-{{ $fTone }} d-block mt-1">Finanzas: {{ $CRM::FINANCIAL_STATUSES[$fs] ?? $fs }}</span>
								@endif
							</td>
							<td>
								{{ $r['subject'] }}
								@if($r['requested_credit_limit'] !== null)<div class="crm-meta">Cupo solicitado: <strong>{{ $money($r['requested_credit_limit']) }}</strong></div>@endif
								@if($r['dispatch_order_id'])<div class="crm-meta">Despacho vinculado #{{ $r['dispatch_order_id'] }}</div>@endif
								@if($r['resolution'])<div class="crm-meta">{{ $r['resolution'] }}</div>@endif
							</td>
							<td><span class="crm-pill crm-prio-{{ $r['priority'] }}">{{ $CRM::REQUEST_PRIORITIES[$r['priority']] }}</span></td>
							<td>{{ $r['assigned_to'] ?: 'Sin asignar' }}</td>
							<td>{{ $r['due_date'] ?: '—' }}@if($r['overdue']) <span class="crm-pill crm-bad">Vencida</span>@endif</td>
							<td>{{ $CRM::REQUEST_STATUSES[$r['status']] }}</td>
							<td class="text-right" style="white-space:nowrap;">
								@if($r['category'] === 'delivery')
									<a href="{{ $U('/dispatches') }}" class="crm-link mr-2" title="La resolución operativa logística se ejecuta en Despachos">Despachos →</a>
								@endif
								@if($r['category'] === 'credit' && ($r['financial_status'] ?? 'pending_finance') === 'pending_finance' && $canApproveFinance)
									<button type="button" class="crm-link mr-2 text-primary" data-credit-decision="{{ json_encode(array_intersect_key($r, array_flip(['id', 'code', 'subject', 'customer_name', 'requested_credit_limit']))) }}">Aprobar crédito</button>
								@endif
								<button type="button" class="crm-link" data-request="{{ json_encode(array_intersect_key($r, array_flip(['id', 'code', 'subject', 'status', 'assigned_user_id', 'due_date', 'resolution', 'dispatch_order_id']))) }}">Gestionar</button>
							</td>
						</tr>
					@empty
						<tr><td colspan="9" class="crm-empty">Sin casos ni solicitudes para este filtro.</td></tr>
					@endforelse
					</tbody>
				</table>
			</div>
		</article>
	</section>

	{{-- 6. CAMPAÑAS & RETENCIÓN --}}
	<section class="crm-tab-panel @if($tab === 'campaigns') active @endif" id="panel-campaigns" data-crm-panel="campaigns">
		<article class="ded-card">
			<div class="ded-card-header">
				<div>
					<span class="ded-card-title">Campañas de Retención y Reactivación</span>
					<span class="crm-meta d-block">Audiencia calculada según consentimiento vigente por canal y compras reales posteriores al inicio</span>
				</div>
				@if($canManage)
					<button type="button" class="ded-select-btn crm-btn-primary" data-toggle="modal" data-target="#crm-new-campaign">
						<i data-lucide="plus"></i><span>Nueva Campaña</span>
					</button>
				@endif
			</div>

			<div class="crm-campaigns-grid">
				@forelse($campaigns as $cp)
					@php
						$stTone = ['active' => 'good', 'paused' => 'warn', 'scheduled' => 'info', 'completed' => 'muted'][$cp['status']] ?? 'muted';
						$stLabel = ['active' => 'Activa', 'paused' => 'Pausada', 'scheduled' => 'Programada', 'completed' => 'Finalizada'][$cp['status']] ?? $cp['status'];
					@endphp
					<div class="crm-campaign-card">
						<div class="d-flex justify-content-between align-items-start" style="gap:8px;">
							<div>
								<strong style="font-size:14px;">{{ $cp['name'] }}</strong>
								<div class="crm-meta">{{ $CRM::CAMPAIGN_CHANNELS[$cp['channel']] ?? $cp['channel'] }} · Segmento: {{ $CRM::SEGMENTS[$cp['target_segment']] ?? 'Todos' }}</div>
							</div>
							<span class="crm-pill crm-{{ $stTone }}">{{ $stLabel }}</span>
						</div>
						@if($cp['offer_text'])<p class="crm-meta mb-0">{{ $cp['offer_text'] }}</p>@endif
						<div class="d-flex justify-content-between pt-2 border-top" style="font-size:12px;">
							<span>Audiencia con permiso: <strong>{{ $cp['eligible_count'] }}</strong> / {{ $cp['target_count'] }}</span>
							<span>Conversión: <strong>{{ $cp['converted_count'] }}</strong> ({{ number_format($cp['conversion_pct'], 1) }} %)</span>
						</div>
						<div class="d-flex justify-content-between align-items-center" style="font-size:12px;">
							<span class="crm-meta">Ingreso medido: <strong>{{ $money($cp['converted_revenue']) }}</strong></span>
							<div class="d-flex" style="gap:10px;">
								<a href="{{ $baseUrl(['tab' => 'directory', 'segment' => $cp['target_segment'] !== 'all' ? $cp['target_segment'] : '']) }}" class="crm-link">Audiencia →</a>
								@if($canManage)
									<button type="button" class="crm-link crm-campaign-toggle" data-campaign-id="{{ $cp['id'] }}" data-next-status="{{ $cp['status'] === 'active' ? 'paused' : 'active' }}">
										{{ $cp['status'] === 'active' ? 'Pausar' : 'Activar' }}
									</button>
								@endif
							</div>
						</div>
					</div>
				@empty
					<p class="crm-empty">Aún no hay campañas registradas.</p>
				@endforelse
			</div>
		</article>
	</section>

	{{-- 7. INTELIGENCIA & AUTOMATIZACIÓN (incluye auditoría PII para gerencia) --}}
	<section class="crm-tab-panel @if($tab === 'analytics') active @endif" id="panel-analytics" data-crm-panel="analytics">
		<article class="ded-card">
			<div class="ded-card-header">
				<div>
					<span class="ded-card-title">Reglas Inteligentes y Automatización</span>
					<span class="crm-meta d-block">Evaluación en tiempo real sobre clientes identificados de {{ $selectedBranchId ? 'la sucursal seleccionada' : 'todas las sucursales' }}</span>
				</div>
				@if($canManage)
					<button type="button" class="ded-select-btn crm-btn-primary" id="crm-run-automations">
						<i data-lucide="play"></i><span>Evaluar reglas ahora</span>
					</button>
				@endif
			</div>

			<div class="table-responsive">
				<table class="table crm-table">
					<thead><tr><th>Regla</th><th>Disparador</th><th>Acción / Canal</th><th class="text-right">Coincidencias reales</th><th>Última ejecución</th><th>Estado</th><th></th></tr></thead>
					<tbody>
					@forelse($automations as $au)
						<tr>
							<td><strong>{{ $au['name'] }}</strong></td>
							<td class="crm-meta">{{ $au['trigger_type'] }}</td>
							<td>{{ $au['action_text'] }}</td>
							<td class="text-right"><strong>{{ $au['live_matches'] }}</strong> <span class="crm-meta">registros</span></td>
							<td>{{ $au['last_run_at'] ? substr($au['last_run_at'], 0, 16) : 'Sin ejecutar' }}</td>
							<td><span class="crm-pill crm-{{ intval($au['active']) ? 'good' : 'muted' }}">{{ intval($au['active']) ? 'Activa' : 'Pausada' }}</span></td>
							<td class="text-right">
								@if($canManage)
									<button type="button" class="crm-link crm-auto-toggle" data-rule-id="{{ $au['id'] }}" data-next-active="{{ intval($au['active']) ? 0 : 1 }}">
										{{ intval($au['active']) ? 'Pausar' : 'Activar' }}
									</button>
								@endif
							</td>
						</tr>
					@empty
						<tr><td colspan="7" class="crm-empty">Sin reglas configuradas.</td></tr>
					@endforelse
					</tbody>
				</table>
			</div>
		</article>

		@if($canManage)
		<article class="ded-card">
			<div class="ded-card-header">
				<span class="ded-card-title">Auditoría y Trazabilidad CRM</span>
				<span class="crm-meta">Consultas PII, ediciones, fusiones, aprobaciones financieras y automatizaciones</span>
			</div>
			<div class="table-responsive crm-scroll">
				<table class="table crm-table crm-table-compact">
					<thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Cliente</th><th>Detalle</th></tr></thead>
					<tbody>
					@forelse($audit as $a)
						<tr>
							<td>{{ $a['created_at'] }}</td>
							<td>{{ $a['username'] ?? '—' }}</td>
							<td><span class="crm-chip">{{ $a['action'] }}</span></td>
							<td>@if($a['customer_id'])<a href="{{ $U('/customers/' . $a['customer_id']) }}">{{ $a['customer_name'] ?: '#' . $a['customer_id'] }}</a>@else — @endif</td>
							<td class="crm-meta crm-mono">{{ $a['detail'] }}</td>
						</tr>
					@empty
						<tr><td colspan="5" class="crm-empty">Sin eventos registrados.</td></tr>
					@endforelse
					</tbody>
				</table>
			</div>
		</article>
		@endif
	</section>
</div>

@include('components.crm_modals', [
	'customersForSelect' => $customers,
	'branches' => $branches,
	'users' => $users,
	'canPii' => $canPii,
	'regional' => $reg,
	'fixedCustomerId' => null
])

<script type="application/json" id="crm-monthly-data">{!! json_encode($o['monthly'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="crm-boot">{!! json_encode([
	'canManage' => $canManage,
	'canPii' => $canPii,
	'canApproveFinance' => $canApproveFinance,
	'branchId' => $selectedBranchId,
	'days' => $days,
	'regional' => $reg
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script src="{{ $U('/js/daily_customers.js?v=', true) }}{{ daily_asset_version('/js/daily_customers.js') }}"></script>
@endpush
