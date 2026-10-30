// Genera el guion del presentador a partir de las notas de cada diapositiva
const fs = require('fs');
const { S, FULL, EXEC } = require('./deck');
const LABELS = ['QUÉ DECIR', 'QUÉ MOSTRAR', 'BENEFICIO', 'PREGUNTA HABITUAL', 'PREGUNTA PARA EL CLIENTE', 'PREGUNTA DE CIERRE', 'ACLARAR SIEMPRE', 'ACLARAR', 'LLAMADO A LA ACCIÓN'];
const re = new RegExp('(' + LABELS.join('|') + '):', 'g');
function fmt(notes) {
  const parts = notes.split(re).filter(Boolean); let out = '';
  for (let i = 0; i < parts.length - 1; i += 2) out += `- **${parts[i][0] + parts[i].slice(1).toLowerCase()}:** ${parts[i + 1].trim()}\n`;
  return out;
}
const title = k => S[k].o.title || (k === 'cover' ? 'Portada — DAILY: Inteligencia y Control Operativo para Retail' : 'Cierre — De los datos a las decisiones');
let md = `# DAILY — Guion del presentador

> Acompaña a \`DAILY_presentacion_comercial.pptx/.pdf\` (28 diapositivas, ~35 min) y a \`DAILY_resumen_ejecutivo_5.pptx/.pdf\` (5 diapositivas, 5 min). Las mismas notas están en las notas del orador de cada PPTX.

## Reglas para presentar

- Todas las capturas son **pantallas reales con datos de demostración**. Decirlo al inicio y no presentarlas como resultados de clientes.
- Lo que está en desarrollo se dice **"próxima incorporación"** (Seguridad industrial, conector de nómina, geolocalización en fichaje, integración con hardware antihurto).
- Las recomendaciones de DAILY son **analítica explicable (reglas y estadística)**. No venderlas como inteligencia artificial, aunque algún botón diga "IA".
- El Centro de Alertas web de la diapositiva 18 muestra un **escenario de demostración fijo**; el motor de alertas real alimenta Hoy y la app móvil.
- El ejemplo económico (diapositiva 24) es **ilustrativo**: nunca prometer ahorros.
- No hay precios definitivos ni testimonios de clientes en esta presentación.

## Guion por diapositiva (versión completa)

`;
FULL.forEach((k, i) => { md += `### ${i + 1}. ${title(k)}\n\n${fmt(S[k].notes)}\n`; });
md += `## Versión ejecutiva de 5 minutos

| # | Diapositiva | Tiempo | Mensaje en una frase |
|---|---|---|---|
| 1 | Portada | 30 s | "DAILY conecta inventario, mermas, ventas y personal en una sola plataforma." |
| 2 | Panel ejecutivo | 1 min | "El gerente ve las desviaciones del mes el mismo día." |
| 3 | Demostración: diferencia de 2 unidades | 1 min 30 s | "Recibidas 10, esperadas 8, contadas 6: la diferencia aparece al contar, con todos sus movimientos." |
| 4 | Impacto económico (ilustrativo) | 1 min | "Medio punto de merma en $1.000.000 de venta son $5.000 al mes; lo medimos con sus datos." |
| 5 | Piloto de 30 días | 1 min | "Proponemos medir en una o dos tiendas, con línea base y seis indicadores." |

## Preguntas frecuentes

| Pregunta | Respuesta sugerida |
|---|---|
| ¿Reemplaza mi ERP o mi POS? | No. Se conecta a ellos por archivo (CSV), API o webhook y agrega control operativo. |
| ¿Funciona en varias sucursales? | Sí. Cada usuario ve solo sus sucursales; la gerencia ve la red completa y compara tiendas. |
| ¿Detecta robos? | Señala diferencias, movimientos atípicos y patrones por producto. No se integra hoy con cámaras ni antenas antihurto. |
| ¿Usa inteligencia artificial? | Usa analítica explicable: promedios, desviaciones, estacionalidad y reglas. Cada recomendación muestra de dónde sale. |
| ¿Funciona sin internet en la tienda? | La app móvil guarda lecturas y registros sin conexión y los envía al reconectar. |
| ¿Qué lectores de código acepta? | Cámara del teléfono y lectores en modo teclado (GS1). La validación con el modelo exacto del cliente se hace en el piloto. |
| ¿Hay app en las tiendas de aplicaciones? | Es una app web instalable (PWA). No está publicada en tiendas. |
| ¿Cuánto cuesta? | Depende de sucursales, usuarios y módulos; se cotiza después del diagnóstico. |
| ¿Tienen clientes que lo usen? | Proponemos un piloto medido como primer paso; no citamos testimonios. |
| ¿Cuánto tarda implementarlo? | Se define en el diagnóstico; el piloto propuesto dura 30 días una vez cargados los datos. |
| ¿Qué pasa con mis datos? | Son del cliente; se acuerdan por contrato el respaldo, el acceso y la confidencialidad. |
`;
fs.writeFileSync(process.argv[2], md);
console.log('guion ok');
