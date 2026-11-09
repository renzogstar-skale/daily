# DAILY — Estado de funciones (INTERNO, no compartir)

Fecha: 31/10/2026. Auditoría hecha para la presentación v2. Reemplaza a `09_presentacion_visual`, que queda intacta.

## Corregir antes de una demostración en vivo

| # | Problema | Efecto | Dónde |
|---|---|---|---|
| 1 | `GetParsedBody()` no existe | Error 500 al usar desde la web: conteo, recepción, devolución, reconteo, ajuste y cierre de Conciliación, y control de movimientos | `controllers/Api/ReconciliationApiController.php`, `controllers/Api/MovementControlApiController.php` |
| 2 | `public/packages/*/dist` vacíos | La app abre sin estilos ni JavaScript | Recuperar con `yarn install` |
| 3 | Faltan imágenes y se piden en bucle | Decenas de miles de peticiones; páginas colgadas o caídas | `/img/control_center/` (`depto_lacteos.jpg`, `daily_card_shelf.jpg`, `prod_bebida_proteica.jpg`) |
| 4 | Nombres reales en el código | Pueden aparecer en Horarios durante una demo | `controllers/ScheduleController.php`, `public/viewjs/schedule.js` |
| 5 | La base contiene personal real | Riesgo de exposición al mostrar la base original | `employees`, `users`, `workforce_planilla_collaborators` |

Sin el punto 1, las diapositivas 5 a 8 no se pueden repetir con los botones de la web. El caso se ejecutó con `ReconciliationService`, la misma lógica que usa la API.

## Estado por área

| Área | Estado | Nota |
|---|---|---|
| HOY, Panel, Ventas, Stock, Kardex, Mapa, Traslados, Escáner, Calidad, Mermas, Alertas, Horarios, Asistencia, Tareas, Sucursales, Reportes | Disponible | Las 37 páginas revisadas responden 200 con datos |
| Web móvil `/m` | Disponible | Hoy, Tareas, Escáner, Alertas, Inventario, Centro de Control |
| Conciliación | Parcial | Consultas correctas; escritura desde la web rota (punto 1) |
| Pronóstico de ventas y horas | Disponible | No alimenta todavía el generador de turnos |
| Reglas del generador | Disponible | Pendiente de validación jurídica |
| Integración POS | Parcial | Importación y API; no hay ruta de webhook |
| Seguridad y Salud en el Trabajo | No existe | Diapositiva 18 marcada como proyectada |
| Tiempos y movimientos | No existe | Diapositiva 15 marcada como proyectada |
| Clientes, testimonios, precios definitivos | No existen | No se citan |

## Cómo se hicieron las capturas

- Copia aislada de `data/`; el original no se modificó.
- Nombres de personas sustituidos en la copia y en pantalla (`Colaborador NN`), con verificación del texto de cada pantalla.
- Caso 10→8 real sobre la copia: `INV-2026-00002`, alerta #841, impacto $1.120 al costo, ajuste autorizado y cierre.
- Marca del producto de ejemplo oculta en las capturas.
- `_generador/` no incluye `names.json` porque contiene nombres reales. Para regenerar hay que recrearlo desde la base.

## Diferencias con el pedido

- Multisucursal: la red de demostración tiene 10 tiendas, no 8. Se muestran las tarjetas reales sin fijar el número en el título.
- Móvil «Incidencias»: no existe esa pantalla; se usa Centro de Control, que reúne los casos.
- WMS: se muestra como Mapa de Ubicaciones, Traslados, Escáner y Conteos, que son los módulos reales.
