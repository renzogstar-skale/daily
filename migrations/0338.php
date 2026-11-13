<?php

// DAILY — Dos opciones «Doral» en los filtros: son registros distintos. Doral (SUC-09) es la tienda de la empresa de ejemplo;
// la sucursal con código DEMO-TI es el entorno aislado del escenario de horarios (DemoSchedulingScenarioService la crea y su
// reinicio borra su personal y turnos, por eso no se fusiona con la tienda real). Se le da un nombre distintivo.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("UPDATE branches SET name = 'Doral · escenario de horarios (DEMO-TI)' WHERE code = 'DEMO-TI' AND name LIKE 'Doral%'
	AND EXISTS (SELECT 1 FROM branches b WHERE b.code <> 'DEMO-TI' AND b.name = 'Doral')");
