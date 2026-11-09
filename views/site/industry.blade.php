@extends('site.layout')

@section('description', $industry['lead'])

@section('content')
@include('site.partials.pagehero', ['title' => $L('DAILY para ', 'DAILY for ') . $industry['name'], 'eyebrow' => $L('Industrias', 'Industries'), 'sub' => $industry['lead'], 'crumbs' => [[$L('Industrias', 'Industries'), '/industries'], [$industry['name'], null]]])

<section class="s-section" aria-labelledby="pb-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal><h2 id="pb-title">{{ $L('Problemas que resuelve', 'Problems it solves') }}</h2></div>
		<div class="s-probs">
			@foreach($industry['problems'] as $i => [$pt, $pd])
			<div class="s-prob" data-reveal><span class="s-prob__n">0{{ $i + 1 }}</span><h3>{{ $pt }}</h3><p>{{ $pd }}</p></div>
			@endforeach
		</div>
	</div>
</section>

<section class="s-section s-feat--alt">
	<div class="s-wrap s-feat__grid">
		<div data-reveal>
			<span class="s-eyebrow">{{ $L('Módulos recomendados', 'Recommended modules') }}</span>
			<h2>{{ $L('Cómo se arma DAILY para ', 'How DAILY is set up for ') }}{{ mb_strtolower($industry['name']) }}</h2>
			<div class="s-modlist">
				@foreach($industry['modules'] as $ms)
				@php $m = $c['modules'][$ms]; @endphp
				<a href="{{ $U('/features/' . $ms) }}"><span class="s-badge s-badge--{{ $m['tone'] }}"><svg class="s-ico"><use href="#i-{{ $m['icon'] }}"/></svg></span><span><strong>{{ $m['name'] }}</strong><small>{{ $m['summary'] }}</small></span><svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></a>
				@endforeach
			</div>
		</div>
		<div data-reveal>@include('site.partials.shot', ['img' => $industry['img'], 'alt' => $industry['name']])</div>
	</div>
</section>

<section class="s-section" aria-labelledby="ld-title">
	<div class="s-wrap s-demo__grid">
		<div data-reveal>
			<h2 id="ld-title">{{ $L('Pide una demostración para ', 'Request a demo for ') }}{{ mb_strtolower($industry['name']) }}</h2>
			<p>{{ $L('Te mostramos DAILY con los procesos de tu operación.', 'We show you DAILY with your own operation\'s processes.') }}</p>
		</div>
		<div class="s-card" data-reveal>@include('site.partials.lead_form', ['type' => 'demo', 'preset' => $slug])</div>
	</div>
</section>
@endsection
