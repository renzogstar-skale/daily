@extends('site.layout')

@section('head')
<link rel="preload" as="image" href="{{ $U('/img/site/today.webp') }}" fetchpriority="high">
@endsection

@section('content')
{{-- 1. Hero --}}
<section class="s-hero">
	<div class="s-hero__bg" aria-hidden="true"><i></i><i></i><i></i></div>
	<div class="s-wrap s-hero__grid">
		<div class="s-hero__copy">
			<span class="s-eyebrow s-eyebrow--pill"><span class="s-dot"></span>{{ $L('Supermercados · Retail · Distribución', 'Supermarkets · Retail · Distribution') }}</span>
			<h1><span class="s-grad">DAILY</span> — {{ $L('Inteligencia y control para cada operación de tu negocio.', 'Intelligence and control for every operation in your business.') }}</h1>
			<p class="s-hero__sub">{{ $L('Una plataforma integral para gestionar ventas, inventarios, almacenes, recursos humanos, despachos y prevención de pérdidas desde un solo lugar.', 'An all-in-one platform to manage sales, inventory, warehouses, workforce, dispatch and loss prevention from a single place.') }}</p>
			<div class="s-actions">
				<a class="s-btn s-btn--primary s-btn--lg" href="{{ $U('/demo') }}">{{ $L('Solicitar una demo', 'Request a demo') }}<svg class="s-ico"><use href="#i-arrow"/></svg></a>
				<a class="s-btn s-btn--outline s-btn--lg" href="#tour">{{ $L('Conocer DAILY', 'Discover DAILY') }}<svg class="s-ico"><use href="#i-play"/></svg></a>
			</div>
			<ul class="s-hero__trust">
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $L('Web y móvil con escáner', 'Web and mobile with scanner') }}</li>
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $L('Multisucursal con roles', 'Multi-branch with roles') }}</li>
				<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $L('Español e inglés', 'Spanish and English') }}</li>
			</ul>
		</div>
		<div class="s-hero__visual" aria-hidden="false">
			<div class="s-hero__tilt">
				@include('site.partials.shot', ['img' => 'today', 'alt' => $L('Panel HOY de DAILY con ventas, merma y alertas del día', 'DAILY TODAY panel with sales, shrink and alerts'), 'eager' => true, 'cls' => 's-shot--hero'])
			</div>
			<div class="s-float s-float--a" aria-hidden="true"><span class="s-badge s-badge--green"><svg class="s-ico"><use href="#i-chart"/></svg></span><div><small>{{ $L('Ventas por hora', 'Hourly sales') }}</small><svg class="s-spark" viewBox="0 0 100 28"><path d="M0 24 L12 20 L24 22 L36 14 L48 16 L60 9 L72 11 L84 5 L100 3"/></svg></div></div>
			<div class="s-float s-float--b" aria-hidden="true"><span class="s-badge s-badge--amber"><svg class="s-ico"><use href="#i-drop"/></svg></span><div><small>{{ $L('Merma por causal', 'Shrink by cause') }}</small><span class="s-minibars"><i style="--h:90%"></i><i style="--h:62%"></i><i style="--h:40%"></i><i style="--h:22%"></i><i style="--h:12%"></i></span></div></div>
			<div class="s-float s-float--c" aria-hidden="true"><span class="s-badge s-badge--dark"><svg class="s-ico"><use href="#i-shield"/></svg></span><div><small>{{ $L('Alertas del día', 'Today\'s alerts') }}</small><strong>{{ $L('Por atender', 'To review') }}</strong></div></div>
			{{-- Celular animado en vivo: Home -> Tap en Scan -> Escaneo con láser -> Detección de Yogur --}}
			<figure class="s-phone s-phone--anim" aria-label="{{ $L('Demostración animada: Escaneo de producto en DAILY', 'Animated demo: Product scan in DAILY') }}">
				<div class="s-phone__screen-wrap">
					{{-- Capa 1: Pantalla de Inicio --}}
					<div class="s-phone__layer s-phone__layer--home">
						<img src="{{ $U('/img/site/m_home.webp') }}" alt="" width="390" height="844" loading="eager" decoding="async">
						{{-- Hora y fecha de Miami en tiempo real --}}
						<span class="s-phone__live-time" data-miami-time aria-hidden="true">--:--</span>
						<span class="s-phone__live-date" data-miami-date aria-hidden="true">Miami Beach</span>
						{{-- Indicador táctil animado sobre el botón Scan --}}
						<span class="s-phone__tap-circle" aria-hidden="true"></span>
					</div>

					{{-- Capa 2: Pantalla de Escáner en vivo --}}
					<div class="s-phone__layer s-phone__layer--scan" aria-hidden="true">
						<span class="s-phone__live-time s-phone__live-time--scan" data-miami-time aria-hidden="true">--:--</span>
						{{-- Barra superior de escáner --}}
						<div class="s-phone__scan-top">
							<span class="s-phone__scan-back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg></span>
							<span class="s-phone__scan-title">{{ $L('Escanear UPC', 'Scan Barcode') }}</span>
							<span class="s-phone__scan-flash"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></span>
						</div>

						{{-- Visor central con producto y código de barras --}}
						<div class="s-phone__hud">
							<div class="s-phone__hud-target">
								<span class="s-phone__corner s-phone__corner--tl"></span>
								<span class="s-phone__corner s-phone__corner--tr"></span>
								<span class="s-phone__corner s-phone__corner--bl"></span>
								<span class="s-phone__corner s-phone__corner--br"></span>

								<div class="s-phone__prod-wrap">
									<img src="{{ $U('/img/control_center/strawberry_yogurt_cup.svg') }}" alt="" class="s-phone__hud-prod">
									<div class="s-phone__hud-barcode">
										<svg viewBox="0 0 120 32" fill="currentColor">
											<rect x="0" y="0" width="2" height="24"/>
											<rect x="4" y="0" width="1" height="24"/>
											<rect x="7" y="0" width="3" height="24"/>
											<rect x="12" y="0" width="1.5" height="24"/>
											<rect x="16" y="0" width="4" height="24"/>
											<rect x="23" y="0" width="1" height="24"/>
											<rect x="26" y="0" width="3" height="24"/>
											<rect x="31" y="0" width="1.5" height="24"/>
											<rect x="35" y="0" width="3" height="24"/>
											<rect x="41" y="0" width="1" height="24"/>
											<rect x="44" y="0" width="3" height="24"/>
											<rect x="50" y="0" width="2" height="24"/>
											<rect x="54" y="0" width="3.5" height="24"/>
											<rect x="60" y="0" width="1" height="24"/>
											<rect x="63" y="0" width="3" height="24"/>
											<rect x="68" y="0" width="1.5" height="24"/>
											<rect x="72" y="0" width="4" height="24"/>
											<rect x="78" y="0" width="1.5" height="24"/>
											<rect x="82" y="0" width="2.5" height="24"/>
											<rect x="87" y="0" width="1" height="24"/>
											<rect x="90" y="0" width="3" height="24"/>
											<rect x="95" y="0" width="1.5" height="24"/>
											<rect x="99" y="0" width="3.5" height="24"/>
											<rect x="105" y="0" width="2" height="24"/>
											<rect x="110" y="0" width="3" height="24"/>
											<rect x="116" y="0" width="2" height="24"/>
											<text x="60" y="31" font-size="6" text-anchor="middle" font-family="monospace" letter-spacing="1.5">779123456789</text>
										</svg>
									</div>
								</div>

								{{-- Línea láser verde animada --}}
								<div class="s-phone__laser"></div>
							</div>
						</div>

						{{-- Ficha real de la herramienta DAILY (Bottom Sheet Operativo) --}}
						<div class="s-phone__sheet">
							<div class="s-phone__sheet-handle"></div>
							
							{{-- Encabezado con código UPC detectado y estado FEFO --}}
							<div class="s-phone__sheet-head">
								<span class="s-phone__upc-badge">UPC 779123456789</span>
								<span class="s-phone__fefo-badge"><span class="s-phone__dot"></span>{{ $L('FEFO • Vence 2d', 'FEFO • Exp 2d') }}</span>
							</div>

							{{-- Ficha del producto en piso de venta --}}
							<div class="s-phone__sheet-row">
								<img src="{{ $U('/img/control_center/strawberry_yogurt_cup.svg') }}" alt="" class="s-phone__sheet-img">
								<div class="s-phone__sheet-info">
									<strong>{{ $L('Yogur de Frutilla 1L', 'Strawberry Yogurt 1L') }}</strong>
									<small>{{ $L('Stock actual: 48 un. • Pasillo 4', 'Current stock: 48 units • Aisle 4') }}</small>
								</div>
							</div>

							{{-- Selector de cantidad a procesar --}}
							<div class="s-phone__sheet-stepper">
								<span>{{ $L('Cantidad a operar:', 'Qty to process:') }}</span>
								<div class="s-phone__stepper-ctrl">
									<button type="button" class="s-phone__stepper-btn" tabindex="-1">-</button>
									<b class="s-phone__stepper-val">1</b>
									<button type="button" class="s-phone__stepper-btn" tabindex="-1">+</button>
								</div>
							</div>

							{{-- Botones de acción operativa reales de DAILY --}}
							<div class="s-phone__sheet-actions">
								<button type="button" class="s-phone__act s-phone__act--primary">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
									<span>{{ $L('Confirmar Recepción', 'Confirm Stock Reception') }}</span>
								</button>
								<button type="button" class="s-phone__act s-phone__act--sub">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>
									<span>{{ $L('Conteo Físico', 'Physical Count') }}</span>
								</button>
								<button type="button" class="s-phone__act s-phone__act--danger">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
									<span>{{ $L('Reportar Merma', 'Report Waste') }}</span>
								</button>
							</div>
						</div>

						{{-- Toast flotante de confirmación exitosa de DAILY --}}
						<div class="s-phone__toast" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
							<span>{{ $L('✓ Recepción de Stock (+1) guardada', '✓ Stock reception (+1) saved') }}</span>
						</div>
					</div>

					{{-- Dynamic Island iOS superior --}}
					<div class="s-phone__dynamic-island" aria-hidden="true"></div>
				</div>
			</figure>
		</div>
	</div>
