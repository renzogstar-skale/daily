{{-- Navegación móvil DAIRY: barra inferior, hoja de accesos rápidos y escáner. Reusa $dairyNav del layout y DairyAccess. --}}
@php
// Barra inferior (< 768 px): HOY · Módulos · Escanear · Alertas. Cada destino depende del rol y del plan:
// sin /today, HOY lleva al inicio del rol; sin escáner, el botón central es la acción principal permitida;
// sin /alerts, la última pestaña es el primer módulo operativo disponible. «Módulos» abre todas las áreas habilitadas.
$mobileFirst = function (array $opts) { foreach ($opts as $t) { if (\Grocy\Helpers\DairyAccess::CanOpen($t[0])) return $t; } return null; };
$mobileHome = $mobileFirst([
	['/today', 'fa-list-check', 'HOY', ['today']],
	['/operations/attendance?role_view=employee', 'fa-list-check', 'HOY', ['operations_attendance', 'attendance']],
	['/sales', 'fa-list-check', 'HOY', ['sales_history']],
]);
$mobileCanScan = \Grocy\Helpers\DairyAccess::CanOpen('/scan');
$mobileCenter = $mobileCanScan ? null : $mobileFirst([
	['/waste/report', 'fa-camera', 'Merma', ['waste_report']],
	['/loss-prevention/counts', 'fa-clipboard-list', 'Conteo', ['loss_prevention_counts']],
	['/pos', 'fa-cash-register', 'Vender', ['pos']],
]);
$mobileLast = $mobileFirst([
	['/alerts', 'fa-bell', 'Alertas', ['alert_center']],
	['/operations/scheduling', 'fa-calendar-days', 'Turnos', ['operations_scheduling', 'schedule']],
	['/workforce', 'fa-users', 'Personal', ['workforce']],
]);
// El menú del sidebar se arma dentro de su propio @include (ámbito aislado): la hoja «Módulos» lo construye aquí;
// antes leía un $dairyNav inexistente y la hoja salía vacía.
$dairyNav = \Grocy\Helpers\DairyNavigation::Build($viewName, fn($s) => $__t($s));
$mobileHref = function ($route) use ($U) { $p = explode('#', $route, 2); return $U($p[0]) . (isset($p[1]) ? '#' . $p[1] : ''); };
$mobileCan = [
	'waste' => \Grocy\Helpers\DairyAccess::CanOpen('/waste/report'),
	'kardex' => \Grocy\Helpers\DairyAccess::CanOpen('/kardex'),
	'stock' => \Grocy\Helpers\DairyAccess::CanOpen('/stockoverview'),
	'inventory' => \Grocy\Helpers\DairyAccess::CanOpen('/inventory'),
];
@endphp

<nav id="dairyBottomNav" class="dairy-bottom-nav d-md-none" aria-label="Navegación principal móvil">
	@if($mobileHome)
	<a href="{{ $mobileHref($mobileHome[0]) }}" class="dairy-bottom-nav-item @if(in_array($viewName, $mobileHome[3])) is-active @endif" @if(in_array($viewName, $mobileHome[3])) aria-current="page" @endif>
		<i class="fa-solid {{ $mobileHome[1] }}"></i><span>{{ $mobileHome[2] }}</span>
	</a>
	@endif
	<button type="button" class="dairy-bottom-nav-item" data-toggle="modal" data-target="#dairyQuickSheet" aria-haspopup="dialog" aria-label="Módulos">
		<i class="fa-solid fa-grip"></i><span>Módulos</span>
	</button>
	@if($mobileCanScan)
	<button type="button" class="dairy-bottom-nav-item dairy-bottom-nav-scan" data-dairy-scan aria-label="Escanear código">
		<span class="dairy-bottom-nav-fab"><i class="fa-solid fa-barcode"></i></span><span>Escanear</span>
	</button>
	@elseif($mobileCenter)
	<a href="{{ $mobileHref($mobileCenter[0]) }}" class="dairy-bottom-nav-item dairy-bottom-nav-scan @if(in_array($viewName, $mobileCenter[3])) is-active @endif" aria-label="{{ $mobileCenter[2] }}">
		<span class="dairy-bottom-nav-fab"><i class="fa-solid {{ $mobileCenter[1] }}"></i></span><span>{{ $mobileCenter[2] }}</span>
	</a>
	@endif
	@if($mobileLast)
	<a href="{{ $mobileHref($mobileLast[0]) }}" class="dairy-bottom-nav-item @if(in_array($viewName, $mobileLast[3])) is-active @endif" @if(in_array($viewName, $mobileLast[3])) aria-current="page" @endif>
		<i class="fa-solid {{ $mobileLast[1] }}"></i><span>{{ $mobileLast[2] }}</span>
	</a>
	@endif
</nav>

