<?php

// DAILY — Rendimiento: índices para filtros por día de venta y disponibilidad por producto (solo lectura más rápida, no cambian datos)

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// Las consultas de ventas filtran por date(created_timestamp): el índice debe ser sobre esa misma expresión
$db->exec('CREATE INDEX IF NOT EXISTS ix_sales_day_status_branch ON sales (date(created_timestamp), status, branch_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_waste_records_product_status ON waste_records (product_id, status)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_order_items_product ON customer_order_items (product_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_stock_cycle_counts_product ON stock_cycle_counts (product_id, executed_date)');
