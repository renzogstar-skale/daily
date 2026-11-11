<?php
/**
 * Preparación/restauración del E2E del POS sobre la base viva: `php e2e_pos_setup.php setup` respalda la base, crea un
 * supervisor de prueba y elige productos con stock y código; `php e2e_pos_setup.php restore` devuelve la base al respaldo.
 */
$root = dirname(__DIR__, 2);
$db = $root . '/data/grocy_es.db';
$bak = $root . '/data/grocy_es.e2e_pos_bak.db';
if (($argv[1] ?? '') === 'restore')
{
	if (is_file($bak))
	{
		copy($bak, $db);
		unlink($bak);
	}
	echo json_encode(['restored' => true]);
	exit;
}
copy($db, $bak);
define('GROCY_DATAPATH', $root . '/data');
define('GROCY_USER_ID', 1);
require $root . '/packages/autoload.php';
require $root . '/data/config.php';
require $root . '/config-dist.php';
$pdo = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
if (!$pdo->query("SELECT id FROM users WHERE username = 'sup_e2e'")->fetchColumn())
{
	Grocy\Services\UsersService::GetInstance()->CreateUser('sup_e2e', 'Supervisor', 'E2E', 'Clave-E2E-1');
}
$sup = intval($pdo->query("SELECT id FROM users WHERE username = 'sup_e2e'")->fetchColumn());
$pdo->exec("DELETE FROM user_permissions WHERE user_id = $sup");
$pdo->exec("INSERT INTO user_permissions (permission_id, user_id) SELECT id, $sup FROM permission_hierarchy WHERE name = 'SALES_SUPERVISE'");
$pdo->exec("UPDATE pos_cash_shifts SET status = 'closed', closed_at = datetime('now', 'localtime') WHERE branch_id = 1 AND register_name = 'Caja E2E' AND status = 'open'");
$rows = $pdo->query("SELECT p.id, (SELECT barcode FROM product_barcodes b WHERE b.product_id = p.id LIMIT 1) AS code, p.sku, SUM(s.amount) AS stock
	FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
	WHERE l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0 AND p.active = 1 AND p.sale_price > 0 AND p.id NOT IN (SELECT product_id FROM quarantine_items WHERE status = 'in_quarantine')
	GROUP BY p.id HAVING SUM(s.amount) >= 10 ORDER BY p.id")->fetchAll(PDO::FETCH_ASSOC);
$co = Grocy\Services\CustomerOrderService::GetInstance();
$rows = array_values(array_filter($rows, fn($r) => $r['stock'] - $co->Reserved(intval($r['id']), 1, null) >= 10));
echo json_encode(['products' => array_slice(array_map(fn($r) => ['id' => intval($r['id']), 'code' => $r['code'] ?: $r['sku']], $rows), 0, 3)]);
