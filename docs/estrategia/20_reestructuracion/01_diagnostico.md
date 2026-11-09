# 20.01 · Diagnóstico inicial de DAILY (4 de noviembre de 2026)

Alcance: código (`helpers/DairyNavigation.php`, controladores, servicios, vistas Blade), base de demostración `data/grocy_es.db` y recorrido con Playwright (Edge, 1440×900) de HOY, Panel, Ventas, Stock, Control de inventario, Mermas, Calidad, Despachos, Horarios, MRP y Reportes.

DAILY es PHP (Slim + Blade + Bootstrap 4 + Chart.js 2.9) sobre SQLite. No es React/Next/PostgreSQL: los cambios se hicieron dentro de esa arquitectura, sin reconstruir.

## 1. Hallazgos críticos (bloquean la confianza en las cifras)

| # | Hallazgo | Evidencia | Impacto |
|---|---|---|---|
| C1 | **Costo de ventas corrompido en la BD de demostración.** Scripts de "calibración" (`scratch/apply_stock_calibration.php`, `scratch/calibrate_healthy_coverage.php`, `scratch/fix_sale_margins.php`, 3–4 nov.) multiplicaron `sale_items.amount`, `sale_items.total_cost`, `stock.amount` y `stock.price` por factores arbitrarios. | Nov.: ventas netas $106.396 vs. costo $2.432.570. 6.093 líneas con `subtotal ≠ cantidad × precio` (antes 48). Ej.: venta 8229, 118 u. registradas y subtotal de 3 u. | Margen bruto −607 % (HOY), −2.186 % (Reportes); valor de inventario +41.873 %; rotación y cobertura falsas. |
| C2 | **Panel Ejecutivo (/kpis) era una réplica de una foto con cifras fijas.** | `$128,450`, `28,4 %`, `98,1 % exactitud`, `87 %`, `99,1 %` escritos en la vista; 10 sucursales colombianas inventadas en `KpiService` (Calle 80, Envigado…) que no existen en la BD (las sucursales son de Florida). | Se mostraban datos ficticios como reales. El «98,1 %» no tenía fórmula. |
| C3 | **Marca de un tercero real.** «Alkosto - Red Colombia» en el selector del Panel y «Alkosto Calle 80…» en Prevención de Pérdidas. | `views/executive_dashboard.blade.php`, `views/loss_prevention.blade.php` | Riesgo legal y comercial. |
| C4 | **Curvas fijas en Ventas.** Las vistas «por hora» y «mensual» de la curva comercial usan arreglos escritos a mano (`[1240, 3120, …]`, `[94200, …, 128450]`). | `views/sales_history.blade.php` (script de la curva) | Datos ficticios en el tablero principal. |

## 2. Inconsistencias de datos entre módulos

| # | Hallazgo | Detalle |
|---|---|---|
| D1 | **Dos clasificaciones de «departamento».** | HOY/Panel usan `products.department` (texto) agrupado en `DailyMetricsService::DEPARTMENTS`; Ventas usaba `product_groups` (maestro configurable) con la etiqueta «Departamento». Ej.: «Lácteos y Leches» (grupo) contiene productos de los departamentos «Lácteos» y «Lácteos y huevos». |
| D2 | Sinónimos en `products.department`. | «Carnes» / «Carnes y aves», «Lácteos» / «Lácteos y huevos», «Bebidas» / «Bebidas refrigeradas». La lista de equivalencias está fija en código. |
| D3 | Áreas de personal ≠ departamentos de producto. | `employee_shifts.department`: «Carnes y Pollo», «Dairy / Lácteos», «Cajas y Atención»… No hay una tabla que las relacione con los departamentos de venta. |
| D4 | Sucursal duplicada. | Dos sucursales «Doral» (ids 9 y 11). |
| D5 | Subcategoría casi vacía. | 1 de ~600 productos activos con `subcategory`. |
| D6 | Sin datos de tráfico. | No existe tabla ni integración de conteo de ingreso de clientes. |
| D7 | Historia corta. | Ventas desde ene-2026 (3/mes hasta ago.); volumen real desde sep-2026. Las comparaciones «año anterior» no tienen base y los «vs. período anterior» muestran +1.400 % por el arranque de la semilla. |

## 3. Navegación (antes)

Orden: Supervisión → 1. Planeación → 2. Inventario → 3. Ventas → 4. Despachos → 5. Calidad → 6. Mermas → 7. Prevención de Pérdidas → 8. Operaciones → 9. Reportes y Analítica → 10. Administración.
- Mermas y Prevención eran dos secciones (15 entradas); 8 de ellas eran pestañas internas de `/waste` y `/loss-prevention` duplicadas en el menú.
- «Pedidos de Clientes» (reserva, picking, despacho) estaba en Ventas aunque su operación es de Despachos.
- No había una pantalla para buscar un producto agotado por SKU.

## 4. Ventas (antes)

Pestaña principal con 6 indicadores + curva + meta + participación + sucursales + análisis de productos + oportunidades (≈1.200 líneas), y 9 pestañas numeradas. Filtros: sucursal, «departamento» (= grupo de producto), fechas, comparación. Sin productividad por personal ni tráfico; sin «fin de semana vs. anterior».

## 5. KPI sin contexto (muestra)

| Pantalla | Indicador | Problema |
|---|---|---|
| Panel | Inventario 98,1 % | Sin fórmula ni fuente (cifra fija). |
| Panel | Personal 87 %, Calidad 99,1 % | Cifras fijas. |
| HOY | Margen bruto −607 % | Fórmula correcta, dato de costo corrompido (C1). |
| Stock | Valor del inventario +41.873,8 % «vs. inicio del período» | Dato corrompido (C1). |
| Control de inventario | «Venta perdida $165,32» | Sin período. |
| Despachos | Exactitud preparación 100 % | Sin período ni denominador visible. |

## 6. Qué se conserva, traslada, consolida o retira del menú

| Funcionalidad | Decisión |
|---|---|
| HOY, Centro de Alertas, Panel Ejecutivo | Se mantienen en Supervisión y Control. |
| Reportes y Analítica | Se renombra la sección a **Indicadores Financieros** (Rentabilidad y Margen, Centros de Costo, Reportes e Informes, Arturito IA). `/reports` se conserva. |
| Pedidos de Clientes | Se traslada a Despachos («Pedidos y Entregas»). Ruta `/orders` intacta. |
| Gestión de Clientes | Se mantiene en Ventas, en 4.º lugar. |
| Historial de ventas | Nueva entrada de menú a `/sales?tab=detalle`. |
| Mermas + Prevención de Pérdidas | Se consolidan en **4. Mermas y Prevención** (7 entradas visibles). Las 8 pestañas internas quedan fuera del menú pero accesibles en su tablero y por URL. |
| Productos agotados | Nueva pantalla `/inventory/stockouts`. |
| Conciliación con inventario / Integración POS (pestañas de Ventas) | Se conservan; en el menú, la conciliación vive en Inventario («6. Conciliación e Inventario Sensible»). |

Ninguna ruta, archivo, tabla ni dato se eliminó.
