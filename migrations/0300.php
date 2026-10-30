<?php

// DAILY — Empresa de ejemplo coherente en las 10 sucursales: catálogo único (SKU, unidad, departamento, precio),
// ubicaciones, clientes y personal por sucursal, y 58 días de operación registrados con los servicios de la aplicación
// (compras y recepciones, ventas POS, pedidos, devoluciones, mermas, temperaturas, metas). Ver DailyCompanySeedService.

if (!defined('GROCY_USER_ID'))
{
	define('GROCY_USER_ID', 1);
}
if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease' || GROCY_MODE === 'dev')
{
	\Grocy\Services\DailyCompanySeedService::GetInstance()->Run(intval(getenv('DAILY_SEED_DAYS') ?: 58)); // DAILY_SEED_DAYS solo para pruebas cortas
}