</section>

{{-- 2. Franja oscura --}}
<section class="s-band" aria-label="{{ $L('Por qué cambiar', 'Why switch') }}">
	<div class="s-wrap s-band__in">
		<p class="s-band__title">{{ $L('¿Tu operación todavía vive en hojas de cálculo, chats y reportes de fin de mes?', 'Does your operation still live in spreadsheets, chats and month-end reports?') }}</p>
		<ul class="s-band__pills">
			<li><svg class="s-ico"><use href="#i-grid"/></svg>{{ $L('Un solo sistema', 'One system') }}</li>
			<li><svg class="s-ico"><use href="#i-phone"/></svg>{{ $L('Captura en piso', 'Capture on the floor') }}</li>
			<li><svg class="s-ico"><use href="#i-eye"/></svg>{{ $L('Visibilidad diaria', 'Daily visibility') }}</li>
		</ul>
		<a class="s-btn s-btn--primary" href="{{ $U('/demo') }}">{{ $L('Hablemos de tu operación', 'Let\'s talk about your operation') }}</a>
	</div>
</section>

{{-- 3. Funcionalidades / Plataforma 8 Módulos --}}
<section class="s-section s-platform-section" id="platform" aria-labelledby="mods-title">
	<div class="s-wrap">
		<div class="s-platform-head" data-reveal>
			<span class="s-platform-eyebrow">
				<svg class="s-ico" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
					<path d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"/>
				</svg>
				{{ $L('PLATAFORMA DAILY', 'DAILY PLATFORM') }}
			</span>
			<h2 id="mods-title" class="s-platform-title">
				{!! $L('Ocho módulos conectados, <span class="s-platform-title__hl">una sola operación</span>', 'Eight connected modules, <span class="s-platform-title__hl">one operation</span>') !!}
			</h2>
			<p class="s-platform-subtitle">
				{{ $L('Gestión integral del retail: ventas, inventario, mermas, personal y suministro, con datos en tiempo real y control total por sucursal.', 'Comprehensive retail management: sales, inventory, shrink, workforce and supply, with real-time data and full multi-branch control.') }}
			</p>
		</div>

		<div class="s-platform-grid">
			@php
			$modulesShowcase = [
				[
					'img' => 'mod_1_sales.webp',
					'title' => $L('Ventas y Rentabilidad', 'Sales & Profitability'),
					'desc' => $L('Monitorea las ventas, el cumplimiento de metas y la rentabilidad por sucursal, departamento y producto.', 'Track sales, target completion and margins by branch, department and product.'),
					'features' => [
						$L('Ventas por hora y acumuladas', 'Hourly and cumulative sales'),
						$L('Cumplimiento de metas', 'Target completion'),
						$L('Margen, costo y utilidad', 'Margin, cost and profit'),
						$L('Productos más vendidos', 'Best-selling products'),
					]
				],
				[
					'img' => 'mod_2_inventory.webp',
					'title' => $L('Inventario, Almacenes y Bodegas', 'Inventory, Warehouses & Stock'),
					'desc' => $L('Controla existencias, movimientos y ubicaciones con trazabilidad completa de la mercancía.', 'Control stocks, movements and storage bins with complete merchandise traceability.'),
					'features' => [
						$L('Existencias y Kardex valorizado', 'Stock levels & valued ledger'),
						$L('Conteos físicos y selectivos', 'Physical and cycle counts'),
						$L('Escáner UPC / Códigos de barras', 'UPC / Barcode scanner'),
						$L('Traslados y mapa de ubicaciones', 'Transfers & location map'),
					]
				],
				[
					'img' => 'mod_3_waste.webp',
					'title' => $L('Mermas y Prevención', 'Shrink & Prevention'),
					'desc' => $L('Identifica, analiza y reduce las pérdidas operativas y comerciales mediante controles y acciones.', 'Identify, analyze and cut operational and commercial loss with root-cause controls.'),
					'features' => [
						$L('Registro de mermas y causales', 'Waste logs and root causes'),
						$L('Matriz de causas y análisis Pareto', 'Pareto cause matrix'),
						$L('Investigación y seguimiento', 'Investigation & follow-up'),
						$L('Control de ajustes y aprobaciones', 'Adjustment & approval controls'),
					]
				],
				[
					'img' => 'mod_4_lp.webp',
					'title' => $L('Prevención de Pérdidas', 'Loss Prevention & Security'),
					'desc' => $L('Detecta, investiga y resuelve pérdidas mediante el control de activos, mercancía sensible y evidencias.', 'Detect, investigate and resolve loss through asset, sensitive goods and evidence tracking.'),
					'features' => [
						$L('Control de celulares por IMEI', 'IMEI mobile phone control'),
						$L('Control de activos y equipos', 'Asset & equipment registry'),
						$L('Investigaciones y expedientes', 'Investigation case files'),
						$L('CCTV, evidencias y alertas', 'CCTV, evidence & alerts'),
					]
				],
				[
					'img' => 'mod_5_dispatches.webp',
					'title' => $L('Despachos y Devoluciones', 'Dispatches & Returns'),
					'desc' => $L('Gestiona la preparación, entrega y devolución de mercancía con seguimiento y trazabilidad.', 'Manage order prep, delivery dispatch and customer returns with full audit trail.'),
					'features' => [
						$L('Preparación y validación de pedidos', 'Order picking and validation'),
						$L('Control de salidas y entregas', 'Outbound and delivery checks'),
						$L('Seguimiento de despachos', 'Dispatch status tracking'),
						$L('Recepción y gestión de devoluciones', 'Returns intake and triage'),
					]
				],
				[
					'img' => 'mod_6_hr.webp',
					'title' => $L('Recursos Humanos y Operaciones', 'HR & Store Operations'),
					'desc' => $L('Organiza turnos, controla la asistencia y optimiza la productividad del personal según la demanda.', 'Schedule shifts, record attendance and align staff productivity with peak demand.'),
					'features' => [
						$L('Programación de turnos y cobertura', 'Shift scheduling & coverage'),
						$L('Control de asistencia', 'Time & attendance tracking'),
						$L('Tareas, incidencias y solicitudes', 'Tasks, incidents & requests'),
						$L('Productividad por empleado y área', 'Productivity by associate and floor'),
					]
				],
				[
					'img' => 'mod_7_planning.webp',
					'title' => $L('Planeación y Compras', 'Planning & Procurement'),
					'desc' => $L('Anticipa la demanda, optimiza el abastecimiento y gestiona proveedores para asegurar la disponibilidad.', 'Forecast demand, balance stock replenishment and manage vendors to prevent stockouts.'),
					'features' => [
						$L('Planeación de demanda (MRP)', 'Demand planning (MRP)'),
						$L('Abastecimiento y cobertura', 'Supply replenishment & runway'),
						$L('Gestión de proveedores', 'Supplier management'),
						$L('Órdenes de compra y recepción', 'Purchase orders and receiving'),
					]
				],
				[
					'img' => 'mod_8_analytics.webp',
					'title' => $L('Indicadores y Analítica', 'Analytics & Executive KPIs'),
					'desc' => $L('Transforma los datos operativos en decisiones mediante indicadores financieros y comparativos por sucursal.', 'Turn store operations into business clarity with financial and multi-branch KPIs.'),
					'features' => [
						$L('Panel Ejecutivo y HOY', 'Executive & TODAY dashboard'),
						$L('KPIs operativos y financieros', 'Operational & financial KPIs'),
						$L('Comparativos por período', 'Period-over-period trends'),
						$L('Reportes exportables y auditoría', 'Exportable reports & audit logs'),
					]
				],
			];
			@endphp

			@foreach($modulesShowcase as $item)
			<article class="s-mod-card" data-reveal>
				<div class="s-mod-card__visual">
					<img src="{{ $U('/img/site/modules/' . $item['img']) }}" alt="{{ $item['title'] }}" width="232" height="124" loading="lazy" decoding="async">
				</div>
				<div class="s-mod-card__body">
					<h3 class="s-mod-card__title">{{ $item['title'] }}</h3>
					<p class="s-mod-card__desc">{{ $item['desc'] }}</p>
					<ul class="s-mod-card__features" role="list">
						@foreach($item['features'] as $f)
						<li>
							<span class="s-mod-card__check" aria-hidden="true">
								<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
									<path d="M3.5 8.5l3 3 6-6"/>
								</svg>
							</span>
							<span>{{ $f }}</span>
						</li>
						@endforeach
					</ul>
				</div>
			</article>
			@endforeach
		</div>
	</div>
