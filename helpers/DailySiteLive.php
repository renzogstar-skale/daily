<?php

namespace Grocy\Helpers;

/**
 * Vistas animadas del recorrido "DAILY en acción" (sitio comercial).
 * Cifras transcritas de las capturas de la empresa demo (public/img/site/mod_*_2400.webp, v3 y /dispatches del 05/11/2026).
 * KPI, tablas y leyendas: valores exactos de la captura. Puntos de curvas y barras sin etiqueta: lectura del gráfico.
 * No agregar cifras que no estén en esas pantallas.
 */
class DailySiteLive
{
	public static function Get(string $lang): array
	{
		$t = fn (string $es, string $en) => $lang === 'en' ? $en : $es;
		$k = fn (string $l, float $v, string $p = '', string $s = '', int $d = 0, string $n = '', string $tone = 'mute', array $x = []) => ['l' => $l, 'v' => $v, 'p' => $p, 's' => $s, 'd' => $d, 'n' => $n, 't' => $tone] + $x;

		return [
			'exec' => [
				'cols' => 4,
				'kpis' => [
					$k($t('Ventas netas', 'Net sales'), 39654, '$', '', 0, $t('↑ +119.7% vs. martes anterior', '↑ +119.7% vs. last Tuesday'), 'up'),
					$k($t('Cumplimiento meta', 'Target attainment'), 100.0, '', '%', 1, $t('✓ Real: 174.1% · +74.1% sobre meta', '✓ Actual: 174.1% · +74.1% over target'), 'up', ['bar' => 1, 'bt' => 'green']),
					$k($t('Margen bruto', 'Gross margin'), 41.7, '', '%', 1, $t('↓ -0.1 pp vs. martes anterior', '↓ -0.1 pp vs. last Tuesday'), 'down'),
					$k($t('Utilidad bruta', 'Gross profit'), 16538, '$', '', 0, $t('↑ +119.1% venta neta − costo', '↑ +119.1% net sales − cost'), 'up'),
					$k($t('Ticket promedio', 'Average ticket'), 535.87, '$', '', 2, $t('↑ +211.8% venta neta ÷ tickets', '↑ +211.8% net sales ÷ tickets'), 'up'),
					$k($t('Transacciones', 'Transactions'), 74, '', '', 0, $t('↓ -29.5% tickets completados', '↓ -29.5% completed tickets'), 'down'),
					$k($t('Venta por hora laboral', 'Sales per labor hour'), 75.99, '$', '', 2, $t('↑ +97.5% · 521.8 h transcurridas', '↑ +97.5% · 521.8 h elapsed'), 'up'),
					$k($t('Merma sobre ventas', 'Shrink on sales'), 0.1, '', '%', 1, $t('↓ -0.1 pp · objetivo ≤ 2.0%', '↓ -0.1 pp · target ≤ 2.0%'), 'up'),
				],
				'w' => [
					['type' => 'line', 'span' => 7, 'title' => $t('Ventas reales vs. meta por hora', 'Actual sales vs. hourly target'),
						'x' => ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'], 'xt' => [0, 1, 2, 3, 4, 5, 6],
						'ymax' => 40000, 'yt' => [0, 10000, 20000, 30000, 40000], 'yf' => 'k',
						'series' => [
							['name' => $t('Venta real', 'Actual sales'), 'tone' => 'green', 'area' => true, 'dots' => true, 'v' => [0, 150, 8200, 15500, 16700, 28700, null]],
							['name' => $t('Meta', 'Target'), 'tone' => 'gray', 'dash' => true, 'v' => [null, 0, 4000, 8800, 11500, 16800, 22500]],
						],
						'aside' => ['l' => $t('Proyección de cierre', 'Closing forecast'), 'v' => 46148, 'p' => '$', 'd' => 0, 'n' => $t('+102.6% vs. meta del día', '+102.6% vs. daily target')]],
					['type' => 'donut', 'span' => 5, 'title' => $t('Ventas por departamento', 'Sales by department'), 'pick' => true,
						'center' => ['v' => 39857, 'p' => '$', 'd' => 0, 'n' => '100%'],
						'segs' => [
							['l' => $t('Tecnología', 'Technology'), 'v' => 23297, 'pct' => 58.5],
							['l' => $t('Electrodomésticos', 'Appliances'), 'v' => 11628, 'pct' => 29.2],
							['l' => $t('Accesorios', 'Accessories'), 'v' => 3544, 'pct' => 8.9],
							['l' => $t('Hogar', 'Home'), 'v' => 436, 'pct' => 1.1],
							['l' => $t('Otros 6 departamentos', 'Other 6 departments'), 'v' => 953, 'pct' => 2.4],
						]],
				],
			],
			'sales' => [
				'cols' => 3,
				'kpis' => [
					$k($t('Ventas netas', 'Net sales'), 660072.98, '$', '', 2, $t('▲ +1,411.3% vs. periodo anterior', '▲ +1,411.3% vs. previous period'), 'up'),
					$k($t('Cumplimiento', 'Attainment'), 96.6, '', '%', 1, $t('▼ -3.4 pp · meta 100.0%', '▼ -3.4 pp · target 100.0%'), 'down', ['bar' => .966, 'bt' => 'green']),
					$k($t('Utilidad bruta', 'Gross profit'), 231932.44, '$', '', 2, $t('▲ +1,217.5% vs. periodo anterior', '▲ +1,217.5% vs. previous period'), 'up'),
					$k($t('Margen bruto', 'Gross margin'), 35.1, '', '%', 1, $t('▼ -5.2 pp vs. periodo anterior', '▼ -5.2 pp vs. previous period'), 'down'),
					$k($t('Transacciones', 'Transactions'), 5063, '', '', 0, $t('▲ +79.8% vs. periodo anterior', '▲ +79.8% vs. previous period'), 'up'),
					$k($t('Ticket promedio', 'Average ticket'), 130.37, '$', '', 2, $t('▲ +740.6% vs. periodo anterior', '▲ +740.6% vs. previous period'), 'up'),
				],
				'w' => [
					['type' => 'line', 'span' => 7, 'title' => $t('Curva de ventas · últimos 30 días', 'Sales curve · last 30 days'),
						'x' => ['05 Oct', '', '', '08 Oct', '', '', '11 Oct', '', '', '14 Oct', '', '', '17 Oct', '', '', '20 Oct', '', '', '23 Oct', '', '', '26 Oct', '', '', '29 Oct', '', '', '01 Nov', '', ''],
						'xt' => [0, 6, 12, 18, 24], 'ymax' => 35000, 'yt' => [0, 10000, 20000, 30000], 'yf' => 'k',
						'series' => [
							['name' => $t('Ventas actuales', 'Current sales'), 'tone' => 'green', 'area' => true, 'v' => [1000, 800, 900, 1000, 1100, 1500, 2700, 900, 26500, 28800, 28000, 17500, 30500, 30000, 27000, 24000, 23500, 30500, 22900, 30000, 29000, 27500, 25000, 23000, 26000, 32000, 34500, 27500, 27500, 31200]],
						],
						'ref' => ['v' => 22780.67, 'name' => $t('Meta del periodo', 'Period target')]],
					['type' => 'hbars', 'span' => 5, 'title' => $t('Top 5 productos', 'Top 5 products'),
						'modes' => [$t('Ventas netas', 'Net sales'), $t('Unidades', 'Units')],
						'rows' => [
							['l' => 'Laptop Dell XPS 15 OLED', 'v' => [17820.49, 891], 'p' => ['$', ''], 'd' => [2, 0]],
							['l' => 'Laptop MacBook Pro 14 M3 Pro', 'v' => [16479.62, 729], 'p' => ['$', ''], 'd' => [2, 0]],
							['l' => 'Smartphone Pro 5G 128GB', 'v' => [14762.84, 19], 'p' => ['$', ''], 'd' => [2, 0]],
							['l' => 'iPhone 15 Pro Max 256GB', 'v' => [11543.37, 810], 'p' => ['$', ''], 'd' => [2, 0]],
							['l' => 'Laptop Lenovo Legion Pro 5', 'v' => [9404.34, 486], 'p' => ['$', ''], 'd' => [2, 0]],
						]],
					['type' => 'goal', 'span' => 12, 'title' => $t('Avance frente a la meta de ventas', 'Progress against the sales target'), 'pct' => 96.6,
						'stats' => [
							['l' => $t('Ventas del periodo', 'Period sales'), 'v' => 660072.98, 'p' => '$', 'd' => 2],
							['l' => $t('Meta del periodo', 'Period target'), 'v' => 683420.00, 'p' => '$', 'd' => 2],
							['l' => $t('Brecha vs. meta', 'Gap to target'), 'v' => 23347.02, 'p' => '$', 'd' => 2, 't' => 'warn'],
							['l' => $t('Días restantes', 'Days left'), 'v' => 0, 's' => $t(' días', ' days'), 'd' => 0],
						]],
				],
			],
			'inventory' => [
				'cols' => 4,
				'kpis' => [
					$k($t('Valor del inventario', 'Inventory value'), 14243800, '$', '', 0, $t('↑ +41,394.9% vs. inicio', '↑ +41,394.9% vs. start'), 'up'),
					$k($t('Unidades en stock', 'Units in stock'), 142780, '', '', 0, $t('699 productos con existencias', '699 products in stock'), 'mute'),
					$k($t('Rotación de inventario', 'Inventory turnover'), 1.08, '', '', 2, $t('↓ -97.0% · objetivo 2.0×', '↓ -97.0% · target 2.0×'), 'down', ['bar' => .54, 'bt' => 'amber']),
					$k($t('Cobertura promedio', 'Average cover'), 27.3, '', $t(' días', ' days'), 1, $t('Objetivo: 10–15 días', 'Target: 10–15 days'), 'warn', ['bar' => .91, 'bt' => 'amber', 'mark' => .5]),
					$k($t('Productos bajo mínimo', 'Below minimum'), 3208, '', '', 0, $t('1,032 críticos · 2,176 preventivos', '1,032 critical · 2,176 preventive'), 'down'),
					$k($t('Próximos a vencer', 'Expiring soon'), 1043, '', '', 0, $t('104 en 0–7 días · 909 en 16–30', '104 in 0–7 days · 909 in 16–30'), 'warn'),
					$k($t('Productos agotados', 'Out of stock'), 613, '', '', 0, $t('↓ -89.8% · de 6,743 pares', '↓ -89.8% · of 6,743 pairs'), 'up'),
					$k($t('Valor en riesgo', 'Value at risk'), 1434659, '$', '', 0, $t('Vencimientos próximos 30 días', 'Expiries in the next 30 days'), 'down', ['pulse' => true]),
				],
				'w' => [
					['type' => 'cols', 'span' => 6, 'title' => $t('Evolución del valor del inventario', 'Inventory value over time'),
						'x' => [$t('Jun', 'Jun'), $t('Jul', 'Jul'), $t('Ago', 'Aug'), $t('Sep', 'Sep'), $t('Oct', 'Oct'), $t('Nov', 'Nov')],
						'ymax' => 15000000, 'yt' => [0, 5000000, 10000000, 15000000], 'yf' => 'M',
						'bars' => [['name' => $t('Valor del inventario', 'Inventory value'), 'tone' => 'green', 'v' => [6200000, 8000000, 10000000, 60000, 60000, 14243800]]],
						'line' => ['name' => $t('Periodo anterior', 'Previous period'), 'tone' => 'amber', 'v' => [6200000, 8000000, 10000000, 12100000, 10100000, 12200000]]],
					['type' => 'alerts', 'span' => 6, 'title' => $t('Productos en alerta', 'Products on alert'),
						'chips' => [
							['l' => $t('Bajo mínimo', 'Below minimum'), 'v' => 3208, 'tone' => 'green'],
							['l' => $t('Por vencer', 'Expiring'), 'v' => 1043, 'tone' => 'amber'],
							['l' => $t('Agotados', 'Out of stock'), 'v' => 613, 'tone' => 'red'],
						],
						'head' => [$t('Producto', 'Product'), $t('Stock', 'Stock'), $t('Mínimo', 'Minimum')],
						'rows' => [
							[$t('Agua Mineral Natural', 'Natural Mineral Water'), 4, 10],
							[$t('Jugo 100% Naranja', '100% Orange Juice'), 4, 10],
							[$t('Néctar de Durazno', 'Peach Nectar'), 4, 10],
							[$t('Gaseosa Refresco Cola', 'Cola Soft Drink'), 4, 10],
						]],
				],
			],
			'shrink' => [
				'cols' => 4,
				'kpis' => [
					$k($t('Merma total', 'Total shrink'), 1640, '$', '', 0, $t('↓ -4.1% vs. mismos días', '↓ -4.1% vs. same days'), 'up'),
					$k($t('Merma / ventas', 'Shrink / sales'), 1.61, '', '%', 2, $t('↑ +1.29 pp · meta ≤ 2.00%', '↑ +1.29 pp · target ≤ 2.00%'), 'down', ['bar' => .805, 'bt' => 'amber']),
					$k($t('Merma operativa', 'Operational shrink'), 1372, '$', '', 0, $t('↓ -14.5% · 83.7% del total', '↓ -14.5% · 83.7% of total'), 'up'),
					$k($t('Ajustes', 'Adjustments'), 168, '$', '', 0, $t('↑ +1,222.8% · 10.2% del total', '↑ +1,222.8% · 10.2% of total'), 'down'),
					$k($t('Recuperación financiera', 'Financial recovery'), 0, '$', '', 0, $t('Sin recuperaciones registradas', 'No recoveries recorded'), 'mute', ['bar' => 0, 'bt' => 'green']),
					$k($t('Pérdida evitable', 'Avoidable loss'), 226, '$', '', 0, $t('Merma por vencimiento', 'Expiry shrink'), 'down'),
					$k($t('Casos pendientes', 'Pending cases'), 8, '', $t(' casos', ' cases'), 0, $t('● 3 críticos · investigación activa', '● 3 critical · active investigation'), 'down', ['pulse' => true]),
					$k($t('Planes de acción', 'Action plans'), 94.2, '', '%', 1, $t('✓ 16/17 cerrados a tiempo', '✓ 16/17 closed on time'), 'up', ['bar' => .942, 'bt' => 'green']),
				],
				'w' => [
					['type' => 'cols', 'span' => 7, 'title' => $t('Causas principales de merma (Pareto)', 'Main shrink causes (Pareto)'),
						'x' => [$t('Rotura', 'Breakage'), $t('Caducidad', 'Expired'), $t('Dif. inventario', 'Stock diff.'), $t('Empaque abierto', 'Open pack'), $t('Vencimiento', 'Expiry')],
						'ymax' => 1200, 'yt' => [0, 400, 800, 1200], 'yf' => '$',
						'bars' => [['name' => $t('Valor ($)', 'Value ($)'), 'tone' => 'red', 'v' => [1146, 196, 168, 100, 31], 'lbl' => ['$1,146', '$196', '$168', '$100', '$31']]],
						'line' => ['name' => $t('% acumulado', 'Cumulative %'), 'tone' => 'amber', 'max' => 100, 'dots' => true, 'v' => [69.9, 81.8, 92.0, 98.1, 100.0]]],
					['type' => 'donut', 'span' => 5, 'title' => $t('Mermas por tipo', 'Shrink by type'),
						'center' => ['v' => 1640, 'p' => '$', 'd' => 0, 'n' => '100%'], 'tones' => ['red', 'amber', 'gray', 'green'],
						'segs' => [
							['l' => $t('Operativa', 'Operational'), 'v' => 1372, 'pct' => 83.7],
							['l' => $t('Averías', 'Damage'), 'v' => 100, 'pct' => 6.1],
							['l' => $t('Comercial', 'Commercial'), 'v' => 0, 'pct' => 0.0],
							['l' => $t('Ajustes', 'Adjustments'), 'v' => 168, 'pct' => 10.2],
						]],
				],
			],
			'dispatch' => [
				'cols' => 4,
				'flow' => [$t('Pedido', 'Order'), $t('Validación', 'Validation'), $t('Reserva', 'Reserve'), 'Picking', 'Packing', $t('Verificación', 'Check'), $t('Despacho', 'Dispatch'), $t('Entrega', 'Delivery'), $t('Conciliación', 'Reconcile')],
				'kpis' => [
					$k($t('Pedidos pendientes', 'Pending orders'), 16, '', '', 0, $t('En preparación / empaque', 'Being prepared / packed'), 'warn'),
					$k($t('Pedidos despachados', 'Dispatched orders'), 34, '', '', 0, $t('✓ Despachos completados', '✓ Completed dispatches'), 'up'),
					$k($t('Entregas atrasadas', 'Late deliveries'), 16, '', '', 0, $t('▲ Compromiso vencido', '▲ Commitment missed'), 'down', ['pulse' => true]),
					$k($t('OTIF (a tiempo y completo)', 'OTIF (on time in full)'), 94.1, '', '%', 1, $t('Meta corporativa: ≥ 95.0%', 'Corporate target: ≥ 95.0%'), 'warn', ['bar' => .941, 'bt' => 'green', 'mark' => .95]),
					$k($t('Exactitud de picking', 'Picking accuracy'), 100.0, '', '%', 1, $t('Escaneo láser validado', 'Laser scan validated'), 'up', ['bar' => 1, 'bt' => 'green']),
					$k($t('Tiempo de preparación', 'Preparation time'), 18.5, '', ' min', 1, $t('Promedio por orden', 'Average per order'), 'mute'),
					$k($t('Incidencias registradas', 'Recorded incidents'), 2, '', '', 0, $t('Faltantes / variaciones', 'Shortages / variances'), 'warn'),
					$k($t('Productividad picking', 'Picking productivity'), 4.8, '', $t(' ped/h', ' ord/h'), 1, $t('Por operador en turno', 'Per operator on shift'), 'mute'),
				],
				'w' => [
					['type' => 'cols', 'span' => 6, 'title' => $t('Conciliación por etapa · unidades', 'Reconciliation by stage · units'),
						'x' => [$t('Solicitada', 'Requested'), $t('Preparada', 'Prepared'), $t('Escaneada', 'Scanned'), $t('Despachada', 'Dispatched'), $t('Recibida', 'Received')],
						'ymax' => 25, 'yt' => [0, 5, 10, 15, 20, 25], 'yf' => 'n',
						'bars' => [['name' => $t('Unidades', 'Units'), 'tone' => 'green', 'v' => [22, 14, 14, 14, 14], 'lbl' => ['22', '14', '14', '14', '14'], 'tones' => ['green', 'amber', 'amber', 'amber', 'amber']]]],
					['type' => 'order', 'span' => 6, 'title' => 'PED-2026-00002', 'sub' => $t('Cafetería Central Gourmet · Hallandale Beach', 'Cafetería Central Gourmet · Hallandale Beach'),
						'tags' => [[$t('Retiro en tienda', 'Store pickup'), 'amber'], [$t('Empacado', 'Packed'), 'green']],
						'steps' => [$t('Pedido recibido', 'Order received'), $t('Preparación picking', 'Picking'), $t('Validación UPC', 'UPC check'), $t('Listo para retiro', 'Ready for pickup'), $t('Verificar PIN o QR', 'Verify PIN or QR'), $t('Entregado', 'Delivered')], 'cur' => 3,
						'stats' => [
							['l' => $t('Costo devoluciones', 'Returns cost'), 'v' => 1035.81, 'p' => '$', 'd' => 2],
							['l' => $t('Valor recuperable', 'Recoverable value'), 'v' => 530.73, 'p' => '$', 'd' => 2, 't' => 'up'],
							['l' => $t('Impacto neto real', 'Real net impact'), 'v' => -58.14, 'p' => '$', 'd' => 2, 't' => 'down'],
						]],
				],
			],
			'workforce' => [
				'cols' => 4,
				'kpis' => [
					$k($t('Personal programado', 'Scheduled staff'), 20, '', '', 0, $t('100% dotación programada', '100% planned headcount'), 'up', ['bar' => 1, 'bt' => 'green']),
					$k($t('Presentes', 'Present'), 10, '', '', 0, $t('50% · 10 con entrada pendiente', '50% · 10 not clocked in yet'), 'warn', ['bar' => .5, 'bt' => 'green']),
					$k($t('Tardanzas', 'Late arrivals'), 0, '', '', 0, $t('0% del personal', '0% of staff'), 'up'),
					$k($t('Ausencias', 'Absences'), 0, '', '', 0, $t('0% del personal', '0% of staff'), 'up'),
					$k($t('Horas asignadas', 'Assigned hours'), 1127, '', ' h', 0, $t('Costo programado $25,436', 'Scheduled cost $25,436'), 'mute'),
					$k($t('Cobertura proyectada', 'Projected coverage'), 167.7, '', '%', 1, $t('Sobrecobertura · horas óptimas por ventas', 'Over-coverage · optimal hours by sales'), 'warn', ['bar' => 1, 'bt' => 'amber', 'mark' => 1 / 1.677]),
					$k($t('Ventas por hora (SPLH)', 'Sales per labor hour'), 24.5, '$', '/h', 1, $t('Meta objetivo: $140.0', 'Target: $140.0'), 'down', ['bar' => .175, 'bt' => 'amber']),
					$k($t('Brecha de cobertura', 'Coverage gap'), 455, '+', ' h', 0, $t('Horas programadas vs. demanda', 'Scheduled hours vs. demand'), 'warn'),
				],
				'w' => [
					['type' => 'cols', 'span' => 7, 'title' => $t('Ventas proyectadas vs. horas laborales', 'Projected sales vs. labor hours'),
						'x' => [$t('Lun', 'Mon'), $t('Mar', 'Tue'), $t('Mié', 'Wed'), $t('Jue', 'Thu'), $t('Vie', 'Fri'), $t('Sáb', 'Sat'), $t('Dom', 'Sun')],
						'ymax' => 250, 'yt' => [0, 50, 100, 150, 200, 250], 'yf' => 'n',
						'bars' => [
							['name' => $t('Horas necesarias', 'Required hours'), 'tone' => 'soft', 'v' => [48, 88, 86, 136, 96, 99, 119]],
							['name' => $t('Horas programadas', 'Scheduled hours'), 'tone' => 'green', 'v' => [137, 200, 225, 190, 138, 138, 99]],
						],
						'line' => ['name' => $t('Ventas ($ mil)', 'Sales ($ k)'), 'tone' => 'teal', 'max' => 6, 'dots' => true, 'v' => [2.0, 3.6, 3.6, 5.7, 3.9, 3.9, 4.8]]],
					['type' => 'hbars', 'span' => 5, 'title' => $t('Productividad por departamento (SPLH)', 'Productivity by department (SPLH)'), 'note' => $t('Barra = cobertura', 'Bar = coverage'),
						'rows' => [
							['l' => $t('Ventas y Operación', 'Sales & Operations'), 'v' => [280.4], 'p' => [''], 's' => ['%'], 'd' => [1], 'x' => '$20/h', 'tone' => 'amber'],
							['l' => $t('Bodega Digital', 'Digital Warehouse'), 'v' => [131.7], 'p' => [''], 's' => ['%'], 'd' => [1], 'x' => '$38/h', 'tone' => 'amber'],
							['l' => $t('Accesorios', 'Accessories'), 'v' => [120.1], 'p' => [''], 's' => ['%'], 'd' => [1], 'x' => '$31/h', 'tone' => 'amber'],
							['l' => $t('Despachos', 'Dispatch'), 'v' => [96.9], 'p' => [''], 's' => ['%'], 'd' => [1], 'x' => '$26/h', 'tone' => 'green'],
							['l' => 'Virtual Teams', 'v' => [96.9], 'p' => [''], 's' => ['%'], 'd' => [1], 'x' => '$26/h', 'tone' => 'green'],
						]],
				],
			],
			'loss-prevention' => [
				'cols' => 3,
				'kpis' => [
					$k($t('Sucursales incluidas', 'Branches included'), 11, '', '', 0, $t('10 sincronizadas al corte', '10 synced at cut-off'), 'warn'),
					$k($t('Pérdida confirmada', 'Confirmed loss'), 749.45, '$', '', 2, $t('5 eventos · -12.85% vs. anterior', '5 events · -12.85% vs. previous'), 'up'),
					$k($t('% merma s/ ventas netas', 'Shrink % of net sales'), 2.27, '', '%', 2, $t('Ventas netas $33,011.90', 'Net sales $33,011.90'), 'down'),
					$k($t('En investigación', 'Under investigation'), 19574.57, '$', '', 2, $t('111 casos abiertos', '111 open cases'), 'warn', ['pulse' => true]),
					$k($t('Recuperación financiera', 'Financial recovery'), 0, '$', '', 2, $t('de $100.00 recuperable', 'of $100.00 recoverable'), 'mute', ['bar' => 0, 'bt' => 'green']),
					$k($t('Valor del inventario', 'Inventory value'), 14243800.17, '$', '', 2, $t('142,780 u. al generar', '142,780 units at run time'), 'mute'),
				],
				'w' => [
					['type' => 'hbars', 'span' => 5, 'title' => $t('Diferencias en investigación', 'Differences under investigation'), 'note' => $t('Barra = casos', 'Bar = cases'),
						'rows' => [
							['l' => $t('Expedientes abiertos', 'Open case files'), 'v' => [34], 'p' => [''], 's' => [''], 'd' => [0], 'x' => '$19,289.87', 'tone' => 'red'],
							['l' => $t('Mermas por aprobar', 'Shrink pending approval'), 'v' => [76], 'p' => [''], 's' => [''], 'd' => [0], 'x' => '$276.30', 'tone' => 'amber'],
							['l' => $t('Ajustes pendientes', 'Pending adjustments'), 'v' => [1], 'p' => [''], 's' => [''], 'd' => [0], 'x' => '$8.40', 'tone' => 'amber'],
						],
						'status' => ['l' => $t('Estado general', 'Overall status'), 'v' => $t('Atención', 'Attention'), 'tone' => 'amber',
							'rows' => [[$t('Operativa', 'Operational'), '$285.95'], [$t('Comercial', 'Commercial'), '$195.50'], [$t('Ajustes (neto)', 'Adjustments (net)'), '$168.00']]]],
					['type' => 'list', 'span' => 7, 'title' => $t('Prioridades del día', 'Today\'s priorities'), 'badge' => $t('6 acciones', '6 actions'),
						'rows' => [
							['l' => $t('Doral: sin ventas registradas hasta el corte', 'Doral: no sales recorded at cut-off'), 'o' => $t('Sistemas / Gerente', 'IT / Manager'), 's' => $t('Abierta', 'Open'), 'tone' => 'amber'],
							['l' => $t('34 × Investigación abierta ($19,289.87)', '34 × Open investigation ($19,289.87)'), 'o' => $t('Prevención de pérdidas', 'Loss prevention'), 's' => $t('Abierta', 'Open'), 'tone' => 'amber'],
							['l' => $t('32 × Producto sensible con diferencia', '32 × Sensitive item with variance'), 'o' => $t('Prevención de pérdidas', 'Loss prevention'), 's' => $t('Abierta', 'Open'), 'tone' => 'amber'],
							['l' => $t('1 × Posible error de despacho ($62.80)', '1 × Possible dispatch error ($62.80)'), 'o' => $t('Logística / CEDIS', 'Logistics / DC'), 's' => $t('Abierta', 'Open'), 'tone' => 'amber'],
							['l' => $t('20 × Alerta crítica sin resolver', '20 × Unresolved critical alert'), 'o' => $t('Responsable de la alerta', 'Alert owner'), 's' => $t('Abierta', 'Open'), 'tone' => 'amber', 'pulse' => true],
							['l' => $t('158 productos sensibles sin conteo en 30 días', '158 sensitive items not counted in 30 days'), 'o' => $t('Supervisor de inventario', 'Inventory supervisor'), 's' => $t('Abierta', 'Open'), 'tone' => 'amber'],
						]],
				],
			],
		];
	}

