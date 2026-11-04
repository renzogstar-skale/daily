# DAILY — Auditoría de ciclos operativos

Fecha: 2026-11-02 · Base: `data/grocy_es.db` · Servidor: localhost:8080

Método: (a) GET de las 56 rutas del menú (todas 200; `/userentities` 34 s = CatchUp horario, no costo estable);
(b) consultas de integridad entre módulos sobre la BD real; (c) expediente derivado (`ProcessFlowService`) sobre
cada tipo de documento. Un ciclo es **Completo** solo si cada paso deja huella verificable en la BD y enlaza el
documento siguiente.

## Etapa 1 — Matriz por dashboard

| Dashboard | Entrada | Proceso | Salida | Siguiente módulo | Estado |
|---|---|---|---|---|---|
| HOY · Operación del Día | Ventas, alertas, tareas, mermas | Resumen, decisiones | Tareas/alertas | Alertas, Tareas | Parcial: 297 tareas abiertas, 208 sin responsable |
| Centro de Alertas | Reglas (`dairy_alerts`) | Clasificar, asignar, resolver, aprobar | Cierre + evento | Módulo origen (`href`), tareas | Parcial: 247 abiertas sin responsable, 242 vencidas; 695 cierres automáticos sin verificación |
| Panel Ejecutivo | DailyMetrics/KPI | KPI y desviaciones | Indicadores | Desglose por módulo | Parcial (sin acción/seguimiento ligado a KPI) |
| **Expedientes y Procesos (nuevo)** | 10 tipos de documento | Paso actual, responsable, límite | Expediente | Módulo dueño | Completo (lectura) |
| Planificación MRP | Demanda, stock | Sugerencia → OC | `procurement_orders` | Proveedores | Parcial: 9 OC aprobadas nunca enviadas |
| Simulador What-If | Variables | Simulación | Escenario | — | Incompleto: sin aprobar decisión → acción |
| Proveedores & Compras | OC | Aprobar, enviar, recibir, factura | Recepción + stock | Mercancías, Kardex | Completo (1068 recepciones con transacción en `stock_log`) |
| Sucursales y Almacenes | Maestro | Configurar | Estructura | Todos | Parcial (sin flujo de activación) |
| Control de Mercancías | Recepciones/ajustes | Validar, aprobar | Stock | Kardex | Completo (ajustes con aprobación) |
| Operaciones con Escáner | UPC | Movimiento | `stock_log` | Stock | Parcial (sin expediente de sesión) |
| Mapa de Ubicaciones | Ubicaciones | Ocupación | Vista | — | Parcial (solo consulta) |
| Stock & Movimientos | Stock | Movimientos | `stock_log` | Kardex | Completo |
| Traslados | Solicitud | Despacho, recepción | Stock destino | Kardex, merma por diferencia | Parcial: **sin paso de aprobación**; TRA-202610-0002 en tránsito sin transacción de salida |
| Kardex Valorizado | `stock_log` | Valorización | Consulta | Conciliación | Completo: verificado que consultar no escribe (`stock_log` sin cambios) |
| Control de Inventario | Anomalías | Análisis, ajuste | Ajuste | Kardex | Parcial: 4 conteos cíclicos con discrepancia sin ajuste |
| Conteos Físicos | Sesiones | Contar, revisar, conciliar | Ajuste | Kardex | Parcial (estados sin responsable por usuario, solo texto) |
| Conciliación & Sensible | Diferencias | Casos, hipótesis | Ajuste autorizado | Investigaciones | Parcial: 26 hipótesis pendientes |
| Ventas y Rentabilidad | `sales` | KPI | Margen | Panel | Parcial: 251 líneas de venta sin `transaction_id` (carga histórica) |
| POS | Escaneo | Cobro | Venta + consumo | Ventas, stock | Parcial: 10 turnos de caja abiertos > 1 día; anulación reingresa como `purchase` (ensucia analítica de compras) |
| Pedidos de Clientes | Pedido | Confirmar → despachar | Venta | Despachos | Parcial: 10 borradores > 2 días; 1 despachado sin venta |
| Clientes y Cuentas | Maestro | Crédito | Cuenta | Pedidos | Parcial (sin conciliación de cuenta) |
| Picking y Packing | Pedido | Escaneo, empaque | Bultos | Despacho | Completo |
| Centro de Despachos | Pedido empacado | Despacho, entrega | Entrega | Conciliación | Parcial: 33 despachados sin conciliar |
| Devoluciones | Solicitud | 8 estados | Stock/merma | Inventario | Parcial: 3 cerradas/conciliadas sin destino (ni merma ni reingreso) |
| Calidad & Vencimientos | Lotes | Inspección, cuarentena | Bloqueo | Merma/retiro | Parcial: 7 lotes vencidos con stock (120 u) sin cuarentena; 1 cuarentena > 7 días |
| Trazabilidad de Lotes | Lote | Historial | Consulta | — | Completo (lectura) |
| Retiros de Producto | Alerta | Retiro | Merma | — | Sin uso (0 retiros): no verificable con datos |
| Cuadro de Mermas | `waste_records` | Análisis | Planes | Causales | Parcial: 2 planes vencidos |
| Registro de Merma | Evidencia | Registro | Solicitud | Aprobaciones | Completo |
| Aprobaciones de Merma | Pendientes | Aprobar/rechazar | Descuento stock | Kardex | Parcial: 76 pendientes > 3 días; 2 pendientes **ya descontadas de inventario** (inconsistencia) |
| Matriz de Causales | Causas | Catálogo | Reglas | Merma | Completo (maestro) |
| Investigaciones de Merma | Caso | Estados | Cierre | Recuperación | **Corregido**: aceptaba cualquier estado (cerrar sin aprobar) |
| Recuperación Financiera | Reclamo | Crédito | Recupero | Conciliación | Parcial (enlace por `case_number`, sin FK) |
| PP · Centro de Control | Riesgos | Casos | Resolución | — | Parcial |
| PP · Investigaciones | Alerta | Expediente con hash | Resolución | Ajuste | Completo (bitácora encadenada) |
| PP · Celulares | IMEI | Custodia | Venta/baja | Conciliación | Completo (eventos con hash) |
| PP · Activos / Accesos / CCTV / Rondas / Planes | Pestañas | Registro | — | — | Parcial: tablas de acciones/planes/tareas de caso vacías |
| Reportes y KPI de Prevención | Casos | KPI | Reporte | — | Completo (lectura) |
| Gestión de Personal | Planilla | Alta/baja | Colaboradores | Horarios | Parcial |
| Horarios y Cobertura | Necesidad | Generar, publicar | Turnos | Asistencia | Completo (13 publicaciones) |
| Asistencia y Fichajes | Fichajes | Ajustes | Jornada | Productividad | Parcial: 1 ajuste solicitado sin decidir |
| Tareas y Roles | Alertas, mermas, equipos | Ejecutar | Cierre | Productividad | Incompleto: 294 vencidas, 208 sin responsable, 192 sin fecha de creación |
| Productividad | Turnos vs. tareas | Medición | KPI | — | Parcial |
| Reportes / Centros de Costo / Arturito | Datos | Cálculo | Reporte | — | Parcial (sin historial de ejecución de reportes: `dairy_report_runs` vacío) |
| Administración (8 + 10 maestros) | Configuración | CRUD | Datos | Todos | Parcial: `dairy_change_log` solo audita 6 tablas maestras |

