@extends('layout.default')

@section('title', 'Suscripción y planes')

@section('content')
@php
	use Grocy\Helpers\DailySubscription as DS;
	$o = $overview;
	$s = $o['subscription'];
	$statusLabels = ['active' => 'Activa', 'trial' => 'Prueba', 'suspended' => 'Suspendida', 'expired' => 'Vencida', 'cancelled' => 'Cancelada'];
	$eventLabels = ['activated' => 'Activación', 'upgrade' => 'Ampliación', 'downgrade' => 'Reducción', 'status_changed' => 'Cambio de estado', 'dates_changed' => 'Cambio de fechas', 'upgrade_requested' => 'Solicitud de ampliación', 'upgrade_rejected' => 'Solicitud rechazada', 'unchanged' => 'Sin cambio'];
	$reqLabels = ['pending' => 'Pendiente', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'cancelled' => 'Cancelada'];
	$d = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
	$mine = DS::PlanRank($s['contracted_plan']);
	$asked = is_string($_GET['plan'] ?? null) ? $_GET['plan'] : null;
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-gem"></i></div>
		<div>
			<h4 class="dairy-page-title">Suscripción y planes</h4>
			<p class="dairy-page-subtitle"><span class="notranslate">{{ $o['company']['name'] }}</span> · El plan define qué funcionalidades ofrece DAILY; el rol de cada usuario define qué acciones ejecuta dentro de ellas.</p>
		</div>
	</div>
</div>

@if($s['preview'])
<div class="alert alert-warning d-flex align-items-center"><i class="fa-solid fa-eye mr-2"></i><span>Vista previa del plan <strong class="notranslate">{{ $s['plan_name'] }}</strong>. Tu plan contratado es <strong class="notranslate">{{ DS::PlanName($s['contracted_plan']) }}</strong>.</span><a class="ml-auto font-weight-bold" href="{{ $U('/daily/set-plan?plan=') }}">Salir de la vista previa</a></div>
@endif

<div class="dsub-status mb-4" data-testid="subscription-status">
	<div class="dsub-stat"><div class="k">Plan contratado</div><div class="v notranslate" data-testid="current-plan">{{ DS::PlanName($s['contracted_plan']) }}</div></div>
	<div class="dsub-stat"><div class="k">Estado</div><div class="v"><span class="dsub-state {{ $s['active'] ? 'is-on' : 'is-off' }}">{{ $statusLabels[$s['status']] ?? $s['status'] }}</span></div></div>
	<div class="dsub-stat"><div class="k">Activación</div><div class="v">{{ $d($s['activated_at']) }}</div></div>
	<div class="dsub-stat"><div class="k">{{ $s['expires_at'] ? 'Vencimiento' : 'Renovación' }}</div><div class="v">{{ $d($s['expires_at'] ?: $s['renews_at']) }}</div></div>
	<div class="dsub-stat"><div class="k">Funcionalidades</div><div class="v">{{ count($o['capabilities']) }} / {{ count(DS::CAPABILITIES) }}</div></div>
</div>
@if($s['scope'] === 'user')
<p class="small text-muted mb-4"><i class="fa-solid fa-flask mr-1"></i>Cuenta de evaluación: usa su propio plan, independiente del plan de la empresa.</p>
@endif

<h5 class="font-weight-bold mb-3" id="planes">Comparación de planes</h5>
<div class="dsub-plans mb-2">
	@foreach($o['plans'] as $i => $p)
	<div class="dsub-plan @if($p['current']) is-current @endif" data-plan="{{ $p['id'] }}">
		<div class="d-flex align-items-center">
			<div class="dsub-plan-icon"><i class="fa-solid {{ $p['icon'] }}"></i></div>
			@if($p['current'])<span class="dsub-current-pill">Tu plan</span>@endif
		</div>
		<h3 class="notranslate">{{ $p['name'] }}</h3>
		<div class="tag">{{ $p['tagline'] }}</div>
		<ul>
			@if($i > 0)<li class="inherits">Todo lo de <span class="notranslate">&nbsp;{{ $o['plans'][$i - 1]['name'] }}</span>, además:</li>@endif
			@foreach($p['added'] as $c)
			<li><i class="fa-solid fa-check"></i><span>{{ $c['label'] }}</span></li>
			@endforeach
		</ul>
		<div class="quote"><i class="fa-solid fa-file-signature mr-1"></i>Precio por cotización</div>
		@if($p['current'])
		<button class="btn btn-sm btn-outline-secondary" disabled>Plan actual</button>
		@elseif($o['can_request'])
		<button class="btn btn-sm {{ DS::PlanRank($p['id']) > $mine ? 'dsub-btn-primary' : 'btn-outline-secondary' }} dsub-request-btn" data-plan="{{ $p['id'] }}">{{ DS::PlanRank($p['id']) > $mine ? 'Solicitar ampliación' : 'Solicitar cambio' }}</button>
		@endif
	</div>
	@endforeach
</div>
<p class="small text-muted mb-4">Todos los planes se comercializan mediante cotización. Al reducir el plan no se borra información: los módulos no contratados quedan restringidos y sus datos se conservan.</p>

