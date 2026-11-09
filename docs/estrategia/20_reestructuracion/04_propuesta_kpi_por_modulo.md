# 20.04 · Propuesta de indicadores por módulo (para revisión)

Fase 7. Ventas e Inventario (agotados) ya se implementaron. Para los demás módulos esta es una **propuesta**: no se cambió su tablero hasta que se apruebe, porque varios requisitos aún no están definidos.

Regla común: cada indicador muestra nombre, valor, unidad, período, alcance (sucursal/departamento), definición, fuente y actualización (componente `views/components/daily_kpi.blade.php`). Valor sin registros = «Sin información», nunca cero ni estimado.

| Módulo | Objetivo operativo | 3–5 indicadores principales (arriba) | Pasan a detalle | Duplicaciones a quitar |
|---|---|---|---|---|
| Ventas y Rentabilidad ✅ | Vender más y con margen | Ventas netas y evolución · fin de semana vs. anterior · ventas por hora trabajada · Top 5 · tráfico | Meta del período, participación por departamento, sucursales, oportunidades (en «Análisis complementario») | Los 6 KPI anteriores repetían ventas/margen del bloque 1 |
| Inventario y Almacenes | Producto disponible y stock confiable | Productos agotados · disponibilidad del surtido · bajo mínimo · valor de inventario · diferencias de conteo pendientes | ABC-XYZ, stock fantasma, lotes estancados | «Valor del inventario» aparece en Stock, Control de inventario y Reportes con criterios distintos |
| Despachos y Devoluciones | Entregar completo y a tiempo | Pedidos a tiempo (28 días) · pedidos atrasados · exactitud de preparación (con denominador) · devoluciones ($ y n.º) | Faltantes por 1.000 u., transportistas | Pedidos aparecían en Ventas y en Despachos |
| Mermas y Prevención | Reducir pérdidas | Merma aprobada ($) · merma/ventas (%) · pendientes de aprobación · casos abiertos · recuperado de proveedores | Causales, perecederos, CCTV, rondas | 15 entradas de menú para 2 tableros (resuelto) |
| Calidad y Vencimientos | Solo producto apto | Lotes vencidos con stock · por vencer (≤ 5 días, $) · cuarentena · excursiones de frío | Retiros, trazabilidad | «Riesgo crítico $342.783» mezcla vencidos y por vencer: separar |
| Operaciones y Personal | Gente en la hora necesaria | Horas programadas vs. presentes · cobertura mínima · asistencia · costo de personal / ventas | Equidad, festivos, picos | «Empleados programados 23 de 23» vs. «112 personas» en Ventas: aclarar alcance (sucursal vs. red) |
| Administración y Sistema | Configurar y proteger | Usuarios activos · importaciones con error · eventos críticos de auditoría | Economía SaaS (solo administradores) | — |
| Planeación y Compras | Reponer sin sobrestock | Compras atrasadas · OTIF · cobertura en días · sugerencias MRP pendientes | Simulador | — |

## Decisiones que se necesitan

1. ¿«Departamento» es `products.department` (agrupado) y «Categoría» es `product_groups`? Es lo implementado. Alternativa: crear una tabla `departments` por empresa y relacionar `product_groups.department_id`.
2. Tabla de equivalencia entre áreas de personal y departamentos de venta, para calcular productividad por departamento (hoy no aplica y se indica).
3. Fuente de tráfico de clientes (sensor de puerta, cámara de conteo o integración del centro comercial).
4. Meta de margen y de merma por departamento (existe `daily_department_budgets`; confirmar si es la fuente oficial).
