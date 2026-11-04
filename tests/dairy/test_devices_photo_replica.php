<?php

require __DIR__ . '/bootstrap.php';

echo "=======================================================\n";
echo " DAILY ENTERPRISE — CONTROL DE CELULARES 360°\n";
echo " Verificación de Réplica de Captura de Referencia\n";
echo "=======================================================\n\n";

$svc = \Grocy\Services\DeviceService::GetInstance();
$svc->SetSystem(true);

// 1. Verificación de KPIs en Dashboard
$dash = $svc->Dashboard([]);
$k = $dash['kpis'];

ok('Paso 1.1: Dashboard retorna los 8 KPIs principales',
	isset($k['registered'], $k['showcase'], $k['warehouse'], $k['in_transit'], $k['reserved'], $k['not_located'], $k['open_cases'], $k['pending_value']),
	"Registrados: {$k['registered']}, En vitrina: {$k['showcase']}, En bodega: {$k['warehouse']}, No localizados: {$k['not_located']}, Valor: \${$k['pending_value']}"
);

ok('Paso 1.2: El total registrado es 24 (réplica de foto)',
	intval($k['registered']) === 24,
	"Total: {$k['registered']}"
);

ok('Paso 1.3: Valor en riesgo coincide con la foto ($1,030.00)',
	floatval($k['pending_value']) === 1030.00,
	"Valor: \${$k['pending_value']}"
);

ok('Paso 1.4: Equipos no localizados es 3',
	intval($k['not_located']) === 3,
	"No localizados: {$k['not_located']}"
);

// 2. Verificación de Donut Chart (Estado de Celulares)
$donut = $dash['donut_distribution'];
ok('Paso 2.1: donut_distribution contiene los 5 estados clave',
	isset($donut['available'], $donut['showcase'], $donut['in_transit'], $donut['not_located'], $donut['reserved']),
	"Disponible: {$donut['available']['pct']}%, Vitrina: {$donut['showcase']['pct']}%, Tránsito: {$donut['in_transit']['pct']}%, No localizado: {$donut['not_located']['pct']}%"
);

ok('Paso 2.2: Distribución porcentual coincide con la referencia visual',
	$donut['available']['pct'] == 41.7 && $donut['showcase']['pct'] == 33.3 && $donut['in_transit']['pct'] == 8.3 && $donut['not_located']['pct'] == 12.5,
	"41.7% / 33.3% / 8.3% / 12.5%"
);

// 3. Verificación de Unidades y Tabla
$units = $dash['units'];
ok('Paso 3.1: units contiene 24 registros individuales',
	count($units) === 24,
	"Total unidades: " . count($units)
);

$firstUnit = $units[0];
ok('Paso 3.2: Cada unidad tiene campos formateados para la tabla profesional',
	isset($firstUnit['imei1_masked'], $firstUnit['product_name'], $firstUnit['branch_name'], $firstUnit['zone_label'], $firstUnit['state_label'], $firstUnit['product_thumb']),
	"Unidad 1: {$firstUnit['imei1_masked']} | {$firstUnit['product_name']} | {$firstUnit['state_label']}"
);

// 4. Verificación de Pestañas (Movimientos, Alertas, Investigaciones)
ok('Paso 4.1: Movimientos recientes generados y trazados',
	isset($dash['movements']) && is_array($dash['movements']),
	"Movimientos: " . count($dash['movements'])
);

ok('Paso 4.2: Alertas de dispositivos identificadas',
	isset($dash['alerts']) && count($dash['alerts']) >= 3,
	"Alertas: " . count($dash['alerts'])
);

ok('Paso 4.3: Casos de investigación vinculados',
	isset($dash['cases']) && count($dash['cases']) >= 3,
	"Casos: " . count($dash['cases'])
);

// 5. Verificación de Operaciones Reales (Registro, Validación Luhn, Duplicados)
$validImei = \Grocy\Services\DeviceService::DemoImei(777);
ok('Paso 5.1: DemoImei genera IMEI válido por algoritmo de Luhn',
	\Grocy\Services\DeviceService::ValidImei($validImei),
	"IMEI: $validImei"
);

expectError('Paso 5.2: IMEI con formato inválido es rechazado', function() use ($svc) {
	$svc->QuickRegister([
		'product_id' => 9003,
		'branch_id' => 1,
		'imei1' => '123456', // No tiene 15 dígitos ni pasa Luhn
	], 1);
});

expectError('Paso 5.3: Intento de registrar IMEI duplicado es rechazado', function() use ($svc) {
	// 99000099000010 ya existe en demo
	$existing = \Grocy\Services\DeviceService::DemoImei(1);
	$svc->QuickRegister([
		'product_id' => 9003,
		'branch_id' => 1,
		'imei1' => $existing,
	], 1);
});

// 6. Verificación de Archivos y Plantillas
$viewContent = file_get_contents($root . '/views/devices.blade.php');
ok('Paso 6.1: views/devices.blade.php contiene el componente execution_context_bar',
	str_contains($viewContent, 'components.execution_context_bar')
);

ok('Paso 6.2: views/devices.blade.php contiene el Hero Banner de Control de Celulares',
	str_contains($viewContent, 'Control de Celulares') && str_contains($viewContent, 'dv-hero-banner')
);

ok('Paso 6.3: views/devices.blade.php contiene los 8 KPIs en grid',
	str_contains($viewContent, 'dv-kpis-grid-8') && str_contains($viewContent, 'Valor en riesgo')
);

ok('Paso 6.4: views/devices.blade.php contiene la navegación interna de 6 pestañas',
	str_contains($viewContent, 'tab-equipos') && str_contains($viewContent, 'tab-movimientos') && str_contains($viewContent, 'tab-alertas') && str_contains($viewContent, 'tab-investigaciones') && str_contains($viewContent, 'tab-historial') && str_contains($viewContent, 'tab-reportes')
);

ok('Paso 6.5: views/devices.blade.php contiene el Donut Chart de Estado de Celulares',
	str_contains($viewContent, 'Estado de Celulares') && str_contains($viewContent, 'dv-donut-visual')
);

ok('Paso 6.6: views/devices.blade.php contiene los 6 botones de Acciones Rápidas',
	str_contains($viewContent, 'Llamar') && str_contains($viewContent, 'Mensajear') && str_contains($viewContent, 'Asignar') && str_contains($viewContent, 'Solicitar apoyo') && str_contains($viewContent, 'Escalar') && str_contains($viewContent, 'Resolver')
);

ok('Paso 6.7: views/devices.blade.php contiene la tarjeta de Trazabilidad 360°',
	str_contains($viewContent, 'dv-trace-card') && str_contains($viewContent, 'Trazabilidad 360°')
);

ok('Paso 6.8: views/devices.blade.php contiene el drawer lateral de Ficha del Equipo 360°',
	str_contains($viewContent, 'drawerDeviceDetail') && str_contains($viewContent, 'Ficha del Equipo 360°')
);

echo "\nResultados: Total OK. Verificación de Control de Celulares 360° superada con éxito.\n";

