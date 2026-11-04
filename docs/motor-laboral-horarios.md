# Motor laboral de Horarios y Cobertura

Diagnóstico, reglas, algoritmo y decisiones pendientes. Fecha de corte: 2026-10-30. Sucursal analizada: Hallandale (23 personas).

## 1. Diagnóstico del módulo anterior

Ya existía un generador mensual con borrador, aprobación, reapertura, exportación Excel y validación (`WorkforceScheduleEngineService`). La historia real va del 31/8 al 1/11 (`source = 'planilla'`). Lo que faltaba o estaba mal:

- El ciclo dominical usaba dos lógicas distintas (racha y calendario). Al volver de una ausencia podía dar un libre fuera de fase.
- Solo se revisaban como pendientes los dos últimos domingos, y nunca a quien salía a vacaciones antes de la fecha límite. Julio Jara y Marisol Méndez trabajaron el 11/10, salieron el 15/10 y su compensatorio no aparecía.
- `can_night` se deducía de quien hubiera trasnochado, por eso Bodega Digital y Despachos entraban al grupo de nocturnos.
- El día del compensatorio no rotaba por persona: salía del puntaje de cobertura.
- El 1/11 real hacía creer que noviembre estaba confirmado, y diciembre no pedía aprobarlo.
- No existían fechas especiales, restricciones individuales, solicitudes unificadas, registro de excepciones ni cálculo de horas legales.

## 2. Matriz de reglas

| Regla | Antes | Ahora | Clase |
|---|---|---|---|
| 3×1 individual y continuo entre meses | Parcial | Implementada (`WorkforceLaborRulesService`) | Bloqueante |
| Domingo como trabajado, libre, festivo, vacaciones o incapacidad | Faltaba | Vista Domingos y hoja Excel | — |
| Excepción con motivo y recálculo | Faltaba | `workforce_sunday_exceptions` | — |
| Compensatorio lunes a viernes sin festivos | Implementada | Se conserva | Bloqueante |
| Rotación con secuencia por persona | Faltaba | Implementada; Nicolás: L, M, J | Optimizable |
| Estados pendiente, programado, disfrutado, reprogramado | Parcial | Implementada | — |
| Un domingo, un compensatorio | Parcial | Índice único y validación | Bloqueante |
| Alerta de compensatorio sin fecha | Faltaba | `comp_overdue`, requiere gerencia | Obligatoria |
| Sin compensatorio el 27/11 y sin libre el 29/11 | Faltaba | Fechas especiales configurables | Obligatoria |
| Vacaciones con compensatorio pendiente | Fallaba | Implementada | Bloqueante |
| Nocturno solo Ventas y Operaciones | Contradicha | Implementada | Bloqueante |
| Horas nocturnas legales y recargos | Faltaba | Implementada | — |
| Planta, restricciones y solicitudes | Faltaba | Implementadas | Bloqueante / — |
| Conflicto con causa, alternativas y gerencia | Parcial | Implementado | — |
| Vista anual, compensatorios, nocturnos, vacaciones | Faltaba | Implementadas en 3. Vista mensual | — |

## 3. Decisiones de diseño

**Vacaciones y ciclo 3×1.** El ciclo corre por calendario desde el último domingo libre. Una ausencia no cuenta como domingo trabajado ni reinicia el ciclo. Si el domingo libre cae dentro de la ausencia, se consume allí. Esto evita más de tres domingos seguidos y mantiene repartidos los grupos. Aplica igual a incapacidad, permiso y restricción total.

**Regla del 29/11.** Afecta la secuencia 3×1, no el descanso legal: esos domingos generan compensatorio. A quien libraba el 29 se le adelanta el libre al domingo anterior más cercano (hasta tres semanas) que deje al área con al menos la mitad de su gente. El ciclo sigue desde esa fecha. Si ninguno sirve, el libre no se mueve, aparece el cuarto domingo como bloqueante y se pide decisión.

**Compensatorios.** Los domingos sin compensatorio se calculan desde la programación: primero por domingo de origen y luego en orden, porque la planilla no trae el origen. Los de meses anteriores van en la primera fecha válida. Si una ausencia desplaza un compensatorio, vuelve a pendiente.

**Domingo ocasional o habitual.** Hasta dos domingos en el mes es ocasional y tres o más, habitual. El motor da compensatorio en ambos casos, como la planilla, y guarda la clasificación. En el ocasional la persona puede preferir el pago: esa elección aún no se registra.

**Horas legales.** Noche desde las 19:00, recargo nocturno 35 %, dominical y festivo 90 % (100 % desde 1/7/2027). Las horas después de medianoche pertenecen al día siguiente. Como la planilla no dice a qué hora es el almuerzo, se descuenta en proporción. No se calcula valor en pesos.

## 4. Algoritmo mensual

1. Estado real por persona: ciclo, compensatorios sin disfrutar, rotación, noches y horas.
2. Lo ya transcurrido y la planilla quedan fijos.
3. Ausencias y restricción total; domingos según el ciclo.
4. Fechas sin libre dominical: se adelanta el libre y se registra la excepción.
5. Disponibilidad parcial, noches, compensatorios, tope de horas, descanso y cobertura.
6. Validación completa. Solo la aprobación publica; al hacerlo guarda banco y excepciones. El mes siguiente parte de lo aprobado.

Las solicitudes pasan de pendiente a aprobada o rechazada, y de aprobada a aplicada. Si aplicar una solicitud crea una regla bloqueante nueva, se revierte y queda aprobada con su conflicto.

## 5. Conflictos y datos pendientes

**Requiere concepto jurídico**

- El ciclo real 35/49/42/42 promedia 42 h en cuatro semanas. El art. 165 del CST habla de un período de hasta tres semanas: 49 + 42 + 42 da 44,3 h. El motor conserva cuatro semanas porque es el patrón real y lo muestra como punto por confirmar.
- La semana de 49 h tiene siete días trabajados. Si el pico no es válido, el ciclo necesita un libre adicional y hay que reducir la dotación disponible.
- La 3×1 se trata como bloqueante por decisión operativa. Lo que se encontró en la norma es el compensatorio, no un límite de tres domingos.

**Datos que RR. HH. debe completar (vista Planta)**

- Tipo de contrato de las 23 personas. Disponibilidad de quienes no la tienen.
- Secuencia de compensatorio de 22 personas; mientras tanto se usa el día menos usado.
- Fecha de regreso de Leidy Amaya y códigos definitivos de TEMP2 y TEMP3.
- `can_night = 1` de Jair Melo, Linda Medina y Diana Lozano: el departamento ya los excluye, pero el dato sigue contradiciendo la regla.

**Cobertura.** Las áreas de dos o tres personas no siempre pueden tener apertura y cierre los siete días. El motor lo reporta y no inventa personal.

## 6. Referencias

- [Recargo dominical al 90 %](https://www.bloomberglinea.com/latinoamerica/colombia/pago-de-domingos-y-festivos-sube-al-90-desde-hoy-1-de-julio-de-2026-en-colombia/)
- [Jornada de 42 horas desde el 15 de julio](https://www.noticiascaracol.com/economia/reduccion-de-la-jornada-laboral-en-colombia-asi-sera-el-nuevo-limite-de-horas-desde-el-15-de-julio-so35)
- [Art. 165 CST, trabajo por turnos](https://leyonline.co/laws/codigo-sustantivo-del-trabajo/articulo-165-trabajo-por-turnos)
- [Descanso compensatorio por trabajo dominical](https://www.gerencie.com/descanso-compensatorio-remunerado-por-trabajo-dominical.html)

Estas fuentes son secundarias; el texto vigente debe confirmarlo jurídica.
