# DAILY — Auditoría interna (Documento 1)

> **ESTRICTAMENTE CONFIDENCIAL — USO INTERNO.** No entregar a clientes, distribuidores ni socios. Contiene estado real, brechas y riesgos.

Fecha: 2026-10-24 · Fuente: código del repositorio (controladores, servicios, rutas, migraciones 0258–0297, vistas), `DAIRY_AUDIT.md` (etapas 1–35), `DAIRY_DATOS_REALES.md`, `docs/DAIRY_LOSS_CONTROL.md` y ejecución de `php tests/dairy/run.php` hoy (todas las suites en **0 fallos**).

## 0. Resumen ejecutivo

- DAILY es una **capa propia sobre Grocy** (software libre, licencia MIT): ~37 controladores API, ~60 servicios, ~95 vistas y 40 migraciones propias. Cubre mermas, inventario, calidad, compras/MRP, personal, ventas/POS, analítica y móvil (PWA).
- El núcleo operativo está **implementado y probado con datos de demostración**: ~450 verificaciones unitarias, ~170 de barrido HTTP, ~125 de concurrencia, accesibilidad axe 0 críticos.
- **Nada ha sido validado con un cliente real** ni con hardware físico (lectores Zebra/Honeywell, iOS/Android reales). No hay métricas de ahorro reales.
- Los motores "inteligentes" son **estadísticos y determinísticos** (medias ponderadas, σ, estacionalidad, reglas). No hay aprendizaje automático ni LLM. Esto es vendible como "analítica explicable", **no** como "IA predictiva".
- Bloqueantes para vender como SaaS: (1) **todo el código DAILY está sin commitear** y el remoto `origin` es el repositorio público de Grocy; (2) arquitectura de **una instancia SQLite por empresa**, sin multi-tenant ni respaldo/restauración en la app; (3) sin 2FA, sin correo/SMS/WhatsApp, push con app cerrada bloqueado; (4) conectores POS/nómina **inactivos** (solo CSV y webhook propio); (5) nombre interno "DAIRY" vs. marca "DAILY".

## 1. Escala de estados

1 Implementada y funcional · 2 Parcial · 3 Solo visual · 4 Planificada · 5 Ausente · 6 No verificable

"Funcional" = existe lógica de servidor, UI conectada y prueba automatizada que pasa. No implica validación en producción.

## 2. Inventario de capacidades

### Operaciones
| Herramienta | Estado | Evidencia / observación |
|---|---|---|
| Hoy / resumen operativo | 1 | `/today` (TodayCommandService, ManagerAgendaService): KPIs vs. ayer, agenda priorizada, aprobaciones, actividad, dotación |
| Centro de Alertas | 1 | `/alerts`: reglas configurables, sin duplicados, cierre automático, ciclo abierta→en curso→resuelta→aprobada, evidencias, pasos, prevención (0277, 0294–0296) |
| Asignación y seguimiento de tareas | 1 | Tareas de piso, tarea desde alerta, centro de tareas en `/m` |
| Responsables, prioridades, vencimientos | 1 | Responsable, plazo y severidad por alerta/tarea |
| Escalamiento | 1 | Tareas vencidas + margen → tarjeta roja en Hoy (`STORE_TASK_ESCALATION_MINUTES`) |
| Resolución de incidentes | 1 | Resolver con nota, aprobación por otra persona, descartar con motivo, bitácora |
| Aviso push con app cerrada | 4 | Bloqueado: requiere claves VAPID y servidor de envío |

