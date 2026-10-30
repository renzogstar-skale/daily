# DAILY — Hoja de ruta (Documento 5)

> **Uso interno.** Tiempos para 1 desarrollador a tiempo completo; costo = semanas × costo semanal propio (hipótesis US$600–1.000/semana). Escalas: Valor / Impacto / Potencial comercial 1–5 · Complejidad y Riesgo B/M/A.

## Fase 0 — Antes del primer cliente (bloqueantes, ~4–5 semanas)

| # | Tarea | Valor | Impacto $ | Complej. | Tiempo | Dependencias | Riesgo | Potencial |
|---|---|---|---|---|---|---|---|---|
| 0.1 | Repositorio privado propio, commits de toda la capa DAILY, quitar `origin` público | 5 | Protege el activo | B | 1 día | — | B | 5 |
| 0.2 | Respaldo automático cifrado + restauración probada + monitoreo de disponibilidad | 5 | Evita pérdida total de datos del cliente | M | 1 sem | 0.1 | M | 5 |
| 0.3 | Instalación limpia `MODE=production` sin datos demo; guía de carga por capas | 5 | Habilita onboarding | B | 3 días | — | B | 5 |
| 0.4 | Autenticación de dos factores (TOTP) | 4 | Requisito de cadenas | B | 1 sem | — | B | 4 |
| 0.5 | Unificar marca visible DAIRY → DAILY | 3 | Credibilidad | B | 2 días | — | B | 4 |
| 0.6 | Alertas críticas por correo (SMTP) | 4 | Uso diario sin abrir la app | B | 1 sem | — | B | 4 |
| 0.7 | Términos de servicio, política de datos personales, contrato de piloto | 5 | Legal | B | (abogado) | — | M | 5 |
| 0.8 | Entorno demo con datos ficticios marcados para ventas | 5 | Cierra ventas | B | 3 días | 0.3 | B | 5 |

## Fase 1 — Primer trimestre con clientes (0–3 meses)

| # | Tarea | Valor | Impacto $ | Complej. | Tiempo | Dependencias | Riesgo | Potencial |
|---|---|---|---|---|---|---|---|---|
| 1.1 | Conector POS real del primer cliente (sobre la ingesta existente) | 5 | Sin ventas no hay KPIs | M | 2–3 sem | POS del cliente | M | 5 |
| 1.2 | Medidor de ROI en producto (línea base vs. actual, método visible) | 5 | Renovaciones | M | 2 sem | 1.1 | B | 5 |
| 1.3 | Notificaciones WhatsApp (proveedor oficial de API) | 4 | Adopción en LatAm | M | 1–2 sem | 0.6 | M | 4 |
| 1.4 | Push con app cerrada (VAPID) | 3 | Tareas y alertas | B | 1 sem | — | B | 3 |
| 1.5 | Prueba de carga con volumen de cadena (10 tiendas, 1 año) | 4 | Define límite por instancia | M | 1 sem | 0.3 | M | 4 |
| 1.6 | Biblioteca de plantillas de auditoría sanitaria (BPM local) | 4 | Diferenciador vs. checklists | B | 1 sem | — | B | 4 |
| 1.7 | Cierre diario de pérdidas (resumen + 3 acciones con $) | 5 | Diferenciador | M | 2 sem | — | B | 5 |

## Fase 2 — Escalar (3–6 meses)

| # | Tarea | Valor | Impacto $ | Complej. | Tiempo | Dependencias | Riesgo | Potencial |
|---|---|---|---|---|---|---|---|---|
| 2.1 | Consola multi-instancia (alta de clientes, versiones, respaldos, salud) | 5 | Reduce costo de soporte | A | 4–6 sem | 0.2 | A | 5 |
| 2.2 | Conectores ERP/contables comunes (exportación de asientos de merma/ajustes) | 4 | Cadenas | M | 3–4 sem | — | M | 4 |
| 2.3 | Promociones (precio por período y su efecto en pronóstico) | 4 | Margen | M | 3 sem | — | M | 4 |
| 2.4 | Pronóstico con festivos/promos y confianza por recomendación | 4 | Menos vencido/quiebres | M | 3 sem | 2.3 | M | 4 |
| 2.5 | Ingesta de sensores de temperatura (API genérica) | 3 | Inocuidad | M | 2 sem | — | M | 3 |
| 2.6 | Devoluciones parciales de clientes; reposición góndola↔bodega | 3 | Exactitud | M | 3 sem | — | M | 3 |
| 2.7 | Lista formal de SKU sensibles con frecuencia de conteo propia | 3 | Merma desconocida | B | 1 sem | — | B | 3 |

## Fase 3 — Enterprise (6–12 meses)

| # | Tarea | Valor | Impacto $ | Complej. | Tiempo | Dependencias | Riesgo | Potencial |
|---|---|---|---|---|---|---|---|---|
| 3.1 | SSO (SAML/OIDC) | 4 | Cadenas grandes | M | 2 sem | 0.4 | M | 4 |
| 3.2 | Base de datos servidor (PostgreSQL) si 1.5 lo exige | 4 | Escala | A | 6–10 sem | 1.5 | A | 4 |
| 3.3 | App nativa contenedora (tiendas + SDK escáner industrial) | 3 | Confianza / hardware | M | 4–6 sem | — | M | 3 |
| 3.4 | Integración de nómina (1 proveedor regional) | 3 | Personal | M | 3 sem | Acceso a API | M | 3 |
| 3.5 | Modelos de aprendizaje automático (cuando haya ≥ 12 meses de datos de clientes) | 4 | Pronóstico | A | 8+ sem | Datos reales | A | 4 |
| 3.6 | Benchmark anónimo entre clientes | 4 | Diferenciador | M | 3 sem | ≥10 clientes, consentimiento | M | 4 |
| 3.7 | Revisión de seguridad externa / pentest | 5 | Requisito corporativo | — | (proveedor) | 0.4 | B | 5 |

## No hacer (por ahora)
Planogramas, RFID/EAS/video, precio dinámico con etiquetas electrónicas, nómina propia, multimoneda — complejidad alta y bajo retorno para el nicho inicial.
