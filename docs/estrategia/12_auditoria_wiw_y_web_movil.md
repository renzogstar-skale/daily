# Auditoría DAILY: When I Work y requisitos web/móvil (20 puntos)

Fecha: 29/10/2026 · Solo diagnóstico: **no se modificó código.** Las pruebas se ejecutaron sobre copias de la BD y la navegación contra el servidor local (`MODE=demo`, `DISABLE_AUTH=true`).

## 0. Stack real (corrige supuestos de los prompts)

| Supuesto del prompt | Realidad verificada |
|---|---|
| Next.js / React web | PHP 8 + Slim + Blade + jQuery/Bootstrap 4 (base Grocy) |
| PostgreSQL + Prisma / Supabase | SQLite (`data/grocy_es.db`, 16 MB), PDO; sin Prisma ni Supabase |
| App React Native + Expo | `mobile/` = plantilla Expo SDK 58 **vacía** (`App.tsx` 20 líneas; el plan está en `PLAN_MOVIL.md`). La app móvil que funciona es la **web móvil `/m`** (`public/js/dairy_m.js` + `/api/m/*`) |
| Página comercial pública | **No existe dentro de la app.** Solo hay `/login`; los materiales comerciales son HTML/PDF en `docs/estrategia` |

Recomendación: **no migrar a Postgres/Prisma/Next ahora.** Sería reescribir el producto. Lo que conviene: Expo como cliente de las mismas APIs `/api/*` (fuente única), y planear Postgres solo cuando haya despliegue multiempresa real.

---

## Parte A — When I Work vs. DAILY

Leyenda: ✅ implementada y verificada (prueba automática o navegación) · 🟡 parcial · ⚪ existe sin conexión funcional · ❌ no implementada · 🔍 requiere investigación

