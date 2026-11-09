@php
$dairyNav = \Grocy\Helpers\DairyNavigation::Build($viewName, fn($s) => $__t($s));
$dnavHref = function ($route) use ($U) {
	$parts = explode('#', $route, 2);
	return $U($parts[0]) . (isset($parts[1]) ? '#' . $parts[1] : '');
};
@endphp
<ul class="navbar-nav navbar-sidenav dnav"
	id="dairySidenav"
	aria-label="Navegación principal">

	<!-- Expandir sidebar (visible solo con el sidebar contraído) -->
	<li class="nav-item sidebar-expand-item d-none d-md-block">
		<div class="sidebar-expand-action">
			<button type="button"
				id="sidebarExpandBtn"
				class="sidebar-toggle-btn"
				title="Expandir menú lateral"
				aria-label="Expandir menú lateral">
				<i class="fa-solid fa-chevron-right"
					aria-hidden="true"></i>
			</button>
		</div>
	</li>

	@foreach($dairyNav as $cat)
	<li class="dnav-cat @if($cat['active']) is-active is-open @endif"
		data-cat="{{ $cat['id'] }}">
		<button type="button"
			class="dnav-cat-btn"
			id="dnav-btn-{{ $cat['id'] }}"
			aria-expanded="{{ $cat['active'] ? 'true' : 'false' }}"
			aria-controls="dnav-sub-{{ $cat['id'] }}"
			data-toggle="tooltip"
			data-placement="right"
			title="{{ $cat['label'] }}">
			<i class="fa-solid {{ $cat['icon'] }} dnav-icon"
				aria-hidden="true"></i>
			<span class="nav-link-text">{{ $cat['label'] }}</span>
			<span class="dnav-dot d-none"
				aria-hidden="true"></span>
			<i class="fa-solid fa-chevron-down dnav-caret"
				aria-hidden="true"></i>
		</button>
		<div class="dnav-sub-wrap"
			id="dnav-sub-{{ $cat['id'] }}"
			role="region"
			aria-labelledby="dnav-btn-{{ $cat['id'] }}">
			<ul class="dnav-sub">
				<li class="dnav-flyout-title"
					aria-hidden="true">{{ $cat['label'] }}</li>
				@foreach($cat['items'] as $item)
				@if(isset($item['children']))
				<li class="dnav-item dnav-group @if($item['active']) is-active is-open @endif"
					data-item="{{ $item['id'] }}">
					<button type="button"
						class="dnav-link dnav-group-btn"
						aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
						aria-controls="sub-nav-{{ $item['id'] }}"
						title="{{ $item['desc'] }}">
						<i class="fa-solid {{ $item['icon'] }} dnav-icon"
							aria-hidden="true"></i>
						<span class="nav-link-text">{{ $item['label'] }}</span>
						<i class="fa-solid fa-chevron-down dnav-caret"
							aria-hidden="true"></i>
					</button>
					<div class="dnav-sub-wrap dnav-nested"
						id="sub-nav-{{ $item['id'] }}">
						<ul class="dnav-sub">
							@foreach($item['children'] as $child)
							<li class="dnav-item @if($child['active']) is-active @endif">
								<a class="dnav-link dnav-link-leaf discrete-link"
									href="{{ $dnavHref($child['route']) }}"
									@if($child['active'])
									aria-current="page"
									@endif>
									<span class="nav-link-text">{{ $child['label'] }}</span>
								</a>
							</li>
							@endforeach
						</ul>
					</div>
				</li>
				@else
				<li class="dnav-item @if($item['active']) is-active @endif"
					data-item="{{ $item['id'] }}">
					<a class="dnav-link discrete-link"
						href="{{ $dnavHref($item['route']) }}"
						title="{{ $item['desc'] }}"
						@if($item['active'])
						aria-current="page"
						@endif>
						<i class="fa-solid {{ $item['icon'] }} dnav-icon"
							aria-hidden="true"></i>
						<span class="nav-link-text">{{ $item['label'] }}</span>
						@if(!empty($item['badge']))
						<span class="dnav-badge d-none"
							data-dnav-badge="{{ $item['badge'] }}"></span>
						@endif
					</a>
				</li>
				@endif
				@endforeach
			</ul>
		</div>
	</li>
	@endforeach

	@php $dnavLocked = \Grocy\Helpers\DairyNavigation::Locked(fn($s) => $__t($s)); @endphp
	@if(count($dnavLocked) > 0)
	<!-- Ampliación de plan: separado del trabajo diario, cerrado por defecto -->
	<li class="dnav-cat dnav-upgrade"
		data-cat="upgrade">
		<button type="button"
			class="dnav-cat-btn"
			id="dnav-btn-upgrade"
			aria-expanded="false"
			aria-controls="dnav-sub-upgrade"
			data-toggle="tooltip"
			data-placement="right"
			title="AMPLIAR PLAN">
			<i class="fa-solid fa-gem dnav-icon"
				aria-hidden="true"></i>
			<span class="nav-link-text">AMPLIAR PLAN</span>
			<i class="fa-solid fa-chevron-down dnav-caret"
				aria-hidden="true"></i>
		</button>
		<div class="dnav-sub-wrap"
			id="dnav-sub-upgrade"
			role="region"
			aria-labelledby="dnav-btn-upgrade">
			<ul class="dnav-sub">
				<li class="dnav-flyout-title"
					aria-hidden="true">AMPLIAR PLAN</li>
				@foreach($dnavLocked as $lockedPlan => $lockedItems)
				<li class="dnav-upgrade-plan"
					aria-hidden="true">{{ \Grocy\Helpers\DailySubscription::PlanName($lockedPlan) }}</li>
				@foreach($lockedItems as $item)
				<li class="dnav-item dnav-locked"
					data-item="{{ $item['id'] }}">
					<a class="dnav-link discrete-link"
						href="{{ $dnavHref($item['route']) }}"
						title="{{ $item['desc'] }}">
						<i class="fa-solid {{ $item['icon'] }} dnav-icon"
							aria-hidden="true"></i>
						<span class="nav-link-text">{{ $item['label'] }}</span>
						<i class="fa-solid fa-lock dnav-lock"
							aria-hidden="true"></i>
					</a>
				</li>
				@endforeach
				@endforeach
				<li class="dnav-item">
					<a class="dnav-link discrete-link dnav-upgrade-cta"
						href="{{ $U('/subscription#planes') }}">
						<i class="fa-solid fa-table-columns dnav-icon"
							aria-hidden="true"></i>
						<span class="nav-link-text">Ver planes</span>
					</a>
				</li>
			</ul>
		</div>
	</li>
	@endif
