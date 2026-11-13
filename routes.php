<?php

use Grocy\Controllers\Api\AlertCenterApiController;
use Grocy\Controllers\Api\SubscriptionApiController;
use Grocy\Controllers\SubscriptionController;
use Grocy\Controllers\Api\BatteriesApiController;
use Grocy\Controllers\Api\CalendarApiController;
use Grocy\Controllers\Api\ChoresApiController;
use Grocy\Controllers\Api\FilesApiController;
use Grocy\Controllers\Api\GenericEntityApiController;
use Grocy\Controllers\Api\OpenApiController;
use Grocy\Controllers\Api\PhysicalCountApiController;
use Grocy\Controllers\Api\PrintApiController;
use Grocy\Controllers\Api\RecipesApiController;
use Grocy\Controllers\Api\StockApiController;
use Grocy\Controllers\Api\SystemApiController;
use Grocy\Controllers\Api\TasksApiController;
use Grocy\Controllers\Api\UsersApiController;
use Grocy\Controllers\BatteriesController;
use Grocy\Controllers\CalendarController;
use Grocy\Controllers\ChoresController;
use Grocy\Controllers\EquipmentController;
use Grocy\Controllers\GenericEntityController;
use Grocy\Controllers\LoginController;
use Grocy\Controllers\RecipesController;
use Grocy\Controllers\StockController;
use Grocy\Controllers\StockReportsController;
use Grocy\Controllers\SiteController;
use Grocy\Controllers\SystemController;
use Grocy\Controllers\TasksController;
use Grocy\Controllers\UsersController;
use Grocy\Controllers\WasteController;
use Grocy\Controllers\SalesController;
use Grocy\Controllers\KpiController;
use Grocy\Controllers\BranchController;
use Grocy\Controllers\SupplierProcurementController;
use Grocy\Controllers\Api\WasteApiController;
use Grocy\Controllers\Api\SalesApiController;
use Grocy\Controllers\Api\KpiApiController;
use Grocy\Controllers\Api\SupplierProcurementApiController;
use Grocy\Controllers\WorkforceController;
use Grocy\Controllers\ScheduleController;
use Grocy\Controllers\HrController;
use Grocy\Controllers\Api\HrApiController;
use Grocy\Controllers\WarehouseController;
use Grocy\Controllers\Api\WarehouseApiController;
use Grocy\Controllers\MrpController;
use Grocy\Controllers\StockTransferController;
use Grocy\Controllers\DocumentController;
use Grocy\Controllers\KardexController;
use Grocy\Controllers\Api\MrpApiController;
use Grocy\Controllers\Api\StockTransferApiController;
use Grocy\Controllers\Api\MovementControlApiController;
use Grocy\Controllers\Api\WorkforceApiController;
use Grocy\Controllers\Api\ScheduleApiController;
use Grocy\Controllers\AttendanceController;
use Grocy\Controllers\Api\AttendanceApiController;
use Grocy\Controllers\Api\BranchApiController;
use Grocy\Controllers\Api\MarkdownApiController;
use Grocy\Controllers\Api\LossPreventionApiController;
use Grocy\Controllers\Api\LossIntelligenceApiController;
use Grocy\Controllers\Api\LossCensusApiController;
use Grocy\Controllers\LossPreventionController;
use Grocy\Controllers\OperationsController;
use Grocy\Controllers\Api\OperationsApiController;
use Grocy\Controllers\Api\MobileApiController;
use Grocy\Controllers\Api\MobileWorkApiController;
use Grocy\Controllers\MobileController;
use Grocy\Controllers\SystemMapController;
use Grocy\Controllers\RetailPreventionController;
use Grocy\Controllers\Api\RetailPreventionApiController;
use Grocy\Controllers\Api\WorkforcePeakApiController;
use Grocy\Controllers\Api\SystemMapApiController;
use Grocy\Controllers\Api\GlobalSearchApiController;
use Grocy\Controllers\ReconciliationController;
use Grocy\Controllers\Api\ReconciliationApiController;
use Grocy\Middleware\CorsMiddleware;
use Grocy\Middleware\JsonMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('', function (RouteCollectorProxy $group)
{
	// System routes
	$group->get('/', [SystemController::class, 'Root'])->setName('root');
	$group->get('/app', [SystemController::class, 'AppEntry']);
	$group->get('/about', [SystemController::class, 'About']);

	// Sitio comercial público (rutas con nombre site.* = sin autenticación)
	$group->get('/features', [SiteController::class, 'Features'])->setName('site.features');
	$group->get('/features/{slug}', [SiteController::class, 'Feature'])->setName('site.feature');
	$group->get('/solutions', [SiteController::class, 'Solutions'])->setName('site.solutions');
	$group->get('/industries', [SiteController::class, 'Industries'])->setName('site.industries');
	$group->get('/industries/{slug}', [SiteController::class, 'Industry'])->setName('site.industry');
	$group->get('/pricing', [SiteController::class, 'Pricing'])->setName('site.pricing');
	$group->get('/demo', [SiteController::class, 'Demo'])->setName('site.demo');
	$group->get('/contact', [SiteController::class, 'Contact'])->setName('site.contact');
	$group->get('/privacy', [SiteController::class, 'Legal'])->setName('site.privacy');
	$group->get('/terms', [SiteController::class, 'Legal'])->setName('site.terms');
	$group->post('/site/lead', [SiteController::class, 'SubmitLead'])->setName('site.lead');
	$group->get('/manifest', [SystemController::class, 'Manifest']);
	$group->get('/barcodescannertesting', [SystemController::class, 'BarcodeScannerTesting']);

	// Login routes
	$group->get('/login', [LoginController::class, 'LoginPage'])->setName('login');
	$group->post('/login', [LoginController::class, 'ProcessLogin'])->setName('login');
	$group->get('/logout', [LoginController::class, 'Logout']);

	// Generic entity interaction
	$group->get('/userfields', [GenericEntityController::class, 'UserfieldsList']);
	$group->get('/userfield/{userfieldId}', [GenericEntityController::class, 'UserfieldEditForm']);
	$group->get('/userentities', [GenericEntityController::class, 'UserentitiesList']);
	$group->get('/userentity/{userentityId}', [GenericEntityController::class, 'UserentityEditForm']);
	$group->get('/userobjects/{userentityName}', [GenericEntityController::class, 'UserobjectsList']);
	$group->get('/userobject/{userentityName}/{userobjectId}', [GenericEntityController::class, 'UserobjectEditForm']);

	// User routes
	$group->get('/users', [UsersController::class, 'UsersList']);
	$group->get('/user/{userId}', [UsersController::class, 'UserEditForm']);
	$group->get('/user/{userId}/permissions', [UsersController::class, 'PermissionList']);
	$group->get('/user/{userId}/sessions', [UsersController::class, 'SessionList']);
	$group->get('/usersettings', [UsersController::class, 'UserSettings']);

	// Stock master data routes
	$group->get('/products', [StockController::class, 'ProductsList']);
	$group->get('/product/{productId}', [StockController::class, 'ProductEditForm']);
	$group->get('/quantityunits', [StockController::class, 'QuantityUnitsList']);
	$group->get('/quantityunit/{quantityunitId}', [StockController::class, 'QuantityUnitEditForm']);
	$group->get('/quantityunitconversion/{quConversionId}', [StockController::class, 'QuantityUnitConversionEditForm']);
	$group->get('/productgroups', [StockController::class, 'ProductGroupsList']);
	$group->get('/productgroup/{productGroupId}', [StockController::class, 'ProductGroupEditForm']);
	$group->get('/product/{productId}/grocycode', [StockController::class, 'ProductGrocycodeImage']);

	// Stock handling routes
	$group->get('/stockoverview', [StockController::class, 'Overview']);
	$group->get('/stockentries', [StockController::class, 'Stockentries']);
	$group->get('/purchase', [StockController::class, 'Purchase']);
	$group->get('/consume', [StockController::class, 'Consume']);
	$group->get('/transfer', [StockController::class, 'Transfer']);
	$group->get('/inventory', [StockController::class, 'Inventory']);
	$group->get('/inventory/stockouts', [StockController::class, 'Stockouts']);
	$group->get('/stockentry/{entryId}', [StockController::class, 'StockEntryEditForm']);
	$group->get('/stocksettings', [StockController::class, 'StockSettings']);
	$group->get('/locations', [StockController::class, 'LocationsList']);
	$group->get('/location/{locationId}', [StockController::class, 'LocationEditForm']);
	$group->get('/stockjournal', [StockController::class, 'Journal']);
	$group->get('/locationcontentsheet', [StockController::class, 'LocationContentSheet']);
	$group->get('/quantityunitpluraltesting', [StockController::class, 'QuantityUnitPluralFormTesting']);
	$group->get('/stockjournal/summary', [StockController::class, 'JournalSummary']);
	$group->get('/productbarcodes/{productBarcodeId}', [StockController::class, 'ProductBarcodesEditForm']);
	$group->get('/stockentry/{entryId}/grocycode', [StockController::class, 'StockEntryGrocycodeImage']);
	$group->get('/stockentry/{entryId}/label', [StockController::class, 'StockEntryGrocycodeLabel']);
	$group->get('/quantityunitconversionsresolved', [StockController::class, 'QuantityUnitConversionsResolved']);
	$group->get('/stockreports/spendings', [StockReportsController::class, 'Spendings']);

	// Stock price tracking
	$group->get('/shoppinglocations', [StockController::class, 'ShoppingLocationsList']);
	$group->get('/shoppinglocation/{shoppingLocationId}', [StockController::class, 'ShoppingLocationEditForm']);

	// Shopping list routes
	$group->get('/shoppinglist', [StockController::class, 'ShoppingList']);
	$group->get('/shoppinglistitem/{itemId}', [StockController::class, 'ShoppingListItemEditForm']);
	$group->get('/shoppinglist/{listId}', [StockController::class, 'ShoppingListEditForm']);
	$group->get('/shoppinglistsettings', [StockController::class, 'ShoppingListSettings']);

	// Recipe routes
	$group->get('/recipes', [RecipesController::class, 'Overview']);
	$group->get('/recipe/{recipeId}', [RecipesController::class, 'RecipeEditForm']);
	$group->get('/recipe/{recipeId}/pos/{recipePosId}', [RecipesController::class, 'RecipePosEditForm']);
	$group->get('/recipessettings', [RecipesController::class, 'RecipesSettings']);
	$group->get('/recipe/{recipeId}/grocycode', [RecipesController::class, 'RecipeGrocycodeImage']);

	// Meal plan routes
	$group->get('/mealplan', [RecipesController::class, 'MealPlan']);
	$group->get('/mealplansections', [RecipesController::class, 'MealPlanSectionsList']);
	$group->get('/mealplansection/{sectionId}', [RecipesController::class, 'MealPlanSectionEditForm']);

	// Chore routes
	$group->get('/choresoverview', [ChoresController::class, 'Overview']);
	$group->get('/choretracking', [ChoresController::class, 'TrackChoreExecution']);
	$group->get('/choresjournal', [ChoresController::class, 'Journal']);
	$group->get('/chores', [ChoresController::class, 'ChoresList']);
	$group->get('/chore/{choreId}', [ChoresController::class, 'ChoreEditForm']);
	$group->get('/choressettings', [ChoresController::class, 'ChoresSettings']);
	$group->get('/chore/{choreId}/grocycode', [ChoresController::class, 'ChoreGrocycodeImage']);

	// Battery routes
	$group->get('/batteriesoverview', [BatteriesController::class, 'Overview']);
	$group->get('/batterytracking', [BatteriesController::class, 'TrackChargeCycle']);
	$group->get('/batteriesjournal', [BatteriesController::class, 'Journal']);
	$group->get('/batteries', [BatteriesController::class, 'BatteriesList']);
	$group->get('/battery/{batteryId}', [BatteriesController::class, 'BatteryEditForm']);
	$group->get('/batteriessettings', [BatteriesController::class, 'BatteriesSettings']);
	$group->get('/battery/{batteryId}/grocycode', [BatteriesController::class, 'BatteryGrocycodeImage']);

	// Task routes
	$group->get('/tasks', [TasksController::class, 'Overview']);
	$group->get('/task/{taskId}', [TasksController::class, 'TaskEditForm']);
	$group->get('/taskcategories', [TasksController::class, 'TaskCategoriesList']);
	$group->get('/taskcategory/{categoryId}', [TasksController::class, 'TaskCategoryEditForm']);
	$group->get('/taskssettings', [TasksController::class, 'TasksSettings']);

	// Equipment routes
	$group->get('/equipment', [EquipmentController::class, 'Overview']);
	$group->get('/equipment/{equipmentId}', [EquipmentController::class, 'EditForm']);

	// Calendar routes
	$group->get('/calendar', [CalendarController::class, 'Overview']);

	// OpenAPI routes
	$group->get('/api', [OpenApiController::class, 'DocumentationUi']);
	$group->get('/manageapikeys', [OpenApiController::class, 'ApiKeysList']);
	$group->get('/manageapikeys/new', [OpenApiController::class, 'CreateNewApiKey']);

	// Enterprise Platform Routes
	// Waste Management
	$group->get('/waste', [WasteController::class, 'Overview']);
	$group->get('/waste/report', [WasteController::class, 'ReportForm']);
	$group->get('/waste/approvals', [WasteController::class, 'ApprovalsQueue']);
	$group->get('/waste/reasons', [WasteController::class, 'ReasonsList']);
	$group->get('/waste/tab/{tab}', [WasteController::class, 'Tab']);

	// Sales & POS
	$group->get('/pos', [SalesController::class, 'Pos']);
	$group->get('/pos/returns', [SalesController::class, 'PosReturns']);
	$group->get('/sales', [SalesController::class, 'SalesHistory']);
	$group->get('/customers', [SalesController::class, 'CustomersList']);
	$group->get('/customers/export', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Export']);
	$group->get('/customers/{customerId:[0-9]+}', [SalesController::class, 'CustomerProfile']);

	// Executive KPIs & Analytics
	$group->get('/today', [KpiController::class, 'Today']);
	// DAIRY Mobile (manager)
	$group->get('/m', [MobileController::class, 'App']);
	$group->get('/kpis', [KpiController::class, 'ExecutiveDashboard']);
	$group->get('/reports', [KpiController::class, 'ReportsHub']);
	$group->get('/reports/loss-executive', [\Grocy\Controllers\LossExecutiveReportController::class, 'Overview']);
	$group->get('/cost-centers', [KpiController::class, 'CostCenters']);

	// Multi-Branch Management
	$group->get('/branches', [BranchController::class, 'BranchesList']);
	$group->get('/branch/{branchId}/stock', [BranchController::class, 'BranchStock']);

	// Suppliers & Procurement
	$group->get('/suppliers', [SupplierProcurementController::class, 'Overview']);
	$group->get('/procurement', function (Request $request, Response $response) {
		return $response->withRedirect($this->get('UrlManager')->ConstructUrl('/suppliers' . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : '')), 301);
	});

	// Workforce Floor Operations (Mobile)
	$group->get('/workforce', [WorkforceController::class, 'StoreFloorView']);
	$group->get('/hr', [HrController::class, 'Dashboard']);
	$group->get('/hr/payroll/export', [HrController::class, 'ExportPayrollCsv']);
	$group->get('/schedule', [ScheduleController::class, 'Overview']);
	$group->get('/operations/scheduling', [ScheduleController::class, 'Overview']);
	$group->get('/attendance', [AttendanceController::class, 'Overview']);
	$group->get('/operations/attendance', [AttendanceController::class, 'Overview']);

	// MRP / Planificación de materiales
	$group->get('/mrp', [MrpController::class, 'Overview']);
	$group->get('/transfers', [StockTransferController::class, 'Overview']);
	$group->get('/inventory-control', [LossPreventionController::class, 'InventoryControl']);
	$group->get('/scan', [OperationsController::class, 'Scan']);
	$group->get('/data-import', [OperationsController::class, 'DataImport']);
	$group->get('/locations/labels', [OperationsController::class, 'LocationLabels']);
	$group->get('/locations/map', [OperationsController::class, 'LocationMap']);
	$group->get('/traceability', [OperationsController::class, 'Traceability']);
	// Calidad y vencimientos viven en Mermas › Vencimientos y FEFO (filtros: fefo_q, fefo_estado, branch_id)
	$group->get('/quality', function (Request $request, Response $response) {
		$qs = $request->getUri()->getQuery();
		return $response->withRedirect($this->get('UrlManager')->ConstructUrl('/waste' . ($qs !== '' ? '?' . $qs : '')) . '#tab-fefo', 302);
	});
	$group->get('/orders', [OperationsController::class, 'Orders']);
	$group->get('/dispatches', [RetailPreventionController::class, 'Dispatch']);
	$group->get('/dispatch', [RetailPreventionController::class, 'Dispatch']);
	$group->get('/returns', [RetailPreventionController::class, 'Returns']);
	$group->get('/recalls', [LossPreventionController::class, 'Recalls']);
	$group->get('/audits', [LossPreventionController::class, 'Audits']);
	$group->get('/alerts', [LossPreventionController::class, 'Alerts']);
	$group->get('/document/{type}/{id}', [DocumentController::class, 'Show']);
	$group->get('/kardex', [KardexController::class, 'Overview']);
	$group->get('/reconciliation', [ReconciliationController::class, 'Overview']);
	$group->get('/loss-prevention', [\Grocy\Controllers\LossCaseController::class, 'Dashboard']);
	$group->get('/merchandise', [\Grocy\Controllers\LossCaseController::class, 'Merchandise']);
	$group->get('/warehouse', [WarehouseController::class, 'Dashboard']);
	$group->get('/loss-prevention/counts', [\Grocy\Controllers\LossCaseController::class, 'Counts']);
	$group->get('/loss-prevention/devices', [\Grocy\Controllers\LossCaseController::class, 'Devices']);
	$group->get('/loss-prevention/devices/{id}', [\Grocy\Controllers\LossCaseController::class, 'Device']);
	$group->get('/loss-prevention/case/{id}', [\Grocy\Controllers\LossCaseController::class, 'CaseDetail']);
	$group->get('/traceability-reconciliation', function (Request $request, Response $response) {
		return $response->withRedirect($this->get('UrlManager')->ConstructUrl('/reconciliation' . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : '')), 301);
	});
	$group->get('/system-map', [SystemMapController::class, 'Overview']);
	$group->get('/action-center', [\Grocy\Controllers\DailyActionCenterController::class, 'Index']);
	$group->get('/process-flow', [\Grocy\Controllers\ProcessFlowController::class, 'Inbox']);
	$group->get('/demo/case', [\Grocy\Controllers\DemoCaseController::class, 'Overview']);
	$group->get('/admin/economics', [\Grocy\Controllers\ProductEconomicsController::class, 'Dashboard']);
	$group->get('/settings', [\Grocy\Controllers\SystemAdminController::class, 'Settings']);
	$group->get('/audit-log', [\Grocy\Controllers\SystemAdminController::class, 'AuditLog']);
	$group->get('/daily/set-role', [SystemController::class, 'SetSimulatedRole']);

	// Suscripción y planes DAILY
	$group->get('/subscription', [SubscriptionController::class, 'Overview']);
	$group->get('/daily/set-plan', [SubscriptionController::class, 'SetPreviewPlan']);
});

