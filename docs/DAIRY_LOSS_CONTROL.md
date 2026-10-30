# DAIRY — Centro de Control de Mermas, Pérdidas y Prevención (Etapa 35, 2026-10-22)

## 1. Auditoría de lo existente
| Ya existía | Estado / decisión |
|---|---|
| `/waste` con 11 pestañas (`waste_overview.blade.php`), `WasteService` (registro, aprobación con segregación, idempotencia, duplicados) | Se conserva; navegación reorganizada en las 13 secciones pedidas |
| `AkcalMetricsService::GetControlCenterData` (KPI superiores, donut por categoría) | Fila de KPI y chips **eliminados** (cifras distintas a la nueva fuente = duplicación). Se conservan evolución diaria, top productos, vencimientos, FEFO |
| `LossPreventionService` (pérdida total, stock fantasma, ABC-XYZ, lotes inmovilizados) | Reutilizado (pérdida total dentro de «Impacto y recuperación», capital inmovilizado) |
| `CycleCountService::ScheduleForProducts` (conteo ciego) | Reutilizado por el ranking de riesgo |
| `QualityExpiryService` (temperaturas, cuarentena) | Reutilizado: la cuarentena se dispara desde la excursión |
| `waste_reasons` con códigos repetidos (M00 ×6), `loss_category` siempre «operativa» | Causales canónicas nuevas con código único; los motivos apuntan a una causal |
| `/m` (PWA) con su propio cálculo de mermas (ventas brutas) | Se agrega la tarjeta «Centro de Control» con la misma fuente y fórmulas que la web |

## 2. Componentes reutilizados
`dairy_export` (CSV/impresión), `DairyUI.post/formDialog`, carga diferida de pestañas, `DairyAccess` (permisos/sucursal), `BaseApiController::DairyRead`, `dairy_change_log` (auditoría del cambio de precio), Chart.js 2, `kpi()` de `dairy_m.js`.

## 3. Implementado
- **Fuente única de eventos** `LossIntelligenceService::Events` con ID único por evento (`W` merma aprobada, `A` ajuste de stock, `J` ajuste aprobado sin movimiento, `D` avería sin merma) y exclusiones anti doble conteo (misma transacción, avería ya enviada a merma, ajuste vinculado a merma).
- **10 KPI superiores** ($ y %), cada uno enlaza a su sección y de ahí al SKU/documento (kardex, `/document/waste|adjustment`).
- Fórmulas: % sobre ventas netas (sin impuesto), % de distribución, interanual, recuperación (recuperado / recuperable sin duplicar devoluciones a proveedor), margen antes/después (pp), valor de venta potencial separado, merma por hora (fichajes → turnos programados) y por empleado; `null` cuando no hay dato.
- **Secciones** (`/waste#tab-…`): 1 resumen · 2 operativa y comercial (+ formatos, sucursal, mapa de calor, $/m², $/1.000 u.) · 3 ajustes (faltantes/sobrantes) · 4 averías · 5 comparativo anual (cualquier año, acumulado/mensual, $/%/unidades, filtros sucursal/formato/departamento/clase/proveedor/SKU/causal, diferencia absoluta y %, meses pico, explicación generada con datos) · 6 causales (Pareto, barras, matriz con responsable/aprobación/tratamiento, remapeo de motivos) · 7 selectivos (puntaje de 8 factores, frecuencia, programación de conteos ciegos) · 8 precios (detección automática POS≠autorizado, código de barras = SKU de otro producto, diferencia entre sucursales; registro manual de góndola/etiqueta/promo/EPC; flujo de 8 pasos con evidencia obligatoria, segregación, cambio de precio solo al aprobar, referencia POS obligatoria; sobrecobro/subcobro/devolución/pérdida comercial; impacto «comprobado» solo tras validar) · 9 fruver y cárnicos (lotes con peso recibido, agua, sangre, deshidratación, maduración, golpes, transporte, deterioro, vencimiento, descarte, vendido; tolerancia técnica vs. evitable; teórico vs. real al cerrar; por familia; días de inventario y recomendación de liquidación) · 10 frío y vencimientos (excursiones con duración, lotes comprometidos, cuarentena; nunca libera solo) · 11 robo (solo confirmados; Top 10 por unidades y por $, % del total, interno/externo, tendencia; «bajo investigación» separado; acceso restringido) · 12 impacto y recuperación (+ pérdida total clasificada, VIS) · 13 investigación y planes.
- Causal indefinida (`INV`) ⇒ la merma nace en `investigating` con plazo y responsable, y se mantiene así al aprobar.
- API `/api/loss/*` (20 rutas) con permisos y alcance de sucursal; misma lógica para `/m`.
- Paleta sin azul (grafito, salvia, ámbar, rojo suave) en `.dlx-*`; móvil: filtros en hoja inferior, KPI 2 columnas, controles 44 px, sin scroll horizontal.

## 4. Errores corregidos
Duplicación de KPI con cifras distintas (Akcal vs. nueva fuente); códigos de causal repetidos; sin motivo activo para origen no confirmado; `/m` y web calculaban % sobre bases distintas (venta bruta vs. neta) — ahora la tarjeta de control usa la neta.

## 5–6. Evidencia y pruebas
- `php tests/dairy/test_loss_intelligence.php` — 76 verificaciones (fórmulas, acumulados, 2 años, Top 10, anti duplicación, causal INV, flujo de precios y segregación, pollo 100/3/2, fruver, frío, conteos, formatos, VIS).
- `php tests/dairy/run.php` — 708 OK, 0 fallos. `php tests/dairy/http_sweep.php` — 186 OK.
- Sandbox (scratchpad de la sesión): `e2e35.php` (HTTP, 15/15), `roles35.php` (empleado/supervisor/manager, 10/10), `ui35.mjs 1366` y `ui35.mjs 375 812` (16 pestañas: 0 errores JS, 0 red ≥ 400, 0 desbordes, todos los canvas dibujados), `m35.mjs` (/m = web: $219,30). Tiempos de pestaña < 250 ms.

## 7. Integraciones pendientes
POS en vivo para «Sincronización POS» (hoy: referencia manual obligatoria); lector RFID/EPC; sensores con eventos de puerta abierta; precio de góndola por lectura (hoy manual); seguros/cobros como fuente de recuperación interna.

## 8. Limitaciones y datos faltantes
- Sin histórico 2024/2025 en la BD: el comparativo lo dice y pide importar (no se simula).
- `branches.format` y `sales_area_m2` vacíos: cargar desde la tabla de sucursales (lápiz en «Operativa y comercial»).
- Sin fichajes ni turnos: merma por hora/empleado = «—».
- VIS sin definición del negocio: no se calcula (se registra definición y fuente en «Impacto»).
- Horas de reproceso: sin fuente.
- Precio autorizado es único por producto (no por sucursal).
- El descarte de un lote perecedero debe registrarse también como merma para descontar stock (el lote es analítico y no suma al total).

## 9. Checklist de funcionamiento real
- [x] Lógica, datos reales, permisos, validaciones, persistencia y pruebas en cada sección
- [x] Sin datos simulados; estados vacíos con acción (registrar / importar / ir a Calidad)
- [x] Trazabilidad KPI → sección → SKU → documento
- [x] Sin doble conteo entre merma, ajuste y avería
- [x] Web y móvil con la misma fuente
- [ ] Cargar formato/superficie de sucursales, dotación y fichajes
- [ ] Definir VIS
- [ ] Conectar POS/RFID/sensores
