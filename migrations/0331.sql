-- DAILY — Integridad de mermas: una merma pendiente sin transacción de stock no debe marcar inventory_deducted=1
UPDATE waste_records
SET inventory_deducted = 0
WHERE status = 'pending_approval'
  AND inventory_deducted = 1
  AND TRIM(IFNULL(transaction_id, '')) = '';

