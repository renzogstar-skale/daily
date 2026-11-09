# 20.05 · Informe final de la reestructuración (4–5 de noviembre de 2026)

Pruebas ejecutadas: `tests/dairy/test_shared_filters.php` (PHP, copia temporal de la BD) y `tests/dairy/e2e_restructure.js` (Playwright + Edge, escritorio 1440×900 y móvil 390×844, contra `http://localhost:8080`). Regresión: `test_today_panel_sync` (92/92), `test_kpi_consistency` (34/34), `test_loss_prevention_360` (41/41), `test_loss_prevention_photo_replica` (OK).

## Tabla de cambios

| Módulo | Problema detectado | Cambio aplicado | Estado | Prueba |
|---|---|---|---|---|
| Menú | Orden distinto al pedido; Mermas y Prevención separados; 8 pestañas internas duplicadas en el menú; Pedidos en Ventas | Nuevo orden (Supervisión, 1–8, Indicadores Financieros); Mermas y Prevención en una sección (7 visibles, 8 ocultas con ruta activa); Pedidos y Entregas en Despachos; Historial de ventas y Productos agotados como entradas | Verificado | E2E «Orden de categorías» y «Ruta …» para cada enlace del menú; rutas antiguas (`/orders`, `/reports`, `/inventory`, anclas de `/waste` y `/loss-prevention`) responden 200 |
| Reportes y Analítica | Sección genérica | Renombrada «Indicadores Financieros»: Rentabilidad y Margen, Centros de Costo, Reportes e Informes, Arturito IA. `/reports` intacto | Verificado | E2E de rutas |
| Filtros | Cada tablero con parámetros propios; «Departamento» significaba cosas distintas en Ventas y HOY | `DailyFilters`: empresa, sucursal, departamento, categoría, subcategoría, producto/SKU y fechas con un solo nombre por filtro; cada tablero declara qué filtros aplica; persistencia entre tableros; «Restablecer» | Verificado en Ventas, Agotados y enlaces a HOY/Panel/Mermas/Calidad/Stock | E2E «Persistencia…», «Restablecer limpia los filtros» |
| Ventas | 6 KPI + secciones largas; curvas por hora y mes con números fijos; sin productividad, fin de semana ni tráfico | Tablero de 5 bloques (evolución diaria/hora/semana/acumulada; fin de semana y año anterior; ventas por colaborador y por hora trabajada; Top 5 con SKU y detalle; tráfico «Sin información»). Resto en «Análisis complementario». Pestañas reordenadas (clientes y POS al final). Botones de curva con datos fijos retirados | Verificado | PHP: ventas = DailyMetricsService para 5 combinaciones sucursal/departamento; series, semanas y horas suman el total. E2E: bloques, filtros por departamento, SKU y fechas |
| Ventas · departamento | Cifras de otros departamentos al filtrar | Con departamento/SKU, el análisis complementario se oculta y las demás pestañas lo advierten; productividad «No aplica» (el personal no se asigna por departamento) | Verificado | E2E «Top 5 solo de Carnes», «Análisis complementario oculto» |
| KPI | Cifras sin período, definición ni fuente | Componente `daily_kpi`: nombre, valor, unidad, período, alcance, definición, fuente, actualización y comparación | Verificado en Ventas, Rentabilidad, Agotados y Panel | E2E «KPI con período, definición y fuente», «KPI con período correcto» |
| Margen | Sin vista diaria/mensual/acumulada; márgenes de −607 % | Margen diario, mensual y acumulado del año en Rentabilidad; si el costo supera 3× la venta muestra «Dato no confiable» | Verificado (hoy todos muestran «Dato no confiable» por C1) | E2E «Margen diario/mensual/acumulado» |
| Inventario | Sin búsqueda de agotados por SKU | `/inventory/stockouts`: agotado / sin disponible / bajo mínimo, departamento, sucursal, stock registrado y disponible, último movimiento, reposición (OC o traslado), detalle en Kardex y ventas | Verificado | PHP: agotados = HOY (`StockStatusCounts`) en 9 combinaciones. E2E: búsqueda por SKU, detalle, filtro por departamento |
| Panel Ejecutivo | Réplica de una foto con cifras fijas (incluido «98,1 %»); marca «Alkosto» | 6 indicadores reales con la misma fuente que HOY (ventas, margen, merma, disponibilidad del surtido, asistencia, novedades de calidad). La maqueta restante quedó plegada con la leyenda «cifras de ejemplo». «Alkosto» retirado de Panel y Prevención | Verificado | E2E «Panel con indicadores reales», «Sin cifra fija», «Sin marca de terceros» |
| Identidad | Mensaje anterior; ícono de vaca en Mermas | «DAILY — Gestión, Control y Beneficios» en inicio de sesión y logotipo; `fa-cow` reemplazado. El monograma «D» se mantiene como símbolo | Implementado | Revisión de la vista de inicio de sesión |
| Documentación | Sin mapa por módulos | `02_mapa_mental.md` (mapa + tabla de ramas), `03_retail_y_produccion.md`, `04_propuesta_kpi_por_modulo.md`, `SystemMapCatalog` con el mismo orden | Implementado | — |
| Idioma EN | Etiquetas nuevas sin traducción | Entradas agregadas a `localization/daily/en/dairy_navigation.json` y catálogo reconstruido | Implementado (no auditado en navegador) | — |

