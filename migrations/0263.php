<?php

// Enterprise Platform Migration: Clasificación Censo Nacional de Mermas (FENALCO / CICO)
// Merma conocida (causa identificada) vs. merma desconocida (origen no identificado)
// Categorías Censo: vencimientos, desperdicios, robo externo, robo interno, error administrativo, error de proveedor, otros

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$cols = $db->query("PRAGMA table_info(waste_reasons)")->fetchAll(\PDO::FETCH_COLUMN, 1);
if (!in_array('shrink_type', $cols))
{
	$db->exec("ALTER TABLE waste_reasons ADD COLUMN shrink_type TEXT DEFAULT 'conocida'");
}
if (!in_array('censo_category', $cols))
{
	$db->exec("ALTER TABLE waste_reasons ADD COLUMN censo_category TEXT DEFAULT 'desperdicios'");
}

// code => [shrink_type, censo_category]
$censoMap = [
	'M01' => ['conocida', 'desperdicios'],         // Avería en manipulación / Tienda
	'M02' => ['conocida', 'desperdicios'],         // Avería en transporte / Despacho
	'M03' => ['desconocida', 'robo_interno'],      // Hurto interno
	'M04' => ['desconocida', 'robo_externo'],      // Hurto externo / Farderos
	'M05' => ['desconocida', 'error_proveedor'],   // Mercancía no recibida / Error proveedor
	'M06' => ['desconocida', 'otros'],             // Diferencia de inventario sin causa
	'M07' => ['desconocida', 'error_administrativo'], // Error en despacho / Traslado
	'M08' => ['conocida', 'otros'],                // Devolución de cliente en mal estado
	'M09' => ['conocida', 'otros'],                // Producto obsoleto
	'M10' => ['desconocida', 'error_administrativo'], // Error administrativo / Facturación
	'M11' => ['conocida', 'vencimientos'],         // Mercancía vencida en bodega
	'M12' => ['conocida', 'desperdicios'],         // Calidad defectuosa de origen
	'M13' => ['conocida', 'otros'],                // Rechazo en garantía
	'M14' => ['conocida', 'desperdicios'],         // Empaque deteriorado por embalaje
	'D01' => ['conocida', 'desperdicios'],         // Ruptura de cadena de frío
	'D02' => ['conocida', 'desperdicios'],         // Temperatura fuera de rango
	'D03' => ['conocida', 'vencimientos'],         // Vencimiento por rotación deficiente
	'D04' => ['conocida', 'desperdicios'],         // Deteriorado / Fermentación
	'D05' => ['conocida', 'desperdicios'],         // Empaque abierto o dañado
	'D06' => ['conocida', 'vencimientos'],         // Sobre-pedido
	'D07' => ['conocida', 'vencimientos'],         // FEFO incumplido
	'D08' => ['conocida', 'desperdicios'],         // Mal almacenado
	'D09' => ['conocida', 'otros'],                // Alerta sanitaria
	'D10' => ['conocida', 'otros']                 // Error de etiquetado
];

$stmt = $db->prepare("UPDATE waste_reasons SET shrink_type = :t, censo_category = :c WHERE code = :code");
foreach ($censoMap as $code => $v)
{
	$stmt->execute([':t' => $v[0], ':c' => $v[1], ':code' => $code]);
}
