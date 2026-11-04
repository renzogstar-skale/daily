# DAILY — Fase de optimización empresarial · matriz de cobertura

Fecha: 2026-11-03. Regla aplicada: antes de construir se buscó el equivalente existente y se mejoró en lugar de duplicarlo.

## Matriz de cobertura funcional (12 capacidades)

| # | Capacidad | Existe en DAILY | Verificado | Estado | Brecha principal |
|---|---|---|---|---|---|
| 1 | Centro de Comando Operativo | `/today` (TodayCommandService) + `/process-flow` | Rutas 200, `test_today_panel_sync` | Parcial | Sin vista única de «qué decide el manager hoy» con excepciones + aprobaciones pendientes |
| 2 | Automatizaciones y reglas | `AutomationEngineService` (3 eventos, 6 acciones, idempotencia, reintentos) | `test_automation` 26/26 | **Mejorado** | Solo eventos de alerta y merma; faltan eventos de documento (OC, traslado, pedido) |
| 3 | Trazabilidad 360° | `/traceability`, `/api/quality/traceability/{id}` (lote), Celulares (IMEI aparte) | `test_traceability` | Parcial | Sin búsqueda unificada por UPC e IMEI (TraceabilityService no los trata) |
| 4 | Centro de Excepciones | **Nuevo** sobre el Centro de Alertas: 9 reglas de integridad + panel en `/process-flow` | `test_process_flow` 34/34 | Completo (v1) | Resolver desde el panel; hoy enlaza al expediente o módulo |
| 5 | Arturito IA proactivo | `/ai-advisor`, AIAdvisorService, AdvisorQaService | Ruta 200 | Parcial | Respuestas sin cita de fuente/expediente; no lee las excepciones |
| 6 | Predicción | Pronóstico laboral (`/schedule/forecast`), `predictive_expiry_risk`, MRP | `test_labor_forecast` | Parcial | Sin pronóstico de quiebres por producto con error medido |
| 7 | Horarios y cobertura | Motor laboral, picos, auto-programación | `test_labor_engine`, `test_schedule_*` | Completo | Pendiente jurídico (promedio 4 vs 3 semanas) |
| 8 | Móvil offline | `/m/*`, `dairy_offline.js` | `test_mobile`, `test_offline_sync` | Completo | — |
| 9 | Portal de proveedores | No existe | — | Incompleto | Requiere autenticación externa: decisión de seguridad previa |
| 10 | Control financiero | Presupuestos, centros de costo, conciliación de 3 vías de facturas | `run.php` (factura) | Parcial | Sin cierre contable mensual ni aprobación de ajustes financieros |
| 11 | Auditorías por riesgo | `/audits` (plantillas, 0 auditorías ejecutadas) | Ruta 200 | Incompleto | Sin selección de tienda/área por riesgo |
| 12 | Salud del sistema | `/system-map` (estructura y pruebas) | Ruta 200 | Parcial | Sin salud en ejecución: disco, integridad de BD, migraciones, errores, rutas lentas |

## Cambios de esta etapa

| Cambio | Archivos | Prueba |
|---|---|---|
| 9 reglas de integridad en el motor de alertas existente (lote vencido vendible, merma sin decisión, merma descontada sin aprobación, despacho sin conciliar, caja sin cerrar, OC aprobada sin enviar, devolución sin destino, traslado sin salida de stock, tareas vencidas sin responsable) | `migrations/0318.php`, `services/AlertCenterService.php` | Escaneo real: 136 excepciones; autocierre verificado |
| **Bug:** `expiring_lots` dejaba de ver el lote al vencer y autocerraba la alerta; el lote vencido seguía vendible | idem (`expired_lot_unblocked`) | test §6 |
| **Bug:** la acción `quarantine_lot` nunca se ejecutaba desde una alerta (el evento no traía lote ni cantidad) y no guardaba ubicación (el POS bloquea por lote+ubicación) | `services/AutomationEngineService.php` | test §6: aísla stock real, idempotente, sin movimientos |
| Automatización «Lote vencido → cuarentena», creada **inactiva** | `migrations/0319.php` | test §6 |
| Centro de Excepciones (API + panel) | `services/ProcessFlowService.php`, `/api/process-flow/exceptions`, `views/process_flow.blade.php` | test §6 |

## Rendimiento medido

| Operación | Tiempo |
|---|---|
| `/process-flow` (página) | 0,12 s |
| `/api/process-flow/{tipo}/{id}` | 0,03–0,05 s |
| `/api/process-flow/inbox?limit=5` (10 procesos) | 0,7 s |
| Escaneo completo de alertas, 10 sucursales | 17,4 s (POST manual; el programado corre por regla) |

## Riesgos y preparación para producción

| Riesgo | Severidad | Situación |
|---|---|---|
| Disco de `%TEMP%` al 100 % (235 copias `dairy_tests_*`, 4,4 GB) | Alta | Pendiente de autorización para borrar |
| 2 mermas descontadas sin aprobación | Alta | Visibles como excepción crítica; decisión de negocio |
| 3 lotes vencidos a la venta | Alta | Excepción crítica; activar `QUA_EXPIRED_BLOCK` o bloquear a mano |
| 10 turnos de caja abiertos (desde el 28/10) | Alta | Excepción crítica |
| Fallas preexistentes: `test_alert_center` (2, series diarias), `test_global_processes` (`ReconciliationService:675`) | Media | No introducidas en esta etapa |
| Bandeja calcula expedientes en PHP (N consultas) | Media | Aceptable a 0,7 s; con más volumen conviene vista SQL por tipo |
| Portal de proveedores expone la app a terceros | Alta | No iniciar sin diseño de autenticación y aislamiento |

**Preparación para producción:** no lista. Antes hay que liberar disco, decidir las 2 mermas descontadas, cerrar las cajas abiertas y dejar en verde las 2 suites con fallas preexistentes.
