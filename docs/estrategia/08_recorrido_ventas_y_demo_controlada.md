# DAILY — Recorrido de Ventas y demostración controlada (Documento 8)

> Uso interno del equipo comercial. Acompaña a la presentación `07_presentacion_comercial_ventas.html/.pdf`.
> Regla base: se muestra **solo** el guion de Ventas y unas pocas pantallas de apoyo; el resto de la plataforma no se abre ni se menciona en detalle.

## A. Recorrido comercial ordenado (del primer contacto al piloto)

| Etapa | Objetivo | Material | Duración |
|---|---|---|---|
| 1. Primer contacto | Conseguir la reunión | Discurso de 3 min (Documento 6, sección A) | 3 min |
| 2. Presentación | Mostrar el problema y la solución de Ventas | Documento 7, diapositivas 1–4 | 5 min |
| 3. Demostración controlada | Ver el producto con datos ficticios | Modo demo + recorrido de 8 pasos (sección B) | 15–20 min |
| 4. Cierre de la reunión | Acordar el diagnóstico | Documento 7, diapositivas 9–11 | 5 min |
| 5. Diagnóstico | Levantar la línea base (venta, margen, merma) | Propuesta (Documento 3) | 1 semana |
| 6. Piloto 90 días | Medir contra la línea base | Hoja de ruta (Documento 5) | 90 días |

## B. Guion de la demostración (orden fijo)

Pantalla de inicio: **Ventas** (`/sales`). Pulsar **«Recorrido Comercial»** y avanzar con «Sig.».

| Paso | Pantalla | Qué decir | Pregunta al cliente |
|---|---|---|---|
| 1 | Ventas · Resumen | "Toda la venta de la red en una sola vista, en tres niveles: resumen, análisis y detalle." | — |
| 2 | Filtros | "Elijo sucursal, departamento y período; todo lo que sigue respeta esa selección." | "¿Cuántas sucursales tienen?" |
| 3 | 6 KPI | Venta neta, cumplimiento, utilidad, margen, tickets y ticket promedio, comparados con el período anterior. | "¿Qué indicador revisan hoy cada mañana?" |
| 4 | Termómetro | Avance contra la meta, monto pendiente y proyección de cierre; la fórmula está a la vista. | "¿Cuándo se enteran hoy de que no llegan a la meta?" |
| 5 | Mapa de calor | Días y horas con más venta → base para decidir cajas y turnos. | "¿Cómo deciden cuántas cajas abrir?" |
| 6 | Detalle por producto | Qué artículos sostienen la venta y el margen. | — |
| 7 | Volver al resumen | Se vuelve sin perder los filtros. | — |
| 8 | Punto de venta | Emitir un ticket de prueba: descuenta primero el lote que vence primero y aparece en el tablero. | "¿Su caja descuenta hoy el inventario por lote?" |

**Apoyo opcional (solo si el cliente lo pide, máx. 1 min cada una):** Hoy (`/today`), Panel ejecutivo (`/kpis`), Mermas (`/waste`), Calidad (`/quality`), Horarios (`/operations/scheduling`). Para todo lo demás: "Lo vemos en el diagnóstico con sus datos".

## C. Modo demostración (qué se muestra y qué no)

**Activar** (antes de que llegue el cliente, con el usuario de demo ya conectado):
`http://<servidor>/daily/set-role?role=demo` → abre Ventas.

**Salir:** `http://<servidor>/daily/set-role?role=manager` (no hay botón visible; escribir la dirección).

En modo demo:
- El menú lateral solo muestra: Supervisión (Hoy, Panel ejecutivo), Ventas y Rentabilidad, Inventario (Stock, Kardex, Trazabilidad), Personal (Horarios), Calidad y Mermas.
- Cualquier otra página abierta por dirección (mapa del sistema, usuarios, compras/MRP, asesor IA, API, configuración, catálogos) **redirige a Ventas**.
- No aparece el selector de rol ni «Perfil de usuario»; el perfil muestra «Demostración».
- La búsqueda rápida (Ctrl+K) solo ofrece páginas del guion.
- La lista de páginas permitidas está en `helpers/DairyAccess.php` (`DEMO_PAGES`).

Límite: el modo demo es una vista del navegador (cookie), no una barrera de seguridad. Usar siempre un usuario y una base de demostración, nunca datos reales de clientes.

## D. Lista de control antes de la demo

- [ ] Base de demostración cargada; período con datos (últimos 30 días).
- [ ] Modo demo activado y pantalla de Ventas abierta.
- [ ] Recorrido probado de principio a fin (8 pasos).
- [ ] Producto de prueba con stock para el ticket del paso 8.
- [ ] Notificaciones del sistema operativo silenciadas; otras pestañas cerradas.
- [ ] Presentación 07 abierta en otra ventana.

## E. Puntos a vigilar en la demo actual (detectados al preparar este documento)

| Punto | Riesgo | Cómo manejarlo |
|---|---|---|
| Cumplimiento 71 % y proyección −28,9 % en el período por defecto | El tablero «se ve mal» | Explicarlo como caso real de alerta temprana, o elegir un período con mejor cumplimiento |
| Etiqueta «Fuente de meta: benchmark_proporcional» bajo el termómetro | Texto técnico visible | No detenerse ahí; corregir la etiqueta antes de demos importantes |
| «Año anterior: sin ventas registradas» | Parece falta de datos | "La base de demo tiene menos de 12 meses de historial" |
| La versión móvil (`/m`) se titula «DAIRY Mobile» | Nombre interno visible | No mostrar la vista móvil hasta corregir el título |
| Errores en la consola del navegador | Invisibles para el cliente | No abrir herramientas de desarrollador |

## F. Reglas

- Mostrar solo datos ficticios y decirlo ("datos de demostración").
- No abrir pantallas fuera del guion ni explicar cálculos internos más allá de su definición de negocio.
- Lo que está en desarrollo se dice «próxima incorporación».
