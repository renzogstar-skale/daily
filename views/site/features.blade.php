@extends('site.layout')

@section('content')
@include('site.partials.pagehero', ['title' => $L('Todo lo que tu operación necesita, conectado', 'Everything your operation needs, connected'), 'eyebrow' => $L('Funcionalidades', 'Features'), 'sub' => $L('Ocho módulos que comparten productos, sucursales, personal y movimientos. Elige uno para ver el detalle.', 'Eight modules sharing products, branches, staff and movements. Pick one to see the details.'), 'crumbs' => [[$L('Funcionalidades', 'Features'), null]]])

<nav class="s-chips s-wrap" aria-label="{{ $L('Módulos', 'Modules') }}">
	@foreach($c['modules'] as $slug => $m)
	<a href="#m-{{ $slug }}"><svg class="s-ico s-ico--sm"><use href="#i-{{ $m['icon'] }}"/></svg>{{ $m['name'] }}</a>
	@endforeach
</nav>

@foreach($c['modules'] as $slug => $m)
<section class="s-section s-feat{{ $loop->even ? ' s-feat--alt' : '' }}" id="m-{{ $slug }}" aria-labelledby="h-{{ $slug }}">
	<div class="s-wrap s-feat__grid">
		<div class="s-feat__text" data-reveal>
			<span class="s-badge s-badge--{{ $m['tone'] }}"><svg class="s-ico"><use href="#i-{{ $m['icon'] }}"/></svg></span>
			<h2 id="h-{{ $slug }}">{{ $m['name'] }}</h2>
			<p class="s-lead">{{ $m['tagline'] }}</p>
			<ul class="s-checks s-checks--cols">
				@foreach($m['features'] as $f)
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $f[0] }}</li>
				@endforeach
			</ul>
			<div class="s-actions">
				<a class="s-btn s-btn--primary" href="{{ $U('/features/' . $slug) }}">{{ $L('Ver detalle', 'See details') }}<svg class="s-ico"><use href="#i-arrow"/></svg></a>
				<a class="s-btn s-btn--ghost" href="{{ $U('/demo') }}">{{ $L('Pedir demo', 'Get a demo') }}</a>
			</div>
		</div>
		<div data-reveal>@include('site.partials.shot', ['img' => $m['img'], 'alt' => $L('Pantalla de DAILY: ', 'DAILY screen: ') . $m['name']])</div>
	</div>
</section>
@endforeach
@endsection