## Cambios implementados y probados
Todo lo marcado «Verificado» arriba.

**Resultado E2E (5-nov-2026): 118 verificaciones, 1 fallida.** La falla es previa y ajena a estos cambios: dos imágenes que `SalesController`/`CustomerOrderService` referencian y no existen (`/img/control_center/bebidas_refrigeradas.jpg`, `queso_cottage.jpg`). El explorador REST heredado (`/api`) tiene un error de consola propio (`require is not defined`) que se excluyó. Se corrigió un error de consola de Chart.js en pestañas ocultas de Ventas (`public/js/dairy_charts.js`). PHP: 75/75.

## Cambios pendientes
1. **Restaurar la BD de demostración** (C1): los costos y cantidades fueron alterados por scripts de calibración. Respaldo coherente: `data/grocy_es.bak_pre_nombres_demo.db` (3-nov 17:35, margen ≈ 35 %). Requiere aprobación (se pierden los cambios posteriores, incluidos los nombres demo de ese día).
2. Panel Ejecutivo: reconstruir con datos reales el mapa, el ranking de departamentos, alertas y procesos (hoy plegados como maqueta).
3. Aplicar el componente `daily_kpi` y la barra de filtros a Mermas, Calidad, Despachos, Operaciones y Planeación según `04_propuesta_kpi_por_modulo.md`, después de aprobarla.
4. Que las pestañas secundarias de Ventas apliquen departamento y SKU (hoy aplican sucursal, categoría y fechas, y lo advierten).
5. Auditoría visual en inglés de las etiquetas nuevas.
6. Sucursal duplicada «Doral» (ids 9 y 11).
7. Imágenes faltantes `bebidas_refrigeradas.jpg` y `queso_cottage.jpg`.

## Decisiones que requieren aprobación
1. Restaurar la BD (pendiente 1).
2. Ubicación de «Indicadores Financieros» (hoy al final, sin número) y si absorbe Centros de Costo y Arturito IA.
3. Definición de Departamento vs. Categoría (ver `04_propuesta_kpi_por_modulo.md`).
4. Ocultar del menú las 8 pestañas internas de Mermas y Prevención (siguen accesibles dentro de cada tablero).

## Recomendaciones futuras
- Tabla `departments` configurable por empresa y relación con áreas de personal (productividad por departamento).
- Integrar un contador de tráfico para calcular conversión.
- Marca `companies.data_mode` (demo/real) y etiqueta visible «Datos de demostración».
- Retirar de `scratch/` los scripts que reescriben datos históricos.
