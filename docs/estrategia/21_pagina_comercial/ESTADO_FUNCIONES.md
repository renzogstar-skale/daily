# Página comercial DAILY — implementación y estado de funciones (05/11/2026)

## Rutas públicas (sin sesión)

| Ruta | Contenido |
|---|---|
| `/` | Home comercial (visitantes sin sesión). Con sesión válida (modo producción) redirige a la app como antes |
| `/app` | Nueva entrada a la aplicación (el logo del layout interno apunta aquí) |
| `/features`, `/features/{sales,inventory,shrink,loss-prevention,dispatch,workforce,planning,analytics}` | Catálogo y página por módulo |
| `/solutions` | Soluciones por necesidad |
| `/industries`, `/industries/{supermarkets,retail,electronics,distribution,distribution-centers,multi-branch}` | Industrias |
| `/pricing` | 4 planes + comparativa + calculadora |
| `/demo`, `/contact` | Formularios |
| `/privacy`, `/terms` | Borradores legales |
| `POST /site/lead` | Guarda solicitudes en `data/site_leads.jsonl` (no toca la BD) |

Rutas con nombre `site.*` quedan exentas de autenticación en `BaseAuthMiddleware`. Idioma: `?lang=es|en` + cookie `daily_lang` (la misma de la app).

Archivos: `controllers/SiteController.php`, `helpers/DailySiteContent.php` (todo el texto ES/EN), `views/site/**`, `public/css/daily_site.css`, `public/js/daily_site.js`, `public/img/site/*.webp` (capturas de la empresa demo, saneadas en la v3). Prueba: `tests/dairy/site_e2e.js`.

## Auditoría: real / simulado / pendiente

| Función anunciada | Estado | Evidencia |
|---|---|---|
| Ventas por hora, metas, margen, departamentos, POS y devoluciones | Real | `/sales`, `/today/sales-executive`, `/sales/department-performance`, `/pos`, `/pos/returns` |
| Existencias, Kardex, conteos, escáner, traslados, ubicaciones, conciliación, ABC/XYZ | Real | `/stockoverview`, `/kardex`, `/inventory-control`, `/scan`, `/transfers`, `/locations/map`, `/reconciliation` |
| Mermas: causales, aprobaciones, investigaciones, rebajas, FEFO, Pareto | Real | `/waste*`, `/markdown/*`, `/reports/loss-executive` |
| Prevención: IMEI, custodia, casos con referencia CCTV, conteos sensibles | Real | `/loss-prevention*`, `SensitiveCaseService` |
| KPIs de encabezado en `/loss-prevention` y `/merchandise` | **Simulado** (cifras fijas) | ver `17_.../00_INTERNO_estado_de_funciones.md` |
| Panel Ejecutivo `/kpis` | **Maqueta** (marca de ejemplo y cifras fijas) | El sitio usa HOY (`/today`) como panel ejecutivo |
| Despachos, devoluciones, analítica de despacho | Real | `/orders`, `/dispatches`, `/returns`, `/retail/dispatch/analytics` |
| Turnos, cobertura, asistencia, tareas, vacaciones, turnos abiertos | Real | `/schedule*`, `/attendance`, `/workforce/tasks`, `/m/open-shifts` |
| MRP, sugeridos de compra, proveedores, órdenes | Real | `/mrp`, `/procurement`, `/suppliers` |
| "Simulaciones" de compras | **No existe** (SimulatorService eliminado) | Se anuncia solo "escenarios de cobertura laboral" (`/schedule/scenarios`) |
| Asesor IA | **Eliminado** | No se anuncia |
| Roles por sucursal, auditoría, ES/EN, importación de datos | Real | `DairyAccess`, `/audit-log`, `daily_i18n`, `/data-import` |

## Pendiente de configuración comercial

