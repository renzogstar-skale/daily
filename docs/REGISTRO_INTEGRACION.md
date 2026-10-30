# Registro de integración técnica (base de datos/KPI/permisos)

Trabajo en la carpeta compartida (sin rama: todo el trabajo DAIRY está sin commitear en `master`). No se tocaron vistas.

## 2026-10-28 · Empresa de ejemplo conectada (detalle en `docs/estrategia/10_sucursales_demo.md`)

**Datos.** `DailyCompanySeedService` (migración 0300) ahora opera 58 días con 8 departamentos (13 artículos nuevos, ids 176–188), bodega seca y congelador por sucursal, proveedores nuevos, traslados, conteos cíclicos y lecturas de congelador. Ventas cargadas sin descargo dentro del período → estado `void` (se conservan; no suman ni son devolución). BD reconstruida desde `grocy_es.db.bak_pre_empresa`; la anterior quedó en `grocy_es.db.bak_empresa_28d`.

**Errores corregidos.**
- `WasteService`: merma sin ubicación ni lote descontaba FEFO en **cualquier sucursal** (también al aprobar). Ahora descuenta en la ubicación de la sucursal de la merma (`BranchLocation`).
- `WasteApiController::ApproveCycleCountAdjustment`: usaba `InventoryProduct` (compara con el total de la red y consume sin ubicación). Ahora corrige en la ubicación contada.
- `StockTransferService`: correlativo de merma de traslado `COUNT+1` chocaba con `case_number` único → `MAX+1`.
- `KpiService`: valor de inventario incluía stock en ubicaciones inexistentes y en tránsito; ahora = Inventario y Almacenes.
- `WasteExecutiveControlCenterService`: faltaba `use DairyAccess` (error fatal en Mermas ejecutivo).
- `/stockjournal`: agotaba memoria con 16 mil movimientos → 1 mes por defecto y 2.500 filas como máximo (aviso en pantalla).

**Pantallas.** Centro de Alertas leído de `dairy_alerts` (antes un conjunto fijo en JS) y acciones por API. Respaldos con cifras fijas → «Sin información» en Mermas (resumen, censo, anual, controles, perecederos, selectivos), Inventario, Despachos, POS, Conciliación y Horarios. Acciones FEFO y «flujo de 10 pasos» de Inventario rotuladas como simulación.

**Pruebas.** `test_company_coherence.php` (53 verificaciones: catálogo, métricas cruzadas, venta, recepción, pedido, merma, vencimiento, personal, traslados, conteos, textos). Ajustes de supuestos de datos en `test_stage34`, `test_platform360`, `test_mobile_work`, `test_loss_prevention`.

**Pendiente (no tocado).** `/api/schedule/coverage/suggest`, `/changes`, `/adjustments` llaman métodos inexistentes de `WorkforceScheduleService` (500); `/api/schedule/templates` y `/attendance` responden vacío sin explicar parámetros.

## 2026-10-27 · Margen de ventas: una sola fórmula

**Problema.** `sales.gross_margin` se guarda **neto de IVA** (`total − IVA − costo`, `SalesService::CreateSale`), pero:
- `KpiService` (KPI ejecutivo, deltas, ranking de sucursales) y `BranchService` (scorecards) dividían ese margen entre la venta **con** IVA.
- `CostCenterService` y la rentabilidad por producto (`KpiService::GetDeepProductProfitability`) calculaban el margen como `subtotal − costo` **sin restar el IVA** → el mismo período daba márgenes distintos según la pantalla.
- La rentabilidad por producto usaba `JOIN quantity_units` y **descartaba las ventas** de productos con unidad inválida (1.848 en la demo de oct-2026).
- `CostCenterService` filtraba `status != 'cancelled'` y el resto `status = 'completed'`.

**Corrección.**
- Fuente común en `SalesService`: `SQL_NET_SALES`, `SQL_ITEM_NET_SALES` (prorratea el IVA del ticket a sus líneas) y `MarginPct(margen, venta_neta)`.
- Margen % = margen / venta neta de IVA en KPI ejecutivo, deltas (actual y anterior), ranking, scorecards, centro de costos y producto.
- Centro de costos y producto: margen = venta neta − costo (misma definición que `sales.gross_margin`); centro de costos expone `net_sales`.
- `LEFT JOIN quantity_units`; estado `completed` en centro de costos.
- Costo faltante ≠ costo cero: cada producto expone `lines_without_cost` (líneas con `unit_cost = 0` por falta de precio de compra).
- **No cambió** el importe mostrado como "Ventas" (sigue con IVA en KPI/sucursales).

**Archivos.** `services/SalesService.php`, `services/KpiService.php`, `services/BranchService.php`, `services/CostCenterService.php`, `tests/dairy/test_kpi_consistency.php` (nuevo).

**Validación (pruebas, BD demo copiada).** `test_kpi_consistency.php` 13/13: margen % idéntico entre KPI ejecutivo, ranking, scorecard y referencia SQL por sucursal; centro de costos = rentabilidad por producto = margen por líneas. Sin regresiones en `run.php`, `test_branch_access`, `test_platform360`, `test_platform360_roles`, `test_mobile`, `test_pos_ingest` y el resto de `tests/dairy/test_*.php`. No verificado en navegador.

## 2026-10-27 · Permiso faltante en `/api/today/sales-executive`

**Problema.** La ruta no figura en `DairyAccess::PAGES`: cualquier usuario autenticado podía leerla. (El alcance por sucursal sí lo aplica `DairyPermissionMiddleware` a todas las rutas.)
**Corrección.** `KpiApiController::GetTodayExecutiveSales` exige `DairyAccess::CanOpen('/today')` (mismos permisos que la página).
**Validación.** `php -l`; sin prueba HTTP.

## Pendientes / para el otro agente (no corregidos: archivos de pantalla en curso)

1. **`TodayExecutiveSalesService`** mezcla datos reales con valores fijos/simulados (márgenes por departamento 24,5 %, devoluciones `640 × factor`, descuentos `1200 × factor`, margen tomado de una constante). Conectar a `sales.discount_amount`, devoluciones reales y `SalesService::MarginPct`, o rotular como demostración.
2. **`views/today.blade.php` `fetchExecSales`**: no aborta la petición anterior; una respuesta lenta puede sobrescribir un filtro más reciente (guardar el `jqXHR` y `abort()`, o descartar por número de petición).
3. **`KpiController::CostCenters`** (≈l.108): para usuarios con varias sucursales recalcula `margin_pct = margin / sales` (con IVA). Sumar también `net_sales` y usar `SalesService::MarginPct($total['margin'], $total['net_sales'])`.
4. **Definición de negocio a decidir:** "Ventas" se muestra con IVA en KPI/sucursales (`BranchService` lo llama `net_sales`) y sin IVA en móvil y Centro de alertas. Elegir una y rotular.
5. **Datos demo:** 4 ventas de oct-2026 (ids 8, 10, 25, 27) cuyas líneas no cuadran con la cabecera; las vistas por línea (producto/categoría) difieren del KPI por cabecera mientras existan.
6. **Horarios:** `test_dynamic_schedule_engine.php` falla 6 verificaciones (estándares departamentales/SPLH ausentes en la BD demo). No relacionado con estos cambios (no tocan horarios); no se comprobó contra el código anterior.
