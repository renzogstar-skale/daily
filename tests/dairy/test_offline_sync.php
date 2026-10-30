<?php
/**
 * Cola sin conexión: idempotencia del servidor (X-Idempotency-Key) y merma rápida de Piso de Tienda por el flujo único.
 */

use Grocy\Middleware\DairyIdempotencyMiddleware;
use Grocy\Services\WorkforceService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/bootstrap.php';

echo "\n1. Idempotencia de la API\n";
$rf = new \Slim\Psr7\Factory\ResponseFactory();
$mw = new DairyIdempotencyMiddleware(new \DI\Container(), $rf);
$req = fn(string $path, ?string $key) => (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', 'http://localhost/api' . $path)->withHeader('X-Idempotency-Key', (string) $key);
$handler = function (callable $fn) use ($rf) {
	return new class($fn, $rf) implements RequestHandlerInterface {
		public int $calls = 0;
		public function __construct(private $fn, private $rf) {}
		public function handle(ServerRequestInterface $request): ResponseInterface
		{
			$this->calls++;
			return ($this->fn)($this->rf->createResponse(200)->withHeader('Content-Type', 'application/json'), $this->calls);
		}
	};
};
$json = function ($res, $data, $status = 200) { $res = $res->withStatus($status); $res->getBody()->write(json_encode($data)); return $res; };

$h = $handler(fn($res, $n) => $json($res, ['n' => $n]));
$k = 'test-' . bin2hex(random_bytes(6));
$r1 = $mw($req('/x', $k), $h);
$r2 = $mw($req('/x', $k), $h);
ok('primera petición se ejecuta', $r1->getStatusCode() === 200 && $h->calls === 1);
ok('reenvío con la misma clave no se ejecuta otra vez', $h->calls === 1 && (string) $r2->getBody() === (string) $r1->getBody() && $r2->getHeaderLine('X-Idempotent-Replay') === '1');
ok('misma clave en otra ruta → 422', $mw($req('/y', $k), $h)->getStatusCode() === 422 && $h->calls === 1);
ok('sin clave no interviene', $mw($req('/x', null), $h)->getStatusCode() === 200 && $h->calls === 2);
ok('clave inválida → 400', $mw($req('/x', 'a b'), $h)->getStatusCode() === 400);

$hf = $handler(fn($res, $n) => $n === 1 ? $json($res, ['error_message' => 'x'], 400) : $json($res, ['n' => $n]));
$k2 = 'test-' . bin2hex(random_bytes(6));
$mw($req('/x', $k2), $hf);
ok('si falla, la clave se libera y se puede reintentar', $mw($req('/x', $k2), $hf)->getStatusCode() === 200 && $hf->calls === 2);

$k3 = 'test-' . bin2hex(random_bytes(6));
$db->exec("INSERT INTO dairy_idempotency_keys (idempotency_key, user_id, request_path) VALUES ('$k3', " . GROCY_USER_ID . ", 'POST /api/x')");
ok('en proceso → 409', $mw($req('/x', $k3), $h)->getStatusCode() === 409);
$db->exec("UPDATE dairy_idempotency_keys SET created_timestamp = datetime('now', 'localtime', '-10 minutes') WHERE idempotency_key = '$k3'");
ok('en proceso abandonado (> 2 min) se vuelve a ejecutar', $mw($req('/x', $k3), $h)->getStatusCode() === 200);

echo "\n2. Merma rápida de Piso de Tienda = flujo de Registrar merma\n";
$wf = WorkforceService::GetInstance();
$p = $db->query("SELECT s.product_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 GROUP BY s.product_id HAVING SUM(s.amount) - (SELECT IFNULL(SUM(w.amount), 0) FROM waste_records w WHERE w.product_id = s.product_id AND w.branch_id = 1 AND w.status = 'pending_approval' AND w.inventory_deducted = 0) > 5 LIMIT 1")->fetchColumn(); // disponible sin contar mermas por aprobar
$reason = $db->query('SELECT code FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 ORDER BY id LIMIT 1')->fetchColumn();
$reasonEv = $db->query('SELECT code FROM waste_reasons WHERE active = 1 AND requires_evidence = 1 ORDER BY id LIMIT 1')->fetchColumn();
$base = ['product_id' => $p, 'branch_id' => 1, 'amount' => 1, 'notes' => 'prueba'];
$before = intval($val('SELECT COUNT(*) FROM waste_records'));
$r = $wf->QuickWasteReport($base + ['reason_code' => $reason], 1);
expectError('misma merma repetida sin confirmar → posible duplicado (Etapa 21)', fn() => $wf->QuickWasteReport($base + ['reason_code' => $reason], 1));
$base['confirm_duplicate'] = true; // las siguientes repeticiones son intencionales
$st = $val("SELECT status FROM waste_records WHERE id = {$r['waste_record_id']}");
ok('crea un caso con estado del flujo (no "reported")', in_array($st, ['pending_approval', 'approved'], true), $st);
ok('tiene número de caso', !empty($r['case_number']));
expectError('causal inexistente → error (antes caía en la causal #1)', fn() => $wf->QuickWasteReport($base + ['reason_code' => 'NOEXISTE'], 1));
if ($reasonEv)
{
	expectError('causal con evidencia obligatoria sin foto → error', fn() => $wf->QuickWasteReport($base + ['reason_code' => $reasonEv], 1));
}
$key = 'off-' . bin2hex(random_bytes(6));
$a = $wf->QuickWasteReport($base + ['reason_code' => $reason, 'idempotency_key' => $key], 1);
$b = $wf->QuickWasteReport($base + ['reason_code' => $reason, 'idempotency_key' => $key], 1);
ok('mismo idempotency_key → un solo caso', $a['waste_record_id'] === $b['waste_record_id'] && intval($val('SELECT COUNT(*) FROM waste_records')) === $before + 2);

echo "\n3. Reenvío de la cola sin conexión (middleware + servicio)\n";
$hw = $handler(fn($res, $n) => $json($res, $wf->QuickWasteReport($base + ['reason_code' => $reason], 1)));
$kq = 'off-' . bin2hex(random_bytes(6));
$c0 = intval($val('SELECT COUNT(*) FROM waste_records'));
$mw($req('/workforce/quick-waste', $kq), $hw);
$mw($req('/workforce/quick-waste', $kq), $hw);
$mw($req('/workforce/quick-waste', $kq), $hw);
ok('3 envíos de la misma operación → 1 merma', intval($val('SELECT COUNT(*) FROM waste_records')) === $c0 + 1 && $hw->calls === 1);

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
