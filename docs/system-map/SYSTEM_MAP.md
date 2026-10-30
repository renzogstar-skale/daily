# DAIRY — Mapa del Sistema (informe generado)

Generado: 2026-10-21 21:19:51 por `SystemMapService` (análisis estático) + evidencia de `tests/dairy/system_map_build.php`. Ver en la app: `/system-map` (solo ADMIN).

**Criterio de estado** — Verificado: la pantalla carga sin errores en el barrido HTTP **y** al menos una prueba automática que la cubre pasa. Parcial: carga, pero sin prueba de sus operaciones o con hallazgos abiertos. Error: falla confirmada (ruta/controlador/vista inexistente, llamada a API inexistente, barrido o prueba fallida). No verificado: sin evidencia de ejecución. Propuesto: no implementado.

## Evidencia de ejecución
- Barrido HTTP: 2026-10-21 19:09:22 — 169 verificaciones, 0 fallos
- `run.php`: OK (70 verificaciones, 0 fallidas)
- `test_branch_access.php`: OK (10 verificaciones, 0 fallidas)
- `test_labor_forecast.php`: OK (57 verificaciones, 0 fallidas)
- `test_loss_prevention.php`: OK (57 verificaciones, 0 fallidas)
- `test_mobile.php`: OK (27 verificaciones, 0 fallidas)
- `test_mobile_work.php`: OK (29 verificaciones, 0 fallidas)
- `test_movement_ledger.php`: OK (16 verificaciones, 0 fallidas)
- `test_offline_sync.php`: OK (15 verificaciones, 0 fallidas)
- `test_operations.php`: OK (52 verificaciones, 0 fallidas)
- `test_orders.php`: OK (35 verificaciones, 0 fallidas)
- `test_reliability.php`: OK (24 verificaciones, 0 fallidas)
- `test_schedule_mgmt.php`: OK (42 verificaciones, 0 fallidas)
- `test_traceability.php`: OK (20 verificaciones, 0 fallidas)
- `http_reliability.php`: OK (125 verificaciones, 0 fallos) 2026-10-21 18:17:23
- `http_labor_forecast.php`: OK (23 verificaciones, 0 fallos) 2026-10-21 18:17:32

## Resumen
| Módulos | Rutas web | Endpoints API | Controladores | Servicios | Vistas | Tablas | Migraciones | Conexiones verificadas | Propuestas | Hallazgos |
|---|---|---|---|---|---|---|---|---|---|---|
| 50 | 121 | 278 | 64 | 56 | 138 | 152 | 286 | 44 | 10 | 38 |

Estados: Verificado 17 · Parcial 33

## Inventario de módulos, rutas y funcionalidades
### Inicio
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Hoy | `/today` | Verificado | 0 | 3 | 4 | 57 | 9 | sí |
| Centro de Alertas | `/alerts` | Verificado | 0 | 9 | 4 | 18 | 3 | sí |
| Panel Ejecutivo | `/kpis` | Parcial | 0 | 9 | 0 | 32 | 3 | — |

### Mermas
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Centro de Control | `/waste` | Parcial | 11 | 11 | 14 | 44 | 11 | sí |
| Registrar Merma | `/waste/report` | Verificado | 0 | 1 | 2 | 15 | 8 | sí |
| Aprobaciones | `/waste/approvals` | Verificado | 0 | 3 | 2 | 15 | 6 | sí |
| Causales de merma | `/waste/reasons` | Parcial | 0 | 5 | 2 | 12 | 5 | sí |

### Calidad
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Calidad & Vencimientos | `/quality` | Parcial | 5 | 14 | 5 | 17 | 1 | — |
| Retiros de Producto | `/recalls` | Verificado | 0 | 3 | 6 | 11 | 1 | sí |

### Inventario
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Stock & Movimientos | `/stockoverview` | Parcial | 0 | 3 | 22 | 43 | 6 | sí |
| Operaciones con Escáner | `/scan` | Parcial | 0 | 4 | 10 | 33 | 8 | sí |
| Mapa de Ubicaciones | `/locations/map` | Parcial | 0 | 0 | 0 | 4 | 0 | — |
| Traslados | `/transfers` | Verificado | 0 | 6 | 4 | 11 | 2 | sí |
| Kardex | `/kardex` | Verificado | 0 | 3 | 1 | 15 | 2 | — |
| Trazabilidad | `/traceability` | Verificado | 0 | 1 | 0 | 23 | 1 | — |
| Control de Inventario | `/inventory-control` | Parcial | 0 | 4 | 1 | 18 | 4 | sí |
| Conteos Físicos | `/inventory` | Parcial | 0 | 0 | 12 | 34 | 5 | sí |
| Sucursales | `/branches` | Parcial | 0 | 1 | 1 | 11 | 1 | — |
| Etiquetas de ubicación | `/locations/labels` | Parcial | 0 | 1 | 0 | 2 | 0 | — |

### Planeación
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Planificación MRP | `/mrp` | Parcial | 0 | 10 | 11 | 23 | 3 | — |
| Proveedores & Compras | `/suppliers` | Parcial | 6 | 15 | 28 | 54 | 8 | sí |
| Simulador What-If | `/ai-advisor#tab-simulator` | Parcial | 1 | 2 | 2 | 19 | 1 | sí |

### Operaciones
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Piso de Tienda | `/workforce` | Parcial | 2 | 8 | 23 | 50 | 7 | sí |
| Personal & Turnos | `/schedule` | Parcial | 9 | 22 | 16 | 18 | 7 | sí |
| Auditorías de Tienda | `/audits` | Parcial | 0 | 4 | 3 | 7 | 1 | sí |
| Punto de Venta | `/pos` | Parcial | 0 | 8 | 1 | 17 | 3 | sí |
| Ventas | `/sales` | Parcial | 5 | 4 | 2 | 27 | 6 | sí |
| Pedidos de Clientes | `/orders` | Parcial | 0 | 5 | 2 | 14 | 1 | — |
| Clientes | `/customers` | Parcial | 0 | 0 | 0 | 1 | 0 | — |

### Análisis
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Reportes & Analítica | `/reports` | Verificado | 0 | 3 | 0 | 28 | 2 | — |
| Centros de Costo | `/cost-centers` | Verificado | 0 | 1 | 1 | 7 | 3 | sí |
| Arturito IA | `/ai-advisor` | Parcial | 2 | 2 | 2 | 19 | 1 | sí |
| Documentos imprimibles | `/document/{type}/{id}` | Parcial | 0 | 1 | 0 | 15 | 1 | — |

### DAIRY Mobile
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| DAIRY Mobile (PWA) | `/m` | Parcial | 0 | 0 | 0 | 0 | 0 | sí |

### Datos maestros
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Importación de datos | `/data-import` | Parcial | 0 | 2 | 1 | 11 | 2 | — |
| Productos | `/products` | Verificado | 0 | 3 | 14 | 38 | 5 | sí |
| Ubicaciones | `/locations` | Verificado | 0 | 1 | 4 | 38 | 5 | sí |
| Proveedores (maestro Grocy) | `/shoppinglocations` | Verificado | 0 | 1 | 4 | 38 | 5 | sí |

### Administración
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Mapa del Sistema | `/system-map` | Parcial | 0 | 11 | 3 | 0 | 0 | — |
| Usuarios y permisos | `/users` | Parcial | 0 | 2 | 5 | 11 | 0 | — |
| Claves API | `/manageapikeys` | Parcial | 0 | 1 | 1 | 2 | 0 | — |
| Campos y entidades personalizadas | `/userfields` | Verificado | 0 | 2 | 3 | 33 | 5 | sí |
| Acerca de / versión | `/about` | Parcial | 2 | 0 | 0 | 1 | 0 | — |

### Núcleo Grocy heredado
| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |
|---|---|---|---|---|---|---|---|---|
| Recetas y plan de comidas | `/recipes` | Verificado | 2 | 2 | 17 | 39 | 6 | sí |
| Quehaceres (Grocy) | `/choresoverview` | Verificado | 0 | 3 | 10 | 38 | 5 | sí |
| Baterías (Grocy) | `/batteriesoverview` | Parcial | 0 | 3 | 6 | 12 | 0 | — |
| Tareas (Grocy) | `/tasks` | Parcial | 0 | 2 | 4 | 12 | 0 | — |
| Equipos | `/equipment` | Parcial | 2 | 1 | 3 | 5 | 0 | — |
| Calendario | `/calendar` | Parcial | 0 | 0 | 1 | 8 | 0 | — |
| Lista de compras (Grocy) | `/shoppinglist` | Verificado | 0 | 1 | 21 | 34 | 6 | sí |

