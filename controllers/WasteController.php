<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\LossIntelligenceService;
use Grocy\Services\LossCensusService;
use Grocy\Services\DatabaseService;
use Grocy\Services\WasteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WasteController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$status = $params['status'] ?? null;
		$workflowStatus = $params['workflow_status'] ?? null;
		$riskLevel = $params['risk_level'] ?? null;
		$category = $params['category'] ?? null;
		$reasonId = isset($params['reason_id']) && $params['reason_id'] !== '' ? intval($params['reason_id']) : null;
		$period = $params['period'] ?? 'month';
		$startDate = $params['start_date'] ?? null;
		$endDate = $params['end_date'] ?? null;

		if (empty($startDate) && empty($endDate))
		{
			$startDate = date('Y-m-01');
			$endDate = date('Y-m-t');
		}

		$wasteService = WasteService::GetInstance();
		$branchService = BranchService::GetInstance();

		$wasteRecords = $wasteService->GetWasteRecords(
			$branchId,
			$status,
			$workflowStatus,
			$riskLevel,
			$category,
			$startDate,
			$endDate,
			$reasonId
		);

		$analytics = $wasteService->GetWasteAnalytics($branchId, $period, $startDate, $endDate);
		$reasons = $wasteService->GetWasteReasons(false, null, null, null, true);
		$categories = $wasteService->GetWasteCategories();
		$branches = $branchService->GetBranches();
		$users = $this->DB->users()->orderBy('username')->fetchAll();
		$suppliers = $this->DB->shopping_locations()->orderBy('name')->fetchAll();

		$akcalService = \Grocy\Services\AkcalMetricsService::GetInstance();
		$akcalMetrics = $akcalService->GetAkcalExecutiveMetrics($branchId, $period, $startDate, $endDate);
		$censoShrink = $akcalService->GetCensoShrinkBreakdown($branchId, $period, $startDate, $endDate);
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Products for Quick Registration in Tab 3 (incluye código de barras primario para escáner UPC)
		$products = $dbRaw->query("
			SELECT p.id, p.name, IFNULL(p.sku, '—') as sku,
				   (SELECT pb.barcode FROM product_barcodes pb WHERE pb.product_id = p.id ORDER BY pb.id ASC LIMIT 1) as barcode,
				   qu.name as qu_name,
				   COALESCE((SELECT price FROM stock_log WHERE product_id = p.id AND transaction_type = 'purchase' ORDER BY id DESC LIMIT 1), 
							(SELECT price FROM stock_log WHERE product_id = p.id ORDER BY id DESC LIMIT 1), 
							p.sale_price, 0) as last_price,
				   COALESCE((SELECT SUM(amount) FROM stock WHERE product_id = p.id), 0) as current_stock,
				   p.location_id
			FROM products p
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			WHERE p.active = 1
			ORDER BY p.name ASC
		")->fetchAll(\PDO::FETCH_OBJ);

		$locations = $this->DB->locations()->orderBy('name')->fetchAll();
		$quantityUnits = $this->DB->quantity_units()->orderBy('name')->fetchAll();

		// Centro de Control: datos reales del período, departamento y sucursal seleccionados (fuente única)
		$photoKpis = $akcalService->GetControlCenterData($branchId, $startDate, $endDate);
		$lf = LossIntelligenceService::Filters($params);
		$lf['branch_id'] = $branchId;
		$lossSummary = LossIntelligenceService::GetInstance()->Summary($lf, $startDate, $endDate);
		$wasteExec = \Grocy\Services\WasteExecutiveControlCenterService::GetInstance()->GetExecutiveData(
			$branchId,
			!empty($params['date']) ? $params['date'] : $endDate,
			!empty($params['department']) ? $params['department'] : null,
			!empty($params['causal']) ? $params['causal'] : null,
			!empty($params['metric']) ? $params['metric'] : 'pct',
			$startDate,
			$endDate
		);

		$rawDb = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$plansMatrix = $akcalService->GetActionPlansMatrix($branchId);
		$lpSummary = null;
		try
		{
			$lpSummary = \Grocy\Services\SensitiveCaseService::GetInstance()->Summary($branchId);
		}
		catch (\Throwable $e)
		{
			$lpSummary = ['open_cases' => 0, 'open_value' => 0.0, 'pending_counts' => 0];
		}

		$sharedFilters = \Grocy\Helpers\DailyFilters::FromQuery($params);
		$filterOptions = \Grocy\Helpers\DailyFilters::Options($sharedFilters);
		$wasteTab = $params['tab'] ?? 'centro-control';
		$wasteBoardSvc = new \Grocy\Services\WasteDashboardService();
		$wasteBoard = $wasteBoardSvc->GetDashboardData($sharedFilters);

		return $this->RenderPage($response, 'waste_overview', [
			'wasteBoard' => $wasteBoard,
			'sharedFilters' => $sharedFilters,
			'filterOptions' => $filterOptions,
			'wasteTab' => $wasteTab,
			'lpSummary' => $lpSummary,
			'plansKpis' => $plansMatrix['kpis'] ?? [],
			'wasteExec' => $wasteExec,
			'selectedDepartment' => $params['department'] ?? 'todos',
			'selectedCausal' => $params['causal'] ?? 'todas',
			'wasteRecords' => $wasteRecords,
			'analytics' => $analytics,
			'summary' => $analytics['summary'],
			'photoKpis' => $photoKpis,
			'lossSummary' => $lossSummary,
			'products' => $products,
			'locations' => $locations,
			'quantityUnits' => $quantityUnits,
			'reasons' => $reasons,
			'categories' => $categories,
			'branches' => $branches,
			'suppliers' => $suppliers,
			'users' => $users,
			'akcalMetrics' => $akcalMetrics,
			'censoShrink' => $censoShrink,
			'markdownRules' => \Grocy\Services\MarkdownService::GetInstance()->GetRules(),
			'activeMarkdowns' => \Grocy\Services\MarkdownService::GetInstance()->GetActiveMarkdowns(),
			'selectedBranchId' => $branchId,
			'selectedStatus' => $status,
			'selectedWorkflowStatus' => $workflowStatus,
			'selectedRiskLevel' => $riskLevel,
			'selectedCategory' => $category,
			'selectedReasonId' => $reasonId,
			'selectedPeriod' => $period,
			'startDate' => $analytics['start_date'],
			'endDate' => $analytics['end_date']
		]);
	}

	/**
	 * Pestañas de waste_overview que se cargan por AJAX al activarlas (planes, recuperación, FEFO, conteos, auditoría).
	 */
	public function Tab(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$startDate = !empty($params['start_date']) ? $params['start_date'] : date('Y-m-01');
		$endDate = !empty($params['end_date']) ? $params['end_date'] : date('Y-m-t');

		$akcalService = \Grocy\Services\AkcalMetricsService::GetInstance();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$data = [];

		switch ($args['tab'])
		{
			case 'planes':
			case 'acciones':
			case 'investigaciones':
				$matrix = $akcalService->GetActionPlansMatrix($branchId);
				$data['actionPlans'] = $matrix['plans'];
				$data['plansKpis'] = $matrix['kpis'];
				$branchSql = $branchId ? " AND wr.branch_id = " . intval($branchId) : "";
				$data['cases'] = $dbRaw->query("
					SELECT wr.*, p.name as product_name, p.picture_file_name, u.username as investigator_username,
					       lc.name as loss_cause_name, lc.code as loss_cause_code,
					       r.name as reason_name, r.code as reason_code,
					       b.name as branch_name, loc.name as location_name
					FROM waste_records wr
					JOIN products p ON wr.product_id = p.id
					LEFT JOIN branches b ON wr.branch_id = b.id
					LEFT JOIN locations loc ON wr.location_id = loc.id
					LEFT JOIN users u ON wr.investigator_user_id = u.id
					LEFT JOIN waste_reasons r ON wr.waste_reason_id = r.id
					LEFT JOIN loss_causes lc ON r.loss_cause_code = lc.code
					WHERE wr.workflow_status IN ('investigating', 'reviewing')
					  AND IFNULL(r.censo_category, '') NOT IN ('robo_interno', 'robo_externo')
					  AND UPPER(IFNULL(r.code, '')) NOT IN ('M03', 'M04', 'FPO01', 'FEL01') $branchSql
					ORDER BY wr.id DESC
				")->fetchAll(\PDO::FETCH_ASSOC);
				$data['users'] = $dbRaw->query("SELECT id, username, first_name, last_name FROM users ORDER BY username")->fetchAll(\PDO::FETCH_ASSOC);
				$data['reasons'] = $dbRaw->query("SELECT id, code, name FROM waste_reasons WHERE active = 1 AND IFNULL(censo_category, '') NOT IN ('robo_interno', 'robo_externo') AND UPPER(IFNULL(code, '')) NOT IN ('M03', 'M04', 'FPO01', 'FEL01') ORDER BY code")->fetchAll(\PDO::FETCH_ASSOC);
				$data['branches'] = DairyAccess::FilterBranches($dbRaw->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), 'id');
				$data['canInvestigate'] = DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
				$data['canPlan'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REASONS_EDIT, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ADMIN]);
				$data['canAssign'] = DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
				$data['canExport'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
				$data['startDate'] = $startDate;
				$data['endDate'] = $endDate;
				$data['branchId'] = $branchId;
				break;
			case 'recuperacion':
				$data['supplierReturns'] = $akcalService->GetSupplierReturnsConsolidated($branchId)['returns'];
				$data['photoKpis'] = $akcalService->GetControlCenterData($branchId, $startDate, $endDate);
				break;
			case 'fefo':
				$data['fefoLots'] = $dbRaw->query("
					SELECT s.*, p.name as product_name, IFNULL(p.sku, '—') as product_sku,
					       p.picture_file_name, p.product_group_id,
					       IFNULL(pg.name, 'Sin categoría') as category_name,
					       IFNULL(qu.name, 'u.') as qu_name,
					       IFNULL(l.name, 'Sin ubicación') as location_name,
					       l.is_freezer,
					       CAST(julianday(s.best_before_date) - julianday('now') AS INTEGER) as days_left,
					       (SELECT COUNT(*) FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine') AS is_quarantined
					FROM stock s
					JOIN products p ON s.product_id = p.id
					LEFT JOIN locations l ON s.location_id = l.id
					LEFT JOIN product_groups pg ON p.product_group_id = pg.id
					LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
					ORDER BY s.best_before_date ASC
				")->fetchAll(\PDO::FETCH_OBJ);
				$data['cold'] = LossIntelligenceService::GetInstance()->ColdChain($branchId, $startDate, min($endDate, date('Y-m-d')));
				$data['photoKpis'] = $akcalService->GetControlCenterData($branchId, $startDate, $endDate);
				$data['canQuarantine'] = DairyAccess::HasAny([User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_ADMIN]);
				$data['branchId'] = $branchId;
				$data['startDate'] = $startDate;
				$data['endDate'] = $endDate;
				$data['lookups'] = $this->LossLookups();
				$data['locationsList'] = $dbRaw->query("SELECT id, name, is_freezer, branch_id FROM locations WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
				$data['productGroups'] = $dbRaw->query("SELECT id, name FROM product_groups WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
				break;
			case 'conteos':
				$data['rank'] = LossIntelligenceService::GetInstance()->SensitiveRanking($branchId, 100);
				$data['branchId'] = $branchId;
				$data['branchesList'] = DairyAccess::FilterBranches(DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), 'id');
				$cycleCountsData = $akcalService->GetSensitiveInventoryCycleCounts($branchId);
				$data['cycleCounts'] = $cycleCountsData['counts'];
				$data['cycleCountsKpi'] = $cycleCountsData['kpis'];
				$data['canSchedule'] = DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_ADMIN]);
				$data['canApprove'] = DairyAccess::HasAny([User::PERMISSION_INVENTORY_ADJUST_APPROVE, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
				break;
			case 'auditoria':
				$data['auditLogs'] = $dbRaw->query("
					SELECT al.*, 
					       u.username, u.first_name, u.last_name, 
					       wr.case_number, wr.total_cost, wr.amount, wr.branch_id, wr.notes as waste_notes,
					       p.name as product_name, p.sku,
					       b.name as branch_name
					FROM waste_action_logs al
					JOIN users u ON al.user_id = u.id
					LEFT JOIN waste_records wr ON al.waste_record_id = wr.id
					LEFT JOIN products p ON wr.product_id = p.id
					LEFT JOIN branches b ON wr.branch_id = b.id
					ORDER BY al.id DESC
					LIMIT 200
				")->fetchAll(\PDO::FETCH_OBJ);
				$data['totalAuditCount'] = intval($dbRaw->query("SELECT COUNT(*) FROM waste_action_logs")->fetchColumn());
				$data['users'] = $this->DB->users()->orderBy('username')->fetchAll();
				$data['branches'] = \Grocy\Services\BranchService::GetInstance()->GetBranches();
				$data['timezone'] = defined('GROCY_TIMEZONE') && GROCY_TIMEZONE !== '' ? GROCY_TIMEZONE : date_default_timezone_get();
				break;
			case 'prevencion':
			case 'rebajas':
				$data['activeMarkdowns'] = \Grocy\Services\MarkdownService::GetInstance()->GetActiveMarkdowns();
				$data['markdownRules'] = \Grocy\Services\MarkdownService::GetInstance()->GetRules();
				$data['photoKpis'] = $akcalService->GetControlCenterData($branchId, $startDate, $endDate);
				$data['canManageRules'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REASONS_EDIT, User::PERMISSION_ADMIN]);
				$args['tab'] = 'prevencion';
				break;
			case 'registro':
				$data['products'] = $dbRaw->query("
					SELECT p.id, p.name, COALESCE(p.sku, 'SKU-' || printf('%04d', p.id)) as sku, qu.name as qu_name,
						   COALESCE((SELECT price FROM stock_log WHERE product_id = p.id AND transaction_type = 'purchase' ORDER BY id DESC LIMIT 1), 
									(SELECT price FROM stock_log WHERE product_id = p.id ORDER BY id DESC LIMIT 1), 
									p.sale_price, 0) as last_price,
						   COALESCE((SELECT SUM(amount) FROM stock WHERE product_id = p.id), 0) as current_stock,
						   p.location_id, p.product_group_id, p.picture_file_name,
						   COALESCE(pg.name, '') as group_name
					FROM products p
					JOIN quantity_units qu ON p.qu_id_stock = qu.id
					LEFT JOIN product_groups pg ON p.product_group_id = pg.id
					WHERE p.active = 1
					ORDER BY p.name ASC
				")->fetchAll(\PDO::FETCH_OBJ);
				$data['reasons'] = \Grocy\Services\WasteService::GetInstance()->GetWasteReasons(true, null, null, null, true);
				$data['branches'] = \Grocy\Services\BranchService::GetInstance()->GetBranches();
				$data['locations'] = $this->DB->locations()->orderBy('name');
				$data['quantityUnits'] = $this->DB->quantity_units()->orderBy('name');
				$data['users'] = $this->DB->users()->orderBy('username')->fetchAll();
				$args['tab'] = 'registro';
				break;
			case 'perdida':
				$data['loss'] = \Grocy\Services\LossPreventionService::GetInstance()->TotalLoss($branchId, $startDate, min($endDate, date('Y-m-d')));
				break;
			case 'clasificacion':
			case 'ajustes':
			case 'averias':
			case 'pareto':
			case 'anual':
			case 'precios':
			case 'perecederos':
			case 'robo':
			case 'impacto':
			case 'censo':
			case 'errores':
			case 'controles':
				// Alcance de sucursal: un usuario limitado a una sucursal solo ve la suya
				$allowed = DairyAccess::AllowedBranches();
				if ($branchId !== null && !DairyAccess::CanSeeBranch($branchId))
				{
					return $response->withStatus(403);
				}
				if ($branchId === null && $allowed !== null && count($allowed) === 1)
				{
					$branchId = $allowed[0];
				}
				$li = LossIntelligenceService::GetInstance();
				$f = LossIntelligenceService::Filters($params);
				$f['branch_id'] = $branchId;
				$data += ['f' => $f, 'startDate' => $startDate, 'endDate' => $endDate, 'lookups' => $this->LossLookups()];
				if ($args['tab'] === 'robo' && !DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]))
				{
					return $this->Render($response, 'components.waste_tabs.restricted', $data);
				}
				switch ($args['tab'])
				{
					case 'clasificacion':
					case 'ajustes':
					case 'averias':
						$data['section'] = $args['tab'];
						$data['cls'] = $li->Classification($f, $startDate, $endDate);
						$data['formats'] = $args['tab'] === 'clasificacion' ? $li->Formats($f, $startDate, $endDate) : null;
						$data['summary'] = $li->Summary($f, $startDate, $endDate);
						$args['tab'] = 'clasificacion';
						break;
					case 'pareto':
						$data['matrix'] = $li->CauseMatrix($f, $startDate, $endDate);
						$data['canEditCauses'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REASONS_EDIT, User::PERMISSION_ADMIN]);
						$data['standardReasons'] = WasteService::GetInstance()->GetWasteReasons(false);
						break;
					case 'anual':
						$years = array_slice(array_values(array_filter(array_map('intval', explode(',', strval($params['years'] ?? '')))) ), 0, 6);
						if (count($years) < 1)
						{
							$years = [intval(date('Y')) - 1, intval(date('Y'))];
						}
						$data['cmp'] = $li->YearComparison($f, $years, $params['metric'] ?? 'cost');
						break;
					case 'precios':
						$data['price'] = $li->PriceIssues($branchId, $params['price_status'] ?? null);
						$data['canApprove'] = DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ADMIN]);
						break;
					case 'perecederos':
						$data['per'] = $li->Perishables($f, $startDate, $endDate);
						$data['dest'] = LossCensusService::GetInstance()->PerishableDestinations($f, $startDate, $endDate);
						break;
					case 'robo':
						$data['theft'] = $li->Theft($f, $startDate, $endDate);
						$data['intel'] = LossCensusService::GetInstance()->TheftIntel($f, $startDate, $endDate);
						$data['canTheft'] = DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
						$data['canReport'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT, User::PERMISSION_ADMIN]);
						$data['canExport'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
						break;
					case 'censo':
						$cs = LossCensusService::GetInstance();
						$data['h'] = $cs->Hierarchy($f, $startDate, $endDate);
						$data['monthly'] = $cs->Monthly($f, intval($params['year'] ?? substr($endDate, 0, 4)));
						$data['dept'] = $cs->Departments($f, $startDate, $endDate);
						$data['targets'] = $cs->Targets();
						$data['benchmarks'] = $cs->Benchmarks();
						$data['canEdit'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REASONS_EDIT, User::PERMISSION_ADMIN]);
						break;
					case 'errores':
						$data['ae'] = LossCensusService::GetInstance()->AdminErrors($branchId, $params['ae_status'] ?? 'open', $params['ae_category'] ?? null);
						$data['canApprove'] = DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ADMIN]);
						break;
					case 'controles':
						$data['eco'] = LossCensusService::GetInstance()->Economic($f, $startDate, $endDate);
						$data['canEdit'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REASONS_EDIT, User::PERMISSION_ADMIN]);
						$data['canTheft'] = DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
						$data['causesList'] = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT code, name FROM loss_causes WHERE active = 1 ORDER BY code')->fetchAll(\PDO::FETCH_ASSOC);
						break;
					case 'impacto':
						$data['fin'] = $li->Financial($f, $startDate, $endDate);
						$data['photoKpis'] = $akcalService->GetControlCenterData($branchId, $startDate, $endDate);
						$data['supplierReturns'] = $akcalService->GetSupplierReturnsConsolidated($branchId)['returns'];
						$data['loss'] = \Grocy\Services\LossPreventionService::GetInstance()->TotalLoss($branchId, $startDate, min($endDate, date('Y-m-d')));
						$data['canConfig'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REASONS_EDIT, User::PERMISSION_ADMIN]);
						$data['canClaim'] = DairyAccess::HasAny([User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_ADMIN]);
						$data['canExport'] = DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
						break;
				}
				break;
			default:
				return $response->withStatus(404);
		}

		$targetView = in_array($args['tab'], ['planes', 'acciones'], true) ? 'investigaciones' : $args['tab'];
		return $this->Render($response, 'components.waste_tabs.' . $targetView, $data);
	}

	private function LossLookups(): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		return [
			'branches' => DairyAccess::FilterBranches($db->query('SELECT id, name, format FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC), 'id'),
			'groups' => $db->query('SELECT id, name FROM product_groups WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'suppliers' => $db->query('SELECT id, name FROM shopping_locations ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'formats' => $db->query("SELECT DISTINCT format FROM branches WHERE IFNULL(format, '') <> '' ORDER BY format")->fetchAll(\PDO::FETCH_COLUMN),
			'products' => $db->query("SELECT id, name, IFNULL(sku, '') AS sku FROM products WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC),
		];
	}

	public function ReportForm(Request $request, Response $response, array $args)
	{
		$wasteService = WasteService::GetInstance();
		$branchService = BranchService::GetInstance();

		$products = DatabaseService::GetInstance()->GetDbConnectionRaw()->query("
			SELECT p.id, p.name, COALESCE(p.sku, 'SKU-' || printf('%04d', p.id)) as sku, qu.name as qu_name,
				   COALESCE((SELECT price FROM stock_log WHERE product_id = p.id AND transaction_type = 'purchase' ORDER BY id DESC LIMIT 1), 
							(SELECT price FROM stock_log WHERE product_id = p.id ORDER BY id DESC LIMIT 1), 
							p.sale_price, 0) as last_price,
				   COALESCE((SELECT SUM(amount) FROM stock WHERE product_id = p.id), 0) as current_stock,
				   p.location_id, p.product_group_id, p.picture_file_name,
				   COALESCE(pg.name, '') as group_name
			FROM products p
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN product_groups pg ON p.product_group_id = pg.id
			WHERE p.active = 1
			ORDER BY p.name ASC
		")->fetchAll(\PDO::FETCH_OBJ);

		$startDate = date('Y-m-01');
		$endDate = date('Y-m-t');
		$lf = LossIntelligenceService::Filters([]);
		$lossSummary = LossIntelligenceService::GetInstance()->Summary($lf, $startDate, $endDate);

		return $this->RenderPage($response, 'waste_report', [
			'products' => $products,
			'reasons' => $wasteService->GetWasteReasons(true, null, null, null, true),
			'branches' => $branchService->GetBranches(),
			'locations' => $this->DB->locations()->orderBy('name'),
			'quantityUnits' => $this->DB->quantity_units()->orderBy('name'),
			'users' => $this->DB->users()->orderBy('username')->fetchAll(),
			'lossSummary' => $lossSummary
		]);
	}

	public function ApprovalsQueue(Request $request, Response $response, array $args)
	{
		$wasteService = WasteService::GetInstance();
		$branchService = BranchService::GetInstance();

		$pendingRecords = $wasteService->GetWasteRecords(null, 'pending_approval');
		$branches = $branchService->GetBranches();
		$categories = $wasteService->GetWasteCategories();
		$reasons = $wasteService->GetWasteReasons(false);
		$currentUser = $this->DB->users()->where('id', GROCY_USER_ID)->fetch();
		$canSelfApprove = defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL;

		return $this->RenderPage($response, 'waste_approvals', [
			'pendingRecords' => $pendingRecords,
			'branches' => $branches,
			'categories' => $categories,
			'reasons' => $reasons,
			'currentUser' => $currentUser,
			'currentUserId' => GROCY_USER_ID,
			'canSelfApprove' => $canSelfApprove
		]);
	}

	public function ReasonsList(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$category = $params['category'] ?? null;
		$riskLevel = $params['risk_level'] ?? null;
		$area = $params['area'] ?? null;
		$showAll = isset($params['all']) ? true : false;

		$wasteService = WasteService::GetInstance();
		$reasons = $wasteService->GetWasteReasons(!$showAll, $category, $riskLevel, $area, true);
		$categories = $wasteService->GetWasteCategories();
		$areas = $wasteService->GetResponsibleAreas();

		return $this->RenderPage($response, 'waste_reasons', [
			'reasons' => $reasons,
			'categories' => $categories,
			'areas' => $areas,
			'selectedCategory' => $category,
			'selectedRiskLevel' => $riskLevel,
			'selectedArea' => $area,
			'showAll' => $showAll
		]);
	}
}