</section>

{{-- 4. DAILY en acción --}}
@include('site.partials.tour')

{{-- 5. Para ti si --}}
<section class="s-section s-fit" aria-labelledby="fit-title">
	<div class="s-wrap s-fit__grid">
		<div class="s-fit__visual" data-reveal>
			@include('site.partials.shot', ['img' => 'scan', 'alt' => $L('Operaciones con escáner en DAILY', 'Scanner operations in DAILY')])
			<figure class="s-phone s-phone--fit"><img src="{{ $U('/img/site/m_scan.webp') }}" alt="{{ $L('Escáner en la app móvil de DAILY', 'Scanner in the DAILY mobile app') }}" width="390" height="844" loading="lazy" decoding="async"></figure>
		</div>
		<div data-reveal>
			<span class="s-eyebrow">{{ $L('El reto de crecer', 'The challenge of scaling') }}</span>
			<h2 id="fit-title">{{ $L('¿Tienes todo bajo control?', 'Do you have everything under control?') }}</h2>
			<p class="s-fit__lead">{{ $L('DAILY conecta tus tiendas, inventarios, ventas y equipos en una sola plataforma para que tomes mejores decisiones.', 'DAILY connects your stores, inventory, sales and teams in a single platform so you make better decisions.') }}</p>
			<ul class="s-checks s-checks--fit">
				<li>
					<svg class="s-ico"><use href="#i-check"/></svg>
					<div>
						<strong>{{ $L('Control centralizado', 'Centralized control') }}</strong>
						<p>{{ $L('Todas tus sucursales y bodegas en una sola vista.', 'All your stores and warehouses in a single view.') }}</p>
					</div>
				</li>
				<li>
					<svg class="s-ico"><use href="#i-check"/></svg>
					<div>
						<strong>{{ $L('Menos pérdidas', 'Less shrink') }}</strong>
						<p>{{ $L('Identifica mermas, causas y responsables.', 'Pinpoint shrink, root causes and owners.') }}</p>
					</div>
				</li>
				<li>
					<svg class="s-ico"><use href="#i-check"/></svg>
					<div>
						<strong>{{ $L('Inventario confiable', 'Reliable inventory') }}</strong>
						<p>{{ $L('Conteos digitales y trazabilidad de movimientos.', 'Digital counts and full movement traceability.') }}</p>
					</div>
				</li>
				<li>
					<svg class="s-ico"><use href="#i-check"/></svg>
					<div>
						<strong>{{ $L('Mercancía protegida', 'Protected merchandise') }}</strong>
						<p>{{ $L('Control de productos sensibles por IMEI y serial.', 'Track high-value goods by IMEI and serial number.') }}</p>
					</div>
				</li>
				<li>
					<svg class="s-ico"><use href="#i-check"/></svg>
					<div>
						<strong>{{ $L('Personal optimizado', 'Optimized workforce') }}</strong>
						<p>{{ $L('Turnos y cobertura alineados con la demanda.', 'Shifts and coverage aligned with store demand.') }}</p>
					</div>
				</li>
			</ul>
			<div class="s-actions">
				<a class="s-btn s-btn--primary" href="{{ $U('/demo') }}">{{ $L('Quiero una demo', 'I want a demo') }}</a>
				<a class="s-btn s-btn--outline" href="{{ $U('/industries') }}">{{ $L('Ver industrias', 'See industries') }}</a>
			</div>
		</div>
	</div>
