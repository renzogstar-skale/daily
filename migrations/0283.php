<?php

// DAIRY Etapa 26: integridad de mermas ante reenvíos y registros simultáneos.
// - Una clave de idempotencia identifica un solo registro (reenvíos desde la cola o doble toque nunca duplican).
// - Número de caso único (la numeración secuencial ya se calcula con el bloqueo de escritura tomado).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_waste_records_idempotency ON waste_records(idempotency_key) WHERE idempotency_key IS NOT NULL AND idempotency_key <> ''");

// Solo si los datos existentes lo permiten (no se reescriben casos históricos)
if (intval($db->query("SELECT COUNT(*) FROM (SELECT case_number FROM waste_records WHERE case_number IS NOT NULL GROUP BY case_number HAVING COUNT(*) > 1)")->fetchColumn()) === 0)
{
	$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_waste_records_case_number ON waste_records(case_number) WHERE case_number IS NOT NULL');
}

// Una sola entrada abierta por empleado (respaldo en BD del bloqueo de ClockIn)
if (intval($db->query('SELECT COUNT(*) FROM (SELECT employee_id FROM employee_attendance WHERE clock_out IS NULL GROUP BY employee_id HAVING COUNT(*) > 1)')->fetchColumn()) === 0)
{
	$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_employee_attendance_open ON employee_attendance(employee_id) WHERE clock_out IS NULL');
}
