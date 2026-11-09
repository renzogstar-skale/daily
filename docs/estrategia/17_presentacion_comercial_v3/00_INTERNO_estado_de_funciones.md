# DAILY — Estado de funciones para la presentación v3 (INTERNO, no compartir)

Fecha: 03/11/2026. Reemplaza a `15_presentacion_comercial_v2`, que queda intacta.

## Antes de una demostración en vivo

| # | Problema | Efecto | Dónde |
|---|---|---|---|
| 1 | El Panel Ejecutivo (`/kpis`) muestra cifras fijas y la marca «Alkosto - Red Colombia» | Parece un cliente real y no coincide con HOY | `views/executive_dashboard.blade.php` |
| 2 | Encabezados de KPI con valores de muestra | Cifras que no salen de la base (10.842, 94,8 %, $5.420…) | `views/loss_prevention.blade.php` (l. 177–404), `views/merchandise.blade.php`, `services/MerchandiseService.php:510` |
| 3 | Nombres de famosos en el código y la base | Aparecen en Horarios y Sucursales | `controllers/ScheduleController.php`, `public/viewjs/schedule.js`, `workforce_planilla_collaborators` |
| 4 | `/loss-prevention/counts` sin estilos | No sirve para mostrar | vista `loss_counts` |
| 5 | Pestaña «Análisis» de Ventas: `#pill-analisis` ya no existe | Captura omitida | `views/sales_history.blade.php` |

En el deck no se usa el Panel `/kpis`; la vista ejecutiva es HOY + Informe Ejecutivo de Pérdidas. Los encabezados del punto 2 se marcan «en desarrollo».

## Cómo se hicieron las capturas

- Copia aislada de `data/` en el scratchpad; servidor propio en `:8082`. El original no se tocó.
- En la copia se reemplazaron 2.436 apariciones de nombres de famosos y del titular por nombres ficticios genéricos; en pantalla, sustitución adicional en el DOM y verificación del texto (0 fugas).
- Se quitó la marca del producto de ejemplo («Samsung»). Las marcas de laptops y celulares del catálogo de demostración se dejaron.
- Caso 1 = expediente real `DEMO-001` de la empresa de demostración (8 esperadas, 6 contadas, −2 u., $1.040).

## Regenerar

```
cd _generador && npm i
node mklist.js
NAME_MAP=<mapa nombres.json> MSYS_NO_PATHCONV=1 node cap.js ../capturas list.json
NODE_PATH=<_generador>/node_modules node build.js ../capturas ..
node guion.js ../DAILY_guion_presentador.md
```

`NAME_MAP` es un JSON `{"nombre real o famoso": "nombre ficticio"}`; no se guarda en docs. `thumbs.js` y `sheet.js` generan miniaturas con cuadrícula y hojas de QA.
