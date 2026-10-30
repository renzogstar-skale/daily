# DAIRY — Plataforma 360° (Etapa 30, 2026-10-21)

Auditoría verificable de las 33 rutas pedidas, patrones del Centro de Control (`/waste`), cambios realizados y pendientes reales.
Todo lo que aquí se afirma sale de un script ejecutado o de una prueba; lo no verificado se marca como tal.

## 1. Método y evidencia

| Fuente | Qué mide | Cómo repetirlo |
|---|---|---|
| Análisis estático | Por vista: cabecera estándar, KPI, pestañas, tablas, gráficos, modales, exportación, historial, pasos, estados vacíos, filtros, estilos en línea, enlaces a otros módulos | `static_audit.php` (scratchpad de la sesión) sobre `views/` + `public/viewjs/` |
| Barrido UI (Edge headless, sandbox con copia de la BD) | Errores JS, red ≥ 400, desborde horizontal, tablas cortadas, objetivos táctiles < 40 px, texto < 11 px, nº de KPI/pestañas/tablas/gráficos, botón Exportar, largo en pantallas | `uiaudit.mjs <ancho> <rutas…>` a 1366 y 390 px |
| Conexiones | Conexiones operativas verificadas por patrón en el código | `SystemMapCatalog::CONNECTIONS` (lo comprueba `/system-map`) |
| Pruebas | Unitarias (copia de BD), HTTP por rol, HTTP con concurrencia real | `php tests/dairy/run.php`, `http_sweep.php`, `http_reliability.php` |

**Línea base (antes de cambiar nada)**: 29 páginas × 2 anchos = 58 cargas, **0 errores JS, 0 red ≥ 400, 0 desbordes horizontales**. Los problemas eran de profundidad y consistencia, no de roturas:
texto < 11 px en 19 de 29 pantallas de escritorio (hasta 102 elementos en `/sales`), sin exportación en 23 de 29, listas de 17 pantallas en móvil (`/alerts`, `/sales`),
`/sales` sin KPI, sin análisis, sin devoluciones ni conciliación, y 2 de 5 tarjetas de `/today` sin destino.

## 2. Matriz de cobertura funcional (estado tras la Etapa 30)

Leyenda: ✅ completo y probado · 🟡 funciona con brechas · ⛔ falta. «Conectado con» = conexiones verificadas del catálogo (entrada ← / salida →).

