@extends('site.layout')

@section('description', $module['summary'])

@section('content')
@include('site.partials.pagehero', ['title' => $module['name'], 'eyebrow' => $L('Módulo', 'Module'), 'sub' => $module['tagline'], 'crumbs' => [[$L('Funcionalidades', 'Features'), '/features'], [$module['name'], null]]])

<section class="s-section s-section--tight">
	<div class="s-wrap">
		<div class="s-feature__hero" data-reveal @if($slug === 'analytics') id="panel-ejecutivo" @endif>
			@if(!empty($module['preview']))
			@include('site.partials.preview', ['key' => $module['preview'], 'alt' => $L('Indicadores y gráficos de DAILY: ', 'DAILY KPIs and charts: ') . $module['name'], 'mode' => 'eager', 'cls' => 's-pv--wide'])
			@else
			@include('site.partials.shot', ['img' => $module['img'], 'alt' => $L('Pantalla de DAILY: ', 'DAILY screen: ') . $module['name'], 'eager' => true])
			@endif
		</div>
		@if($slug === 'analytics')
		<div class="s-kpis" aria-labelledby="pe-title">
			<h2 id="pe-title">{{ $L('Panel Ejecutivo: qué muestra cada indicador', 'Executive panel: what each indicator shows') }}</h2>
			<dl>
				@foreach([
					[$L('Ventas netas', 'Net sales'), $L('Venta del día y variación frente al mismo día y hora de la semana anterior.', 'Today\'s sales and change versus the same weekday and hour last week.')],
					[$L('Cumplimiento de meta', 'Target attainment'), $L('Avance sobre la meta del día.', 'Progress against the day\'s target.')],
					[$L('Margen bruto', 'Gross margin'), $L('Margen sobre la venta neta, en puntos frente al periodo de referencia.', 'Margin on net sales, in points versus the reference period.')],
					[$L('Utilidad bruta', 'Gross profit'), $L('Venta neta menos costo de ventas.', 'Net sales minus cost of sales.')],
					[$L('Ticket promedio', 'Average ticket'), $L('Venta neta dividida entre tickets.', 'Net sales divided by tickets.')],
					[$L('Transacciones', 'Transactions'), $L('Tickets completados del día.', 'Completed tickets for the day.')],
					[$L('Venta por hora laboral', 'Sales per labor hour'), $L('Venta neta sobre las horas programadas del personal.', 'Net sales over scheduled staff hours.')],
					[$L('Merma sobre ventas', 'Shrink as % of sales'), $L('Merma aprobada frente al objetivo.', 'Approved shrink against target.')],
					[$L('Ventas reales vs. meta por hora', 'Actual sales vs. hourly target'), $L('Curva acumulada del día contra la meta y proyección de cierre.', 'Running curve for the day against the target and closing forecast.')],
					[$L('Ventas por departamento', 'Sales by department'), $L('Participación y monto de cada departamento.', 'Share and amount for each department.')],
				] as [$kt, $kd])
				<div><dt>{{ $kt }}</dt><dd>{{ $kd }}</dd></div>
				@endforeach
			</dl>
		</div>
		@endif
	</div>
</section>

<section class="s-section" aria-labelledby="fx-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal>
			<h2 id="fx-title">{{ $L('Qué incluye', 'What\'s included') }}</h2>
			<p>{{ $module['summary'] }}</p>
		</div>
		<div class="s-fx">
			@foreach($module['features'] as [$ft, $fd])
			<div class="s-fx__item" data-reveal><svg class="s-ico"><use href="#i-check"/></svg><div><h3>{{ $ft }}</h3><p>{{ $fd }}</p></div></div>
			@endforeach
		</div>
	</div>
</section>

<section class="s-section s-feat--alt" aria-labelledby="bx-title">
	<div class="s-wrap s-feat__grid">
		<div data-reveal>
			<span class="s-eyebrow">{{ $L('Beneficios', 'Benefits') }}</span>
			<h2 id="bx-title">{{ $L('Lo que gana tu equipo', 'What your team gains') }}</h2>
			<ul class="s-checks s-checks--lg">
				@foreach($module['benefits'] as $b)
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $b }}</li>
				@endforeach
			</ul>
			<div class="s-actions"><a class="s-btn s-btn--primary" href="{{ $U('/demo') }}">{{ $L('Verlo en una demo', 'See it in a demo') }}</a></div>
		</div>
		@if(count($module['gallery']) > 1)
		<div class="s-gallery" data-reveal>
			@foreach(array_slice($module['gallery'], 1, 3) as $g)
			@include('site.partials.shot', ['img' => $g, 'alt' => $L('Otra pantalla del módulo ', 'Another screen of ') . $module['name']])
			@endforeach
		</div>
		@endif
	</div>
</section>

<section class="s-section" aria-labelledby="ox-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal><h2 id="ox-title">{{ $L('Se conecta con', 'Connects with') }}</h2></div>
		<div class="s-mods s-mods--small">
			@foreach($c['modules'] as $s => $m)
			@if($s !== $slug)
			<a class="s-mod s-mod--{{ $m['tone'] }}" href="{{ $U('/features/' . $s) }}" data-reveal><span class="s-badge s-badge--{{ $m['tone'] }}"><svg class="s-ico"><use href="#i-{{ $m['icon'] }}"/></svg></span><h3>{{ $m['name'] }}</h3><span class="s-link">{{ $L('Ver módulo', 'View module') }}<svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></span></a>
			@endif
			@endforeach
		</div>
	</div>
</section>
@endsection