| Función When I Work | DAILY | Web | /m | Evidencia / qué falta |
|---|:-:|:-:|:-:|---|
| Calendario semanal | ✅ | Sí | Turno propio | `/operations/scheduling`, `GET /schedule/week`; test_schedule_mgmt 42/42 |
| Calendario mensual / diario | 🟡 | Sí | No | `/schedule/monthly` (generar, publicar, exportar); falta vista diaria por horas |
| Crear, copiar, mover, dividir, cancelar turnos | ✅ | Sí | No | `/schedule/shifts`, `duplicate`, `move`, `split`, `cancel`, `bulk`, `undo` |
| Arrastrar y soltar | 🔍 | — | — | El endpoint `move` existe; falta verificar el arrastre en la UI |
| Generación automática | ✅ | Sí | No | `WorkforceAutoScheduleService`, `equity/generate`, `dynamic-plan/optimize` |
| Plantillas reutilizables | ✅ | Sí | No | `/schedule/templates` (+ apply/delete) |
| Turnos apertura/cierre/noche | ✅ | Sí | — | `CheckConflicts` contempla turnos nocturnos (día ±1) |
| Rotaciones configurables | 🟡 | Sí | — | Equidad de turnos (`/schedule/equity`); falta definir patrones de rotación explícitos |
| Publicación de horarios | ✅ | Sí | Aviso | `PublishWeek`, `monthly/publish`; /m muestra "Turno publicado" |
| Confirmación de turno por empleado | ❌ | — | — | `employee_shifts` no tiene campo de confirmación |
| Prevención de solapamientos | ✅ | Sí | — | `CheckConflicts` y `AssertNoAttendanceOverlap` |
| Reglas laborales y descansos | 🟡 | Sí | — | `/schedule/policies`, certificaciones; falta validar descanso mínimo entre turnos en todos los flujos |
| Vacaciones, permisos, compensatorios | 🟡 | Sí | Solicitar | `/schedule/vacations`, `absences` + decide; compensatorios no verificados |
| Disponibilidad del empleado | ✅ | Sí | No | `employees/{id}/availability` |
| Turnos abiertos (el empleado los toma) | 🟡 | Sí | No | `coverage-requests` (los crea el manager); **no hay ruta para que el empleado tome el turno** |
| Intercambio de turnos con aprobación | ✅ | Sí | Notifica | `RequestSwap` / `DecideSwap`; tipo `swap` en notificaciones /m |
| Aprobaciones del manager | ✅ | Sí | Sí | Swaps, ausencias y ajustes con `decide`; bandeja `/m/tasks?scope=approve` |
| Alertas de falta de cobertura | ✅ | Sí | Sí | `coverage/suggest`, `/m/team` (coverage.gaps) |
| Cobertura entre sucursales | 🟡 | Sí | — | Hay colaboradores (`/schedule/collaborators`); sin flujo de préstamo autorizado |
| Horas extra (alertas y límites) | 🟡 | Sí | No | Aviso `overtime` al programar y en la hoja de horas; sin límite que bloquee ni alerta proactiva durante la semana |
| Planificado vs. trabajado | ✅ | Sí | — | `/schedule/timesheet` (worked, scheduled, overtime) |
| Presupuesto vs. utilizado / costo laboral | 🟡 | Sí | Gerencia | `hourly_cost` y costo laboral en 10 archivos; falta un panel por departamento contra presupuesto |
| Ventas por hora trabajada / productividad | ✅ | Sí | — | SPLH en 13 archivos; `/workforce/peaks/dashboard` |
| Pronóstico de demanda con POS | ✅ | Sí | — | `LaborForecastService` (forecast, band, accuracy, eventos); test_labor_forecast 57/57 |
| Feriados y temporadas | ✅ | Sí | — | `/schedule/holidays`, `forecast/events` |
| Clock-in / clock-out | ✅ | Sí | Sí | `/attendance/clock-in\|out`, `source:'mobile'` |
| Descansos | ✅ | Sí | 🔍 | `break-start`, `break-end` |
| Retrasos, ausencias, salidas anticipadas | 🟡 | Sí | — | Asistencia vs. turno en `/attendance/overview`; falta verificar la clasificación automática |
| Corrección autorizada de marcaciones | ✅ | Sí | Aprobar | `attendance/adjustment` + decide; aviso "decisión de mi ausencia me notifica" (test_mobile_work 29 OK) |
| Geolocalización / geocerca | ❌ | — | — | Sin rastro en el código |
| Tareas por turno, checklists | 🟡 | Sí | Sí | `/workforce/tasks`, checklists de piso, `/m/tasks`; falta atar cada tarea a un turno específico |
| Mensajería individual y grupal | ❌ | — | — | No existe |
| Confirmación de lectura | 🟡 | — | Sí | `read_at` en notificaciones; no en mensajes (no existen) |
| Notificaciones push | ❌ | — | — | `sw.js` sin push; Expo sin `expo-notifications`; avisos solo dentro de la app |
| Integración de nómina | ❌ | — | — | Solo exportación mensual (`monthly/export`) |
| API, webhooks, SSO | 🟡 | — | — | API REST + claves (`manageapikeys`); sin webhooks salientes ni SSO |

**Ventaja de DAILY que When I Work no tiene:** pronóstico con ventas POS reales, SPLH, escenarios laborales, ligue con mermas e inventario. Las brechas que más pesan frente a When I Work son: **mensajería, push, turnos abiertos que el empleado pueda tomar, confirmación de turno y la app nativa.**

---

## Parte B — 20 requisitos web/móvil

Columnas: **Pública** = página comercial · **Panel** = dashboard web · **/m** = web móvil · **Expo** = app nativa · **API** = backend e infraestructura. NA = no aplica.