</ul>
<script>
	// Antes del primer pintado: corrige el activo para rutas con fragmento y restaura la última categoría
	// abierta en pantallas que no pertenecen al menú (evita parpadeos).
	(function()
	{
		var nav = document.getElementById('dairySidenav');
		var hash = window.location.hash;
		var hashLink = hash ? nav.querySelector('a.dnav-link[href$="' + window.location.pathname + hash + '"]') : null;
		if (hashLink)
		{
			nav.querySelectorAll('.dnav-item.is-active, .dnav-cat.is-active').forEach(function(el) { el.classList.remove('is-active', 'is-open'); });
			nav.querySelectorAll('[aria-current="page"]').forEach(function(el) { el.removeAttribute('aria-current'); });
			hashLink.setAttribute('aria-current', 'page');
			hashLink.parentElement.classList.add('is-active');
			hashLink.closest('.dnav-cat').classList.add('is-active');
		}
		var open = nav.querySelector('.dnav-cat.is-active');
		if (!open)
		{
			try { var last = localStorage.getItem('dairy_nav_open'); if (last) { open = nav.querySelector('.dnav-cat[data-cat="' + last + '"]'); } } catch (e) { }
		}
		nav.querySelectorAll('.dnav-cat').forEach(function(cat)
		{
			var isOpen = cat === open;
			cat.classList.toggle('is-open', isOpen);
			cat.querySelector('.dnav-cat-btn').setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});
		if (hash && (hash.indexOf('#sub-nav-') === 0 || hash === '#sub-nav-manage-master-data'))
		{
			var group = nav.querySelector('.dnav-group');
			if (group)
			{
				nav.querySelectorAll('.dnav-cat').forEach(function(c) { c.classList.remove('is-open'); c.querySelector('.dnav-cat-btn').setAttribute('aria-expanded', 'false'); });
				var gc = group.closest('.dnav-cat');
				gc.classList.add('is-open');
				gc.querySelector('.dnav-cat-btn').setAttribute('aria-expanded', 'true');
				group.classList.add('is-open');
				group.querySelector('.dnav-group-btn').setAttribute('aria-expanded', 'true');
			}
		}
	})();
</script>