</section>

{{-- 6. Industrias / necesidades --}}
<section class="s-section s-seg" aria-labelledby="seg-title">
	<div class="s-wrap">
		<div class="s-seg__box" data-reveal>
			<div class="s-seg__head">
				<div>
					<span class="s-eyebrow s-eyebrow--light">{{ $L('Soluciones', 'Solutions') }}</span>
					<h2 id="seg-title">{{ $L('Una operación, no una colección de módulos', 'One operation, not a pile of modules') }}</h2>
				</div>
				<div class="s-switch" role="tablist" aria-label="{{ $L('Ver por', 'View by') }}">
					<button role="tab" aria-selected="true" aria-controls="seg-ind" id="seg-t1">{{ $L('Por industria', 'By industry') }}</button>
					<button role="tab" aria-selected="false" aria-controls="seg-need" id="seg-t2" tabindex="-1">{{ $L('Por necesidad', 'By need') }}</button>
				</div>
			</div>
			<div class="s-seg__cards" id="seg-ind" role="tabpanel" aria-labelledby="seg-t1">
				@foreach($c['industries'] as $slug => $ind)
				<a class="s-seg__card" href="{{ $U('/industries/' . $slug) }}"><svg class="s-ico s-ico--lg"><use href="#i-{{ $ind['icon'] }}"/></svg><strong>{{ $ind['name'] }}</strong><small>{{ $ind['lead'] }}</small></a>
				@endforeach
			</div>
			<div class="s-seg__cards" id="seg-need" role="tabpanel" aria-labelledby="seg-t2" hidden>
				@foreach($c['solutions'] as $s)
				<a class="s-seg__card" href="{{ $U('/features/' . $s['module']) }}"><svg class="s-ico s-ico--lg"><use href="#i-{{ $s['icon'] }}"/></svg><strong>{{ $s['title'] }}</strong><small>{{ $s['text'] }}</small></a>
				@endforeach
			</div>
		</div>
	</div>
