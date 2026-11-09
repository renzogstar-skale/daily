<section class="s-phero{{ isset($tone) ? ' s-phero--' . $tone : '' }}">
	<div class="s-hero__bg" aria-hidden="true"><i></i><i></i></div>
	<div class="s-wrap">
		<nav class="s-crumbs" aria-label="{{ $L('Ruta', 'Breadcrumb') }}"><a href="{{ $U('/') }}">{{ $L('Inicio', 'Home') }}</a>@foreach(($crumbs ?? []) as [$cl, $cu])<span aria-hidden="true">/</span>@if($cu)<a href="{{ $U($cu) }}">{{ $cl }}</a>@else<span aria-current="page">{{ $cl }}</span>@endif @endforeach</nav>
		@if(!empty($eyebrow))<span class="s-eyebrow">{{ $eyebrow }}</span>@endif
		<h1>{{ $title }}</h1>
		@if(!empty($sub))<p class="s-hero__sub">{{ $sub }}</p>@endif
		@if(empty($noCta))
		<div class="s-actions">
			<a class="s-btn s-btn--primary s-btn--lg" href="{{ $U('/demo') }}">{{ $L('Solicitar una demo', 'Request a demo') }}<svg class="s-ico"><use href="#i-arrow"/></svg></a>
			<a class="s-btn s-btn--outline s-btn--lg" href="{{ $U('/pricing') }}">{{ $L('Ver planes', 'See plans') }}</a>
		</div>
		@endif
	</div>
</section>
