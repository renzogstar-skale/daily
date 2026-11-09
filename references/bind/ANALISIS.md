# Análisis de bind.com.mx (2026-11-05)

Captura: Playwright 1.64 + Edge (Chromium) visible, 1440×900 y móvil 390×844. Modal de quiz de tryadviser.com bloqueado para que no tape las capturas. Sin CAPTCHA.

## Técnica
- WordPress 6.9 + Elementor, Swiper (carrusel de logos). Formularios HubSpot embebidos. Altura del home: ~10 000 px.
- Tipografía: Poppins (títulos y cuerpo), Rubik como secundaria. H1 64px/600.
- Paleta: azul primario #0072CE / #0D5FE8, azul marino #1C244B / #0D2D6B, naranja CTA #F2A429 / #F4932F, gris de fondo #F3F5F8.
- Header de 2 niveles: barra azul superior (Atención · Crear cuenta · Contratar ahora) y barra blanca (logo, menú, "Solicitar demo" naranja, "Iniciar sesión" azul tipo pastilla). Al bajar queda fija solo la barra blanca.
- Elementos flotantes: "Quiero renovar" (pastilla azul), WhatsApp, tarjeta "¡Asesoría gratuita!" y modal-quiz "¿No sabes si BIND es para ti?".

## Menú
- Soluciones (mega menú): Por necesidad (ERP, Inventarios, Almacén, Compras, Ventas, Facturación, Contabilidad, Ecommerce, Integraciones) / Industrias (Distribuidoras, B2B, B2C, Construcción, Pymes)
- Precios
- Compañía: Casos de éxito, Webinars, Prueba gratis, Formato gratis
- Más módulos: Recordatorios de pago, Mercado Libre, Shopify

## Estructura del home (en orden)
1. Hero: eyebrow "Sistema para empresas distribuidoras", H1 sobre el dolor del cliente ("Controla la mercancía que tienes y el dinero que te deben"), subtítulo, 2 CTA (Demo azul / Ver planes naranja) y maqueta 3D inclinada del dashboard con KPI flotantes.
2. Franja oscura de promoción: "¿Te subieron el precio de tu sistema? Cámbiate a Bind" con 3 píldoras (15 %, MSI, 15 días) y CTA.
3. Calculadora "Dinero atorado" (lead magnet): texto + tarjeta tipo calculadora con $ detenido y barras.
4. "Bind está hecho para ti si:" lista de checks + foto de persona con tarjetas de UI encima + CTA dobles.
5. Prueba social: "+10 000 empresas" con carrusel de logos y testimonio.
6. 3 tarjetas de métricas: 80 % más rápido / ahorra $60 000 / vende 4× más.
7. "¿Por qué elegir BIND?": cuadrícula de 3×3 con ícono y beneficio; hover levanta la tarjeta con sombra.
8. Planes: 3 columnas (Una bodega / Varias sucursales / Operación completa) con lista de checks; la del centro destacada con borde naranja.
9. Bloque azul redondeado: "Una operación, no una colección de módulos" + selector Por industria / Por necesidad + tarjetas.
10. "Implementación en 90 días" + acompañamiento.
11. CTA final: "Quiero saber si BIND es para mi empresa".
12. FAQ en acordeón (unas 12 preguntas).
13. Footer de 4 columnas (Bind ERP, Soluciones, Contacto/redes, Legal) + franja de copyright.

## Páginas internas capturadas
- /crea-tu-cuenta: prueba de 7 días; qué incluye y qué puedes probar.
- /precios: H1 de beneficio, resumen, planes y comparativa.
- /sistemadeadministracion/softwareadministrativo: página SEO larga (comparativa con Excel, cómo elegir).
- /erp/administracion-de-modulos: catálogo de módulos (14 000 px).

## Patrones que conviene reutilizar en DAILY
- H1 que nombra el dolor del cliente en pesos o mercancía, no una lista de funciones.
- Maqueta del producto real en perspectiva con KPI flotantes (en DAILY: HOY / Merma / Agotados).
- Lead magnet interactivo (calculadora). En DAILY: "¿Cuánto pierdes en merma y agotados?".
- Página "Hecho para ti si…" con un checklist de calificación.
- Métricas grandes de 3 en 3 y cuadrícula de beneficios con íconos.
- Planes en 3 columnas con el plan del centro destacado.
- Segmentación Por industria / Por necesidad.
- FAQ en acordeón y CTA repetido cada 2 o 3 secciones.

## Lo que no conviene copiar
- Exceso de elementos flotantes (4 a la vez) y el modal intrusivo.
- CTA con estilos inconsistentes (radios de 4, 16, 30, 50 y 999 px).
- Huecos en blanco grandes entre secciones y un salto de línea roto en el subtítulo del hero.