</section>

{{-- 7. Beneficios --}}
<section class="s-section s-benefits" aria-labelledby="ben-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal>
			<span class="s-eyebrow"><svg class="s-ico"><use href="#i-spark"/></svg>{{ $L('Beneficios', 'Benefits') }}</span>
			<h2 id="ben-title">{{ $L('¿Por qué elegir DAILY?', 'Why choose DAILY?') }}</h2>
		</div>
		<div class="s-ben__layout">
			<div class="s-ben__grid">
				@foreach($c['benefits'] as $b)
				<div class="s-ben" data-reveal><span class="s-badge s-badge--soft"><svg class="s-ico"><use href="#i-{{ $b['icon'] }}"/></svg></span><h3>{{ $b['title'] }}</h3><p>{{ $b['text'] }}</p></div>
				@endforeach
			</div>
			<aside class="s-example" data-reveal aria-labelledby="ex-title">
				<span class="s-tag">{{ $L('Ejemplo ilustrativo · datos ficticios', 'Illustrative example · fictitious data') }}</span>
				<h3 id="ex-title">{{ $L('Pareto de merma: dónde actuar primero', 'Shrink Pareto: where to act first') }}</h3>
				<p>{{ $L('Así presenta DAILY la merma por causal: pocas causas suelen concentrar la mayor parte del valor perdido.', 'This is how DAILY presents shrink by cause: a few causes usually concentrate most of the lost value.') }}</p>
				<div class="s-pareto" role="img" aria-label="{{ $L('Gráfico ilustrativo de barras de merma por causal', 'Illustrative bar chart of shrink by cause') }}">
					@foreach([[$L('Vencimiento', 'Expiry'), 42], [$L('Daño', 'Damage'), 23], [$L('Faltante', 'Missing'), 15], [$L('Error adm.', 'Admin error'), 11], [$L('Otros', 'Other'), 9]] as [$lbl, $v])
					<div class="s-pareto__row"><span>{{ $lbl }}</span><i style="--w:{{ $v * 2.2 }}%"></i><b>{{ $v }} %</b></div>
					@endforeach
				</div>
				<a class="s-link" href="{{ $U('/features/shrink') }}">{{ $L('Ver Mermas y Prevención', 'See Shrink Control') }}<svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></a>
			</aside>
		</div>
	</div>