| Ruta | Para qué sirve · herramientas | Acciones (dónde se guarda) | Conectado con | Brechas abiertas | Móvil (390 px) | Pruebas |
|---|---|---|---|---|---|---|
| `/today` | Trabajo del día: decisiones por urgencia, aprobaciones, tareas, alertas, merma y margen del mes | Abre el flujo de cada decisión; 5/5 tarjetas con destino (antes 3/5) | → alertas | Sin «recomendaciones contextuales» propias (las da Arturito) | ✅ 6,6 pantallas | `run.php` (agenda) |
| `/alerts` | Alertas accionables con responsable, plazo, aprobación de cierre | Asignar, atender, resolver, aprobar, descartar, historial (`dairy_alerts`, `dairy_alert_events`) | ← auditorías, control inv., hoy, **calidad (nuevo)** · → móvil | — | ✅ 10,4 pantallas (antes 17,2), «Ver más» | `test_loss_prevention`, `test_platform360` §4 |
| `/kpis` | Panel ejecutivo: ventas, CMV, margen, merma, sucursales, tendencia, dotación de mañana | Lectura + enlaces a POS, merma, IA, centros de costo | ← ventas, mermas · → IA | Sin drill-down a transacción desde el gráfico (sí a `/sales`) | 🟡 11,8 pantallas | `run.php` |
| `/waste` | **Referencia**: 10 pestañas (control, prevención, registro, causales, investigaciones, planes, recuperación, FEFO, conteos, pérdidas) | Registrar, investigar, planes, reclamos, conteos, exportar | ← calidad, piso · → KPIs, centros de costo, **reportes (nuevo)** | — | 🟡 8,8 pantallas | `run.php`, `test_reliability`, `http_reliability` D–G |
| `/waste/report` | Registro guiado con evidencia, lote y causal | Crea `waste_records` (pendiente o aprobado según política) | ← móvil · → aprobaciones | — | ✅ | `http_reliability` D–F |
| `/waste/approvals` | Cola de aprobación con segregación | Aprobar/rechazar → descuenta stock (kardex) | ← registro · → stock, piso | — | ✅ | `http_reliability` G |
| `/quality` | FEFO, cadena de frío, cuarentena, inspección | Temperatura, inspección, aislar lote | ← stock, retiros · → POS (rebaja), mermas, **alertas (nuevo)** | Sin prueba unitaria directa de `QualityExpiryService` | 🟡 11,3 pantallas | indirectas (`test_traceability`) |
| `/recalls` | Retiro de lote en red con confirmación por sucursal | Crear, confirmar separación, cerrar | → calidad | — | ✅ | `test_loss_prevention` |
| `/stockoverview` | Stock real/disponible, min., vencimientos | Diario, entradas, reportes | ← ventas, aprob., MRP, traslados, sucursales · → calidad, kardex, mapa | Días de inventario y rotación viven en MRP/Control (no se duplican aquí) | 🟡 6,9 pantallas | `run.php` |
| `/scan` | Conteo, movimiento, recepción, picking por escáner (GS1) | Movimientos con idempotencia | ← pedidos · → MRP, conteos | Sin SDK Zebra/Honeywell (cámara/teclado sí) | ✅ | `test_operations`, `test_orders` |
| `/locations/map` | Ocupación, valor, vencimientos por ubicación | Lectura | ← stock (nuevo) | — | ✅ | `http_sweep` |
| `/transfers` | Envío/recepción en dos pasos, diferencias como merma | Crear, recibir, anular (bloqueo de escritura) | → stock | — | ✅ | `http_reliability` G4 |
| `/kardex` | Movimientos valorizados por producto/lote | Lectura, exportación | ← stock, trazabilidad, **conteos (nuevo)**, **ventas (enlaces por SKU)** | — | ✅ | `run.php` §I (Δ kardex = Δ stock) |
| `/traceability` | Lote de punta a punta | Búsqueda por lote/serie/documento, imprimir | ← MRP · → kardex | — | ✅ | `test_traceability` |
| `/inventory-control` | Stock fantasma, lotes inmovilizados (baja rotación), ABC-XYZ, conciliación POS | Lectura + alertas | → alertas | — | 🟡 7 pantallas | `test_loss_prevention` |
| `/inventory` | Conteo físico | Ajuste con transacción de inventario | ← escáner · → kardex (nuevo) | Pantalla Grocy heredada (sin KPI) | ✅ | `run.php` (conteo ciego) |
| `/branches` | Estructura multisucursal, accesos | Asignar sucursales a usuarios | → stock | — | 🟡 6,4 pantallas | `test_branch_access` |
| `/mrp` | VMD, SS, ROP, cobertura, OC, aprobación, recepción con lote | Calcular, crear/aprobar/recibir OC | ← ventas, escáner · → proveedores, stock, trazab., horarios, IA | Pronóstico de ventas no alimenta la compra (usa VMD) — propuesta `p_forecast_mrp` | 🟡 9,8 pantallas | `run.php`, `http_reliability` G3 |
| `/suppliers` | Proveedores, cotizaciones, facturas (3-way match), cumplimiento | Alta, cotización, factura | ← MRP | Cuentas por pagar (propuesta `p_ap`); sin prueba unitaria directa | ✅ | indirectas |
| `/ai-advisor#tab-simulator` | What-if rebaja FEFO vs. merma | Simular | — | Sin prueba de `SimulatorService` | ✅ | ⛔ |
| `/workforce` | Piso de tienda: tareas, equipos, merma exprés | Completar tareas, registrar | ← aprob., auditorías · → mermas | — | ✅ texto ≥ 12 px (antes 34 elementos < 11 px) | `run.php`, `test_offline_sync` |
| `/schedule` | Turnos, plantillas, disponibilidad, ausencias, intercambios, correcciones, pronóstico laboral | Crear/editar/publicar/deshacer turnos, aplicar recomendación | ← ventas, MRP, móvil | Decidir correcciones de fichaje en web (propuesta `p_adj_web`) | ✅ | `test_schedule_mgmt` 42, `test_labor_forecast` 57, `http_labor_forecast` 23 |
| `/audits` | Checklist por sucursal con evidencia | Ejecutar, hallazgo → tarea | → piso, alertas | — | ✅ | `test_loss_prevention` |
| `/pos` | Venta FEFO con rebaja activa e idempotencia | Venta (`sales`, `sale_items`, stock_log) | ← calidad · → ventas | Promociones generales (propuesta `p_promos`) | ✅ | `http_reliability` G4, `test_platform360` §2 |
| `/sales` | **Rehecha**: Centro de Análisis — transacciones, análisis diario/horario, departamento, venta vs. merma, sucursales, SKU, devoluciones, conciliación con inventario | Detalle, anular (reintegra stock), exportar, ir a kardex/MRP | ← POS, pedidos · → stock, MRP, horarios, KPIs, centros de costo, reportes | — | ✅ 8,6 pantallas (antes 17) | `test_platform360` §1–2 |
| `/orders` | Pedidos con reserva, picking FEFO, despacho como venta | Crear, reservar, pickear, despachar | → ventas, escáner | Olas de picking (propuesta `p_waves`) | ✅ | `test_orders` 35 |
| `/reports` | Reportes ejecutivos, censo de merma, sucursales, rebajas | Lectura, exportar | ← ventas, mermas (nuevo) | Reportes personalizables por el usuario ⛔ | 🟡 4,8 pantallas | `http_sweep` |
| `/cost-centers` | Margen y merma por sucursal/categoría, presupuesto | Lectura por mes | ← ventas, mermas · → IA | Sin prueba unitaria directa de `CostCenterService` | ✅ | indirectas |
| `/ai-advisor` | Arturito: preguntas sobre KPIs, MRP, centros de costo; anomalías | Preguntar (solo lectura) | ← KPIs, MRP, centros de costo | No crea tareas/órdenes (propuesta `p_ai_actions`; correcto: no ejecuta operaciones sensibles) | ✅ | ⛔ sin prueba de `AdvisorQaService` |
| `/system-map` | Mapa vivo: módulos, conexiones verificadas, estado, pruebas | Lectura, exportación | (todas) | — | ✅ | `system_map_build.php` |
| Datos maestros | Productos, ubicaciones, unidades, grupos, usuarios, permisos, importación CSV | CRUD con historial (`dairy_change_log`) | → productos | Respaldo/restauración desde la app (propuesta `p_backup`) | heredado | `run.php` |

