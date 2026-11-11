<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\DatabaseService;
use Grocy\Services\SensitiveCaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LossCaseController extends BaseController
{
	private function Branch(Request $request): ?int
	{
		$b = intval($request->getQueryParams()['branch_id'] ?? 0);
		return $b > 0 && DairyAccess::CanSeeBranch($b) ? $b : null;
	}

	private function Branches(): array
	{
		return array_values(array_filter(iterator_to_array(BranchService::GetInstance()->GetBranches()), fn($b) => DairyAccess::CanSeeBranch(intval($b->id))));
	}

	public function Dashboard(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$branchId = $this->Branch($request);
		if ($branchId)
		{
			$q['branch_id'] = $branchId;
		}
		$unit = $q['unit'] ?? 'day';
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$svc = SensitiveCaseService::GetInstance();
		$data = $svc->Dashboard($q, $unit);

		$departments = $db->query('SELECT DISTINCT department FROM products WHERE department IS NOT NULL AND department <> "" UNION SELECT DISTINCT department FROM sensitive_investigation_cases WHERE department IS NOT NULL AND department <> "" ORDER BY 1')->fetchAll(\PDO::FETCH_COLUMN);
		$usersList = $db->query("SELECT u.id, u.username, u.first_name, u.last_name,
			(SELECT GROUP_CONCAT(ph.name, ',') FROM user_permissions up JOIN permission_hierarchy ph ON ph.id = up.permission_id WHERE up.user_id = u.id) AS perms
			FROM users u ORDER BY u.username")->fetchAll(\PDO::FETCH_ASSOC);
		$productsList = $db->query("SELECT p.id, p.name, p.sku, p.department, p.is_sensitive, p.device_control, p.sale_price,
			(SELECT b.barcode FROM product_barcodes b WHERE b.product_id = p.id LIMIT 1) AS barcode
			FROM products p WHERE p.active = 1 ORDER BY p.device_control DESC, p.is_sensitive DESC, p.name LIMIT 150")->fetchAll(\PDO::FETCH_ASSOC);
		$devDash = \Grocy\Services\DeviceService::GetInstance()->Dashboard($branchId ? ['branch_id' => $branchId] : []);

		return $this->RenderPage($response, 'loss_prevention', [
			'data' => $data,
			'filters' => $q,
			'branches' => $this->Branches(),
			'selectedBranchId' => $branchId,
			'departments' => $departments,
			'usersList' => $usersList,
			'productsList' => $productsList,
			'devicesList' => array_slice($devDash['units'] ?? [], 0, 25),
			'canRevealImei' => \Grocy\Services\DeviceService::CanReveal(),
			'unit' => $unit,
			'statuses' => SensitiveCaseService::STATUSES,
			'frequencies' => SensitiveCaseService::FREQUENCIES,
			'categories' => $db->query('SELECT * FROM sensitive_categories WHERE active = 1 ORDER BY sort_order')->fetchAll(\PDO::FETCH_ASSOC),
			'plans' => $db->query('SELECT pl.*, b.name AS branch_name, c.name AS category_name, p.name AS product_name FROM sensitive_count_plans pl LEFT JOIN branches b ON b.id = pl.branch_id LEFT JOIN sensitive_categories c ON c.id = pl.category_id LEFT JOIN products p ON p.id = pl.product_id ORDER BY pl.active DESC, pl.id DESC')->fetchAll(\PDO::FETCH_ASSOC),
			'canSupervise' => DairyAccess::HasAny(SensitiveCaseService::SupervisorPerms()),
			'canApprove' => DairyAccess::HasAny(SensitiveCaseService::ManagerPerms()),
		]);
	}

	public function Merchandise(Request $request, Response $response, array $args): Response
	{
		$f = $request->getQueryParams();
		if (!empty($f['branch_id']) && !DairyAccess::CanSeeBranch(intval($f['branch_id'])))
		{
			unset($f['branch_id']);
		}
		$f += ['from' => date('Y-m-d', strtotime('-30 days')), 'to' => date('Y-m-d'), 'dim' => 'branch'];
		$dims = \Grocy\Services\MerchandiseService::DIMS;
		$f['dim'] = isset($dims[$f['dim']]) ? $f['dim'] : 'branch';
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		return $this->RenderPage($response, 'merchandise', [
			'd' => \Grocy\Services\MerchandiseService::GetInstance()->Dashboard($f), 'f' => $f, 'dims' => $dims, 'branches' => $this->Branches(),
			'products' => $db->query('SELECT id, name, sku FROM products WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'entryLabels' => \Grocy\Services\MerchandiseService::ENTRIES, 'exitLabels' => \Grocy\Services\MerchandiseService::EXITS,
			'bucketLabels' => \Grocy\Services\LossReconciliationService::BUCKETS + ['internal' => 'Movimiento interno', 'transit' => 'En tránsito'],
		]);
	}

	public function CaseDetail(Request $request, Response $response, array $args): Response
	{
		return $this->RenderPage($response, 'loss_case', ['d' => SensitiveCaseService::GetInstance()->Detail(intval($args['id']))]);
	}

	public function Devices(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$svc = \Grocy\Services\DeviceService::GetInstance();
		$svc->GenerateCounts();
		return $this->RenderPage($response, 'devices', [
			'data' => $svc->Dashboard($q), 'filters' => $q, 'branches' => $this->Branches(),
			'states' => \Grocy\Services\DeviceService::STATES, 'zones' => \Grocy\Services\DeviceService::ZONES, 'reasons' => \Grocy\Services\DeviceService::COUNT_REASONS,
			'products' => DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name FROM products WHERE device_control = 1 AND active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'canSupervise' => DairyAccess::HasAny(SensitiveCaseService::SupervisorPerms()), 'canApprove' => DairyAccess::HasAny(SensitiveCaseService::ManagerPerms()),
		]);
	}

	public function Device(Request $request, Response $response, array $args): Response
	{
		return $this->RenderPage($response, 'device_detail', ['d' => \Grocy\Services\DeviceService::GetInstance()->Detail(intval($args['id'])), 'states' => \Grocy\Services\DeviceService::STATES]);
	}

	public function Counts(Request $request, Response $response, array $args): Response
	{
		$branchId = $this->Branch($request);
		$svc = SensitiveCaseService::GetInstance();
		$svc->GenerateTasks($branchId);
		return $this->RenderPage($response, 'loss_counts', ['tasks' => $svc->Tasks($branchId, GROCY_USER_ID), 'branches' => $this->Branches(), 'selectedBranchId' => $branchId]);
	}
}
