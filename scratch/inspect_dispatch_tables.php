<?php
require __DIR__ . '/../tests/dairy/bootstrap.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$orders = $db->query('SELECT id, order_code, status, delivery_type, requested_date, carrier_name, carrier_vehicle, delivery_status FROM customer_orders')->fetchAll(PDO::FETCH_ASSOC);
print_r($orders);
