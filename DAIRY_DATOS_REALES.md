# DAIRY — Paso de datos demo a datos reales

Fecha: 2026-10-17 · Complementa `DAIRY_AUDIT.md`. Objetivo: saber **qué cargar, en qué orden, por dónde y qué módulo lo consume**, para reemplazar los datos de demostración sin romper stock, kardex ni KPIs.

## 1. Regla de oro

Todo dato real entra por **pantalla o API** (que llaman a los servicios), nunca con `INSERT` directo.
Los servicios escriben a la vez `stock` + `stock_log` (kardex) + costo + bitácora + segregación de funciones. Un `INSERT` directo deja el stock descuadrado del kardex y los KPIs sin trazabilidad.

Evidencia de que hoy hay mock directo en la BD (`data/grocy_es.db`, `MODE = demo` en `data/config.php`):

| Tabla | Filas | Observación |
|---|---|---|
| `sales` / `sale_items` | 51 / 93 | 49 ventas sin movimiento en `stock_log` → insertadas sin descontar stock |
| `waste_records` | 65 | ídem: mermas sin consumo de stock asociado |
| `supplier_deliveries` | 20 | entregas sin `procurement_orders` (0) ni recepciones (0) |
| `cold_chain_temperatures`, `quality_inspections`, `quarantine_items`, `stock_cycle_counts`, `supplier_returns`, `waste_action_plans`, `markdown_rules` | pocas | sembradas por migraciones (ver §5) |

## 2. Orden de carga (cada capa depende de la anterior)

### Capa 0 — Configuración (una vez)
| Qué | Dónde | Campos clave | Lo usa |
|---|---|---|---|
| Modo, moneda, idioma | `data/config.php`: `MODE='production'`, `CURRENCY`, `DEFAULT_LOCALE='es'` | — | todo |
| Metas y reglas DAIRY | `data/config.php` (sobrescribe `config-dist.php`): `WASTE_TARGET_PCT`, `MARGIN_TARGET_PCT`, `DSI_TARGET_DAYS`, `WASTE_PCT_BASE`, `MRP_SERVICE_LEVEL`, `MRP_REVIEW_PERIOD_DAYS`, `RECEIVING_MIN_SHELF_LIFE_PCT`, `INVOICE_PRICE_TOLERANCE_PCT`, `SALE_PRICE_DEFAULT_MARKUP_PCT`, `CYCLE_COUNT_DAYS_A/B/C`, `WORKFORCE_*` | valores de la empresa | Panel, MRP, compras, POS, conteos, turnos |
| Usuarios y permisos | `/users` + permisos; roles DAIRY (`roles`, `user_roles`) | un usuario por persona (la segregación compara IDs: quien registra ≠ quien aprueba) | aprobaciones de merma, ajustes, facturas |

### Capa 1 — Estructura
| Qué | Dónde | Campos clave | Si falta |
|---|---|---|---|
| Sucursales | ⚠ sin pantalla ni API de alta (solo migración/SQL). **Pendiente:** CRUD en `/branches` | `name`, `code`, activo | todo cuelga de `branch_id` |
| Ubicaciones | `/locations` (`/api/objects/locations`) | `name`, **`branch_id`**, `is_freezer` | stock "sin sucursal": no aparece en KPIs por sucursal ni en conteos ABC |
| Acceso por sucursal | `/branches` → "Acceso de usuarios" (`POST /api/branches/user-access/{userId}`) | sucursales del usuario | sin filas = ve toda la red |
| Centros de costo / presupuesto | `/cost-centers#budgets` (`POST /api/budgets`) | por sucursal y mes: ventas, merma, margen, DSI | se usan las metas de `config.php` |

