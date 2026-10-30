# DAIRY — Auditoría técnica y funcional

Fecha: 2026-10-15 · Base: Grocy (master `41206cb9`) + capa DAIRY sin commitear (82 archivos).
Reutilizar este documento: re-auditar solo archivos con cambios posteriores (`git diff --stat`).

## 1. Estructura auditada

| Capa | Archivos DAIRY |
|---|---|
| Rutas | `routes.php` (vistas + API: waste, sales, kpi, suppliers, quality, mrp, workforce, simulator, markdown) |
| Controladores | Waste, Sales, Kpi, Branch, Ai, SupplierProcurement, QualityExpiry, Workforce (+ 9 `Api/*`) |
| Servicios | Waste (1158 l), Kpi (1232), AkcalMetrics (1048), QualityExpiry (674), SupplierProcurement (629), AIAdvisor, Mrp (315), DairyInventory, Sales, Workforce, Branch, Markdown, Simulator, AutomationEngine |
| BD | migraciones 0258–0264: branches, waste_reasons/records/action_logs/action_plans, sales/sale_items/customers, roles/user_roles, suppliers_meta/prices/deliveries/incidents/returns, cold_chain_temperatures, quality_inspections, quarantine_items, stock_cycle_counts, markdown_rules; `locations.branch_id` |
| Vistas | waste_overview (1882 l), executive_dashboard (1193), suppliers_procurement (1134), quality_expiry (821), pos, waste_reasons/report/approvals, ai_advisor, reports_hub, workforce, sales_history, branches, branch_stock, customers |
| Diseño | `public/css/dairy_enterprise_theme.css` (tokens `--dairy-*`), `components/dairy_kpi_row.blade.php` |

## 2. Hallazgos (problema · prioridad · solución)

P1 = rompe datos/confianza · P2 = funcionalidad incompleta · P3 = diseño/orden

| # | Área | Problema (evidencia) | P | Solución |
|---|---|---|---|---|
| 1 | Mermas | `registerNewCase()` hace POST a `/api/waste/records` (solo existe GET) y `quickApproveCase()` a `/api/waste/records/{id}/approve` (ruta real `/api/waste/{id}/approve`). El `error:` muestra "registrado bajo protocolo local seguro" / "Caso aprobado en sistema": **fallos presentados como éxito** (`waste_overview.blade.php:1805-1840`) | P1 | Apuntar a `/api/waste/report` y `/api/waste/{id}/approve`; el error debe mostrar el error real |
| 2 | Mermas | Botones solo con `alert()` sin backend: Ejecutar acción, Bloquear lote, Plan rápido, Cerrar plan, Reclamo proveedor, Nuevo conteo, Guardar investigación (`waste_overview:391,910,1119,1150,1180,1275,1303,1852`) | P1 | Conectar a APIs existentes: `/waste/action-plans`, `/quality/quarantine`, `/procurement/incidents`, `/waste/cases/{id}/investigation`, `/stock/cycle-counts` |
| 3 | Trazabilidad | Merma desde cuarentena hace `UPDATE stock SET amount=...` directo, sin `stock_log` ni transacción Grocy (`QualityExpiryService.php:477`) | P1 | Usar `StockService::ConsumeProduct(..., spoiled=true)` como `WasteService:448` |
| 4 | Navegación | Paleta de comandos enlaza `/mrp`, `/simulator`, `/suppliers-procurement`: **rutas inexistentes (404)** (`layout/default.blade.php`) | P1 | Crear vista MRP o reapuntar a `/ai-advisor`, `/suppliers` |
| 5 | Control interno | `User::CheckPermission` falta en `KpiApiController` y `SimulatorApiController` (los otros 7 lo usan parcialmente); no hay control de que el aprobador ≠ quien reporta (`WasteService::ApproveWaste:671`) | P1 | Permisos por rol (roles/user_roles ya existen) y segregación de funciones en aprobación |
| 6 | Datos fijos | Selector de sucursal del header con 3 sucursales escritas a mano; badge "3 notificaciones críticas" fijo; precio por defecto `3.99` (`WorkforceService:168`); OC con `Math.random()` (`suppliers_procurement:1103`) | P1 | Leer `branches`, contar alertas reales (`/api/ai/alerts`), número de OC secuencial en BD |
| 7 | Migraciones | 0259–0262 insertan datos demo (sucursales, inspecciones, cuarentena, conteos) en migraciones productivas | P2 | Mover seeds a `DemoDataGeneratorService` |
| 8 | Mermas | Paginación de casos con páginas 1-7 fijas en HTML (`waste_overview:760-768`, luego JS la reemplaza) | P3 | Render solo vía JS o DataTables |
| 9 | MRP | Sin vista propia: MRP vive dentro de `ai_advisor`. Sin entrada en sidebar | P2 | Vista `/mrp` (lista de necesidades + OC sugeridas) |
| 10 | MRP | Demanda = promedio simple 30 días; si no hay ventas **inventa** VMD = min_stock/7 o 0.5 (`MrpService:89-93`) | P2 | Marcar "sin historia" y no sugerir compra automática; pronóstico con media móvil ponderada/estacional por día de semana |
| 11 | MRP | Stock disponible no descuenta lotes por vencer antes del lead time, cuarentena, ni suma OC abiertas/en tránsito (`MrpService:80-104`) | P1 | Disponible = stock libre − cuarentena − vence ≤ LT + OC abiertas |
| 12 | MRP | Stock de seguridad fijo = 2 días VMD; sobreinventario fijo > 25 días sin relación con vida útil (`MrpService:101,124`) | P2 | SS = z·σ·√LT por nivel de servicio; sobreinventario si cobertura > % de vida útil |
| 13 | MRP | Lead time desde `supplier_product_prices` o default 2; sin MOQ / múltiplo de empaque / calendario de entrega | P2 | Añadir campos al proveedor-producto |
| 14 | Duplicación | Rutas duplicadas `/quality` = `/quality-expiry`, `/suppliers` = `/procurement`; "Asesor IA" aparece **dos veces** en el sidebar | P3 | Dejar una ruta canónica (+redirect) y un solo ítem |
| 15 | Diseño | 49 colores hex distintos y 372 `style=""` inline en vistas DAIRY; 17 tamaños de fuente y 8 radios distintos; tokens `--dairy-*` existen pero no se usan; colores de Chart.js escritos por vista (`#10b981` y `#059669` mezclados como "éxito", `#ef4444`/`#dc2626` como "peligro") | P2 | Reemplazar inline por clases/tokens; helper JS único `DairyChartPalette`; escala tipográfica de 5 tamaños |
| 16 | Diseño | `dairy_kpi_row` solo en 7 vistas; executive_dashboard, pos, ai_advisor, workforce, sales_history usan KPIs propios; `<style>` embebido en executive_dashboard y waste_overview | P3 | Migrar a componentes; mover CSS al tema |
| 17 | Rendimiento | Vistas monolíticas (waste_overview 1882 l con 8+ pestañas cargadas a la vez); varias consultas por pestaña aunque no se abra | P2 | Cargar pestañas pesadas por AJAX al activarlas |
| 18 | UX | `alert()`/`confirm()` nativos para feedback | P3 | Usar `toastr`/`bootbox` ya incluidos en Grocy |
| 20 | Mermas | Conteos cíclicos registran la diferencia pero no ajustan el stock de Grocy (sin movimiento de ajuste) | P2 | Ajuste vía `StockService::InventoryProduct` tras aprobación del supervisor (Etapa 3/4) |
| 19 | Mermas | Formulario rápido del Centro de Control no envía foto; las causales con foto obligatoria rechazan el registro (ahora el error es visible) | P2 | Añadir carga de foto (`/api/waste/upload-photo`) o redirigir a `/waste/report` |

## 3. Comparación con SAP (solo funciones estándar documentadas)

| SAP | Capacidad estándar | DAIRY hoy | Falta |
|---|---|---|---|
| MM – Inventario | Movimientos con motivo (551 desguace, 701/702 diferencias de inventario), inventario físico (MI01/MI04/MI07) | Mermas con causal, conteos cíclicos, consumo vía StockService | Tipo de movimiento explícito por merma/ajuste; ajuste de conteo contabilizado como movimiento separado de merma |
| QM | Lotes de inspección, decisión de empleo, stock en control de calidad / bloqueado | Inspecciones, cuarentena, temperatura | Decisión de empleo formal (liberar/merma/devolver) ligada al lote; stock bloqueado excluido de venta y MRP en todos los cálculos |
| Gestión de lotes | Fecha de caducidad (SLED), vida útil mínima remanente en recepción | `best_before_date` de Grocy, FEFO en consumo | Rechazo en recepción si vida útil remanente < mínimo por producto |
| EWM | Estrategias FEFO de picking, ubicaciones | FEFO en venta (StockService), ubicaciones con branch_id | Sugerencia de rotación/reubicación por ubicación |
| CO | Centros de coste, imputación de costes de desguace, análisis de rentabilidad | Costo unitario y total por merma, KPIs por sucursal | Centro de coste por sucursal/área; merma como % de venta por categoría (CO-PA simplificado) |
| MRP | Planificación por punto de pedido / basada en pronóstico, stock de seguridad, tamaño de lote, MD04 (lista de stock/necesidades) | ROP + SS fijo + tope por vida útil, OC sugeridas | Pronóstico, entradas planificadas (OC abiertas), stock no disponible, tamaño de lote/MOQ, vista tipo MD04 |

## 4. Mermas — cobertura actual vs. objetivo

