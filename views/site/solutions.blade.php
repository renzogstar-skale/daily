@extends('site.layout')

@section('content')
@include('site.partials.pagehero', ['title' => $L('Soluciones para los problemas que más cuestan', 'Solutions for the problems that cost the most'), 'eyebrow' => $L('Soluciones', 'Solutions'), 'sub' => $L('Empieza por la necesidad más urgente; todo queda conectado en la misma plataforma.', 'Start with your most urgent need; everything stays connected on the same platform.'), 'crumbs' => [[$L('Soluciones', 'Solutions'), null]]])

<section class="s-section">
	<div class="s-wrap s-sol">
		@foreach($c['solutions'] as $s)
		@php $m = $c['modules'][$s['module']]; @endphp
		<article class="s-sol__card" data-reveal>
			@include('site.partials.shot', ['img' => $m['img'], 'alt' => $m['name']])
			<div class="s-sol__body">
				<span class="s-badge s-badge--{{ $m['tone'] }}"><svg class="s-ico"><use href="#i-{{ $s['icon'] }}"/></svg></span>
				<h2>{{ $s['title'] }}</h2>
				<p>{{ $s['text'] }}</p>
				<ul class="s-checks s-checks--sm">@foreach(array_slice($m['features'], 0, 3) as $f)<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $f[0] }}</li>@endforeach</ul>
				<a class="s-link" href="{{ $U('/features/' . $s['module']) }}">{{ $m['name'] }}<svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></a>
			</div>
		</article>
		@endforeach
	</div>
</section>
@include('site.partials.tour')
@endsection