### Capa 2 — Maestros
| Qué | Dónde | Campos clave | Lo usa |
|---|---|---|---|
| Unidades y grupos | `/quantityunits`, `/productgroups` | — | productos, Centros de costo por categoría |
| Productos | `/products` (`/api/objects/products`) | `name`, `qu_id_stock`, `product_group_id`, **`sale_price`**, **`default_best_before_days`** (vida útil), `min_stock_amount`, `location_id`, `sku` | POS (sin precio ni costo no se vende), FEFO/vencimientos, MRP (tope por vida útil), rebajas |
| Códigos de barra | `/api/objects/product_barcodes` | `barcode`, `product_id`, `qu_id` | escáner móvil, POS |
| Proveedores | `/suppliers` (`POST /api/suppliers`) → crea `shopping_locations` + `suppliers_meta` | **`lead_time_days`**, `min_order_amount`, condiciones de pago | MRP (punto de pedido), OTIF |
| Precios proveedor-producto | `POST /api/procurement/prices` | precio, `min_order_qty` (MOQ), múltiplo | MRP (costo y MOQ), 3-way match |
| Reglas de recepción | `/mrp` → "Reglas de recepción" (`POST /api/mrp/receiving-rules/{productId}`) | vida útil mínima, temperatura | rechazo en recepción |
| Causales de merma | `/waste/reasons` (`POST /api/waste/reasons`) | tipo (operativa/comercial), exige foto | registro y análisis de merma |
| Reglas de rebaja FEFO | `POST /api/markdown/rules` | días a vencer → % | POS y simulador |
| Empleados | `/schedule` (`POST /api/schedule/employees`) | sucursal, horas contratadas, disponibilidad | turnos, productividad, merma por turno |
| Clientes | ⚠ solo lectura (`/customers`); sin alta por API | — | POS (opcional) |

### Capa 3 — Saldos iniciales (corte)
Inventario físico de apertura por ubicación y lote:
- `/inventory` o `POST /api/stock/products/{id}/inventory` con `new_amount`, `best_before_date`, `location_id`, **`price`** (costo unitario).
- Sin precio no hay valorización, DSI ni costo de merma. Sin fecha de vencimiento no hay FEFO.
- Opcional: saldo histórico no se migra; el kardex empieza en la fecha de corte.

### Capa 4 — Operación diaria (genera la historia que alimenta los indicadores)
| Evento | Pantalla / API | Escribe |
|---|---|---|
| Compra | `/mrp`: OC → aprobar → enviar → recibir (`/api/mrp/order/*`) → factura (`/invoice`) | `procurement_*`, stock + `stock_log` con lote/vencimiento/costo, OTIF, `supplier_invoices` |
| Venta | `/pos` (`POST /api/sales`) | `sales`, `sale_items` con costo FEFO, consumo de stock |
| Merma | `/waste/report` → `/waste/approvals` (`/api/waste/report`, `/approve`) | `waste_records`, consumo `spoiled` en stock, tarea correctiva |
| Traslado | `/transfers` | salida/entrada en dos pasos |
| Conteo | Mermas → Conteos (`/api/stock/cycle-counts*`, ABC automático) | conteo + ajuste aprobado por otra persona |
| Calidad | `/quality` (temperaturas, inspecciones, cuarentena) | `cold_chain_temperatures`, `quality_inspections`, `quarantine_items` |
| Turnos y asistencia | `/schedule` | `employee_*` |

## 3. Cuánta historia necesita cada indicador

| Módulo / indicador | Fuente | Mínimo para que muestre algo útil |
|---|---|---|
| Panel: ventas, margen, merma % | `sales`, `sale_items`, `waste_records` aprobadas | 1 día; comparativo = período anterior completo |
| Interanual | idem | 12 meses |
| DSI / rotación | stock valorizado + costo de ventas | 30 días de ventas |
| MRP: demanda (VMD), σ, stock de seguridad | `sale_items` últimos 28 días | 28 días; sin ventas → estado "sin historia" (no inventa demanda) |
| MRP: estacionalidad semanal | idem | ≥ `MRP_SEASONALITY_MIN_UNITS` (28) unidades en 28 días |
| Precisión de pronóstico (WAPE/sesgo) | ventas por semana | 5 semanas cerradas |
| Conteo cíclico ABC | ventas 90 días + stock por sucursal | sin ventas todo es clase C |
| IRA (exactitud de inventario) | `stock_cycle_counts` ejecutados | 1 conteo ejecutado |
| OTIF proveedor | recepciones contra OC | 1 OC recibida |
| Arturito (anomalías μ+2σ) | series diarias de merma/ventas | ~4 semanas |
| Productividad / merma por turno | turnos + fichajes + mermas con `shift_id` | 1 semana publicada |

