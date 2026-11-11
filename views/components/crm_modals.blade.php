@php
	$CRM = \Grocy\Services\CustomerCrmService::class;
	$reg = $regional ?? $CRM::GetInstance()->RegionalConfig();
@endphp
{{-- Modales del CRM de clientes: alta de cliente, nueva solicitud, gestión, aprobación financiera y campaña (daily_customers.js) --}}
<div class="modal fade crm-modal" id="crm-new-customer" tabindex="-1" role="dialog" aria-labelledby="crm-new-customer-title" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document"><form class="modal-content" id="crm-customer-form" novalidate>
		<div class="modal-header"><h2 class="modal-title" id="crm-new-customer-title">Nuevo cliente</h2><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
		<div class="modal-body">
			<p class="crm-meta">Registre al menos un identificador (documento, correo o teléfono). El sistema compara con la base para evitar duplicados.</p>
			<div class="crm-form-grid">
				<label class="crm-span-2">Nombre o razón social *<input type="text" name="name" required minlength="3" maxlength="150" autocomplete="off" placeholder="Ej. Supermercado La Fontana S.A.S."></label>
				<label>Tipo<select name="customer_type">@foreach(['Minorista', 'Comercial', 'Corporativo', 'Institucional'] as $t)<option>{{ $t }}</option>@endforeach</select></label>
				<label>Sucursal de referencia<select name="branch_id"><option value="">—</option>@foreach($branches as $b)<option value="{{ is_object($b) ? $b->id : $b['id'] }}">{{ is_object($b) ? $b->name : $b['name'] }}</option>@endforeach</select></label>
				<label>{{ $reg['tax_id_label'] }}<input type="text" name="tax_id" maxlength="30" autocomplete="off" placeholder="{{ $reg['tax_id_placeholder'] }}"></label>
				<label>Teléfono<input type="tel" name="phone" maxlength="30" autocomplete="off" placeholder="{{ $reg['phone_placeholder'] }}"></label>
				<label>Correo<input type="email" name="email" maxlength="120" autocomplete="off" placeholder="contacto@cliente.com"></label>
				<label>Fecha de nacimiento<input type="date" name="birth_date"></label>
				<label class="crm-span-2">Dirección<input type="text" name="address" maxlength="250" autocomplete="off"></label>
				<label>Etiquetas<input type="text" name="tags" maxlength="200" placeholder="separadas por coma"></label>
				<label>Límite de crédito ({{ $reg['currency_code'] }})<input type="number" name="credit_limit" min="0" step="0.01" placeholder="0.00"></label>
				<label class="crm-span-2">Notas internas<textarea name="notes" rows="2" maxlength="1000"></textarea></label>
				<div class="crm-span-2 crm-new-only crm-form-grid">
					<label class="crm-check crm-span-2"><input type="checkbox" name="consent_data_processing" value="1"> El cliente autoriza el tratamiento de sus datos personales</label>
					<label>Origen del consentimiento<select name="consent_source">@foreach($CRM::CONSENT_SOURCES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
				</div>
			</div>
			<div class="crm-dup-warning" hidden role="alert"></div>
		</div>
		<div class="modal-footer"><button type="button" class="ded-select-btn" data-dismiss="modal">Cancelar</button><button type="submit" class="ded-select-btn crm-btn-primary">Guardar cliente</button></div>
	</form></div>
</div>

<div class="modal fade crm-modal" id="crm-new-request" tabindex="-1" role="dialog" aria-labelledby="crm-new-request-title" aria-hidden="true">
	<div class="modal-dialog" role="document"><form class="modal-content" id="crm-request-form" novalidate>
		<div class="modal-header"><h2 class="modal-title" id="crm-new-request-title">Nuevo caso o solicitud</h2><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
		<div class="modal-body crm-form-grid">
			@if($fixedCustomerId)
				<input type="hidden" name="customer_id" value="{{ $fixedCustomerId }}">
			@else
				<label class="crm-span-2">Cliente *<select name="customer_id" required><option value="">Elija un cliente…</option>@foreach($customersForSelect as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach</select></label>
			@endif
			<label>Categoría *<select name="category" id="crm-req-category" required>@foreach($CRM::REQUEST_CATEGORIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
			<label>Prioridad<select name="priority">@foreach($CRM::REQUEST_PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($k === 'normal')>{{ $l }}</option>@endforeach</select></label>
			<label class="crm-span-2">Asunto *<input type="text" name="subject" required minlength="5" maxlength="200" placeholder="Resumen breve del caso o reclamo"></label>
			<label class="crm-span-2 crm-req-extra crm-req-credit" hidden>Cupo de crédito solicitado ({{ $reg['currency_code'] }})<input type="number" name="requested_credit_limit" min="0" step="0.01" placeholder="Ej. 5000.00"></label>
			<label class="crm-span-2 crm-req-extra crm-req-delivery" hidden>ID de orden / despacho relacionado<input type="number" name="dispatch_order_id" min="1" step="1" placeholder="Opcional · la resolución logística se gestiona en Despachos"></label>
			<label class="crm-span-2">Detalle<textarea name="detail" rows="3" maxlength="2000"></textarea></label>
			<label>Responsable<select name="assigned_user_id"><option value="">Sin asignar</option>@foreach($users as $u)<option value="{{ $u['id'] }}">{{ trim($u['display']) ?: $u['username'] }}</option>@endforeach</select></label>
			<label>Vence<input type="date" name="due_date" title="Vacío: según prioridad (urgente 1 día, alta 2, normal 5, baja 10)"></label>
		</div>
		<div class="modal-footer"><button type="button" class="ded-select-btn" data-dismiss="modal">Cancelar</button><button type="submit" class="ded-select-btn crm-btn-primary">Registrar caso</button></div>
	</form></div>
</div>

<div class="modal fade crm-modal" id="crm-manage-request" tabindex="-1" role="dialog" aria-labelledby="crm-manage-request-title" aria-hidden="true">
	<div class="modal-dialog" role="document"><form class="modal-content" id="crm-manage-form" novalidate>
		<div class="modal-header"><h2 class="modal-title" id="crm-manage-request-title">Gestionar caso</h2><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
		<div class="modal-body crm-form-grid">
			<input type="hidden" name="id">
			<p class="crm-span-2 crm-manage-subject"></p>
			<label>Estado<select name="status">@foreach($CRM::REQUEST_STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
			<label>Responsable<select name="assigned_user_id"><option value="">Sin asignar</option>@foreach($users as $u)<option value="{{ $u['id'] }}">{{ trim($u['display']) ?: $u['username'] }}</option>@endforeach</select></label>
			<label>Vence<input type="date" name="due_date"></label>
			<label>ID despacho vinculado<input type="number" name="dispatch_order_id" min="1" step="1" placeholder="Opcional"></label>
			<label class="crm-span-2">Resolución / avance<textarea name="resolution" rows="3" maxlength="2000" placeholder="Obligatoria para resolver o anular"></textarea></label>
		</div>
		<div class="modal-footer"><button type="button" class="ded-select-btn" data-dismiss="modal">Cancelar</button><button type="submit" class="ded-select-btn crm-btn-primary">Guardar</button></div>
	</form></div>
</div>

<div class="modal fade crm-modal" id="crm-credit-decision" tabindex="-1" role="dialog" aria-labelledby="crm-credit-decision-title" aria-hidden="true">
	<div class="modal-dialog" role="document"><form class="modal-content" id="crm-credit-form" novalidate>
		<div class="modal-header"><h2 class="modal-title" id="crm-credit-decision-title">Aprobación Financiera de Crédito</h2><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
		<div class="modal-body crm-form-grid">
			<input type="hidden" name="id">
			<p class="crm-span-2 crm-credit-subject"></p>
			<p class="crm-span-2 crm-meta mb-1">Separación de funciones: CRM recibe la solicitud, pero solo Administración / Finanzas puede aprobar o rechazar el cupo.</p>
			<label>Decisión financiera *<select name="decision" required><option value="approved_finance">Aprobar crédito</option><option value="rejected_finance">Rechazar crédito</option></select></label>
			<label>Cupo aprobado ({{ $reg['currency_code'] }})<input type="number" name="approved_limit" min="0" step="0.01" placeholder="Opcional"></label>
			<label class="crm-span-2">Sustento financiero *<textarea name="note" rows="3" minlength="5" maxlength="1000" required placeholder="Evaluación de riesgo, historial o condiciones aprobadas"></textarea></label>
		</div>
		<div class="modal-footer"><button type="button" class="ded-select-btn" data-dismiss="modal">Cancelar</button><button type="submit" class="ded-select-btn crm-btn-primary">Confirmar dictamen</button></div>
	</form></div>
</div>

<div class="modal fade crm-modal" id="crm-new-campaign" tabindex="-1" role="dialog" aria-labelledby="crm-new-campaign-title" aria-hidden="true">
	<div class="modal-dialog" role="document"><form class="modal-content" id="crm-campaign-form" novalidate>
		<div class="modal-header"><h2 class="modal-title" id="crm-new-campaign-title">Nueva campaña de retención</h2><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
		<div class="modal-body crm-form-grid">
			<label class="crm-span-2">Nombre de campaña *<input type="text" name="name" required minlength="3" maxlength="120" placeholder="Ej. Reactivación clientes en riesgo 30d"></label>
			<label>Segmento objetivo<select name="target_segment"><option value="all">Todos los identificados</option>@foreach($CRM::SEGMENTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
			<label>Canal<select name="channel">@foreach($CRM::CAMPAIGN_CHANNELS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
			<label>Fecha inicio<input type="date" name="starts_on" value="{{ date('Y-m-d') }}"></label>
			<label>Fecha fin<input type="date" name="ends_on"></label>
			<label>Sucursal<select name="branch_id"><option value="">Todas las sucursales</option>@foreach($branches as $b)<option value="{{ is_object($b) ? $b->id : $b['id'] }}">{{ is_object($b) ? $b->name : $b['name'] }}</option>@endforeach</select></label>
			<label>Estado<select name="status"><option value="active">Activa</option><option value="scheduled">Programada</option><option value="paused">Pausada</option></select></label>
			<label class="crm-span-2">Mensaje u oferta<textarea name="offer_text" rows="2" maxlength="400" placeholder="Beneficio o mensaje comercial para clientes con consentimiento"></textarea></label>
		</div>
		<div class="modal-footer"><button type="button" class="ded-select-btn" data-dismiss="modal">Cancelar</button><button type="submit" class="ded-select-btn crm-btn-primary">Crear campaña</button></div>
	</form></div>
</div>