## Problemas priorizados
| Prioridad | Tipo | Módulo | Hallazgo | Confianza | Evidencia | Recomendación |
|---|---|---|---|---|---|---|
| Info | Módulo oculto | batteries | Baterías (Grocy): ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:160` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| Info | Módulo oculto | calendar | Calendario: ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:180` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| Info | Módulo oculto | chores | Quehaceres (Grocy): ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:151` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| Info | Módulo oculto | equipment | Equipos: ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:176` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| Info | Módulo oculto | recipes | Recetas y plan de comidas: ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:139` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| Info | Módulo oculto | shoppinglist | Lista de compras (Grocy): ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:133` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| Info | Módulo oculto | tasks | Tareas (Grocy): ruta activa de Grocy que no aparece en el menú DAIRY | Confirmado | `routes.php:169` | Decidir: ocultar con feature flag de Grocy o integrarlo |
| P1 | Funcionalidad ausente | users | No existe respaldo/restauración en la app (depende de copiar data/) | Documentado | DAIRY_AUDIT.md matriz: «Respaldo y restauración — Bloqueado»; `controllers/SystemController.php` | Documentar y probar procedimiento de respaldo; luego exponerlo a ADMIN |
| P2 | Datos simulados | branches | Semillas demo en migraciones sin condicionar a MODE=demo: una instalación nueva nace con datos ficticios | Documentado | DAIRY_DATOS_REALES.md §5 y tabla de huecos; `migrations/0258.php` | Condicionar las inserciones de 0258–0262, 0264, 0268, 0269 a GROCY_MODE === demo (migración nueva, sin editar las aplicadas) |
| P2 | Web presente / móvil ausente | mobile | Sin push con la app cerrada (requiere claves VAPID y servidor de envío) | Documentado | DAIRY_AUDIT.md matriz: «Notificaciones push — Bloqueado»; `public/js/dairy_m.js` | Configurar VAPID solo si negocio lo exige |
| P2 | Web presente / móvil ausente | schedule | Solicitudes de cobertura abiertas no se avisan en /m | Documentado | DAIRY_AUDIT.md Etapa 29 «Pendiente P2»; `services/MobileWorkService.php` | Agregar tarjeta en /m (MobileWorkService::Today) |
| P2 | Móvil presente / web ausente | schedule | Decidir correcciones de fichaje solo desde móvil/API, no desde /schedule | Documentado | DAIRY_AUDIT.md Etapa 25 «Pendientes»; `public/viewjs/schedule.js` | Botón aprobar/rechazar en la pestaña de asistencia |
| P3 | API sin consumidor | ai_advisor | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:400` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | audits | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:527` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | branches | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:403` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Módulo aislado | customers | Clientes no está en el menú ni tiene conexiones verificadas | Confirmado | `helpers/DairyNavigation.php` | Enlazarlo desde el módulo que lo usa |
| P3 | API sin consumidor | data_import | 2 endpoint(s) sin llamada desde la web ni la app móvil (2 sin prueba que los use) | Confirmado | `routes.php:509`; `routes.php:510` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Módulo aislado | documents | Documentos imprimibles no está en el menú ni tiene conexiones verificadas | Confirmado | `helpers/DairyNavigation.php` | Enlazarlo desde el módulo que lo usa |
| P3 | Verificación pendiente | inventory | Conteos y ajustes sin prueba HTTP concurrente | Documentado | DAIRY_AUDIT.md matriz: «Conteos y ajustes»; `tests/dairy/http_reliability.php` | Agregar caso a http_reliability.php |
| P3 | API sin consumidor | inventory_control | 4 endpoint(s) sin llamada desde la web ni la app móvil (4 sin prueba que los use) | Confirmado | `routes.php:497`; `routes.php:507`; `routes.php:518`; `routes.php:520` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | kpis | 2 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:399`; `routes.php:401` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Módulo aislado | location_labels | Etiquetas de ubicación no está en el menú ni tiene conexiones verificadas | Confirmado | `helpers/DairyNavigation.php` | Enlazarlo desde el módulo que lo usa |
| P3 | Web presente / móvil ausente | mobile | Sin conexión no se encolan fotos ni fichaje | Documentado | DAIRY_AUDIT.md matriz: «Offline y sincronización»; `public/js/dairy_m.js` | Encolar fotos en IndexedDB con la misma clave de idempotencia |
| P3 | API sin consumidor | mrp | 2 endpoint(s) sin llamada desde la web ni la app móvil (2 sin prueba que los use) | Confirmado | `routes.php:430`; `routes.php:431` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | orders | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:516` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Verificación pendiente | orders | Pedidos y picking sin prueba HTTP concurrente | Documentado | DAIRY_AUDIT.md matriz: «Pedidos y picking»; `tests/dairy/http_reliability.php` | Agregar caso a http_reliability.php |
| P3 | Verificación pendiente | pos | POS: idempotencia verificada por API; UI no re-probada en navegador tras el cambio | Documentado | DAIRY_AUDIT.md matriz: «Ventas POS — Aprobado con observaciones»; `views/pos.blade.php:539` | Prueba E2E del POS en navegador |
| P3 | API sin consumidor | quality | 6 endpoint(s) sin llamada desde la web ni la app móvil (6 sin prueba que los use) | Confirmado | `routes.php:419`; `routes.php:420`; `routes.php:421`; `routes.php:424`; `routes.php:581`; `routes.php:582` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | sales | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:393` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | scan | 3 endpoint(s) sin llamada desde la web ni la app móvil (3 sin prueba que los use) | Confirmado | `routes.php:499`; `routes.php:500`; `routes.php:503` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | schedule | 4 endpoint(s) sin llamada desde la web ni la app móvil (4 sin prueba que los use) | Confirmado | `routes.php:456`; `routes.php:457`; `routes.php:461`; `routes.php:492` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Integración incompleta | schedule | Conector de nómina PAYCOM inactivo (sin credenciales ni documentación de API) | Documentado | DAIRY_AUDIT.md Etapa 29; `services/LaborForecastService.php:599` | Validar API del proveedor antes de sincronizar |
| P3 | API sin consumidor | stockoverview | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:445` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Permisos insuficientes | stockoverview | /api/objects/stock* no filtra por sucursal (exige STOCK) | Documentado | DAIRY_AUDIT.md §Etapa 27 (matriz: Aislamiento multisucursal); `controllers/Api/GenericEntityApiController.php` | Filtrar por user_branches en GenericEntityApiController para tablas de stock |
| P3 | API sin consumidor | suppliers | 4 endpoint(s) sin llamada desde la web ni la app móvil (4 sin prueba que los use) | Confirmado | `routes.php:408`; `routes.php:409`; `routes.php:414`; `routes.php:416` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | API sin consumidor | waste | 7 endpoint(s) sin llamada desde la web ni la app móvil (6 sin prueba que los use) | Confirmado | `routes.php:370`; `routes.php:371`; `routes.php:374`; `routes.php:375`; `routes.php:377`; `routes.php:382`; `routes.php:512` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |
| P3 | Módulo aislado | waste_reasons | Causales de merma no está en el menú ni tiene conexiones verificadas | Confirmado | `helpers/DairyNavigation.php` | Enlazarlo desde el módulo que lo usa |
| P3 | API sin consumidor | workforce | 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use) | Confirmado | `routes.php:452` | Conectarlos a la UI, documentarlos como API de integración o retirarlos |

## Módulos incompletos o sin verificar
- **Panel Ejecutivo** (`/kpis`, Parcial): P3: 2 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use)
- **Centro de Control** (`/waste`, Parcial): P3: 7 endpoint(s) sin llamada desde la web ni la app móvil (6 sin prueba que los use)
- **Calidad & Vencimientos** (`/quality`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P3: 6 endpoint(s) sin llamada desde la web ni la app móvil (6 sin prueba que los use)
- **Stock & Movimientos** (`/stockoverview`, Parcial): P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use); P3: /api/objects/stock* no filtra por sucursal (exige STOCK)
- **Operaciones con Escáner** (`/scan`, Parcial): P3: 3 endpoint(s) sin llamada desde la web ni la app móvil (3 sin prueba que los use)
- **Mapa de Ubicaciones** (`/locations/map`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Control de Inventario** (`/inventory-control`, Parcial): P3: 4 endpoint(s) sin llamada desde la web ni la app móvil (4 sin prueba que los use)
- **Conteos Físicos** (`/inventory`, Parcial): P3: Conteos y ajustes sin prueba HTTP concurrente
- **Sucursales** (`/branches`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P2: Semillas demo en migraciones sin condicionar a MODE=demo: una instalación nueva nace con datos ficticios; P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use)
- **Planificación MRP** (`/mrp`, Parcial): P3: 2 endpoint(s) sin llamada desde la web ni la app móvil (2 sin prueba que los use)
- **Proveedores & Compras** (`/suppliers`, Parcial): P3: 4 endpoint(s) sin llamada desde la web ni la app móvil (4 sin prueba que los use)
- **Simulador What-If** (`/ai-advisor#tab-simulator`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Piso de Tienda** (`/workforce`, Parcial): P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use)
- **Personal & Turnos** (`/schedule`, Parcial): P2: Solicitudes de cobertura abiertas no se avisan en /m; P2: Decidir correcciones de fichaje solo desde móvil/API, no desde /schedule; P3: 4 endpoint(s) sin llamada desde la web ni la app móvil (4 sin prueba que los use); P3: Conector de nómina PAYCOM inactivo (sin credenciales ni documentación de API)
- **Auditorías de Tienda** (`/audits`, Parcial): P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use)
- **Punto de Venta** (`/pos`, Parcial): P3: POS: idempotencia verificada por API; UI no re-probada en navegador tras el cambio
- **Ventas** (`/sales`, Parcial): P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use)
- **Pedidos de Clientes** (`/orders`, Parcial): P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use); P3: Pedidos y picking sin prueba HTTP concurrente
- **Arturito IA** (`/ai-advisor`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P3: 1 endpoint(s) sin llamada desde la web ni la app móvil (1 sin prueba que los use)
- **Mapa del Sistema** (`/system-map`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Causales de merma** (`/waste/reasons`, Parcial): P3: Causales de merma no está en el menú ni tiene conexiones verificadas
- **Clientes** (`/customers`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P3: Clientes no está en el menú ni tiene conexiones verificadas
- **Etiquetas de ubicación** (`/locations/labels`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P3: Etiquetas de ubicación no está en el menú ni tiene conexiones verificadas
- **Importación de datos** (`/data-import`, Parcial): P3: 2 endpoint(s) sin llamada desde la web ni la app móvil (2 sin prueba que los use)
- **Documentos imprimibles** (`/document/{type}/{id}`, Parcial): Pantalla no abierta por el barrido (ruta con parámetros); P3: Documentos imprimibles no está en el menú ni tiene conexiones verificadas
- **DAIRY Mobile (PWA)** (`/m`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P2: Sin push con la app cerrada (requiere claves VAPID y servidor de envío); P3: Sin conexión no se encolan fotos ni fichaje
- **Usuarios y permisos** (`/users`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones; P1: No existe respaldo/restauración en la app (depende de copiar data/)
- **Claves API** (`/manageapikeys`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Acerca de / versión** (`/about`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Baterías (Grocy)** (`/batteriesoverview`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Tareas (Grocy)** (`/tasks`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Equipos** (`/equipment`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones
- **Calendario** (`/calendar`, Parcial): Carga sin errores, pero ninguna prueba automática cubre sus operaciones

## Conexiones entre módulos
| Desde | Hacia | Qué fluye | Estado | Evidencia |
|---|---|---|---|---|
| Punto de Venta | Ventas | La venta del POS se guarda en sales / sale_items | Comprobada | `services/SalesService.php:219` |
| Ventas | Stock & Movimientos | Cada venta descuenta stock lote a lote en orden FEFO | Comprobada | `services/SalesService.php:131` |
| Calidad & Vencimientos | Punto de Venta | El POS aplica la rebaja FEFO activa del lote | Comprobada | `services/SalesService.php:39` |
| Stock & Movimientos | Calidad & Vencimientos | Lotes con fecha de vencimiento alimentan el semáforo FEFO | Comprobada | `services/QualityExpiryService.php:25` |
| Calidad & Vencimientos | Centro de Control | Rebajas por vencimiento (prevención antes de la merma) | Comprobada | `services/MarkdownService.php:79` |
| Registrar Merma | Aprobaciones | La merma queda pendiente de aprobación según política | Comprobada | `services/WasteService.php:83` |
| Aprobaciones | Stock & Movimientos | Aprobar descuenta stock (transacción de consumo) | Comprobada | `services/WasteService.php:480` |
| Stock & Movimientos | Kardex | Kardex valoriza cada movimiento de stock_log | Comprobada | `services/KardexService.php:55` |
| Aprobaciones | Piso de Tienda | Merma aprobada dispara reglas de automatización (tareas) | Comprobada | `services/WasteService.php:857` |
| Ventas | Planificación MRP | Venta media diaria (VMD) para sugerir la compra | Comprobada | `services/MrpService.php:404` |
| Planificación MRP | Proveedores & Compras | Recomendaciones de compra usan el cálculo MRP | Comprobada | `services/SupplierProcurementService.php:533` |
| Planificación MRP | Stock & Movimientos | Recepción de OC suma stock (compra con lote y vencimiento) | Comprobada | `services/MrpService.php:827` |
| Planificación MRP | Trazabilidad | La recepción registra lote/serie para trazabilidad | Comprobada | `services/MrpService.php:914` |
| Ventas | Personal & Turnos | Ventas reales → pronóstico de dotación (Personal & Turnos) | Comprobada | `services/LaborForecastService.php:90` |
| Planificación MRP | Personal & Turnos | OC por recibir = horas de recepción en el pronóstico laboral | Comprobada | `services/LaborForecastService.php:308` |
| Auditorías de Tienda | Piso de Tienda | Hallazgo de auditoría crea tarea correctiva | Comprobada | `services/StoreAuditService.php:196` |
| Auditorías de Tienda | Centro de Alertas | Auditoría reprobada abre alerta | Comprobada | `services/AlertCenterService.php:254` |
| Control de Inventario | Centro de Alertas | Stock fantasma / anomalías generan alertas | Comprobada | `services/AlertCenterService.php:174` |
| Centro de Alertas | DAIRY Mobile (PWA) | Alerta → tarea asignada en DAIRY Mobile | Comprobada | `services/MobileWorkService.php:273` |
| Ventas | Panel Ejecutivo | Ventas y margen en el Panel Ejecutivo | Comprobada | `services/KpiService.php:23` |
| Centro de Control | Panel Ejecutivo | Merma en el Panel Ejecutivo | Comprobada | `services/KpiService.php:49` |
| Ventas | Centros de Costo | Margen por sucursal y categoría | Comprobada | `services/CostCenterService.php:109` |
| Centro de Control | Centros de Costo | Merma por centro de costo | Comprobada | `services/CostCenterService.php:125` |
| Panel Ejecutivo | Arturito IA | Arturito responde con los KPIs reales | Comprobada | `services/AdvisorQaService.php:339` |
| Planificación MRP | Arturito IA | Arturito consulta el MRP | Comprobada | `services/AdvisorQaService.php:232` |
| Centros de Costo | Arturito IA | Arturito consulta centros de costo | Comprobada | `services/AdvisorQaService.php:310` |
| Retiros de Producto | Calidad & Vencimientos | Retiro de lote pasa por cuarentena | Comprobada | `services/RecallService.php:104` |
| Traslados | Stock & Movimientos | Traslados mueven stock entre sucursales | Comprobada | `services/StockTransferService.php:209` |
| Pedidos de Clientes | Ventas | Despacho del pedido se registra como venta | Comprobada | `services/CustomerOrderService.php:9` |
| Pedidos de Clientes | Operaciones con Escáner | Picking por escáner con FEFO | Comprobada | `services/CustomerOrderService.php:54` |
| Operaciones con Escáner | Planificación MRP | Recepción por escáner contra OC | Comprobada | `services/ScanService.php:13` |
| Operaciones con Escáner | Conteos Físicos | Conteo guiado por escáner | Comprobada | `services/ScanService.php:771` |
| Trazabilidad | Kardex | Trazabilidad usa el kardex del lote | Comprobada | `services/TraceabilityService.php:176` |
| Piso de Tienda | Centro de Control | Merma exprés desde Piso de Tienda | Comprobada | `services/WorkforceService.php:146` |
| Hoy | Centro de Alertas | Hoy reúne decisiones pendientes (agenda del gerente) | Comprobada | `controllers/KpiController.php:99` |
| Importación de datos | Productos | Importación CSV de maestros | Comprobada | `services/ImportService.php:272` |
| Sucursales | Stock & Movimientos | Toda petición se acota a las sucursales del usuario | Comprobada | `middleware/DairyPermissionMiddleware.php:44` |
| DAIRY Mobile (PWA) | Personal & Turnos | Mi horario, fichaje y ausencias en móvil | Comprobada | `services/MobileWorkService.php:37` |
| DAIRY Mobile (PWA) | Registrar Merma | Merma desde móvil (escáner y foto) | Comprobada | `public/js/dairy_m.js:877` |
| Calidad & Vencimientos | Centro de Alertas | Lotes por vencer abren alerta con responsable, plazo y cierre | Comprobada | `services/AlertCenterService.php:232` |
| Conteos Físicos | Kardex | El conteo físico ajusta stock con transacción de inventario | Comprobada | `controllers/Api/StockApiController.php:517` |
| Ventas | Reportes & Analítica | Ventas y margen por sucursal en Reportes | Comprobada | `controllers/KpiController.php:52` |
| Centro de Control | Reportes & Analítica | Desglose de merma (censo) en Reportes | Comprobada | `controllers/KpiController.php:51` |
| Stock & Movimientos | Mapa de Ubicaciones | Ocupación, valor y vencimientos por ubicación | Comprobada | `controllers/OperationsController.php:88` |
| Personal & Turnos | Planificación MRP | Usar el pronóstico de ventas (no solo la VMD) en la sugerencia de compra | Propuesta | Prompt del mapa: «Ventas → Pronóstico → MRP»; MrpService usa vmd_daily_sales |
| Arturito IA | Piso de Tienda | Arturito crea tareas u órdenes desde sus recomendaciones (hoy solo responde) | Propuesta | AdvisorQaService::Ask devuelve texto y fuentes |
| Centro de Alertas | DAIRY Mobile (PWA) | Notificaciones push con la app cerrada (Web Push + VAPID) | Propuesta | DAIRY_AUDIT.md: matriz «Notificaciones push — Bloqueado» |
| Usuarios y permisos | Stock & Movimientos | Respaldo y restauración desde la aplicación | Propuesta | DAIRY_AUDIT.md: matriz «Respaldo y restauración — Bloqueado» |
| Personal & Turnos | Centros de Costo | Sincronizar horas con nómina PAYCOM | Propuesta | DAIRY_AUDIT.md Etapa 29: conector inactivo sin credenciales |
| Proveedores & Compras | Centros de Costo | Cuentas por pagar y pagos a proveedores | Propuesta | DAIRY_AUDIT.md Etapa 15: pendiente |
| Punto de Venta | Ventas | Promociones generales por período/cliente | Propuesta | DAIRY_AUDIT.md Etapa 15: pendiente |
| Pedidos de Clientes | Operaciones con Escáner | Olas de picking multi-pedido y guía de despacho imprimible | Propuesta | DAIRY_AUDIT.md Etapa 22: pendiente |
| Personal & Turnos | DAIRY Mobile (PWA) | Avisar en /m las solicitudes de cobertura abiertas | Propuesta | DAIRY_AUDIT.md Etapa 29: pendiente P2 |
| Personal & Turnos | Personal & Turnos | Decidir correcciones de fichaje desde la web /schedule | Propuesta | DAIRY_AUDIT.md Etapa 25: pendiente P2 (hoy móvil y API) |

## Recomendaciones para integrar mejor el sistema
- Usar el pronóstico de ventas (no solo la VMD) en la sugerencia de compra (Personal & Turnos → Planificación MRP). Fuente: Prompt del mapa: «Ventas → Pronóstico → MRP»; MrpService usa vmd_daily_sales
- Arturito crea tareas u órdenes desde sus recomendaciones (hoy solo responde) (Arturito IA → Piso de Tienda). Fuente: AdvisorQaService::Ask devuelve texto y fuentes
- Notificaciones push con la app cerrada (Web Push + VAPID) (Centro de Alertas → DAIRY Mobile (PWA)). Fuente: DAIRY_AUDIT.md: matriz «Notificaciones push — Bloqueado»
- Respaldo y restauración desde la aplicación (Usuarios y permisos → Stock & Movimientos). Fuente: DAIRY_AUDIT.md: matriz «Respaldo y restauración — Bloqueado»
- Sincronizar horas con nómina PAYCOM (Personal & Turnos → Centros de Costo). Fuente: DAIRY_AUDIT.md Etapa 29: conector inactivo sin credenciales
- Cuentas por pagar y pagos a proveedores (Proveedores & Compras → Centros de Costo). Fuente: DAIRY_AUDIT.md Etapa 15: pendiente
- Promociones generales por período/cliente (Punto de Venta → Ventas). Fuente: DAIRY_AUDIT.md Etapa 15: pendiente
- Olas de picking multi-pedido y guía de despacho imprimible (Pedidos de Clientes → Operaciones con Escáner). Fuente: DAIRY_AUDIT.md Etapa 22: pendiente
- Avisar en /m las solicitudes de cobertura abiertas (Personal & Turnos → DAIRY Mobile (PWA)). Fuente: DAIRY_AUDIT.md Etapa 29: pendiente P2
- Decidir correcciones de fichaje desde la web /schedule (Personal & Turnos → Personal & Turnos). Fuente: DAIRY_AUDIT.md Etapa 25: pendiente P2 (hoy móvil y API)

## Arquitectura
- **Frontend**: Blade (views/*.blade.php) + jQuery/Bootstrap 4 + JS por vista (public/viewjs) + componentes DAIRY (public/js/dairy_*.js, public/css/dairy_enterprise_theme.css)
- **Backend**: PHP 8 + Slim 4: controladores de página (controllers/*.php), API (controllers/Api/*.php) y servicios singleton (services/*.php)
- **Base de datos**: SQLite (data/grocy*.db) con migraciones numeradas (migrations/*.php|sql) que corren al visitar /
- **APIs**: REST bajo /api (Grocy + DAIRY); especificación OpenAPI heredada en grocy.openapi.json
- **Autenticación**: Sesión por cookie, clave API (cabecera GROCY-API-KEY), proxy inverso o LDAP; MODE=demo inicia sesión como usuario 1
- **Roles y permisos**: Permisos por usuario (user_permissions); páginas DAIRY protegidas por DairyAccess::PAGES (middleware) y APIs por permiso + sucursal (BaseApiController::DairyRead). Rol mostrado en /m: gerente/supervisor/empleado derivado de permisos
- **Multisucursal**: user_branches + DairyPermissionMiddleware acota branch_id en ruta, query y cuerpo
- **Integraciones**: Lectores de código (teclado/cámara, GS1), impresión térmica, CSV, conector PAYCOM (inactivo)
- **Aplicación móvil**: PWA /m (public/js/dairy_m.js, service worker, cola offline con idempotencia); sin app nativa por decisión
- **Servicios compartidos**: DatabaseService (transacciones/bloqueos), DairyAccess, DairyChangeLog (triggers de historial), AutomationEngineService, DocumentService
- **Pruebas**: tests/dairy: run.php (unitarias sobre copia de BD), http_sweep.php (todas las rutas GET), http_reliability.php (concurrencia), http_labor_forecast.php

### Servicios (dependencias y tablas)
| Servicio | Líneas | Depende de | Tablas | Usado por |
|---|---|---|---|---|
| AIAdvisorService | 373 |  | stock, products, quantity_units, locations, cache__products_last_purchased, waste_records, cold_chain_temperatures, quarantine_items, supplier_incidents, shopping_locations, waste_reasons, users | AiController, KpiApiController |
| AdvisorQaService | 367 | MrpService, CostCenterService, KpiService | waste_records, waste_reasons, products, stock, locations, waste_action_plans, workforce_store_tasks | KpiApiController, SystemMapCatalog |
| AkcalMetricsService | 1049 |  | stock_cycle_counts, products, quantity_units, locations, branches, users, supplier_returns, shopping_locations, waste_action_plans, waste_reasons, sales, waste_records … | KpiController, WasteController |
| AlertCenterService | 423 | LossPreventionService, MovementLedgerService | dairy_alert_rules, dairy_alert_events, branches, dairy_alerts, workforce_store_tasks, cold_chain_temperatures, locations, stock, products, quarantine_items, product_recalls, product_recall_items … | LossPreventionController, LossPreventionApiController, MobileApiController, MobileManagerService, MobileWorkService, SystemMapCatalog |
| ApiKeyService | 101 |  | api_keys, users | CalendarApiController, OpenApiController, SystemMapService |
| ApplicationService | 113 |  | migrations | BaseController, ExceptionController, SystemController, OpenApiController, SystemApiController, StockService, SystemMapService, BaseMiddleware |
| AutomationEngineService | 121 |  | automation_rules, workforce_store_tasks, quarantine_items | WasteService, SystemMapCatalog |
| BaseService | 26 |  |  | AIAdvisorService, AdvisorQaService, AkcalMetricsService, AlertCenterService, ApiKeyService, ApplicationService, AutomationEngineService, BatteriesService |
| BatteriesService | 77 |  | batteries, battery_charge_cycles, batteries_current | BatteriesController, BatteriesApiController, CalendarService, DemoDataGeneratorService |
| BranchService | 185 |  | stock, products, quantity_units, locations, cache__products_last_purchased, sales, waste_records, branches, user_permissions_resolved, users, user_branches | AiController, BranchController, KpiController, QualityExpiryController, SalesController, SupplierProcurementController, WasteController, BranchApiController |
| BudgetService | 119 |  | dairy_budgets, branches | KpiApiController, CostCenterService, KpiService, LaborForecastService |
| CalendarService | 204 | UsersService, StockService, TasksService, ChoresService, BatteriesService | products, chores, batteries, meal_plan_sections, recipes, meal_plan | CalendarController, CalendarApiController |
| ChoresService | 283 | UsersService, StockService | chores_log, chores, chores_execution_users_statistics, chores_current, chores_execution_average_frequency, users | ChoresController, ChoresApiController, CalendarService, DemoDataGeneratorService |
| CostCenterService | 140 | BudgetService | branches, sale_items, sales, products, product_groups, waste_records | KpiController, AdvisorQaService, SystemMapCatalog |
| CustomerOrderService | 432 | SalesService, ScanService | customer_order_items, customer_orders, cache__products_last_purchased, products, branches, customers, quarantine_items, customer_order_picks, stock, locations, dairy_stock_lots, dairy_scan_sessions … | OperationsController, OperationsApiController, SalesService, ScanService, SystemMapCatalog |
| CycleCountService | 173 |  | stock_cycle_counts, stock, products_last_purchased, locations, products, sale_items, sales | LossPreventionApiController, WasteApiController, ScanService, SystemMapCatalog |
| DairyExportService | 74 |  |  | OperationsApiController |
| DairyInventoryService | 311 |  | products, product_groups, quantity_units, locations, cache__products_average_price, cache__products_last_purchased, stock, sale_items, sales, waste_records, stock_log | StockController |
| DatabaseMigrationService | 87 |  | migrations | SystemController, SystemMapService |
| DatabaseService | 200 | UsersService |  | BaseController, KardexController, KpiController, LossPreventionController, OperationsController, SalesController, ScheduleController, StockReportsController |
| DemoDataGeneratorService | 478 | StockService, ChoresService, BatteriesService | migrations, users, user_permissions, locations, shopping_locations, quantity_units, product_groups, products, product_barcodes, quantity_unit_conversions, shopping_list, recipes … | SystemController, SystemMapService |
| DocumentService | 399 | MrpService, SupplierInvoiceService, StockTransferService | supplier_invoices, procurement_orders, shopping_locations, branches, procurement_receipts, procurement_receipt_items, products, quantity_units, locations, waste_records, waste_reasons, stock_cycle_counts … | DocumentController, SystemMapCatalog |
| FilesService | 117 |  |  | FilesApiController, StockService |
| ImportService | 505 | ScanService | dairy_import_jobs, products, product_barcodes, product_groups, quantity_units, locations, shopping_locations, customers, branches, dairy_change_log, users | OperationsController, OperationsApiController, SystemMapCatalog |
| KardexService | 172 |  | products, quantity_units, locations, stock_log, branches, users, dairy_stock_lots, stock, procurement_receipts, stock_transfers, waste_records, stock_cycle_counts … | KardexController, TraceabilityService, SystemMapCatalog |
| KpiService | 1398 | BudgetService, QualityExpiryService | sales, waste_records, stock, products, cache__products_last_purchased, locations, waste_reasons, quantity_units, shopping_locations, supplier_deliveries, supplier_incidents, supplier_returns … | KpiController, KpiApiController, AdvisorQaService, SystemMapCatalog |
| LaborForecastService | 1327 | WorkforceScheduleService, BudgetService, MrpService | sales, sale_items, labor_forecast_snapshots, labor_calendar_events, products, product_groups, labor_task_standards, employee_shifts, procurement_orders, procurement_order_items, supplier_deliveries, stock … | KpiController, ScheduleController, ScheduleApiController, SystemMapCatalog |
| LocalizationService | 225 |  | quantity_units | BaseController, StockController, StockApiController, SystemApiController, CalendarService, DemoDataGeneratorService, RecipesService, StockService |
| LossPreventionService | 350 |  | dairy_alert_rules, stock, locations, products, sale_items, sales, stock_cycle_counts, stock_log, users, waste_records, waste_reasons, supplier_incidents … | LossPreventionController, SalesController, WasteController, LossPreventionApiController, OperationsApiController, AlertCenterService, MobileManagerService, SystemMapCatalog |
| ManagerAgendaService | 172 |  | stock, products, locations, waste_records, waste_reasons, stock_cycle_counts, procurement_orders, shopping_locations, procurement_order_items, stock_transfers, branches, customer_order_items … | KpiController, SystemMapCatalog |
| MarkdownService | 133 |  | markdown_rules, stock, products, cache__products_last_purchased | KpiController, SalesController, WasteController, MarkdownApiController, SalesService, SystemMapCatalog |
| MobileManagerService | 650 | LossPreventionService, AlertCenterService | sales, sale_items, products, product_groups, branches, waste_records, stock, locations, waste_reasons, users, quantity_units, stock_cycle_counts … | SalesController, MobileApiController, MobileWorkService |
| MobileWorkService | 856 | WorkforceScheduleService, WorkforceService, AlertCenterService, MobileManagerService | employee_shifts, employee_attendance, branches, workforce_store_tasks, users, employees, dairy_alerts, stock_cycle_counts, waste_records, products, employee_absences, shift_swap_requests … | MobileApiController, MobileWorkApiController, SystemMapCatalog |
| MovementLedgerService | 187 |  | dairy_movement_ledger, stock_log, products, locations, users, waste_records | KardexController, MovementControlApiController, AlertCenterService |
| MrpService | 1123 | StockService, TraceabilityService | locations, stock, products_last_purchased, products, quantity_units, product_groups, supplier_product_prices, shopping_locations, suppliers_meta, quarantine_items, procurement_order_items, procurement_orders … | MrpController, MrpApiController, AdvisorQaService, DocumentService, LaborForecastService, ScanService, SupplierProcurementService, SystemMapCatalog |
| PrintService | 71 |  |  | PrintApiController |
| QualityExpiryService | 702 | StockService | stock, products, quantity_units, locations, branches, cold_chain_temperatures, quality_inspections, quarantine_items, waste_reasons, stock_log, waste_records, shopping_locations | QualityExpiryController, QualityExpiryApiController, KpiService, RecallService, SystemMapCatalog |
| RecallService | 275 | QualityExpiryService | stock, locations, branches, quarantine_items, products, product_recalls, product_recall_items, waste_records, supplier_returns, shopping_locations, users | LossPreventionController, LossPreventionApiController, SystemMapCatalog |
| RecipesService | 175 | StockService | recipes_pos_resolved, recipes, recipes_pos, recipes_nestings, products, cache__quantity_unit_conversions_resolved, shopping_list, meal_plan, recipes_resolved | RecipesController, StockController, RecipesApiController |
| SalesService | 414 | StockService, MarkdownService, CustomerOrderService | quarantine_items, stock, locations, sale_items, sales, branches, customers, users, products, quantity_units, stock_log, cache__products_last_purchased | SalesController, SalesApiController, CustomerOrderService, SystemMapCatalog |
| ScanService | 904 | MrpService, CustomerOrderService, CycleCountService, StockService, TraceabilityService | product_barcodes, products, locations, branches, stock, dairy_stock_lots, quantity_units, product_groups, quarantine_items, waste_records, stock_cycle_counts, procurement_orders … | OperationsController, MobileWorkApiController, OperationsApiController, CustomerOrderService, ImportService, TraceabilityService, SystemMapCatalog |
| SessionService | 98 |  | sessions, users | LoginController, UsersController, SystemMapService |
| SimulatorService | 94 |  | products, product_groups, quantity_units | SimulatorApiController |
| StockService | 1873 | UsersService, ApplicationService, FilesService | stock_current, products, stock_current_location_content, stock_current_locations, stock_missing_products, products_resolved, stock, stock_log, product_barcodes, quantity_unit_conversions, recipes_pos, recipes … | RecipesController, StockController, GenericEntityApiController, PrintApiController, StockApiController, WasteApiController, CalendarService, ChoresService |
| StockTransferService | 507 | StockService | locations, stock_transfer_items, stock_transfers, branches, users, products, quantity_units, waste_records, waste_reasons, stock, quarantine_items | StockTransferController, MobileApiController, StockTransferApiController, DocumentService, SystemMapCatalog |
| StoreAuditService | 256 |  | audit_templates, audit_template_items, branches, store_audits, store_audit_answers, workforce_store_tasks, users | LossPreventionController, LossPreventionApiController, SystemMapCatalog |
| SupplierInvoiceService | 194 |  | procurement_orders, supplier_invoices, procurement_order_items, supplier_invoice_items, shopping_locations, products | MrpController, MrpApiController, DocumentService, SystemMapCatalog |
| SupplierProcurementService | 565 | MrpService | supplier_product_prices, supplier_deliveries, supplier_incidents, shopping_locations, suppliers_meta, products, quantity_units, branches | SupplierProcurementController, SupplierProcurementApiController, SystemMapCatalog |
| SystemMapService | 1572 | DatabaseMigrationService, DemoDataGeneratorService, SessionService, ApplicationService, ApiKeyService |  | SystemMapApiController, DairyContextNav, SystemMapCatalog |
| TasksService | 77 | UsersService | task_categories, tasks_current, tasks | TasksController, TasksApiController, CalendarService |
| TraceabilityService | 385 | ScanService, KardexService | stock_log, dairy_stock_lots, sale_items, sales, customer_order_picks, customer_orders, procurement_receipts, stock_transfer_items, stock_transfers, products, quantity_units, locations … | OperationsController, OperationsApiController, MrpService, ScanService, SystemMapCatalog |
| UserfieldsService | 154 |  | userfields, userfield_values_resolved, userentities, userfield_values | BatteriesController, ChoresController, EquipmentController, GenericEntityController, RecipesController, StockController, TasksController, UsersController |
| UsersService | 166 |  | users, permission_hierarchy, user_permissions, user_settings, users_dto | BaseController, BatteriesController, ChoresController, StockController, TasksController, GenericEntityApiController, UsersApiController, CalendarService |
| WasteService | 1346 | StockService, WorkforceScheduleService, AutomationEngineService | waste_reasons, waste_records, products, quantity_units, branches, locations, users, waste_action_logs, stock, stock_log, workforce_store_tasks, cache__products_last_purchased | WasteController, OperationsApiController, WasteApiController, WorkforceService, SystemMapCatalog |
| WorkforceScheduleService | 1486 |  | employees, branches, users, employee_availability, employee_shifts, employee_absences, employee_shift_changes, employee_attendance, shift_templates, users_dto, coverage_requirements, role_certification_requirements … | ScheduleController, ScheduleApiController, LaborForecastService, MobileWorkService, WasteService, SystemMapCatalog |
| WorkforceService | 181 | WasteService | workforce_store_tasks, branches, users, waste_reasons | WorkforceController, WorkforceApiController, MobileWorkService, SystemMapCatalog |

## Rutas sin módulo asignado
`/`, `/manifest`, `/barcodescannertesting`, `/login`, `/logout`, `/userobjects/{userentityName}`, `/userobject/{userentityName}/{userobjectId}`, `/quantityunitconversion/{quConversionId}`, `/product/{productId}/grocycode`, `/consume`, `/stocksettings`, `/quantityunitpluraltesting`, `/stockentry/{entryId}/grocycode`, `/stockentry/{entryId}/label`, `/quantityunitconversionsresolved`, `/recipe/{recipeId}/pos/{recipePosId}`, `/recipessettings`, `/recipe/{recipeId}/grocycode`, `/mealplansection/{sectionId}`, `/chore/{choreId}`, `/choressettings`, `/chore/{choreId}/grocycode`, `/battery/{batteryId}`, `/batteriessettings`, `/battery/{batteryId}/grocycode`, `/task/{taskId}`, `/taskcategory/{categoryId}`, `/taskssettings`, `/waste/tab/{tab}`, `/procurement`, `/quality-expiry`

## Inventario completo de rutas
| Método | Ruta | Controlador | Módulo | Permisos en el método | Consumidores | Barrido |
|---|---|---|---|---|---|---|
| GET | `/` | SystemController::Root |  |  | 0 | OK |
| GET | `/about` | SystemController::About | about |  | 0 | OK |
| GET | `/manifest` | SystemController::Manifest |  |  | 0 | OK |
| GET | `/barcodescannertesting` | SystemController::BarcodeScannerTesting |  |  | 0 | OK |
| GET | `/login` | LoginController::LoginPage |  |  | 0 | OK |
| POST | `/login` | LoginController::ProcessLogin |  |  | 0 | OK |
| GET | `/logout` | LoginController::Logout |  |  | 0 | — |
| GET | `/userfields` | GenericEntityController::UserfieldsList | userfields |  | 0 | OK |
| GET | `/userfield/{userfieldId}` | GenericEntityController::UserfieldEditForm | userfields |  | 0 | — |
| GET | `/userentities` | GenericEntityController::UserentitiesList | userfields |  | 0 | OK |
| GET | `/userentity/{userentityId}` | GenericEntityController::UserentityEditForm | userfields |  | 0 | — |
| GET | `/userobjects/{userentityName}` | GenericEntityController::UserobjectsList |  |  | 0 | — |
| GET | `/userobject/{userentityName}/{userobjectId}` | GenericEntityController::UserobjectEditForm |  |  | 0 | — |
| GET | `/users` | UsersController::UsersList | users | USERS_READ | 0 | OK |
| GET | `/user/{userId}` | UsersController::UserEditForm | users | USERS_CREATE, USERS_EDIT_SELF, USERS_EDIT | 0 | — |
| GET | `/user/{userId}/permissions` | UsersController::PermissionList | users | USERS_READ | 0 | — |
| GET | `/user/{userId}/sessions` | UsersController::SessionList | users | USERS_EDIT_SELF, USERS_EDIT | 0 | — |
| GET | `/usersettings` | UsersController::UserSettings | users |  | 0 | OK |
| GET | `/products` | StockController::ProductsList | products |  | 0 | OK |
| GET | `/product/{productId}` | StockController::ProductEditForm | products |  | 0 | — |
| GET | `/quantityunits` | StockController::QuantityUnitsList | products |  | 0 | OK |
| GET | `/quantityunit/{quantityunitId}` | StockController::QuantityUnitEditForm | products |  | 0 | — |
| GET | `/quantityunitconversion/{quConversionId}` | StockController::QuantityUnitConversionEditForm |  |  | 0 | — |
| GET | `/productgroups` | StockController::ProductGroupsList | products |  | 0 | OK |
| GET | `/productgroup/{productGroupId}` | StockController::ProductGroupEditForm | products |  | 0 | — |
| GET | `/product/{productId}/grocycode` | StockController::ProductGrocycodeImage |  |  | 0 | — |
| GET | `/stockoverview` | StockController::Overview | stockoverview |  | 0 | OK |
| GET | `/stockentries` | StockController::Stockentries | stockoverview |  | 0 | OK |
| GET | `/purchase` | StockController::Purchase | suppliers |  | 0 | OK |
| GET | `/consume` | StockController::Consume |  |  | 0 | OK |
| GET | `/transfer` | StockController::Transfer | stockoverview |  | 0 | OK |
| GET | `/inventory` | StockController::Inventory | inventory |  | 0 | OK |
| GET | `/stockentry/{entryId}` | StockController::StockEntryEditForm | stockoverview |  | 0 | — |
| GET | `/stocksettings` | StockController::StockSettings |  |  | 0 | OK |
| GET | `/locations` | StockController::LocationsList | locations |  | 0 | OK |
| GET | `/location/{locationId}` | StockController::LocationEditForm | locations |  | 0 | — |
| GET | `/stockjournal` | StockController::Journal | stockoverview |  | 0 | OK |
| GET | `/locationcontentsheet` | StockController::LocationContentSheet | stockoverview |  | 0 | OK |
| GET | `/quantityunitpluraltesting` | StockController::QuantityUnitPluralFormTesting |  |  | 0 | OK |
| GET | `/stockjournal/summary` | StockController::JournalSummary | reports |  | 0 | OK |
| GET | `/productbarcodes/{productBarcodeId}` | StockController::ProductBarcodesEditForm | products |  | 0 | — |
| GET | `/stockentry/{entryId}/grocycode` | StockController::StockEntryGrocycodeImage |  |  | 0 | — |
| GET | `/stockentry/{entryId}/label` | StockController::StockEntryGrocycodeLabel |  |  | 0 | — |
| GET | `/quantityunitconversionsresolved` | StockController::QuantityUnitConversionsResolved |  |  | 0 | OK |
| GET | `/stockreports/spendings` | StockReportsController::Spendings | reports |  | 0 | OK |
| GET | `/shoppinglocations` | StockController::ShoppingLocationsList | shoppinglocations |  | 0 | OK |
| GET | `/shoppinglocation/{shoppingLocationId}` | StockController::ShoppingLocationEditForm | shoppinglocations |  | 0 | — |
| GET | `/shoppinglist` | StockController::ShoppingList | shoppinglist |  | 0 | OK |
| GET | `/shoppinglistitem/{itemId}` | StockController::ShoppingListItemEditForm | suppliers |  | 0 | — |
| GET | `/shoppinglist/{listId}` | StockController::ShoppingListEditForm | suppliers |  | 0 | — |
| GET | `/shoppinglistsettings` | StockController::ShoppingListSettings | shoppinglist |  | 0 | OK |
| GET | `/recipes` | RecipesController::Overview | recipes |  | 0 | OK |
| GET | `/recipe/{recipeId}` | RecipesController::RecipeEditForm | recipes |  | 0 | — |
| GET | `/recipe/{recipeId}/pos/{recipePosId}` | RecipesController::RecipePosEditForm |  |  | 0 | — |
| GET | `/recipessettings` | RecipesController::RecipesSettings |  |  | 0 | OK |
| GET | `/recipe/{recipeId}/grocycode` | RecipesController::RecipeGrocycodeImage |  |  | 0 | — |
| GET | `/mealplan` | RecipesController::MealPlan | recipes |  | 0 | OK |
| GET | `/mealplansections` | RecipesController::MealPlanSectionsList | recipes |  | 0 | OK |
| GET | `/mealplansection/{sectionId}` | RecipesController::MealPlanSectionEditForm |  |  | 0 | — |
| GET | `/choresoverview` | ChoresController::Overview | chores |  | 0 | OK |
| GET | `/choretracking` | ChoresController::TrackChoreExecution | chores |  | 0 | OK |
| GET | `/choresjournal` | ChoresController::Journal | chores |  | 0 | OK |
| GET | `/chores` | ChoresController::ChoresList | chores |  | 0 | OK |
| GET | `/chore/{choreId}` | ChoresController::ChoreEditForm |  |  | 0 | — |
| GET | `/choressettings` | ChoresController::ChoresSettings |  |  | 0 | OK |
| GET | `/chore/{choreId}/grocycode` | ChoresController::ChoreGrocycodeImage |  |  | 0 | — |
| GET | `/batteriesoverview` | BatteriesController::Overview | batteries |  | 0 | OK |
| GET | `/batterytracking` | BatteriesController::TrackChargeCycle | batteries |  | 0 | OK |
| GET | `/batteriesjournal` | BatteriesController::Journal | batteries |  | 0 | OK |
| GET | `/batteries` | BatteriesController::BatteriesList | batteries |  | 0 | OK |
| GET | `/battery/{batteryId}` | BatteriesController::BatteryEditForm |  |  | 0 | — |
| GET | `/batteriessettings` | BatteriesController::BatteriesSettings |  |  | 0 | OK |
| GET | `/battery/{batteryId}/grocycode` | BatteriesController::BatteryGrocycodeImage |  |  | 0 | — |
| GET | `/tasks` | TasksController::Overview | tasks |  | 0 | OK |
| GET | `/task/{taskId}` | TasksController::TaskEditForm |  |  | 0 | — |
| GET | `/taskcategories` | TasksController::TaskCategoriesList | tasks |  | 0 | OK |
| GET | `/taskcategory/{categoryId}` | TasksController::TaskCategoryEditForm |  |  | 0 | — |
| GET | `/taskssettings` | TasksController::TasksSettings |  |  | 0 | OK |
| GET | `/equipment` | EquipmentController::Overview | equipment |  | 0 | OK |
| GET | `/equipment/{equipmentId}` | EquipmentController::EditForm | workforce |  | 0 | — |
| GET | `/calendar` | CalendarController::Overview | calendar |  | 0 | OK |
| GET | `/api` | OpenApiController::DocumentationUi | apikeys |  | 0 | OK |
| GET | `/manageapikeys` | OpenApiController::ApiKeysList | apikeys | ADMIN | 0 | OK |
| GET | `/manageapikeys/new` | OpenApiController::CreateNewApiKey | apikeys |  | 0 | OK |
| GET | `/waste` | WasteController::Overview | waste |  | 0 | OK |
| GET | `/waste/report` | WasteController::ReportForm | waste_report |  | 0 | OK |
| GET | `/waste/approvals` | WasteController::ApprovalsQueue | waste_approvals |  | 0 | OK |
| GET | `/waste/reasons` | WasteController::ReasonsList | waste_reasons |  | 0 | OK |
| GET | `/waste/tab/{tab}` | WasteController::Tab |  |  | 0 | — |
| GET | `/pos` | SalesController::Pos | pos |  | 0 | OK |
| GET | `/sales` | SalesController::SalesHistory | sales |  | 0 | OK |
| GET | `/customers` | SalesController::CustomersList | customers |  | 0 | OK |
| GET | `/today` | KpiController::Today | today | WASTE_APPROVE, TASKS_MARK_COMPLETED | 0 | OK |
| GET | `/m` | MobileController::App | mobile |  | 0 | OK |
| GET | `/kpis` | KpiController::ExecutiveDashboard | kpis |  | 0 | OK |
| GET | `/reports` | KpiController::ReportsHub | reports |  | 0 | OK |
| GET | `/cost-centers` | KpiController::CostCenters | cost_centers |  | 0 | OK |
| GET | `/branches` | BranchController::BranchesList | branches | BRANCH_MANAGE | 0 | OK |
| GET | `/branch/{branchId}/stock` | BranchController::BranchStock | branches |  | 0 | — |
| GET | `/ai-advisor` | AiController::AdvisorView | ai_advisor |  | 0 | OK |
| GET | `/suppliers` | SupplierProcurementController::Overview | suppliers |  | 0 | OK |
| GET | `/procurement` | closure (redirección) |  |  | 0 | OK |
| GET | `/quality` | QualityExpiryController::Overview | quality |  | 0 | OK |
| GET | `/quality-expiry` | closure (redirección) |  |  | 0 | OK |
| GET | `/workforce` | WorkforceController::StoreFloorView | workforce |  | 0 | OK |
| GET | `/schedule` | ScheduleController::Overview | schedule | WORKFORCE_SCHEDULE_MANAGE, WORKFORCE_ATTENDANCE_MANAGE, KPIS_VIEW, WORKFORCE_OVERTIME_APPROVE | 0 | OK |
| GET | `/mrp` | MrpController::Overview | mrp |  | 0 | OK |
| GET | `/transfers` | StockTransferController::Overview | transfers |  | 0 | OK |
| GET | `/inventory-control` | LossPreventionController::InventoryControl | inventory_control | STOCK_INVENTORY | 0 | OK |
| GET | `/scan` | OperationsController::Scan | scan | STOCK_INVENTORY, STOCK_TRANSFER, PROCUREMENT_RECEIVE, WASTE_REPORT, ORDERS_PICK | 0 | OK |
| GET | `/data-import` | OperationsController::DataImport | data_import |  | 0 | OK |
| GET | `/locations/labels` | OperationsController::LocationLabels | location_labels |  | 0 | OK |
| GET | `/locations/map` | OperationsController::LocationMap | locations_map |  | 0 | OK |
| GET | `/traceability` | OperationsController::Traceability | traceability |  | 0 | OK |
| GET | `/orders` | OperationsController::Orders | orders | ORDERS_MANAGE, ORDERS_PICK, SALES_POS | 0 | OK |
| GET | `/recalls` | LossPreventionController::Recalls | recalls | RECALL_MANAGE | 0 | OK |
| GET | `/audits` | LossPreventionController::Audits | audits | ALERTS_MANAGE | 0 | OK |
| GET | `/alerts` | LossPreventionController::Alerts | alerts | ALERTS_MANAGE | 0 | OK |
| GET | `/document/{type}/{id}` | DocumentController::Show | documents |  | 0 | — |
| GET | `/kardex` | KardexController::Overview | kardex |  | 0 | OK |
| GET | `/system-map` | SystemMapController::Overview | system_map | ADMIN | 0 | OK |
| GET | `/api/openapi/specification` | OpenApiController::DocumentationSpec |  |  | 1 | OK |
| GET | `/api/system/info` | SystemApiController::GetSystemInfo |  |  | 0 | OK |
| GET | `/api/system/time` | SystemApiController::GetSystemTime |  |  | 0 | OK |
| GET | `/api/system/db-changed-time` | SystemApiController::GetDbChangedTime |  |  | 2 | OK |
| GET | `/api/system/config` | SystemApiController::GetConfig |  |  | 0 | OK |
| POST | `/api/system/log-missing-localization` | SystemApiController::LogMissingLocalization |  |  | 2 | — |
| GET | `/api/system/localization-strings` | SystemApiController::GetLocalizationStrings |  |  | 0 | OK |
| GET | `/api/objects/{entity}` | GenericEntityApiController::GetObjects |  |  | 9 | — |
| GET | `/api/objects/{entity}/{objectId}` | GenericEntityApiController::GetObject |  |  | 7 | — |
| POST | `/api/objects/{entity}` | GenericEntityApiController::AddObject |  | SHOPPINGLIST_ITEMS_ADD, RECIPES, RECIPES_MEALPLAN, EQUIPMENT, MASTER_DATA_EDIT, ADMIN | 33 | — |
| PUT | `/api/objects/{entity}/{objectId}` | GenericEntityApiController::EditObject |  | SHOPPINGLIST_ITEMS_ADD, RECIPES, RECIPES_MEALPLAN, EQUIPMENT, MASTER_DATA_EDIT, ADMIN | 39 | — |
| DELETE | `/api/objects/{entity}/{objectId}` | GenericEntityApiController::DeleteObject |  | SHOPPINGLIST_ITEMS_DELETE, RECIPES, RECIPES_MEALPLAN, EQUIPMENT, MASTER_DATA_EDIT, ADMIN | 25 | — |
| GET | `/api/userfields/{entity}/{objectId}` | GenericEntityApiController::GetUserfields |  |  | 0 | — |
| PUT | `/api/userfields/{entity}/{objectId}` | GenericEntityApiController::SetUserfields |  | MASTER_DATA_EDIT | 0 | — |
| PUT | `/api/files/{group}/{fileName}` | FilesApiController::UploadFile |  |  | 0 | — |
| GET | `/api/files/{group}/{fileName}` | FilesApiController::ServeFile |  |  | 0 | — |
| DELETE | `/api/files/{group}/{fileName}` | FilesApiController::DeleteFile |  |  | 0 | — |
| GET | `/api/users` | UsersApiController::GetUsers |  | USERS_READ | 0 | OK |
| POST | `/api/users` | UsersApiController::CreateUser |  | USERS_CREATE | 1 | OK |
| PUT | `/api/users/{userId}` | UsersApiController::EditUser |  | USERS_EDIT_SELF, USERS_EDIT | 1 | — |
| DELETE | `/api/users/{userId}` | UsersApiController::DeleteUser |  | USERS_EDIT | 1 | — |
| GET | `/api/users/{userId}/permissions` | UsersApiController::ListPermissions |  | ADMIN | 0 | — |
| POST | `/api/users/{userId}/permissions` | UsersApiController::AddPermission |  | ADMIN | 0 | — |
| PUT | `/api/users/{userId}/permissions` | UsersApiController::SetPermissions |  | ADMIN | 2 | — |
| GET | `/api/user` | UsersApiController::CurrentUser |  |  | 0 | OK |
| GET | `/api/user/settings` | UsersApiController::GetUserSettings |  |  | 0 | OK |
| GET | `/api/user/settings/{settingKey}` | UsersApiController::GetUserSetting |  |  | 0 | — |
| PUT | `/api/user/settings/{settingKey}` | UsersApiController::SetUserSetting |  |  | 1 | — |
| DELETE | `/api/user/settings/{settingKey}` | UsersApiController::DeleteUserSetting |  |  | 1 | — |
| GET | `/api/stock` | StockApiController::CurrentStock |  |  | 1 | OK |
| GET | `/api/stock/entry/{entryId}` | StockApiController::StockEntry |  |  | 1 | — |
| PUT | `/api/stock/entry/{entryId}` | StockApiController::EditStockEntry |  | STOCK_EDIT | 1 | — |
| GET | `/api/stock/volatile` | StockApiController::CurrentVolatileStock |  |  | 1 | OK |
| GET | `/api/stock/products/{productId}` | StockApiController::ProductDetails |  |  | 27 | — |
| GET | `/api/stock/products/{productId}/entries` | StockApiController::ProductStockEntries |  |  | 33 | — |
| GET | `/api/stock/products/{productId}/locations` | StockApiController::ProductStockLocations |  |  | 30 | — |
| GET | `/api/stock/products/{productId}/price-history` | StockApiController::ProductPriceHistory |  |  | 28 | — |
| POST | `/api/stock/products/{productId}/add` | StockApiController::AddProduct |  | STOCK_PURCHASE | 1 | — |
| POST | `/api/stock/products/{productId}/consume` | StockApiController::ConsumeProduct |  | STOCK_CONSUME | 3 | — |
| POST | `/api/stock/products/{productId}/transfer` | StockApiController::TransferProduct |  | STOCK_TRANSFER | 1 | — |
| POST | `/api/stock/products/{productId}/inventory` | StockApiController::InventoryProduct |  | STOCK_INVENTORY | 1 | — |
| POST | `/api/stock/products/{productId}/open` | StockApiController::OpenProduct |  | STOCK_OPEN | 2 | — |
| POST | `/api/stock/products/{productIdToKeep}/merge/{productIdToRemove}` | StockApiController::MergeProducts |  | STOCK_EDIT | 1 | — |
| POST | `/api/stock/products/{productId}/copy` | StockApiController::CopyProduct |  | STOCK_EDIT | 1 | — |
| GET | `/api/stock/products/by-barcode/{barcode}` | StockApiController::ProductDetailsByBarcode |  |  | 38 | — |
| POST | `/api/stock/products/by-barcode/{barcode}/add` | StockApiController::AddProductByBarcode |  |  | 1 | — |
| POST | `/api/stock/products/by-barcode/{barcode}/consume` | StockApiController::ConsumeProductByBarcode |  |  | 1 | — |
| POST | `/api/stock/products/by-barcode/{barcode}/transfer` | StockApiController::TransferProductByBarcode |  |  | 1 | — |
| POST | `/api/stock/products/by-barcode/{barcode}/inventory` | StockApiController::InventoryProductByBarcode |  |  | 1 | — |
| POST | `/api/stock/products/by-barcode/{barcode}/open` | StockApiController::OpenProductByBarcode |  |  | 1 | — |
| GET | `/api/stock/locations/{locationId}/entries` | StockApiController::LocationStockEntries |  |  | 0 | — |
| GET | `/api/stock/bookings/{bookingId}` | StockApiController::StockBooking |  |  | 1 | — |
| POST | `/api/stock/bookings/{bookingId}/undo` | StockApiController::UndoBooking |  | STOCK_EDIT | 6 | — |
| GET | `/api/stock/transactions/{transactionId}` | StockApiController::StockTransactions |  |  | 3 | — |
| POST | `/api/stock/transactions/{transactionId}/undo` | StockApiController::UndoTransaction |  | STOCK_EDIT | 5 | — |
| GET | `/api/stock/barcodes/external-lookup/{barcode}` | StockApiController::ExternalBarcodeLookup |  | MASTER_DATA_EDIT | 0 | — |
| GET | `/api/stock/products/{productId}/printlabel` | StockApiController::ProductPrintLabel |  |  | 31 | — |
| GET | `/api/stock/entry/{entryId}/printlabel` | StockApiController::StockEntryPrintLabel |  |  | 3 | — |
| POST | `/api/stock/shoppinglist/add-missing-products` | StockApiController::AddMissingProductsToShoppingList |  | SHOPPINGLIST_ITEMS_ADD | 1 | — |
| POST | `/api/stock/shoppinglist/add-overdue-products` | StockApiController::AddOverdueProductsToShoppingList |  | SHOPPINGLIST_ITEMS_ADD | 1 | — |
| POST | `/api/stock/shoppinglist/add-expired-products` | StockApiController::AddExpiredProductsToShoppingList |  | SHOPPINGLIST_ITEMS_ADD | 1 | — |
| POST | `/api/stock/shoppinglist/clear` | StockApiController::ClearShoppingList |  | SHOPPINGLIST_ITEMS_DELETE | 2 | — |
| POST | `/api/stock/shoppinglist/add-product` | StockApiController::AddProductToShoppingList |  | SHOPPINGLIST_ITEMS_ADD | 1 | — |
| POST | `/api/stock/shoppinglist/remove-product` | StockApiController::RemoveProductFromShoppingList |  | SHOPPINGLIST_ITEMS_DELETE | 0 | — |
| POST | `/api/recipes/{recipeId}/add-not-fulfilled-products-to-shoppinglist` | RecipesApiController::AddNotFulfilledProductsToShoppingList |  | SHOPPINGLIST_ITEMS_ADD | 2 | — |
| GET | `/api/recipes/{recipeId}/fulfillment` | RecipesApiController::GetRecipeFulfillment |  |  | 0 | — |
| POST | `/api/recipes/{recipeId}/consume` | RecipesApiController::ConsumeRecipe |  | STOCK_CONSUME | 2 | — |
| GET | `/api/recipes/fulfillment` | RecipesApiController::GetRecipeFulfillment |  |  | 0 | OK |
| POST | `/api/recipes/{recipeId}/copy` | RecipesApiController::CopyRecipe |  |  | 1 | — |
| GET | `/api/recipes/{recipeId}/printlabel` | RecipesApiController::RecipePrintLabel |  |  | 2 | — |
| GET | `/api/chores` | ChoresApiController::Current |  |  | 1 | OK |
| GET | `/api/chores/{choreId}` | ChoresApiController::ChoreDetails |  |  | 4 | — |
| POST | `/api/chores/{choreId}/execute` | ChoresApiController::TrackChoreExecution |  | CHORE_TRACK_EXECUTION | 2 | — |
| POST | `/api/chores/executions/{executionId}/undo` | ChoresApiController::UndoChoreExecution |  | CHORE_UNDO_EXECUTION | 3 | — |
| POST | `/api/chores/executions/calculate-next-assignments` | ChoresApiController::CalculateNextExecutionAssignments |  |  | 4 | — |
| GET | `/api/chores/{choreId}/printlabel` | ChoresApiController::ChorePrintLabel |  |  | 6 | — |
| POST | `/api/chores/{choreIdToKeep}/merge/{choreIdToRemove}` | ChoresApiController::MergeChores |  | MASTER_DATA_EDIT | 3 | — |
| GET | `/api/print/shoppinglist/thermal` | PrintApiController::PrintShoppingListThermal |  | SHOPPINGLIST | 1 | OK |
| GET | `/api/batteries` | BatteriesApiController::Current |  |  | 1 | OK |
| GET | `/api/batteries/{batteryId}` | BatteriesApiController::BatteryDetails |  |  | 2 | — |
| POST | `/api/batteries/{batteryId}/charge` | BatteriesApiController::TrackChargeCycle |  | BATTERIES_TRACK_CHARGE_CYCLE | 2 | — |
| POST | `/api/batteries/charge-cycles/{chargeCycleId}/undo` | BatteriesApiController::UndoChargeCycle |  | BATTERIES_UNDO_CHARGE_CYCLE | 2 | — |
| GET | `/api/batteries/{batteryId}/printlabel` | BatteriesApiController::BatteryPrintLabel |  |  | 4 | — |
| GET | `/api/tasks` | TasksApiController::Current |  |  | 1 | OK |
| POST | `/api/tasks/{taskId}/complete` | TasksApiController::MarkTaskAsCompleted |  | TASKS_MARK_COMPLETED | 1 | — |
| POST | `/api/tasks/{taskId}/undo` | TasksApiController::UndoTask |  | TASKS_UNDO_EXECUTION | 1 | — |
| GET | `/api/calendar/ical` | CalendarApiController::Ical |  |  | 0 | — |
| GET | `/api/calendar/ical/sharing-link` | CalendarApiController::IcalSharingLink |  |  | 1 | OK |
| GET | `/api/waste/reasons` | WasteApiController::GetWasteReasons |  |  | 0 | OK |
| GET | `/api/waste/reasons/{reasonId}` | WasteApiController::GetWasteReason |  |  | 0 | — |
| POST | `/api/waste/reasons` | WasteApiController::SaveWasteReason |  | WASTE_REASONS_EDIT | 1 | OK |
| POST | `/api/waste/reasons/{reasonId}/toggle-active` | WasteApiController::ToggleWasteReasonActive |  | WASTE_REASONS_EDIT | 1 | — |
| GET | `/api/waste/records` | WasteApiController::GetWasteRecords |  |  | 0 | OK |
| GET | `/api/waste/cases/{caseId}` | WasteApiController::GetCaseDetails |  |  | 0 | — |
| POST | `/api/waste/cases/{caseId}/investigation` | WasteApiController::UpdateCaseInvestigation |  | WASTE_APPROVE | 6 | — |
| GET | `/api/waste/analytics` | WasteApiController::GetWasteAnalytics |  |  | 0 | OK |
| POST | `/api/waste/report` | WasteApiController::ReportWaste |  | WASTE_REPORT | 8 | — |
| POST | `/api/waste/{wasteId}/approve` | WasteApiController::ApproveWaste |  | WASTE_APPROVE | 3 | — |
| POST | `/api/waste/{wasteId}/reject` | WasteApiController::RejectWaste |  | WASTE_APPROVE | 2 | — |
| POST | `/api/waste/upload-photo` | WasteApiController::UploadWastePhoto |  | WASTE_REPORT | 4 | — |
| GET | `/api/waste/photo/{fileName}` | WasteApiController::ServeWastePhoto |  |  | 0 | — |
| POST | `/api/waste/action-plans` | WasteApiController::SaveActionPlan |  | WASTE_APPROVE | 1 | — |
| POST | `/api/waste/action-plans/{planId}/update` | WasteApiController::UpdateActionPlanStatus |  | WASTE_APPROVE | 1 | — |
| POST | `/api/stock/cycle-counts` | WasteApiController::ScheduleCycleCount |  | STOCK_INVENTORY | 3 | — |
| POST | `/api/stock/cycle-counts/auto-abc` | WasteApiController::AutoScheduleCycleCounts |  | STOCK_INVENTORY | 2 | — |
| POST | `/api/waste/supplier-returns` | WasteApiController::SaveSupplierReturn |  | WASTE_APPROVE | 1 | — |
| POST | `/api/waste/supplier-returns/{returnId}/credit` | WasteApiController::RecordSupplierCredit |  | WASTE_APPROVE | 1 | — |
| POST | `/api/stock/cycle-counts/{countId}/execute` | WasteApiController::ExecuteCycleCount |  | STOCK_INVENTORY | 3 | — |
| POST | `/api/stock/cycle-counts/{countId}/approve-adjustment` | WasteApiController::ApproveCycleCountAdjustment |  | INVENTORY_ADJUST_APPROVE | 2 | — |
| GET | `/api/sales` | SalesApiController::GetSales |  | SALES_VIEW | 0 | OK |
| GET | `/api/sales/{saleId}` | SalesApiController::GetSale |  | SALES_VIEW | 1 | — |
| POST | `/api/sales` | SalesApiController::CreateSale |  | SALES_POS | 1 | OK |
| POST | `/api/sales/{saleId}/cancel` | SalesApiController::CancelSale |  | SALES_CANCEL | 2 | — |
| GET | `/api/kpi/executive` | KpiApiController::GetExecutiveDashboard |  | KPIS_VIEW | 0 | OK |
| GET | `/api/ai/alerts` | KpiApiController::GetSmartAlerts |  | KPIS_VIEW, LOSS_PREVENTION, ALERTS_MANAGE | 0 | OK |
| GET | `/api/budgets` | KpiApiController::GetBudgets |  | KPIS_VIEW | 0 | OK |
| POST | `/api/ai/ask` | KpiApiController::Ask |  | KPIS_VIEW | 2 | — |
| GET | `/api/branches/user-access` | BranchApiController::GetUserAccess |  | BRANCH_MANAGE | 0 | OK |
| POST | `/api/branches/user-access/{userId}` | BranchApiController::SetUserAccess |  | BRANCH_MANAGE | 1 | — |
| POST | `/api/budgets` | KpiApiController::SaveBudget |  | BUDGET_MANAGE | 1 | OK |
| GET | `/api/suppliers` | SupplierProcurementApiController::GetSuppliers |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 0 | OK |
| GET | `/api/suppliers/{supplierId}` | SupplierProcurementApiController::GetSupplier |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 0 | — |
| POST | `/api/suppliers` | SupplierProcurementApiController::SaveSupplier |  | PROCUREMENT_MANAGE | 1 | OK |
| POST | `/api/suppliers/{supplierId}/toggle-active` | SupplierProcurementApiController::ToggleActive |  | PROCUREMENT_MANAGE | 1 | — |
| GET | `/api/procurement/comparison/{productId}` | SupplierProcurementApiController::GetCostComparison |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 1 | — |
| POST | `/api/procurement/prices` | SupplierProcurementApiController::AddPrice |  | PROCUREMENT_MANAGE | 1 | — |
| POST | `/api/procurement/deliveries` | SupplierProcurementApiController::RecordDelivery |  | PROCUREMENT_RECEIVE | 0 | — |
| POST | `/api/procurement/incidents` | SupplierProcurementApiController::SaveIncident |  | PROCUREMENT_RECEIVE | 2 | — |
| GET | `/api/procurement/recommendations` | SupplierProcurementApiController::GetRecommendations |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 0 | OK |
| GET | `/api/quality/batches` | QualityExpiryApiController::GetBatchExpiry |  | QUALITY_INSPECT, QUALITY_QUARANTINE, STOCK, KPIS_VIEW | 0 | OK |
| GET | `/api/quality/cold-chain` | QualityExpiryApiController::GetColdChain |  | QUALITY_INSPECT, QUALITY_QUARANTINE, STOCK, KPIS_VIEW | 0 | OK |
| GET | `/api/quality/temperatures` | QualityExpiryApiController::GetTemperatures |  | QUALITY_INSPECT, QUALITY_QUARANTINE, STOCK, KPIS_VIEW | 0 | OK |
| POST | `/api/quality/temperatures` | QualityExpiryApiController::RecordTemperature |  | QUALITY_INSPECT | 1 | OK |
| POST | `/api/quality/inspections` | QualityExpiryApiController::RecordInspection |  | QUALITY_INSPECT | 1 | — |
| GET | `/api/quality/quarantine` | QualityExpiryApiController::GetQuarantine |  | QUALITY_INSPECT, QUALITY_QUARANTINE, STOCK, KPIS_VIEW | 0 | OK |
| POST | `/api/quality/quarantine` | QualityExpiryApiController::MoveToQuarantine |  | QUALITY_QUARANTINE | 2 | OK |
| POST | `/api/quality/quarantine/{quarantineId}/resolve` | QualityExpiryApiController::ResolveQuarantine |  | QUALITY_QUARANTINE | 1 | — |
| GET | `/api/quality/traceability/{identifier}` | QualityExpiryApiController::GetTraceability |  | QUALITY_INSPECT, QUALITY_QUARANTINE, STOCK, KPIS_VIEW | 1 | — |
| GET | `/api/mrp/calculate` | MrpApiController::GetMrpCalculations |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 0 | OK |
| GET | `/api/mrp/orders` | MrpApiController::GetOrders |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 0 | OK |
| POST | `/api/mrp/order/create` | MrpApiController::CreateOrder |  | PROCUREMENT_MANAGE | 1 | — |
| POST | `/api/mrp/orders/generate` | MrpApiController::GenerateOrders |  | PROCUREMENT_MANAGE | 1 | — |
| POST | `/api/mrp/order/{orderId}/approve` | MrpApiController::ApproveOrder |  | PROCUREMENT_MANAGE | 1 | — |
| GET | `/api/mrp/order/{orderId}` | MrpApiController::GetOrder |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 2 | — |
| POST | `/api/mrp/order/{orderId}/send` | MrpApiController::SendOrder |  | PROCUREMENT_MANAGE | 1 | — |
| POST | `/api/mrp/order/{orderId}/close` | MrpApiController::CloseOrder |  | PROCUREMENT_MANAGE | 1 | — |
| POST | `/api/mrp/order/{orderId}/receive` | MrpApiController::ReceiveOrder |  | PROCUREMENT_RECEIVE | 1 | — |
| POST | `/api/mrp/order/{orderId}/invoice` | MrpApiController::RegisterInvoice |  | PROCUREMENT_MANAGE | 1 | — |
| GET | `/api/mrp/invoices/{invoiceId}/items` | MrpApiController::GetInvoiceItems |  | PROCUREMENT_MANAGE | 1 | — |
| POST | `/api/mrp/invoices/{invoiceId}/decide` | MrpApiController::DecideInvoice |  | PROCUREMENT_MANAGE | 1 | — |
| GET | `/api/mrp/receiving-rules` | MrpApiController::GetReceivingRules |  | PROCUREMENT_MANAGE, PROCUREMENT_RECEIVE, KPIS_VIEW | 1 | OK |
| POST | `/api/mrp/receiving-rules/{productId}` | MrpApiController::SaveReceivingRule |  | PROCUREMENT_MANAGE | 1 | — |
| GET | `/api/stock/ledger/verify` | MovementControlApiController::VerifyLedger |  | STOCK | 1 | OK |
| GET | `/api/stock/anomalies` | MovementControlApiController::GetAnomalies |  | STOCK | 0 | OK |
| GET | `/api/transfers/{transferId}` | StockTransferApiController::GetTransfer |  | STOCK_TRANSFER, STOCK, KPIS_VIEW | 1 | — |
| POST | `/api/transfers` | StockTransferApiController::CreateTransfer |  | STOCK_TRANSFER | 1 | — |
| POST | `/api/transfers/{transferId}/receive` | StockTransferApiController::ReceiveTransfer |  | STOCK_TRANSFER | 2 | — |
| POST | `/api/transfers/{transferId}/cancel` | StockTransferApiController::CancelTransfer |  | STOCK_TRANSFER | 1 | — |
| GET | `/api/workforce/tasks` | WorkforceApiController::GetTasks |  | TASKS, WORKFORCE, WORKFORCE_SCHEDULE_MANAGE | 0 | OK |
| POST | `/api/workforce/tasks/{taskId}/complete` | WorkforceApiController::CompleteTask |  | TASKS_MARK_COMPLETED | 2 | — |
| POST | `/api/workforce/quick-waste` | WorkforceApiController::QuickWasteReport |  | WASTE_REPORT | 1 | — |
| GET | `/api/schedule/week` | ScheduleApiController::GetWeek |  | WORKFORCE_SCHEDULE_MANAGE, WORKFORCE_ATTENDANCE_MANAGE | 1 | OK |
| GET | `/api/schedule/my` | ScheduleApiController::GetMySchedule |  |  | 0 | OK |
| POST | `/api/schedule/employees` | ScheduleApiController::SaveEmployee |  |  | 0 | — |
| POST | `/api/schedule/employees/{id}/availability` | ScheduleApiController::SetAvailability |  |  | 1 | — |
| POST | `/api/schedule/shifts/check` | ScheduleApiController::CheckShift |  |  | 2 | — |
| POST | `/api/schedule/shifts` | ScheduleApiController::SaveShift |  |  | 1 | — |
| POST | `/api/schedule/shifts/{id}/move` | ScheduleApiController::MoveShift |  |  | 0 | — |
| POST | `/api/schedule/shifts/{id}/cancel` | ScheduleApiController::CancelShift |  |  | 1 | — |
| POST | `/api/schedule/week/publish` | ScheduleApiController::PublishWeek |  |  | 1 | — |
| POST | `/api/schedule/week/copy` | ScheduleApiController::CopyWeek |  |  | 1 | — |
| POST | `/api/schedule/coverage` | ScheduleApiController::SaveCoverage |  |  | 1 | — |
| GET | `/api/schedule/coverage/suggest` | ScheduleApiController::SuggestCoverage |  |  | 1 | OK |
| POST | `/api/schedule/certifications` | ScheduleApiController::SaveCertification |  |  | 1 | — |
| POST | `/api/schedule/certifications/{id}/delete` | ScheduleApiController::DeleteCertification |  |  | 1 | — |
| POST | `/api/schedule/role-requirements` | ScheduleApiController::SaveRoleRequirements |  |  | 1 | — |
| POST | `/api/schedule/shifts/bulk` | ScheduleApiController::BulkShifts |  |  | 1 | — |
| POST | `/api/schedule/shifts/{id}/duplicate` | ScheduleApiController::DuplicateShift |  |  | 1 | — |
| POST | `/api/schedule/shifts/{id}/split` | ScheduleApiController::SplitShift |  |  | 1 | — |
| GET | `/api/schedule/shifts/{id}/history` | ScheduleApiController::ShiftHistory |  |  | 1 | — |
| GET | `/api/schedule/changes` | ScheduleApiController::ChangeLog |  |  | 2 | OK |
| POST | `/api/schedule/undo` | ScheduleApiController::Undo |  |  | 2 | — |
| GET | `/api/schedule/templates` | ScheduleApiController::GetTemplates |  |  | 1 | OK |
| POST | `/api/schedule/templates` | ScheduleApiController::SaveTemplate |  |  | 1 | OK |
| POST | `/api/schedule/templates/{id}/delete` | ScheduleApiController::DeleteTemplate |  |  | 1 | — |
| POST | `/api/schedule/templates/{id}/apply` | ScheduleApiController::ApplyTemplate |  |  | 1 | — |
| GET | `/api/schedule/timesheet` | ScheduleApiController::GetTimesheet |  | WORKFORCE_ATTENDANCE_MANAGE | 1 | OK |
| GET | `/api/schedule/attendance` | ScheduleApiController::GetAttendance |  | WORKFORCE_ATTENDANCE_MANAGE, WORKFORCE_SCHEDULE_MANAGE | 2 | OK |
| GET | `/api/schedule/adjustments` | ScheduleApiController::GetAdjustments |  | WORKFORCE_ATTENDANCE_MANAGE | 1 | OK |
| POST | `/api/schedule/adjustments/{id}/decide` | ScheduleApiController::DecideAdjustment |  |  | 1 | — |
| GET | `/api/schedule/forecast` | ScheduleApiController::Forecast |  | WORKFORCE_SCHEDULE_MANAGE, WORKFORCE_OVERTIME_APPROVE | 1 | OK |
| GET | `/api/schedule/forecast/band` | ScheduleApiController::ForecastBand |  |  | 1 | OK |
| POST | `/api/schedule/forecast/recommend` | ScheduleApiController::ForecastRecommend |  | WORKFORCE_OVERTIME_APPROVE | 1 | — |
| POST | `/api/schedule/forecast/apply` | ScheduleApiController::ForecastApply |  | WORKFORCE_OVERTIME_APPROVE | 1 | — |
| GET | `/api/schedule/forecast/accuracy` | ScheduleApiController::ForecastAccuracy |  |  | 1 | OK |
| GET | `/api/schedule/forecast/events` | ScheduleApiController::ForecastEvents |  |  | 1 | OK |
| POST | `/api/schedule/forecast/events` | ScheduleApiController::SaveForecastEvent |  |  | 1 | OK |
| POST | `/api/schedule/forecast/events/{id}/delete` | ScheduleApiController::DeleteForecastEvent |  |  | 1 | — |
| POST | `/api/schedule/forecast/standards` | ScheduleApiController::SaveForecastStandards |  |  | 0 | — |
| POST | `/api/schedule/coverage-requests` | ScheduleApiController::CreateCoverageRequest |  |  | 1 | — |
| POST | `/api/schedule/coverage-requests/{id}/close` | ScheduleApiController::CloseCoverageRequest |  |  | 1 | — |
| GET | `/api/inventory-control/phantom` | LossPreventionApiController::Phantom |  | STOCK | 0 | OK |
| GET | `/api/scan/resolve` | OperationsApiController::Resolve |  | STOCK | 1 | OK |
| GET | `/api/scan/form-data` | OperationsApiController::FormData |  | SCAN_OPERATE | 0 | OK |
| GET | `/api/scan/sessions` | OperationsApiController::Sessions |  | SCAN_OPERATE | 0 | OK |
| POST | `/api/scan/sessions` | OperationsApiController::OpenSession |  | SCAN_OPERATE, ORDERS_PICK, STOCK_INVENTORY, STOCK_TRANSFER, PROCUREMENT_RECEIVE | 1 | OK |
| GET | `/api/scan/sessions/{id}` | OperationsApiController::GetSession |  | SCAN_OPERATE | 1 | — |
| POST | `/api/scan/sessions/{id}/lines` | OperationsApiController::AddLine |  | SCAN_OPERATE | 0 | — |
| POST | `/api/scan/lines/{lineId}/void` | OperationsApiController::VoidLine |  | SCAN_OPERATE | 1 | — |
| POST | `/api/scan/sessions/{id}/close` | OperationsApiController::CloseSession |  | SCAN_OPERATE | 1 | — |
| POST | `/api/scan/sessions/{id}/cancel` | OperationsApiController::CancelSession |  | SCAN_OPERATE | 1 | — |
| GET | `/api/inventory-control/stagnant` | OperationsApiController::StagnantLots |  | STOCK | 0 | OK |
| POST | `/api/data-import/{entity}` | OperationsApiController::ImportRun |  | DATA_IMPORT | 2 | — |
| GET | `/api/data-import/jobs` | OperationsApiController::ImportJobs |  | DATA_IMPORT | 0 | OK |
| GET | `/api/data-import/{entity}/template` | OperationsApiController::Template |  | DATA_IMPORT | 0 | — |
| GET | `/api/change-history/{table}/{id}` | OperationsApiController::History |  | STOCK | 1 | — |
| GET | `/api/waste/export` | OperationsApiController::WasteExport |  | WASTE_REPORT | 0 | OK |
| GET | `/api/orders` | OperationsApiController::Orders |  | ORDERS_MANAGE, ORDERS_PICK | 1 | OK |
| GET | `/api/traceability` | OperationsApiController::Trace |  | STOCK | 1 | OK |
| POST | `/api/orders` | OperationsApiController::CreateOrder |  | ORDERS_MANAGE | 2 | OK |
| GET | `/api/orders/{id}` | OperationsApiController::GetOrder |  | ORDERS_MANAGE, ORDERS_PICK | 0 | — |
| POST | `/api/orders/{id}/{action}` | OperationsApiController::OrderAction |  | ORDERS_MANAGE, ORDERS_PICK, SALES_POS | 3 | — |
| GET | `/api/inventory-control/abc-xyz` | LossPreventionApiController::AbcXyz |  | STOCK | 0 | OK |
| POST | `/api/inventory-control/counts` | LossPreventionApiController::ScheduleCounts |  | STOCK_INVENTORY | 1 | — |
| GET | `/api/loss/total` | LossPreventionApiController::TotalLoss |  | WASTE_APPROVE | 0 | OK |
| GET | `/api/recalls/preview` | LossPreventionApiController::RecallPreview |  | RECALL_MANAGE | 1 | OK |
| GET | `/api/recalls/{id}` | LossPreventionApiController::GetRecall |  | QUALITY_QUARANTINE | 1 | — |
| POST | `/api/recalls` | LossPreventionApiController::CreateRecall |  | RECALL_MANAGE | 1 | — |
| POST | `/api/recalls/items/{itemId}/confirm` | LossPreventionApiController::ConfirmRecallItem |  | QUALITY_QUARANTINE | 1 | — |
| POST | `/api/recalls/{id}/close` | LossPreventionApiController::CloseRecall |  | RECALL_MANAGE | 1 | — |
| POST | `/api/recalls/{id}/cancel` | LossPreventionApiController::CancelRecall |  | RECALL_MANAGE | 1 | — |
| GET | `/api/audits/{id}` | LossPreventionApiController::GetAudit |  | AUDIT_PERFORM | 0 | — |
| POST | `/api/audits/start` | LossPreventionApiController::StartAudit |  | AUDIT_PERFORM | 1 | — |
| POST | `/api/audits/templates` | LossPreventionApiController::SaveAuditTemplate |  | ALERTS_MANAGE | 1 | — |
| POST | `/api/audits/{id}/answers` | LossPreventionApiController::SaveAuditAnswers |  | AUDIT_PERFORM | 1 | — |
| GET | `/api/alerts` | LossPreventionApiController::GetAlerts |  |  | 1 | OK |
| POST | `/api/alerts/scan` | LossPreventionApiController::ScanAlerts |  | ALERTS_MANAGE | 1 | — |
| POST | `/api/alerts/rules/{code}` | LossPreventionApiController::SaveAlertRule |  | ALERTS_MANAGE | 11 | — |
| GET | `/api/alerts/{id}/events` | LossPreventionApiController::GetAlertEvents |  |  | 1 | — |
| POST | `/api/alerts/{id}/{action}` | LossPreventionApiController::AlertAction |  | ALERTS_MANAGE | 11 | — |
| GET | `/api/m/me` | MobileApiController::Me |  | KPIS_VIEW | 1 | OK |
| GET | `/api/m/home` | MobileApiController::Home |  | KPIS_VIEW, WASTE_APPROVE, STOCK, SALES_VIEW | 2 | OK |
| GET | `/api/m/sales` | MobileApiController::Sales |  | SALES_VIEW, KPIS_VIEW | 1 | OK |
| GET | `/api/m/sales/{id}` | MobileApiController::Sale |  | SALES_VIEW, KPIS_VIEW, SALES_CANCEL | 1 | — |
| GET | `/api/m/waste` | MobileApiController::Waste |  | WASTE_REPORT, WASTE_APPROVE, KPIS_VIEW | 1 | OK |
| GET | `/api/m/inventory` | MobileApiController::Inventory |  | STOCK | 1 | OK |
| GET | `/api/m/sku/{id}` | MobileApiController::Sku |  | STOCK, WASTE_REPORT, STOCK_INVENTORY, STOCK_TRANSFER | 3 | — |
| GET | `/api/m/inbox` | MobileApiController::Inbox |  | WASTE_APPROVE, ALERTS_MANAGE, KPIS_VIEW, STOCK_INVENTORY, WASTE_REPORT, INVENTORY_ADJUST_APPROVE, STOCK_TRANSFER | 3 | OK |
| GET | `/api/m/case/{type}/{id}` | MobileApiController::CaseDetail |  | WASTE_APPROVE, WASTE_REPORT, STOCK_INVENTORY, INVENTORY_ADJUST_APPROVE, ALERTS_MANAGE, STOCK_TRANSFER | 1 | — |
| GET | `/api/m/search` | MobileApiController::Search |  | STOCK, WASTE_REPORT, STOCK_INVENTORY, STOCK_TRANSFER, SALES_VIEW, KPIS_VIEW, WASTE_APPROVE | 2 | OK |
| GET | `/api/m/lookups` | MobileApiController::Lookups |  | WASTE_APPROVE, ALERTS_MANAGE | 1 | OK |
| GET | `/api/m/today` | MobileWorkApiController::Today |  |  | 1 | OK |
| GET | `/api/m/tasks` | MobileWorkApiController::Tasks |  | TASKS, WASTE_APPROVE, ALERTS_MANAGE, KPIS_VIEW, WORKFORCE_ATTENDANCE_MANAGE | 1 | OK |
| POST | `/api/m/tasks` | MobileWorkApiController::CreateTask |  | TASKS, WORKFORCE_SCHEDULE_MANAGE, WORKFORCE_ATTENDANCE_MANAGE, ALERTS_MANAGE | 1 | OK |
| GET | `/api/m/tasks/{id}` | MobileWorkApiController::Task |  | TASKS_MARK_COMPLETED | 1 | — |
| POST | `/api/m/tasks/{id}/complete` | MobileWorkApiController::CompleteTask |  | TASKS_MARK_COMPLETED | 1 | — |
| POST | `/api/m/alerts/{id}/task` | MobileWorkApiController::TaskFromAlert |  | ALERTS_MANAGE | 1 | — |
| GET | `/api/m/team` | MobileWorkApiController::Team |  | WORKFORCE_SCHEDULE_MANAGE, WORKFORCE_ATTENDANCE_MANAGE, TASKS, ALERTS_MANAGE | 1 | OK |
| GET | `/api/m/schedule` | MobileWorkApiController::Schedule |  |  | 1 | OK |
| GET | `/api/m/notifications` | MobileWorkApiController::Notifications |  |  | 3 | OK |
| POST | `/api/m/notifications/seen` | MobileWorkApiController::NotificationsSeen |  |  | 1 | — |
| POST | `/api/m/prefs` | MobileWorkApiController::SavePrefs |  |  | 2 | — |
| GET | `/api/m/scan` | MobileWorkApiController::Scan |  | STOCK, SCAN_OPERATE, WASTE_REPORT, STOCK_INVENTORY, STOCK_TRANSFER | 2 | OK |
| POST | `/api/schedule/clock-in` | ScheduleApiController::ClockIn |  |  | 2 | — |
| POST | `/api/schedule/clock-out` | ScheduleApiController::ClockOut |  |  | 2 | — |
| POST | `/api/schedule/attendance` | ScheduleApiController::SaveAttendance |  |  | 1 | OK |
| POST | `/api/schedule/absences` | ScheduleApiController::RequestAbsence |  |  | 1 | — |
| POST | `/api/schedule/absences/{id}/decide` | ScheduleApiController::DecideAbsence |  |  | 1 | — |
| POST | `/api/schedule/swaps` | ScheduleApiController::RequestSwap |  |  | 1 | — |
| POST | `/api/schedule/swaps/{id}/decide` | ScheduleApiController::DecideSwap |  |  | 1 | — |
| GET | `/api/system-map` | SystemMapApiController::Get |  | ADMIN | 2 | OK |
| POST | `/api/system-map/refresh` | SystemMapApiController::Refresh |  | ADMIN | 1 | — |
| GET | `/api/system-map/export/{format}` | SystemMapApiController::Export |  | ADMIN | 4 | — |
| GET | `/api/dairy/search` | GlobalSearchApiController::Search |  |  | 1 | — |
| POST | `/api/simulator/evaluate` | SimulatorApiController::EvaluateSimulation |  | KPIS_VIEW | 1 | — |
| GET | `/api/markdown/active` | MarkdownApiController::GetActiveMarkdowns |  | SALES_POS, SALES_VIEW, WASTE_APPROVE, KPIS_VIEW | 0 | OK |
| GET | `/api/markdown/rules` | MarkdownApiController::GetRules |  | SALES_POS, SALES_VIEW, WASTE_APPROVE, KPIS_VIEW | 0 | OK |
| POST | `/api/markdown/rules` | MarkdownApiController::SaveRules |  | WASTE_APPROVE | 1 | OK |
