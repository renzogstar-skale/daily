<?php

// Enterprise Platform Migration: permisos DAIRY en la jerarquía de Grocy
// Sin estas filas los permisos solo los cumplía ADMIN y no se podían asignar a otros usuarios

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

// Trazabilidad del ajuste de inventario contabilizado desde un conteo cíclico
$cols = array_column($db->query('PRAGMA table_info(stock_cycle_counts)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('adjustment_transaction_id', $cols))
{
	$db->exec('ALTER TABLE stock_cycle_counts ADD COLUMN adjustment_transaction_id TEXT');
}

$adminId = intval($db->query("SELECT id FROM permission_hierarchy WHERE name = 'ADMIN'")->fetchColumn());

$tree = [
	'WASTE' => ['WASTE_REPORT', 'WASTE_APPROVE', 'WASTE_REASONS_EDIT'],
	'SALES' => ['SALES_POS', 'SALES_CANCEL', 'SALES_VIEW'],
	'PROCUREMENT' => ['PROCUREMENT_MANAGE', 'PROCUREMENT_RECEIVE'],
	'QUALITY' => ['QUALITY_INSPECT', 'QUALITY_QUARANTINE'],
	'KPIS_VIEW' => [],
	'BRANCH_MANAGE' => [],
	'INVENTORY_ADJUST_APPROVE' => []
];

$insert = $db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:name, :parent)');
$getId = $db->prepare('SELECT id FROM permission_hierarchy WHERE name = :name');

foreach ($tree as $group => $children)
{
	$insert->execute([':name' => $group, ':parent' => $adminId]);
	$getId->execute([':name' => $group]);
	$groupId = intval($getId->fetchColumn());

	foreach ($children as $child)
	{
		$insert->execute([':name' => $child, ':parent' => $groupId]);
	}
}
