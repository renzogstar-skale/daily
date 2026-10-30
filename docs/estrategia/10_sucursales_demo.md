# DAILY — Sucursales y empresa de ejemplo

Las 10 sucursales son ficticias. Los nombres se tomaron de ciudades y zonas del sur de Florida y no representan tiendas reales. No tienen dirección ni teléfono: los campos quedaron vacíos («Sin información»).

**Fuente única:** tabla `branches`, leída con `BranchService` (`GetBranches`, `NameById`). Migración: `migrations/0299.php`.

## Correspondencia (id y código conservados)

| id | Código | Nombre anterior | Nombre nuevo | Registros asociados (ejemplos) |
|---|---|---|---|---|
| 1 | SUC-01 | Sucursal Matriz | Hallandale Beach | 78 ventas, 38 mermas, 152 empleados, 3.271 turnos, 265 alertas, 5 pedidos |
| 2 | SUC-02 | [DEMO] Sucursal Norte | Miami Beach | 13 ventas, 21 mermas, 4 alertas |
| 3 | SUC-03 | [DEMO] Sucursal Poniente | Sunny Isles Beach | 12 ventas, 16 mermas, 5 alertas |
| 4 | SUC-04 | [DEMO] Sucursal Sur | Aventura | tareas, pronóstico laboral, turno de caja |
| 5 | SUC-05 | [DEMO] Sucursal Chapinero | North Miami Beach | tareas, pronóstico laboral, turno de caja |
| 6 | SUC-06 | [DEMO] Sucursal Salitre | Miami | tareas, pronóstico laboral, turno de caja |
| 7 | SUC-07 | [DEMO] Sucursal Fontibón | Hollywood | tareas, pronóstico laboral, turno de caja |
| 8 | SUC-08 | [DEMO] Sucursal Cedritos | Fort Lauderdale | tareas, pronóstico laboral, turno de caja |
| 9 | DEMO-TI | DEMO · Tienda Integral | Doral | 25 empleados, 721 turnos, 39 alertas (escenario de horarios) |
| 10 | SUC-10 | — (nueva) | Hialeah | sin registros |

Direcciones anteriores eliminadas (eran ficticias de Bogotá o Ciudad de México): Av. Reforma #500 Centro; Av. Norte 120; Calle 80 #68-30; Autopista Sur #45-10; Carrera 13 #58-20; Av. Esperanza #68B-15; Calle 17 #98-40; Calle 140 #11-50; Av. Comercial Integral #88.

## Paneles calculados con los registros

HOY (ventas), Panel ejecutivo, Inventario y Almacenes (Stock ejecutivo), Mermas (Panel ejecutivo) y Centro de Alertas calculan sus cifras con las mismas tablas y definiciones (`DailyMetricsService`, `KpiService`, `AlertCenterService`). Los marcadores `[[B<id>]]` solo resuelven el nombre de la sucursal desde el catálogo. Sin registros se muestra «Sin información» (no cero ni un valor de referencia).

El Centro de Alertas ya no usa un conjunto fijo: lee `dairy_alerts` por `/api/alerts/list` (activas + cerradas del período) y registra cambios de estado, comentarios, evidencias, asignaciones, altas manuales y escaneos con la API de alertas.

## Empresa de ejemplo (`DailyCompanySeedService`, migración 0300)

Se ejecuta una sola vez al migrar (`daily_seed_runs`, código `daily_company_v1`); si una carga se interrumpe, no se repite encima: hay que restaurar el respaldo. Opera los últimos **58 días** día a día con los servicios de la aplicación, por lo que inventario, kardex, libro de movimientos, ventas, mermas y KPI salen de los mismos registros:

| Proceso | Cómo se registra |
|---|---|
| Catálogo | 43 artículos activos con SKU, unidad, departamento, precio y costo: lácteos y huevos, bebidas, frutas y verduras, carnes, panadería, abarrotes, congelados y electrónica. Ids 176–188 nuevos; los existentes conservan id, SKU y nombre. |
| Ubicaciones | Por sucursal: cámara fría, góndola, bodega seca y congelador (Hallandale Beach usa sus ubicaciones existentes). |
| Proveedores | Existentes + Panificadora del Puerto, Distribuidora de Abarrotes Costa Sur, Congelados del Litoral, Distribuidora Tecnológica Atlántico y Proveedor Hortícola Regional (nombres genéricos). |
| Compras | OC → aprobación → envío → recepción con lote, vencimiento y temperatura de llegada según almacenamiento (`MrpService`). |
| Ventas | Tickets POS con costo FEFO (`SalesService::CreateSale`); una devolución total por sucursal (`CancelSale`, el stock vuelve al lote). |
| Pedidos | Semanales por cliente comercial: confirmación (reserva) → preparación FEFO → empaque → despacho (genera la venta una sola vez). Hoy: uno confirmado y uno en borrador por sucursal. |
| Mermas | Retiro de lotes vencidos cada mañana y roturas en piso (`WasteService`); las de mayor monto quedan pendientes de aprobación. |
| Traslados | Ocho entre sucursales; uno con faltante (merma de traslado) y el más reciente en tránsito. |
| Conteos | Semanales, dos artículos por sucursal; dos con faltante pendiente de aprobación de otra persona. |
| Personal | 8 colaboradores por sucursal (Doral conserva su escenario de 25) con turnos publicados de hoy a dos semanas. |
| Calidad | Temperaturas de cámara (2 lecturas/día) y congelador (1/día); dos excursiones registradas. |
| Metas | Meta de ventas del mes por sucursal (ritmo de 28 días + 5 %) y de red = suma. |

Ventas cargadas antes sin descargo de inventario dentro del período quedan con estado `void` («Anulada»): se conservan cabecera y líneas, pero no suman ventas ni cuentan como devolución.

## Persistencia

Todo vive en `data/grocy_es.db` (SQLite). Lo que el usuario registra por pantallas o API permanece tras recargar. No hay sincronización en tiempo real entre dispositivos: cada pantalla muestra lo guardado al cargarla. Respaldos: `grocy_es.db.bak_pre_empresa` (antes de la empresa de ejemplo).

## Funciones simuladas (rotuladas en pantalla)

- Inventario y Almacenes → «Gestión FEFO» y «Flujo operativo (simulación de 10 pasos)»: se anotan en una bitácora propia, sin mover stock.
- Horarios → escenario de ejemplo de Doral (validación, restauración, simulador de picos).
- Centro de Alertas → configuración de reglas y rango personalizado no disponibles en esa vista.
