<?php

// Etapa 10: kardex por producto/lote. Las líneas de venta guardan la transacción de stock que consumieron
// para enlazar cada salida del kardex con su documento de venta.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$cols = array_column($db->query('PRAGMA table_info(sale_items)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('transaction_id', $cols))
{
	$db->exec('ALTER TABLE sale_items ADD COLUMN transaction_id TEXT');
}
$db->exec('CREATE INDEX IF NOT EXISTS ix_sale_items_transaction ON sale_items(transaction_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_stock_log_product_time ON stock_log(product_id, row_created_timestamp)');