### Control y prevención de mermas
| Herramienta | Estado | Evidencia / observación |
|---|---|---|
| Merma operativa y comercial | 1 | Clasificación por causal; fuente única de eventos sin doble conteo (76 pruebas) |
| Ajustes administrativos | 2 | Servicio y tablas (`admin_error_cases`, 0292) existen; sin prueba dedicada |
| Averías | 1 | Sección propia en `/waste` |
| Registro de pérdidas | 1 | Web, Piso, móvil, escáner; foto obligatoria por causal; detección de duplicados; idempotencia |
| Matriz de causales | 1 | Causales canónicas con código único, tipo, riesgo, exige foto |
| Investigaciones | 1 | 5 porqués, causal indefinida nace "en investigación" con plazo |
| Planes de acción | 1 | Crear/cerrar planes; tarea correctiva automática por merma aprobada |
| Recuperación financiera | 1 | Devoluciones a proveedor, notas de crédito, % recuperado, pérdida neta |
| Indicadores $ y % | 1 | % sobre venta neta, margen antes/después, $/m², $/1.000 u. |
| Comparativos históricos | 1 | Interanual, mensual/acumulado, cualquier año |
| Aprobación con segregación y por monto | 1 | Quien reporta no aprueba; nivel alto exige permiso especial |
| Prevención inteligente | 2 | Stock fantasma, ABC-XYZ, lotes inmovilizados, anomalías μ+2σ/μ+3σ, rebajas FEFO, "pérdidas evitadas" (atribución, no causalidad). Todo por reglas; sin ML |
| Pérdida desconocida (robo) con RFID/EAS/video | 5 | No hay integración con hardware de seguridad |

### Inventario
| Herramienta | Estado | Observación |
|---|---|---|
| Stock y movimientos | 1 | Grocy + capa por sucursal |
| Kardex | 1 | Por producto/sucursal/lote, valorizado, enlazado a documentos; historial inalterable con cadena SHA-256 |
| Conteos cíclicos | 1 | Ciego por defecto, programación ABC automática, ajuste aprobado por otra persona |
| Inventarios selectivos | 1 | Programación por productos / ranking de riesgo |
| Inventarios sensibles | 2 | Existe priorización por riesgo (stock fantasma, ABC-XYZ); no hay una lista formal de "SKU sensibles" con frecuencia propia |
| Escáner | 1 | Cámara (ZXing), lector tipo teclado HID, GS1 completo. **No probado con hardware físico**; SDK nativo Zebra/Honeywell ausente |
| Traslados | 1 | Dos pasos con tránsito y diferencias → merma |
| Ubicaciones | 1 | Jerarquía almacén›zona›pasillo›estante, etiquetas QR/Code128, mapa, clase de almacenamiento con bloqueo |
| Conciliación | 1 | Por lote; POS ↔ inventario |
| Rotación / días de inventario / exactitud (IRA) | 1 | DSI configurable, IRA por conteos |
| Reposición góndola↔bodega | 5 | Pendiente desde Etapa 19 |

### Calidad
| Herramienta | Estado | Observación |
|---|---|---|
| Vencimientos y FEFO | 1 | FEFO en venta, picking y traslados |
| Cadena de frío | 2 | Registro manual/API y rechazo en recepción; sin sensores IoT |
| Cuarentena | 1 | Bloquea venta y MRP; resolución bajo bloqueo |
| Retiros (recall) | 1 | Multisucursal, clientes afectados, reclamo a proveedor |
| Trazabilidad | 1 | Proveedor → recepción → traslado → venta/merma, hacia adelante y atrás |
| Auditorías sanitarias | 2 | Motor de auditorías con plantillas ponderadas y acciones correctivas (1); plantillas normativas específicas (p. ej. INVIMA/BPM) no cargadas (4) |

### Compras y planificación
| Herramienta | Estado | Observación |
|---|---|---|
| MRP | 1 | Disponible neto, SS por nivel de servicio, MOQ/múltiplos, tope por vida útil, por sucursal |
| Proveedores | 1 | Lead time, OTIF, puntaje con base declarada |
| Órdenes de compra | 1 | Ciclo completo, recepción contra OC, 3-way match con factura |
| Reposición | 1 | Un solo motor (MRP) |
| Pronóstico de ventas | 2 | Media móvil ponderada + estacionalidad semanal/anual + eventos + corrección aprendida; WAPE/sesgo. Sin ML; estacionalidad anual sin un año real de datos |
| Inventario óptimo | 1 | SS z·σ·√LT |
| Alertas de sobrestock / faltantes | 1 | Sobreinventario por vida útil, quiebres |
| Envío electrónico de OC (EDI/correo) / portal de proveedor | 5 | |
| Promociones generales | 5 | Solo rebajas por vencimiento |