## 4. Verificación tras cada capa

```sql
-- Ubicaciones sin sucursal (deben ser 0)
SELECT id, name FROM locations WHERE branch_id IS NULL;
-- Productos activos sin precio de venta ni vida útil
SELECT id, name FROM products WHERE active = 1 AND (IFNULL(sale_price,0) = 0 OR IFNULL(default_best_before_days,0) = 0);
-- Stock sin costo (no se valoriza)
SELECT product_id, SUM(amount) FROM stock WHERE IFNULL(price,0) = 0 GROUP BY product_id;
-- Ventas sin movimiento de stock (indica carga directa)
SELECT COUNT(*) FROM sales s WHERE s.status = 'completed' AND NOT EXISTS (SELECT 1 FROM sale_items si JOIN stock_log l ON l.transaction_id = si.transaction_id WHERE si.sale_id = s.id);
-- Proveedores sin lead time configurado
SELECT sl.id, sl.name FROM shopping_locations sl LEFT JOIN suppliers_meta m ON m.id = sl.id WHERE m.id IS NULL;
```
Hoy la consulta de ventas devuelve **49 de 51**: confirma que las ventas demo no pasaron por el servicio.
Además: `php tests/dairy/run.php` debe seguir en 0 fallas (usa copia de la BD).

## 5. Limpieza del mock (no ejecutado; hacer con respaldo)

1. Respaldar `data/grocy_es.db`.
2. **Recomendado:** BD nueva en `MODE=production` y cargar capas 0→4. Antes, condicionar las semillas demo de las migraciones (`0258–0262`, `0264`, `0268`, `0269` insertan sucursales, causales, ubicaciones, productos, proveedores, inspecciones, cuarentena, conteos, rebajas) a `GROCY_MODE === 'demo'`; conservar solo catálogos de referencia (roles, causales estándar si se usan).
3. **Alternativa** (reutilizar la BD actual): conservar capas 0–2 revisadas y vaciar lo transaccional, luego hacer el inventario de corte (capa 3):
```sql
-- Orden: hijos antes que padres
DELETE FROM sale_items; DELETE FROM sales;
DELETE FROM waste_action_logs; DELETE FROM waste_action_plans; DELETE FROM waste_records;
DELETE FROM supplier_invoice_items; DELETE FROM supplier_invoices;
DELETE FROM procurement_receipt_items; DELETE FROM procurement_receipts;
DELETE FROM procurement_order_items; DELETE FROM procurement_orders;
DELETE FROM supplier_deliveries; DELETE FROM supplier_incidents; DELETE FROM supplier_returns;
DELETE FROM cold_chain_temperatures; DELETE FROM quality_inspections; DELETE FROM quarantine_items;
DELETE FROM stock_cycle_counts; DELETE FROM stock_transfer_items; DELETE FROM stock_transfers;
DELETE FROM stock; DELETE FROM stock_log;
```
   Tablas de turnos (`employee_*`, `shift_swap_requests`) y tareas solo si contienen pruebas.

## 6. Huecos detectados para operar con datos reales

| Hueco | Impacto | Propuesta |
|---|---|---|
| Sin alta de sucursales (UI/API) | dependes de SQL | CRUD en `/branches` (permiso `BRANCH_MANAGE`) |
| Sin alta de clientes | POS sin cliente nominado | CRUD en `/customers` |
| Semillas demo en migraciones | una instalación nueva nace con datos ficticios | condicionarlas a `MODE=demo` |
| Sin importación masiva (CSV) de productos, precios y saldo inicial | carga lenta | importador que llame a los mismos servicios (no `INSERT`) |
