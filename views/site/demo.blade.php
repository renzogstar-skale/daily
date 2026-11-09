@extends('site.layout')

@section('content')
<section class="s-phero s-phero--form">
	<div class="s-hero__bg" aria-hidden="true"><i></i><i></i></div>
	<div class="s-wrap s-demo__grid">
		<div>
			<nav class="s-crumbs" aria-label="{{ $L('Ruta', 'Breadcrumb') }}"><a href="{{ $U('/') }}">{{ $L('Inicio', 'Home') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $L('Demostración', 'Demo') }}</span></nav>
			<span class="s-eyebrow">{{ $L('Solicitar demostración', 'Request a demo') }}</span>
			<h1>{{ $L('Ve DAILY funcionando con tus procesos', 'See DAILY working with your processes') }}</h1>
			<p class="s-hero__sub">{{ $L('Cuéntanos sobre tu operación y preparamos una demostración enfocada en lo que más te importa.', 'Tell us about your operation and we will prepare a demo focused on what matters most to you.') }}</p>
			<ul class="s-checks s-checks--lg">
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $L('Recorrido por los módulos que elijas', 'Walkthrough of the modules you choose') }}</li>
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $L('Preguntas sobre implementación y datos', 'Questions on implementation and data') }}</li>
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $L('Propuesta según sucursales y módulos', 'Proposal by branches and modules') }}</li>
			</ul>
			@include('site.partials.shot', ['img' => 'today', 'alt' => $L('Panel HOY de DAILY', 'DAILY TODAY panel'), 'cls' => 's-shot--demo'])
		</div>
		<div class="s-card s-card--form">
			<h2>{{ $L('Datos de contacto', 'Contact details') }}</h2>
			@include('site.partials.lead_form', ['type' => 'demo'])
		</div>
	</div>
</section>
@endsection
