<figure class="s-shot{{ isset($cls) ? ' ' . $cls : '' }}">
	<div class="s-shot__bar" aria-hidden="true"><i></i><i></i><i></i><span>daily / {{ $img }}</span></div>
	<img src="{{ $U('/img/site/' . $img . '.webp') }}" alt="{{ $alt }}" width="1600" height="900" @if(!empty($eager)) fetchpriority="high" @else loading="lazy" @endif decoding="async">
</figure>
