@php
	// Vista animada de un módulo (recorrido). Datos: Grocy\Helpers\DailySiteLive. El HTML ya trae los valores finales:
	// sin JS o con movimiento reducido se ve el estado final; daily_site_live.js anima desde cero.
	$LV = \Grocy\Helpers\DailySiteLive::class;
	$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	$b = $board;
	$num = fn ($v, $d = 0, $p = '', $s = '') => '<span class="lv-num" data-v="' . $v . '" data-d="' . $d . '" data-p="' . $e($p) . '" data-s="' . $e($s) . '">' . $e($LV::Num($v, $d, $p, $s)) . '</span>';
	$palette = ['green' => 'var(--lv-green)', 'g2' => 'var(--lv-g2)', 'teal' => 'var(--lv-teal)', 'soft' => 'var(--lv-soft)', 'gray' => 'var(--lv-gray)', 'amber' => 'var(--lv-amber)', 'red' => 'var(--lv-red)'];
	$zoomBase = '/img/site/mod_' . $key;
@endphp
<figure class="s-live" data-live="{{ $key }}" data-zoom="{{ $U($zoomBase . '_2400.webp') }}" data-zoom-m="{{ $U($zoomBase . '_m.webp') }}" data-alt="{{ $alt }}">
	<div class="lv" style="--cols: {{ $b['cols'] }}">
		@if(!empty($b['flow']))
		<ol class="lv-flow" style="--n: {{ count($b['flow']) }}">
			<i class="lv-flow__track"><i class="lv-fill" style="--w: 1"></i></i>
			@foreach($b['flow'] as $i => $f)
			<li class="lv-in" style="--i: {{ $i }}"><b>{{ $i + 1 }}</b><span>{{ $f }}</span></li>
			@endforeach
		</ol>
		@endif
		<div class="lv-kpis">
			@foreach($b['kpis'] as $i => $kp)
			<div class="lv-kpi lv-in{{ !empty($kp['pulse']) ? ' lv-alert' : '' }}" style="--i: {{ $i }}">
				<span class="lv-kpi__l">{{ $kp['l'] }}</span>
				<strong class="lv-kpi__v">{!! $num($kp['v'], $kp['d'], $kp['p'], $kp['s']) !!}</strong>
				@if(isset($kp['bar']))
				<span class="lv-meter lv-meter--{{ $kp['bt'] }}"><i class="lv-fill" style="--w: {{ $kp['bar'] }}"></i>@if(isset($kp['mark']))<em style="left: {{ round($kp['mark'] * 100, 1) }}%"></em>@endif</span>
				@endif
				<small class="lv-note lv-t-{{ $kp['t'] }}">{{ $kp['n'] }}</small>
			</div>
			@endforeach
		</div>
		<div class="lv-grid">
			@foreach($b['w'] as $wi => $w)
			@php $uid = 'lv-' . $key . '-' . $wi; @endphp
			<section class="lv-card lv-in lv-card--{{ $w['type'] }}" style="--span: {{ $w['span'] }}; --i: {{ count($b['kpis']) + $wi }}">
				<header class="lv-card__h"><h4>{{ $w['title'] }}</h4>
					@if(!empty($w['modes']))
					<span class="lv-seg" role="group" aria-label="{{ $L('Métrica', 'Metric') }}">@foreach($w['modes'] as $mi => $mn)<button type="button" data-mode="{{ $mi }}" aria-pressed="{{ $mi === 0 ? 'true' : 'false' }}">{{ $mn }}</button>@endforeach</span>
					@elseif(!empty($w['badge']))<span class="lv-chip">{{ $w['badge'] }}</span>
					@elseif(!empty($w['note']))<span class="lv-muted">{{ $w['note'] }}</span>
					@endif
				</header>

				@if($w['type'] === 'line')
				@php
					$W = $w['span'] >= 7 ? 440 : 340; $H = 180; $l = 40; $r = 10; $tp = 10; $bt = 22;
					$pw = $W - $l - $r; $ph = $H - $tp - $bt; $n = count($w['x']);
					$X = fn ($i) => $l + $i * $pw / max(1, $n - 1);
					$Y = fn ($v) => $tp + $ph * (1 - $v / $w['ymax']);
				@endphp
				<div class="lv-chart">
					<svg viewBox="0 0 {{ $W }} {{ $H }}" aria-hidden="true">
						@foreach($w['yt'] as $yv)
						<line class="lv-grid-l" x1="{{ $l }}" x2="{{ $W - $r }}" y1="{{ $Y($yv) }}" y2="{{ $Y($yv) }}"/>
						<text class="lv-ax" x="{{ $l - 6 }}" y="{{ $Y($yv) + 3.5 }}" text-anchor="end">{{ $LV::Axis($yv, $w['yf']) }}</text>
						@endforeach
						@foreach($w['xt'] as $xi)
						<text class="lv-ax" x="{{ $X($xi) }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $w['x'][$xi] }}</text>
						@endforeach
						@if(!empty($w['ref']))
						<line class="lv-ref lv-reveal" x1="{{ $l }}" x2="{{ $W - $r }}" y1="{{ $Y($w['ref']['v']) }}" y2="{{ $Y($w['ref']['v']) }}"/>
						@endif
						@foreach($w['series'] as $si => $s)
						@php
							$pts = [];
							foreach ($s['v'] as $i => $v) { if ($v !== null) { $pts[] = [$X($i), $Y($v)]; } }
							$d = $LV::Path($pts, $tp, $tp + $ph);
							$tone = $palette[$s['tone']];
						@endphp
						@if(!empty($s['area']))
						<path class="lv-area lv-reveal" d="{{ $d }} L{{ end($pts)[0] }} {{ $tp + $ph }} L{{ $pts[0][0] }} {{ $tp + $ph }} Z" fill="url(#{{ $uid }}-g)"/>
						<defs><linearGradient id="{{ $uid }}-g" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="{{ $tone }}" stop-opacity=".22"/><stop offset="1" stop-color="{{ $tone }}" stop-opacity="0"/></linearGradient></defs>
						@endif
						<path class="lv-line lv-reveal{{ !empty($s['dash']) ? ' lv-line--dash' : '' }}" d="{{ $d }}" style="stroke: {{ $tone }}; --i: {{ $si }}"/>
						@if(!empty($s['dots']))
						@foreach($pts as $pi => $pt)<circle class="lv-dot" cx="{{ $pt[0] }}" cy="{{ $pt[1] }}" r="3.2" style="fill: {{ $tone }}; --i: {{ $pi }}"/>@endforeach
						@endif
						@endforeach
					</svg>
					@if(!empty($w['aside']))
					<aside class="lv-aside lv-in" style="--i: 6"><span>{{ $w['aside']['l'] }}</span><strong>{!! $num($w['aside']['v'], $w['aside']['d'], $w['aside']['p']) !!}</strong><small class="lv-t-up">{{ $w['aside']['n'] }}</small></aside>
					@endif
				</div>
				<p class="lv-legend">
					@foreach($w['series'] as $s)<span><i style="background: {{ $palette[$s['tone']] }}"></i>{{ $s['name'] }}</span>@endforeach
					@if(!empty($w['ref']))<span><i class="lv-i-dash"></i>{{ $w['ref']['name'] }}</span>@endif
				</p>

				@elseif($w['type'] === 'cols')
				@php
					$W = $w['span'] >= 7 ? 440 : 360; $H = 180; $l = 40; $r = !empty($w['line']['max']) ? 12 : 10; $tp = 14; $bt = 22;
					$pw = $W - $l - $r; $ph = $H - $tp - $bt; $n = count($w['x']); $gw = $pw / $n; $k = count($w['bars']);
					$bw = min(34, $gw * .62 / $k);
					$Y = fn ($v, $max = null) => $tp + $ph * (1 - $v / ($max ?? $w['ymax']));
				@endphp
				<div class="lv-chart">
					<svg viewBox="0 0 {{ $W }} {{ $H }}" aria-hidden="true">
						@foreach($w['yt'] as $yv)
						<line class="lv-grid-l" x1="{{ $l }}" x2="{{ $W - $r }}" y1="{{ $Y($yv) }}" y2="{{ $Y($yv) }}"/>
						<text class="lv-ax" x="{{ $l - 6 }}" y="{{ $Y($yv) + 3.5 }}" text-anchor="end">{{ $LV::Axis($yv, $w['yf']) }}</text>
						@endforeach
						@foreach($w['x'] as $i => $xl)
						<text class="lv-ax" x="{{ $l + ($i + .5) * $gw }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $xl }}</text>
						@endforeach
						@foreach($w['bars'] as $bi => $bs)
						@foreach($bs['v'] as $i => $v)
						@php $bx = $l + ($i + .5) * $gw - $k * $bw / 2 - ($k - 1) + $bi * ($bw + 2); $by = $Y($v); $tone = $palette[$bs['tones'][$i] ?? $bs['tone']]; @endphp
						<rect class="lv-bar" x="{{ round($bx, 1) }}" y="{{ round($by, 1) }}" width="{{ round($bw, 1) }}" height="{{ round(max(1, $tp + $ph - $by), 1) }}" rx="3" style="fill: {{ $tone }}; --i: {{ $i * $k + $bi }}"/>
						@if(!empty($bs['lbl']))<text class="lv-val lv-in" x="{{ round($bx + $bw / 2, 1) }}" y="{{ round($by - 4, 1) }}" text-anchor="middle" style="--i: {{ $i + 4 }}">{{ $bs['lbl'][$i] }}</text>@endif
						@endforeach
						@endforeach
						@if(!empty($w['line']))
						@php
							$ln = $w['line']; $pts = [];
							foreach ($ln['v'] as $i => $v) { $pts[] = [$l + ($i + .5) * $gw, $Y($v, $ln['max'] ?? null)]; }
							$tone = $palette[$ln['tone']];
						@endphp
						<path class="lv-line lv-reveal lv-late" d="{{ $LV::Path($pts, $tp, $tp + $ph) }}" style="stroke: {{ $tone }}"/>
						@foreach($pts as $pi => $pt)<circle class="lv-dot lv-late" cx="{{ round($pt[0], 1) }}" cy="{{ round($pt[1], 1) }}" r="3" style="fill: #fff; stroke: {{ $tone }}; --i: {{ $pi }}"/>@endforeach
						@endif
					</svg>
				</div>
				<p class="lv-legend">
					@foreach($w['bars'] as $bs)<span><i style="background: {{ $palette[$bs['tone']] }}"></i>{{ $bs['name'] }}</span>@endforeach
					@if(!empty($w['line']))<span><i class="lv-i-line" style="background: {{ $palette[$w['line']['tone']] }}"></i>{{ $w['line']['name'] }}</span>@endif
				</p>

				@elseif($w['type'] === 'donut')
				@php $tones = $w['tones'] ?? ['green', 'g2', 'teal', 'soft', 'gray']; $acc = 0; @endphp
				<div class="lv-donut">
					<div class="lv-donut__ring">
						<svg viewBox="0 0 120 120" aria-hidden="true">
							<mask id="{{ $uid }}-m"><circle class="lv-sweep" cx="60" cy="60" r="46" pathLength="100"/></mask>
							<circle cx="60" cy="60" r="46" class="lv-donut__bg"/>
							<g class="lv-rot" mask="url(#{{ $uid }}-m)">
								@foreach($w['segs'] as $si => $sg)
								@if($sg['pct'] > 0)
								<circle class="lv-seg-arc" data-seg="{{ $si }}" cx="60" cy="60" r="46" pathLength="100" stroke-dasharray="{{ max(.1, $sg['pct'] - .6) }} 100" stroke-dashoffset="{{ -$acc }}" style="stroke: {{ $palette[$tones[$si]] }}"/>
								@endif
								@php $acc += $sg['pct']; @endphp
								@endforeach
							</g>
						</svg>
						<div class="lv-donut__c" data-total-v="{{ $w['center']['v'] }}" data-total-n="{{ $w['center']['n'] }}"><strong>{!! $num($w['center']['v'], $w['center']['d'], $w['center']['p']) !!}</strong><small>{{ $w['center']['n'] }}</small></div>
					</div>
					<ul class="lv-dlist">
						@foreach($w['segs'] as $si => $sg)
						<li class="lv-in" style="--i: {{ $si + 3 }}">
							@if(!empty($w['pick']))<button type="button" data-seg="{{ $si }}" data-v="{{ $sg['v'] }}" data-pct="{{ $sg['pct'] }}" aria-pressed="false">@else<span>@endif
							<i style="background: {{ $palette[$tones[$si]] }}"></i><span class="lv-dlist__l">{{ $sg['l'] }}</span><em>{{ number_format($sg['pct'], 1) }}%</em><b>{{ $LV::Num($sg['v'], 0, '$') }}</b>
							@if(!empty($w['pick']))</button>@else</span>@endif
						</li>
						@endforeach
					</ul>
				</div>

				@elseif($w['type'] === 'hbars')
				@php $mx = max(array_map(fn ($r) => $r['v'][0], $w['rows'])); @endphp
				<ul class="lv-hb">
					@foreach($w['rows'] as $ri => $row)
					<li class="lv-in" style="--i: {{ $ri + 2 }}" data-vals="{{ json_encode($row['v']) }}" data-ps="{{ json_encode($row['p']) }}" data-ds="{{ json_encode($row['d']) }}" data-ss="{{ json_encode($row['s'] ?? ['', '']) }}">
						<span class="lv-hb__l" title="{{ $row['l'] }}">{{ $row['l'] }}</span>
						<span class="lv-hb__t"><i class="lv-fill lv-fill--{{ $row['tone'] ?? 'green' }}" style="--w: {{ round($row['v'][0] / $mx, 4) }}; --i: {{ $ri }}"></i></span>
						<b>{!! $num($row['v'][0], $row['d'][0], $row['p'][0], $row['s'][0] ?? '') !!}</b>
						@if(!empty($row['x']))<em>{{ $row['x'] }}</em>@endif
					</li>
					@endforeach
				</ul>
				@if(!empty($w['status']))
				<div class="lv-status lv-in" style="--i: 6">
					<span>{{ $w['status']['l'] }}</span><strong class="lv-t-warn">{{ $w['status']['v'] }}</strong>
					@foreach($w['status']['rows'] as [$sl, $sv])<small>{{ $sl }} <b>{{ $sv }}</b></small>@endforeach
				</div>
				@endif

				@elseif($w['type'] === 'goal')
				<div class="lv-goal">
					<div class="lv-goal__bar"><span class="lv-meter lv-meter--lg lv-meter--green"><i class="lv-fill" style="--w: {{ $w['pct'] / 100 }}"></i></span><strong class="lv-t-up">{!! $num($w['pct'], 1, '', '%') !!}</strong></div>
					<div class="lv-goal__stats">
						@foreach($w['stats'] as $si => $st)
						<div class="lv-in" style="--i: {{ $si + 6 }}"><b class="lv-t-{{ $st['t'] ?? 'ink' }}">{!! $num($st['v'], $st['d'], $st['p'] ?? '', $st['s'] ?? '') !!}</b><small>{{ $st['l'] }}</small></div>
						@endforeach
					</div>
				</div>

				@elseif($w['type'] === 'alerts')
				<div class="lv-chips">
					@foreach($w['chips'] as $ci => $ch)
					<span class="lv-tag lv-tag--{{ $ch['tone'] }} lv-in" style="--i: {{ $ci + 2 }}"><small>{{ $ch['l'] }}</small>{!! $num($ch['v']) !!}</span>
					@endforeach
				</div>
				<table class="lv-table">
					<thead><tr>@foreach($w['head'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
					<tbody>
						@foreach($w['rows'] as $ri => [$pn, $st, $mn])
						<tr class="lv-in lv-crit" style="--i: {{ $ri + 5 }}"><td>{{ $pn }}</td><td><span class="lv-stock"><i class="lv-fill lv-fill--red" style="--w: {{ $st / $mn }}"></i></span><b class="lv-t-down">{{ $st }}</b></td><td>{{ $mn }}</td></tr>
						@endforeach
					</tbody>
				</table>

				@elseif($w['type'] === 'order')
				<p class="lv-order__sub">{{ $w['sub'] }} @foreach($w['tags'] as [$tg, $tt])<span class="lv-tag lv-tag--{{ $tt }} lv-tag--sm">{{ $tg }}</span>@endforeach</p>
				<ol class="lv-steps" style="--n: {{ count($w['steps']) }}; --cur: {{ $w['cur'] }}">
					<i class="lv-steps__track"><i class="lv-fill" style="--w: {{ $w['cur'] / (count($w['steps']) - 1) }}"></i></i>
					@foreach($w['steps'] as $si => $sp)
					<li class="{{ $si < $w['cur'] ? 'is-done' : ($si === $w['cur'] ? 'is-cur' : '') }}" style="--i: {{ $si }}"><b>@if($si < $w['cur'])✓@else{{ $si + 1 }}@endif</b><span>{{ $sp }}</span></li>
					@endforeach
				</ol>
				<div class="lv-goal__stats lv-goal__stats--3">
					@foreach($w['stats'] as $si => $st)
					<div class="lv-in" style="--i: {{ $si + 8 }}"><b class="lv-t-{{ $st['t'] ?? 'ink' }}">{!! $num($st['v'], $st['d'], $st['p'] ?? '') !!}</b><small>{{ $st['l'] }}</small></div>
					@endforeach
				</div>

				@elseif($w['type'] === 'list')
				<ul class="lv-list">
					@foreach($w['rows'] as $ri => $row)
					<li class="lv-in{{ !empty($row['pulse']) ? ' lv-alert' : '' }}" style="--i: {{ $ri + 3 }}"><span class="lv-list__l">{{ $row['l'] }}<small>{{ $row['o'] }}</small></span><span class="lv-tag lv-tag--{{ $row['tone'] }} lv-tag--sm">{{ $row['s'] }}</span></li>
					@endforeach
				</ul>
				@endif
			</section>
			@endforeach
		</div>
	</div>
	<figcaption class="s-live__bar">
		<span class="s-live__badge"><i></i>{{ $L('Datos de la empresa demo', 'Demo company data') }}</span>
		<button class="s-live__replay" type="button"><svg class="s-ico s-ico--sm"><use href="#i-play"/></svg>{{ $L('Repetir animación', 'Replay animation') }}</button>
		<button class="s-pv__zoom s-live__zoom" type="button" aria-label="{{ $L('Ver captura real', 'See real screenshot') }}: {{ $alt }}"><svg class="s-ico s-ico--sm"><use href="#i-eye"/></svg><span>{{ $L('Ver captura real', 'See real screenshot') }}</span></button>
	</figcaption>
</figure>
