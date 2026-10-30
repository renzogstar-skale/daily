{{-- Franja de alertas del módulo: lee el registro único del Centro de Alertas (mismo ALR-id; no crea copias).
     Parámetros: $alertCategories (array de categorías), $alertTitle (opcional), $alertBranch (opcional). --}}
@php
	$__canAlerts = \Grocy\Helpers\DairyAccess::CanOpen('/alerts');
	$__alerts = null;
	if ($__canAlerts) {
		try {
			$__svc = \Grocy\Services\AlertCenterService::GetInstance();
			$__f = ['allowed' => \Grocy\Helpers\DairyAccess::AllowedBranches(), 'category' => implode(',', $alertCategories ?? []), 'branch_id' => $alertBranch ?? null];
			$__alerts = $__svc->List(array_filter($__f, fn($v) => $v !== null && $v !== ''), 'all', 1, 3, 'priority');
		} catch (\Throwable $e) { $__alerts = null; }
	}
	$__q = http_build_query(array_filter(['category' => count($alertCategories ?? []) === 1 ? $alertCategories[0] : null, 'branch_id' => $alertBranch ?? null]));
@endphp
@if($__alerts && $__alerts['counts']['all'] > 0)
<div class="dairy-alert-strip" role="region" aria-label="Alertas del módulo">
	<div class="dairy-alert-strip-head">
		<span><i class="fa-solid fa-bell"></i> <b>{{ $alertTitle ?? 'Alertas activas del módulo' }}:</b> {{ $__alerts['counts']['all'] }}
			@if($__alerts['counts']['P1']) · <span class="dairy-as-p1">{{ $__alerts['counts']['P1'] }} críticas</span>@endif
			@if($__alerts['counts']['overdue']) · {{ $__alerts['counts']['overdue'] }} vencidas @endif
			@if($__alerts['counts']['unassigned']) · {{ $__alerts['counts']['unassigned'] }} sin responsable @endif</span>
		<a href="{{ $U('/alerts') }}?{{ $__q }}#alc-list">Abrir en el Centro de Alertas <i class="fa-solid fa-arrow-right"></i></a>
	</div>
	<ul>
		@foreach($__alerts['rows'] as $__a)
		<li><a href="{{ $U('/alerts') }}?{{ $__q }}#alr-{{ $__a['id'] }}"><span class="dairy-as-prio p-{{ $__a['priority'] }}">{{ $__a['priority'] }}</span> ALR-{{ $__a['id'] }} · {{ $__a['title'] }}@if(intval($__a['overdue'])) <em>· vencida</em>@endif</a></li>
		@endforeach
	</ul>
</div>
@once
<style>
	.dairy-alert-strip { border: 1px solid #E4EAF2; border-left: 4px solid #EF4444; background: #fff; border-radius: 12px; padding: 10px 14px; margin-bottom: 12px; font-size: 12.5px; }
	body.night-mode .dairy-alert-strip { background: #1E293B; border-color: #334155; border-left-color: #EF4444; }
	.dairy-alert-strip-head { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
	.dairy-alert-strip-head i { color: #EF4444; }
	.dairy-alert-strip ul { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-direction: column; gap: 3px; }
	.dairy-alert-strip li a { color: inherit; }
	.dairy-alert-strip em { color: #B91C1C; font-style: normal; font-weight: 600; }
	.dairy-as-p1 { color: #B91C1C; font-weight: 700; }
	.dairy-as-prio { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 1px 6px; border-radius: 5px; background: #EFF6FF; color: #1D4ED8; }
	.dairy-as-prio.p-P1 { background: #FEF0F0; color: #B91C1C; }
	.dairy-as-prio.p-P2 { background: #FFF1E7; color: #C2410C; }
	.dairy-as-prio.p-P4 { background: #F1F5F9; color: #64748B; }
</style>
@endonce
@endif