</section>

{{-- 8. Calculadora --}}
@include('site.partials.calculator')

{{-- 9. Planes --}}
<section class="s-section s-pricing" id="plans" aria-labelledby="plans-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal>
			<span class="s-eyebrow"><svg class="s-ico"><use href="#i-tag"/></svg>{{ $L('Planes', 'Plans') }}</span>
			<h2 id="plans-title">{{ $L('Un plan para cada etapa de tu operación', 'A plan for every stage of your operation') }}</h2>
			<p>{{ $L('Los precios se cotizan según sucursales y módulos.', 'Prices are quoted based on branches and modules.') }} <a class="s-link" href="{{ $U('/pricing') }}#compare">{{ $L('Ver comparativa completa', 'See full comparison') }}<svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></a></p>
		</div>
		@include('site.partials.plans')
	</div>
</section>

{{-- 10. Implementación --}}
<section class="s-section s-steps" aria-labelledby="steps-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal>
			<span class="s-eyebrow">{{ $L('Implementación', 'Implementation') }}</span>
			<h2 id="steps-title">{{ $L('De la demostración a la operación', 'From demo to day-to-day operation') }}</h2>
		</div>
		<ol class="s-steps__list">
			@foreach([[$L('Diagnóstico', 'Assessment'), $L('Revisamos tus procesos, sucursales y prioridades.', 'We review your processes, branches and priorities.')], [$L('Carga de datos', 'Data load'), $L('Productos, existencias, sucursales y personal desde archivos.', 'Products, stock, branches and staff from files.')], [$L('Configuración', 'Configuration'), $L('Roles, permisos por sucursal, causales y reglas.', 'Roles, branch permissions, causes and rules.')], [$L('Capacitación y arranque', 'Training & go-live'), $L('Formación por rol y acompañamiento en los primeros cierres.', 'Role-based training and support through the first closings.')]] as $i => [$st, $tx])
			<li data-reveal><span class="s-steps__n">{{ $i + 1 }}</span><h3>{{ $st }}</h3><p>{{ $tx }}</p></li>
			@endforeach
		</ol>
	</div>