{{-- Hoja «Módulos»: acciones rápidas y todos los módulos visibles para el rol, agrupados por área operativa --}}
<div class="modal fade dairy-sheet" id="dairyQuickSheet" tabindex="-1" role="dialog" aria-label="Módulos" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="dairy-sheet-handle"></div>
			<div class="dairy-sheet-header">
				<strong>Módulos</strong>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="dairy-sheet-body">
				<div class="dairy-sheet-actions">
					@if($mobileCanScan)
					<button type="button" class="dairy-sheet-action" data-dairy-scan data-dismiss="modal"><i class="fa-solid fa-barcode"></i>Escanear</button>
					@endif
					@if($mobileCan['waste'])
					<a class="dairy-sheet-action" href="{{ $U('/waste/report') }}"><i class="fa-solid fa-camera"></i>Merma con foto</a>
					@endif
					@if(\Grocy\Helpers\DairyAccess::CanOpen('/m'))
					<a class="dairy-sheet-action" href="{{ $U('/m') }}#/tasks"><i class="fa-solid fa-clipboard-check"></i>Tareas</a>
					@endif
					<button type="button" class="dairy-sheet-action" id="dairyNotifyToggle"><i class="fa-solid fa-bell"></i><span>Notificaciones</span></button>
					<button type="button" class="dairy-sheet-action d-none" id="dairyInstallApp"><i class="fa-solid fa-download"></i>Instalar app</button>
				</div>
				@php
				$dnavHref = $dnavHref ?? function ($route) use ($U) {
					$parts = explode('#', $route, 2);
					return $U($parts[0]) . (isset($parts[1]) ? '#' . $parts[1] : '');
				};
				@endphp
				@if(!empty($dairyNav) && is_array($dairyNav))
				@foreach($dairyNav as $navSection)
				@php
					$sectionLabel = $navSection['label'] ?? ($navSection[0] ?? 'Inicio');
					$sectionItems = $navSection['items'] ?? ($navSection[1] ?? []);
				@endphp
				@if(count($sectionItems) > 0)
				<div class="dairy-sheet-section">{{ $sectionLabel }}</div>
				<div class="dairy-sheet-grid">
					@foreach($sectionItems as $navItem)
					@if(isset($navItem['children']))
						@foreach($navItem['children'] as $child)
						<a class="dairy-sheet-tile @if(!empty($child['active'])) is-active @endif" href="{{ $dnavHref($child['route']) }}">
							<i class="fa-solid {{ $child['icon'] ?? $navItem['icon'] ?? 'fa-cube' }} text-primary"></i><span>{{ $child['label'] }}</span>
						</a>
						@endforeach
					@elseif(!empty($navItem['route']))
						<a class="dairy-sheet-tile @if(!empty($navItem['active'])) is-active @endif" href="{{ $dnavHref($navItem['route']) }}">
							<i class="fa-solid {{ $navItem['icon'] ?? 'fa-circle' }} text-primary"></i><span>{{ $navItem['label'] ?? ($navItem[3] ?? '') }}</span>
						</a>
					@endif
					@endforeach
				</div>
				@endif
				@endforeach
				@endif
				<button type="button" class="btn btn-light btn-block border mt-3" data-dismiss="modal" data-dairy-open-drawer><i class="fa-solid fa-bars mr-1"></i>Menú completo</button>
			</div>
		</div>
	</div>
</div>

{{-- Escáner de códigos (cámara + ingreso manual) → ficha del producto con acciones según permisos --}}
<div class="modal fade" id="dairyScanModal" tabindex="-1" role="dialog" aria-label="Escanear producto" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header py-2">
				<h5 class="modal-title"><i class="fa-solid fa-barcode mr-1 text-primary"></i>Escanear producto</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<div class="dairy-scan-video-wrap" id="dairyScanVideoWrap">
					<video id="dairyScanVideo" playsinline muted></video>
					<div class="dairy-scan-frame"></div>
					<button type="button" class="btn btn-sm btn-light dairy-scan-torch d-none" id="dairyScanTorch" title="Linterna"><i class="fa-solid fa-lightbulb"></i></button>
				</div>
				<div class="small text-muted mt-2" id="dairyScanStatus">Apunta la cámara al código de barras o QR.</div>
				<form class="input-group mt-2" id="dairyScanManual" autocomplete="off">
					<input type="text" class="form-control" id="dairyScanInput" inputmode="numeric" placeholder="O escribe el código">
					<div class="input-group-append"><button class="btn btn-primary" type="submit">Buscar</button></div>
				</form>
				<div id="dairyScanResult" class="mt-3 d-none"></div>
			</div>
		</div>
	</div>
</div>

<script>
	window.DairyMobile = {
		can: {!! json_encode($mobileCan) !!},
		zxingUrl: '{{ $U('/packages/@zxing/library/umd/index.min.js?v=', true) }}{{ $version }}',
		swUrl: null
	};
</script>