| # | Requisito | Estado | Riesgo | Prio. | Evidencia y acción |
|---|---|---|:-:|:-:|---|
| 3 | Secretos y seguridad de API | **Parcial** | **Alto** | **P0** | `data/` está en `.gitignore` y no hay claves en código ni en el repo. **Pero:** `ReconciliationApiController` (12 endpoints) y `RetailPreventionApiController` (14) **no validan permisos** ni en el controlador ni en el servicio; `WorkforcePeakApiController` (3) tampoco. Acción: guardas `DairyRead/DairyWrite` y filtro por sucursal |
| — | Detalle de errores expuesto | **Falla** | **Alto** | **P0** | `app.php:125` usa `addErrorMiddleware(true, …)` y `views/errors/base.blade.php` muestra archivo, línea, mensaje SQL y traza. Además imprime `{!! $exception->getMessage() !!}` **sin escapar** (riesgo XSS). Acción: detalles solo en `MODE=dev`, mensaje escapado |
| — | Autenticación desactivada | Config demo | **Alto en prod** | **P0** | `DISABLE_AUTH=true` en `data/config.php`. Acción: checklist de despliegue y bloqueo de arranque en modo `production` si está activo |
| 4 | Forzar HTTPS | No implementado | Medio | P0 | Cookies de sesión `httponly` + `SameSite=Lax` pero **sin `secure`** (`BaseAuthMiddleware.php:75`); sin HSTS ni redirección. Se resuelve en el servidor de producción + flag `secure` condicionado a HTTPS; no tocar localhost |
| 18 | Protección contra spam y fuerza bruta | No implementado | Medio | P1 | Sin rate limiting en `/login` ni en la API, sin honeypot. No hay formularios públicos (no hay página comercial). Acción: límite de intentos de login y de la API por clave |
| 16 | Enlaces rotos | Parcial | Medio | P1 | Rastreo de 66 rutas internas (menú + Reportes + /m): **0 rotas.** Errores confirmados: `/logout` sin cookie → **500** (`LoginController.php:18` pasa null a `DeleteToken`); `/api/<inexistente>` → 200 vacío en vez de 404 JSON |
| 15 | Página 404 / 401 / 403 / 500 | Parcial | Bajo | P1 | Existen `views/errors/404, 403, 500` y la ruta inexistente devuelve 404. El 500 expone detalles (ver P0) |
| 17 | Validación de formularios | Parcial | Medio | P1 | Hay validaciones de servidor en mermas (duplicados, idempotencia `X-Idempotency-Key`), turnos (`CheckConflicts`) y asistencia (solapes). Brecha: conciliación sin permisos (P0). No se auditó formulario por formulario: pendiente revisar recepciones, devoluciones y traslados |
| 12 | Velocidad de carga | Parcial | Medio | P2 | Mediana 0,19 s por página (servidor local). Lentas: `/kardex` 4,3 s, `/sales` 3,4 s, `/operations/scheduling` 3,4 s, `/alerts` 2,6 s. HTML pesado: `/quality` 3,0 MB, `/waste` 2,0 MB, `/mrp` 1,25 MB. LCP/INP/CLS **no medidos** (no hay Lighthouse instalado; no se instalaron dependencias) |
| 11 | Compresión de imágenes | No implementado | Bajo | P2 | 0 imágenes WebP/AVIF. `public/img/control_center/widget_frio.jpg` pesa 1,1 MB; fotos demo de 1–2 MB. Las fotos de evidencia de mermas **no deben recomprimirse con pérdida** |
| 10 | Textos alternativos | Parcial | Bajo | P2 | 15 de 102 `<img>` en vistas sin `alt`; ~10 botones de solo ícono sin `aria-label`/`title` |
| 13 | Contraste WCAG 2.2 AA | No verificable | Medio | P2 | Sin herramienta axe/Lighthouse. Los semáforos ya incluyen texto ("Crítico", "Atención"); medir con axe antes de declarar cumplimiento |
| 14 | Web en móvil | Parcial | Medio | P2 | `/m` es la experiencia móvil dedicada. El panel de escritorio en teléfono no se probó por pantalla. Pendiente: pruebas a 375/768 px |
| 8 | Favicon / manifest / PWA | Implementado | Bajo | P3 | `icon.svg`, `icon-32.png`, `icon-1024.png`, apple-touch-icon, `/manifest` dinámico, `sw.js`. Expo usa los íconos por defecto de la plantilla (`app.json` name "mobile") |
| 9 | Sitemap y robots | Correcto para app privada | Bajo | P3 | `robots.txt` = `Disallow: /` y `<meta name="robots" content="noindex,nofollow">` en el layout. No hay sitemap porque no hay páginas públicas. La protección real es la autenticación (hoy desactivada en demo) |
| 1 | Política de privacidad | No implementado | Alto (comercial) | P1 | No hay página ni enlace en el login ni en /m. Preparar ruta pública + borrador para revisión legal, sin declarar cumplimiento |
| 2 | Términos y condiciones | No implementado | Alto (comercial) | P1 | Ídem; separar términos de plataforma y políticas internas del cliente |
| 5 | Banner de cookies | No aplica hoy | Bajo | P3 | Solo cookies técnicas de sesión, sin analítica. Se necesitará cuando exista página comercial con GA4 |
| 6 / 7 | Metadatos, SEO, Open Graph | NA en panel · ❌ en pública | Bajo | P3 | El panel no debe indexarse (correcto). Open Graph y metadatos van en la página comercial, que no existe |
| 19 | GA4 | No implementado | Bajo | P3 | Solo para la página comercial con consentimiento; nunca en el panel |
| 20 | CTA claros | NA pública · Parcial panel | Bajo | P3 | Panel con acciones claras (Registrar, Aprobar, Exportar). Botones sin función: no detectados en el rastreo, pero el rastreo solo cubre enlaces, no botones JS |

