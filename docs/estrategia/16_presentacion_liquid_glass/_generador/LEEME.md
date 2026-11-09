# Generador · Presentación DAILY Liquid Glass

1. `npm i` en esta carpeta (pptxgenjs, playwright-core, sharp, @fortawesome/free-solid-svg-icons).
2. `php names.php` → crea `names.json` con nombres reales para anonimizar. **No versionar ni copiar a docs.**
3. `node cap.js shots` → capturas reales de http://localhost:8080 con Edge (anonimiza nombres y marcas; informa fugas).
4. `node build_web.js ..` → `DAILY_presentacion_interactiva.html` + `capturas/`.
5. `node build_pptx.js ..` → `DAILY_presentacion_editable.pptx`.
6. `node verify_web.js ../DAILY_presentacion_interactiva.html` → renderiza cada diapositiva en `verify/`.

Contenido compartido de ambas versiones: `content.js`. Las capturas del caso 10→8 salen de `../15_presentacion_comercial_v2/capturas/`.
Trampa de pptxgenjs: muta el objeto `shadow`; pasar siempre una copia (`{ ...shadow }`) o el archivo queda dañado.
