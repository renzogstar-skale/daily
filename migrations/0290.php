<?php

// DAIRY Etapa 34: margen bruto neto de impuesto en una sola definición (venta − IVA − costo de ventas), igual que /m.
// Solo cambia ventas con impuesto registrado (hasta hoy, únicamente las importadas de POS externos con IVA). Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("UPDATE sales SET gross_margin = ROUND(total_amount - tax_amount - total_cogs, 2) WHERE IFNULL(tax_amount, 0) > 0 AND ABS(gross_margin - ROUND(total_amount - tax_amount - total_cogs, 2)) > 0.004");