$app->group('/api', function (RouteCollectorProxy $group)
{
	// OpenAPI
	$group->get('/openapi/specification', [OpenApiController::class, 'DocumentationSpec']);

	// System
	// Suscripción y planes DAILY
	$group->get('/subscription', [SubscriptionApiController::class, 'Get']);
	$group->get('/subscription/capabilities', [SubscriptionApiController::class, 'Capabilities']);
	$group->post('/subscription/request', [SubscriptionApiController::class, 'RequestUpgrade']);
	$group->post('/subscription/change', [SubscriptionApiController::class, 'Change']);
	$group->post('/subscription/requests/{id:[0-9]+}/decide', [SubscriptionApiController::class, 'DecideRequest']);

	$group->get('/system/info', [SystemApiController::class, 'GetSystemInfo']);
	$group->get('/system/time', [SystemApiController::class, 'GetSystemTime']);
	$group->get('/system/db-changed-time', [SystemApiController::class, 'GetDbChangedTime']);
	$group->get('/system/config', [SystemApiController::class, 'GetConfig']);
	$group->post('/system/log-missing-localization', [SystemApiController::class, 'LogMissingLocalization']);
	$group->post('/system/client-error', [SystemApiController::class, 'LogClientError']);
	$group->get('/system/localization-strings', [SystemApiController::class, 'GetLocalizationStrings']);

	// Generic entity interaction
	$group->get('/objects/{entity}', [GenericEntityApiController::class, 'GetObjects']);
	$group->get('/objects/{entity}/{objectId}', [GenericEntityApiController::class, 'GetObject']);
	$group->post('/objects/{entity}', [GenericEntityApiController::class, 'AddObject']);
	$group->put('/objects/{entity}/{objectId}', [GenericEntityApiController::class, 'EditObject']);
	$group->delete('/objects/{entity}/{objectId}', [GenericEntityApiController::class, 'DeleteObject']);
	$group->get('/userfields/{entity}/{objectId}', [GenericEntityApiController::class, 'GetUserfields']);
	$group->put('/userfields/{entity}/{objectId}', [GenericEntityApiController::class, 'SetUserfields']);

	// Files
	$group->put('/files/{group}/{fileName}', [FilesApiController::class, 'UploadFile']);
	$group->get('/files/{group}/{fileName}', [FilesApiController::class, 'ServeFile']);
	$group->delete('/files/{group}/{fileName}', [FilesApiController::class, 'DeleteFile']);

	// Users
	$group->get('/users', [UsersApiController::class, 'GetUsers']);
	$group->post('/users', [UsersApiController::class, 'CreateUser']);
	$group->put('/users/{userId}', [UsersApiController::class, 'EditUser']);
	$group->delete('/users/{userId}', [UsersApiController::class, 'DeleteUser']);
	$group->get('/users/{userId}/permissions', [UsersApiController::class, 'ListPermissions']);
	$group->post('/users/{userId}/permissions', [UsersApiController::class, 'AddPermission']);
	$group->put('/users/{userId}/permissions', [UsersApiController::class, 'SetPermissions']);

	// User
	$group->get('/user', [UsersApiController::class, 'CurrentUser']);
	$group->get('/user/settings', [UsersApiController::class, 'GetUserSettings']);
	$group->get('/user/settings/{settingKey}', [UsersApiController::class, 'GetUserSetting']);
	$group->put('/user/settings/{settingKey}', [UsersApiController::class, 'SetUserSetting']);
	$group->delete('/user/settings/{settingKey}', [UsersApiController::class, 'DeleteUserSetting']);

	// Stock
	$group->get('/stock', [StockApiController::class, 'CurrentStock']);
	$group->get('/stock/entry/{entryId}', [StockApiController::class, 'StockEntry']);
	$group->put('/stock/entry/{entryId}', [StockApiController::class, 'EditStockEntry']);
	$group->get('/stock/volatile', [StockApiController::class, 'CurrentVolatileStock']);
	$group->get('/stock/products/{productId}', [StockApiController::class, 'ProductDetails']);
	$group->get('/stock/products/{productId}/entries', [StockApiController::class, 'ProductStockEntries']);
	$group->get('/stock/products/{productId}/locations', [StockApiController::class, 'ProductStockLocations']);
	$group->get('/stock/products/{productId}/price-history', [StockApiController::class, 'ProductPriceHistory']);
	$group->post('/stock/products/{productId}/add', [StockApiController::class, 'AddProduct']);
	$group->post('/stock/products/{productId}/consume', [StockApiController::class, 'ConsumeProduct']);
	$group->post('/stock/products/{productId}/transfer', [StockApiController::class, 'TransferProduct']);
	$group->post('/stock/products/{productId}/inventory', [StockApiController::class, 'InventoryProduct']);
	$group->post('/stock/products/{productId}/open', [StockApiController::class, 'OpenProduct']);
	$group->post('/stock/products/{productIdToKeep}/merge/{productIdToRemove}', [StockApiController::class, 'MergeProducts']);
	$group->post('/stock/products/{productId}/copy', [StockApiController::class, 'CopyProduct']);
	$group->get('/stock/products/by-barcode/{barcode}', [StockApiController::class, 'ProductDetailsByBarcode']);
	$group->post('/stock/products/by-barcode/{barcode}/add', [StockApiController::class, 'AddProductByBarcode']);
	$group->post('/stock/products/by-barcode/{barcode}/consume', [StockApiController::class, 'ConsumeProductByBarcode']);
	$group->post('/stock/products/by-barcode/{barcode}/transfer', [StockApiController::class, 'TransferProductByBarcode']);
	$group->post('/stock/products/by-barcode/{barcode}/inventory', [StockApiController::class, 'InventoryProductByBarcode']);
	$group->post('/stock/products/by-barcode/{barcode}/open', [StockApiController::class, 'OpenProductByBarcode']);
	$group->get('/stock/locations/{locationId}/entries', [StockApiController::class, 'LocationStockEntries']);
	$group->get('/stock/bookings/{bookingId}', [StockApiController::class, 'StockBooking']);
	$group->post('/stock/bookings/{bookingId}/undo', [StockApiController::class, 'UndoBooking']);
	$group->get('/stock/transactions/{transactionId}', [StockApiController::class, 'StockTransactions']);
	$group->post('/stock/transactions/{transactionId}/undo', [StockApiController::class, 'UndoTransaction']);
	$group->get('/stock/barcodes/external-lookup/{barcode}', [StockApiController::class, 'ExternalBarcodeLookup']);
	$group->get('/stock/products/{productId}/printlabel', [StockApiController::class, 'ProductPrintLabel']);
	$group->get('/stock/entry/{entryId}/printlabel', [StockApiController::class, 'StockEntryPrintLabel']);

	// Shopping list
	$group->post('/stock/shoppinglist/add-missing-products', [StockApiController::class, 'AddMissingProductsToShoppingList']);
	$group->post('/stock/shoppinglist/add-overdue-products', [StockApiController::class, 'AddOverdueProductsToShoppingList']);
	$group->post('/stock/shoppinglist/add-expired-products', [StockApiController::class, 'AddExpiredProductsToShoppingList']);
	$group->post('/stock/shoppinglist/clear', [StockApiController::class, 'ClearShoppingList']);
	$group->post('/stock/shoppinglist/add-product', [StockApiController::class, 'AddProductToShoppingList']);
	$group->post('/stock/shoppinglist/remove-product', [StockApiController::class, 'RemoveProductFromShoppingList']);

	// Recipes
	$group->post('/recipes/{recipeId}/add-not-fulfilled-products-to-shoppinglist', [RecipesApiController::class, 'AddNotFulfilledProductsToShoppingList']);
	$group->get('/recipes/{recipeId}/fulfillment', [RecipesApiController::class, 'GetRecipeFulfillment']);
	$group->post('/recipes/{recipeId}/consume', [RecipesApiController::class, 'ConsumeRecipe']);
	$group->get('/recipes/fulfillment', [RecipesApiController::class, 'GetRecipeFulfillment']);
	$group->Post('/recipes/{recipeId}/copy', [RecipesApiController::class, 'CopyRecipe']);
	$group->get('/recipes/{recipeId}/printlabel', [RecipesApiController::class, 'RecipePrintLabel']);


	// Chores
	$group->get('/chores', [ChoresApiController::class, 'Current']);
	$group->get('/chores/{choreId}', [ChoresApiController::class, 'ChoreDetails']);
	$group->post('/chores/{choreId}/execute', [ChoresApiController::class, 'TrackChoreExecution']);
	$group->post('/chores/executions/{executionId}/undo', [ChoresApiController::class, 'UndoChoreExecution']);
	$group->post('/chores/executions/calculate-next-assignments', [ChoresApiController::class, 'CalculateNextExecutionAssignments']);
	$group->get('/chores/{choreId}/printlabel', [ChoresApiController::class, 'ChorePrintLabel']);
	$group->post('/chores/{choreIdToKeep}/merge/{choreIdToRemove}', [ChoresApiController::class, 'MergeChores']);

	// Printing
	$group->get('/print/shoppinglist/thermal', [PrintApiController::class, 'PrintShoppingListThermal']);

	// Batteries
	$group->get('/batteries', [BatteriesApiController::class, 'Current']);
	$group->get('/batteries/{batteryId}', [BatteriesApiController::class, 'BatteryDetails']);
	$group->post('/batteries/{batteryId}/charge', [BatteriesApiController::class, 'TrackChargeCycle']);
	$group->post('/batteries/charge-cycles/{chargeCycleId}/undo', [BatteriesApiController::class, 'UndoChargeCycle']);
	$group->get('/batteries/{batteryId}/printlabel', [BatteriesApiController::class, 'BatteryPrintLabel']);

	// Tasks
	$group->get('/tasks', [TasksApiController::class, 'Current']);
	$group->post('/tasks/{taskId}/complete', [TasksApiController::class, 'MarkTaskAsCompleted']);
	$group->post('/tasks/{taskId}/undo', [TasksApiController::class, 'UndoTask']);

	// Calendar
	$group->get('/calendar/ical', [CalendarApiController::class, 'Ical'])->setName('calendar-ical');
	$group->get('/calendar/ical/sharing-link', [CalendarApiController::class, 'IcalSharingLink']);

	// Enterprise Platform API Routes
	// Waste API
	$group->get('/waste/reasons', [WasteApiController::class, 'GetWasteReasons']);
	$group->get('/waste/reasons/{reasonId}', [WasteApiController::class, 'GetWasteReason']);
	$group->post('/waste/reasons', [WasteApiController::class, 'SaveWasteReason']);
	$group->post('/waste/reasons/{reasonId}/toggle-active', [WasteApiController::class, 'ToggleWasteReasonActive']);
	$group->get('/waste/records', [WasteApiController::class, 'GetWasteRecords']);
	$group->get('/waste/cases/{caseId}', [WasteApiController::class, 'GetCaseDetails']);
	$group->post('/waste/cases/{caseId}/investigation', [WasteApiController::class, 'UpdateCaseInvestigation']);
	$group->get('/waste/analytics', [WasteApiController::class, 'GetWasteAnalytics']);
	$group->post('/waste/report', [WasteApiController::class, 'ReportWaste']);
	$group->post('/waste/{wasteId}/approve', [WasteApiController::class, 'ApproveWaste']);
	$group->post('/waste/{wasteId}/reject', [WasteApiController::class, 'RejectWaste']);
	$group->post('/waste/{wasteId}/request-info', [WasteApiController::class, 'RequestWasteInfo']);
	$group->post('/waste/{wasteId}/evidence', [WasteApiController::class, 'AttachWasteEvidence']);
	$group->post('/waste/upload-photo', [WasteApiController::class, 'UploadWastePhoto']);
	$group->get('/waste/photo/{fileName}', [WasteApiController::class, 'ServeWastePhoto']);
	$group->post('/waste/action-plans', [WasteApiController::class, 'SaveActionPlan']);
	$group->post('/waste/action-plans/{planId}/update', [WasteApiController::class, 'UpdateActionPlanStatus']);
	$group->post('/stock/cycle-counts', [WasteApiController::class, 'ScheduleCycleCount']);
	$group->post('/stock/cycle-counts/auto-abc', [WasteApiController::class, 'AutoScheduleCycleCounts']);
	$group->post('/waste/supplier-returns', [WasteApiController::class, 'SaveSupplierReturn']);
	$group->post('/waste/supplier-returns/{returnId}/credit', [WasteApiController::class, 'RecordSupplierCredit']);
	$group->post('/stock/cycle-counts/{countId}/execute', [WasteApiController::class, 'ExecuteCycleCount']);
	$group->post('/stock/cycle-counts/{countId}/approve-adjustment', [WasteApiController::class, 'ApproveCycleCountAdjustment']);

	// Sales API
	$group->get('/sales', [SalesApiController::class, 'GetSales']);
	$group->get('/sales/department-performance', [SalesApiController::class, 'GetDepartmentPerformance']);
	$group->get('/sales/ty-ly', [SalesApiController::class, 'GetTyLyComparison']);
	$group->get('/sales/department-performance/category/{groupId}', [SalesApiController::class, 'GetDepartmentCategoryProducts']);
	$group->post('/sales/departments/config', [SalesApiController::class, 'SaveDepartmentConfig']);
	$group->post('/sales/history/corrections', [SalesApiController::class, 'SaveHistoryCorrection']);
	$group->post('/sales/history/closures', [SalesApiController::class, 'SaveHistoryClosure']);
	$group->get('/sales/{saleId}', [SalesApiController::class, 'GetSale']);
	$group->post('/sales', [SalesApiController::class, 'CreateSale']);
	$group->post('/sales/{saleId}/cancel', [SalesApiController::class, 'CancelSale']);
	$group->post('/sales/actions/execute', [SalesApiController::class, 'ExecuteCommercialAction']);
	$group->post('/pos/ingest', [\Grocy\Controllers\Api\PosIntegrationApiController::class, 'Ingest']);
	$group->post('/pos/import-csv', [\Grocy\Controllers\Api\PosIntegrationApiController::class, 'ImportCsv']);
	$group->get('/pos/status', [\Grocy\Controllers\Api\PosIntegrationApiController::class, 'Status']);
	$group->post('/pos/external/{id}/retry', [\Grocy\Controllers\Api\PosIntegrationApiController::class, 'Retry']);

	// Executive KPI & AI Advisor API
	$group->get('/kpi/executive', [KpiApiController::class, 'GetExecutiveDashboard']);
	$group->get('/today/sales-executive', [KpiApiController::class, 'GetTodayExecutiveSales']);
	$group->get('/budgets', [KpiApiController::class, 'GetBudgets']);

	// Reportes personalizables y programados (Etapa 34)
	$group->get('/reports/catalog', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'Catalog']);
	$group->post('/reports/preview', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'Preview']);
	$group->get('/reports/definitions', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'List']);
	$group->post('/reports/definitions', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'Save']);
	$group->post('/reports/definitions/{id}', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'Save']);
	$group->post('/reports/definitions/{id}/delete', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'Delete']);
	$group->post('/reports/definitions/{id}/run', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'Execute']);
	$group->get('/reports/runs/{id}/csv', [\Grocy\Controllers\Api\ReportBuilderApiController::class, 'DownloadRun']);
	// Informe Ejecutivo de Pérdidas y Novedades — Control 360
	$group->post('/reports/loss-executive/generate', [\Grocy\Controllers\Api\LossExecutiveReportApiController::class, 'Generate']);
	$group->get('/reports/loss-executive/history', [\Grocy\Controllers\Api\LossExecutiveReportApiController::class, 'History']);
	$group->get('/reports/loss-executive/{id}', [\Grocy\Controllers\Api\LossExecutiveReportApiController::class, 'Get']);
	$group->get('/reports/loss-executive/{id}/xlsx', [\Grocy\Controllers\Api\LossExecutiveReportApiController::class, 'ExportXlsx']);
	$group->post('/reports/loss-executive/schedule/{type}', [\Grocy\Controllers\Api\LossExecutiveReportApiController::class, 'SaveSchedule']);
	$group->get('/branches/user-access', [BranchApiController::class, 'GetUserAccess']);
	$group->post('/branches/user-access/{userId}', [BranchApiController::class, 'SetUserAccess']);
	$group->post('/budgets', [KpiApiController::class, 'SaveBudget']);

	// Suppliers & Procurement API
	$group->get('/suppliers', [SupplierProcurementApiController::class, 'GetSuppliers']);
	$group->get('/suppliers/contracts', [SupplierProcurementApiController::class, 'GetContracts']);
	$group->post('/suppliers/contracts', [SupplierProcurementApiController::class, 'SaveContract']);
	$group->get('/suppliers/{supplierId}', [SupplierProcurementApiController::class, 'GetSupplier']);
	$group->post('/suppliers', [SupplierProcurementApiController::class, 'SaveSupplier']);
	$group->post('/suppliers/{supplierId}/toggle-active', [SupplierProcurementApiController::class, 'ToggleActive']);
	$group->get('/procurement/comparison/{productId}', [SupplierProcurementApiController::class, 'GetCostComparison']);
	$group->post('/procurement/prices', [SupplierProcurementApiController::class, 'AddPrice']);
	$group->post('/procurement/deliveries', [SupplierProcurementApiController::class, 'RecordDelivery']);
	$group->post('/procurement/incidents', [SupplierProcurementApiController::class, 'SaveIncident']);
	$group->get('/procurement/recommendations', [SupplierProcurementApiController::class, 'GetRecommendations']);

	// Quarantine API (Waste / Stock)
	$group->post('/waste/quarantine', [WasteApiController::class, 'MoveToQuarantine']);
	$group->post('/stock/quarantine', [WasteApiController::class, 'MoveToQuarantine']);

	// MRP & Materials Planning API
	$group->get('/mrp/calculate', [MrpApiController::class, 'GetMrpCalculations']);
	$group->post('/mrp/simulate', [MrpApiController::class, 'SimulateDemand']);
	$group->get('/mrp/forecast/branches', [MrpApiController::class, 'GetBranchForecast']);
	$group->get('/mrp/transfers/recommendations', [MrpApiController::class, 'GetTransferRecommendations']);
	$group->post('/mrp/transfers/execute', [MrpApiController::class, 'ExecuteTransferRecommendation']);
	$group->get('/mrp/deliveries/pending', [MrpApiController::class, 'GetPendingDeliveries']);
	$group->get('/mrp/history', [MrpApiController::class, 'GetSupplyHistory']);
	$group->get('/mrp/orders', [MrpApiController::class, 'GetOrders']);
	$group->post('/mrp/order/create', [MrpApiController::class, 'CreateOrder']);
	$group->post('/mrp/orders/generate', [MrpApiController::class, 'GenerateOrders']);
	$group->post('/mrp/orders/bulk-approve', [MrpApiController::class, 'BulkApproveOrders']);
	$group->post('/mrp/quotations/request', [MrpApiController::class, 'RequestQuotation']);
	$group->post('/mrp/order/{orderId}/draft-items', [MrpApiController::class, 'UpdateDraftOrder']);
	$group->post('/mrp/order/{orderId}/approve', [MrpApiController::class, 'ApproveOrder']);
	$group->get('/mrp/order/{orderId}', [MrpApiController::class, 'GetOrder']);
	$group->post('/mrp/order/{orderId}/send', [MrpApiController::class, 'SendOrder']);
	$group->post('/mrp/order/{orderId}/confirm', [MrpApiController::class, 'ConfirmOrder']);
	$group->post('/mrp/order/{orderId}/close', [MrpApiController::class, 'CloseOrder']);
	$group->post('/mrp/order/{orderId}/receive', [MrpApiController::class, 'ReceiveOrder']);
	$group->post('/mrp/order/{orderId}/receive-admin', [MrpApiController::class, 'ReceiveOrderAdmin']);
	$group->post('/mrp/receipts/{receiptId}/confirm-physical', [MrpApiController::class, 'ConfirmPhysicalReceipt']);
	$group->post('/mrp/order/{orderId}/invoice', [MrpApiController::class, 'RegisterInvoice']);
	$group->get('/mrp/invoices/{invoiceId}/items', [MrpApiController::class, 'GetInvoiceItems']);
	$group->post('/mrp/invoices/{invoiceId}/decide', [MrpApiController::class, 'DecideInvoice']);
	$group->get('/mrp/receiving-rules', [MrpApiController::class, 'GetReceivingRules']);
	$group->post('/mrp/receiving-rules/{productId}', [MrpApiController::class, 'SaveReceivingRule']);
	$group->post('/procurement/incidents/{incidentId}/resolve', [MrpApiController::class, 'ResolveIncident']);
	$group->get('/stock/ledger/verify', [MovementControlApiController::class, 'VerifyLedger']);
	$group->get('/stock/anomalies', [MovementControlApiController::class, 'GetAnomalies']);
	$group->post('/stock/movement/{id}/link-waste', [MovementControlApiController::class, 'LinkWasteCase']);
	$group->post('/stock/movement/{id}/justify', [MovementControlApiController::class, 'JustifyMovement']);
	$group->get('/transfers/{transferId}', [StockTransferApiController::class, 'GetTransfer']);
	$group->post('/transfers', [StockTransferApiController::class, 'CreateTransfer']);
	$group->post('/transfers/{transferId}/receive', [StockTransferApiController::class, 'ReceiveTransfer']);
	$group->post('/transfers/{transferId}/cancel', [StockTransferApiController::class, 'CancelTransfer']);

	// Workforce Store Tasks API
	$group->get('/workforce/tasks', [WorkforceApiController::class, 'GetTasks']);
	$group->post('/workforce/tasks/{taskId}/complete', [WorkforceApiController::class, 'CompleteTask']);
	$group->post('/workforce/tasks/{taskId}/status', [WorkforceApiController::class, 'UpdateTaskStatus']);
	$group->post('/workforce/tasks/convert-agreement', [WorkforceApiController::class, 'CreateAgreementTask']);
	$group->post('/workforce/quick-waste', [WorkforceApiController::class, 'QuickWasteReport']);
	$group->get('/schedule/week', [ScheduleApiController::class, 'GetWeek']);
	$group->get('/schedule/my', [ScheduleApiController::class, 'GetMySchedule']);
	$group->post('/schedule/employees', [ScheduleApiController::class, 'SaveEmployee']);
	$group->post('/schedule/employees/{id}/availability', [ScheduleApiController::class, 'SetAvailability']);
	$group->post('/schedule/shifts/check', [ScheduleApiController::class, 'CheckShift']);
	$group->post('/schedule/shifts', [ScheduleApiController::class, 'SaveShift']);
	$group->post('/schedule/shifts/{id}/move', [ScheduleApiController::class, 'MoveShift']);
	$group->post('/schedule/shifts/{id}/cancel', [ScheduleApiController::class, 'CancelShift']);
	$group->post('/schedule/week/publish', [ScheduleApiController::class, 'PublishWeek']);
	$group->post('/schedule/week/copy', [ScheduleApiController::class, 'CopyWeek']);
	$group->post('/schedule/coverage', [ScheduleApiController::class, 'SaveCoverage']);
	$group->get('/schedule/coverage/suggest', [ScheduleApiController::class, 'SuggestCoverage']);
	$group->post('/schedule/certifications', [ScheduleApiController::class, 'SaveCertification']);

	// DAILY — Recursos Humanos (HCM) API
	$group->post('/hr/candidate/{id}/stage', [HrApiController::class, 'UpdateCandidateStage']);
	$group->post('/hr/onboarding/{id}/toggle', [HrApiController::class, 'ToggleOnboardingTask']);
	$group->post('/hr/request/{id}/decide', [HrApiController::class, 'DecideRequest']);
	$group->post('/hr/communication', [HrApiController::class, 'PostCommunication']);

	// DAILY — Almacén y Bodega (WMS) API
	$group->post('/warehouse/putaway/{id}/confirm', [WarehouseApiController::class, 'ConfirmPutaway']);
	$group->post('/warehouse/inspection', [WarehouseApiController::class, 'RecordInspection']);
	$group->post('/warehouse/dock/{id}/status', [WarehouseApiController::class, 'UpdateDockStatus']);
	$group->post('/schedule/certifications/{id}/delete', [ScheduleApiController::class, 'DeleteCertification']);
	$group->post('/schedule/role-requirements', [ScheduleApiController::class, 'SaveRoleRequirements']);
	$group->post('/schedule/shifts/bulk', [ScheduleApiController::class, 'BulkShifts']);
	$group->post('/schedule/shifts/{id}/duplicate', [ScheduleApiController::class, 'DuplicateShift']);
	$group->post('/schedule/shifts/{id}/split', [ScheduleApiController::class, 'SplitShift']);
	$group->get('/schedule/shifts/{id}/history', [ScheduleApiController::class, 'ShiftHistory']);
	$group->get('/schedule/changes', [ScheduleApiController::class, 'ChangeLog']);
	$group->post('/schedule/undo', [ScheduleApiController::class, 'Undo']);
	$group->get('/schedule/templates', [ScheduleApiController::class, 'GetTemplates']);
	$group->post('/schedule/templates', [ScheduleApiController::class, 'SaveTemplate']);
	$group->post('/schedule/templates/{id}/delete', [ScheduleApiController::class, 'DeleteTemplate']);
	$group->post('/schedule/templates/{id}/apply', [ScheduleApiController::class, 'ApplyTemplate']);
	$group->get('/schedule/timesheet', [ScheduleApiController::class, 'GetTimesheet']);
	$group->get('/schedule/attendance', [ScheduleApiController::class, 'GetAttendance']);
	$group->get('/schedule/adjustments',[ScheduleApiController::class, 'GetAdjustments']);
	$group->post('/schedule/adjustments/{id}/decide', [ScheduleApiController::class, 'DecideAdjustment']);
	$group->get('/schedule/forecast', [ScheduleApiController::class, 'Forecast']);
	$group->get('/schedule/forecast/band', [ScheduleApiController::class, 'ForecastBand']);
	$group->post('/schedule/forecast/recommend', [ScheduleApiController::class, 'ForecastRecommend']);
	$group->post('/schedule/forecast/apply', [ScheduleApiController::class, 'ForecastApply']);
	$group->get('/schedule/forecast/accuracy', [ScheduleApiController::class, 'ForecastAccuracy']);
	$group->get('/schedule/forecast/events', [ScheduleApiController::class, 'ForecastEvents']);
	$group->post('/schedule/forecast/events', [ScheduleApiController::class, 'SaveForecastEvent']);
	$group->post('/schedule/forecast/events/{id}/delete', [ScheduleApiController::class, 'DeleteForecastEvent']);
	$group->post('/schedule/forecast/standards', [ScheduleApiController::class, 'SaveForecastStandards']);
	$group->post('/schedule/scenarios/simulate', [ScheduleApiController::class, 'SimulateScenario']);
	$group->get('/schedule/scenarios', [ScheduleApiController::class, 'Scenarios']);
	$group->post('/schedule/scenarios', [ScheduleApiController::class, 'SaveScenario']);
	$group->post('/schedule/scenarios/{id}/delete', [ScheduleApiController::class, 'DeleteScenario']);
	$group->post('/schedule/coverage-requests', [ScheduleApiController::class, 'CreateCoverageRequest']);
	$group->post('/schedule/coverage-requests/{id}/close', [ScheduleApiController::class, 'CloseCoverageRequest']);

	// DAIRY: Motor Inteligente de Horarios y Cobertura según Ventas (Módulo 35)
	$group->get('/schedule/dynamic-plan', [ScheduleApiController::class, 'DynamicPlan']);
	$group->post('/schedule/dynamic-plan/optimize', [ScheduleApiController::class, 'DynamicPlanOptimize']);
	$group->post('/schedule/recommendations/{id}/decide', [ScheduleApiController::class, 'DecideRecommendation']);
	$group->post('/schedule/daily-automation', [ScheduleApiController::class, 'RunDailyAutomation']);
	$group->get('/schedule/department-configs', [ScheduleApiController::class, 'GetDepartmentConfigs']);
	$group->post('/schedule/department-configs', [ScheduleApiController::class, 'SaveDepartmentConfigs']);
	$group->post('/schedule/simulate-scenario', [ScheduleApiController::class, 'SimulateSalesScenario']);

	// DAILY: Motor Inteligente de Horarios, Equivalencia de Turnos y Programación 2026-2030+
	$group->get('/schedule/equity', [ScheduleApiController::class, 'GetEquity']);
	$group->post('/schedule/equity/generate', [ScheduleApiController::class, 'GenerateEquitySchedule']);
	$group->post('/schedule/equity/publish', [ScheduleApiController::class, 'PublishEquitySchedule']);
	$group->post('/schedule/equity/swap-preview', [ScheduleApiController::class, 'PreviewShiftSwap']);
	$group->post('/schedule/equity/swap-apply', [ScheduleApiController::class, 'ApplyShiftSwap']);
	$group->post('/schedule/equity/update-shift', [ScheduleApiController::class, 'UpdateShift']);
	$group->get('/schedule/monthly', [ScheduleApiController::class, 'GetMonthlySchedule']);
	$group->post('/schedule/monthly/generate', [ScheduleApiController::class, 'GenerateMonthlySchedule']);
	$group->post('/schedule/monthly/publish', [ScheduleApiController::class, 'PublishMonthlySchedule']);
	$group->post('/schedule/monthly/update-shift', [ScheduleApiController::class, 'UpdateMonthlyShift']);
	$group->post('/schedule/monthly/shift', [ScheduleApiController::class, 'UpdateMonthlyShift']);
	$group->get('/schedule/monthly/export', [ScheduleApiController::class, 'ExportMonthlySchedule']);
	$group->get('/schedule/engine/dashboard', [ScheduleApiController::class, 'EngineDashboard']);
	$group->get('/schedule/engine/validate', [ScheduleApiController::class, 'EngineValidate']);
	$group->post('/schedule/engine/build', [ScheduleApiController::class, 'EngineBuild']);
	$group->post('/schedule/engine/reopen', [ScheduleApiController::class, 'EngineReopen']);
	$group->get('/schedule/engine/drafts/{id}', [ScheduleApiController::class, 'EngineDraft']);
	$group->post('/schedule/engine/drafts/{id}/balance', [ScheduleApiController::class, 'EngineBalance']);
	$group->get('/schedule/engine/drafts/{id}/replacement', [ScheduleApiController::class, 'EngineReplacement']);
	$group->post('/schedule/engine/drafts/{id}/replacement', [ScheduleApiController::class, 'EngineApplyReplacement']);
	$group->post('/schedule/engine/drafts/{id}/approve', [ScheduleApiController::class, 'EngineApprove']);
	$group->post('/schedule/engine/drafts/{id}/discard', [ScheduleApiController::class, 'EngineDiscard']);
	$group->post('/schedule/engine/drafts/{id}/recalculate', [ScheduleApiController::class, 'EngineRecalculate']);
	$group->post('/schedule/engine/drafts/{id}/cell', [ScheduleApiController::class, 'EngineUpdateCell']);
	$group->post('/schedule/engine/absences/{id}/end', [ScheduleApiController::class, 'EngineAbsenceEnd']);
	$group->post('/schedule/engine/absences/{id}/decide', [ScheduleApiController::class, 'EngineDecideAbsence']);
	$group->get('/schedule/engine/export', [ScheduleApiController::class, 'EngineExport']);
	$group->get('/schedule/engine/control', [ScheduleApiController::class, 'EngineControl']);
	$group->post('/schedule/engine/restrictions', [ScheduleApiController::class, 'EngineSaveRestriction']);
	$group->post('/schedule/engine/restrictions/{id}/delete', [ScheduleApiController::class, 'EngineDeleteRestriction']);
	$group->post('/schedule/engine/collaborators/{code}/config', [ScheduleApiController::class, 'EngineSaveConfig']);
	$group->post('/schedule/engine/requests', [ScheduleApiController::class, 'EngineCreateRequest']);
	$group->post('/schedule/engine/requests/{id}/decide', [ScheduleApiController::class, 'EngineDecideRequest']);
	$group->post('/schedule/engine/requests/{id}/apply', [ScheduleApiController::class, 'EngineApplyRequest']);
	$group->get('/schedule/skills-matrix', [ScheduleApiController::class, 'GetSkillsMatrix']);
	$group->post('/schedule/skills-matrix', [ScheduleApiController::class, 'UpdateSkillsMatrix']);
	$group->get('/schedule/vacations', [ScheduleApiController::class, 'GetVacations']);
	$group->post('/schedule/vacations', [ScheduleApiController::class, 'SaveVacation']);
	$group->get('/schedule/holidays', [ScheduleApiController::class, 'GetHolidays']);
	$group->get('/schedule/policies', [ScheduleApiController::class, 'GetCompanyPolicies']);
	$group->post('/schedule/policies', [ScheduleApiController::class, 'SaveCompanyPolicy']);
	$group->get('/schedule/jurisdictions', [ScheduleApiController::class, 'GetJurisdictions']);
	$group->get('/schedule/coverage/hourly', [ScheduleApiController::class, 'GetDepartmentHourlyCoverage']);
	$group->get('/schedule/compensatory', [ScheduleApiController::class, 'GetCompensatoryLedger']);
	$group->post('/schedule/compensatory', [ScheduleApiController::class, 'AccrueOrRedeemCompensatory']);
	$group->get('/schedule/vacations/balances', [ScheduleApiController::class, 'GetVacationBalances']);
	$group->post('/schedule/collaborators', [ScheduleApiController::class, 'RegisterCollaborator']);

	// DEMO · Tienda Integral (Escenario aislado de 25 personas y 7 áreas)
	$group->post('/schedule/demo/restore', [ScheduleApiController::class, 'RestoreDemoScenario']);
	$group->get('/schedule/demo/coverage-matrix', [ScheduleApiController::class, 'GetDemoCoverageMatrix']);
	$group->get('/schedule/demo/validation-scenarios', [ScheduleApiController::class, 'GetDemoValidationScenarios']);
	$group->post('/schedule/demo/test-case', [ScheduleApiController::class, 'ApplyDemoValidationTestCase']);

	// DAILY: Motor de Prevención de Mermas, Vencimientos, Despachos, Devoluciones y Bakery
	$group->get('/retail/expiry/alerts', [RetailPreventionApiController::class, 'GetExpiryAlerts']);
	$group->post('/retail/expiry/alerts/{id}/resolve', [RetailPreventionApiController::class, 'ResolveExpiryAlert']);
	$group->get('/retail/expiry/rules', [RetailPreventionApiController::class, 'GetExpiryRules']);
	$group->post('/retail/expiry/rules', [RetailPreventionApiController::class, 'SaveExpiryRule']);
	$group->get('/retail/dispatch/orders/{id}', [RetailPreventionApiController::class, 'GetDispatchOrderDetail']);
	$group->get('/retail/dispatch/orders/{id}/label', [RetailPreventionApiController::class, 'GetDispatchLabel']);
	$group->post('/retail/dispatch/create-order', [RetailPreventionApiController::class, 'CreateDispatchOrder']);
	$group->post('/retail/dispatch/reserve-order', [RetailPreventionApiController::class, 'ReserveOrder']);
	$group->post('/retail/dispatch/pack-order', [RetailPreventionApiController::class, 'PackOrder']);
	$group->post('/retail/dispatch/verify-pickup', [RetailPreventionApiController::class, 'VerifyPickup']);
	$group->post('/retail/dispatch/record-delivery', [RetailPreventionApiController::class, 'RecordDelivery']);
	$group->post('/retail/dispatch/scan-upc', [RetailPreventionApiController::class, 'FastScanUPC']);
	$group->post('/retail/dispatch/resolve-variance', [RetailPreventionApiController::class, 'ResolveVariance']);
	$group->post('/retail/dispatch/assign-picking', [RetailPreventionApiController::class, 'AssignPicking']);
	$group->post('/retail/dispatch/schedule-transport', [RetailPreventionApiController::class, 'ScheduleTransport']);
	$group->post('/retail/dispatch/confirm-exit', [RetailPreventionApiController::class, 'ConfirmExit']);
	$group->post('/retail/dispatch/report-incident', [RetailPreventionApiController::class, 'ReportIncident']);
	$group->post('/retail/dispatch/resolve-incident', [RetailPreventionApiController::class, 'ResolveIncident']);
	$group->post('/retail/dispatch/conciliate', [RetailPreventionApiController::class, 'ConciliateOrder']);
	$group->post('/retail/dispatch/cancel-order', [RetailPreventionApiController::class, 'CancelOrder']);
	$group->get('/retail/dispatch/analytics', [RetailPreventionApiController::class, 'GetDispatchAnalytics']);
	$group->get('/retail/returns', [RetailPreventionApiController::class, 'GetReturns']);
	$group->get('/retail/returns/scan-upc', [RetailPreventionApiController::class, 'ScanReturnUpc']);
	$group->get('/retail/returns/{id:[0-9]+}', [RetailPreventionApiController::class, 'GetReturnDetail']);
	$group->post('/retail/returns', [RetailPreventionApiController::class, 'CreateReturn']);
	$group->post('/retail/returns/{id:[0-9]+}/receive', [RetailPreventionApiController::class, 'ReceiveReturn']);
	$group->post('/retail/returns/{id:[0-9]+}/inspect', [RetailPreventionApiController::class, 'InspectReturn']);
	$group->post('/retail/returns/{id:[0-9]+}/classify', [RetailPreventionApiController::class, 'ClassifyReturn']);
	$group->post('/retail/returns/{id:[0-9]+}/disposition', [RetailPreventionApiController::class, 'AuthorizeReturnDisposition']);
	$group->post('/retail/returns/{id:[0-9]+}/conciliate', [RetailPreventionApiController::class, 'ConciliateReturn']);
	$group->post('/retail/returns/{id:[0-9]+}/evidence', [RetailPreventionApiController::class, 'AddReturnEvidence']);
	$group->post('/retail/returns/{id}/advance', [RetailPreventionApiController::class, 'AdvanceReturn']);
	$group->get('/retail/bakery/dashboard', [RetailPreventionApiController::class, 'GetBakeryDashboard']);

	// DAILY — Motor Inteligente de Horas Peak y Dotación de Personal
	$group->get('/workforce/peaks/dashboard', [WorkforcePeakApiController::class, 'Dashboard']);
	$group->post('/workforce/peaks/simulate', [WorkforcePeakApiController::class, 'Simulate']);
	$group->post('/workforce/peaks/recommendations/{id}/apply', [WorkforcePeakApiController::class, 'ApplyRecommendation']);

	// Etapa 19: control de inventario, pérdida total, retiros, auditorías y alertas
	$group->get('/inventory-control/phantom', [LossPreventionApiController::class, 'Phantom']);
	$group->get('/scan/resolve', [OperationsApiController::class, 'Resolve']);
	$group->get('/scan/form-data', [OperationsApiController::class, 'FormData']);
	$group->get('/scan/sessions', [OperationsApiController::class, 'Sessions']);
	$group->post('/scan/sessions', [OperationsApiController::class, 'OpenSession']);
	$group->get('/scan/sessions/{id}', [OperationsApiController::class, 'GetSession']);
	$group->post('/scan/sessions/{id}/lines', [OperationsApiController::class, 'AddLine']);
	$group->post('/scan/lines/{lineId}/void', [OperationsApiController::class, 'VoidLine']);
	$group->post('/scan/sessions/{id}/close', [OperationsApiController::class, 'CloseSession']);
	$group->post('/scan/sessions/{id}/cancel', [OperationsApiController::class, 'CancelSession']);
	$group->get('/inventory-control/stagnant', [OperationsApiController::class, 'StagnantLots']);
	$group->post('/data-import/{entity}', [OperationsApiController::class, 'ImportRun']);
	$group->get('/data-import/jobs', [OperationsApiController::class, 'ImportJobs']);
	$group->get('/data-import/{entity}/template', [OperationsApiController::class, 'Template']);
	$group->get('/change-history/{table}/{id}', [OperationsApiController::class, 'History']);
	$group->get('/waste/export', [OperationsApiController::class, 'WasteExport']);
	$group->get('/orders', [OperationsApiController::class, 'Orders']);
	$group->get('/traceability', [OperationsApiController::class, 'Trace']);
	$group->post('/orders', [OperationsApiController::class, 'CreateOrder']);
	$group->get('/orders/{id}', [OperationsApiController::class, 'GetOrder']);
	$group->post('/orders/{id}/{action}', [OperationsApiController::class, 'OrderAction']);
	$group->get('/inventory-control/abc-xyz', [LossPreventionApiController::class, 'AbcXyz']);
	$group->post('/inventory-control/counts', [LossPreventionApiController::class, 'ScheduleCounts']);
	$group->post('/inventory-control/counts/schedule', [LossPreventionApiController::class, 'ScheduleBlindCount']);
	$group->post('/inventory-control/phantom/dismiss', [LossPreventionApiController::class, 'DismissPhantom']);
	$group->post('/inventory-control/gondola-review', [LossPreventionApiController::class, 'RequestGondolaReview']);
	$group->post('/inventory-control/reclassify', [LossPreventionApiController::class, 'ReclassifyAbcXyz']);

	// Inventario y Almacenes (InventoryOperationsService): estáticas antes que las de {id}
	$group->get('/inventory-ops/summary', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Summary']);
	$group->get('/inventory-ops/positions', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Positions']);
	$group->get('/inventory-ops/locations', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Locations']);
	$group->get('/inventory-ops/kardex', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Kardex']);
	$group->get('/inventory-ops/anomalies', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Anomalies']);
	$group->get('/inventory-ops/transfers', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Transfers']);
	$group->get('/inventory-ops/aging', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Aging']);
	$group->get('/inventory-ops/coverage', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Coverage']);
	$group->get('/inventory-ops/lots', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Lots']);
	$group->get('/inventory-ops/replenishment', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Replenishment']);
	$group->post('/inventory-ops/replenishment', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'CreateReplenishment']);
	$group->post('/inventory-ops/replenishment/{taskId:[0-9]+}/confirm', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'ConfirmReplenishment']);
	$group->post('/inventory-ops/replenishment/{taskId:[0-9]+}/cancel', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'CancelReplenishment']);
	$group->get('/inventory-ops/reservations', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Reservations']);
	$group->get('/inventory-ops/audit', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Audit']);
	$group->get('/inventory-ops/scan/{code:.+}', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Scan']);
	$group->get('/inventory-ops/adjustments', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'Adjustments']);
	$group->post('/inventory-ops/adjustments', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'RequestAdjustment']);
	$group->post('/inventory-ops/adjustments/{adjustmentId:[0-9]+}/approve', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'ApproveAdjustment']);
	$group->post('/inventory-ops/adjustments/{adjustmentId:[0-9]+}/reject', [\Grocy\Controllers\Api\InventoryOpsApiController::class, 'RejectAdjustment']);
	$group->post('/inventory/counts', [PhysicalCountApiController::class, 'CreateCount']);
	$group->post('/inventory/counts/{id}/start', [PhysicalCountApiController::class, 'StartCount']);
	$group->post('/inventory/counts/{id}/scan', [PhysicalCountApiController::class, 'ScanItem']);
	$group->post('/inventory/counts/{id}/reconcile', [PhysicalCountApiController::class, 'ReconcileCount']);
	$group->get('/loss/total', [LossPreventionApiController::class, 'TotalLoss']);
	$group->get('/recalls/preview', [LossPreventionApiController::class, 'RecallPreview']);
	$group->get('/recalls/{id}', [LossPreventionApiController::class, 'GetRecall']);
	$group->post('/recalls', [LossPreventionApiController::class, 'CreateRecall']);
	$group->post('/recalls/items/{itemId}/confirm', [LossPreventionApiController::class, 'ConfirmRecallItem']);
	$group->post('/recalls/{id}/close', [LossPreventionApiController::class, 'CloseRecall']);
	$group->post('/recalls/{id}/cancel', [LossPreventionApiController::class, 'CancelRecall']);
	$group->get('/audits/{id}', [LossPreventionApiController::class, 'GetAudit']);
	$group->post('/audits/start', [LossPreventionApiController::class, 'StartAudit']);
	$group->post('/audits/templates', [LossPreventionApiController::class, 'SaveAuditTemplate']);
	$group->post('/audits/{id}/answers', [LossPreventionApiController::class, 'SaveAuditAnswers']);
	$group->get('/alerts', [LossPreventionApiController::class, 'GetAlerts']);
	$group->post('/alerts/scan', [LossPreventionApiController::class, 'ScanAlerts']);
	$group->post('/alerts/rules/{code}', [LossPreventionApiController::class, 'SaveAlertRule']);
	$group->get('/alerts/{id}/events', [LossPreventionApiController::class, 'GetAlertEvents']);
	// Fase 1 — arquitectura central (rutas estáticas antes que {id})
	$group->get('/workflows/definitions', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'WorkflowDefinitions']);
	$group->post('/workflows/definitions', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'SaveWorkflowDefinition']);
	$group->get('/workflows/stats', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'WorkflowStats']);
	$group->get('/workflows', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'Workflows']);
	$group->post('/workflows', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'StartWorkflow']);
	$group->get('/workflows/{id:[0-9]+}', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'Workflow']);
	$group->post('/workflows/{id:[0-9]+}/{action:[a-z]+}', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'WorkflowAction']);
	$group->get('/kpi-catalog', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'KpiCatalog']);
	$group->get('/process-flow/types', [\Grocy\Controllers\Api\ProcessFlowApiController::class, 'Types']);
	$group->get('/process-flow/exceptions', [\Grocy\Controllers\Api\ProcessFlowApiController::class, 'Exceptions']);
	$group->get('/process-flow/inbox',[\Grocy\Controllers\Api\ProcessFlowApiController::class, 'Inbox']);
	$group->get('/process-flow/{type:[a-z_]+}/{id:[0-9]+}', [\Grocy\Controllers\Api\ProcessFlowApiController::class, 'Get']);
	$group->get('/demo/case', [\Grocy\Controllers\DemoCaseController::class, 'Summary']);
	$group->post('/demo/case/{action:[a-z_]+}', [\Grocy\Controllers\DemoCaseController::class, 'Action']);
	$group->get('/kpi-catalog/compute', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'KpiCompute']);
	$group->post('/kpi-catalog/{code:[a-z_]+}/settings', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'SaveKpiSettings']);
	$group->get('/inventory-movements/types', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'MovementTypes']);
	$group->get('/inventory-movements/duplicates', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'MovementDuplicates']);
	$group->get('/inventory-movements', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'MovementLedger']);
	$group->get('/inventory-movements/position/{productId:[0-9]+}', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'MovementPosition']);
	$group->get('/master-data/registry', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'MasterRegistry']);
	$group->get('/master-data/integrity', [\Grocy\Controllers\Api\CoreArchitectureApiController::class, 'MasterIntegrity']);
	// Centro de Alertas v2 (registro único ALR-<id>; web y DAIRY Mobile)
	$group->get('/alerts/dashboard', [AlertCenterApiController::class, 'Dashboard']);
	$group->get('/alerts/list', [AlertCenterApiController::class, 'List']);
	$group->get('/alerts/report', [AlertCenterApiController::class, 'Report']);
	$group->get('/alerts/series', [AlertCenterApiController::class, 'Series']);
	$group->post('/alerts/prevention/{pid:[0-9]+}/verify', [AlertCenterApiController::class, 'VerifyPrevention']);
	$group->get('/alerts/export', [AlertCenterApiController::class, 'Export']);
	$group->post('/alerts', [AlertCenterApiController::class, 'Create']);
	$group->post('/alerts/bulk', [AlertCenterApiController::class, 'Bulk']);
	$group->post('/alerts/escalations/run', [AlertCenterApiController::class, 'Escalations']);
	$group->get('/alerts/automation', [AlertCenterApiController::class, 'AutomationConfig']);
	$group->post('/alerts/automation', [AlertCenterApiController::class, 'AutomationSave']);
	$group->post('/alerts/automation/{ruleId:[0-9]+}', [AlertCenterApiController::class, 'AutomationSave']);
	$group->get('/alerts/automation/{ruleId:[0-9]+}/executions', [AlertCenterApiController::class, 'AutomationExecutions']);
	$group->post('/alerts/automation/executions/{execId:[0-9]+}/retry', [AlertCenterApiController::class, 'AutomationRetry']);
	$group->get('/alerts/notifications', [AlertCenterApiController::class, 'Notifications']);
	$group->post('/alerts/notifications/read', [AlertCenterApiController::class, 'NotificationsRead']);
	$group->get('/alerts/views', [AlertCenterApiController::class, 'Views']);
	$group->post('/alerts/views', [AlertCenterApiController::class, 'SaveView']);
	$group->delete('/alerts/views/{viewId:[0-9]+}', [AlertCenterApiController::class, 'DeleteView']);
	$group->get('/alerts/evidence/{evidenceId:[0-9]+}', [AlertCenterApiController::class, 'ServeEvidence']);
	$group->post('/alerts/actions/{actionId:[0-9]+}/complete', [AlertCenterApiController::class, 'CompleteAction']);
	$group->get('/alerts/{id:[0-9]+}', [AlertCenterApiController::class, 'Detail']);
	$group->post('/alerts/{id:[0-9]+}/evidence', [AlertCenterApiController::class, 'UploadEvidence']);
	$group->post('/alerts/{id:[0-9]+}/{action}', [AlertCenterApiController::class, 'Action']);
	// DAIRY Etapa 35: Centro de Control de pérdidas (misma lógica para web y /m)
	$group->get('/loss/summary', [LossIntelligenceApiController::class, 'Summary']);
	$group->get('/loss/year-comparison', [LossIntelligenceApiController::class, 'YearComparison']);
	$group->get('/loss/causes', [LossIntelligenceApiController::class, 'Causes']);
	$group->get('/loss/classification', [LossIntelligenceApiController::class, 'Classification']);
	$group->get('/loss/theft', [LossIntelligenceApiController::class, 'Theft']);
	$group->get('/loss/formats', [LossIntelligenceApiController::class, 'Formats']);
	$group->get('/loss/financial', [LossIntelligenceApiController::class, 'Financial']);
	$group->get('/loss/ranking', [LossIntelligenceApiController::class, 'Ranking']);
	$group->post('/loss/ranking/schedule', [LossIntelligenceApiController::class, 'ScheduleCounts']);
	$group->get('/loss/cold-chain', [LossIntelligenceApiController::class, 'ColdChain']);
	$group->get('/loss/perishables', [LossIntelligenceApiController::class, 'Perishables']);
	$group->get('/loss/price-issues', [LossIntelligenceApiController::class, 'PriceIssues']);
	$group->post('/loss/price-issues', [LossIntelligenceApiController::class, 'CreatePriceIssue']);
	$group->post('/loss/price-issues/detect', [LossIntelligenceApiController::class, 'DetectPriceIssues']);
	$group->post('/loss/price-issues/{id}/advance', [LossIntelligenceApiController::class, 'AdvancePriceIssue']);
	$group->post('/loss/yield-lots', [LossIntelligenceApiController::class, 'SaveYieldLot']);
	$group->post('/loss/yield-lots/{id}/close', [LossIntelligenceApiController::class, 'CloseYieldLot']);
	$group->post('/loss/settings', [LossIntelligenceApiController::class, 'SaveSettings']);
	$group->post('/loss/reasons/{id}/cause', [LossIntelligenceApiController::class, 'SetReasonCause']);
	$group->post('/loss/branches/{id}/format', [LossIntelligenceApiController::class, 'SaveBranchFormat']);
	$group->get('/loss/control-center-executive', [LossIntelligenceApiController::class, 'ControlCenterExecutive']);
	// Etapa 36: metodología del Censo Nacional de Mermas
	$group->get('/loss/census/hierarchy', [LossCensusApiController::class, 'Hierarchy']);
	$group->get('/loss/census/departments', [LossCensusApiController::class, 'Departments']);
	$group->get('/loss/census/benchmarks', [LossCensusApiController::class, 'Benchmarks']);
	$group->get('/loss/census/targets', [LossCensusApiController::class, 'Targets']);
	$group->post('/loss/census/targets', [LossCensusApiController::class, 'SaveTarget']);
	$group->get('/loss/census/admin-errors', [LossCensusApiController::class, 'AdminErrors']);
	$group->post('/loss/census/admin-errors', [LossCensusApiController::class, 'CreateAdminError']);
	$group->post('/loss/census/admin-errors/detect', [LossCensusApiController::class, 'DetectAdminErrors']);
	$group->get('/loss/census/admin-errors/{id}/history', [LossCensusApiController::class, 'AdminErrorHistory']);
	$group->post('/loss/census/admin-errors/{id}/advance', [LossCensusApiController::class, 'AdvanceAdminError']);
	$group->get('/loss/census/theft', [LossCensusApiController::class, 'Theft']);
	$group->post('/loss/census/waste/{id}/details', [LossCensusApiController::class, 'WasteDetails']);
	$group->get('/loss/census/perishables', [LossCensusApiController::class, 'Perishables']);
	$group->get('/loss/census/economic', [LossCensusApiController::class, 'Economic']);
	$group->get('/loss/census/controls', [LossCensusApiController::class, 'Controls']);
	$group->post('/loss/census/controls', [LossCensusApiController::class, 'SaveControl']);
	$group->post('/loss/census/social', [LossCensusApiController::class, 'SaveSocial']);
	// DAIRY Mobile (manager): lecturas agregadas; las acciones usan las APIs de cada módulo
	$group->get('/m/me', [MobileApiController::class, 'Me']);
	$group->get('/m/home', [MobileApiController::class, 'Home']);
	$group->get('/m/sales', [MobileApiController::class, 'Sales']);
	$group->get('/m/sales/{id}', [MobileApiController::class, 'Sale']);
	$group->get('/m/waste', [MobileApiController::class, 'Waste']);
	$group->get('/m/inventory', [MobileApiController::class, 'Inventory']);
	$group->get('/m/sku/{id}', [MobileApiController::class, 'Sku']);
	$group->get('/m/inbox', [MobileApiController::class, 'Inbox']);
	$group->get('/m/case/{type}/{id}', [MobileApiController::class, 'CaseDetail']);
	$group->get('/m/search', [MobileApiController::class, 'Search']);
	$group->get('/m/lookups', [MobileApiController::class, 'Lookups']);
	// Etapa 24: DAIRY Mobile por rol
	$group->get('/m/scan', [MobileWorkApiController::class, 'Scan']);
	$group->post('/m/ops/receive', [MobileWorkApiController::class, 'OpReceive']);
	$group->post('/m/ops/count', [MobileWorkApiController::class, 'OpCount']);
	$group->get('/m/ops/waste-reasons', [MobileWorkApiController::class, 'OpWasteReasons']);
	$group->post('/m/ops/waste', [MobileWorkApiController::class, 'OpWaste']);
	$group->get('/m/approvals', [MobileWorkApiController::class, 'Approvals']);
	$group->post('/m/approvals/{kind}/{id}', [MobileWorkApiController::class, 'DecideApproval']);
	$group->get('/m/today', [MobileWorkApiController::class, 'Today']);
	$group->get('/m/tasks', [MobileWorkApiController::class, 'Tasks']);
	$group->post('/m/tasks', [MobileWorkApiController::class, 'CreateTask']);
	$group->get('/m/tasks/{id}', [MobileWorkApiController::class, 'Task']);
	$group->post('/m/tasks/{id}/complete', [MobileWorkApiController::class, 'CompleteTask']);
	$group->post('/m/alerts/{id}/task', [MobileWorkApiController::class, 'TaskFromAlert']);
	$group->get('/m/team', [MobileWorkApiController::class, 'Team']);
	$group->get('/m/schedule', [MobileWorkApiController::class, 'Schedule']);
	$group->get('/m/notifications', [MobileWorkApiController::class, 'Notifications']);
	$group->post('/m/notifications/seen', [MobileWorkApiController::class, 'NotificationsSeen']);
	// Autoservicio y comunicación (web móvil y app nativa)
	$group->get('/m/messages', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Threads']);
	$group->get('/m/messages/contacts', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Contacts']);
	$group->post('/m/messages/direct', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'OpenDirect']);
	$group->post('/m/messages/group', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'CreateGroup']);
	$group->post('/m/messages/announcement', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Announce']);
	$group->get('/m/messages/{id}', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Messages']);
	$group->post('/m/messages/{id}', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Send']);
	$group->post('/m/push/register', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'RegisterPush']);
	$group->post('/m/push/unregister', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'UnregisterPush']);
	$group->get('/m/push/devices', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'PushDevices']);
	$group->get('/m/open-shifts', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'OpenShifts']);
	$group->post('/m/open-shifts', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'CreateOpenShift']);
	$group->post('/m/open-shifts/{id}/claim', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Claim']);
	$group->post('/m/open-shift-claims/{id}/withdraw', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'Withdraw']);
	$group->post('/m/open-shift-claims/{id}/decide', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'DecideClaim']);
	$group->post('/m/shifts/{id}/confirm', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'ConfirmShift']);
	$group->get('/m/geofence', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'MyGeofence']);
	$group->post('/branches/geofence', [\Grocy\Controllers\Api\WorkforceSelfServiceApiController::class, 'SaveGeofence']);
	$group->post('/m/prefs', [MobileWorkApiController::class, 'SavePrefs']);
	// Asistencia API (Módulo Control de Entradas, Salidas y Novedades)
	$group->get('/attendance/overview', [AttendanceApiController::class, 'GetOverview']);
	$group->post('/attendance/record', [AttendanceApiController::class, 'RecordAttendance']);
	$group->post('/attendance/adjustment', [AttendanceApiController::class, 'RequestAdjustment']);
	$group->post('/attendance/adjustments/{id}/decide', [AttendanceApiController::class, 'DecideAdjustment']);
	$group->post('/attendance/quick-checkout-all', [AttendanceApiController::class, 'QuickCheckoutAll']);
	$group->post('/attendance/demo/reset', [AttendanceApiController::class, 'ResetDemo']);
	$group->post('/attendance/clock-in', [AttendanceApiController::class, 'ClockIn']);
	$group->post('/attendance/clock-out', [AttendanceApiController::class, 'ClockOut']);
	$group->post('/attendance/break-start', [AttendanceApiController::class, 'StartBreak']);
	$group->post('/attendance/break-end', [AttendanceApiController::class, 'EndBreak']);

	$group->post('/schedule/clock-in', [AttendanceApiController::class, 'ClockIn']);
	$group->post('/schedule/clock-out', [AttendanceApiController::class, 'ClockOut']);
	$group->post('/schedule/attendance', [AttendanceApiController::class, 'RecordAttendance']);
	$group->post('/schedule/absences', [ScheduleApiController::class, 'RequestAbsence']);
	$group->post('/schedule/absences/{id}/decide', [ScheduleApiController::class, 'DecideAbsence']);
	$group->post('/schedule/swaps', [ScheduleApiController::class, 'RequestSwap']);
	$group->post('/schedule/swaps/{id}/decide', [ScheduleApiController::class, 'DecideSwap']);

	// Mapa del Sistema (solo ADMIN)
	$group->get('/system-map', [SystemMapApiController::class, 'Get']);
	$group->post('/system-map/refresh', [SystemMapApiController::class, 'Refresh']);
	$group->get('/system-map/export/{format}', [SystemMapApiController::class, 'Export']);

	// Economía de DAILY (solo ADMIN)
	$group->post('/admin/economics/simulate', [\Grocy\Controllers\ProductEconomicsController::class, 'SimulateApi']);
	$group->post('/admin/economics/expense', [\Grocy\Controllers\ProductEconomicsController::class, 'AddExpenseApi']);

	// Administración y Sistema (solo ADMIN)
	$group->post('/admin/settings', [\Grocy\Controllers\SystemAdminController::class, 'SaveSettings']);
	$group->get('/audit-log/export', [\Grocy\Controllers\SystemAdminController::class, 'AuditLogExport']);

	// DAILY Action Center API
	$group->post('/action-center/{id:[0-9]+}/action', [\Grocy\Controllers\Api\DailyActionCenterApiController::class, 'ExecuteAction']);
	$group->post('/action-center/rules/run', [\Grocy\Controllers\Api\DailyActionCenterApiController::class, 'RunRule']);

	// Buscador global del header
	$group->get('/dairy/search', [GlobalSearchApiController::class, 'Search']);

	// Rebaja automática FEFO (markdown)
	$group->get('/markdown/active', [MarkdownApiController::class, 'GetActiveMarkdowns']);
	$group->get('/markdown/rules', [MarkdownApiController::class, 'GetRules']);
	$group->post('/markdown/rules', [MarkdownApiController::class, 'SaveRules']);

	// Trazabilidad, Conciliación e Inventario Sensible API
	$group->get('/devices/required', [\Grocy\Controllers\Api\DeviceApiController::class, 'Required']);
	$group->get('/devices/lookup/{code}', [\Grocy\Controllers\Api\DeviceApiController::class, 'Lookup']);
	$group->get('/devices/{id}/reveal', [\Grocy\Controllers\Api\DeviceApiController::class, 'Reveal']);
	$group->get('/devices/{id}', [\Grocy\Controllers\Api\DeviceApiController::class, 'GetDetail']);
	$group->post('/devices/register', [\Grocy\Controllers\Api\DeviceApiController::class, 'QuickRegister']);
	$group->post('/devices/action', [\Grocy\Controllers\Api\DeviceApiController::class, 'QuickAction']);
	$group->post('/devices/receipts', [\Grocy\Controllers\Api\DeviceApiController::class, 'OpenReceipt']);
	$group->post('/devices/receipts/{id}/scan', [\Grocy\Controllers\Api\DeviceApiController::class, 'ScanReceipt']);
	$group->post('/devices/receipts/{id}/close', [\Grocy\Controllers\Api\DeviceApiController::class, 'CloseReceipt']);
	$group->post('/devices/moves', [\Grocy\Controllers\Api\DeviceApiController::class, 'RequestMove']);
	$group->post('/devices/moves/{id}/confirm', [\Grocy\Controllers\Api\DeviceApiController::class, 'ConfirmMove']);
	$group->post('/devices/block', [\Grocy\Controllers\Api\DeviceApiController::class, 'SetBlocked']);
	$group->post('/devices/holds/{id}', [\Grocy\Controllers\Api\DeviceApiController::class, 'DecideHold']);
	$group->post('/devices/rules', [\Grocy\Controllers\Api\DeviceApiController::class, 'SetRule']);
	$group->post('/devices/orders/{id}/reserve', [\Grocy\Controllers\Api\DeviceApiController::class, 'Reserve']);
	$group->post('/devices/orders/{id}/scan', [\Grocy\Controllers\Api\DeviceApiController::class, 'OrderScan']);
	$group->post('/devices/transfer', [\Grocy\Controllers\Api\DeviceApiController::class, 'Transfer']);
	$group->post('/devices/transfers/{id}/receive', [\Grocy\Controllers\Api\DeviceApiController::class, 'ReceiveTransfer']);
	$group->post('/devices/returns', [\Grocy\Controllers\Api\DeviceApiController::class, 'RegisterReturn']);
	$group->post('/devices/{id}/return-decision', [\Grocy\Controllers\Api\DeviceApiController::class, 'DecideReturn']);
	$group->post('/devices/counts', [\Grocy\Controllers\Api\DeviceApiController::class, 'OpenCount']);
	$group->post('/devices/counts/{id}/scan', [\Grocy\Controllers\Api\DeviceApiController::class, 'ScanCount']);
	$group->post('/devices/counts/{id}/close', [\Grocy\Controllers\Api\DeviceApiController::class, 'CloseCount']);
	$group->post('/devices/found', [\Grocy\Controllers\Api\DeviceApiController::class, 'Found']);
	$group->post('/loss-control/cases/{id}/cause', [\Grocy\Controllers\Api\LossCaseApiController::class, 'ConfirmCause']);
	$group->post('/loss-control/cases/{id}/reopen', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Reopen']);
	$group->get('/merchandise/book', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Book']);
	$group->post('/merchandise/movement', [\Grocy\Controllers\Api\LossCaseApiController::class, 'RecordMovement']);
	$group->get('/merchandise/trace/search', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SearchTrace']);
	$group->get('/merchandise/trace/{id}',[\Grocy\Controllers\Api\LossCaseApiController::class, 'TraceJourney']);
	$group->get('/merchandise/tab/{tab}', [\Grocy\Controllers\Api\LossCaseApiController::class, 'MerchandiseTab']);
	$group->post('/merchandise/transfers', [\Grocy\Controllers\Api\LossCaseApiController::class, 'RecordTransfer']);
	$group->post('/merchandise/transfers/{id}/receive', [\Grocy\Controllers\Api\LossCaseApiController::class, 'ReceiveTransfer']);
	$group->post('/merchandise/adjustments', [\Grocy\Controllers\Api\LossCaseApiController::class, 'RequestAdjustment']);
	$group->post('/merchandise/adjustments/{id}/decide', [\Grocy\Controllers\Api\LossCaseApiController::class, 'DecideAdjustment']);
	$group->post('/merchandise/differences/{id}/action', [\Grocy\Controllers\Api\LossCaseApiController::class, 'DifferenceAction']);
	$group->get('/merchandise/scan/{barcode}', [\Grocy\Controllers\Api\LossCaseApiController::class, 'ScanBarcode']);
	$group->get('/loss-control/dashboard', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Dashboard']);
	$group->get('/loss-control/differences', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Differences']);
	$group->get('/loss-control/tasks', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Tasks']);
	$group->post('/loss-control/tasks/{id}/count', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SubmitCount']);
	$group->post('/loss-control/plans', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SavePlan']);
	$group->post('/loss-control/plans/{id}/active', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SetPlanActive']);
	$group->post('/loss-control/categories', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SaveCategory']);
	$group->post('/loss-control/products/{id}/classify', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Classify']);
	$group->post('/loss-control/cases', [\Grocy\Controllers\Api\LossCaseApiController::class, 'CreateIncident']);
	$group->get('/loss-control/cases/{id}', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetCase']);
	$group->post('/loss-control/cases/{id}/recount-request', [\Grocy\Controllers\Api\LossCaseApiController::class, 'RequestRecount']);
	$group->post('/loss-control/cases/{id}/assign', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Assign']);
	$group->post('/loss-control/cases/{id}/propose', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Propose']);
	$group->post('/loss-control/cases/{id}/decide', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Decide']);
	$group->post('/loss-control/cases/{id}/close', [\Grocy\Controllers\Api\LossCaseApiController::class, 'CloseCase']);
	$group->post('/loss-control/cases/{id}/evidence', [\Grocy\Controllers\Api\LossCaseApiController::class, 'AddEvidence']);
	$group->get('/loss-control/evidence/{id}', [\Grocy\Controllers\Api\LossCaseApiController::class, 'ServeEvidence']);
	$group->post('/loss-control/hypotheses/{id}', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Hypothesis']);
	$group->post('/loss-control/cases/{id}/call', [\Grocy\Controllers\Api\LossCaseApiController::class, 'LogCall']);
	$group->get('/loss-control/cases/{id}/calls', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetCalls']);
	$group->get('/loss-control/cases/{id}/thread', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetThread']);
	$group->post('/loss-control/cases/{id}/message', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SendMessage']);
	$group->post('/loss-control/cases/{id}/escalate', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Escalate']);
	$group->post('/loss-control/cases/{id}/checkin', [\Grocy\Controllers\Api\LossCaseApiController::class, 'Checkin']);
	$group->post('/loss-control/cases/{id}/scan-verify', [\Grocy\Controllers\Api\LossCaseApiController::class, 'ScanVerify']);
	$group->post('/loss-control/cases/{id}/tasks', [\Grocy\Controllers\Api\LossCaseApiController::class, 'AddTask']);
	$group->post('/loss-control/tasks/{id}/status', [\Grocy\Controllers\Api\LossCaseApiController::class, 'UpdateTaskStatus']);
	$group->get('/loss-control/cases/{id}/tasks', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetTasks']);
	$group->post('/loss-control/cases/{id}/actions', [\Grocy\Controllers\Api\LossCaseApiController::class, 'AddAction']);
	$group->post('/loss-control/actions/{id}/decide', [\Grocy\Controllers\Api\LossCaseApiController::class, 'DecideAction']);
	$group->get('/loss-control/cases/{id}/actions', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetActions']);
	$group->post('/loss-control/cases/{id}/preventive-plan', [\Grocy\Controllers\Api\LossCaseApiController::class, 'CreatePreventivePlan']);
	$group->post('/loss-control/cases/{id}/handover', [\Grocy\Controllers\Api\LossCaseApiController::class, 'AddHandover']);
	$group->get('/loss-control/preventive-plans', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetPreventivePlans']);
	$group->get('/loss-control/staff', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetStaff']);
	$group->get('/loss-control/map', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetMap']);
	$group->get('/loss-control/productivity', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetProductivity']);
	$group->post('/loss-control/cases/{id}/wizard-step', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SaveWizardStep']);
	$group->get('/loss-control/cases/{id}/wizard-state', [\Grocy\Controllers\Api\LossCaseApiController::class, 'GetWizardState']);
	$group->post('/loss-control/cases/{id}/root-cause', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SaveRootCause']);
	$group->post('/loss-control/cases/{id}/financial-recovery', [\Grocy\Controllers\Api\LossCaseApiController::class, 'SaveFinancialRecovery']);
	$group->post('/loss-control/cctv/access', [\Grocy\Controllers\Api\LossCaseApiController::class, 'RecordCctvAccess']);
	$group->get('/loss-control/cctv/logs', [\Grocy\Controllers\Api\LossCaseApiController::class, 'CctvAccessLogs']);
	$group->get('/loss-control/pos-anomalies', [\Grocy\Controllers\Api\LossCaseApiController::class, 'PosAnomalies']);
	$group->get('/reconciliation/overview', [ReconciliationApiController::class, 'GetOverview']);
	$group->get('/reconciliation/products', [ReconciliationApiController::class, 'GetProducts']);
	$group->get('/reconciliation/product/{productId}', [ReconciliationApiController::class, 'GetProduct']);
	$group->get('/reconciliation/timeline/{productId}', [ReconciliationApiController::class, 'GetTimeline']);
	$group->post('/reconciliation/count', [ReconciliationApiController::class, 'ExecuteCount']);
	$group->get('/reconciliation/case/{caseId}', [ReconciliationApiController::class, 'GetCase']);
	$group->post('/reconciliation/case/{caseId}/recount', [ReconciliationApiController::class, 'RecountCase']);
	$group->post('/reconciliation/case/{caseId}/adjust', [ReconciliationApiController::class, 'AuthorizeAdjustment']);
	$group->post('/reconciliation/case/{caseId}/close', [ReconciliationApiController::class, 'CloseCase']);
	$group->post('/reconciliation/goods-in', [ReconciliationApiController::class, 'RegisterGoodsIn']);
	$group->post('/reconciliation/return', [ReconciliationApiController::class, 'RegisterReturn']);
	$group->post('/reconciliation/dispatch', [ReconciliationApiController::class, 'RegisterMovement']);

	// DAILY: Stock & Movimientos — Panel Ejecutivo de Inventario API
	$group->get('/stockoverview/executive', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'GetOverview']);
	$group->get('/stockoverview/heatmap', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'GetHeatmap']);
	$group->get('/stockoverview/top-products', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'GetTopProducts']);
	$group->get('/stockoverview/expiring', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'GetExpiring']);
	$group->get('/stockoverview/product/{productId}', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'GetProduct360']);
	$group->post('/stockoverview/fefo-action', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'ExecuteFefoAction']);
	$group->post('/stockoverview/workflow', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'ExecuteWorkflowStep']);
	$group->post('/stockoverview/reset', [\Grocy\Controllers\Api\StockExecutiveApiController::class, 'ResetDemo']);

	// DAILY: Punto de Venta Rápido (POS) — Turnos de Caja y Ventas en Espera API
	$group->get('/pos/state', [SalesApiController::class, 'GetPosState']);
	// POS (PosService): cobro idempotente, autorizaciones de supervisor, turnos con arqueo, recibos y consulta
	$group->post('/pos/quote', [\Grocy\Controllers\Api\PosApiController::class, 'Quote']);
	$group->post('/pos/checkout', [\Grocy\Controllers\Api\PosApiController::class, 'Checkout']);
	$group->post('/pos/authorize', [\Grocy\Controllers\Api\PosApiController::class, 'Authorize']);
	$group->get('/pos/lookup', [\Grocy\Controllers\Api\PosApiController::class, 'Lookup']);
	$group->get('/pos/shifts/current', [\Grocy\Controllers\Api\PosApiController::class, 'CurrentShift']);
	$group->post('/pos/shifts/open', [\Grocy\Controllers\Api\PosApiController::class, 'OpenShift']);
	$group->get('/pos/shifts/{id:[0-9]+}', [\Grocy\Controllers\Api\PosApiController::class, 'GetShift']);
	$group->post('/pos/shifts/{id:[0-9]+}/movement', [\Grocy\Controllers\Api\PosApiController::class, 'CashMovement']);
	$group->post('/pos/shifts/{id:[0-9]+}/close', [\Grocy\Controllers\Api\PosApiController::class, 'CloseShift']);
	$group->get('/pos/transactions', [\Grocy\Controllers\Api\PosApiController::class, 'Transactions']);
	$group->get('/pos/sales/{id:[0-9]+}', [\Grocy\Controllers\Api\PosApiController::class, 'GetSale']);
	$group->post('/pos/sales/{id:[0-9]+}/void', [\Grocy\Controllers\Api\PosApiController::class, 'VoidSale']);
	$group->post('/pos/sales/{id:[0-9]+}/reprint', [\Grocy\Controllers\Api\PosApiController::class, 'Reprint']);
	$group->post('/pos/hold/{holdId:[0-9]+}/recall', [\Grocy\Controllers\Api\PosApiController::class, 'Recall']);
	// Turno sin arqueo ni fondo contado: retirados (usar /pos/shifts/...)
	$group->post('/pos/shift/open', [\Grocy\Controllers\Api\PosApiController::class, 'OpenShift']);
	$group->post('/pos/hold', [SalesApiController::class, 'HoldSale']);
	$group->delete('/pos/hold/{holdId}', [SalesApiController::class, 'DeleteHeldSale']);
	$group->post('/pos/hold/{holdId}/delete', [SalesApiController::class, 'DeleteHeldSale']);

	// DAILY: Devoluciones del POS API
	// Devoluciones comerciales (PosReturnService): elegibilidad, reembolso al medio original, nota de crédito, inspección en /returns
	$group->get('/pos/returns/lookup', [\Grocy\Controllers\Api\PosApiController::class, 'ReturnLookup']);
	$group->post('/pos/returns/process', [\Grocy\Controllers\Api\PosApiController::class, 'ReturnProcess']);
	$group->get('/pos/returns/history', [\Grocy\Controllers\Api\PosApiController::class, 'ReturnHistory']);
	$group->get('/pos/returns/{id:[0-9]+}', [\Grocy\Controllers\Api\PosApiController::class, 'ReturnGet']);
	$group->get('/pos/credit-notes/{code}', [\Grocy\Controllers\Api\PosApiController::class, 'CreditNote']);
	$group->get('/pos/customers/{customerId}/loyalty', [\Grocy\Controllers\Api\PosApiController::class, 'CustomerLoyalty']);

	// DAILY: Directorio de Clientes API
	// CRM de clientes (CustomerCrmService): datos personales enmascarados sin CUSTOMERS_PII; cambios auditados
	$group->get('/customers', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Directory']);
	$group->post('/customers', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Save']);
	$group->post('/customers/merge', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Merge']);
	$group->post('/customers/settings', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Settings']);
	$group->post('/customers/campaigns', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'SaveCampaign']);
	$group->post('/customers/automations/run', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'RunAutomations']);
	$group->post('/customers/automations/{ruleId}/toggle', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'ToggleAutomation']);
	$group->post('/customers/requests', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'SaveRequest']);
	$group->post('/customers/requests/{requestId}', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'SaveRequest']);
	$group->post('/customers/requests/{requestId}/credit-decision', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'CreditDecision']);
	$group->get('/customers/{customerId}', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Profile']);
	$group->post('/customers/{customerId}', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Save']);
	$group->post('/customers/{customerId}/active', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'SetActive']);
	$group->post('/customers/{customerId}/consents', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Consent']);
	$group->post('/customers/{customerId}/preferences', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Preferences']);
	$group->post('/customers/{customerId}/interactions', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Interaction']);
	$group->post('/customers/{customerId}/loyalty', [\Grocy\Controllers\Api\CustomerCrmApiController::class, 'Loyalty']);
})->add(new CorsMiddleware($container, $app->getResponseFactory()))->add(new JsonMiddleware($container, $app->getResponseFactory()));


// For CORS preflight OPTIONS requests
$app->any('/api/{routes:.+}', function (Request $request, Response $response): Response
{
	return $response;
})->add(new CorsMiddleware($container, $app->getResponseFactory()));