	public static function Num(float $v, int $d = 0, string $p = '', string $s = ''): string
	{
		return $p . ($v < 0 ? '-' : '') . number_format(abs($v), $d, '.', ',') . $s;
	}

	/** Eje Y abreviado: k = miles, M = millones, $ = moneda, n = número */
	public static function Axis(float $v, string $f): string
	{
		if ($f === 'k') { return '$' . ($v >= 1000 ? number_format($v / 1000) . 'k' : '0'); }
		if ($f === 'M') { return '$' . ($v >= 1000000 ? number_format($v / 1000000) . 'M' : '0'); }
		if ($f === '$') { return '$' . number_format($v); }
		return number_format($v);
	}

	/** Trazo suave (Catmull-Rom → Bézier) entre puntos [x, y]; omite huecos */
	public static function Path(array $pts, float $top = -INF, float $bottom = INF): string
	{
		$n = count($pts);
		if ($n === 0) { return ''; }
		$cl = fn (float $y) => max($top, min($bottom, $y)); // sin rebasar el área del gráfico
		$d = sprintf('M%.1f %.1f', $pts[0][0], $pts[0][1]);
		for ($i = 0; $i < $n - 1; $i++) {
			$p0 = $pts[max(0, $i - 1)]; $p1 = $pts[$i]; $p2 = $pts[$i + 1]; $p3 = $pts[min($n - 1, $i + 2)];
			$c1 = [$p1[0] + ($p2[0] - $p0[0]) / 6, $cl($p1[1] + ($p2[1] - $p0[1]) / 6)];
			$c2 = [$p2[0] - ($p3[0] - $p1[0]) / 6, $cl($p2[1] - ($p3[1] - $p1[1]) / 6)];
			$d .= sprintf(' C%.1f %.1f %.1f %.1f %.1f %.1f', $c1[0], $c1[1], $c2[0], $c2[1], $p2[0], $p2[1]);
		}
		return $d;
	}
}