**Estado de preparación para producción: NO LISTO.** Bloqueantes P0: endpoints sin permisos, detalles de error expuestos (con XSS posible), autenticación desactivada en la configuración actual, cookies sin `secure` y sin HTTPS.

---

## Plan de implementación (por prioridad y dependencia)

| Fase | Contenido | Archivos principales | Depende de |
|---|---|---|---|
| **P0-1** Seguridad | Guardas de permiso y sucursal en Reconciliation/RetailPrevention/WorkforcePeak; errores sin detalle fuera de `dev` y mensaje escapado; cookie `secure` bajo HTTPS; bloqueo de `DISABLE_AUTH` en producción | `controllers/Api/{Reconciliation,RetailPrevention,WorkforcePeak}ApiController.php`, `app.php`, `views/errors/base.blade.php`, `middleware/Auth/BaseAuthMiddleware.php` | — |
| **P1-1** Fallas | `/logout` sin cookie; 404 JSON en API; rate limiting en login y API | `controllers/LoginController.php`, `controllers/ExceptionController.php`, middleware nuevo | P0-1 |
| **P1-2** Legal | Rutas públicas `/privacy` y `/terms` con borrador marcado "pendiente de revisión legal"; enlaces en login y /m | vistas nuevas, `routes.php`, `DairyAccess` | — |
| **P1-3** Personal (brechas When I Work) | Tomar turnos abiertos desde /m; confirmación de turno (migración: `confirmed_at`, `confirmed_by`); límite de horas extra que bloquea con aprobación; tarea ligada a turno | `WorkforceScheduleService`, `LaborForecastService`, `MobileWorkService`, `dairy_m.js`, migración 0303 | P0-1 |
| **P1-4** Comunicaciones | Mensajes 1:1 y por equipo/sucursal con `read_at` real; avisos de cambio de horario desde el registro de cambios | servicio + migración nuevos, /m | P1-3 |
| **P2** Rendimiento y accesibilidad | Paginación del lado servidor en `/kardex`, `/sales`, `/quality`, `/waste`; WebP para imágenes de interfaz (no evidencias); `alt`/`aria-label`; axe + pruebas a 375/768 px | vistas y servicios citados | P1 |
| **P3** App nativa Expo | Pantallas de `PLAN_MOVIL.md` sobre las APIs `/api/m/*` existentes; `expo-notifications` + tokens push guardados en el servidor; nombre e íconos DAILY | `mobile/` | P1-3, P1-4 |
| **P3** Comercial | Página pública (si se decide alojarla en el mismo dominio): metadatos, Open Graph, sitemap, banner de consentimiento, GA4 sin datos sensibles, CTA con destino real | por definir | Decisión de hosting |

**Diferenciación propuesta frente a When I Work** (sobre lo que ya existe): dotación sugerida por franja según pronóstico POS + carga operativa (recepciones, conteos, mermas); alerta "poca dotación + alta merma" por sucursal y franja; recomendaciones de reasignación entre áreas y sucursales autorizadas con costo y SPLH proyectado; comparativo multisucursal de SPLH, cobertura y horas extra.
