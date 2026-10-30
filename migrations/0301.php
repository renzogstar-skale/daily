<?php

// DAILY — Dotación mínima de la empresa de ejemplo por sucursal y área (coverage_requirements), para que HOY y Personal
// calculen la cobertura con la programación registrada. Solo en sucursales sin requerimientos cargados.

if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease' || GROCY_MODE === 'dev')
{
	$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
	// Área (mismo nombre que employee_shifts.department) => [inicio, fin, personas]
	$req = [
		['Cajas y Atención al Cliente', '08:00', '21:00', 2],
		['Dairy / Lácteos', '07:00', '15:00', 1],
		['Bodega y Despachos', '07:00', '13:00', 1]
	];
	$has = $db->prepare('SELECT COUNT(*) FROM coverage_requirements WHERE branch_id = ?');
	$ins = $db->prepare('INSERT INTO coverage_requirements (branch_id, department, weekday, start_time, end_time, min_staff) VALUES (?, ?, 0, ?, ?, ?)');
	foreach ($db->query('SELECT id FROM branches WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN) as $b)
	{
		$has->execute([$b]);
		if (intval($has->fetchColumn()) > 0)
		{
			continue;
		}
		foreach ($req as [$dept, $from, $to, $n])
		{
			$ins->execute([$b, $dept, $from, $to, $n]);
		}
	}
}
