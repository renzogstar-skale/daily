# 14 · Definiciones formales de KPIs compartidos

Fuente única: `services/DailyMetricsService.php` (HOY, Panel Ejecutivo, ranking de sucursales, Compras) sobre las tablas operativas. Reglas de estado: `DailyMetricsService::RULES`.
Período: el de la pantalla, anclado a la fecha de referencia (`?date=`) y cortado en ella (`PeriodRange`). Sucursal: `?branch_id=` (vacío = red; la red es la suma de sucursales, nunca un promedio de porcentajes).
Actualización: bajo demanda (cada carga de página lee los registros). Un valor `null` = «Sin información» (sin registros), distinto de un cero real.

| KPI | Fórmula | Unidad | Fuente | Exclusiones |
|---|---|---|---|---|
| Ventas netas | Σ (total_amount − tax_amount) de ventas `completed` por fecha de la venta. Por departamento: Σ sale_items.subtotal | $ | `sales`, `sale_items` | Anuladas y devueltas (status ≠ completed). Nunca se mezcla con venta bruta. |
| Meta de ventas | Σ metas diarias del período; meta diaria = meta mensual ÷ días del mes | $ | `dairy_budgets` (BudgetService) | La meta de red no se hereda como meta de una sucursal. |
| Cumplimiento | Ventas netas ÷ meta × 100 | % | derivado | Sin meta o sin ventas → sin información. |
| Costo de ventas | Σ total_cogs (costo FEFO del lote consumido) | $ | `sales` | — |
| Margen bruto | Ventas netas − costo de ventas; % = margen ÷ ventas netas | $, % | `sales` | — |
| Ticket promedio | Ventas netas ÷ tickets | $ | `sales` | — |
| Merma confirmada | Σ total_cost de mermas `approved` | $ | `waste_records` | Pendientes de aprobación se informan aparte; diferencias de inventario y ajustes no son merma. |
| % merma | Merma ÷ base configurada (BudgetService::WasteBase) × 100 | % | derivado | Sin ventas → sin información. |
| Stock disponible | Existencias en ubicaciones de la sucursal (sin tránsito) − cuarentena − reservas de pedidos | u. | `stock`, `quarantine_items`, `customer_orders` | Tránsito entre sucursales no suma a origen ni destino. |
| Valor de inventario | Σ cantidad × costo del lote (o último costo de compra) | $ | `stock` | Ubicaciones de tránsito. |
| Rotación | Costo de ventas del período ÷ valor de inventario | veces | derivado | Objetivo = días del período ÷ días de inventario objetivo. |
| Diferencias de inventario | Ajustes `pending_approval` + investigaciones sensibles abiertas | n.º | `inventory_adjustments`, `sensitive_investigation_cases` | Solo se ajusta stock tras autorización (corrección de inventario, no merma). |
| Pedidos a tiempo | Despachados con fecha ≤ solicitada ÷ despachados, 28 días | % | `customer_orders` | — |
| Pedidos atrasados | Confirmados/en preparación con fecha solicitada vencida | n.º | `customer_orders` | — |
| Horas programadas | Σ turnos no cancelados − pausas | h | `employee_shifts` | — |
| Horas presentes | Horas programadas − horas de ausencias aprobadas e inasistencias (sin fichaje 30 min después del inicio, si la sucursal registra asistencia ese día) | h | `employee_shifts`, `employee_attendance`, `employee_absences` | — |
| Cobertura de personal | Horas presentes ÷ horas requeridas por la cobertura mínima | % | `coverage_requirements` | Sin requerimiento → se muestran personas con turno. |
| Venta por hora | Ventas netas ÷ horas programadas (transcurridas si es hoy) | $/h | derivado | — |
| Presupuesto de compras | Meta de ventas × (1 − margen objetivo), por departamento según su peso en el costo de ventas | $ | derivado | No hay presupuesto de compras cargado: es una estimación declarada como tal. |

## Reglas de estado (verde / amarillo / rojo / sin información)

| Indicador | En objetivo | Atención | Crítico |
|---|---|---|---|
| Cumplimiento de ventas | ≥ 100 % | 90–99,9 % | < 90 % |
| Margen | ≥ objetivo | hasta 3 pp bajo objetivo | más de 3 pp bajo objetivo |
| % merma | ≤ objetivo | ≤ 1,5 × objetivo | > 1,5 × objetivo |
| Cobertura | ≥ 95 % | 85–94 % | < 85 % |
| Compras atrasadas / pedidos vencidos | 0 | 1–2 | > 2 |
| Diferencias de inventario | 0 | 1–3 | > 3 |
| Problemas de calidad | 0 | 1–5 | > 5 |

Pruebas: `tests/dairy/test_today_panel_sync.php`, `test_global_processes.php`, `test_kpi_consistency.php`, `test_company_coherence.php`.