## 3. Patrones del Centro de Control convertidos en sistema de diseño

| Patrón de `/waste` | Componente compartido | Estado |
|---|---|---|
| Cabecera con icono, título, subtítulo y acciones | `.dairy-page-header` / `.dairy-page-icon` / `.dairy-page-actions` | Ya existía; 28/29 rutas lo usan (`/today` usa su variante `.dh-*`) |
| Fila de 6 KPI uniformes con tono y destino | `components.dairy_kpi_row` | Ya existía; añadido a `/sales` |
| Pestañas tipo píldora | `.dairy-subnav-pills` + **apertura por `#tab-x` y URL compartible** (`.dairy-tabs-url`) en `dairy_ui.js` | Nuevo (antes cada vista copiaba su propio código de hash) |
| Menú «Exportar» | `components.dairy_export` + `DairyUI.exportTables(csv / print)` (tablas visibles de la pestaña activa, CSV UTF-8 con BOM, omite «Acciones») | **Nuevo**, en 15 pantallas (14 + `/sales`) |
| Pasos del proceso | `components.dairy_process_steps` | Ya existía |
| Historial de cambios | `components.dairy_change_history` | Ya existía |
| Navegación a módulos relacionados | `components.dairy_related` + `Helpers\DairyContextNav` (lee `SystemMapCatalog::CONNECTIONS`, filtra por permiso) | **Nuevo**, en todas las pantallas del menú salvo POS, escáner y registro de merma |
| Tablas → tarjetas en móvil | `dairy_mobile.js` (`cardTable`) | Ya existía (se reutilizó; no se creó un segundo mecanismo) |
| Listas largas en móvil | `table[data-chunk="N"]` → «Ver N más» en `dairy_ui.js` | **Nuevo** (`/alerts`, `/sales`) |
| Escala tipográfica | `--dairy-fs-2xs` = 11 px en escritorio (12 px en móvil) y `.badge` ≥ 11 px | **Corregido**: 0 textos < 11 px en las 29 rutas |

## 4. Duplicaciones: reales vs. reutilización legítima

