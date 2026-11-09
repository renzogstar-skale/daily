@php
	// Recorte real de KPI y gráficos: escritorio mod_{key}_1400/2400 (proporción fija), móvil mod_{key}_m (apilado)
	$base = '/img/site/mod_' . $key;
	$size = @getimagesize(__DIR__ . '/../../../public' . $base . '_1400.webp') ?: [1400, 737];
	$mode = $mode ?? 'lazy'; // eager | lazy | deferred (lo carga JS)
	$p = $mode === 'deferred' ? 'data-' : '';
	$srcset = $U($base . '_1400.webp') . ' 1400w, ' . $U($base . '_2400.webp') . ' 2400w';
@endphp
<figure class="s-pv{{ isset($cls) ? ' ' . $cls : '' }}" data-zoom="{{ $U($base . '_2400.webp') }}" data-zoom-m="{{ $U($base . '_m.webp') }}">
	<div class="s-pv__frame">
		<picture>
			<source media="(max-width: 640px)" {{ $p }}srcset="{{ $U($base . '_m.webp') }}">
			<img {{ $p }}src="{{ $U($base . '_1400.webp') }}" {{ $p }}srcset="{{ $srcset }}" sizes="(max-width: 1060px) 94vw, 780px" alt="{{ $alt }}" width="{{ $size[0] }}" height="{{ $size[1] }}" decoding="async" @if($mode === 'eager') fetchpriority="high" @elseif($mode === 'lazy') loading="lazy" @endif>
		</picture>
	</div>
	<button class="s-pv__zoom" type="button" aria-label="{{ $L('Ampliar vista', 'Enlarge view') }}: {{ $alt }}"><svg class="s-ico s-ico--sm"><use href="#i-eye"/></svg><span>{{ $L('Ampliar', 'Enlarge') }}</span></button>
</figure>