### Personal
| Herramienta | Estado | Observación |
|---|---|---|
| Horarios, edición, plantillas, deshacer, arrastrar y soltar (web) | 1 | Historial inalterable, versión optimista |
| Cobertura por departamento | 2 | Servicio nuevo (0297) sin prueba dedicada; cobertura por franja sí probada |
| Disponibilidad, ausencias, intercambios | 1 | |
| Control de horas | 1 | Fichaje web/PWA, correcciones con aprobación segregada |
| Productividad | 1 | Venta por hora trabajada |
| Planificación según ventas / recomendaciones de dotación | 1 | Pronóstico por franja, tareas sin doble conteo, recomendación y simulador de escenarios |
| Nómina | 4 | Conector inactivo (sin API ni credenciales) |
| Geolocalización / biometría en fichaje | 4 / 5 | Decisión legal pendiente |

### Analítica
| Herramienta | Estado | Observación |
|---|---|---|
| Panel Ejecutivo, rentabilidad, costos, ventas, márgenes | 1 | Margen neto de IVA, margen tras merma, centros de costo, presupuesto vs. real |
| Por tienda / departamento / SKU | 1 | "Departamento" = grupo de producto |
| Históricos y tendencias | 1 | |
| Reportes exportables | 1 | CSV/Excel, impresión/PDF del navegador, reportes programados (requiere cron del SO) |
| Conector BI (Power BI, Looker) | 5 | Solo vía API REST |
| Asistente "Arturito" | 2 | Responde con datos reales y acciones con confirmación; motor determinístico, sin LLM |

### Plataforma
| Herramienta | Estado | Observación |
|---|---|---|
| Web | 1 | 82 páginas sin errores JS en barrido |
| Móvil | 1 (PWA) / 5 (nativa) | PWA `/m` por rol, offline con cola; sin app en tiendas |
| Roles y permisos | 1 | RBAC + aislamiento por sucursal en servidor |
| Seguridad | 2 | Sesiones, claves API, idempotencia, auditoría de cambios. **Sin 2FA**, sin SSO/SAML propio, sin pruebas de penetración |
| Integraciones | 2 | API REST/OpenAPI, webhook POS con HMAC, CSV. Conectores POS/nómina inactivos |
| Notificaciones | 2 | In-app y del sistema con app abierta; sin correo, SMS ni WhatsApp |
| UX / accesibilidad | 1 | axe 0 críticos en 28 rutas + 35 vistas móviles |
| Rendimiento | 6 | Rutas < 2 s con BD demo; sin prueba de carga con volumen real |
| Escalabilidad / multiempresa | 2 | Una instancia SQLite por empresa; multisucursal dentro de la instancia; sin consola central multi-tenant |
| Respaldo y restauración | 4 | No existe en la app |
| Multimoneda | 5 | Moneda única por instancia |

## 3. Riesgos que bloquean la venta

| # | Riesgo | Gravedad | Acción |
|---|---|---|---|
| R1 | Código DAILY sin commitear; `origin` = github.com/grocy/grocy (público) | Crítica | Repositorio privado propio hoy mismo; nunca hacer push al remoto actual |
| R2 | Licencia MIT de Grocy: la base no es propiedad exclusiva; hay que conservar el aviso de copyright | Alta (legal) | La PI vendible es la capa DAILY; documentarlo en contratos (ver Doc. 3 §6) |
| R3 | Sin respaldo/restauración ni monitoreo | Crítica | Respaldo automático cifrado + prueba de restauración antes del primer cliente |
| R4 | SQLite por instancia: escritura concurrente limitada | Media | Suficiente para 1–10 tiendas por instancia (hipótesis, medir); cadenas grandes requieren prueba de carga o migrar BD |
| R5 | Sin 2FA ni pentest | Alta para cadenas | 2FA TOTP + revisión de seguridad externa |
| R6 | Datos demo sembrados en migraciones | Media | Instalaciones de cliente con `MODE=production` y carga por capas |
| R7 | Marca mixta DAIRY/DAILY en código y textos | Baja | Unificar textos visibles a DAILY |
| R8 | Ningún beneficio económico medido con cliente real | Alta (comercial) | Piloto con línea base (Doc. 3 §4) |

## 4. Qué está listo para vender hoy (con piloto)

Mermas completas, inventario con kardex y conteos, calidad/FEFO/cuarentena/retiros/trazabilidad, compras con MRP y 3-way match, horarios y fichaje, panel ejecutivo y reportes, PWA móvil. Condición: R1, R3 y R6 resueltos antes de instalar en un cliente.
