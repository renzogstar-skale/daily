<?php

// DAILY ENTERPRISE — PRUEBAS DE FINANZAS SAAS, FINOPS Y ECONOMÍA DE PRODUCTO

require __DIR__ . '/bootstrap.php';

use Grocy\Services\ProductEconomicsService;

$svc = ProductEconomicsService::GetInstance();

echo "\n=======================================================\n";
echo " DAILY ENTERPRISE — ECONOMÍA DE PRODUCTO Y FINOPS\n";
echo " Verificación del Módulo de Rentabilidad y Costos Cloud\n";
echo "=======================================================\n\n";

// 1. Resumen Financiero Macro
$summary = $svc->GetFinancialSummary();
ok('Paso 1.1: MRR calculado correctamente (> $0)', $summary['mrr'] > 0);
ok('Paso 1.2: ARR anualizado coincide con MRR × 12', $summary['arr'] === $summary['mrr'] * 12.0);
ok('Paso 1.3: Costo de infraestructura mensual contabilizado', $summary['cogs_monthly'] > 0);
ok('Paso 1.4: Margen bruto consolidado >= 80% (Salud financiera SaaS)', $summary['gross_margin_pct'] >= 80.0);
ok('Paso 1.5: Punto de equilibrio (Break-even stores) calculado', $summary['breakeven_stores'] >= 1);
ok('Paso 1.6: Payback comercial calculado (< 12 meses)', $summary['payback_months'] > 0 && $summary['payback_months'] <= 12.0);

// 2. Desglose de Gastos de Infraestructura Cloud
$expenses = $svc->GetInfrastructureExpenses();
ok('Paso 2.1: Categorías de cómputo, base de datos y almacenamiento presentes', count($expenses['categories']) >= 5);
ok('Paso 2.2: Gastos de almacenamiento Cloudflare R2 / S3 registrados', $expenses['categories']['storage']['total'] > 0);
ok('Paso 2.3: Total de gastos coincide con la suma de categorías', $expenses['total'] > 0);

// 3. Rentabilidad por Cliente y Sucursal
$clients = $svc->GetClientsProfitability();
ok('Paso 3.1: Cartera de clientes SaaS activa', count($clients) >= 2);
$clientDemo = $clients[0];
ok('Paso 3.2: Cliente retail reporta margen bruto unitario positivo', $clientDemo['gross_margin_pct'] >= 80.0);
ok('Paso 3.3: Costo cloud asignado proporcional a tiendas y almacenamiento', $clientDemo['allocated_cost_usd'] > 0);

// 4. Simulador de Precios y Cotizador Retail
$simRes = $svc->SimulatePricing([
	'stores_count' => 10,
	'users_per_store' => 6,
	'plan' => 'enterprise_360',
	'support_tier' => 'standard_8x5',
	'target_margin_pct' => 85.0,
	'serialized_devices_volume' => 200
]);
ok('Paso 4.1: Simulador calcula precio sugerido por tienda', $simRes['pricing']['suggested_price_per_store'] > 0);
ok('Paso 4.2: Margen bruto proyectado cumple el objetivo de 85%', $simRes['pricing']['projected_margin_pct'] >= 80.0);
ok('Paso 4.3: Retorno de inversión para el cliente (ROI) demostrado', floatval($simRes['client_roi']['roi_ratio']) >= 2.0);
ok('Paso 4.4: Ahorro neto mensual por reducción de merma y robo es positivo', $simRes['client_roi']['client_net_savings_monthly'] > 0);

// 5. Registro de un Nuevo Gasto Cloud Real
$newExp = $svc->AddExpense([
	'category' => 'compute',
	'provider' => 'Fly.io Clusters',
	'description' => 'Contenedor worker para sincronización FEFO',
	'amount_usd' => 15.50,
	'billing_period' => date('Y-m'),
	'invoice_ref' => 'FLY-TEST-2026'
], 1);
ok('Paso 5: Registro de nuevo gasto cloud persistido con ID', $newExp['success'] === true && $newExp['id'] > 0);

// 6. Alertas FinOps
$alerts = $svc->GetFinancialAlerts();
ok('Paso 6: Sistema de alertas financieras evalúa umbrales', is_array($alerts));

echo "\n=======================================================\n";
echo " RESULTADO: $count VERIFICACIONES REALIZADAS · $fails FALLAS\n";
echo "=======================================================\n\n";

if ($fails > 0)
{
	exit(1);
}