@if($o['can_request'])
<div class="card mb-4 d-none" id="solicitar">
	<div class="card-header font-weight-bold"><i class="fa-solid fa-paper-plane mr-2"></i>Solicitud de cambio de plan</div>
	<div class="card-body">
		<form id="dsub-request-form" class="row">
			<div class="col-md-3 form-group">
				<label for="dsub-req-plan">Plan</label>
				<select id="dsub-req-plan" name="plan" class="form-control">
					@foreach(DS::PLANS as $pp)@if($pp !== $s['contracted_plan'])<option value="{{ $pp }}" @if($pp === $asked) selected @endif>{{ DS::PlanName($pp) }}</option>@endif @endforeach
				</select>
			</div>
			<div class="col-md-4 form-group">
				<label for="dsub-req-contact">Contacto</label>
				<input id="dsub-req-contact" name="contact" class="form-control" maxlength="200" placeholder="Correo o teléfono">
			</div>
			<div class="col-md-5 form-group">
				<label for="dsub-req-msg">Comentario</label>
				<input id="dsub-req-msg" name="message" class="form-control" maxlength="1000" placeholder="Sucursales, usuarios o necesidad">
			</div>
			<div class="col-12"><button type="submit" class="btn dsub-btn-primary">Enviar solicitud</button></div>
		</form>
	</div>
</div>
@endif

@if($o['can_manage'])
<div class="card mb-4" id="administrar">
	<div class="card-header font-weight-bold"><i class="fa-solid fa-sliders mr-2"></i>Confirmar cambio de suscripción</div>
	<div class="card-body">
		<form id="dsub-change-form" class="row">
			<div class="col-md-3 form-group">
				<label for="dsub-chg-plan">Plan</label>
				<select id="dsub-chg-plan" name="plan" class="form-control">
					@foreach(DS::PLANS as $pp)<option value="{{ $pp }}" @if($pp === $s['contracted_plan']) selected @endif>{{ DS::PlanName($pp) }}</option>@endforeach
				</select>
			</div>
			<div class="col-md-3 form-group">
				<label for="dsub-chg-status">Estado</label>
				<select id="dsub-chg-status" name="status" class="form-control">
					@foreach($statusLabels as $k => $v)<option value="{{ $k }}" @if($k === $s['status']) selected @endif>{{ $v }}</option>@endforeach
				</select>
			</div>
			<div class="col-md-3 form-group">
				<label for="dsub-chg-renews">Renovación</label>
				<input id="dsub-chg-renews" type="date" name="renews_at" class="form-control" value="{{ $s['renews_at'] }}">
			</div>
			<div class="col-md-3 form-group">
				<label for="dsub-chg-expires">Vencimiento</label>
				<input id="dsub-chg-expires" type="date" name="expires_at" class="form-control" value="{{ $s['expires_at'] }}">
			</div>
			<div class="col-md-9 form-group">
				<label for="dsub-chg-note">Nota</label>
				<input id="dsub-chg-note" name="note" class="form-control" maxlength="500" placeholder="Referencia de la cotización o contrato">
			</div>
			<div class="col-md-3 form-group d-flex align-items-end"><button type="submit" class="btn dsub-btn-primary btn-block">Confirmar cambio</button></div>
		</form>
	</div>
</div>
@endif

<div class="card mb-4">
	<div class="card-header font-weight-bold"><i class="fa-solid fa-table-list mr-2"></i>Funcionalidades por plan</div>
	<div class="card-body p-0 table-responsive">
		<table class="table table-sm mb-0 dsub-matrix">
			<thead><tr><th>Funcionalidad</th><th class="d-none d-lg-table-cell">Identificador</th>@foreach(DS::PLANS as $pp)<th class="text-center notranslate">{{ DS::PlanName($pp) }}</th>@endforeach</tr></thead>
			<tbody>
				@php $lastGroup = null; @endphp
				@foreach($o['matrix'] as $row)
				@if($row['group'] !== $lastGroup)
				<tr class="dsub-group"><td colspan="{{ 2 + count(DS::PLANS) }}">{{ $row['group'] }}</td></tr>
				@php $lastGroup = $row['group']; @endphp
				@endif
				<tr class="@if(in_array($row['id'], $o['capabilities'], true)) is-mine @endif">
					<td>{{ $row['label'] }}</td>
					<td class="d-none d-lg-table-cell"><code class="notranslate">{{ $row['id'] }}</code></td>
					@foreach(DS::PLANS as $pp)
					<td class="{{ $row[$pp] ? 'ok' : 'no' }}"><i class="fa-solid {{ $row[$pp] ? 'fa-check' : 'fa-minus' }}" aria-label="{{ $row[$pp] ? 'Incluido' : 'No incluido' }}"></i></td>
					@endforeach
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>