| Hallazgo | Tipo | Decisión |
|---|---|---|
| ~25 consultas `SUM(total_amount) … status='completed'` en 9 servicios | Duplicación de consulta, **misma definición** | Verificado con prueba: venta, tickets, descuentos y Σ sucursales coinciden entre `/sales`, `/m`, `/branches` y `/waste` (`test_platform360` §1). Riesgo latente documentado: margen móvil = (total − impuesto) − CMV; `sales.gross_margin` = total − CMV; hoy `tax_amount` siempre es 0, así que coinciden. Si se activa IVA, unificar en un `SalesMetrics` |
| Análisis de ventas web vs. móvil | Reutilización legítima | `/sales` usa **el mismo** `MobileManagerService::Sales` del gerente en `/m`; la conciliación usa `LossPreventionService::PosReconciliation` (la misma de Control de Inventario y del Centro de Alertas) |
| Vencimientos en `/today` (agenda) y en Centro de Alertas | Reutilización legítima | Misma condición (hoy … +N días, fuera de tránsito); Hoy resume, Alertas gestiona por lote con responsable y cierre |
| Código de pestaña por `#hash` en varias vistas | Duplicación de código | Centralizado en `dairy_ui.js`; las vistas antiguas siguen funcionando (no se tocaron) |
| Conversión tabla → tarjetas | Ya central | Se evitó crear un segundo mecanismo en `/sales` |
| 41 líneas de venta sin movimiento de stock en 30 días (51 en 90) | **Datos**, no código | Ventas demo insertadas directo en BD (ya documentado en DAIRY_DATOS_REALES.md); ahora visibles en `/sales` → Conciliación. Las ventas del POS sí mueven stock (prueba §2). No se borró nada |

## 5. Cambios de la Etapa 30

| Archivo | Cambio |
|---|---|
| `views/sales_history.blade.php`, `controllers/SalesController.php` | Centro de Análisis de Ventas: período único (30 días por defecto, accesos 7/30/90), 6 KPI con comparación al período anterior, 5 pestañas, gráficos diario/horario, búsqueda por SKU, enlaces a kardex/MRP, conciliación con inventario; escape HTML en el detalle del ticket |
| `helpers/DairyContextNav.php`, `views/components/dairy_related.blade.php`, `views/layout/default.blade.php` | «Módulos conectados» al pie de cada pantalla, desde el catálogo verificado y filtrado por permiso |
| `views/components/dairy_export.blade.php`, `public/js/dairy_ui.js` | Exportar CSV/Imprimir estándar; pestañas por URL; paginación móvil `data-chunk` |
| 14 vistas (`alert_center`, `recalls`, `quality_expiry`, `stockoverview`, `transfers`, `inventory_control`, `branches`, `mrp`, `suppliers_procurement`, `schedule`, `store_audits`, `orders`, `cost_centers`, `waste_approvals`) | Menú Exportar |
| `public/css/dairy_glass.css` | Estilos del bloque de módulos conectados, barras de participación, mínimo 11 px, `.badge` ≥ 11 px |
| `views/today.blade.php` | «Decisiones» abre la lista de decisiones; «Valor en riesgo» abre `/alerts` |
| `migrations/0285.php`, `services/AlertCenterService.php` | Regla **Lotes por vencer** (umbral 3 días, plazo 24 h): una alerta por lote, sin duplicar; flujo vencimiento → alerta → tarea móvil → cierre con la acción realizada |
| `services/AlertCenterService.php` | **Bug corregido**: el barrido cerraba sola una alerta ya convertida en tarea móvil pendiente (la tarea quedaba huérfana y la acción no se registraba) |
| `services/MobileWorkService.php` | **Bug corregido** (encontrado por `http_reliability` a las 21:18): tarea asignada de noche con vencimiento después de medianoche no se ligaba al turno nocturno en curso |
| `helpers/SystemMapCatalog.php` | +5 conexiones reales que no estaban catalogadas (calidad→alertas, conteo→kardex, ventas/mermas→reportes, stock→mapa de ubicaciones) |
| `public/js/grocy.js` | El resaltado heredado del `#hash` ya no enmarca paneles de pestaña |
| `tests/dairy/test_platform360.php` (31), `test_platform360_roles.php` (3) | Pruebas nuevas |

## 6. Resultados de pruebas (ejecutadas el 2026-10-21)