## Etapa 2 — Rupturas detectadas (verificadas con consulta)

| # | Ruptura | Evidencia | Acción |
|---|---|---|---|
| 1 | No había expediente único ni «paso actual» por documento | `daily_workflow_instances` = 0; motor manual sin UI | **Hecho**: `ProcessFlowService` + `/process-flow` + modal |
| 2 | Caso de merma podía saltar a «cerrado» sin aprobación | `UpdateCaseWorkflow` sin validación | **Hecho**: transiciones validadas |
| 3 | Mermas pendientes con inventario ya descontado | 2 registros (ids 106…) | Pendiente: decidir con negocio (aprobar o revertir) |
| 4 | Traslado sin aprobación y en tránsito sin salida de stock | TRA-202610-0002 | Pendiente: paso de aprobación + reparación |
| 5 | Alertas sin responsable / vencidas | 247 / 242 | Pendiente: asignación automática por regla |
| 6 | Tareas sin responsable | 208 | Pendiente: asignación por rol/turno |
| 7 | Despachos sin conciliar | 33 | Pendiente: conciliación automática si entrega = preparado |
| 8 | Lotes vencidos sin bloqueo | 7 lotes / 120 u | Pendiente: cuarentena automática al vencer |
| 9 | Turnos de caja abiertos > 1 día | 10 | Pendiente: alerta de cierre de caja |
| 10 | Anulación POS reingresa como compra | 28 movimientos `purchase` | Pendiente: tipo de movimiento propio |
| 11 | Devoluciones cerradas sin destino | 3 | Pendiente: exigir destino al cerrar |

## Pruebas

- `tests/dairy/test_process_flow.php`: 26/26 (OC de punta a punta con relectura, merma no cerrable sin aprobar,
  pasos omitidos ≠ completados, anulados interrumpidos, consulta sin movimientos, errores controlados).
- Fallas preexistentes ajenas a este cambio: `test_alert_center.php` (2, series por día) y
  `test_global_processes.php` (excepción en `ReconciliationService:675`).
