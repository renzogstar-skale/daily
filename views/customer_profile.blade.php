@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', $customer['name'] . ' · Clientes · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_customers.css?v=', true) }}{{ daily_asset_version('/css/daily_customers.css') }}" rel="stylesheet">
@endpush

@section('content')
@php
	$CRM = \Grocy\Services\CustomerCrmService::class;
	$reg = $CRM::GetInstance()->RegionalConfig();
	$c = $customer;
	$sym = $reg['currency_symbol'];
	$money = fn($v) => $sym . number_format((float)$v, 2);
	$life = $CRM::LIFECYCLE[$c['lifecycle']];
	$kindLabels = $CRM::INTERACTION_KINDS;
@endphp

<div class="ded-wrapper crm" id="crmProfile" data-customer-id="{{ $c['id'] }}">
	<a class="crm-back" href="{{ $U('/customers') }}"><i data-lucide="arrow-left"></i> Clientes</a>
	<header class="crm-profile-head">
		<div>
			<h1 class="ded-title">{{ $c['name'] }}</h1>
			<p class="crm-tags">
				<span class="crm-pill crm-{{ $life['tone'] }}">{{ $life['label'] }}</span>
				<span class="crm-pill crm-muted">{{ $CRM::SEGMENTS[$c['segment']] }}</span>
				@if($c['value_tier'])<span class="crm-tier crm-tier-{{ $c['value_tier'] }}" title="Tier de valor (Pareto)">Tier {{ $c['value_tier'] }}</span>@endif
				@if(!intval($c['active']))<span class="crm-pill crm-bad">Ficha inactiva</span>@endif
				<span class="crm-meta">#{{ $c['id'] }} · {{ $c['customer_type'] }} · {{ $c['branch_name'] ?? 'Sin sucursal de referencia' }}</span>
				@foreach(array_filter(array_map('trim', explode(',', (string)$c['tags']))) as $t)<span class="crm-chip">{{ $t }}</span>@endforeach
			</p>
		</div>
		<div class="ded-header-right">
			@if($canManage)
				<button type="button" class="ded-select-btn" id="crm-edit"><i data-lucide="pencil"></i><span>Editar ficha</span></button>
				<button type="button" class="ded-select-btn" id="crm-toggle-active" data-active="{{ intval($c['active']) }}"><i data-lucide="{{ intval($c['active']) ? 'user-x' : 'user-check' }}"></i><span>{{ intval($c['active']) ? 'Desactivar' : 'Reactivar' }}</span></button>
			@endif
			<button type="button" class="ded-select-btn crm-btn-primary" data-toggle="modal" data-target="#crm-new-request"><i data-lucide="message-square-plus"></i><span>Nueva solicitud</span></button>
		</div>
	</header>

	@if($duplicates)
		<div class="crm-notice crm-notice-warn" role="alert"><i data-lucide="copy"></i> Esta ficha coincide con
			@foreach($duplicates[0]['customers'] as $d)@if($d['id'] !== $c['id'])<a href="{{ $U('/customers/' . $d['id']) }}">#{{ $d['id'] }} {{ $d['name'] }}</a> @endif @endforeach
			por {{ implode(', ', $duplicates[0]['reasons']) }}. <a href="{{ $U('/customers?tab=quality') }}">Revisar duplicados</a></div>
	@endif
	@if($c['quality']['key'] === 'insufficient')
		<div class="crm-notice crm-notice-warn" role="alert"><i data-lucide="alert-triangle"></i> Identificación insuficiente: la ficha no tiene documento, correo ni teléfono, así que no se puede distinguir de otro cliente con el mismo nombre.</div>
	@endif

	<section class="crm-kpis" aria-label="Indicadores del cliente">
		<article class="crm-kpi"><span class="crm-kpi-label">Compras</span><strong class="crm-kpi-val">{{ $c['tx'] }}</strong><span class="crm-kpi-sub">{{ $c['tx_90d'] }} en 90 días · {{ $c['visit_days'] }} días con compra</span></article>
		<article class="crm-kpi"><span class="crm-kpi-label">Venta neta histórica</span><strong class="crm-kpi-val">{{ $money($c['revenue']) }}</strong><span class="crm-kpi-sub">{{ $money($c['revenue_90d']) }} en 90 días · neto de devoluciones</span></article>
		<article class="crm-kpi"><span class="crm-kpi-label">Ticket promedio</span><strong class="crm-kpi-val">{{ $c['tx'] ? $money($c['avg_ticket']) : '—' }}</strong><span class="crm-kpi-sub">por compra</span></article>
		<article class="crm-kpi"><span class="crm-kpi-label">Frecuencia</span><strong class="crm-kpi-val">{{ $c['frequency_days'] !== null ? 'cada ' . $c['frequency_days'] . ' d' : '—' }}</strong><span class="crm-kpi-sub">{{ $c['purchases_per_year'] }} compras/año observadas</span></article>
		<article class="crm-kpi"><span class="crm-kpi-label">Última compra</span><strong class="crm-kpi-val">{{ $c['recency_days'] !== null ? 'hace ' . $c['recency_days'] . ' d' : '—' }}</strong><span class="crm-kpi-sub">{{ $c['first_purchase'] ? 'Cliente desde ' . substr($c['first_purchase'], 0, 10) : 'Sin compras registradas' }}</span></article>
		<article class="crm-kpi" title="Ticket promedio × compras por año observadas desde la primera compra"><span class="crm-kpi-label">Valor anual proyectado</span><strong class="crm-kpi-val">{{ $money($c['annual_value']) }}</strong><span class="crm-kpi-sub">si mantiene su ritmo actual</span></article>
	</section>

	<div class="crm-profile-grid">
		<aside class="crm-col">
			<section class="ded-card">
				<div class="ded-card-header"><span class="ded-card-title">Identificación y contacto</span><span class="crm-pill crm-{{ ['complete' => 'good', 'basic' => 'warn', 'insufficient' => 'bad'][$c['quality']['key']] }}">{{ $c['quality']['label'] }}</span></div>
				<dl class="crm-dl">
					<dt>{{ $reg['tax_id_label'] }}</dt><dd>{{ $c['tax_id'] ?: '—' }}</dd>
					<dt>Teléfono</dt><dd>{{ $c['phone'] ?: '—' }}</dd>
					<dt>Correo</dt><dd>{{ $c['email'] ?: '—' }}</dd>
					<dt>Dirección</dt><dd>{{ $c['address'] ?: '—' }}</dd>
					@if($c['pii_visible'])<dt>Nacimiento</dt><dd>{{ $c['birth_date'] ?: '—' }}</dd>@endif
					@if($c['credit_limit'] !== null)<dt>Límite de crédito</dt><dd>{{ $money($c['credit_limit']) }}</dd>@endif
					<dt>Pedidos</dt><dd>{{ $orders['total'] }} @if($orders['open'])({{ $orders['open'] }} abiertos · <a href="{{ $U('/orders') }}">ver en Pedidos</a>)@endif</dd>
				</dl>
				@if(!$c['pii_visible'])<p class="crm-foot"><i data-lucide="lock"></i> Datos personales enmascarados para su rol.</p>@else<p class="crm-foot">Esta consulta quedó registrada en la trazabilidad del cliente.</p>@endif
				@if($c['notes'])<p class="crm-notes">{{ $c['notes'] }}</p>@endif
			</section>

			<section class="ded-card" id="consentimientos">
				<div class="ded-card-header"><span class="ded-card-title">Consentimientos</span></div>
				<ul class="crm-consents">
					@foreach($consents['current'] as $k => $cs)
						<li>
							<div><strong>{{ $cs['label'] }}</strong>
								<div class="crm-meta">@if($cs['granted'] === null) Sin registro @else {{ $cs['granted'] ? 'Autorizado' : 'Revocado' }} · {{ substr($cs['at'], 0, 16) }} · {{ $CRM::CONSENT_SOURCES[$cs['source']] ?? $cs['source'] }} @endif</div></div>
							<span class="crm-pill {{ $cs['granted'] === null ? 'crm-muted' : ($cs['granted'] ? 'crm-good' : 'crm-bad') }}">{{ $cs['granted'] === null ? '—' : ($cs['granted'] ? 'Sí' : 'No') }}</span>
							<button type="button" class="crm-link" data-consent="{{ $k }}" data-granted="{{ $cs['granted'] ? 0 : 1 }}">{{ $cs['granted'] ? 'Revocar' : 'Registrar autorización' }}</button>
						</li>
					@endforeach
				</ul>
				<label class="crm-inline">Origen<select id="crm-consent-source">@foreach($CRM::CONSENT_SOURCES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
				<label class="crm-inline">Evidencia<input type="text" id="crm-consent-evidence" maxlength="200" placeholder="Nº de formulario, grabación…"></label>
				@if($consents['history'])
					<details class="crm-details"><summary>Historial ({{ count($consents['history']) }})</summary>
						<ul class="crm-timeline">@foreach($consents['history'] as $h)<li><span class="crm-meta">{{ substr($h['created_at'], 0, 16) }} · {{ $h['username'] ?? '—' }}</span> {{ $CRM::CONSENT_PURPOSES[$h['purpose']] ?? $h['purpose'] }}: <strong>{{ $h['granted'] ? 'autorizado' : 'revocado' }}</strong> ({{ $CRM::CONSENT_SOURCES[$h['source']] ?? $h['source'] }}){{ $h['evidence'] ? ' · ' . $h['evidence'] : '' }}</li>@endforeach</ul>
					</details>
				@endif
			</section>

			<section class="ded-card">
				<div class="ded-card-header"><span class="ded-card-title">Preferencias</span></div>
				<form id="crm-prefs" class="crm-form-grid crm-form-1">
					@foreach($CRM::PREFERENCES as $k => $meta)
						@if($meta['options'] === null)
							<label>{{ $meta['label'] }}<textarea name="{{ $k }}" rows="2" maxlength="500">{{ $preferences[$k] ?? '' }}</textarea></label>
						@else
							<label>{{ $meta['label'] }}<select name="{{ $k }}">@foreach($meta['options'] as $ov => $ol)<option value="{{ $ov }}" @selected(($preferences[$k] ?? '') === (string)$ov)>{{ $ol }}</option>@endforeach</select></label>
						@endif
					@endforeach
					<button type="submit" class="ded-select-btn">Guardar preferencias</button>
				</form>
				@if($departments)
					<h3 class="crm-h3">Departamentos donde más compra</h3>
					<ul class="crm-bars">
						@php $maxD = max(array_column($departments, 'revenue')) ?: 1; @endphp
						@foreach($departments as $d)<li><span>{{ $d['department'] }}</span><span class="crm-bar"><span class="crm-bar-fill crm-info" style="width: {{ round($d['revenue'] / $maxD * 100) }}%"></span></span><strong>{{ $money($d['revenue']) }}</strong></li>@endforeach
					</ul>
				@endif
			</section>

			@if($loyalty['enabled'])
			<section class="ded-card" id="puntos">
				<div class="ded-card-header"><span class="ded-card-title">Puntos y fidelización</span><strong class="crm-kpi-val">{{ number_format($loyalty['balance']) }}</strong></div>
				<p class="crm-meta">Equivale a {{ $money($loyalty['balance_value']) }} · 1 punto por cada {{ $money($loyalty['settings']['amount_per_point']) }} desde {{ $loyalty['settings']['loyalty_start'] }}. El canje al cobrar se realiza en <a href="{{ $U('/pos?customer_id=' . $c['id']) }}">Punto de Venta (POS)</a>.</p>
				@if($canManage)
					<form id="crm-loyalty" class="crm-form-grid">
						<input type="hidden" name="kind" value="adjust">
						<label>Ajuste (+/− pts)<input type="number" name="points" step="1" required placeholder="Ej. 50 o -20"></label>
						<label>Motivo *<input type="text" name="reason" minlength="5" maxlength="200" required placeholder="Cortesía o corrección"></label>
						<button type="submit" class="ded-select-btn crm-span-2">Registrar ajuste administrativo</button>
					</form>
				@endif
				<details class="crm-details"><summary>Historial de puntos</summary>
					<table class="table crm-table crm-table-compact"><tbody>
						@forelse($loyalty['ledger'] as $l)<tr><td>{{ substr($l['created_at'], 0, 10) }}</td><td>{{ $l['reason'] }}</td><td class="text-right {{ $l['points'] < 0 ? 'crm-bad' : '' }}">{{ $l['points'] > 0 ? '+' : '' }}{{ $l['points'] }}</td></tr>@empty<tr><td class="crm-empty">Sin movimientos.</td></tr>@endforelse
					</tbody></table>
				</details>
			</section>
			@endif
		</aside>

		<div class="crm-col crm-col-main">
			<section class="ded-card">
				<div class="ded-card-header"><span class="ded-card-title">Compras por mes (12 meses)</span></div>
				<div class="crm-chart crm-chart-sm"><canvas id="crm-profile-monthly" role="img" aria-label="Compras del cliente por mes"></canvas></div>
				@if($products)
					<h3 class="crm-h3">Productos que más repite</h3>
					<div class="table-responsive"><table class="table crm-table crm-table-compact">
						<thead><tr><th>Producto</th><th class="text-right">Compras</th><th class="text-right">Cantidad</th><th class="text-right">Importe</th><th>Última vez</th></tr></thead>
						<tbody>@foreach($products as $p)<tr><td>{{ $p['name'] }}</td><td class="text-right">{{ $p['tickets'] }}</td><td class="text-right">{{ rtrim(rtrim(number_format($p['qty'], 2), '0'), '.') }}</td><td class="text-right">{{ $money($p['revenue']) }}</td><td>{{ substr($p['last_ts'], 0, 10) }}</td></tr>@endforeach</tbody>
					</table></div>
				@endif
			</section>

			<section class="ded-card" id="historial">
				<div class="ded-card-header"><span class="ded-card-title">Historial de compras</span><span class="crm-meta">{{ count($history) }} de {{ $c['tx'] }} compras{{ count($history) < $c['tx'] ? ' (más recientes)' : '' }}</span></div>
				<div class="table-responsive crm-scroll"><table class="table crm-table crm-table-compact">
					<thead><tr><th>Fecha</th><th>Ticket</th><th>Sucursal</th><th class="text-right">Líneas</th><th>Pago</th><th class="text-right">Total</th><th class="text-right">Devuelto</th></tr></thead>
					<tbody>
					@forelse($history as $h)
						<tr><td>{{ substr($h['ts'], 0, 16) }}</td><td>{{ $h['sale_number'] }}</td><td>{{ $h['branch_name'] }}</td><td class="text-right">{{ $h['lines'] }}</td><td>{{ ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'mixed' => 'Mixto', 'credit' => 'Crédito', 'credit_note' => 'Nota de crédito', 'points' => 'Canje de puntos'][$h['payment_method']] ?? $h['payment_method'] }}</td>
							<td class="text-right">{{ $money($h['total_amount']) }}</td><td class="text-right {{ $h['returned'] > 0 ? 'crm-bad' : '' }}">{{ $h['returned'] > 0 ? '−' . $money($h['returned']) : '—' }}</td></tr>
					@empty
						<tr><td colspan="7" class="crm-empty">Sin compras registradas con este cliente.</td></tr>
					@endforelse
					</tbody>
				</table></div>
			</section>

			<section class="ded-card" id="solicitudes">
				<div class="ded-card-header"><span class="ded-card-title">Solicitudes comerciales</span></div>
				<div class="table-responsive"><table class="table crm-table crm-table-compact">
					<thead><tr><th>Código</th><th>Asunto</th><th>Categoría</th><th>Prioridad</th><th>Responsable</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
					<tbody>
					@forelse($requests as $r)
						<tr @if($r['overdue']) class="crm-row-overdue" @endif><td><strong>{{ $r['code'] }}</strong></td><td>{{ $r['subject'] }}@if($r['resolution'])<div class="crm-meta">{{ $r['resolution'] }}</div>@endif</td>
							<td>{{ $CRM::REQUEST_CATEGORIES[$r['category']] ?? $r['category'] }}</td><td><span class="crm-pill crm-prio-{{ $r['priority'] }}">{{ $CRM::REQUEST_PRIORITIES[$r['priority']] }}</span></td>
							<td>{{ $r['assigned_to'] ?: 'Sin asignar' }}</td><td>{{ $r['due_date'] ?: '—' }}</td><td>{{ $CRM::REQUEST_STATUSES[$r['status']] }}</td>
							<td class="text-right"><button type="button" class="crm-link" data-request="{{ json_encode(array_intersect_key($r, array_flip(['id', 'code', 'subject', 'status', 'assigned_user_id', 'due_date', 'resolution']))) }}">Gestionar</button></td></tr>
					@empty
						<tr><td colspan="8" class="crm-empty">Sin solicitudes.</td></tr>
					@endforelse
					</tbody>
				</table></div>
			</section>

			<section class="ded-card" id="atencion">
				<div class="ded-card-header"><span class="ded-card-title">Historial de atención</span></div>
				<form id="crm-interaction" class="crm-form-grid">
					<label>Tipo<select name="kind">@foreach($kindLabels as $k => $l)@if($k !== 'request')<option value="{{ $k }}">{{ $l }}</option>@endif @endforeach</select></label>
					<label>Solicitud relacionada<select name="request_id"><option value="">—</option>@foreach($requests as $r)<option value="{{ $r['id'] }}">{{ $r['code'] }} · {{ $r['subject'] }}</option>@endforeach</select></label>
					<label class="crm-span-2">Resumen<textarea name="summary" rows="2" minlength="5" maxlength="2000" required></textarea></label>
					<label class="crm-span-2">Resultado / próximo paso<input type="text" name="outcome" maxlength="300"></label>
					<button type="submit" class="ded-select-btn">Registrar contacto</button>
				</form>
				<ul class="crm-timeline">
					@forelse($interactions as $i)
						<li><span class="crm-meta">{{ substr($i['created_at'], 0, 16) }} · {{ $kindLabels[$i['kind']] ?? $i['kind'] }} · {{ $i['username'] ?? '—' }}</span><div>{{ $i['summary'] }}</div>@if($i['outcome'])<div class="crm-meta">→ {{ $i['outcome'] }}</div>@endif</li>
					@empty
						<li class="crm-empty">Sin contactos registrados.</li>
					@endforelse
				</ul>
			</section>

			@if($audit !== null)
			<section class="ded-card">
				<div class="ded-card-header"><span class="ded-card-title">Trazabilidad de la ficha</span></div>
				<details class="crm-details"><summary>Últimos {{ count($audit) }} registros</summary>
					<table class="table crm-table crm-table-compact"><tbody>
						@foreach($audit as $a)<tr><td>{{ $a['created_at'] }}</td><td>{{ $a['username'] ?? '—' }}</td><td>{{ $a['action'] }}</td><td class="crm-meta crm-mono">{{ $a['detail'] }}</td></tr>@endforeach
					</tbody></table>
				</details>
			</section>
			@endif
		</div>
	</div>
</div>

@include('components.crm_modals', ['customersForSelect' => [], 'branches' => $branches, 'users' => $users, 'canPii' => $canPii, 'fixedCustomerId' => $c['id']])

<script type="application/json" id="crm-boot">{!! json_encode([
	'canManage' => $canManage, 'canPii' => $canPii, 'customerId' => intval($c['id']), 'monthly' => $monthly,
	'customer' => $canManage ? array_intersect_key($c, array_flip(['id', 'name', 'customer_type', 'branch_id', 'tax_id', 'phone', 'email', 'birth_date', 'address', 'tags', 'notes', 'credit_limit'])) : null
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script src="{{ $U('/js/daily_customers.js?v=', true) }}{{ daily_asset_version('/js/daily_customers.js') }}"></script>
@endpush