</section>

{{-- 11. FAQ --}}
@include('site.partials.faq')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Reloj y fecha de Miami en tiempo real en el celular
	function updateMiamiRealtime() {
		try {
			var now = new Date();
			// Hora de Miami (Eastern Time)
			var timeStr = new Intl.DateTimeFormat('en-US', {
				timeZone: 'America/New_York',
				hour: 'numeric',
				minute: '2-digit',
				hour12: false
			}).format(now);

			// Fecha de Miami formateada
			var isEn = document.documentElement.lang === 'en' || window.location.pathname.startsWith('/en');
			var dateStr = new Intl.DateTimeFormat(isEn ? 'en-US' : 'es-ES', {
				timeZone: 'America/New_York',
				weekday: 'short',
				month: 'short',
				day: 'numeric',
				year: 'numeric'
			}).format(now) + ' | Miami Beach';

			document.querySelectorAll('[data-miami-time]').forEach(function(el) {
				el.textContent = timeStr;
			});
			document.querySelectorAll('[data-miami-date]').forEach(function(el) {
				el.textContent = dateStr;
			});
		} catch(e) {}
	}
	updateMiamiRealtime();
	setInterval(updateMiamiRealtime, 5000);

	var animPhone = document.querySelector('.s-phone--anim');
	if (animPhone) {
		animPhone.title = "{{ $L('Haz clic para pausar o reanudar la animación del escáner', 'Click to pause or resume scanner animation') }}";
		animPhone.addEventListener('click', function(e) {
			e.preventDefault();
			var isPaused = this.classList.toggle('is-paused');
			var layers = this.querySelectorAll('[style*="animation"], .s-phone__layer, .s-phone__tap-circle, .s-phone__laser, .s-phone__hud-target, .s-phone__sheet');
			layers.forEach(function(el) {
				el.style.animationPlayState = isPaused ? 'paused' : 'running';
			});
		});
	}
});
</script>
@endpush
@endsection