<div class="row">
	<div class="col-lg-6">
		<div class="card mb-4">
			<div class="card-header font-weight-bold"><i class="fa-solid fa-inbox mr-2"></i>Solicitudes</div>
			<div class="card-body p-0 table-responsive">
				<table class="table table-sm mb-0" id="dsub-requests">
					<thead><tr><th>#</th><th>Fecha</th><th>Solicitado</th><th>Usuario</th><th>Estado</th>@if($o['can_manage'])<th></th>@endif</tr></thead>
					<tbody>
						@forelse($o['requests'] as $r)
						<tr>
							<td>{{ $r['id'] }}</td>
							<td>{{ $d($r['row_created_timestamp']) }}</td>
							<td class="notranslate">{{ DS::PlanName($r['current_plan']) }} → {{ DS::PlanName($r['requested_plan']) }}</td>
							<td class="notranslate">{{ $r['requested_by_name'] }}</td>
							<td>{{ $reqLabels[$r['status']] ?? $r['status'] }}</td>
							@if($o['can_manage'])
							<td class="text-nowrap">@if($r['status'] === 'pending')<button class="btn btn-xs btn-sm py-0 dsub-btn-primary dsub-decide" data-id="{{ $r['id'] }}" data-decision="approved">Aprobar</button> <button class="btn btn-sm py-0 btn-outline-secondary dsub-decide" data-id="{{ $r['id'] }}" data-decision="rejected">Rechazar</button>@endif</td>
							@endif
						</tr>
						@empty
						<tr><td colspan="6" class="text-muted text-center py-3">Sin solicitudes</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card mb-4">
			<div class="card-header font-weight-bold"><i class="fa-solid fa-clock-rotate-left mr-2"></i>Historial de cambios</div>
			<div class="card-body p-0 table-responsive">
				<table class="table table-sm mb-0" id="dsub-history">
					<thead><tr><th>Fecha</th><th>Evento</th><th>Plan</th><th>Nota</th></tr></thead>
					<tbody>
						@forelse($o['history'] as $h)
						<tr>
							<td class="text-nowrap">{{ $d($h['row_created_timestamp']) }}</td>
							<td>{{ $eventLabels[$h['event']] ?? $h['event'] }}@if($h['scope'] === 'user') <small class="text-muted">(evaluación)</small>@endif</td>
							<td class="notranslate">{{ $h['from_plan'] ? DS::PlanName($h['from_plan']) . ' → ' : '' }}{{ $h['to_plan'] ? DS::PlanName($h['to_plan']) : '' }}@if($h['to_status'] && $h['from_status'] !== $h['to_status']) · {{ $statusLabels[$h['to_status']] ?? $h['to_status'] }}@endif</td>
							<td class="small">{{ $h['note'] }}</td>
						</tr>
						@empty
						<tr><td colspan="4" class="text-muted text-center py-3">Sin cambios registrados</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@stop

@push('pageScripts')
<script>
(function()
{
	var t = function(s) { return (window.DailyI18n && DailyI18n.t) ? DailyI18n.t(s) : s; };
	var post = function(url, body)
	{
		return fetch(U('/api' + url), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
			.then(function(r) { return r.json().then(function(j) { if (!r.ok) { throw new Error(j.error_message || r.statusText); } return j; }); });
	};
	var fail = function(e) { toastr.error(t(e.message)); };
	var form = document.getElementById('dsub-request-form');
	var box = document.getElementById('solicitar');
	var openRequest = function(plan)
	{
		if (!box) { return; }
		box.classList.remove('d-none');
		if (plan) { document.getElementById('dsub-req-plan').value = plan; }
		box.scrollIntoView({ behavior: 'smooth', block: 'center' });
	};
	document.querySelectorAll('.dsub-request-btn').forEach(function(b) { b.addEventListener('click', function() { openRequest(b.dataset.plan); }); });
	if (new URLSearchParams(location.search).get('plan')) { openRequest(new URLSearchParams(location.search).get('plan')); }
	if (form)
	{
		form.addEventListener('submit', function(e)
		{
			e.preventDefault();
			post('/subscription/request', Object.fromEntries(new FormData(form))).then(function(r)
			{
				toastr.success(r.duplicate ? t('Ya existe una solicitud pendiente para ese plan.') : t('Solicitud enviada. Te contactaremos con la cotización.'));
				setTimeout(function() { location.reload(); }, 900);
			}).catch(fail);
		});
	}
	var change = document.getElementById('dsub-change-form');
	if (change)
	{
		change.addEventListener('submit', function(e)
		{
			e.preventDefault();
			post('/subscription/change', Object.fromEntries(new FormData(change))).then(function()
			{
				toastr.success(t('Suscripción actualizada. Los permisos ya se aplicaron.'));
				setTimeout(function() { location.reload(); }, 700);
			}).catch(fail);
		});
	}
	document.querySelectorAll('.dsub-decide').forEach(function(b)
	{
		b.addEventListener('click', function()
		{
			b.disabled = true;
			post('/subscription/requests/' + b.dataset.id + '/decide', { decision: b.dataset.decision }).then(function() { location.reload(); }).catch(function(e) { b.disabled = false; fail(e); });
		});
	});
	function U(p) { return (Grocy.BaseUrl || '/').replace(/\/$/, '') + p; }
})();
</script>
@endpush
