@extends('layout.default')

@section('title', $inactive ? 'Suscripción inactiva' : 'Requiere un plan superior')

@section('content')
@php
	use Grocy\Helpers\DailySubscription as DS;
	$required = $lock['required_plan'] ?? null;
	// Lo que se habilita al pasar del plan actual al requerido
	$gained = [];
	if ($required)
	{
		foreach (DS::PLANS as $p)
		{
			if (DS::PlanRank($p) > DS::PlanRank($lock['current_plan']) && DS::PlanRank($p) <= DS::PlanRank($required))
			{
				foreach (DS::AddedIn($p) as $c)
				{
					$gained[] = DS::L(DS::CAPABILITIES[$c]);
				}
			}
		}
	}
	$home = \Grocy\Helpers\DairyAccess::CanOpen('/today') && DS::Has('today.view') ? '/today' : '/stockoverview';
@endphp
<div class="dsub-lock"
	data-testid="plan-required"
	data-required-plan="{{ $required }}"
	data-capability="{{ $lock['capability'] ?? '' }}">
	<div class="dsub-lock-card">
		<div class="dsub-lock-icon"><i class="fa-solid {{ $inactive ? 'fa-pause' : 'fa-lock' }}"></i></div>
		@if($inactive)
		<h1>La suscripción no está activa</h1>
		<p class="lead">DAILY quedó en modo consulta: puedes revisar la información, pero las operaciones de registro y cambio están detenidas hasta renovar. Tus datos se conservan íntegros.</p>
		@else
		<h1>Esta funcionalidad requiere el plan <span class="notranslate">{{ $lock['required_plan_name'] }}</span></h1>
		<p class="lead"><strong>{{ $lock['capability_label'] }}</strong> no está incluida en tu plan actual. Tus datos y el resto de DAILY siguen disponibles con normalidad.</p>
		<div class="dsub-lock-meta">
			<span class="dsub-chip"><i class="fa-solid fa-circle-check"></i>Plan actual: <strong class="notranslate">{{ $lock['current_plan_name'] }}</strong></span>
			<span class="dsub-chip is-required"><i class="fa-solid fa-gem"></i>Requiere: <span class="notranslate">{{ $lock['required_plan_name'] }}</span></span>
		</div>
		@if(count($gained) > 0)
		<p class="small text-uppercase font-weight-bold mb-2" style="letter-spacing: .05em; color: var(--dsub-muted);">Con <span class="notranslate">{{ $lock['required_plan_name'] }}</span> se habilita también</p>
		<ul class="dsub-lock-list">
			@foreach($gained as $g)
			<li><i class="fa-solid fa-check"></i><span>{{ $g }}</span></li>
			@endforeach
		</ul>
		@endif
		@endif
		<div class="dsub-actions">
			<a class="btn dsub-btn-primary"
				href="{{ $U('/subscription') }}{{ $required ? '?plan=' . $required : '' }}#planes"
				data-testid="see-plans"><i class="fa-solid fa-table-columns mr-2"></i>Ver planes</a>
			<a class="btn btn-outline-secondary"
				href="{{ $U($home) }}"><i class="fa-solid fa-arrow-left mr-2"></i>Volver a mi trabajo</a>
		</div>
	</div>
</div>
@stop
