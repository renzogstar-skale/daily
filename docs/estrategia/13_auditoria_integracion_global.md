# 13 · Auditoría de integración global (Fase 1)

Fecha: 2026-10-29 · Base auditada: `data/grocy_es.db` (MODE=demo) · Solo lectura, sin cambios de código ni datos.

## 1. Rutas pedidas vs. existentes

| Estado | Rutas |
|---|---|
| Existen (29) | /sales /pos /orders /customers /mrp /procurement /stockoverview /scan /locations/map /transfers /kardex /reconciliation /inventory-control /inventory /branches /returns /operations/scheduling /attendance /tasks /quality /recalls /waste /waste/report /waste/approvals /reports /today /kpis /alerts |
| Con otro nombre | /dispatches → `/dispatch` · /audit-log → `/audits` · /settings → `/usersettings`, `/stocksettings`, `/system/config` |
| No existen | /incidents · /requests · /communications (tampoco están en el menú; las solicitudes de ausencia y la mensajería viven en Horarios y `/m`) |

El menú real (`helpers/DairyNavigation.php`) tiene además: Simulador What-If, Gestión de Personal, Productividad Operativa, Trazabilidad de Lotes, Matriz de Causales, Investigaciones y Casos, Recuperación Financiera, Centros de Costo, Arturito IA, Mapa del Sistema. 237 rutas de página en total.

## 2. Inventario de datos (empresa de ejemplo)

| Entidad | Cantidad | Observación |
|---|---|---|
| Sucursales | 10 | Hallandale Beach, Miami Beach, Sunny Isles Beach, Aventura, North Miami Beach, Miami, Hollywood, Fort Lauderdale, Doral, Hialeah. Difiere de la lista pedida (North Miami, Downtown Miami, Pembroke Pines); la regla del pedido es conservar las existentes. |
| Productos activos | 43 | Pedido: ≥ 1.000. SKU único 100 %; solo 26 códigos de barras (UPC) registrados. |
| Empleados | 241 | 0 vinculados a usuario (6 usuarios). Asistencia: 34 registros frente a 6.960 turnos. |
| Ventas | 5.515 | Volumen bajo: ~9 tickets / ~$100 por sucursal y día frente a ~200 h de turnos → venta por hora y % merma irreales. |
| Movimientos de stock | 16.357 | Libro inalterable = kardex (sin faltantes). |
| Traslados / Conteos / Ajustes | 9 / 83 / 3 | |
| Mermas / Alertas | 637 / 610 | |
| Pedidos / Devoluciones | 50 / 6 | |
| Tareas | 7 | |
| Clientes / Proveedores | 25 / 9 | |

## 3. Recorridos ya integrados y probados

Pruebas existentes en verde (`tests/dairy`), con datos reales del flujo:

- **B · Venta → inventario → rentabilidad**: stock −2, kardex y libro, ventas del día y HOY suben exactamente el importe (`test_company_coherence`).
- **A · Compra → recepción**: stock +10, movimiento de compra en kardex y libro, lote ligado, recepción repetida no duplica (`test_company_coherence`, `test_traceability`).
- **Pedido → despacho**: reserva, picking FEFO, despacho idempotente, venta ligada.
- **C · Traslado**: diferencia en tránsito, saldo por ubicación, línea de tiempo (`test_traceability`).
- **E · Vencimiento → merma**: alerta de lote por vencer, merma idempotente según estado.
- **D · Inventario sensible**: 14 puntos del expediente, conteo con diferencia → alerta → caso → reconteo → ajuste con segregación de funciones (`test_reconciliation`).
- **KPIs**: HOY = Panel = Ventas = Inventario = Mermas = Centro de costos (`test_kpi_consistency`, `test_today_panel_sync`).

## 4. Hallazgos que rompen «una operación → un registro»

| # | Gravedad | Hallazgo | Evidencia |
|---|---|---|---|
| H1 | Alta | Conciliación escribe directo en `stock` sin `stock_log` (ingreso y devolución): el movimiento no llega a kardex ni al libro, no se liga a OC. | `services/ReconciliationService.php` RegisterGoodsIn / RegisterReturn (INSERT INTO stock) |
| H2 | Alta | El ajuste autorizado de una diferencia sensible solo se guarda en `sensitive_inventory_movements` (tabla paralela): Inventario y Kardex no cambian. | `ReconciliationService::AuthorizeAdjustment` |
| H3 | Alta | Presupuesto de compras 100 % fijo en el controlador, con facturas inventadas (FAC-2026-0881…). | `controllers/SupplierProcurementController.php` (78 montos fijos) |
| H4 | Media | Gráficos de Horarios con datos de relleno cuando no hay serie (sucursales «Centro/Norte/Sur», coberturas 92/78/85…). | `public/viewjs/schedule.js:891-911` |
| H5 | Media | Comparativo anual de Mermas con montos fijos de respaldo. | `views/components/waste_tabs/anual.blade.php:81-82` |
| H6 | Media | Folios con `rand()` (pueden repetirse y no son trazables): conciliación, resumen ejecutivo de stock, formularios. | `ReconciliationService`, `StockExecutiveOverviewService`, `views/reconciliation.blade.php` |
| H7 | Media | Filtro de sucursal: 15 de 35 controladores de página lo leen; Stock, Traslados, Compras, Tareas no (filtran en JS o no filtran; por verificar página por página). | `controllers/*Controller.php` |
| H8 | Media | Ningún empleado tiene usuario: Asistencia, Tareas y la app móvil no pueden atribuir acciones a una persona. | `employees.user_id` |
| H9 | Baja | App nativa (Expo) cubre personal (fichaje, horario, turnos, mensajes); escaneo, conteo, recepción, picking, traslados y mermas existen solo en la web móvil `/m`. | `mobile/src/app`, `public/js/dairy_m.js` |

Ya corregido en la sesión anterior: valores fijos de HOY y Panel Ejecutivo.

## 5. Plan por fases (pendiente de aprobación)

| Fase | Trabajo | Cambio estructural |
|---|---|---|
| 2 · Datos | Ampliar catálogo (43 → N productos con UPC válido, ABC-XYZ, mín/máx), vincular empleados a usuarios, subir volumen de ventas a escala realista, completar asistencia. Requiere regenerar la empresa de ejemplo (horas). | **Sí** |
| 3 · Procesos | H1 y H2: conciliación y ajuste autorizado vía `StockService` (stock_log + libro + auditoría). H6: folios secuenciales. | Sí (servicio de conciliación) |
| 4 · Personal | Ausencia → cobertura → alerta → tarea de sustitución, con empleados vinculados. | No |
| 5 · Analítica | H3, H4, H5: reemplazar fijos por datos; H7: contexto de filtros en las páginas que faltan. | No |
| 6 · Validación | Pruebas 1–6 del pedido (venta 100→92, traslado 100/50→80/70, merma 50→45, filtros, persistencia, integridad global). | No |
| Móvil nativo | Llevar escaneo/conteo/recepción/mermas a la app Expo (hoy en `/m`). | Sí (alcance) |
