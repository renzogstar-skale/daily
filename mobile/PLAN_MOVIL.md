# DAILY Móvil (Expo) — hallazgos y plan

Estado: proyecto Expo SDK 58 creado (`blank-typescript`), dependencias: expo-camera, async-storage, safe-area-context. **Pantallas aún no escritas.**

## Hallazgos
- Web: PHP (Slim/Grocy) + Blade + JS. Ya existe app web móvil `/m` (`public/js/dairy_m.js`, `views/mobile_app.blade.php`) sobre APIs `/api/m/*`. No toca escritorio.
- Servidor local: `[::1]:8080` (solo IPv6 local). Para un teléfono: `php -S 0.0.0.0:8080 router.php`.
- `data/config.php`: MODE=demo, DISABLE_AUTH=true → usuario 1 (admin), rol API = manager. Rol simulado web (`daily_simulated_role`) NO afecta permisos de API → en la app el rol es solo una vista simulada.
- Escritura idempotente: cabecera `X-Idempotency-Key` (middleware DairyIdempotencyMiddleware).
- `/api/m/scan` lo usa dairy_m.js pero no hay ruta (responde vacío). Usar `/api/scan/resolve`.
- Otro proceso editaba today.blade.php, TodayExecutiveSalesService, migrations/0301, css executive: no tocar.
- No hay OC abiertas (todas `received`): recepción por OC mostrará "sin órdenes"; hay traslado en tránsito TRA-202610-0002 y pedidos de cliente confirmados (sucursal 6).
- UPC de prueba: 750100000001 (Leche entera, id 30, sucursal 1 con lotes).

## Endpoints a usar
- Sesión/rol: GET /m/me, /m/today?branch_id, /m/notifications, /m/lookups (reasons, locations, branches)
- Escaneo: GET /scan/resolve?code&branch_id → kind product|unknown|location (availability, entries con lote/vencimiento; 2999-12-31 = sin vencimiento). Detalle: /m/sku/{id}?branch_id (sale_price, barcode, counts, movements). Búsqueda: /m/search?q (type sku). Código `P<id>` resuelve por id.
- Conteo: POST /stock/cycle-counts {product_id, location_id, branch_id, notes} → id; POST /stock/cycle-counts/{id}/execute {counted_stock, notes} → theoretical_stock, difference_amount, message.
- Merma: POST /waste/report {product_id, amount, waste_reason_id, branch_id, location_id, stock_id?, batch_number?, notes, idempotency_key, confirm_duplicate?}; error "Posible duplicado…" → confirmar. Foto (requires_evidence) vía /waste/upload-photo: pendiente.
- Tareas: GET /m/tasks?scope=mine|approve, /m/case/{type}/{id} (actions[]); POST /m/tasks/{id}/complete {notes}; merma approve/reject {rejection_reason}; alert /alerts/{id}/acknowledge|resolve {note}.
- Recepción traslado: POST /transfers/{id}/receive {items:[{product_id, received_amount}], notes}.
- OC y preparación: /scan/form-data (orders, customer_orders, locations); POST /scan/sessions {type receive|pick, branch_id, order_id|customer_order_id}; POST /scan/sessions/{id}/lines {client_uuid, raw_code, amount}; POST /scan/sessions/{id}/close.
- Equipo: /m/team?branch_id (people, coverage.gaps, requests); turno propio /m/schedule; fichaje POST /schedule/clock-in|out {source:'mobile'}.
- Gerencia: /m/home?branch_id&period=today|7d|month (sales, waste, inventory, differences, inbox); /m/inventory, /m/waste.

## Pantallas previstas
Configuración (servidor + clave API opcional) · Hoy por rol · Escanear (cámara + manual + búsqueda) · Producto · Conteo · Merma (revisar y confirmar) · Listo · Tareas/Detalle · Recibir · Preparar pedido · Vencimientos · Equipo · Panel gerencia.
