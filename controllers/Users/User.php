<?php

namespace Grocy\Controllers\Users;

use Grocy\Services\DatabaseService;
use LessQL\Result;

class User
{
	const PERMISSION_ADMIN = 'ADMIN';
	const PERMISSION_BATTERIES = 'BATTERIES';
	const PERMISSION_BATTERIES_TRACK_CHARGE_CYCLE = 'BATTERIES_TRACK_CHARGE_CYCLE';
	const PERMISSION_BATTERIES_UNDO_CHARGE_CYCLE = 'BATTERIES_UNDO_CHARGE_CYCLE';
	const PERMISSION_CALENDAR = 'CALENDAR';
	const PERMISSION_CHORES = 'CHORES';
	const PERMISSION_CHORE_TRACK_EXECUTION = 'CHORE_TRACK_EXECUTION';
	const PERMISSION_CHORE_UNDO_EXECUTION = 'CHORE_UNDO_EXECUTION';
	const PERMISSION_EQUIPMENT = 'EQUIPMENT';
	const PERMISSION_MASTER_DATA_EDIT = 'MASTER_DATA_EDIT';
	const PERMISSION_RECIPES = 'RECIPES';
	const PERMISSION_RECIPES_MEALPLAN = 'RECIPES_MEALPLAN';
	const PERMISSION_SHOPPINGLIST = 'SHOPPINGLIST';
	const PERMISSION_SHOPPINGLIST_ITEMS_ADD = 'SHOPPINGLIST_ITEMS_ADD';
	const PERMISSION_SHOPPINGLIST_ITEMS_DELETE = 'SHOPPINGLIST_ITEMS_DELETE';
	const PERMISSION_STOCK = 'STOCK';
	const PERMISSION_STOCK_CONSUME = 'STOCK_CONSUME';
	const PERMISSION_STOCK_EDIT = 'STOCK_EDIT';
	const PERMISSION_STOCK_INVENTORY = 'STOCK_INVENTORY';
	const PERMISSION_STOCK_OPEN = 'STOCK_OPEN';
	const PERMISSION_STOCK_PURCHASE = 'STOCK_PURCHASE';
	const PERMISSION_STOCK_TRANSFER = 'STOCK_TRANSFER';
	const PERMISSION_TASKS = 'TASKS';
	const PERMISSION_TASKS_MARK_COMPLETED = 'TASKS_MARK_COMPLETED';
	const PERMISSION_TASKS_UNDO_EXECUTION = 'TASKS_UNDO_EXECUTION';
	const PERMISSION_USERS = 'USERS';
	const PERMISSION_USERS_CREATE = 'USERS_CREATE';
	const PERMISSION_USERS_EDIT = 'USERS_EDIT';
	const PERMISSION_USERS_EDIT_SELF = 'USERS_EDIT_SELF';
	const PERMISSION_USERS_READ = 'USERS_READ';

	// Enterprise DAIRY Permissions (SAP S/4HANA & AKCAL Model)
	const PERMISSION_WASTE_REPORT = 'WASTE_REPORT';
	const PERMISSION_WASTE_APPROVE = 'WASTE_APPROVE';
	const PERMISSION_WASTE_REASONS_EDIT = 'WASTE_REASONS_EDIT';
	const PERMISSION_SALES_POS = 'SALES_POS';
	const PERMISSION_SALES_CANCEL = 'SALES_CANCEL';
	const PERMISSION_SALES_VIEW = 'SALES_VIEW';
	const PERMISSION_POS_INTEGRATION = 'POS_INTEGRATION';
	const PERMISSION_KPIS_VIEW = 'KPIS_VIEW';
	const PERMISSION_BRANCH_MANAGE = 'BRANCH_MANAGE';
	const PERMISSION_PROCUREMENT_MANAGE = 'PROCUREMENT_MANAGE';
	const PERMISSION_PROCUREMENT_RECEIVE = 'PROCUREMENT_RECEIVE';
	const PERMISSION_QUALITY_INSPECT = 'QUALITY_INSPECT';
	const PERMISSION_QUALITY_QUARANTINE = 'QUALITY_QUARANTINE';
	const PERMISSION_INVENTORY_ADJUST_APPROVE = 'INVENTORY_ADJUST_APPROVE';
	const PERMISSION_WORKFORCE = 'WORKFORCE';
	const PERMISSION_WORKFORCE_SCHEDULE_MANAGE = 'WORKFORCE_SCHEDULE_MANAGE';
	const PERMISSION_WORKFORCE_ATTENDANCE_MANAGE = 'WORKFORCE_ATTENDANCE_MANAGE';
	const PERMISSION_WORKFORCE_OVERTIME_APPROVE = 'WORKFORCE_OVERTIME_APPROVE';
	const PERMISSION_BUDGET_MANAGE = 'BUDGET_MANAGE';
	const PERMISSION_LOSS_PREVENTION = 'LOSS_PREVENTION';
	const PERMISSION_ALERTS_MANAGE = 'ALERTS_MANAGE';
	const PERMISSION_ALERTS_SUPERVISE = 'ALERTS_SUPERVISE';
	const PERMISSION_RECALL_MANAGE = 'RECALL_MANAGE';
	const PERMISSION_AUDIT_PERFORM = 'AUDIT_PERFORM';
	const PERMISSION_SCAN_OPERATE = 'SCAN_OPERATE';
	const PERMISSION_WASTE_APPROVE_HIGH = 'WASTE_APPROVE_HIGH';
	const PERMISSION_DATA_IMPORT = 'DATA_IMPORT';
	const PERMISSION_ORDERS_MANAGE = 'ORDERS_MANAGE';
	const PERMISSION_ORDERS_PICK = 'ORDERS_PICK';

	public function __construct()
	{
		$this->DB = DatabaseService::GetInstance()->GetDbConnection();
	}

	protected $DB;

	public static function PermissionList()
	{
		$user = new self();
		return $user->GetPermissionList();
	}

	public static function CheckPermission($request, string $permission): void
	{
		$user = new self();
		if (!$user->HasPermission($permission))
		{
			throw new PermissionMissingException($request, $permission);
		}
	}

	public function GetPermissionList()
	{
		return $this->DB->uihelper_user_permissions()->where('user_id', GROCY_USER_ID);
	}

	public function HasPermission(string $permission): bool
	{
		$permissions = $this->GetPermissions();
		if ($permissions->where('permission_name', self::PERMISSION_ADMIN)->fetch() !== null)
		{
			return true;
		}

		return $this->GetPermissions()->where('permission_name', $permission)->fetch() !== null;
	}

	public static function HasPermissions(string ...$permissions)
	{
		$user = new self();

		foreach ($permissions as $permission)
		{
			if (!$user->HasPermission($permission))
			{
				return false;
			}
		}

		return true;
	}

	protected function GetPermissions(): Result
	{
		return $this->DB->user_permissions_resolved()->where('user_id', GROCY_USER_ID);
	}
}
