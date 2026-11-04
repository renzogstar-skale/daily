# Stock & Movimientos: reglas de cálculo

Fuentes: `stock`, `stock_log`, `sales`/`sale_items`, `customer_orders`, `quarantine_items`, `stock_transfers`.
Código: `StockExecutiveOverviewService` (tarjetas, evolución, movimientos), `StockOverviewInsightsService` (desglose, alertas, departamentos, oportunidades) y `DailyMetricsService` (departamentos y conteos compartidos con HOY y el Panel ejecutivo). Nada se guarda: todo se calcula al consultar.

| Indicador | Regla |
|---|---|
| Valor del inventario | Σ unidades del lote × costo del lote, sin ubicaciones de tránsito |
| Unidades | Σ unidades físicas de esos lotes |
| Reservado | Unidades de pedidos de cliente confirmados, en picking o empacados |
| Bloqueado | Lotes vencidos ∪ lotes en cuarentena, sin doble conteo por lote |
| En tránsito | Enviado − recibido de traslados `in_transit` hacia la sucursal. No suma al total |
| Disponible | Unidades − reservado − bloqueado (mínimo 0) |
| Rotación | Costo de ventas del período ÷ valor actual del inventario. Objetivo: 2.0× |
| Cobertura | Unidades ÷ unidades vendidas por día (promedio 28 días). Objetivo: 10–15 días |
| Stock mínimo | `products.min_stock_amount`, evaluado por par producto-sucursal |
| Bajo mínimo | 0 < existencia < mínimo. Crítico si existencia ≤ 50 % del mínimo; si no, preventivo |
| Agotado | Producto activo con mínimo configurado y existencia 0 en la sucursal |
| Por vencer | Par con algún lote que vence en 0..N días (N = umbral FEFO, 30 por defecto) |
| Valor en riesgo | Σ valor de los lotes que vencen en ≤ N días. Es costo expuesto, no pérdida confirmada |
| No se vendería | Parte de esos lotes que, con la venta diaria de 28 días y orden FEFO, no se vende antes de vencer |
| Participación | Valor del departamento ÷ valor total × 100, por mayor resto: suma exacta 100.0 % |

## Variación

Se compara contra el inicio del período, reconstruido con el kardex (valor actual − movimientos posteriores). Sin kardex suficiente la variación queda vacía, no en cero.

## Fecha de corte

Afecta a ventas, rotación y evolución. Las existencias y los conteos son siempre los actuales; la pantalla lo avisa.

## Riesgo por departamento

- **Crítico:** ≥ 25 % de sus productos con mínimo agotados, ≥ 25 % del valor vence en la ventana FEFO, o cobertura < 3 días.
- **Atención:** hay agotados, bajo mínimo o por vencer; cobertura > 15 días; o rotación < 0.3×.
- **Normal:** ninguna de las anteriores.

## Oportunidades

Son recomendaciones: no modifican inventario.

- **Exceso:** cobertura del departamento > 15 días. Excedente = valor × (1 − objetivo ÷ cobertura).
- **Agotamiento:** existencia ÷ venta diaria < tiempo de reposición (2 días, valor por defecto del MRP).
- **Vencimiento:** lotes con unidades que no se venderían antes de vencer.
- **Baja rotación:** existencia valorizada sin ventas en 28 días.
- **Traslado:** una sucursal sobre max(2 × mínimo, venta diaria × 15) y otra bajo su mínimo.

## Movimientos

Cada fila de `stock_log` cae en una sola categoría. Los tramos hacia y desde la ubicación de tránsito no se muestran: un traslado aparece como salida en origen y entrada en destino.

## Productos sin departamento

Se muestran como «Sin clasificar», cuentan en la participación y enlazan al catálogo para corregirlos.
