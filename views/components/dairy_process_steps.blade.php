{{-- Pasos de un proceso. $steps: [[título, ayuda, estado]] con estado done|current|pending|cancelled. $title opcional. --}}
<div class="dairy-steps-wrap {{ $class ?? '' }}">
	@if(!empty($title))
	<div class="dairy-steps-title">{{ $title }}</div>
	@endif
	<ol class="dairy-steps">
		@foreach($steps as $i => [$label, $hint, $state])
		<li class="dairy-step is-{{ $state }}">
			<span class="dairy-step-dot">
				@if($state === 'done')<i class="fa-solid fa-check"></i>@elseif($state === 'cancelled')<i class="fa-solid fa-xmark"></i>@else{{ $i + 1 }}@endif
			</span>
			<span class="dairy-step-text">
				<span class="dairy-step-label">{{ $label }}</span>
				@if($hint)<span class="dairy-step-hint">{{ $hint }}</span>@endif
			</span>
		</li>
		@endforeach
	</ol>
</div>
