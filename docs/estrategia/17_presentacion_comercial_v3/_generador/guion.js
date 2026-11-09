// Genera el guion del presentador a partir de las notas de cada diapositiva
const fs = require('fs');
const { S, FULL } = require('./deck');
const LABELS = ['QUÉ DECIR', 'QUÉ MOSTRAR', 'BENEFICIO', 'PREGUNTA HABITUAL', 'PREGUNTA PARA EL CLIENTE', 'ACLARAR', 'LLAMADO A LA ACCIÓN'];
const re = new RegExp('(' + LABELS.join('|') + '):', 'g');
function fmt(notes) {
  const parts = notes.split(re).filter(Boolean); let out = '';
  for (let i = 0; i < parts.length - 1; i += 2) out += `- **${parts[i][0] + parts[i].slice(1).toLowerCase()}:** ${parts[i + 1].trim()}\n`;
  return out;
}
const title = k => S[k].o.title || (k === 'cover' ? 'Portada — DAILY: Inteligencia y Control Operativo para Retail' : 'Cierre — De los datos a las decisiones');
let md = `# DAILY — Guion del presentador

> Acompaña a \`DAILY_presentacion_comercial.pptx/.pdf\` (39 diapositivas, ~45 min) y a \`DAILY_resumen_ejecutivo_5.pptx/.pdf\` (5 diapositivas, 5 min). Las mismas notas están en el panel de notas del PowerPoint.

## Reglas para presentar

- Las capturas son **pantallas reales con una empresa de demostración**. Decirlo al inicio y no presentarlas como resultados de clientes.
- Cada diapositiva lleva una etiqueta: **Función disponible**, **Escenario ilustrativo**, **Disponible + proyectado**, **Proyectado** o **Propuesta comercial**. Respetarla al hablar.
- **Seguridad y Salud en el Trabajo no existe hoy.** Tampoco el estudio de tiempos y movimientos ni la generación de turnos directa desde la demanda.
- La diferencia de inventario aparece **al registrar un conteo**. No decir que DAILY la detecta sola en tiempo real.
- Las recomendaciones son **analítica explicable** (reglas y estadística), aunque algún nombre de pantalla diga "IA".
- **El Panel Ejecutivo mensual (/kpis) está en rediseño y hoy muestra valores de muestra con una marca de ejemplo: no abrirlo en una demostración.** Usar HOY y el Informe Ejecutivo de Pérdidas.
- Los indicadores del encabezado del Centro de Prevención de Pérdidas y de Control de Mercancías son valores de muestra: no citarlos.
- La comparación con Walmart, Costco o Alkosto es de **capacidades**, no de producto: no hay integración ni relación con esas empresas.
- El ejemplo económico es **ilustrativo**: nunca prometer ahorros.
- No hay precios definitivos, clientes ni testimonios en esta presentación.

## Guion por diapositiva (versión completa)

`;
FULL.forEach((k, i) => { md += `### ${i + 1}. ${title(k)}\n\n${fmt(S[k].notes)}\n`; });
md += `## Versión ejecutiva de 5 minutos

| # | Diapositiva | Tiempo | Mensaje en una frase |
|---|---|---|---|
| 1 | Portada | 30 s | "DAILY conecta inventario, mermas, ventas y personal en una sola plataforma." |
| 2 | HOY | 1 min | "El gerente ve la operación del día hora a hora, con avisos y responsables." |
| 3 | Caso 1: del conteo al cierre | 1 min 30 s | "Una diferencia de 2 celulares se detecta al contar, se investiga y se cierra con autorización." |
| 4 | Módulos conectados | 1 min | "Un movimiento alimenta inventario, ventas, mermas, prevención, personal y reportes." |
| 5 | Próximos pasos | 1 min | "Demostración, diagnóstico, piloto de 30 días y decisión en nueve semanas." |

## Preguntas frecuentes

| Pregunta | Respuesta sugerida |
|---|---|
| ¿Reemplaza mi ERP o mi POS? | No necesariamente. Agrega control operativo y recibe datos por importación CSV/Excel o API. La integración exacta se valida en el diagnóstico. |
| ¿Funciona en varias sucursales? | Sí. Se filtra por sucursal y la gerencia compara la red. |
| ¿Detecta robos? | Señala diferencias, movimientos atípicos y casos por investigar. La causa la confirma una persona. |
| ¿Usa inteligencia artificial? | Usa analítica explicable: reglas, promedios y desviaciones. Cada recomendación muestra de dónde sale. |
| ¿Qué lectores de código acepta? | Cámara del teléfono y lectores en modo teclado. El modelo exacto se valida en el piloto. |
| ¿Hay app en las tiendas de aplicaciones? | Hoy se usa desde el navegador del teléfono. La publicación en tiendas no está incluida. |
| ¿Cumple la ley laboral de mi país? | Las reglas del generador son configurables y deben validarse con su área jurídica. |
| ¿Cuánto cuesta? | Depende de sucursales, usuarios y módulos; se cotiza después del diagnóstico. |
| ¿Tienen clientes que lo usen? | Proponemos un piloto medido como primer paso; no citamos testimonios. |
| ¿Cuánto tarda implementarlo? | Se define en el diagnóstico. El piloto propuesto dura 30 días una vez cargados los datos. |
| ¿Qué pasa con mis datos? | Son del cliente; respaldo, acceso y confidencialidad se acuerdan por contrato. |
`;
fs.writeFileSync(process.argv[2], md);
console.log('guion ok');