| Elemento | Estado |
|---|---|
| Causales (operativa/comercial/avería/caducidad, censo FENALCO) | ✔ `waste_reasons.shrink_type/censo_category` |
| Costeo, aprobación, foto evidencia, bitácora | ✔ backend · ✘ UI de Centro de Control parcialmente rota (#1, #2) |
| Ajustes de inventario (conocida vs. desconocida) | Parcial: conteos existen, ajuste no se separa como movimiento |
| Trazabilidad por lote | Parcial (#3 rompe el journal) |
| Análisis producto/lote/sucursal | ✔ AkcalMetrics/Kpi · selector de sucursal del header es fijo (#6) |
| Alertas preventivas (por vencer, rebaja FEFO, frío) | ✔ markdown_rules, cold chain · badge de alertas falso (#6) |
| Planes de acción | ✔ API · botones UI sin conectar (#2) |

## 5. Navegación propuesta (sidebar)

1. **Panel ejecutivo** — /kpis
2. **Mermas** — Centro de control (/waste) · Registrar (/waste/report) · Aprobaciones (/waste/approvals) · Causales (/waste/reasons)
3. **Calidad y vencimientos** — /quality (FEFO, cuarentena, cadena de frío)
4. **Inventario** — Stock (/stockoverview) · Conteos (/inventory) · Sucursales (/branches)
5. **Planeación (MRP)** — /mrp (nuevo) · Proveedores (/suppliers) · Simulador
6. **Operaciones** — Piso de tienda (/workforce) · POS (/pos) · Ventas (/sales) · Clientes
7. **Reportes** — /reports
8. **Asesor IA** — /ai-advisor (una sola vez)

## 6. Plan de implementación por etapas

| Etapa | Alcance | Hallazgos | Riesgo |
|---|---|---|---|
| 1. Integridad ✅ 2026-10-15 | Corregir endpoints y errores enmascarados; merma de cuarentena vía StockService; enlaces 404 de paleta; quitar datos fijos del header | 1, 3, 4, 6 | Bajo |
| 2. Botones conectados ✅ 2026-10-15 | Enlazar acciones de waste_overview a APIs existentes; toastr en vez de alert | 2, 8, 18, 19 | Bajo |
| 3. Control interno ✅ 2026-10-15 | Permisos en APIs DAIRY; aprobador ≠ reportante; seeds fuera de migraciones | 5, 7 | Medio |
| 4. MRP ✅ 2026-10-15 | Disponible neto, SS por nivel de servicio, sobreinventario por vida útil, sin demanda inventada, vista /mrp | 9–13 | Medio |
| 5. Diseño unificado ✅ 2026-10-15 | Tokens en vistas, paleta única de gráficos, `dairy_kpi_row` en todas, CSS embebido al tema | 15, 16 | Bajo (visual) |
| 6. Navegación y rendimiento ✅ 2026-10-15 | Sidebar §5, rutas canónicas, pestañas por AJAX | 14, 17 | Bajo |

## 7. Registro de cambios

**Etapa 1 (2026-10-15)** — probado con servidor local (respaldo de la BD demo restaurado tras las pruebas):
- #1 `waste_overview`: endpoints `/api/waste/report` y `/api/waste/{id}/approve`; los errores muestran `error_message` real.
- #3 `QualityExpiryService::ResolveQuarantine`: baja vía `StockService::ConsumeProduct` (stock_log + transaction_id), costo real desde stock_log, transacción con rollback; con lote específico la ubicación la define el lote.
- #4 Paleta: `/mrp`→`/ai-advisor#tab-mrp`, `/simulator`→`/ai-advisor#tab-simulator`, `/suppliers-procurement`→`/suppliers`; ai_advisor abre la pestaña por hash.
- #6 Header: sucursales desde `branches`, usuario/rol reales (`GROCY_USER_USERNAME`, `user_roles`), período = mes actual, badge = alertas `danger` de `/api/ai/alerts`.
- #6 `WorkforceService::QuickWasteReport`: costo = promedio ponderado del stock o último precio de compra (antes 65 % de precio venta / 3.99); corregido `qu_id` NOT NULL que hacía fallar el reporte rápido.
- #6 OC: número secuencial `OC-AAAA-NNNNN` asignado en servidor (antes `Math.random()`).

**Etapa 2 (2026-10-15)** — probado con servidor local (BD demo y foto de prueba restauradas):
- `waste_overview`: 0 `alert()`; feedback con toastr y confirmaciones con bootbox. Helper reutilizable `dairyFormDialog` + `dairyPost`; maestros en `DairyMaster` (productos, causales, usuarios, proveedores, ubicaciones).
- Conectados: Nuevo plan / Cerrar plan (`/api/waste/action-plans`), Bloquear lote → cuarentena (`/api/quality/quarantine`), Programar conteo (nuevo `POST /api/stock/cycle-counts`) y Registrar conteo (`/execute`), Cerrar caso e Investigación 5 porqués (`/api/waste/cases/{id}/investigation`), Nueva causal → `/waste/reasons`, acciones de rebaja → pestaña Prevención (la rebaja FEFO la aplica el POS).
- Reclamos a proveedor: nuevos `POST /api/waste/supplier-returns` y `/{id}/credit` (no existía forma de crearlos); costo desde stock real.
- Registro rápido: sube la foto (`/api/waste/upload-photo`) antes de reportar (#19); lote ya no se precarga con `rand()`.
- Corregido: tablas de Planes, Conteos y Recuperación leían propiedades inexistentes sobre arrays (celdas vacías); lista "Casos en investigación" y modal 5 porqués ya no son datos fijos; paginación sin HTML fijo; CSV con fecha real; nombres con comillas ya no rompen los `onclick`.
- Backend: costo de diferencias de conteo a precio de costo (antes precio de venta o 2.5); `qu_id` de cuarentena desde el producto (antes 3 fijo).
- Pendiente menor: `confirm()` nativo en `suppliers_procurement` (2) y `waste_reasons` (1).

**Etapa 3 (2026-10-15)** — probado con servidor local (BD demo restaurada; la migración 0265 se aplica al próximo arranque):
- Migración `0265.php`: los 17 permisos DAIRY se registran en `permission_hierarchy` (grupos WASTE, SALES, PROCUREMENT, QUALITY + KPIS_VIEW, BRANCH_MANAGE, INVENTORY_ADJUST_APPROVE). Antes no existían: solo los cumplía ADMIN y no se podían asignar. Traducciones en `localization/es/permissions.po`. Columna `stock_cycle_counts.adjustment_transaction_id`.
- `CheckPermission` añadido en: investigación de casos, planes de acción, reclamos/NC (WASTE_APPROVE), foto (WASTE_REPORT), conteos (STOCK_INVENTORY), tareas de piso (TASKS_MARK_COMPLETED), KPI ejecutivo y simulador (KPIS_VIEW).
- Segregación de funciones: quien reporta una merma no puede aprobarla ni rechazarla; quien ejecuta un conteo no puede aprobar su ajuste. Configurable con `Setting('WASTE_ALLOW_SELF_APPROVAL', false)` en `config-dist.php`.
- #20 Ajuste por conteo: nuevo `POST /api/stock/cycle-counts/{id}/approve-adjustment` (INVENTORY_ADJUST_APPROVE) contabiliza la diferencia con `StockService::InventoryProduct` (tipo `inventory-correction`, no `spoiled`): ajuste separado de la merma. Botón "Aprobar ajuste" en Conteos; estado `adjusted` sigue contando como descuadre en el IRA.
- #7 Migraciones: datos transaccionales de demostración de 0260 (precios, entregas, incidencias, temperaturas, inspecciones, cuarentena) y 0262 (conteos, devoluciones, planes) solo se cargan con `MODE` demo/prerelease. Los maestros (sucursal, causales, roles, cliente genérico, unidades, ubicaciones, reglas de rebaja, proveedores) se mantienen. Instalaciones ya migradas no cambian.
- Nota: todos los usuarios de la BD demo tienen ADMIN; para probar permisos, quitar ADMIN a un usuario en Usuarios → Permisos.

**Etapa 4 (2026-10-15)** — probado con servidor local (BD demo restaurada):
- `MrpService::CalculateMrp` reescrito, por sucursal (stock vía `locations.branch_id`, ventas vía `sales.branch_id`; `branch_id` vacío/0 = red). Antes sumaba el stock de todas las sucursales.
  - Demanda: media móvil ponderada de 4 semanas + σ diaria de 28 días. Sin ventas ya no se inventa VMD: estado `no_history`, repone solo hasta `min_stock_amount`.
  - Disponible neto = stock − vencido − cuarentena − **merma proyectada FEFO** en el horizonte + órdenes MRP abiertas (borrador/aprobadas).
  - SS = z·σ·√LT (nivel de servicio `MRP_SERVICE_LEVEL`, def. 0.95), mínimo el stock mínimo. Pedido = VMD·(LT + `MRP_REVIEW_PERIOD_DAYS`) + SS − neto, tope 80 % de vida útil, redondeo a MOQ y `order_multiple`.
  - Sobreinventario: cobertura > 80 % de la vida útil (25 días si no hay vida útil).
  - Proveedor: precio vigente más barato; si no, proveedor por defecto del producto. Costo: precio proveedor o costo real del stock (antes 65 % del precio de venta, proveedor 1 y nombre fijos). Advertencias por producto (sin proveedor, sin lead time, MOQ > vendible en vida útil, stock por vencer sin ventas).
- `GenerateOrdersFromMrp` + `POST /api/mrp/orders/generate`: una orden borrador por proveedor (ai_advisor enviaba `supplier_id: 1` fijo para todo). `CreateProcurementOrder` exige proveedor.
- Migración `0266.php`: crea `procurement_orders`, `procurement_order_items`, `automation_rules`, `workforce_store_tasks` (solo existían vía `scratch/migrate_enterprise_tables.php`) y `supplier_product_prices.order_multiple`.
- Vista nueva `/mrp` (`MrpController`, `views/mrp.blade.php`): KPIs estándar, lista de necesidades (tipo MD04), órdenes con aprobación; entrada en sidebar y paleta.
- Datos demo: 20 de 22 productos sin ventas en 28 días y 17 sin proveedor/lead time → completar maestros para que el MRP sea útil.
- Pendiente (Etapa 6): la pestaña MRP de `ai_advisor` duplica la vista `/mrp`.

**Etapa 5 (2026-10-15)** — verificado: 22 vistas renderizan 200 sin errores PHP/JS; capturas de Panel Ejecutivo, Mermas, MRP y Gastos (Grocy).
- `:root` del tema = paleta única: 6 tonos (primary, info, success, warning, danger, purple) × {color, -dark, -bg, -border} + neutros (surface, bg-base, bg-page, border-subtle/border/border-strong, text-main/body/secondary/muted/subtle), escala tipográfica `--dairy-fs-2xs…2xl` (10, 11, 12, 13, 14, 16, 20, 24 px) y radios `--dairy-radius-xs…full`. `--dairy-info` pasa a cian (#0891b2) para distinguirlo de primary.
- Unificador (scratchpad `unify.py`) sobre 28 vistas modificadas + `mrp`, componente KPI y los 2 CSS: 382 colores → `var(--dairy-*)`, 272 `font-size` y 96 `border-radius` → escala; en JS/PHP los 33 alias se normalizan al hex canónico. Resultado: 0 colores fuera de paleta (antes 49 en vistas + 58 en CSS), 2 tamaños literales restantes.
- `<style>` embebidos de `executive_dashboard` y `waste_overview` movidos al final del tema.
- `public/js/dairy_charts.js` (cargado con chartjs antes de los scripts de página): `DairyPalette` desde los tokens, fuente Inter, grilla/tooltip del tema, **animaciones 0** (sin parpadeo), colorschemes → paleta DAIRY; el plugin outlabels solo actúa en gráficos `outlabeled*` (dibujaba líneas sueltas en las donas).
- Sin desplazamientos en hover: eliminados 17 `translateY/scale` en `:hover/:active` (se conservan sombra/color).
- KPIs: `dairy_kpi_row` usa tokens; las 4 tarjetas hero del Panel Ejecutivo adoptan el acento inferior estándar (`dairy-kpi-uniform`) y conservan sus sparklines. Las demás vistas sin fila KPI no la necesitaban.
- Categorías de merma con 8 colores distintos de la paleta (antes 3 morados casi iguales); íconos de producto en paleta.
- Pendiente (no bloqueante): 410 atributos `style=""` inline siguen existiendo (ya con tokens); convertirlos a clases sería un refactor amplio sin cambio visual.

### Etapa 6 — Navegación y rendimiento (2026-10-15)
- Sidebar según §5 (`layout/default.blade.php`): arreglo `$dairyNav` con secciones (Mermas, Calidad, Inventario, Planeación, Operaciones, Análisis) y un ítem por destino; "Asesor IA" ya no está duplicado. Etiquetas `.nav-section-label` (en modo colapsado se ven como divisor). Clientes y Causales siguen en Datos maestros (catálogos), sin duplicarse.
- Rutas canónicas: `/quality-expiry` → 301 `/quality` y `/procurement` → 301 `/suppliers` (conservan query string). Paleta apunta a `/quality`.
- `ai_advisor`: la pestaña MRP (duplicaba `/mrp`) es ahora un enlace a `/mrp`; se quitó su JS.
- `waste_overview`: pestañas Planes, Recuperación, FEFO, Conteos y Auditoría se cargan por AJAX al activarse (`GET /waste/tab/{tab}`, `WasteController::Tab`, parciales en `views/components/waste_tabs/`); sus consultas ya no corren al abrir la página. El Centro de Control (pestaña por defecto, ~195 KB de HTML) sigue renderizándose en el servidor.
- Período por defecto de `/waste`: mes actual (antes fijo 2026-10-01..31).
- Verificado: 17 vistas 200, 5 pestañas AJAX 200 (404 en nombre inválido), JS sin errores de sintaxis, captura del sidebar OK. `/waste` ~145 ms.

### Etapa 7 — Cierre de pendientes (2026-10-15)
- `confirm()` nativos → `bootbox.confirm`: `suppliers_procurement` (activar/desactivar proveedor, orden rápida) y `waste_reasons` (activar/desactivar causal). La orden rápida ya no muestra éxito si la API devuelve error.
- Estilos inline: 391 declaraciones pasadas a clases (Bootstrap o utilidades `.dairy-fs-*`, `.dairy-radius-*`, `.dairy-c-*`, `.dairy-bg-*`, `.dairy-lh-*`, `.dairy-gap-*`, `.dairy-bordered`, `.dairy-tab-link` al final del tema); 201 atributos `style` eliminados, quedan 165 (medidas puntuales y valores con Blade). Se descartaron declaraciones "muertas" que el tema ya sobrescribía con `!important` (botones, tarjetas, selects). Verificado por estilos computados vía CDP en 16 vistas + 5 pestañas diferidas: 0 diferencias.
- `waste_overview`: tras una acción (`dairyPost`) se recarga solo la pestaña activa si es diferida; en las demás se recarga la página y vuelve a la misma pestaña (`#tab-…`). El hash abre cualquier pestaña.


## 8. Re-auditoría 2026-10-15 (tras etapas 1–7)

Sin archivos modificados después de la etapa 7; se revisaron solo los objetivos no cubiertos (integración de compras, documentos, transferencias, responsive). No se modificó código.

| # | Área | Hallazgo (evidencia) | P | Propuesta |
|---|---|---|---|---|
| 21 | Compras → Inventario | `RecordDelivery` solo inserta en `supplier_deliveries` (KPI de proveedor); **no** crea entrada de stock (`SupplierProcurementService.php:409`). La compra real se hace aparte en `/purchase` de Grocy: doble digitación y sin vínculo OC↔recepción↔lote | P1 | Recepción contra OC: por línea, cantidad, lote, vencimiento, temperatura → `StockService::AddProduct` (tipo purchase) con `transaction_id` guardado en la recepción |
| 22 | Ciclo de OC | `procurement_orders` solo tiene `draft/approved/cancelled` (`MrpService.php:86,97`); sin `sent`, `partially_received`, `received`, `closed`. El MRP cuenta como "entrada planificada" OC que nunca se cierran | P1 | Estados completos; la recepción cierra la OC y deja de sumar en el MRP |
| 23 | Recepción y mermas | No hay control de vida útil remanente mínima ni rechazo por temperatura en la recepción (la temperatura se guarda pero no bloquea) | P2 | Regla por producto (días mínimos, rango °C); rechazo → incidencia/devolución automática al proveedor |
| 24 | Transferencias | Entre sucursales solo existe `TransferProduct` de Grocy entre ubicaciones (sucursal = `locations.branch_id`): sin documento de envío/recepción, sin confirmación del destino ni diferencias en tránsito | P2 | Documento de traslado (origen, destino, estado enviado/recibido, diferencia → merma "en tránsito") |
| 25 | Comprobantes | Solo ventas tienen comprobante imprimible (`pos`, `sales_history`). Mermas, cuarentenas, ajustes de conteo, reclamos y OC no tienen documento numerado ni impresión; solo OC tiene folio (`OC-AAAA-NNNNN`) | P2 | Folio único por tipo (MER-, AJU-, TRA-, REC-, NC-) + vista imprimible común que muestre usuario, aprobador, lote, costo y `transaction_id` |
| 26 | Historial unificado | La trazabilidad está repartida: `stock_log`, `waste_action_logs`, `sales`, `supplier_deliveries`. No hay "kardex por lote" que una compra → traslado → venta/merma | P2 | Vista Kardex por producto/lote sobre `stock_log` + documentos DAIRY enlazados por `transaction_id` |
| 27 | Responsive | Tema con 4 `@media`/`@container`; tablas con `table-responsive` OK. Sin revisión visual en móvil de waste_overview (8 pestañas) ni POS | P3 | Validar en 375 px con capturas antes de cerrar |

### Comparación SAP adicional
| SAP | Falta en DAIRY |
|---|---|
| MIGO 101 (entrada de mercancía con referencia a OC) | #21, #22 |
| MIGO 303/305 (traslado en dos pasos entre centros) | #24 |
| Documento de material numerado por movimiento | #25 |
| MB51 / MMBE (lista de documentos, resumen de stock por centro) | #26 |
| QM en recepción (lote de inspección 01) | #23 |

### Etapas propuestas
| Etapa | Alcance | Hallazgos |
|---|---|---|
| 8. Ciclo de compras ✅ 2026-10-15 | Estados de OC, recepción contra OC con entrada de stock, reglas de recepción | 21, 22, 23 |
| 9. Documentos ✅ 2026-10-15 | Folios por tipo, vista imprimible común, traslados en dos pasos | 24, 25 |
| 10. Kardex ✅ 2026-10-15 | Historial por producto/lote enlazado a documentos | 26, 27 |

### Etapa 8 — Ciclo de compras (2026-10-15)
Probado con script CLI sobre copia de la BD demo (24 verificaciones OK) + render de `/mrp` y `/suppliers` (200, JS sin errores). La BD real solo recibió la migración 0267.
- Migración `0267.php`: `procurement_orders` + `expected_delivery_date, sent_at/by, closed_at/by, close_reason`; `procurement_order_items` + `received_amount, rejected_amount`; tablas `procurement_receipts` (folio `REC-AAAA-NNNNN`), `procurement_receipt_items`, `product_receiving_rules`.
- Estados de OC: borrador → aprobada → enviada (fecha comprometida) → recepción parcial → recibida; cancelar (borrador/aprobada) o cerrar con faltante (enviada/parcial), siempre con motivo.
- `MrpService::ReceiveProcurementOrder` (#21): recepción contra OC por línea (entregado, rechazado, motivo, vencimiento, ubicación de la sucursal, costo). Lo aceptado entra con `StockService::AddProduct` tipo `purchase` (una transacción por recepción, nota `REC-… / PO-…`, proveedor como tienda); lo rechazado no toca el stock y abre `supplier_incidents` (tipo `cold_chain_break`, `near_expiration` o `reception_rejection`) con costo. Crea la fila OTIF en `supplier_deliveries` (a tiempo/atrasada/parcial y calificación) — ya no se digita aparte. No permite recibir más de lo pendiente.
- #23 Reglas de recepción por producto (vida útil remanente mínima, rango °C); sin regla se exige `RECEIVING_MIN_SHELF_LIFE_PCT` (0.5) de la vida útil por defecto. Incumplir → rechazo automático de la línea.
- #22 MRP: entradas planificadas = pendiente (aprobado − recibido − rechazado) de órdenes borrador/aprobada/enviada/parcial.
- Corregido (Etapa 4): `ServiceLevelZ` usaba claves float (PHP las trunca a 0) → z siempre 2.33; ahora respeta el nivel de servicio (0.95 → 1.65).
- `/mrp`: acciones Aprobar/Enviar/Recibir/Cancelar/Cerrar, modal de recepción, tabla "Recepciones de mercancía", modal "Reglas de recepción". API: `GET /api/mrp/order/{id}`, `POST …/send|close|receive` (recibir exige `PROCUREMENT_RECEIVE`; el resto `PROCUREMENT_MANAGE`), `GET/POST /api/mrp/receiving-rules[/{productId}]`.
- `/suppliers`: "Ordenar" crea una OC borrador real (antes una fila de entrega ficticia); botones "Recepción" llevan a `/mrp`; eliminado el formulario de entrega manual. Recomendaciones sin cotización ya no inventan proveedor 1 / $3.00 / rating 4.8: usan proveedor por defecto y último costo de compra, o muestran "Sin proveedor".
- Nota operativa: tras cambiar `routes.php` hay que borrar `data/viewcache/route_cache.php` (Slim no la regenera; rutas nuevas caían en otros controladores).
- Pendiente detectado (#28): la pestaña de recomendaciones de `/suppliers` es un segundo motor de reposición (máx/mín, cantidades fijas 50/20) que duplica el MRP → unificar con `MrpService` en una etapa futura.

### Etapa 9 — Documentos y traslados (2026-10-15)
Probado con scripts CLI sobre copia de la BD (traslados 20 verificaciones, documentos de los 7 tipos) + render en servidor (`/transfers`, `/mrp`, `/waste`, pestañas diferidas, `/quality`, `/document/*`: 200, JS sin errores; 404 en tipo/id inválido) y capturas. La BD real solo recibió la migración 0268 (respaldo `grocy_es.db.bak9` en scratchpad).
- Migración `0268.php`: `locations.is_transit` + ubicación "En tránsito (traslados)" sin sucursal; tablas `stock_transfers` (folio `TRA-AAAA-NNNNN`) y `stock_transfer_items` (por lote).
- #24 `StockTransferService` + vista `/transfers` (sidebar Inventario y paleta): envío (lotes FEFO del origen, sin cuarentena → tránsito con `TransferProduct`), recepción por producto (repartida FEFO; lo recibido pasa al destino) y anulación (vuelve al origen). La diferencia se da de baja desde tránsito como merma `MRM-…` causal M07 con su transacción `spoiled`. Solo entre sucursales distintas (dentro de la misma, Transferir de Grocy). KPIs: en tránsito, valor, > 48 h, diferencias del mes. API `/api/transfers[/{id}[/receive|/cancel]]` con permiso `STOCK_TRANSFER`.
- El stock en tránsito no cuenta en ninguna sucursal (MRP por sucursal, ventas); sí en la red consolidada.
- #25 `DocumentService` + `/document/{type}/{id}` (`views/dairy_document.blade.php`, página independiente con impresión/PDF): OC (`PO-`), recepción (`REC-`), traslado (`TRA-`), merma (`MRM-`), conteo/ajuste (`CNT-`/`AJU-`), cuarentena (`CUA-`), devolución/NC (`DEV-`). Muestra partes, líneas, totales, firmas (elaboró/aprobó/recibió con fecha), transacciones de stock y documentos enlazados (OC ↔ REC, TRA → MRM, CUA → MRM). Botón imprimir en `/mrp` (órdenes y recepciones), `/transfers`, casos de `/waste`, Conteos, Recuperación y cuarentenas de `/quality`.
- Formulario de ubicaciones (Grocy): campo **Sucursal** (antes no había forma de asignar `branch_id`). Sucursal Norte y Poniente no tienen ubicaciones: crearlas para poder trasladar.
- Corregido en `SalesService::CreateSale` (P1): la cuarentena nunca bloqueaba ventas (buscaba `status = 'active'`, el real es `in_quarantine`); sin ubicación, la venta consumía de cualquier sucursal. Ahora consume FEFO lote a lote solo de ubicaciones de su sucursal (nunca tránsito), excluye lotes retenidos (por lote y ubicación) y falla si no alcanza el stock libre.
- Hallazgo #29 (datos demo, no corregido): 18 `waste_records` ($631.95) y 35 `sale_items` apuntan a productos eliminados; las consultas con `JOIN products` los omiten (KPIs subestiman pérdidas). Los documentos los muestran como "Producto #id (eliminado)". Decidir si se reasignan o eliminan.

### Etapa 10 — Kardex, paso a paso y cierre de pendientes (2026-10-15)
Probado con scripts CLI sobre copia (etapas 8, 9 y 10 completas: saldos del kardex = stock por red y por sucursal, documentos enlazados, recomendaciones = MRP) + render en servidor (16 páginas 200, JS sin errores) y capturas. BD real: migraciones 0269–0270 (respaldo `grocy_es.db.bak10`).
- #26 `KardexService` + vista `/kardex` (sidebar Inventario): por producto, sucursal (solo sus ubicaciones; tránsito excluido), lote y período. Saldo inicial, entradas, salidas, merma, saldo final valorizado, saldo corriente por línea, usuario y documento que originó el movimiento (REC, TRA, MRM, AJU, venta `VTA-`). Clic en un lote = recorrido completo del lote. Exportar CSV.
- Migración `0270.php`: `sale_items.transaction_id` (la venta guarda la transacción de stock que consumió) + índices.
- Paso a paso: componente `components/dairy_process_steps` (estilos `.dairy-step*` en el tema, vertical en móvil). Cada documento imprimible muestra su flujo con el paso actual (OC 5 pasos, REC 4, TRA 4, MRM 5, AJU 4, CUA 3, DEV 3; `DocumentService::ProcessSteps`). Guías con pendientes reales en `/mrp` (ciclo de compra) y `/transfers`; guías en Registrar merma y Calidad.
- #28 Resuelto: las recomendaciones de `/suppliers` usan `MrpService::CalculateMrp` (un solo motor; antes máx/mín con cantidades fijas 50/20 y proveedor inventado).
- #29 Resuelto sin borrar datos: migración `0269.php` recrea los productos eliminados (#152, #159, #171) como inactivos "Producto #id (histórico)"; 18 mermas ($631.95) y 35 líneas de venta vuelven a contar en los KPIs.
- Datos inventados eliminados: OTIF 90 % por defecto (KPI y reportes muestran "—"/"sin entregas registradas"; por proveedor "Sin datos" en vez de 100 %), SKU ficticio `DAIRY-SKU-…` y ubicación "Cámara Frío Principal" en mermas/FEFO.
- Barrido de funciones: 0 `alert()`/`confirm()`, 0 enlaces sin acción, 0 `Math.random` en lógica (solo UUID de idempotencia).

### Etapa 11 — Permisos por rol, centros de costo, tablero del gerente y móvil (2026-10-15)
Verificado: páginas nuevas y existentes 200 en servidor, permisos con usuario de prueba sin ADMIN (copia de BD), capturas escritorio y 375 px; ninguna página con scroll horizontal a 375 px. Sin migraciones nuevas.
- **Permisos por rol**: `helpers/DairyAccess.php` (mapa página → permisos, basta uno; ADMIN todo) + `middleware/DairyPermissionMiddleware.php` (registrado en `app.php` tras auth) devuelve 403 en páginas sin permiso; el sidebar oculta lo no permitido (y secciones vacías); documentos imprimibles con permiso por tipo. `/mrp` muestra Aprobar/Enviar/Cerrar/Generar/Reglas solo con `PROCUREMENT_MANAGE` y Recibir solo con `PROCUREMENT_RECEIVE`. Las APIs ya validaban cada acción. Nota: en Grocy un permiso padre (p. ej. STOCK) incluye sus subpermisos.
- **Centros de costo** `/cost-centers` (Análisis; `CostCenterService`, `KpiController::CostCenters`): por mes, cada sucursal = centro de costo con ventas, CMV real, margen, merma aprobada, merma % venta, Δ en puntos vs mes anterior y margen tras merma; detalle por categoría (red o sucursal), ordenado por merma; marca "sin venta" cuando hay merma sin ventas.
- **Tablero del gerente** `/today` "Hoy" (primer ítem del menú; `ManagerAgendaService`): tarjetas solo con pendientes reales, ordenadas por urgencia e impacto — vencidos en stock, vence en 3 días, mermas por aprobar, ajustes de conteo, OC atrasadas / borradores / por recibir, traslados > 48 h, cuarentenas, investigaciones vencidas, notas de crédito, tareas de piso. Cada línea enlaza su documento y cada tarjeta su página (respeta permisos y `?branch_id` del selector de sucursal). KPIs: decisiones, valor en riesgo, merma de hoy, merma del mes % venta, margen tras merma.
- **Móvil (#27)**: medición automática por CDP a 375 px en 11 páginas. POS: categorías en una fila deslizable, lista acotada y barra fija "Ir al carrito" con el total; carrito sin sticky en móvil. Mermas: fila de 8 categorías deslizable (antes valores truncados). Las barras de pestañas ya se deslizaban.

### Etapa 12 — Experiencia móvil nativa y PWA (2026-10-15)
- Navegación: `components/dairy_mobile_nav` (barra inferior < 768 px: Hoy · Mermas · Escanear · Inventario · Más; hoja "Accesos rápidos" con todos los módulos de `$dairyNav` filtrados por `DairyAccess`). Drawer con fondo, cierre al navegar/deslizar.
- Escáner: cámara (ZXing, carga diferida, linterna) + ingreso manual → `/api/stock/products/by-barcode` → acciones según permisos (merma, consumir, recibir, conteo, kardex, lotes).
- PWA: `public/sw.js` (estáticos en caché; páginas y API siempre en red, aviso sin conexión), manifest con scope, shortcuts y theme-color; metas iOS/Android, `viewport-fit=cover`.
- Notificaciones: alertas críticas de `/api/ai/alerts` vía service worker, activables desde "Más"; sin repetir las ya avisadas.
- Correcciones: `/waste/report?product_id=` ahora preselecciona (enlaces de Calidad estaban rotos); foto con `capture="environment"`; campana del header navega a /today fuera de Mermas; `/workforce` usaba `render` sin `userSettings` (errores JS).
- CSS (sección "MÓVIL Y TÁCTIL" del tema): pestañas deslizables, DataTables apiladas, modales con scroll, objetivos táctiles ≥ 40 px, inputs 16 px, safe-area, reduced-motion, modo nocturno.
- Verificado con CDP: 26 páginas a 375 px sin desbordes; 1366/820 px sin barra inferior ni cambios; escáner, hoja y drawer probados.

## 9. Benchmark ERP (12 plataformas) y Etapa 13 (2026-10-15)

Capacidades de referencia (SAP S/4HANA, Oracle Retail, Dynamics 365, Odoo, RELEX, Blue Yonder, Crisp, SafetyCulture, UKG, Deputy, MarketMan, WISK) agrupadas; estado verificado por código y `tests/dairy/run.php`.
Estados: ✔ existe y funciona · ◐ incompleta · ✚ implementada en esta etapa · ✘ no existe.

| Capacidad (referencia) | Estado | Dónde |
|---|---|---|
| Movimientos de material, kardex inmutable, valoración (SAP MM, Odoo) | ✔ | stock_log Grocy, `/kardex`, documentos `/document/*` |
| Mermas con causales, foto, aprobación con segregación, bitácora, 5 porqués (Odoo scrap, MarketMan) | ✔ | `/waste*`, WasteService |
| Merma aprobada → tarea correctiva + alerta al gerente + reglas de automatización | ✚ | `WasteService::AfterApproved`, `/today` tarjeta "Acciones correctivas", AutomationEngine conectado (evento `waste_approved`) |
| Merma por turno (contexto, sin atribución) | ✚ | `waste_records.shift_id`, `/schedule` → Cobertura "Merma por franja" |
| Métricas: % sobre base configurable, operativa vs comercial, recuperado y pérdida neta, presupuesto y desvío, interanual | ✚ | `KpiService`, `BudgetService`, Panel Ejecutivo, `/cost-centers#budgets` |
| Presupuesto mensual por centro de costo (SAP CO, Dynamics) | ✚ | tabla `dairy_budgets`, `POST /api/budgets` (BUDGET_MANAGE) |
| Recepción contra OC con lote/vencimiento/temperatura, OTIF (SAP MIGO, MarketMan) | ✔ | `/mrp` |
| Diferencia pedido–factura–recepción (3-way match) | ✘ | sin entidad factura de proveedor |
| Devoluciones a proveedor / notas de crédito | ✔ | supplier_returns (recuperado suma en pérdida neta) |
| Traslados en dos pasos con diferencias (SAP 303/305, Oracle) | ✔ | `/transfers` |
| Conteos y ajustes separados de merma (WISK, Odoo) | ✔ | Conteos + aprobación de ajuste |
| Conteo cíclico programado por ABC | ◐ | ABC calculado en KPIs; programación manual |
| FEFO, cuarentena, cadena de frío, inspecciones (SafetyCulture, SAP QM) | ✔ | `/quality` |
| MRP retail: ROP, SS por nivel de servicio, OC abiertas, tope por vida útil (SAP MRP, RELEX) | ✔ | `/mrp` |
| Precisión de pronóstico WAPE/sesgo + quiebres actuales (RELEX, Blue Yonder) | ✚ | `MrpService::GetForecastAccuracy`, `/mrp` |
| Estacionalidad en el pronóstico | ✘ | media móvil ponderada 4 semanas |
| Rentabilidad por producto: margen tras merma, sell-through, días de cobertura reales (Crisp, MarketMan) | ✚ | Panel Ejecutivo (tabla ABC/BCG) |
| Personal: empleados, turnos, disponibilidad, ausencias, cambios, fichaje, horas program./trabajadas/extra, cobertura por hora, productividad, calendario día/semana/mes, vista móvil (UKG, Deputy) | ✚ | `/schedule`, `WorkforceScheduleService`, `/api/schedule/*` |
| Arrastrar y soltar turnos (Deputy) | ✚ | escritorio/tablet con puntero; en táctil se edita con toque |
| Arturito: preguntas sobre datos reales (por qué subió la merma, anomalías μ+2σ, críticos, reposición, acciones, sucursales, periodos), separando observado / estimado / recomendación | ✚ | `AdvisorQaService`, `POST /api/ai/ask`, `/ai-advisor` |
| Redacción con LLM | ✘ | requiere `anthropic-ai/sdk` (Composer) y API key; el motor actual es determinístico y no inventa cifras |
| Precios y promociones generales (Oracle Retail) | ◐ | solo rebajas FEFO (`markdown_rules`) |
| Restricción de datos por sucursal del usuario | ✘ | permisos por rol sí; filtro por sucursal asignada no |
| RBAC | ✔ | `DairyAccess` + permisos nuevos WORKFORCE_SCHEDULE_MANAGE, WORKFORCE_ATTENDANCE_MANAGE, BUDGET_MANAGE |
| PWA / móvil / escáner | ✔ | Etapa 12 |

### Etapa 13 — registro de cambios
- **Datos inventados eliminados (P1)**: sparklines (porcentajes fijos del total) → 7 tramos reales; delta de rotación 4.2 % y sparkline de rotación fijos → eliminados; "evitable 72 % / no evitable 28 %" → merma real por tipo de causal; "cuentas por pagar = 75 % del CMV" → eliminado; metas 2.5 % / 38 % / 400 $/día / +8 % fijas → presupuesto o `config-dist.php`; "Sector lácteos > 1.2x" y "Benchmark 15–25 días" sin fuente → meta DSI configurable.
- **Corregidos**: DSI usaba ×30 en cualquier período; inventario valorizado y merma del período anterior ignoraban la sucursal; días de stock por producto usaban 14 días fijos y stock de toda la red; `AutomationEngineService` no se llamaba, creaba tareas tipo `cold_chain_check` fijo y cuarentenas con estado inválido y 10 u. supuestas.
- Migraciones **0271** (presupuestos, personal/turnos, columnas de origen en tareas, permisos) y **0272** (`waste_records.shift_id`): solo agregan tablas/columnas.
- Nuevos ajustes: `WASTE_TARGET_PCT`, `MARGIN_TARGET_PCT`, `DSI_TARGET_DAYS`, `WASTE_PCT_BASE`, `WASTE_CORRECTIVE_TASK_MIN_COST`, `WORKFORCE_DEFAULT_WEEKLY_HOURS`, `WORKFORCE_MIN_REST_HOURS`.
- **Pruebas**: `php tests/dairy/run.php` (copia temporal de la BD) — 46 verificaciones OK: OC 120 u. → recepción con lote → merma 8 u. pendiente → reintento idempotente → segregación → aprobación descuenta una vez, costo 28.00 → KPI +28 → MRP stock neto → tarea correctiva única → agenda → kardex REC+MRM; presupuesto; turnos (solape, nocturno, descanso, horas extra, ausencia, disponibilidad, mover, cambio, cobertura, publicar, copiar, fichaje, registro manual, merma con turno, productividad). Render 375/1366 px sin desbordes ni errores JS en `/schedule`, `/kpis`, `/cost-centers`, `/mrp`, `/ai-advisor`, `/today`, `/workforce`, `/waste`; 8 intenciones de Arturito vía API.
- Pendientes: 3-way match con factura, conteo cíclico automático por ABC, estacionalidad, restricción por sucursal asignada, promociones generales, narrador LLM (dependencia + key), migraciones sin "down" (Grocy no las soporta: respaldar la BD antes de actualizar).

### Etapa 14 — Acceso por sucursal y conciliación de facturas (2026-10-15, parcial)
- **Acceso por sucursal** (migración 0273 `user_branches`): `DairyAccess::AllowedBranches/CanSeeBranch/FilterBranches`; el middleware rechaza (403) `branch_id`/`from_branch_id` ajenos en ruta, query o cuerpo, completa la sucursal propia si falta (query siempre; cuerpo solo en APIs DAIRY) y oculta "Red consolidada". Listados filtrados en Panel, Centros de costo (total recalculado), Sucursales, Turnos, Arturito y header; traslados validan origen/destino. Administración en `/branches` → "Acceso de usuarios por sucursal" (BRANCH_MANAGE; un usuario restringido solo asigna sus sucursales).
- **3-way match** (migración 0274 `supplier_invoices`/`_items`, `SupplierInvoiceService`): factura por OC con recepción; por línea compara pedido, recibido (acumulando facturas previas) y precio (tolerancia `INVOICE_PRICE_TOLERANCE_PCT`); estados conciliada / con diferencias / aprobada con diferencia / rechazada; decisión con motivo y segregación. UI en `/mrp#invoices`; tarjeta "Facturas con diferencias" en `/today`.
- Corregido: `WorkforceScheduleService` mezclaba hora de SQLite (`localtime`) y de PHP (UTC); ahora usa solo PHP.
- Pruebas: `php tests/dairy/run.php` → 52 + 10 (`test_branch_access.php`, usuario sin ADMIN) OK.
- Pendiente: conteo cíclico automático por ABC, estacionalidad del pronóstico, promociones, narrador LLM, documento imprimible de factura, verificación visual de `/mrp` con facturas.

### Etapa 15 — Barrido de 20 módulos, escala visual y cierre de pendientes (2026-10-16)
- **Barrido automático** (Edge headless, 1366 y 375 px) de Hoy, Panel, Mermas (centro/registro/aprobaciones), Calidad, Stock, Kardex, Traslados, Conteos, Sucursales, MRP, Compras, Simulador, Piso, Turnos, POS, Ventas, Reportes, Centros de costo, Arturito: errores JS, peticiones ≥400, botones sin acción, desbordes, cabecera. Script: scratchpad `sweep.mjs`.
- **Corregido**: 404 de `/viewjs/<vista>.js` en las 20 vistas DAIRY (layout lo pedía siempre; ahora solo si existe, `BaseController` → `viewJsExists`).
- **Escala única de botones** en el tema: 38 / 32 / 26 / 46 px (regular / sm / xs y compactos de tabla / lg); quitadas alturas inline (Panel, Mermas, Piso). `/inventory` con `dairy-page-header`.
- **Datos inventados eliminados**: IRA 98.4 % / 100 % sin conteos → "sin conteos evaluados"; simulador precio 3.99 → "sin precio de venta"; POS, rebajas y validación de venta usaban `costo×1.35` o `$10×1.35` → `SALE_PRICE_DEFAULT_MARKUP_PCT` sobre costo real; sin precio ni costo el producto no se vende (botón "Sin precio de venta", error en API).
- **Conteo cíclico automático por ABC** (SAP MM): `CycleCountService::PlanByAbc`, `POST /api/stock/cycle-counts/auto-abc` (`dry_run` = vista previa), ventas 90 d Pareto 80/95, frecuencia `CYCLE_COUNT_DAYS_A/B/C` (7/30/90), ubicación principal de la sucursal, sin duplicar pendientes. Botón "Programar por ABC" en Mermas → Conteos.
- **Estacionalidad semanal en MRP**: índice por día de la semana (28 d, acotado 0.5–2, mínimo `MRP_SEASONALITY_MIN_UNITS`) aplicado a ROP y sugerencia; icono en `/mrp` cuando hay patrón.
- **Factura imprimible**: `/document/invoice/{id}` (folio FAC, pedido/recibido/facturado, precios, monto en riesgo, decisión); botón en `/mrp#invoices`.
- Pruebas: `php tests/dairy/run.php` → 58 + 10 OK.
- Pendiente: promociones generales (precios por período/cliente), narrador LLM (dependencia + key), cuentas por pagar/pagos, verificación visual de facturas con datos reales.

### Etapa 16 — Móvil v2 (2026-10-17)
- Referencia SAP Fiori (lista → detalle, barra inferior, hojas). Todo el CSS nuevo está en media queries: `≤767.98px` (diseño) y `≤767.98px, (pointer:coarse) and ≤1199.98px` (objetivos de 44 px). Más un ajuste 992–1279 px: las acciones de cabecera pasan bajo el título si no caben.
- Diseño móvil: márgenes 16 px y separación 8/16 px; texto 16 px, títulos 20–24 px (tokens `--dairy-fs-*` redefinidos en móvil); KPIs en 2 columnas; filas/col con anchos inline (Mermas 44.5/31.5/24 %) a una columna; hijos flex con `min-width:0` (sin texto cortado ni scroll de página).
- Tablas → tarjetas (`dairy_mobile.js`): título = columna Producto/Nombre/… o la primera con texto; 4 datos visibles; acciones siempre visibles; resto en "Detalle" desplegable. Se reaplica en `draw.dt`, pestañas, modales y filas cargadas por AJAX (MutationObserver). Comparativas con columnas y scroll localizado: `.dairy-keep-table`, `#kx-table`, `.sch-grid`, `.sch-heat`, `.dairy-budget-table`.
- Diálogos como hoja inferior (cierre de 44 px, cuerpo desplazable, área segura). Teclado abierto → se oculta la barra inferior y se centra el campo.
- Barra inferior: Hoy · Mermas · **Registrar** (merma, si tiene permiso; si no, Escanear) · Inventario · Más.
- Registrar merma: se quitó `capture="environment"` (permite elegir foto existente de la galería; también en Piso de Tienda); error de evidencia junto al campo; borrador `form[data-dairy-draft]` en sessionStorage (solo eventos reales del usuario; un enlace con parámetros tiene prioridad; se limpia al registrar).
- Checkbox de acceso por sucursal envuelto en `label.dairy-check-hit` (44 px en táctil, neutro en escritorio).
- Verificado (scratchpad sesión d44cd24a: `mprobe.mjs`, `ftest.mjs`, `cstyles.mjs` + `ldiff.mjs`): 22 vistas en 320/360/390/430/768/740×360/1024 sin scroll horizontal ni controles < 44 px (salvo input oculto de un custom-checkbox, cuyo label sí mide 44); 20 pruebas funcionales (menú, hoja Más, tarjetas y Detalle, diálogo, teclado, validación, borrador, búsqueda DataTables, pestañas) OK en 320/360/390/740×360; escritorio 1280/1440: estilos computados idénticos salvo elementos ocultos (barra inferior, aviso de evidencia) y el label invisible. `php tests/dairy/run.php` 58+10 OK.
- Limitaciones: sin dispositivo físico (emulación Edge); el teclado virtual se detecta por foco, no por tamaño real del viewport; borrador solo en Registrar merma.

### Etapa 17 — Conteo ciego y escalamiento de tareas (2026-10-18)
- **Defecto corregido (crítico)**: `POST /api/stock/cycle-counts/{id}/execute` re-ejecutaba conteos ya registrados o **ajustados** (permitía un segundo ajuste del mismo conteo) y no validaba la cantidad. Lógica movida a `CycleCountService::Execute`: solo estado `pending`, `UPDATE … AND status='pending'` (reintento/concurrencia), cantidad ≥ 0 obligatoria.
- **Stock del sistema al contar** (antes congelado al programar: ventas intermedias daban diferencias falsas); costo de la diferencia con el costo de esa ubicación.
- **Conteo ciego** (`CYCLE_COUNT_BLIND`, por defecto sí): pendientes muestran "Oculto" y el diálogo no revela el stock; se ve tras registrar.
- **Escalamiento** (`STORE_TASK_ESCALATION_MINUTES`, 30): tareas de piso pendientes pasado plazo + margen → tarjeta roja "Tareas de piso vencidas" en `/today` (rol y asignado); no se repiten en "pendientes" ni "correctivas". Insignia "Vencida" en `/workforce`. `completed_at` usa hora de PHP (antes UTC de SQLite).
- Pruebas: `php tests/dairy/run.php` → 69 + 10 OK (11 nuevas). Render 200 de `/today`, `/waste`, `/waste/tab/conteos`, `/workforce`.

### Etapa 18 — Operación sin conexión, historial inalterable y control de movimientos (2026-10-18)
- **Defectos críticos corregidos (Piso de Tienda)**: la merma rápida era un segundo motor que insertaba con estado `reported` (fuera del flujo: nunca llegaba a Aprobaciones), caía en la causal #1 si el código no existía, no exigía evidencia y **nunca subía la foto**; el botón decía "Bloquear lote" sin bloquear nada. Ahora delega en `WasteService::ReportWaste` (causal activa, evidencia obligatoria, aprobación, turno, idempotencia) y sube la foto. Insignia "En línea (Turno activo)" era fija → refleja la red real. Filtro de roles con chips Bodeguero/Reponedor que nunca coincidían (roles reales `clerk`/`supervisor`) → chips generados de los roles existentes, en español. Título en inglés → "Piso de Tienda".
- **Idempotencia de API** (`DairyIdempotencyMiddleware`, migración 0275): `X-Idempotency-Key` en POST/PUT guarda la respuesta 2xx; reenvío → misma respuesta sin re-ejecutar; simultáneo → 409; fallo → clave liberada; clave en otra ruta/usuario → 422; en proceso > 2 min se libera.
- **Cola sin conexión** (`public/js/dairy_offline.js`, IndexedDB, por usuario): completar tarea y merma rápida (con foto) se guardan sin red y se envían en orden al volver; rechazos del servidor quedan "por revisar" con el motivo (no se reintentan); indicador flotante y panel con Sincronizar/Descartar. Límite: las páginas no se abren sin red (el SW no cachea datos), la cola sirve si la red cae con la página abierta.
- **Historial inalterable** (migración 0276, `MovementLedgerService`): trigger copia cada fila nueva de `stock_log` a `dairy_movement_ledger`, que rechaza DELETE y cambios (solo un sello); cadena SHA-256. `Verify` detecta movimientos borrados, modificados (fusión/división de Grocy también aparecen, explicado), no registrados y cadena alterada. `GET /api/stock/ledger/verify`, botón "Verificar integridad" en Kardex.
- **Movimientos anómalos** (`GET /api/stock/anomalies`, tarjeta "Control de movimientos" en Kardex, por sucursal): duplicado probable (≤ 2 min), corrección de inventario ≥ `INVENTORY_ANOMALY_MIN_VALUE` y ≥ μ+3σ, merma descontada sin caso, fuera de `STORE_OPEN_HOUR`–`STORE_CLOSE_HOUR`, ≥ `INVENTORY_ANOMALY_UNDO_COUNT` anulaciones por usuario. En la BD demo encuentra 3 mermas sembradas directo en BD sin `transaction_id` (dato demo, no defecto del servicio).
- Marca: títulos y textos visibles "Dairy" → "DAIRY"; títulos con "| DAIRY" duplicado corregidos. Se conserva la atribución a Grocy en "Acerca de" (licencia MIT).
- **Decisiones de arquitectura**: (1) multiempresa = una instancia/BD por empresa (aislamiento físico; Grocy no tiene columna de empresa y agregarla a ~270 migraciones es de alto riesgo); multisucursal sigue dentro de la instancia (Etapa 14). (2) Móvil = PWA; no existe app Expo en el repositorio y no se crea una segunda base de código.
- Pruebas: `php tests/dairy/run.php` → 69 + 10 + 16 (`test_movement_ledger.php`) + 14 (`test_offline_sync.php`) OK. E2E Edge 390 px (scratchpad sesión 6c588f50 `offline_e2e.mjs`, con respaldo/restauración de la BD demo): 11/11 (cola, sin duplicados, conflicto, descarte, sin errores JS); Kardex y Piso 390/1366 sin scroll horizontal.

### Etapa 19 — Prevención de pérdidas, operaciones multisucursal y alertas accionables (2026-10-18)
Auditoría previa: se compararon las capacidades pedidas (referencias públicas de SAP EWM, Zebra Workcloud, Sensormatic, Oracle Retail, Manhattan, Blue Yonder, UKG, Legion, Logile) contra el código. ✔ ya existía · ✚ esta etapa · ✘ pendiente.

| Área | Capacidad | Estado | Dónde |
|---|---|---|---|
| Inventario | Perpetuo, kardex, lotes, ubicaciones, escaneo, conteo ciego/cíclico ABC, ajuste aprobado | ✔ | Etapas 8–18 |
| Inventario | Stock fantasma (racha sin venta improbable: (1−f)^g ≤ umbral) → conteo ciego en 1 clic | ✚ | `/inventory-control`, `LossPreventionService::PhantomStock` |
| Inventario | Matriz ABC-XYZ (CV de 12 semanas) con política por celda | ✚ | `/inventory-control` |
| Inventario | Reservas de stock · reposición góndola↔bodega | ✘ | requiere zona por ubicación, capacidad de góndola y tocar disponibilidad de venta/MRP |
| Pérdidas | Pérdida total clasificada: conocida por clase, desconocida (ajustes), salidas sin documento, recuperado, % venta | ✚ | `/waste#tab-perdida` (pestaña diferida) |
| Pérdidas | Conciliación POS ↔ inventario (venta sin movimiento de stock) | ✚ | misma pestaña + alerta `pos_unreconciled` |
| Pérdidas | Causales, evidencia, aprobación, 5 porqués, recuperación, rechazos de recepción, anomalías | ✔ | Etapas 1–18 |
| Pérdidas | Devoluciones de clientes parciales | ✘ | solo anulación total de venta |
| Operaciones | Retiro de producto (recall) multisucursal: cuarentena automática, confirmación por sucursal con diferencia explicada, baja con costo real, reclamo al proveedor | ✚ | `/recalls`, `RecallService` (folio RET-) |
| Operaciones | Auditorías de tienda: plantillas ponderadas con ítems críticos, puntaje, tarea correctiva con plazo (24/72 h), comparativo por sucursal | ✚ | `/audits`, `StoreAuditService` (folio AUD-) |
| Operaciones | Recepción contra OC, 3-way match, frío, inspecciones, traslados | ✔ | Etapas 8–14 |
| Personal | Turnos, disponibilidad, cambios, ausencias, descansos, horas extra, cobertura, productividad, costo laboral | ✔ | `/schedule` (Etapa 13) |
| Personal | Certificaciones con vencimiento; requisito por rol bloquea el turno si falta o venció | ✚ | `/schedule` → Empleados |
| Personal | Cobertura sugerida por demanda (tickets/hora ÷ `WORKFORCE_TICKETS_PER_STAFF_HOUR`) + costo laboral % venta | ✚ | `/schedule` → Cobertura → «Por demanda» |
| Automatización | Centro de alertas: 9 reglas configurables (activa, umbral, severidad, plazo, responsable, aprobación), sin duplicados, cierre automático si la condición desaparece, ciclo abierta→en curso→resuelta→aprobada (otra persona)/rechazada, descartar con motivo, bitácora | ✚ | `/alerts`, `AlertCenterService`; tarjetas en `/today` |
| Integraciones | RFID/EAS, sensores IoT, video, nómina externa | ✘ | no se inventan (regla 7); temperaturas siguen por registro/API |

- Migración **0277.php** (solo agrega): `product_recalls(_items)`, `audit_templates(_items)`, `store_audits`, `store_audit_answers`, `employee_certifications`, `role_certification_requirements`, `dairy_alert_rules` (9 reglas), `dairy_alerts`, `dairy_alert_events`, plantilla base de auditoría; permisos `LOSS_PREVENTION` → `ALERTS_MANAGE`, `RECALL_MANAGE`, `AUDIT_PERFORM`.
- Ajustes nuevos: `WORKFORCE_TICKETS_PER_STAFF_HOUR` (20), `ALERT_SCAN_MINUTES` (15).
- Sidebar: Centro de Alertas (inicio), Retiros (Calidad), Control de Inventario (Inventario), Auditorías (Operaciones). `public/js/dairy_ui.js` = helper común (POST con error real, diálogos).
- Permisos: páginas en `DairyAccess`; APIs con `CheckPermission` y validación de sucursal del registro; asignar/aprobar/descartar alertas = `ALERTS_MANAGE`; atender/resolver = responsable o gestor.
- Pruebas: `php tests/dairy/run.php` → 69 + 10 + **57** (`test_loss_prevention.php`) + 16 + 14 = 166 OK. Render 200 y barrido Edge 1366/390 px de las 4 páginas nuevas + `/waste#tab-perdida`, `/schedule`, `/today`: 0 errores JS, 0 botones sin acción, sin scroll horizontal. Flujos UI con clics reales (scratchpad sesión 2e44fcf8 `flow19.mjs`): auditoría completa, alerta atender→resolver→aprobación exigida (auto-aprobación rechazada con 400 intencional), historial, vista previa de retiro, cobertura por demanda, filtro ABC-XYZ — 11/11 a 1366 y 390 px.
- BD demo: tiene 0277 aplicada (respaldo previo `grocy_es.db.bak19` en scratchpad 2e44fcf8). El primer barrido real generó 33 alertas (sobre todo ventas demo sembradas sin movimiento de stock y movimientos anómalos ya conocidos de la Etapa 18): son hallazgos reales de los datos, no defectos.
- Escala: el barrido es sucursales × reglas; para cientos de sucursales llamar `POST /api/alerts/scan` con `branch_id` desde un programador externo (Grocy no tiene cron) en vez de depender de la apertura de la página.
- Expo Go: no existe app Expo en el repositorio (decisión Etapa 18: móvil = PWA); no se creó. Las páginas nuevas usan el CSS móvil existente (tarjetas, hojas, 44 px).
- Pendientes: reservas, reposición góndola/bodega, devoluciones parciales de clientes, documento imprimible RET/AUD, programador de barridos.

### Etapa 20 — DAIRY Mobile para el manager (2026-10-18)
- **App `/m`** (vista `mobile_app`, sin layout web; `MobileController`, `public/js/dairy_m.js`, `public/css/dairy_m.css`). La web no cambia: en < 768 px el primer botón de la barra inferior web ("Inicio") abre `/m`; desde `/m` la hoja de sucursal enlaza a la versión web.
- **Lecturas** `MobileManagerService` + `Api/MobileApiController` (`/api/m/me|home|sales|sales/{id}|waste|inventory|sku/{id}|inbox|case/{type}/{id}|search|lookups`), con permiso por bloque y sucursal (`DairyAccess`). **Acciones**: APIs existentes (aprobar/rechazar/investigar/asignar/corregir/cerrar merma, ejecutar/aprobar/reconteo, atender/resolver/asignar/aprobar/descartar/comentar alerta, recibir traslado, anular venta, captura) con `X-Idempotency-Key` por botón; el servidor decide qué acciones ve cada caso (permisos, estado, segregación).
- **Navegación**: Inicio · Ventas · Control (insignia de urgentes) · Inventario · Mermas + FAB de captura. General → Departamento (grupo de producto) → Producto/SKU → Registro → Acción; migas, filtros y pestañas en el hash, scroll restaurado al volver, búsqueda global (producto, SKU, código, caso, venta).
- **Mermas sin doble conteo**: operativa / comercial según la causal (`averia` = operativa); *por clasificar* = causal `ajuste` + diferencias netas de inventario ajustadas; por aprobar y conteos sin ajustar se muestran pero no suman; salidas sin documento se informan aparte.
- **Centro de Control móvil**: bandeja única (mermas por aprobar/en investigación, conteos, alertas, traslados) ordenada por severidad, vencimiento e impacto; revisión uno por uno con paginador y avance automático al resolver.
- **Captura rápida**: merma (6 motivos más usados + "Más motivos", foto, validación de disponible y evidencia), conteo ciego, ajuste (pasa por diferencia + aprobación de otra persona), mover entre ubicaciones; escáner ZXing + manual.
- Evidencia: solo se enlaza si el archivo existe (los datos demo guardan nombres sin archivo y se avisa).
- Pruebas: `test_mobile.php` 27 OK (suite completa 69+10+57+27+16+14 OK). E2E Edge (scratchpad sesión fa4e0c58 `mflow.mjs`, BD respaldada/restaurada) 44/44 en 320/390/430 px: drill-down, regreso con estado y scroll, acciones reales persistidas, segregación, validaciones, triple toque = 1 registro, sin scroll horizontal ni errores. Web 1366: sin barra inferior, sidebar intacto.
- Límites: departamento = grupo de producto (no hay entidad departamento); acciones en pausa sin conexión (la cola offline existente es de la web); traslados entre sucursales se crean en la web; capturas con emulación, sin dispositivo físico.

### Etapa 21 — Escáner, rotación, formularios automatizados y política de mermas (2026-10-20)
Referencias: terminales Honeywell, prevención de mermas (4 prácticas), registro automatizado en Excel, gestión web/móvil de mermas. Matriz contra el código (✔ ya existía · ✚ esta etapa · ◐ parcial · ✘ pendiente):

| Área | Capacidad | Estado | Dónde |
|---|---|---|---|
| Escáner | Lectura EAN/UPC/QR por cámara, búsqueda por código | ✔ | Etapa 12 (`dairy_mobile.js`), `/m` |
| Escáner | GS1 (paréntesis, FNC1/GS, ]C1 ]d2 ]Q3): GTIN con dígito verificador, lote (10), vencimiento (15/16/17, DD=00), serie (21), cantidad (30/37), peso (31nn) | ✚ | `ScanService::ParseCode` |
| Escáner | Capa de lectores: cámara (ZXing + Data Matrix/GS1-128), escáner tipo teclado HID (ráfaga < 35 ms), puente SDK industrial | ✚ / ✘ SDK | `public/js/dairy_scan.js`; SDK Honeywell/Zebra requiere app contenedora nativa (`window.DairyScanBridge`), no probado |
| Escáner | Búsqueda por código, SKU, `P<id>`, lote, serie o etiqueta `LOC-` con disponibilidad | ✚ | `/scan` (Consultar), `GET /api/scan/resolve` |
| Escáner | Conteo guiado ciego → conteos cíclicos → ajuste aprobado (sin tocar stock al cerrar) | ✚ | sesión `count` |
| Escáner | Movimiento entre ubicaciones de la sucursal (por lote si se escaneó) | ✚ | sesión `move` → `TransferProduct` |
| Escáner | Recepción contra OC con lote/vencimiento GS1; lote ligado al `stock_id` | ✚ | sesión `receive` → `ReceiveProcurementOrder`, `dairy_stock_lots` |
| Escáner | Idempotencia por lectura (`client_uuid`), rebote del gatillo (400 ms), series duplicadas, tope por stock en origen / pendiente de OC, anulación auditada, cierre con reclamo atómico | ✚ | `dairy_scan_lines`, `CloseSession` |
| Escáner | Sin conexión: lecturas en cola con hora real del escaneo | ✚ | `DairyOffline.send` |
| Escáner | Picking / packing / despacho | ✘ | no existe pedido de cliente ni ola de preparación; requiere entidad de pedido |
| Inventario | Físico, disponible, cuarentena, vencido, merma pendiente (comprometido), tránsito, último conteo | ✚ | `ScanService::Availability`; reservado = «—» (no hay reservas) |
| Organización | Sucursal › almacén › zona › pasillo › estante › posición, etiqueta `LOC-` única, clase (ambiente/refrigerado/congelado), capacidad | ✚ | migraciones 0278/0279, formulario de ubicación, `/locations/labels` (QR, Data Matrix, Code 128 con bwip-js) |
| Organización | Reglas de compatibilidad/capacidad que bloqueen el almacenamiento; mapa visual | ✘ | campos listos; falta clase de almacenamiento por producto y validación en entradas |
| Rotación | Lotes inmovilizados con recomendación determinística (baja / rebaja / traslado / devolución / conteo) | ✚ | `/inventory-control`, `LossPreventionService::StagnantLots` |
| Formularios | Importación CSV de productos, proveedores, clientes, ubicaciones: obligatorios, formatos, referencias, duplicados en archivo y en BD, validar → confirmar (todo o nada), modo alta / actualizar | ✚ | `/data-import`, `ImportService`, `dairy_import_jobs` |
| Formularios | Historial de cambios con usuario y antes/después (también ediciones en pantalla) | ✚ | triggers → `dairy_change_log`, `GET /api/change-history/{tabla}/{id}` |
| Formularios | Exportación CSV (BOM, «;», fórmulas neutralizadas) y Excel (.xls SpreadsheetML) | ✚ | `DairyExportService`, plantillas y datos actuales |
| Formularios | Importar .xlsx directo | ✘ | sin librería (PhpSpreadsheet); se pide guardar como CSV |
| Mermas | Política de aprobación por importe (`WASTE_APPROVAL_MIN_COST`) y riesgo de la causal (Alto/Crítico), además de la causal | ✚ | `WasteService::RequiresApproval` |
| Mermas | Aprobación por nivel: ≥ `WASTE_HIGH_VALUE_COST` exige `WASTE_APPROVE_HIGH` | ✚ | `AssertApprovalLevel` |
| Mermas | Posible duplicado (mismo producto/cantidad/causal/usuario en `WASTE_DUPLICATE_WINDOW_MINUTES`) exige confirmación explícita | ✚ | web, Centro de Control, Piso, `/m` |
| Mermas | Exportación con filtros (CSV / Excel); PDF = informe imprimible | ✚ | `GET /api/waste/export`, menú Exportar de `/waste` |

- Migraciones **0278** (columnas de jerarquía en `locations`, `dairy_stock_lots`, `dairy_scan_sessions/lines`, `dairy_import_jobs`, `dairy_change_log` + triggers en products, product_barcodes, shopping_locations, customers, locations, waste_reasons; permisos `SCAN_OPERATE` ⊂ STOCK, `WASTE_APPROVE_HIGH` junto a WASTE_APPROVE, `DATA_IMPORT` ⊂ ADMIN) y **0279** (etiqueta automática/mayúsculas; separada porque un servidor en :8080 aplicó 0278 antes de agregarla). Solo agregan.
- `DatabaseService` registra la función SQL `dairy_user_id()` usada por los triggers de historial. **Riesgo**: herramientas externas (sqlite CLI, scripts PDO propios) que modifiquen esas 6 tablas deben registrarla (`$pdo->createFunction('dairy_user_id', fn() => null, 0)`) o fallan con «no such function».
- Ajustes nuevos: `WASTE_APPROVAL_MIN_COST` (50), `WASTE_HIGH_VALUE_COST` (500), `WASTE_DUPLICATE_WINDOW_MINUTES` (10), `SCAN_DUPLICATE_WINDOW_MS` (400), `STAGNANT_LOT_DAYS` (30).
- Navegación: Inventario → «Operaciones con Escáner»; Datos maestros → «Importar / Exportar», «Etiquetas de Ubicación». Rutas `/scan`, `/data-import`, `/locations/labels` en `DairyAccess`.
- Defecto encontrado y corregido durante la prueba E2E: la ventana de rebote de 2 s descartaba escaneos legítimos unidad por unidad → milisegundos (400 ms servidor, 300 ms cliente).
- Pruebas: `test_operations.php` 52 OK (GS1, EAN/GTIN, ubicaciones, conteo ciego, reintento, rebote, series, anulación, cierre idempotente, movimiento, recepción con lote, disponibilidad, política y duplicados de merma, importación con errores/todo-o-nada/historial, etiquetas, lotes inmovilizados, exportación). `test_offline_sync.php` ajustada (duplicado ahora exige confirmación). API por HTTP 14/14; E2E Edge `/scan` 12/12 a 1366 y 390 px (consulta, ráfaga de teclado, etiqueta LOC-, conteo ciego, anulación, cierre); `/data-import` 7/7; barrido 1366/375 px de `/scan`, `/data-import`, `/locations/labels`, `/inventory-control`, `/waste`, `/waste/report`, `/location/new`: sin errores JS, sin scroll horizontal, sin botones sin acción. Scripts: scratchpad sesión 383c23ce `flow21.mjs`, `imp21.mjs`, `api21.py`.
- Pendientes: picking/packing/despacho (requiere pedidos de cliente), reservas, SDK industrial nativo, reglas de compatibilidad producto↔clase de ubicación, mapa de ubicaciones, importación .xlsx directa, historial visible en las fichas de producto/proveedor (la API ya existe), predicción ML (el motor sigue siendo determinístico y lo declara).

### Etapa 22 — Pedidos con picking, compatibilidad de almacenamiento, mapa e historial visible (2026-10-20)
| Capacidad | Estado | Dónde |
|---|---|---|
| Pedidos de cliente: borrador → confirmado (reserva) → en preparación → preparado → empacado (bultos) → despachado (venta) / anulado con motivo | ✚ | `/orders`, `CustomerOrderService`, `POST /api/orders[/{id}/confirm|pack|dispatch|cancel]` |
| Reservas: el POS no vende lo reservado; disponibilidad del escáner descuenta reservado; confirmar toma el bloqueo de escritura antes de leer (sin doble reserva) | ✚ | `SalesService::CreateSale`, `ScanService::Availability` |
| Picking por escáner con lote FEFO sugerido (lote, ubicación, vencimiento); lote fuera de FEFO avisa y queda marcado; no excede lo pedido ni el stock libre; picking parcial permitido | ✚ | sesión `pick` en `/scan` |
| Despacho = venta sobre las entradas exactas preparadas (`stock_id` por línea), costo real, kardex, KPIs; idempotente (estado `dispatching` reclamado) | ✚ | `CustomerOrderService::Dispatch` |
| Clase de almacenamiento por producto (ambiente/refrigerado/congelado) con bloqueo central por trigger en `stock` (compra, traslado, inventario, escáner) y mensaje claro en el escáner | ✚ | migración 0280, ficha de producto, importación (`clase_almacenamiento`) |
| Aviso de capacidad de la ubicación destino (no bloquea) | ✚ | escáner mover/recibir |
| Mapa de ubicaciones: sucursal › almacén › zona › pasillo, ocupación vs capacidad, vencidos, por vencer 3 d, valor, lotes en clase incompatible; avisa si faltan jerarquía o capacidades | ✚ | `/locations/map` (celda → contenido en el escáner) |
| Historial de cambios visible en fichas de producto, proveedor y ubicación | ✚ | `components/dairy_change_history` |
| Tarjeta «Preparar y despachar pedidos» en Hoy | ✚ | `ManagerAgendaService` |

- Migraciones **0280** (`products.storage_class`, triggers de compatibilidad, `customer_orders`, `customer_order_items`, `customer_order_picks`, `dairy_scan_sessions.customer_order_id`, permisos `ORDERS_MANAGE`, `ORDERS_PICK` ⊂ SALES) y **0281** (regenera triggers de historial).
- Defectos encontrados y corregidos en pruebas: (1) los triggers de historial fijan columnas al crearse → `storage_class` no se auditaba; `Helpers\DairyChangeLog::InstallTriggers()` debe llamarse en toda migración que agregue columnas a tablas auditadas. (2) La asignación FEFO automática se marcaba «fuera de FEFO» y dos lecturas del mismo cierre podían asignar la misma entrada dos veces → control de lo asignado en el cierre.
- Despachar exige `ORDERS_MANAGE` + `SALES_POS`. Preparar no mueve stock; anular libera la reserva.
- Pruebas: `test_orders.php` 35 OK; suite 281 OK. E2E Edge 14/14 a 1366 y 390 px (alta con reserva, picking con sugerencia FEFO, empaque, despacho con VTA, historial en ficha, mapa); barrido de `/orders`, `/locations/map`, `/scan?pick`, fichas de producto/proveedor/ubicación, `/today`, `/pos`: sin errores JS, sin scroll horizontal ni botones sin acción. BD real restaurada al respaldo previo a las pruebas (solo diferían datos de prueba) con 0280/0281 aplicadas.
- Pendientes: documento imprimible de pedido/guía de despacho, olas de picking multi-pedido, cobro/crédito de cliente (el despacho se registra como `credit`), SDK industrial nativo.

### Etapa 23 — Trazabilidad de punta a punta (2026-10-20)
Prioridad indicada: escaneo + Kardex, FEFO/conteos y mermas ya estaban (Etapas 8–22); faltaba unirlos en una sola cadena por lote.

| Brecha previa | Ahora |
|---|---|
| El lote del proveedor solo se capturaba recibiendo por escáner | También en la recepción de `/mrp` (columna «Lote proveedor»); registro único `TraceabilityService::RegisterLots` (upsert lote + serie) |
| Kardex por producto/entrada, sin origen comercial ni destinos | `/traceability`: origen (proveedor, OC, REC, temperatura, quién recibió, costo) → recorrido con documento y usuario (traslados TRA con tránsito, movimientos de escáner SCN, ventas VTA con cliente y pedido PED, mermas MRM, ajustes) → eventos sin movimiento (cuarentena CUA, retiro RET, merma por aprobar, vencimiento) → dónde está hoy |
| Sin trazabilidad inversa | Búsqueda por VTA-, PED-, REC-, TRA-, id de entrada, lote, serie o GS1 escaneado → lote y proveedor |
| Retiros sin lista de clientes afectados | «Clientes que lo recibieron» con ventas, cantidades y fecha; botón Retiro |
| Sin control de cuadre por lote | Conciliación: recibido − vendido − merma − ajustes − salidas sin documento = saldo en ubicaciones; alertas: entrada sin OC, salida sin documento, vencido con stock, retiro abierto, no concilia |

- Enlaces: Kardex (lote → Trazabilidad, lotes con número del proveedor), escáner (cada entrada → su traza), menú Inventario → Trazabilidad. API `GET /api/traceability?q=` (STOCK).
- Sin migraciones nuevas (usa `dairy_stock_lots` de 0278). Entradas anteriores sin lote del proveedor se trazan por su id de entrada.
- Defecto corregido en la prueba: clientes con ventas agrupadas devolvían mapa en vez de lista.
- Pruebas: `test_traceability.php` 20 OK (OC → REC con lote y 4.5 °C → TRA a otra sucursal con 1 u. de diferencia → venta a cliente → merma → salida sin documento: recibido 30 = vendido 5 + merma 3 + sin documento 1 + saldo 21; búsquedas desde VTA, REC, TRA, GS1 y stock_id; entrada directa y lote vencido con alertas). Suite 301 OK. E2E Edge 18/18 (1366 y 390 px). BD real sin cambios.
- Pendientes: documento imprimible de trazabilidad con firma, captura de lote en compras directas de Grocy (`/purchase`), notificación a clientes en un retiro.

### Etapa 24 — DAIRY Mobile por rol: empleado, supervisor y gerente (2026-10-20)
Decisión vigente (Etapa 18): móvil = PWA `/m`, sin app Expo (no existe en el repositorio y el usuario eligió ampliar la PWA). Una sola base de código, APIs y permisos compartidos con la web.

| Brecha previa | Ahora |
|---|---|
| `/m` solo para gerente (KPIs/ventas) | Rol derivado de permisos (`MobileWorkService::Role`: gerente = KPIS_VIEW/SCHEDULE_MANAGE/BRANCH_MANAGE; supervisor = aprobaciones/alertas/asistencia; resto empleado). `/m` abierto a todo usuario; cada endpoint valida permiso y sucursal |
| Barra Inicio·Ventas·Control·Inventario·Mermas + FAB | **Inicio · Tareas · Escanear (central) · Operaciones · Perfil**; Ventas/Inventario/Mermas/Control/Resumen ejecutivo pasan a Operaciones (vistas intactas); campana de notificaciones en la cabecera |
| Inicio = tablero ejecutivo | «Hoy» personal: saludo, **lo siguiente** (marcar entrada/salida, tarea vencida, decisiones urgentes), turno con descanso y horas semana, progreso, prioridades, por decidir, equipo hoy, salud de la operación (gerente, sin gráficos repetidos), actividad reciente |
| Tareas dispersas (piso, bandeja, alertas) | Centro de tareas: *Mis tareas* (piso de mi rol o asignadas, conteos asignados a mí o sin asignar, alertas a mi nombre, traslados) · *Por decidir* (mermas, ajustes, cierres de alerta, ausencias y cambios de turno de otros) · *Equipo*. Alerta con tarea pendiente se muestra solo como tarea (sin duplicado) |
| Alerta y tarea sin vínculo | «Crear tarea» en la alerta (ALERTS_MANAGE): una sola tarea pendiente por alerta, asigna responsable; al completarla (nota obligatoria) la alerta se resuelve en la misma transacción (o queda por aprobar cierre) |
| Escáner atado al flujo de captura | Pestaña Escanear: cámara, código manual y lectores Zebra/Honeywell en modo teclado; GS1/lote/ubicación (`ScanService::Resolve`); disponible/físico/reservado/vencido/cuarentena, lotes FEFO y **solo las acciones permitidas** (`ScanActions`); vibración y sonido opcional; lecturas repetidas < 2,5 s ignoradas; recientes |
| Sin horario del empleado en móvil | Perfil: mi horario 14 días (borradores ocultos), cambio de turno con compañero, ausencias, **disponibilidad propia** (antes solo gestión), certificaciones con vencimiento, guías rápidas |
| Sin equipo en móvil | Equipo (supervisor/gerente): semana, estado por persona (en turno, tarde, sin marcar, ausencia), huecos de cobertura, decidir solicitudes, registrar entrada/salida de otro (asistencia), agregar turno con verificación de conflictos y anular (gestión) |
| Notificaciones solo de alertas IA | Centro derivado de hechos reales (turno publicado, ausencia/cambio decidido o propuesto, tarea asignada, aprobaciones agrupadas por tipo, alertas críticas), clave estable, «visto» por usuario (`user_settings`), preferencias por tipo, aviso del sistema opcional sin repetir |
| Sin asistente en móvil | Asistente: guías paso a paso (todos) + `POST /api/ai/ask` con datos reales (KPIS_VIEW) mostrando «Datos observados / Estimación / Recomendación» y fuente |
| Sin tutorial | Recorrido inicial de 5 pasos por rol, una vez por usuario (servidor), repetible desde Perfil |
| `/m` sin conexión = acciones en pausa | Cola local con la **misma** clave de idempotencia (merma, movimiento, tarea sin foto), deduplicada por contenido, envío al reconectar, errores revisables en Perfil → Pendientes; lecturas guardadas por usuario para abrir sin red; el SW guarda el armazón `/m` (sin datos) |

- API nueva (`MobileWorkApiController`): `GET /api/m/today|tasks|tasks/{id}|team|schedule|notifications|scan`, `POST /api/m/tasks/{id}/complete`, `/m/alerts/{id}/task`, `/m/notifications/seen`, `/m/prefs`. `/m/sku` y búsqueda de SKU ahora también para quien captura (merma/conteo/traslado). `POST /api/schedule/employees/{id}/availability` acepta al propio empleado.
- Segregación: el listado «por decidir» excluye solicitudes propias (el servicio ya lo impedía). Rutinas de piso de días anteriores no se acumulan en «mis tareas» (siguen en Equipo como vencidas).
- Despliegue: JS/CSS de `/m` versionados por fecha de archivo (`assetV`); el SW (`dairy-static-v3`) servía el JS anterior con la plantilla nueva.
- Sin migraciones (preferencias en `user_settings`).
- Pruebas: `test_mobile_work.php` 29 OK (suite 330 OK). E2E Edge sobre copia de BD (`GROCY_DATAPATH`, servidor 8799; scratchpad sesión 3c01f859 `m24.mjs`, `m24emp.mjs`, `web24.mjs`): gerente 73/73 en 320/390/430 px (recorrido, 5 pestañas, Hoy, tareas, completar tarea persistida, escáner manual y por teclado, antirrepetición, operaciones, resumen ejecutivo intacto, fichaje, equipo, perfil, ausencia, disponibilidad, notificaciones, alerta→tarea, asistente, cola sin conexión → un solo registro); empleado 20/20 a 360 px (403 en servidor para aprobar/equipo/ventas/alerta→tarea, escáner sin acciones de gestión); web 1366/390 sin errores ni desbordes.
- Pendientes / requieren build nativa o configuración externa: push en segundo plano con la app cerrada (requiere Web Push con claves VAPID y servidor de envío; hoy los avisos del sistema salen mientras la app está abierta o en segundo plano reciente); escáner con SDK de Zebra DataWedge/Honeywell (hoy modo teclado, que ambos soportan); publicación en tiendas (sería una app Expo/nativa que consuma estas mismas APIs); cursos con evaluación (no hay módulo LMS); arranque totalmente sin red requiere haber abierto `/m` antes en ese teléfono; fotos y fichaje no se encolan sin conexión (la hora debe ser la real del servidor).

### Etapa 25 — Gestión completa de horarios (Fases 1–2 del plan WFM/mermas, 2026-10-20)
Fase 1 (auditoría acotada a horarios): **P0** `SaveAttendance` con `id` sobrescribía el fichaje sin conservar el original ni pedir aprobación · **P1** la API `/schedule/*` no validaba la sucursal (un gerente de S1 podía ver/editar S2) · **P2** sin historial de turnos, sin deshacer, dividir, duplicar, alta masiva ni plantillas, sin control de ediciones simultáneas · **P2** nocturno con `+86400` (error de 1 h en cambio de horario de verano) · **P3** «Turno publicado» usaba la fecha de creación. Mermas (escáner/manual, idempotencia, aprobación) ya cubiertas en Etapas 18/21: se auditan en Fase 3.

| Cambio | Detalle |
|---|---|
| Migración 0282 | `employee_shift_changes` (antes/después JSON, motivo, lote, usuario; inalterable y sin borrado por trigger), `shift_templates`, `employee_attendance_adjustments` (original, corregido, motivo, solicitante, estado, aprobador, fecha; inalterable una vez decidida), `employee_shifts.version/updated_by/updated_timestamp`; triggers: fichaje registrado solo cambia vía corrección en estado `applying`, nunca se borra |
| Turnos | `SaveShift` con versión optimista, transacción e historial; descanso < duración; no cambia de persona un turno con fichajes; `DuplicateShift`, `SplitShift` (tramos ≥ 1 h, descanso al más largo), `BulkCreateShifts` (≤ 200), plantillas (`SaveTemplate/ApplyTemplate`), `UndoLastChange` (último cambio o lote completo del usuario, solo borradores, sin cambios posteriores, verifica conflictos), `CancelShift` con motivo, `PublishWeek` registra cada turno |
| Reglas | Aviso de límite diario `WORKFORCE_MAX_DAILY_HOURS` (10); `Span` usa el día calendario siguiente (DST correcto: 7 h / 9 h) |
| Horas | `SaveAttendance` = solicitud (registro omitido o corrección); `DecideAttendanceAdjustment`: segregación (ni el solicitante —salvo `WORKFORCE_ADJUSTMENT_SELF_APPROVAL`— ni el propio empleado), rechazo exige motivo, corrección obsoleta rechazada; `GetTimesheet`: programadas (publicadas/borrador), trabajadas, por revisar, extra, descansos, ausencias, tiempo libre, registros manuales, correcciones |
| API | `POST /schedule/shifts/bulk`, `/shifts/{id}/duplicate`, `/shifts/{id}/split`, `GET /shifts/{id}/history`, `GET /schedule/changes`, `POST /schedule/undo`, `GET|POST /schedule/templates`, `/templates/{id}/apply|delete`, `GET /schedule/timesheet`, `GET /schedule/attendance` (`mine=1` para el empleado), `GET /schedule/adjustments`, `POST /schedule/adjustments/{id}/decide`. Todas las rutas `/schedule/*` validan sucursal (`DairyAccess::CanSeeBranch`) del empleado/turno/plantilla |
| Móvil `/m` | Equipo con segmentos **Día · Semana · Horas · Historial**; ficha del turno: editar (precargado, duración neta, revisión de conflictos antes de guardar, motivo obligatorio si está publicado) y menú progresivo duplicar/dividir/historial/anular con motivo; agregar una o **varias personas/días**; Semana: celdas por persona (publicado/borrador/ausencia/conflicto), horas vs contrato, costo, **Publicar (n)**, **Deshacer**, copiar semana, plantillas; Horas: hoja por categoría, fichajes del día con corrección, fichaje omitido, correcciones por decidir; Perfil → **Mis horas** (empleado pide corrección propia). «Por decidir» incluye correcciones (`HRS-`) |
| Avisos | Desde el historial: turno publicado (fecha real), «Tu turno cambió / anulado / reasignado» solo si estaba publicado, corrección aprobada/rechazada |
| Web | `/schedule` intacta; el registro manual informa que queda en aprobación |

- Pruebas: `test_schedule_mgmt.php` 42 OK (suite 373 OK; `run.php` ajustado: el registro manual ahora suma horas solo al aprobarlo otra persona). E2E Edge sobre copia sembrada (scratchpad sesión 5ac6eeae `seed25.py`, `e25.mjs`, `web25.mjs`): gerente 48/48 a 360/390/430 px; permisos/sucursal 21/21 (empleado sin gestión pero con corrección propia; gerente S1 sin acceso a S2); web 1366/390 sin errores ni desbordes.
- Pendientes: decidir correcciones desde la web `/schedule` (hoy en móvil y API) — P2; arrastrar y soltar en móvil (se usa formulario; la web ya lo tiene) — P3; pronóstico de cobertura ya existe (`SuggestCoverage`, ventas reales), sin recomendación automática de reasignación por persona — P2; notificación push con la app cerrada sigue requiriendo VAPID (Etapa 24).

### Etapa 27 — Preparación para producción: autorización, concurrencia y matriz final (2026-10-20)
Línea base: suite unitaria 397 OK (`php tests/dairy/run.php`) y HTTP con concurrencia real 92 OK (`php tests/dairy/http_reliability.php`, 4 servidores PHP sobre copia de la BD; creado en una sesión «Etapa 26» no registrada aquí). Los 4 huecos del E2E multiusuario (aviso al reportero, zona horaria PHP/SQLite, `assigned_employee_id`, riesgo «Alta») ya estaban corregidos y cubiertos (`test_reliability.php`).

| Gravedad | Hallazgo | Corrección |
|---|---|---|
| **P0** | Recepción de OC: pendientes validados **fuera** de la transacción (`beginTransaction` diferido). Dos recepciones simultáneas → hoy «database is locked»; con espera de bloqueo podían sumar stock dos veces | `ReceiveProcurementOrder` toma el bloqueo de escritura antes de leer (`BeginWriteTransaction`); concurrentes reciben «No se puede recibir una orden en estado received» |
| **P0** | Traslados (`Create/Receive/CancelTransfer`) y `CancelSale`: estado validado antes de la transacción y UPDATE sin re-verificar → recibir+anular o anular ×N simultáneos podían mover stock dos veces | `DatabaseService::Locked()` envuelve los 4 métodos; `CreateSale` usa `BeginWriteTransaction` (ventas simultáneas esperan turno en vez de fallar) |
| **P1** | Reintento del POS tras corte de red duplicaba la venta (sin clave de idempotencia) | `pos.blade.php` envía `X-Idempotency-Key` estable hasta que la venta se confirma |
| **P1** | Lecturas sin permiso ni sucursal: `GET /waste/records`, `/waste/analytics`, `/waste/photo/{f}` (evidencia de otra sucursal), `/mrp/order/{id}`, `/transfers/{id}`, `/sales/{id}` | Permiso + sucursal del registro (`BaseApiController::DairyRead`, `AssertOrderBranch`, `AssertSaleBranch`) |
| **P1** | Mutaciones por ID sin sucursal: aprobar/enviar/cerrar/**recibir** OC de otra sucursal, anular venta ajena, completar tarea de otra sucursal | Verificación de sucursal antes de ejecutar (403) |
| **P2** | Lecturas sin permiso (cualquier usuario autenticado): `/mrp/calculate`, `/mrp/orders`, `/mrp/receiving-rules`, `/suppliers*` (costos), `/quality/*`, `/ai/alerts`, `/markdown/*`, `/workforce/tasks` | Permisos por módulo (compras, calidad/stock, KPIs/alertas, ventas, tareas) |

Notas: el middleware ya acotaba listados por `branch_id` (query/cuerpo); el hueco eran los endpoints por ID y la falta de permiso. `/api/objects/*` no expone tablas DAIRY; `stock/stock_log` exigen STOCK pero no se filtran por sucursal (P3, ver riesgos).

- Pruebas nuevas en `http_reliability.php` (125 OK): **G2** lecturas por permiso/sucursal (14), **G3** recepción: orden S1 recibida desde S2 → 403, 3 recepciones simultáneas → 1 entra con mensaje claro, stock +5 exacto, kardex, reintento sin sumar; **G4** venta enviada 3× con la misma clave → 1 venta, reintento devuelve la misma, venta S1 desde S2 → 403, 3 anulaciones simultáneas → 1, stock reintegrado una vez, recibir y anular traslado a la vez → un solo resultado, nada en tránsito. Regresión: unitaria 397 OK; sección I (Δ kardex = Δ stock) OK.
- No verificado en esta etapa: POS en navegador tras el cambio (solo API), dispositivos físicos (cámara, modo avión, lectores Zebra/Honeywell), rendimiento y accesibilidad medidos, cobertura de código (no hay herramienta instalada).

#### Escenario integral (pasos 1–12 del plan)
| # | Paso | Evidencia |
|---|---|---|
| 1–2 | Manager crea/publica horario; empleado recibe turno | HTTP B; `test_schedule_mgmt` 42; E2E Edge Etapa 25 |
| 3 | Supervisor asigna tarea | HTTP C; `test_reliability` |
| 4–5 | Recepción de mercancía → kardex | HTTP G3 (nuevo) |
| 6–7 | Merma escaneada y manual | HTTP D–G |
| 8 | Aprobar / rechazar (segregación, simultáneos) | HTTP G |
| 9 | Stock e indicadores | HTTP G, I; `test_mobile` 27 |
| 10 | Reportes del manager | `test_mobile`, barridos Edge Etapas 15/24 (no repetido hoy) |
| 11–12 | Corte durante operación, recuperación sin duplicar | HTTP F (merma), G3 (recepción), G4 (venta); E2E offline Etapa 18 |

#### Matriz de preparación para producción
| Área | Estado | Observación |
|---|---|---|
| Autenticación, sesiones, revocación | **Aprobado** | HTTP A/H: clave vencida 401, permiso revocado aplica en la siguiente petición |
| Autorización backend y roles | **Aprobado con observaciones** | Corregidos ~25 endpoints; quedan controladores web (vistas) protegidos solo por `DairyAccess::PAGES` |
| Aislamiento multisucursal | **Aprobado con observaciones** | `/api/objects/stock*` no filtra por sucursal (requiere STOCK) — P3 |
| Multiempresa | **No verificado** | Decisión: una instancia por empresa (Etapa 18); no hay aislamiento lógico en una misma BD |
| Horarios, fichaje, horas | **Aprobado con observaciones** | Nocturno/DST/conflictos/historial probados; decidir correcciones desde web pendiente (P2) |
| Tareas | **Aprobado** | |
| Escáner | **Aprobado con observaciones** | Probado por API y Edge; sin dispositivo físico ni SDK Zebra |
| Mermas (manual/escáner/aprobación) | **Aprobado** | Idempotencia, segregación, sin doble descuento, sin negativos |
| Recepción de compras | **Aprobado** | Tras corrección P0 |
| Ventas POS | **Aprobado con observaciones** | Idempotencia por API verificada; UI no re-probada en navegador |
| Traslados | **Aprobado** | Tras corrección P0 |
| Pedidos y picking | **Aprobado con observaciones** | `test_orders` 35; sin prueba HTTP concurrente |
| Conteos y ajustes | **Aprobado con observaciones** | Sin prueba HTTP concurrente |
| Kardex y conciliación | **Aprobado** | Δ kardex = Δ stock en todas las operaciones de la prueba |
| Offline y sincronización | **Aprobado con observaciones** | Fotos y fichaje no se encolan; arranque sin red exige haber abierto `/m` antes |
| Notificaciones push (app cerrada) | **Bloqueado** | Requiere claves VAPID y servidor de envío |
| App nativa (Expo / tiendas) | **No verificado** | No existe: el usuario eligió PWA `/m` |
| Respaldo y restauración | **Bloqueado** | No hay función de respaldo/restauración en la app; depende de copiar `data/` (procedimiento a definir) |
| Secretos | **Aprobado con observaciones** | `data/` ignorado por git; sin secretos en código; revisar `config.php` del servidor real |
| Rendimiento, accesibilidad, cobertura | **No verificado** | Sin métricas medidas en esta etapa |

Quality gates para producción: 0 fallos en `run.php` y `http_reliability.php`; ningún P0/P1 abierto; Δ kardex = Δ stock; respaldo/restauración documentado y probado; VAPID solo si se exige push con la app cerrada.

### Etapa 28 — Barrido de todas las pantallas y rutas (2026-10-20)
Pedido del usuario: «en notificaciones sale error y no hay nada; revisa todo». Evidencia ejecutada, no supuesta.

| Gravedad | Hallazgo | Corrección |
|---|---|---|
| **P0** | Caché de rutas (`data/viewcache/route_cache.php`) solo se invalidaba al cambiar `version.json` (o en MODE=dev). Al agregar rutas, las URL llegaban al controlador equivocado: `/api/m/notifications` devolvía `/api/m/schedule` → notificaciones vacías/error en `/m` | `app.php`: el hash de la caché incluye `routes.php` en todos los modos |
| P2 | `/locations/labels` (QR): «No se pudo generar el código» — bwip-js rechaza `height: undefined` | `location_labels.js` solo pasa alto/texto a Code 128; registra el motivo en consola |
| P2 | `/m#/profile/hours` sin empleado vinculado: error 400 en pantalla | Mensaje guía «vincule su usuario» (igual que Mi horario) |
| P3 | `/api/schedule/*` sin `branch_id` decía «Sin acceso a esa sucursal» | «Indique la sucursal (branch_id)» |

- Nueva prueba `php tests/dairy/http_sweep.php` (163 OK): todas las rutas GET sin parámetros (web + API) sin 5xx ni errores PHP; las que exigen datos responden 400 con mensaje; controlador correcto en `/m/notifications` y `/m/schedule`; caché de rutas obsoleta se invalida (verificado que FALLA sin la corrección). Ninguna ruta > 2 s.
- Barrido en navegador (Edge headless, sin SW ni caché): 82 páginas web a 1366 px y 25 pantallas `/m` a 375 px sin errores JS, sin red ≥ 400 y sin mensajes de error (tras las correcciones).
- Regresión: `run.php` 397 OK, `http_reliability.php` 125 OK, `php -l` 221 archivos OK.
- No cubierto: clic en cada botón/flujo de cada pantalla (solo carga), pantallas con parámetros (`/m#/case/...`, fichas por ID), roles distintos de ADMIN en el barrido UI, dispositivos físicos.

### Etapa 29 — Módulo 17: planificación de personal según ventas (2026-10-20)
**Auditoría previa**: `/schedule` ya tenía turnos, disponibilidad, ausencias, horas extra, requisitos mínimos y «Por demanda» (`SuggestCoverage`: solo tickets/hora ÷ constante, sin tareas, sin productividad, sin pronóstico de un día, sin recomendación por persona). Se reutiliza todo eso; no se duplicó información ni se cambió el diseño (nueva pestaña **Pronóstico** con los componentes existentes).

| Pieza | Implementación |
|---|---|
| Servicio | `services/LaborForecastService.php` (Forecast, BandCandidates, Plan/Recommend, Apply, Accuracy, Outlook, eventos, estándares, solicitudes de cobertura) |
| Migración | `0284.php`: `labor_forecast_snapshots`, `labor_calendar_events`, `labor_task_standards` (minutos editables), `labor_coverage_requests`, `dairy_budgets.labor_budget_amount`, permiso `WORKFORCE_OVERTIME_APPROVE` |
| API | `GET /api/schedule/forecast`, `/forecast/band`, `/forecast/accuracy`, `/forecast/events`; `POST /forecast/recommend`, `/forecast/apply`, `/forecast/events`, `/forecast/events/{id}/delete`, `/forecast/standards`, `/coverage-requests`, `/coverage-requests/{id}/close` |
| UI | `/schedule` → Pronóstico (`public/viewjs/schedule_forecast.js`); Panel Ejecutivo `/kpis` → «Dotación de mañana según ventas»; `/cost-centers` → columna «Laboral ppto. $» |
| Config | `WORKFORCE_SALES_PER_LABOR_HOUR`, `_EFFECTIVE_RATIO` (0.9), `_STORE_HOURS`, `_RECEIVING_WINDOW`, `_MIN_SHIFT_HOURS`, `_STANDARD_SHIFT_HOURS`, `_MIN_STAFF_OPEN`, `_FORECAST_BANDS`, `_FEFO_DAYS`, `_PAYROLL_CONNECTOR/_API_URL/_API_KEY` |

**Cálculo** (todo con datos reales; sin historia no se inventa venta):
- Ventas/tickets/unidades = 70 % media ponderada 4-3-2-1 de los 4 mismos días de semana + 30 % (promedio 28 d × índice del día) × estacionalidad (solo con > 1 año de historia, acotada 0.8–1.25) × eventos del calendario × corrección aprendida (mediana real/pronóstico de ≥ 3 días cerrados, acotada ±15 %). Se muestran promedios 7/14/30/60/90 d y los 8 mismos días anteriores.
- Productividad = ventas ÷ horas fichadas (28 d); si no hay, ÷ horas programadas; si no, configuración o tickets ÷ `WORKFORCE_TICKETS_PER_STAFF_HOUR`.
- Tareas: recepción (OC con entrega esperada + entregas de proveedor, sin repetir referencia), reposición (unidades proyectadas), FEFO (lotes que vencen en ≤ N días), conteos cíclicos, mermas (promedio), tareas de tienda pendientes, horas extra de eventos. **Sin doble conteo**: si la productividad sale de horas reales, solo se suma el excedente de cada tarea sobre su promedio diario de la misma ventana.
- Distribución horaria por la curva de ventas del mismo día de semana; recepción en su ventana; FEFO/conteos en las 3 primeras horas; resto parejo. Personas por franja = ⌈horas de la franja ÷ (horas de atención de la franja × eficiencia)⌉, con mínimos de cobertura. Programados = mínimo presente en toda la franja; las franjas no se suman. Cobertura % = Σ min(programados, necesarios) ÷ Σ necesarios por hora (el exceso de una hora no tapa el faltante de otra). Recomendados = máx(pico simultáneo, ⌈horas requeridas ÷ turno estándar⌉).
- Recomendación (simulación sin escribir): 1) mover turnos completamente en exceso, 2) ampliar turnos contiguos sin pasar el límite diario, 3) agregar turnos con personal disponible (sin conflictos, ausencias, descanso mínimo ni fuera de disponibilidad; menor costo primero), 4) solicitar cobertura. Horas extra solo con `WORKFORCE_OVERTIME_APPROVE`. Aplicar exige motivo (queda en `employee_shift_changes` por turno, lote deshacible), toma el bloqueo de escritura y deja los turnos nuevos en borrador: publicar la semana notifica a cada empleado (mecanismo existente).
- Alertas con causa y acción: ±% vs. día de semana promedio, faltante por franja (abre la franja), recepción en hora pico, horas redistribuibles, riesgo FEFO, costo sobre presupuesto, sin historia.
- Aprendizaje: cada consulta de hoy en adelante guarda el pronóstico (`labor_forecast_snapshots`); «Proyectado vs. real» compara venta, tickets, horas recomendadas/programadas/trabajadas, personal, productividad, tareas completadas/pendientes, costo laboral, quiebres (solo los del día en que se consultó) y merma; WAPE y sesgo alimentan la corrección.
- PAYCOM: `PayrollConnectorStatus()` queda **inactivo** salvo proveedor + URL + clave configurados; aun así no sincroniza hasta validar la API y permisos del proveedor (no hay credenciales ni documentación de API disponibles).

**Permisos**: ver pronóstico = `WORKFORCE_SCHEDULE_MANAGE` o `KPIS_VIEW` (sin gestión no ve turnos individuales); franjas/simular/aplicar/eventos/solicitudes = `WORKFORCE_SCHEDULE_MANAGE`; horas extra = `WORKFORCE_OVERTIME_APPROVE`; eventos de toda la red y estándares = acceso a todas las sucursales; aislamiento por sucursal en todo.

**Pruebas** (ejecutadas):
- `tests/dairy/test_labor_forecast.php` — 57 OK: escenario controlado ($1 000/día, 125 $/h) → 8 h de ventas, tareas 0,98 h, franjas [0, 2, 1, 1, 1], 13 h requeridas, 2 recomendados, cobertura 61,5 %, respuesta textual, ampliar 09–17→09–19 + agregar 09–13, horas extra según permiso, aplicar/motivo/deshacer, solicitudes, modo tickets sin doble conteo, evento ×1,2, presupuesto, WAPE/sesgo, corrección ×1,15, sucursal sin historia.
- `tests/dairy/http_labor_forecast.php` — 23 OK por HTTP en modo producción con claves por rol (sin permiso 403, indicadores solo lectura, gerente sin horas extra, gerente con horas extra, otra sucursal rechazada, eventos de red y estándares solo con acceso total).
- Regresión: `run.php` 454 OK, `http_sweep.php` 167 OK, `http_reliability.php` 125 OK.
- UI (Edge headless, sandbox): flujo de 16 pasos a 1366 y 390 px (respuesta, 6 KPI, 5 franjas, abrir franja → asignar, generar → aplicar → cobertura 75,4 % → 100 %, evento → alerta), 0 errores JS, 0 HTTP ≥ 400, sin scroll horizontal. Corregido durante la prueba: escape de comillas en atributos `data-c` del modal.
- No verificado: integración real con PAYCOM (sin acceso), notificación push con app cerrada (VAPID, pendiente de Etapa 24), estacionalidad con un año real de ventas (la BD demo empieza en 2026-01), cierre automático diario (el cierre se calcula al consultar; no hay programador). Pendiente P2: avisar en `/m` las solicitudes de cobertura abiertas.

### Etapa 30 — Plataforma 360°: auditoría de 33 rutas, sistema de diseño compartido, Ventas e integración (2026-10-21)
Detalle completo (matriz de cobertura por ruta, patrones de `/waste`, duplicaciones, pendientes): **`docs/DAIRY_PLATFORM_360.md`**.
- Línea base medida: 58 cargas (29 rutas × 1366/390 px) sin errores JS/red/desbordes; brechas de profundidad: texto < 11 px en 19 pantallas, sin exportar en 23, listas de 17 pantallas en móvil, `/sales` sin análisis.
- Hecho: `/sales` → Centro de Análisis (reutiliza `MobileManagerService::Sales` y `PosReconciliation`, sin cálculos nuevos); «Módulos conectados» al pie desde `SystemMapCatalog::CONNECTIONS` (+5 conexiones reales catalogadas); Exportar estándar en 14 pantallas; pestañas por URL; «Ver más» móvil; mínimo 11 px; `/today` 5/5 tarjetas con destino; regla de alerta **Lotes por vencer** (migración 0285).
- Bugs corregidos: alerta convertida en tarea móvil se cerraba sola (tarea huérfana); tarea asignada de noche no se ligaba al turno nocturno (fallo de `http_reliability` dependiente de la hora).
- Pruebas: `run.php` 432 OK (incluye `test_platform360` 31 y `test_platform360_roles` 3), `http_sweep` 170 OK, `http_reliability` 125 OK, barrido UI 0 errores.
- Pendiente de decisión: barra móvil Inicio·Tareas·Escanear·Alertas·Menú (hoy Operaciones·Perfil, elegida en Etapa 24); pérdidas evitadas; reportes personalizables.

### Etapa 31 — Prompt maestro «Intelligent Retail Operations»: auditoría incremental + simulador de escenarios de personal (2026-10-22)
**Auditoría (solo lo nuevo frente a Etapas 1–30)**: el prompt pide 15 secciones; ya existen y están probadas: pronóstico laboral por día/franja/hora/departamento con tareas sin doble conteo (Etapa 29), horarios con arrastrar-soltar, plantillas, intercambios, ausencias, correcciones de fichaje con aprobación y publicación con aviso (25), fichaje web/PWA `/m` por rol (24), POS con idempotencia y kardex (27), mermas con clasificación (`waste_reasons.akcal_classification`), política de aprobación, evidencia y recuperación (21), centro de alertas, `/sales` Centro de Análisis y matriz de 33 rutas sin duplicación (30), permisos por sucursal/acción en servidor (27). Decisiones vigentes: móvil = PWA `/m` (no Expo, Etapa 24); multiempresa = instancia por empresa (18).

Brechas reales encontradas:
| # | Brecha (sección del prompt) | Estado |
|---|---|---|
| 1 | §9 Simulador de escenarios de personal guardables y comparables (`SimulatorService` solo cubre merma vs. rebaja) | **Hecho en esta etapa** |
| 2 | §5 Ingesta de ventas POS externas: CSV de ventas, webhook/API con contrato y estado «pendiente de configuración» (`ImportService` no importa ventas) | Pendiente |
| 3 | §7 «Confianza de la predicción» explícita en cada recomendación (hoy: WAPE/sesgo en Precisión, sin nivel por recomendación) | Pendiente |
| 4 | §4 Ubicación/dispositivo en fichaje (solo si se habilita legalmente) | Pendiente (decisión del negocio) |

**Simulador** (`LaborForecastService::Simulate/SaveScenario/GetScenarios/DeleteScenario`, `Forecast(..., $scenario)`):
- Parámetros validados con rango: ventas ±% (−90…300), productividad ±% (−80…200), pallets adicionales (× `WORKFORCE_MINUTES_PER_PALLET`, def. 20 min, en la ventana de recepción), empleados ausentes (quita los N turnos más largos = peor caso), recortar horas (1 h por vez al turno que termina más tarde, mín. 1 h), adelantar reposición (a las 3 primeras horas).
- Calcula base y escenario con el mismo motor; devuelve 10 indicadores + Δ, horas sin cobertura, franja horaria prog./nec., riesgo (cobertura ≥95 bajo, ≥85 medio, resto alto) y cambios aplicados a turnos (en copias).
- Nunca escribe: `persist=false` forzado (no alimenta el aprendizaje), turnos clonados en memoria. Guardar solo inserta en `labor_scenarios` (migración 0286) con parámetros y resultado del momento.
- API: `POST /api/schedule/scenarios/simulate`, `GET|POST /api/schedule/scenarios`, `POST /api/schedule/scenarios/{id}/delete` — `WORKFORCE_SCHEDULE_MANAGE` + sucursal (también la del escenario al eliminar).
- UI: `/schedule` → Pronóstico → tarjeta «Simular escenario» (solo gestores) con comparación lado a lado de escenarios guardados.

**Pruebas ejecutadas**: `tests/dairy/test_labor_scenarios.php` 28 OK (cada palanca con cifras exactas, validación, turnos/aprendizaje intactos, guardar/listar/eliminar); `http_labor_forecast.php` 33 OK (+10: sin permiso/solo lectura 403, otra sucursal rechazada, eliminar escenario ajeno rechazado, turnos intactos); `run.php` 0 fallos; `http_sweep.php` 171 OK. UI Edge headless (sandbox, scratchpad 86674aa5 flow31.mjs): 15/15 a 1366 y 390 px, 0 errores JS, 0 HTTP ≥ 400, sin scroll horizontal.
- No verificado: escenarios con varios días a la vez (es por día), escenarios en `/m`.

### Etapa 32 — Ingesta de ventas de POS externos (§5 del prompt maestro, brecha #2 de la Etapa 31) (2026-10-22)
**Antes**: solo el POS propio (`/pos`) creaba ventas; `ImportService` no importaba ventas; no había webhook ni estado de integración.

| Pieza | Implementación |
|---|---|
| Servicio | `services/PosIngestService.php`: un solo camino para CSV y webhook → `SalesService::CreateSale` (stock FEFO, cuarentena, reservas, kardex, costo) → `sales` alimenta KPIs, `/sales` y el pronóstico laboral con la hora real del POS |
| Migración | `0287.php`: `pos_ingest_batches`, `pos_external_sales` (UNIQUE origen + id externo), permiso `POS_INTEGRATION` |
| API | `POST /api/pos/ingest` (JSON; firma `X-Dairy-Signature: sha256=HMAC` exigida si `POS_WEBHOOK_SECRET`), `POST /api/pos/import-csv`, `GET /api/pos/status`, `POST /api/pos/external/{id}/retry`; `dry_run` valida sin escribir |
| UI | `/sales` → pestaña **Integración POS** (`public/viewjs/pos_integration.js`): estado, CSV validar/importar, resultado por ticket con enlace al detalle, errores con Reintentar. El detalle de venta pasó a clic delegado |
| Config | `POS_WEBHOOK_SECRET`, `POS_TIMEZONE` (hora sin offset; def. la del servidor), `POS_CONNECTOR/_API_URL/_API_KEY` (extracción programada: **inactiva**; sin credenciales = «pendiente», con credenciales = «pendiente de validación»; no sincroniza) |

Reglas: exactamente una vez por ticket (reenvío idéntico = duplicado; mismo id con otro contenido = conflicto, no se toca); número de venta determinista `POS-<ORIGEN>-<id>` que permite recuperar una fila «processing» huérfana tras un corte sin duplicar; envíos simultáneos → 1 venta; ISO 8601 con zona → hora local; sin zona → `POS_TIMEZONE`; fecha futura rechazada; moneda distinta de `CURRENCY` rechazada (no se inventa tipo de cambio); producto por id, código de barras o SKU; precio del POS aceptado como hecho (no se valida contra lista) pero stock sí: sin stock → fallida con motivo, reintento tras recibir; devolución/anulación **total** → `CancelSale` (reintegra stock); **parcial no soportada** (se rechaza con motivo); devoluciones de un lote se procesan después de sus ventas; sucursal por id/código/nombre con aislamiento por usuario.

**Pruebas ejecutadas**: `test_pos_ingest.php` 41 OK; `http_pos_ingest.php` 20 OK (permisos 403, firma ausente/incorrecta 400, duplicado, dry-run, otra sucursal, reintento 403/200, CSV, 4 envíos simultáneos → 1 venta y −3 exacto, devolución); regresión `run.php` 0 fallos, `http_sweep` 172 OK, `http_reliability` 125 OK; UI Edge (scratchpad 86674aa5 flowpos.mjs + ventas_ui.csv) 16/16 a 1366 y 390 px, 0 errores JS/HTTP.
- No verificado / pendiente: conector de extracción real (no hay POS ni credenciales), programador de importaciones (no existe cron en la app), devoluciones parciales, impuestos por línea (se guarda el total del ticket), kardex con fecha del POS (`stock_log` usa la hora de importación; la venta guarda la hora real).

### Etapa 33 — Rediseño integral: identidad de `/waste` en todos los dashboards (2026-10-22)
**Auditoría incremental**: los 29 módulos del pedido ya existen con rutas, servicios y pruebas (matriz en `docs/DAIRY_PLATFORM_360.md` §2; simulador = `/ai-advisor#tab-simulator`). Componentes compartidos ya existentes y reutilizados (no se crearon duplicados): cabecera `.dairy-page-header`, `components.dairy_kpi_row`, `.dairy-subnav-pills`, `components.dairy_export`, `dairy_process_steps`, `dairy_change_history`, `dairy_related` (módulos conectados), `cardTable` móvil, `data-chunk`.

**Medición** (Edge headless, sandbox con copia de BD, `vis.mjs`/`vis2.mjs` del scratchpad 28364b29, 28 rutas × 1366/390 px) — diferencias reales contra la referencia:
| Elemento | Antes | Después |
|---|---|---|
| Título de página | `/waste` 20 px #343a40 (Bootstrap), `/today` 26 px, resto 22 px #111827 | 22 px/700 #111827 en las 28 |
| Subtítulo | `/waste` 13 px #6c757d, resto 14 px #64748b | 14 px `--dairy-text-muted` en todas |
| Caja de pestañas | `/alerts` y `/scan` sin caja | misma caja blanca con borde en todas |
| Encabezado de tarjeta | 3 variantes (16 px pad + 16 px; 14 px + 13,6/600; 14 + 13,6/700) | 14 px pad, título 14 px/700 (como `/waste`) |
| Botones de cabecera | 32 px (`btn-sm`) en 5 pantallas, 38 px en el resto | 38 px en todas |
| KPI `/today` | mín. 148 px | mín. 130 px (estándar); queda 147 px por contenido |

Ya coincidían (sin cambio): fondo #F5F7FA, primario #2563EB, borde #E5EAF1, radio de tarjeta 14 px, icono de cabecera 44 px, KPI estándar 130 px/valor 24 px, encabezado de tabla 11 px/600, sidebar y header 56 px.
Diferencia justificada que se mantiene: pestañas de `/waste` 12 px (10 pestañas en una fila); tarjetas «hero» de `/kpis` 193 px (incluyen variación y barra de meta).
Cambio: solo `public/css/dairy_glass.css` (bloque «Etapa 33», al final).

**Pruebas ejecutadas**: 28 rutas × 2 anchos → 0 errores JS, 0 desbordes horizontales, un solo estilo de título; `run.php` 0 fallos; `http_sweep.php` 172 OK.
**No hecho en esta etapa** (sigue en `docs/DAIRY_PLATFORM_360.md` §7): barra móvil Alertas·Menú (decisión), pérdidas evitadas, reportes personalizables/programados, pronóstico→MRP, Arturito con acciones aprobables, pruebas unitarias directas de Quality/Supplier/Simulator/CostCenter/AdvisorQa, accesibilidad medida con herramienta, dispositivos físicos.

### Etapa 34 — Pendientes reales de la Plataforma 360° (2026-10-22)
Resuelve `docs/DAIRY_PLATFORM_360.md` §7. Hallazgos nuevos al escribir las pruebas (corregidos): ver «Defectos».

| Pendiente | Implementación | Dónde |
|---|---|---|
| Pronóstico de ventas → MRP | `LaborForecastService::DemandOutlook` (eventos del calendario × corrección aprendida × estacionalidad anual, el mismo motor del pronóstico laboral) multiplica la demanda diaria del MRP; el patrón por día de semana ya lo aplicaba el MRP. Cada ítem trae `forecast_demand`; `/mrp` muestra qué ajustes se aplicaron | `MrpService`, `views/mrp.blade.php` |
| Pérdidas evitadas | `AlertCenterService::AvoidedLosses`: por cada alerta «Lotes por vencer», kardex del lote después de la alerta → vendido (evitada), merma, ajustes, pendiente / vencido con stock; % evitado. Atribución, no causalidad (se dice en pantalla) | `/alerts` tarjeta «Pérdidas evitadas» |
| Arturito con acciones | Recomendaciones con `action` (`draft_po`, `action_plan`, `fefo_task`). Vista previa sin escribir → confirmación explícita (`confirm: true` booleano) → el servidor valida el permiso propio de la acción y la sucursal → bitácora `dairy_ai_actions` (0288). Lo creado sigue su flujo: OC en borrador, plan con ahorro 0, tarea pendiente | `AiActionService`, `POST /api/ai/actions/preview|execute`, `GET /api/ai/actions` |
| Reportes personalizables y programados | Catálogo fijo de 9 conjuntos (ventas, ventas por producto, mermas, kardex, inventario, OC, turnos, auditorías, alertas) con columnas, período, sucursal, orden, límite (sin SQL libre); definiciones por usuario; programación diaria/semanal/mensual; cada ejecución guarda CSV (BOM, «;»), últimas 20. Corren con permisos y sucursales del dueño revalidados. Programador: `php bin/dairy_scheduler.php` (Programador de tareas/cron; también barre alertas) y, sin programador, al abrir `/reports` | `ReportBuilderService`, `ReportBuilderApiController`, 0289, `/reports` «Mis reportes» |
| Barra móvil | `/m`: Inicio · Tareas · Escanear · **Alertas** (avisos, insignia de no leídas) · **Menú** (Operaciones + Mi cuenta; `#/ops` y `#/profile` siguen funcionando). La campana del encabezado se oculta para no duplicar. SW `dairy-static-v4` | `mobile_app.blade.php`, `dairy_m.js`, `sw.js` |
| Accesibilidad medida | axe-core 4.10.2 (WCAG 2.0/2.1 A+AA): **antes 486 nodos críticos/serios en 10 reglas (1366 px) → 0 en 28 rutas a 1366 px y 35 vistas a 390 px (incl. `/m`)**. Módulo común en `dairy_ui.js` (nombres accesibles de controles solo-icono y campos sin etiqueta, estructura ARIA de pestañas, regiones desplazables con teclado, barras de progreso con nombre) + contraste: texto de estado con la variante `-dark`, `--dairy-text-muted` #5b6678 (antes #64748b = 4,43:1 sobre el fondo) | `dairy_ui.js`, `dairy_glass.css`, `dairy_m.css` |
| Pruebas directas que faltaban | Calidad, Proveedores, Simulador, Centros de costo, Arturito (AdvisorQa) | `tests/dairy/test_stage34.php` (73), `tests/dairy/http_stage34.php` (21, puerto 8099) |

**Defectos encontrados y corregidos**
- P1 Calidad: `ResolveQuarantine` podía resolverse dos veces (segunda baja de stock del mismo lote) y aceptaba cualquier resolución → validación de estado bajo bloqueo de escritura (`BeginWriteTransaction`) y solo `released`/`discarded_as_waste`. `MoveToQuarantine` permitía aislar más que el lote.
- Datos inventados eliminados: simulador (precio por defecto 3,99, costo = 65 % del precio, «Merma 100 %» como escenario base) → precio real obligatorio, costo real (lotes → última compra → costo de ventas), escenario A = vende a su ritmo de 28 días; supuesto del % vendido con rebaja visible y editable; hechos y supuestos separados; nunca escribe. Calidad: humedad 80 % por defecto y responsable «Supervisor de Calidad» fijo → nulo / usuario real. Proveedores: calificación 5,0, «Contado» y OTIF 100 % supuestos → «sin calificar / sin definir / sin entregas»; estado de entrega manual derivado de fechas y cantidades (antes se aceptaba «a tiempo» por defecto); puntaje 0–100 con base declarada.

- Margen con IVA: `SalesService` calculaba `gross_margin` = venta − costo (con IVA dentro) mientras `/m` usaba venta − IVA − costo → una sola definición neta de IVA; migración 0290 (idempotente, 0 ventas afectadas hoy).

**Pruebas ejecutadas**: `test_stage34` 75/75 · `http_stage34` 21/21 · E2E navegador (scratchpad 28364b29 `e34.mjs`) 14/14 a 1366 y 10/10 a 390 · axe 0 · barrido 28 rutas × 2 anchos 0 errores JS / 0 desbordes · ver regresión abajo.
**No verificado**: dispositivos físicos iOS/Android y lectores Zebra/Honeywell (no hay hardware en este entorno); lector de pantalla real (axe no reemplaza una prueba con NVDA/VoiceOver); el programador del sistema operativo no se dejó instalado (comando documentado).

## Rediseño HOY (/today) — 2026-10-23

Archivos: `views/today.blade.php`, `public/css/dairy_today.css`, `services/TodayCommandService.php`, `controllers/KpiController.php`, `public/css/grocy_menu_layout.css` (sidebar 248→232 px).

- Paleta semántica en tokens `--cc-*` (claro y `body.night-mode`); retícula de 12 columnas; breakpoints 1600/1400/1200/992/768/400; objetivos táctiles de 44 px en móvil; agenda pasa a tarjetas < 1200 px.
- Corrige bug previo: la vista no llamaba `require_frontend_packages(['chartjs'])`, así que sus gráficos nunca se dibujaban.
- KPI (6) con tendencia vs. ayer solo si existe snapshot (`today_snapshots`, ahora guarda también `alerts_action` y `approvals`).
- Gráfico A: `History()` cubre desde el 1 de enero y añade `overdue` (abiertas con plazo cumplido; solo alertas tienen plazo). «Año» agrupa por semana.
- Gráficos B y C reutilizan `/api/loss/year-comparison` y `/api/loss/causes` (misma fuente y permisos `LossIntelligenceApiController::READ`); sin fuente nueva.
- Gráfico D: `Hourly()` ahora devuelve severidad (critical/high/medium/low) con reglas documentadas en el método.
- Agenda: búsqueda, filtros estado/tipo/sucursal, paginación de 10 en cliente; columnas Prioridad · Tipo/descr. · Impacto · Responsable · Límite · Estado · Acción.
- Aprobaciones: foto de evidencia o del producto, código, solicitante; acciones existentes (segregación y auditoría las valida el servidor).
- Actividad: agrupa repetidos (acción+asunto+día → ×N) y muestra sucursal.
- Equipo: «Personas necesarias» desde `LaborForecastService::Forecast` (solo por sucursal, permiso WORKFORCE_SCHEDULE_MANAGE o KPIS_VIEW).
- Autorrecarga cada 5 min salvo interacción en curso.