1. Precios: `price => null` en `DailySiteContent::Get()` → hoy muestra "Precio por cotizar".
2. Empaquetado de planes y comparativa: propuesta, validar con ventas.
3. Leads: hoy solo se guardan en `data/site_leads.jsonl`. Falta notificación por correo o CRM.
4. Datos de contacto (correo, teléfono, WhatsApp, dirección): no hay datos reales; el sitio solo ofrece formularios.
5. Textos legales: borrador; requiere revisión jurídica.
6. Dominio, `BASE_URL` público, analítica web y Open Graph con imagen propia (hoy usa una captura).
7. Testimonios, logos de clientes y métricas de resultados: no se publicaron porque no hay datos validados.
8. En modo `demo` la sesión no existe: `/` siempre muestra el sitio; "Iniciar sesión" lleva a `/today`.

## Recorrido "DAILY en acción" (7 pestañas)

Vistas = recortes de KPI y gráficos reales, sin menú lateral ni cabecera: `public/img/site/mod_{clave}_{1400|2400|m}.webp` (proporción 1,9:1; `_m` = piezas apiladas para móvil). Generador: `compose.py` (solo recorta y une zonas; no escala desigual).

| Pestaña | Clave | Fuente |
|---|---|---|
| Panel Ejecutivo | exec | v3 `01_ejecutivo/today.png` (8 KPI + ventas vs. meta por hora + ventas por departamento) |
| Ventas | sales | v3 `05_ventas/sales.png` (6 KPI, curva de ventas, top productos) |
| Inventario | inventory | v3 `03_inventario/stockoverview.png` (8 KPI, evolución, productos en alerta) |
| Mermas | shrink | v3 `02_mermas/waste_centro-control.png` (10 KPI, evolución, tipo, departamento, top 10, causas) |
| Despachos | dispatch | captura en vivo `/dispatches` y `/returns` del 05/11/2026 (flujo OMS, 8 KPI, devoluciones, conciliación) |
| Recursos Humanos | workforce | v3 `06_personal/attendance.png` + `scheduling.png` (asistencia, 8 KPI, ventas vs. horas, SPLH, costo laboral) |
| Prevención | loss-prevention | v3 `02_mermas/loss_executive.png` (6 KPI, diferencias en investigación, prioridades del día) |

No hay en DAILY un gráfico de "despachos por día" ni "incidentes por causa" en prevención; se usaron conciliación por etapas y prioridades del día. Los KPI del encabezado de `/loss-prevention` tienen cifras fijas y los de `/loss-prevention/devices` no cuadran con su tabla (380 registrados vs. 200 equipos) sin verificar su origen: no se usaron.

## Vistas animadas del recorrido (05/11/2026)

El recorrido ya no muestra imágenes: cada pestaña es una vista HTML/SVG animada (`views/site/partials/live.blade.php`, datos en `helpers/DailySiteLive.php`, `public/css/daily_site_live.css`, `public/js/daily_site_live.js`). Sin React: el sitio es Blade sin build y no se agregaron dependencias.

- Cifras: KPI, tablas y leyendas copiadas exactas de las capturas `mod_*`; puntos de curvas y barras sin etiqueta leídos del gráfico. El botón «Ver captura real» abre la captura original.
- Animación: contadores con requestAnimationFrame (easeOutCubic, ~1,2 s, cifras tabulares), líneas por `clip-path`, barras por `transform`, donut por máscara + rotación, entradas escalonadas, pulso discreto (3 ciclos) en alertas críticas.
- Se anima al entrar en pantalla, al cambiar de pestaña, al usar un filtro (Top 5 ventas/unidades; departamento del donut) o «Repetir animación». Un rerender no reinicia. «Recorrido automático» es opcional, se pausa fuera de pantalla, con el cursor encima o al elegir una pestaña.
- `prefers-reduced-motion`: valores finales sin animación. Sin JS: valores finales.
- Sin datos reales para: Pareto de inventario, despachos por día, incidentes por causa en prevención, distribución de turnos, demanda por hora. Se usaron en su lugar: evolución del valor, conciliación por etapa, diferencias en investigación, asistencia y horas vs. ventas por día.
- Prueba: `tests/dairy/site_e2e.js` (bloque del recorrido) verifica que las cifras terminan en el dataset y que la altura es constante.