| Prueba | Resultado |
|---|---|
| `php tests/dairy/run.php` (todas las `test_*.php`, copia de BD) | **432 verificaciones, 0 fallos** |
| `test_platform360.php` | 31/31: consistencia de ventas web = móvil = sucursales = Centro de Control; venta POS → +1 ticket, +$10, kardex, sin entrar en conciliación; anular → devoluciones; vencimiento → alerta → sin duplicar → tarea móvil → no se cierra sola → completar exige nota → cierra con la acción; tarea nocturna ligada al turno |
| `test_platform360_roles.php` | 3/3: usuario solo de ventas no ve enlaces a stock, MRP, KPIs ni centros de costo |
| `php tests/dairy/http_sweep.php` | **170, 0 fallos** (todas las rutas GET sin 5xx; ninguna > 2 s) |
| `php tests/dairy/http_reliability.php` | **125, 0 fallos** (tras corregir el bug de tareas nocturnas; antes 1 fallo) |
| Barrido UI 29 rutas × {1366, 390} px | 0 errores JS, 0 red ≥ 400, 0 desbordes; texto < 11 px: de 19 pantallas afectadas a 0; Exportar: de 6 a 20 pantallas (14 nuevas + `/sales` rehecha); `/alerts` móvil 17,2 → 10,4 pantallas; `/sales` móvil 17 → 8,6 |
| Flujo `/sales` en navegador | KPI, 5 pestañas, gráficos dibujados, búsqueda SKU (1 de 10), CSV con encabezados, detalle de ticket, bloque de módulos conectados |
| `system_map_build.php --no-tests` | 44 conexiones verificadas, 0 no verificadas |

## 7. Pendientes reales (no hechos en esta etapa)

Priorizados por impacto. Ninguno es un fallo abierto; son alcance del pedido 360° que requiere decisiones o más etapas.

1. **Móvil — barra Inicio · Tareas · Escanear · Alertas · Menú**: hoy `/m` usa Inicio · Tareas · Escanear · Operaciones · Perfil (decisión de la Etapa 24). Cambiarla es una decisión de producto: confirmar antes de tocar.
2. **Pérdidas evitadas**: la alerta de vencimiento registra la acción, pero no se mide aún si el lote terminó vendido o en merma (requiere cruzar `stock_log` del lote al cerrar).
3. **Reportes personalizables** (`/reports`): no existen; hoy son fijos con exportación.
4. Pronóstico de ventas → MRP (`p_forecast_mrp`), Arturito proponiendo tareas/órdenes con confirmación (`p_ai_actions`), cuentas por pagar, promociones, olas de picking, respaldo desde la app, push con app cerrada (VAPID) — ya registrados como propuestas en el Mapa del Sistema.
5. Pruebas unitarias directas que faltan: `QualityExpiryService`, `SupplierProcurementService`, `SimulatorService`, `CostCenterService`, `AdvisorQaService`.
6. Unificar el cálculo de ventas en un solo servicio antes de activar impuestos (ver §4).
7. No verificado: dispositivos físicos (iPhone/Android reales, lectores Zebra), tablet en orientación horizontal, rendimiento con datos de producción, accesibilidad medida con herramienta.

### 7.1 Estado al 2026-10-22 (Etapa 34, detalle en `DAIRY_AUDIT.md`)

| # | Pendiente | Estado |
|---|---|---|
| 1 | Barra móvil Inicio · Tareas · Escanear · Alertas · Menú | ✅ hecho (Operaciones y Perfil dentro de Menú; rutas antiguas conservadas) |
| 2 | Pérdidas evitadas | ✅ `/alerts` → resultado por lote alertado desde el kardex |
| 3 | Reportes personalizables | ✅ `/reports` → «Mis reportes» (9 conjuntos, programación, CSV) + `bin/dairy_scheduler.php` |
| 4 | Pronóstico → MRP · Arturito con acciones | ✅ ambos; acciones con vista previa, confirmación, permiso propio y bitácora. Siguen como propuestas: cuentas por pagar, promociones, olas de picking, respaldo desde la app, push con app cerrada (VAPID) |
| 5 | Pruebas directas de 5 servicios | ✅ `test_stage34.php` (destapó 1 P1 en cuarentena y datos inventados en 3 servicios; corregidos) |
| 6 | Una sola definición de margen con IVA | ✅ `sales.gross_margin` = venta − IVA − costo (antes incluía el IVA), igual que `/m`; migración 0290 recalcula solo ventas con impuesto (0 en la BD actual). Margen por departamento sigue sin IVA por línea (el POS externo informa el impuesto por ticket) |
| 7 | Accesibilidad medida | ✅ axe-core: 486 → 0 nodos críticos/serios (WCAG A/AA). ⛔ No verificable aquí: dispositivos físicos, lectores Zebra, lector de pantalla real |
