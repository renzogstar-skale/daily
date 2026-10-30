{{-- Navegación móvil DAIRY: barra inferior, hoja de accesos rápidos y escáner. Reusa $dairyNav del layout y DairyAccess. --}}
@php
$mobileTabs = array_values(array_filter([
	// En el teléfono el inicio del manager es la app DAIRY Mobile (/m); la barra solo existe < 768 px
	\Grocy\Helpers\DairyAccess::CanOpen('/m') ? ['/m', 'fa-house', 'Inicio', ['today']] : ['/today', 'fa-list-check', 'Hoy', ['today']],
	['/waste', 'fa-trash-can', 'Mermas', ['waste_overview', 'waste_approvals', 'waste_reasons']],
	['/stockoverview', 'fa-boxes-stacked', 'Inventario', ['stockoverview', 'stockentries', 'stockjournal', 'transfer', 'inventory', 'kardex']],
], fn($t) => \Grocy\Helpers\DairyAccess::CanOpen($t[0])));
if (count($mobileTabs) < 3 && \Grocy\Helpers\DairyAccess::CanOpen('/kpis')) { $mobileTabs[] = ['/kpis', 'fa-chart-line', 'Panel', ['executive_dashboard']]; }
$mobileLeft = array_slice($mobileTabs, 0, 2);
$mobileRight = array_slice($mobileTabs, 2, 1);
$mobileCan = [
	'waste' => \Grocy\Helpers\DairyAccess::CanOpen('/waste/report'),
	'kardex' => \Grocy\Helpers\DairyAccess::CanOpen('/kardex'),
	'stock' => \Grocy\Helpers\DairyAccess::CanOpen('/stockoverview'),
	'inventory' => \Grocy\Helpers\DairyAccess::CanOpen('/inventory'),
	'quality' => \Grocy\Helpers\DairyAccess::CanOpen('/quality'),
];
@endphp

<nav id="dairyBottomNav" class="dairy-bottom-nav d-md-none" aria-label="Navegación principal móvil">
	@foreach($mobileLeft as $t)
	<a href="{{ $U($t[0]) }}" class="dairy-bottom-nav-item @if(in_array($viewName, $t[3])) is-active @endif">
		<i class="fa-solid {{ $t[1] }}"></i><span>{{ $t[2] }}</span>
	</a>
	@endforeach
	@if($mobileCan['waste'])
	<a href="{{ $U('/waste/report') }}" class="dairy-bottom-nav-item dairy-bottom-nav-scan @if($viewName === 'waste_report') is-active @endif" aria-label="Registrar merma">
		<span class="dairy-bottom-nav-fab"><i class="fa-solid fa-plus"></i></span><span>Registrar</span>
	</a>
	@else
	<button type="button" class="dairy-bottom-nav-item dairy-bottom-nav-scan" data-dairy-scan aria-label="Escanear código">
		<span class="dairy-bottom-nav-fab"><i class="fa-solid fa-barcode"></i></span><span>Escanear</span>
	</button>
	@endif
	@foreach($mobileRight as $t)
	<a href="{{ $U($t[0]) }}" class="dairy-bottom-nav-item @if(in_array($viewName, $t[3])) is-active @endif">
		<i class="fa-solid {{ $t[1] }}"></i><span>{{ $t[2] }}</span>
	</a>
	@endforeach
	<button type="button" class="dairy-bottom-nav-item" data-toggle="modal" data-target="#dairyQuickSheet" aria-label="Más módulos">
		<i class="fa-solid fa-grip"></i><span>Más</span>
	</button>
</nav>

{{-- Hoja de accesos rápidos: todos los módulos visibles para el rol --}}
<div class="modal fade dairy-sheet" id="dairyQuickSheet" tabindex="-1" role="dialog" aria-label="Accesos rápidos" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="dairy-sheet-handle"></div>
			<div class="dairy-sheet-header">
				<strong>Accesos rápidos</strong>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="dairy-sheet-body">
				<div class="dairy-sheet-actions">
					<button type="button" class="dairy-sheet-action" data-dairy-scan data-dismiss="modal"><i class="fa-solid fa-barcode"></i>Escanear</button>
					@if($mobileCan['waste'])
					<a class="dairy-sheet-action" href="{{ $U('/waste/report') }}"><i class="fa-solid fa-camera"></i>Merma con foto</a>
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
							<i class="fa-solid fa-cube text-primary"></i><span>{{ $child['label'] }}</span>
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
		swUrl: '{{ $U('/sw.js', true) }}'
	};
</script>
