<?php

// DAILY — Rendimiento: índices para las consultas más lentas medidas en Ventas y Stock (solo lectura más rápida, no cambian datos)

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec('CREATE INDEX IF NOT EXISTS ix_sale_items_sale_id ON sale_items (sale_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_sales_created ON sales (created_timestamp)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_stock_log_row_created ON stock_log (row_created_timestamp)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_orders_sale_id ON customer_orders (sale_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_waste_records_transaction ON waste_records (transaction_id)');
