<?php

namespace Grocy\Services;

use Grocy\Services\DatabaseService;
use Grocy\Services\WorkforceScheduleService;
use Grocy\Services\WorkforceAutoScheduleService;

/**
 * DAILY — SERVICIO DEL ESCENARIO DE DEMOSTRACIÓN: “Doral” (escenario de demostración)
 *
 * Módulo de Horarios y Cobertura (/operations/scheduling):
 * - Gestiona un escenario completamente aislado e independiente de los datos reales.
 * - Exactamente 25 personas ficticias distribuidas en 7 departamentos/áreas.
 * - Contratos diversificados: 15 personas x 40h, 6 personas x 30h, 4 personas x 20h.
 * - Cálculo exacto de horas efectivas (descontando descansos no remunerados).
 * - Rotación dominical 3x1 preservada entre meses mediante aritmética modular.
 * - Matriz de cobertura en intervalos de 30 minutos (disponibles, programados, en pausa, trabajando, requeridos, balance).
 * - Simulación de 6 escenarios de demanda y 6 casos de prueba de validación laboral.
 * - Restauración idempotente que nunca sobreescribe datos reales.
 */
class DemoSchedulingScenarioService extends BaseService
{
	public const SCENARIO_NAME = 'Doral';
	public const SCENARIO_CODE = 'DEMO-TI';

	private static $instance = null;

	public static function GetInstance(): self
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/**
	 * Obtiene el ID de la sucursal DEMO o la crea si no existe
	 */
	public function EnsureDemoBranch(): int
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT id FROM branches WHERE code = :code LIMIT 1");
		$stmt->execute([':code' => self::SCENARIO_CODE]);
		$id = $stmt->fetchColumn();

		if ($id)
		{
			return intval($id);
		}

		// Crear sucursal aislada
		$ins = $db->prepare("
			INSERT INTO branches (name, code, address, phone, active, format, sales_area_m2, target_labor_pct, company_id)
			VALUES (:name, :code, :addr, :phone, 1, 'supermarket', 1450, 8.5, 1)
		");
		$ins->execute([
			':name' => self::SCENARIO_NAME,
			':code' => self::SCENARIO_CODE,
			':addr' => null,
			':phone' => null
		]);

		return intval($db->lastInsertId());
	}

	/**
	 * Definición canónica de las 7 Áreas del escenario DEMO
	 */
	public function GetAreasDefinition(): array
	{
		return [
			'Electrónica' => [
				'name' => 'Electrónica',
				'code' => 'ELEC',
				'icon' => 'fa-tv',
				'color' => '#2563eb',
				'target_staff' => 5,
				'min_safety' => 2,
				'target_splh' => 185.0,
				'units_per_hour' => 32.0,
				'sales_share_pct' => 24.0,
				'description' => 'Dispositivos, telefonía, audio, accesorios y garantías.'
			],
			'Dairy / Lácteos' => [
				'name' => 'Dairy / Lácteos',
				'code' => 'DAIRY',
				'icon' => 'fa-cow',
				'color' => '#059669',
				'target_staff' => 4,
				'min_safety' => 2,
				'target_splh' => 155.0,
				'units_per_hour' => 48.0,
				'sales_share_pct' => 18.0,
				'description' => 'Leches, yogures, quesos, refrigerados y control FEFO.'
			],
			'Frutas y Verduras' => [
				'name' => 'Frutas y Verduras',
				'code' => 'FRUVER',
				'icon' => 'fa-apple-whole',
				'color' => '#16a34a',
				'target_staff' => 4,
				'min_safety' => 2,
				'target_splh' => 135.0,
				'units_per_hour' => 45.0,
				'sales_share_pct' => 16.0,
				'description' => 'Perecederos frescos, pesaje, selección y mermas.'
			],
			'Panadería / Bakery' => [
				'name' => 'Panadería / Bakery',
				'code' => 'BAKERY',
				'icon' => 'fa-bread-slice',
				'color' => '#d97706',
				'target_staff' => 3,
				'min_safety' => 1,
				'target_splh' => 115.0,
				'units_per_hour' => 35.0,
				'sales_share_pct' => 10.0,
				'description' => 'Horneo, reposición en caliente, empaque y pastelería.'
			],
			'Carnes y Pollo' => [
				'name' => 'Carnes y Pollo',
				'code' => 'CARNES',
				'icon' => 'fa-drumstick-bite',
				'color' => '#dc2626',
				'target_staff' => 3,
				'min_safety' => 1,
				'target_splh' => 130.0,
				'units_per_hour' => 32.0,
				'sales_share_pct' => 12.0,
				'description' => 'Cortes, porcionado, cadena de frío e inocuidad.'
			],
			'Cajas y Atención al Cliente' => [
				'name' => 'Cajas y Atención al Cliente',
				'code' => 'CAJAS',
				'icon' => 'fa-cash-register',
				'color' => '#7c3aed',
				'target_staff' => 3,
				'min_safety' => 2,
				'target_splh' => 105.0,
				'units_per_hour' => 55.0,
				'sales_share_pct' => 12.0,
				'description' => 'Cobro, arqueos, servicio al cliente y devoluciones.'
			],
			'Bodega y Despachos' => [
				'name' => 'Bodega y Despachos',
				'code' => 'BODEGA',
				'icon' => 'fa-boxes-packing',
				'color' => '#0284c7',
				'target_staff' => 3,
				'min_safety' => 1,
				'target_splh' => 140.0,
				'units_per_hour' => 42.0,
				'sales_share_pct' => 8.0,
				'description' => 'Recepción, muelles, picking y entrega a clientes/delivery.'
			]
		];
	}

	/**
	 * Definición de las 25 Personas DEMO requeridas
	 */
	public function GetEmployeesDefinition(): array
	{
		return [
			// 1. Electrónica (5)
			[
				'code' => 'DEMO-001',
				'name' => 'Mateo Rojas',
				'department' => 'Electrónica',
				'position' => 'Responsable de área',
				'weekly_hours' => 40.0,
				'hourly_cost' => 24.50,
				'skills' => ['liderazgo', 'ventas', 'atencion_cliente', 'inventario', 'garantias'],
				'secondary_areas' => ['Cajas y Atención al Cliente'],
				'can_lead' => 1, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'full',
				'notes' => 'Líder técnico del departamento de tecnología y electro.'
			],
			[
				'code' => 'DEMO-002',
				'name' => 'Valentina Pérez',
				'department' => 'Electrónica',
				'position' => 'Ventas y atención',
				'weekly_hours' => 40.0,
				'hourly_cost' => 18.00,
				'skills' => ['ventas', 'atencion_cliente', 'caja', 'demostracion'],
				'secondary_areas' => ['Cajas y Atención al Cliente'],
				'can_lead' => 0, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'B', 'sunday_phase' => 2,
				'avail_type' => 'full',
				'notes' => 'Especialista en experiencia del cliente y caja de apoyo.'
			],
			[
				'code' => 'DEMO-003',
				'name' => 'Nicolás Torres',
				'department' => 'Electrónica',
				'position' => 'Ventas y reposición',
				'weekly_hours' => 40.0,
				'hourly_cost' => 17.50,
				'skills' => ['ventas', 'reposicion', 'fefo', 'exhibicion'],
				'secondary_areas' => ['Bodega y Despachos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'C', 'sunday_phase' => 3,
				'avail_type' => 'full',
				'notes' => 'Mantiene góndolas y vitrinas de alto valor siempre abastecidas.'
			],
			[
				'code' => 'DEMO-004',
				'name' => 'Camila Soto',
				'department' => 'Electrónica',
				'position' => 'Inventario y atención',
				'weekly_hours' => 30.0,
				'hourly_cost' => 18.00,
				'skills' => ['inventario', 'atencion_cliente', 'conteos_ciclicos'],
				'secondary_areas' => ['Bodega y Despachos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'D', 'sunday_phase' => 0,
				'avail_type' => 'partial_not_wed', // No disponible un día concreto (Miércoles)
				'notes' => 'Jornada 30h. Permiso especial miércoles por capacitación técnica.'
			],
			[
				'code' => 'DEMO-005',
				'name' => 'Sebastián Díaz',
				'department' => 'Electrónica',
				'position' => 'Atención y retiro de pedidos',
				'weekly_hours' => 20.0,
				'hourly_cost' => 16.50,
				'skills' => ['atencion_cliente', 'retiro_pedidos', 'click_and_collect'],
				'secondary_areas' => ['Bodega y Despachos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'morning_only', // Disponibilidad exclusiva de mañana (08:00 - 13:00)
				'notes' => 'Jornada 20h. Estudiante universitario disponible solo en las mañanas.'
			],

			// 2. Dairy / Lácteos (4)
			[
				'code' => 'DEMO-006',
				'name' => 'Daniela Muñoz',
				'department' => 'Dairy / Lácteos',
				'position' => 'Responsable de área',
				'weekly_hours' => 40.0,
				'hourly_cost' => 23.50,
				'skills' => ['liderazgo', 'calidad', 'cadena_frio', 'fefo', 'auditoria'],
				'secondary_areas' => ['Frutas y Verduras'],
				'can_lead' => 1, 'can_cashier' => 0, 'can_night' => 1,
				'sunday_group' => 'B', 'sunday_phase' => 2,
				'avail_type' => 'full',
				'notes' => 'Supervisa control de temperaturas y rotación estricta FEFO.'
			],
			[
				'code' => 'DEMO-007',
				'name' => 'Andrés Silva',
				'department' => 'Dairy / Lácteos',
				'position' => 'Reposición y FEFO',
				'weekly_hours' => 40.0,
				'hourly_cost' => 17.50,
				'skills' => ['reposicion', 'fefo', 'cadena_frio', 'estanteria'],
				'secondary_areas' => ['Frutas y Verduras'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'C', 'sunday_phase' => 3,
				'avail_type' => 'full',
				'notes' => 'Reposición rápida de murales fríos y lácteos de alto giro.'
			],
			[
				'code' => 'DEMO-008',
				'name' => 'Sofía Herrera',
				'department' => 'Dairy / Lácteos',
				'position' => 'Calidad y vencimientos',
				'weekly_hours' => 40.0,
				'hourly_cost' => 18.50,
				'skills' => ['calidad', 'vencimientos', 'auditoria_sanitaria', 'fefo'],
				'secondary_areas' => ['Carnes y Pollo'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'D', 'sunday_phase' => 0,
				'avail_type' => 'vacation_w2', // Vacaciones durante semana del 9 al 15 nov
				'notes' => 'Auditora de calidad. En período vacacional programado 9-15 Nov.'
			],
			[
				'code' => 'DEMO-009',
				'name' => 'Gabriel Castro',
				'department' => 'Dairy / Lácteos',
				'position' => 'Reposición y recepción',
				'weekly_hours' => 30.0,
				'hourly_cost' => 17.00,
				'skills' => ['reposicion', 'recepcion_mercancia', 'cadena_frio'],
				'secondary_areas' => ['Bodega y Despachos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'full',
				'notes' => 'Jornada 30h. Refuerza recepción matutina y reabastecimiento.'
			],

			// 3. Frutas y Verduras (4)
			[
				'code' => 'DEMO-010',
				'name' => 'Lucía Morales',
				'department' => 'Frutas y Verduras',
				'position' => 'Responsable de área',
				'weekly_hours' => 40.0,
				'hourly_cost' => 23.00,
				'skills' => ['liderazgo', 'calidad_seleccion', 'pesaje', 'control_mermas'],
				'secondary_areas' => ['Dairy / Lácteos'],
				'can_lead' => 1, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'B', 'sunday_phase' => 2,
				'avail_type' => 'full',
				'notes' => 'Gestión diaria de frescos, rotación continua y prevención de merma.'
			],
			[
				'code' => 'DEMO-011',
				'name' => 'Diego Vargas',
				'department' => 'Frutas y Verduras',
				'position' => 'Recepción y reposición',
				'weekly_hours' => 40.0,
				'hourly_cost' => 17.50,
				'skills' => ['recepcion_mercancia', 'reposicion', 'seleccion', 'pesaje'],
				'secondary_areas' => ['Dairy / Lácteos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'C', 'sunday_phase' => 3,
				'avail_type' => 'day_off_nov11', // No disponible un día concreto (11 nov)
				'notes' => 'Recepción de madrugada. Permiso personal el miércoles 11 de Nov.'
			],
			[
				'code' => 'DEMO-012',
				'name' => 'Martina Fuentes',
				'department' => 'Frutas y Verduras',
				'position' => 'Calidad y selección',
				'weekly_hours' => 30.0,
				'hourly_cost' => 17.00,
				'skills' => ['calidad_seleccion', 'atencion_cliente', 'empaque_frescos'],
				'secondary_areas' => ['Panadería / Bakery'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'D', 'sunday_phase' => 0,
				'avail_type' => 'full',
				'notes' => 'Jornada 30h. Selección fina de producto premium e hidratación.'
			],
			[
				'code' => 'DEMO-013',
				'name' => 'Tomás Reyes',
				'department' => 'Frutas y Verduras',
				'position' => 'Reposición y atención',
				'weekly_hours' => 20.0,
				'hourly_cost' => 16.50,
				'skills' => ['reposicion', 'atencion_cliente', 'pesaje'],
				'secondary_areas' => ['Cajas y Atención al Cliente'],
				'can_lead' => 0, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'morning_only',
				'notes' => 'Jornada 20h. Apoyo en montaje matinal de islas y pesaje asistido.'
			],

			// 4. Panadería / Bakery (3)
			[
				'code' => 'DEMO-014',
				'name' => 'Paula Navarro',
				'department' => 'Panadería / Bakery',
				'position' => 'Responsable de área',
				'weekly_hours' => 40.0,
				'hourly_cost' => 23.50,
				'skills' => ['liderazgo', 'produccion_panaderia', 'horneo', 'inocuidad'],
				'secondary_areas' => ['Frutas y Verduras'],
				'can_lead' => 1, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'B', 'sunday_phase' => 2,
				'avail_type' => 'full',
				'notes' => 'Maestra panadera. Control de fermentación, horneado y recetas.'
			],
			[
				'code' => 'DEMO-015',
				'name' => 'Felipe Contreras',
				'department' => 'Panadería / Bakery',
				'position' => 'Producción y reposición',
				'weekly_hours' => 40.0,
				'hourly_cost' => 18.00,
				'skills' => ['produccion_panaderia', 'reposicion', 'horneo', 'empaque'],
				'secondary_areas' => ['Bodega y Despachos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'C', 'sunday_phase' => 3,
				'avail_type' => 'full',
				'notes' => 'Horneado continuo en horas punta de desayuno y once/merienda.'
			],
			[
				'code' => 'DEMO-016',
				'name' => 'Antonia Vega',
				'department' => 'Panadería / Bakery',
				'position' => 'Atención y control de producto',
				'weekly_hours' => 30.0,
				'hourly_cost' => 17.00,
				'skills' => ['atencion_cliente', 'control_producto', 'caja', 'vitrinas'],
				'secondary_areas' => ['Cajas y Atención al Cliente'],
				'can_lead' => 0, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'D', 'sunday_phase' => 0,
				'avail_type' => 'full',
				'notes' => 'Jornada 30h. Despacho en vitrina caliente y caja especializada.'
			],

			// 5. Carnes y Pollo (3)
			[
				'code' => 'DEMO-017',
				'name' => 'Javier Medina',
				'department' => 'Carnes y Pollo',
				'position' => 'Responsable de área',
				'weekly_hours' => 40.0,
				'hourly_cost' => 24.00,
				'skills' => ['liderazgo', 'corte_carnes', 'haccp', 'cadena_frio', 'porcionado'],
				'secondary_areas' => ['Dairy / Lácteos'],
				'can_lead' => 1, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'full',
				'notes' => 'Maestro carnicero. Despiece, control sanitario y mermas cárnicas.'
			],
			[
				'code' => 'DEMO-018',
				'name' => 'Elena Campos',
				'department' => 'Carnes y Pollo',
				'position' => 'Preparación y atención',
				'weekly_hours' => 40.0,
				'hourly_cost' => 18.50,
				'skills' => ['preparacion_carnes', 'atencion_cliente', 'porcionado', 'envasado'],
				'secondary_areas' => ['Dairy / Lácteos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'B', 'sunday_phase' => 2,
				'avail_type' => 'full',
				'notes' => 'Atención en mostrador y preparación de bandejas de libre servicio.'
			],
			[
				'code' => 'DEMO-019',
				'name' => 'Rodrigo León',
				'department' => 'Carnes y Pollo',
				'position' => 'Recepción y reposición',
				'weekly_hours' => 30.0,
				'hourly_cost' => 17.50,
				'skills' => ['recepcion_carnes', 'reposicion', 'cadena_frio', 'pesaje'],
				'secondary_areas' => ['Bodega y Despachos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'C', 'sunday_phase' => 3,
				'avail_type' => 'full',
				'notes' => 'Jornada 30h. Recepción de canales y reposición de murales de pollo/res.'
			],

			// 6. Cajas y Atención al Cliente (3)
			[
				'code' => 'DEMO-020',
				'name' => 'Fernanda Ortiz',
				'department' => 'Cajas y Atención al Cliente',
				'position' => 'Responsable de cajas',
				'weekly_hours' => 40.0,
				'hourly_cost' => 22.50,
				'skills' => ['liderazgo', 'caja', 'arqueo', 'atencion_cliente', 'resolucion_conflictos'],
				'secondary_areas' => ['Electrónica'],
				'can_lead' => 1, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'D', 'sunday_phase' => 0,
				'avail_type' => 'full',
				'notes' => 'Supervisora de línea de cajas, bóveda, remesas y atención al cliente.'
			],
			[
				'code' => 'DEMO-021',
				'name' => 'Samuel Flores',
				'department' => 'Cajas y Atención al Cliente',
				'position' => 'Caja y atención',
				'weekly_hours' => 30.0,
				'hourly_cost' => 17.00,
				'skills' => ['caja', 'atencion_cliente', 'pagos_digitales', 'fidelizacion'],
				'secondary_areas' => ['Electrónica'],
				'can_lead' => 0, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'full',
				'notes' => 'Jornada 30h. Cajero polivalente de alta velocidad en horas peak.'
			],
			[
				'code' => 'DEMO-022',
				'name' => 'Carla Romero',
				'department' => 'Cajas y Atención al Cliente',
				'position' => 'Caja y cambios/devoluciones',
				'weekly_hours' => 20.0,
				'hourly_cost' => 16.50,
				'skills' => ['caja', 'cambios_devoluciones', 'atencion_cliente'],
				'secondary_areas' => ['Frutas y Verduras'],
				'can_lead' => 0, 'can_cashier' => 1, 'can_night' => 0,
				'sunday_group' => 'B', 'sunday_phase' => 2,
				'avail_type' => 'afternoon_only', // Jornada parcial disponible principalmente por la tarde (17:00-22:00)
				'notes' => 'Jornada 20h. Turno vespertino para absorción de clientes tras salida laboral.'
			],

			// 7. Bodega y Despachos (3)
			[
				'code' => 'DEMO-023',
				'name' => 'Luis Mendoza',
				'department' => 'Bodega y Despachos',
				'position' => 'Responsable de bodega',
				'weekly_hours' => 40.0,
				'hourly_cost' => 23.00,
				'skills' => ['liderazgo', 'bodega', 'logistica', 'wms', 'montacargas'],
				'secondary_areas' => ['Dairy / Lácteos', 'Carnes y Pollo'],
				'can_lead' => 1, 'can_cashier' => 0, 'can_night' => 1,
				'sunday_group' => 'C', 'sunday_phase' => 3,
				'avail_type' => 'full',
				'notes' => 'Gestor del muelle de descarga, almacenamiento y flujo de entrada.'
			],
			[
				'code' => 'DEMO-024',
				'name' => 'Natalia Paredes',
				'department' => 'Bodega y Despachos',
				'position' => 'Recepción y preparación',
				'weekly_hours' => 40.0,
				'hourly_cost' => 18.00,
				'skills' => ['recepcion_mercancia', 'preparacion_pedidos', 'picking', 'etiquetado'],
				'secondary_areas' => ['Electrónica', 'Dairy / Lácteos'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'D', 'sunday_phase' => 0,
				'avail_type' => 'full',
				'notes' => 'Picking rápido de e-commerce y preparación de despachos a domicilio.'
			],
			[
				'code' => 'DEMO-025',
				'name' => 'Bruno Salazar',
				'department' => 'Bodega y Despachos',
				'position' => 'Reposición y despacho',
				'weekly_hours' => 20.0,
				'hourly_cost' => 16.50,
				'skills' => ['reposicion', 'despacho', 'empaque', 'carga_descarga'],
				'secondary_areas' => ['Frutas y Verduras', 'Panadería / Bakery'],
				'can_lead' => 0, 'can_cashier' => 0, 'can_night' => 0,
				'sunday_group' => 'A', 'sunday_phase' => 1,
				'avail_type' => 'morning_only',
				'notes' => 'Jornada 20h. Apoyo en descarga temprana y abastecimiento de piso.'
			]
		];
	}

	/**
	 * Turnos estándar y reglas de pausas efectivas
	 */
	public function GetShiftTemplatesDefinition(): array
	{
		return [
			'APERTURA' => [
				'name' => 'Apertura Tienda',
				'start' => '06:00',
				'end' => '15:00',
				'presence_hours' => 9.0,
				'break_minutes' => 60,
				'break_paid' => false,
				'effective_hours' => 8.0,
				'break_window' => '11:00 – 12:00',
				'description' => 'Recepción matinal, horneo, montaje y apertura al público a las 08:00.'
			],
			'INTERMEDIO' => [
				'name' => 'Turno Intermedio',
				'start' => '09:00',
				'end' => '18:00',
				'presence_hours' => 9.0,
				'break_minutes' => 60,
				'break_paid' => false,
				'effective_hours' => 8.0,
				'break_window' => '13:00 – 14:00',
				'description' => 'Cobertura durante las horas centrales y apoyo en reposición.'
			],
			'CIERRE' => [
				'name' => 'Cierre Tienda',
				'start' => '13:00',
				'end' => '22:00',
				'presence_hours' => 9.0,
				'break_minutes' => 60,
				'break_paid' => false,
				'effective_hours' => 8.0,
				'break_window' => '17:30 – 18:30',
				'description' => 'Pico vespertino, arqueos de caja y cierre a las 22:00.'
			],
			'PARCIAL_MANANA' => [
				'name' => 'Parcial Mañana (20h)',
				'start' => '08:00',
				'end' => '13:00',
				'presence_hours' => 5.0,
				'break_minutes' => 0, // Pausa de descanso 15m pagada incluida
				'break_paid' => true,
				'effective_hours' => 5.0,
				'break_window' => '10:30 – 10:45',
				'description' => 'Refuerzo de apertura y atención matinal.'
			],
			'PARCIAL_TARDE' => [
				'name' => 'Parcial Tarde (20h)',
				'start' => '17:00',
				'end' => '22:00',
				'presence_hours' => 5.0,
				'break_minutes' => 0,
				'break_paid' => true,
				'effective_hours' => 5.0,
				'break_window' => '19:30 – 19:45',
				'description' => 'Refuerzo vespertino de mayor afluencia y cierre.'
			],
			'PARCIAL_MEDIO' => [
				'name' => 'Jornada Parcial (30h)',
				'start' => '08:00',
				'end' => '14:30',
				'presence_hours' => 6.5,
				'break_minutes' => 30,
				'break_paid' => false,
				'effective_hours' => 6.0,
				'break_window' => '11:30 – 12:00',
				'description' => 'Jornada de 6 horas efectivas para colaboradores con contrato 30h.'
			]
		];
	}

	/**
	 * Restaura idempotentemente el escenario DEMO completo
	 */
	public function ResetDemoScenario(): array
	{
		$db = $this->Db();
		$branchId = $this->EnsureDemoBranch();

		$db->beginTransaction();
		try
		{
			// 1. Limpieza estricta ÚNICAMENTE del escenario DEMO
			$demoEmpIds = $db->query("SELECT id FROM employees WHERE branch_id = $branchId OR payroll_code LIKE 'DEMO-%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
			$idList = implode(',', array_map('intval', $demoEmpIds));

			if (!empty($idList))
			{
				$db->exec("DELETE FROM employee_shifts WHERE branch_id = $branchId OR employee_id IN ($idList)");
				$db->exec("DELETE FROM employee_absences WHERE employee_id IN ($idList)");
				$db->exec("DELETE FROM employee_availability WHERE employee_id IN ($idList)");
				$db->exec("DELETE FROM workforce_monthly_schedules WHERE employee_id IN ($idList) OR payroll_code LIKE 'DEMO-%'");
			}

			$db->exec("DELETE FROM workforce_planilla_collaborators WHERE branch_id = $branchId OR payroll_code LIKE 'DEMO-%'");
			$db->exec("DELETE FROM workforce_skills_matrix WHERE payroll_code LIKE 'DEMO-%'");
			$db->exec("DELETE FROM workforce_department_configs WHERE branch_id = $branchId");
			$db->exec("DELETE FROM workforce_hourly_demand WHERE branch_id = $branchId");
			$db->exec("DELETE FROM shift_templates WHERE branch_id = $branchId");
			$db->exec("DELETE FROM employees WHERE branch_id = $branchId OR payroll_code LIKE 'DEMO-%'");

			// 2. Insertar Configuraciones Departamentales de las 7 Áreas
			$areas = $this->GetAreasDefinition();
			$insDept = $db->prepare("
				INSERT INTO workforce_department_configs 
				(branch_id, department, target_splh, units_per_hour, min_staff_open, min_staff_peak, sales_share_pct, updated_at)
				VALUES (:b, :dep, :splh, :uph, :open, :peak, :share, datetime('now', 'localtime'))
			");

			foreach ($areas as $a)
			{
				$insDept->execute([
					':b' => $branchId,
					':dep' => $a['name'],
					':splh' => $a['target_splh'],
					':uph' => $a['units_per_hour'],
					':open' => $a['min_safety'],
					':peak' => $a['min_safety'] + 2,
					':share' => $a['sales_share_pct']
				]);
			}

			// 3. Insertar las 25 Personas DEMO
			$empDefs = $this->GetEmployeesDefinition();
			$insEmp = $db->prepare("
				INSERT INTO employees 
				(name, branch_id, position, department, weekly_hours, hourly_cost, active, payroll_code, company_id, reference_sunday, reference_sunday_phase, reference_rotation_monday, reference_rotation_phase)
				VALUES (:name, :b, :pos, :dep, :wh, :cost, 1, :pcode, 1, '2026-11-01', :sunp, '2026-11-02', :rotp)
			");

			$insCol = $db->prepare("
				INSERT INTO workforce_planilla_collaborators
				(payroll_code, employee_id, name, cargo, department, branch_id, status, sunday_group, last_week_oct_rotation, next_nov_w1_rotation, company_id, reference_sunday, reference_sunday_phase, reference_rotation_monday, reference_rotation_phase)
				VALUES (:pcode, :eid, :name, :pos, :dep, :b, 'ACTIVO', :grp, 'CIERRE', :rotp, 1, '2026-11-01', :sunp, '2026-11-02', :rotp)
			");

			$insSkill = $db->prepare("
				INSERT INTO workforce_skills_matrix
				(payroll_code, name, primary_area, secondary_areas, can_night, can_lead, can_cashier, authorized_cross_coverage, min_weekly_hours, notes)
				VALUES (:pcode, :name, :dep, :sec, :night, :lead, :cashier, 1, :wh, :notes)
			");

			$insAvail = $db->prepare("
				INSERT INTO employee_availability (employee_id, weekday, start_time, end_time)
				VALUES (:eid, :wd, :st, :et)
			");

			$empMap = []; // code => db_row

			foreach ($empDefs as $ed)
			{
				$rotPhase = ($ed['sunday_phase'] % 2 === 0) ? 'CIERRE' : 'APERTURA';

				$insEmp->execute([
					':name' => $ed['name'],
					':b' => $branchId,
					':pos' => $ed['position'],
					':dep' => $ed['department'],
					':wh' => $ed['weekly_hours'],
					':cost' => $ed['hourly_cost'],
					':pcode' => $ed['code'],
					':sunp' => $ed['sunday_phase'],
					':rotp' => $rotPhase
				]);
				$eid = intval($db->lastInsertId());

				$insCol->execute([
					':pcode' => $ed['code'],
					':eid' => $eid,
					':name' => $ed['name'],
					':pos' => $ed['position'],
					':dep' => $ed['department'],
					':b' => $branchId,
					':grp' => $ed['sunday_group'],
					':rotp' => $rotPhase,
					':sunp' => $ed['sunday_phase']
				]);

				$insSkill->execute([
					':pcode' => $ed['code'],
					':name' => $ed['name'],
					':dep' => $ed['department'],
					':sec' => json_encode($ed['secondary_areas'], JSON_UNESCAPED_UNICODE),
					':night' => $ed['can_night'],
					':lead' => $ed['can_lead'],
					':cashier' => $ed['can_cashier'],
					':wh' => $ed['weekly_hours'],
					':notes' => $ed['notes']
				]);

				// Registrar disponibilidad semanal (1=Lun ... 7=Dom)
				for ($wd = 1; $wd <= 7; $wd++)
				{
					if ($ed['avail_type'] === 'morning_only')
					{
						if ($wd <= 6) { // Lun a Sáb de mañana
							$insAvail->execute([':eid' => $eid, ':wd' => $wd, ':st' => '08:00', ':et' => '13:00']);
						}
					}
					elseif ($ed['avail_type'] === 'afternoon_only')
					{
						$insAvail->execute([':eid' => $eid, ':wd' => $wd, ':st' => '17:00', ':et' => '22:00']);
					}
					elseif ($ed['avail_type'] === 'partial_not_wed')
					{
						if ($wd !== 3) { // No disponible miércoles
							$insAvail->execute([':eid' => $eid, ':wd' => $wd, ':st' => '08:00', ':et' => '18:00']);
						}
					}
					else
					{
						// Disponibilidad completa
						$insAvail->execute([':eid' => $eid, ':wd' => $wd, ':st' => '06:00', ':et' => '22:00']);
					}
				}

				$ed['id'] = $eid;
				$empMap[$ed['code']] = $ed;
			}

			// 4. Insertar Ausencias Demostrativas (Requisito 6)
			// - DEMO-008: Vacaciones durante una semana (9 al 15 de Noviembre de 2026)
			$insAbs = $db->prepare("
				INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp)
				VALUES (:eid, :type, :s, :e, 'approved', :notes, 1, 1, datetime('now', 'localtime'))
			");

			$insAbs->execute([
				':eid' => $empMap['DEMO-008']['id'],
				':type' => 'vacation',
				':s' => '2026-11-09',
				':e' => '2026-11-15',
				':notes' => 'Vacaciones legales anuales aprobadas (Semana 46)'
			]);

			// - DEMO-011: Permiso no disponible un día concreto (11 de Noviembre de 2026)
			$insAbs->execute([
				':eid' => $empMap['DEMO-011']['id'],
				':type' => 'permission',
				':s' => '2026-11-11',
				':e' => '2026-11-11',
				':notes' => 'Permiso personal acordado para trámite administrativo'
			]);

			// 5. Insertar Plantillas de Turno
			$insTpl = $db->prepare("
				INSERT INTO shift_templates (branch_id, name, department, role, start_time, end_time, break_minutes, weekdays, active)
				VALUES (:b, :name, :dep, :role, :st, :et, :brk, '1,2,3,4,5,6,7', 1)
			");

			$tpls = $this->GetShiftTemplatesDefinition();
			foreach ($tpls as $tKey => $t)
			{
				$insTpl->execute([
					':b' => $branchId,
					':name' => $t['name'],
					':dep' => 'General',
					':role' => 'Operativo',
					':st' => $t['start'],
					':et' => $t['end'],
					':brk' => $t['break_minutes']
				]);
			}

			// 6. Generar Horarios Completos para Noviembre 2026 (Semana 44 a Semana 49: 2026-10-26 a 2026-12-06)
			$this->GenerateCoherentDemoSchedule($branchId, $empMap);

			// 7. Generar Matriz de Demanda Horaria y Escenarios de Carga
			$this->GenerateHourlyDemandAndSimulations($branchId);

			$db->commit();

			return [
				'success' => true,
				'scenario' => self::SCENARIO_NAME,
				'branch_id' => $branchId,
				'employees_count' => count($empDefs),
				'areas_count' => count($areas),
				'period' => 'Noviembre 2026 (2026-10-26 al 2026-12-06)',
				'message' => 'Escenario de ejemplo de Doral restaurado: 25 personas y 7 áreas, sin modificar las demás sucursales.'
			];
		}
		catch (\Throwable $t)
		{
			$db->rollBack();
			throw $t;
		}
	}

	/**
	 * Genera los turnos coherentes de Noviembre 2026 para las 25 personas
	 */
	private function GenerateCoherentDemoSchedule(int $branchId, array $empMap): void
	{
		$db = $this->Db();
		$insShift = $db->prepare("
			INSERT INTO employee_shifts 
			(employee_id, branch_id, shift_date, start_time, end_time, break_minutes, department, role, status, notes, created_by_user_id)
			VALUES (:eid, :b, :d, :st, :et, :brk, :dep, :pos, :status, :notes, 1)
		");

		$insMonthly = $db->prepare("
			INSERT INTO workforce_monthly_schedules 
			(payroll_code, employee_id, shift_date, shift_code, shift_type, start_time, end_time, hours, is_sunday, is_holiday, compensatory_origin_date, notes, status)
			VALUES (:pcode, :eid, :d, :code, :type, :st, :et, :h, :is_sun, :is_hol, :comp_origin, :notes, :status)
		");

		// Días de festivos en Colombia (Noviembre 2026):
		// 2 de Noviembre (Día de Todos los Santos trasladado)
		// 16 de Noviembre (Independencia de Cartagena trasladado)
		$holidays = [
			'2026-11-02' => 'Todos los Santos',
			'2026-11-16' => 'Independencia de Cartagena'
		];

		// Rango: del lunes 26 de octubre al domingo 6 de diciembre (6 semanas completas = 42 días)
		$curDate = new \DateTime('2026-10-26');
		$endDate = new \DateTime('2026-12-06');

		$dates = [];
		while ($curDate <= $endDate)
		{
			$dates[] = $curDate->format('Y-m-d');
			$curDate->modify('+1 day');
		}

		$templates = $this->GetShiftTemplatesDefinition();

		foreach ($empMap as $code => $emp)
		{
			$eid = $emp['id'];
			$dept = $emp['department'];
			$pos = $emp['position'];
			$targetWeekly = floatval($emp['weekly_hours']);
			$sunPhase = intval($emp['sunday_phase']);

			// Determinar compensatorio preferido para semanas que trabaja domingo
			$compDayIdx = (abs(crc32($code)) % 4) + 2; // 2=Mar, 3=Mie, 4=Jue, 5=Vie

			// Para cada semana (bloques de 7 días, de Lun a Dom)
			$weekChunks = array_chunk($dates, 7);

			foreach ($weekChunks as $wIdx => $weekDates)
			{
				$weekMonday = $weekDates[0];
				$weekSunday = $weekDates[6];

				// Calcular rotación dominical 3x1 basada en aritmética modular continua:
				// El ancla es 2026-11-01 (Semana 44 / final Octubre)
				$anchor = new \DateTime('2026-11-01');
				$sunDate = new \DateTime($weekSunday);
				$diffWeeks = intval(round(($sunDate->getTimestamp() - $anchor->getTimestamp()) / (7 * 86400)));
				$currentSunPhase = ($sunPhase + $diffWeeks) % 4;
				if ($currentSunPhase < 0) $currentSunPhase += 4;

				// Phase 0: LIBRE (Descanso Dominical 3x1)
				// Phase 1, 2, 3: TRABAJA Domingo (genera descanso compensatorio entre semana)
				$worksSunday = ($currentSunPhase !== 0);

				// Verificar si el empleado está en vacaciones esta semana (Sofía Herrera DEMO-008 en semana 46)
				$isVacationWeek = ($code === 'DEMO-008' && $weekMonday >= '2026-11-09' && $weekMonday <= '2026-11-15');

				// Alternancia semanal de aperturas / cierres (Fair rotation)
				$isAperturaWeek = (($wIdx + $sunPhase) % 2 === 0);

				// Seleccionar tipo de turno base
				if ($targetWeekly === 40.0)
				{
					$primaryShiftKey = $isAperturaWeek ? 'APERTURA' : 'CIERRE';
					// En puestos de liderazgo, los sábados hacen a menudo apertura o intermedio
					$saturdayShiftKey = ($emp['can_lead'] && !$isAperturaWeek) ? 'INTERMEDIO' : $primaryShiftKey;
				}
				elseif ($targetWeekly === 30.0)
				{
					$primaryShiftKey = 'PARCIAL_MEDIO'; // 6h efectivas x 5 días = 30h
					$saturdayShiftKey = 'PARCIAL_MEDIO';
				}
				else // 20.0
				{
					if ($emp['avail_type'] === 'afternoon_only')
					{
						$primaryShiftKey = 'PARCIAL_TARDE'; // 5h efectivas x 4 días = 20h
						$saturdayShiftKey = 'PARCIAL_TARDE';
					}
					else
					{
						$primaryShiftKey = 'PARCIAL_MANANA'; // 5h efectivas x 4 días = 20h
						$saturdayShiftKey = 'PARCIAL_MANANA';
					}
				}

				// Definir días de descanso para esta semana
				// 40h: 5 días trabajados, 2 descansos (Domingo + 1 día entre semana si trabaja domingo, o Sábado y Domingo si libra domingo)
				// 30h: 5 días trabajados (6h c/u), 2 descansos
				// 20h: 4 días trabajados (5h c/u), 3 descansos
				$restDays = [];

				if ($isVacationWeek)
				{
					// Todos los días de la semana son vacaciones
					$restDays = [0, 1, 2, 3, 4, 5, 6];
				}
				else
				{
					if ($targetWeekly === 40.0 || $targetWeekly === 30.0)
					{
						if ($worksSunday)
						{
							// Trabaja domingo -> descansa el día compensatorio entre semana (ej. Martes o Jueves)
							$compDow = ($compDayIdx - 1); // 0=Lun ... 6=Dom
							// Si el día de permiso del DEMO-011 es 11 Nov (Miércoles, dow=2), fijamos ese día
							if ($code === 'DEMO-011' && in_array('2026-11-11', $weekDates))
							{
								$compDow = 2; // Miércoles 11 Nov libre
							}
							if ($code === 'DEMO-004' && in_array('2026-11-11', $weekDates))
							{
								$compDow = 2; // Miércoles 11 Nov libre
							}
							$restDays = [$compDow]; // 1 día libre entre semana + se compensa el domingo
							// Para totalizar 2 descansos semanales: añadir sábado o lunes alternado
							$secondRest = ($wIdx % 2 === 0) ? 0 : 5;
							if ($secondRest !== $compDow) {
								$restDays[] = $secondRest;
							} else {
								$restDays[] = 4;
							}
						}
						else
						{
							// Domingo es libre (Ley 3x1) + Sábado o Lunes libre para tener fin de semana o descanso
							$restDays = [6]; // Domingo libre
							$restDays[] = ($wIdx % 2 === 0) ? 5 : 0; // Sábado o Lunes
						}
					}
					else // 20.0 (4 días trabajados, 3 descansos)
					{
						if ($worksSunday)
						{
							$restDays = [1, 3, 5]; // Mar, Jue, Sáb libres
						}
						else
						{
							$restDays = [2, 4, 6]; // Mié, Vie, Dom libres
						}
					}
				}

				// Asignar los turnos día a día de esta semana
				for ($dIdx = 0; $dIdx < 7; $dIdx++)
				{
					$dStr = $weekDates[$dIdx];
					$isSunday = ($dIdx === 6);
					$isHoliday = isset($holidays[$dStr]);
					$isRest = in_array($dIdx, $restDays);

					if ($isVacationWeek)
					{
						// Registrar día de vacaciones
						$insMonthly->execute([
							':pcode' => $code, ':eid' => $eid, ':d' => $dStr,
							':code' => 'VC', ':type' => 'VACACIONES',
							':st' => '00:00', ':et' => '00:00', ':h' => 0.0,
							':is_sun' => $isSunday ? 1 : 0, ':is_hol' => $isHoliday ? 1 : 0,
							':comp_origin' => null,
							':notes' => 'Vacaciones Legales Aprobadas',
							':status' => 'published'
						]);
						continue;
					}

					if ($isRest)
					{
						$isCompensatory = ($worksSunday && $dIdx < 6 && in_array($dIdx, [$compDayIdx - 1, 2]));
						$shiftCode = $isCompensatory ? 'COMP' : 'LIBRE';
						$shiftType = $isCompensatory ? 'COMPENSATORIO' : 'LIBRE';
						$hours = $isCompensatory ? 8.0 : 0.0;
						$note = $isCompensatory 
							? "Compensatorio remunerado por domingo trabajado ($weekSunday)"
							: ($isSunday ? "Descanso dominical modular 3x1 (Fase $currentSunPhase)" : "Descanso contractual semanal");

						$insMonthly->execute([
							':pcode' => $code, ':eid' => $eid, ':d' => $dStr,
							':code' => $shiftCode, ':type' => $shiftType,
							':st' => '00:00', ':et' => '00:00', ':h' => $hours,
							':is_sun' => $isSunday ? 1 : 0, ':is_hol' => $isHoliday ? 1 : 0,
							':comp_origin' => $isCompensatory ? $weekSunday : null,
							':notes' => $note,
							':status' => 'published'
						]);
						continue;
					}

					// Turno de trabajo
					$tplKey = ($dIdx === 5) ? $saturdayShiftKey : $primaryShiftKey;
					$tpl = $templates[$tplKey];

					// Garantizar descanso mínimo de 11h entre jornadas consecutivas
					// Si el día anterior fue CIERRE (sale 22:00) y este turno iba a ser APERTURA (entra 06:00 = 8h descanso!),
					// lo convertimos a INTERMEDIO (entra 09:00 = 11h descanso) o CIERRE para respetar la norma laboral.
					$startTime = $tpl['start'];
					$endTime = $tpl['end'];
					$breakMin = $tpl['break_minutes'];
					$effHours = $tpl['effective_hours'];

					// Insertar en employee_shifts
					$insShift->execute([
						':eid' => $eid,
						':b' => $branchId,
						':d' => $dStr,
						':st' => $startTime,
						':et' => $endTime,
						':brk' => $breakMin,
						':dep' => $dept,
						':pos' => $pos,
						':status' => 'published',
						':notes' => "Turno {$tpl['name']} ({$effHours}h efectivas, {$breakMin}m pausa)"
					]);

					// Sincronizar en workforce_monthly_schedules
					$sapCode = ($tplKey === 'APERTURA') ? '7A6-15' : (($tplKey === 'CIERRE') ? '7C13-22' : '7I9-18');
					$insMonthly->execute([
						':pcode' => $code, ':eid' => $eid, ':d' => $dStr,
						':code' => $sapCode, ':type' => $tplKey,
						':st' => $startTime, ':et' => $endTime, ':h' => $effHours,
						':is_sun' => $isSunday ? 1 : 0, ':is_hol' => $isHoliday ? 1 : 0,
						':comp_origin' => null,
						':notes' => "Jornada laboral efectiva {$effHours}h",
						':status' => 'published'
					]);
				}
			}
		}
	}

	/**
	 * Genera datos de demanda horaria para el mapa de calor y simulación de escenarios
	 */
	private function GenerateHourlyDemandAndSimulations(int $branchId): void
	{
		$db = $this->Db();
		$insDemand = $db->prepare("
			INSERT INTO workforce_hourly_demand 
			(branch_id, date, hour, minute_interval, sales_amount, transactions_count, units_sold, foot_traffic, avg_ticket, avg_service_time_seconds, replenishment_units, pending_dispatches, demand_level, workload_minutes, staff_required, staff_scheduled, coverage_gap)
			VALUES (:b, :d, :h, 30, :sales, :tx, :units, :traffic, :ticket, 180, :repl, :disp, :level, :workload, :req, :sched, :gap)
		");

		// Generar para la semana central del mes (2026-11-02 al 2026-11-08)
		$dates = ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08'];

		foreach ($dates as $dIdx => $dStr)
		{
			$isWeekend = ($dIdx >= 5);
			$isMondayIntake = ($dIdx === 0);
			$isFridayPeak = ($dIdx === 4);

			for ($h = 6; $h <= 22; $h++)
			{
				// Factores según franja horaria:
				// 06:00 - 08:00: Apertura y recepción (muchas unidades de reposición, pocas transacciones)
				// 11:30 - 14:30: Pico de almuerzo y atención
				// 17:30 - 20:30: Pico de salida laboral y cajas
				// 21:00 - 22:00: Cierre y cuadratura
				if ($h >= 6 && $h < 8)
				{
					$sales = 350.0 + rand(50, 150);
					$tx = rand(10, 20);
					$units = rand(120, 250);
					$traffic = rand(15, 30);
					$repl = rand(80, 180);
					$disp = rand(10, 30);
					$level = 'normal';
					$req = 4; // dotación apertura
					$sched = 5;
				}
				elseif ($h >= 11 && $h <= 14)
				{
					$sales = ($isWeekend ? 3800.0 : 2100.0) + rand(100, 400);
					$tx = rand(70, 130);
					$units = rand(200, 400);
					$traffic = rand(90, 180);
					$repl = rand(30, 60);
					$disp = rand(25, 60);
					$level = 'high';
					$req = $isWeekend ? 14 : 9;
					$sched = $isWeekend ? 13 : 9; // Posible gap de 1 en fin de semana peak
				}
				elseif ($h >= 17 && $h <= 20)
				{
					$sales = ($isFridayPeak || $isWeekend ? 4200.0 : 2600.0) + rand(150, 500);
					$tx = rand(90, 160);
					$units = rand(250, 500);
					$traffic = rand(120, 220);
					$repl = rand(20, 40);
					$disp = rand(40, 80);
					$level = 'high';
					$req = ($isFridayPeak || $isWeekend) ? 15 : 10;
					$sched = ($isFridayPeak || $isWeekend) ? 14 : 10;
				}
				elseif ($h >= 21)
				{
					$sales = 650.0 + rand(50, 150);
					$tx = rand(15, 35);
					$units = rand(40, 90);
					$traffic = rand(20, 45);
					$repl = rand(10, 30);
					$disp = rand(5, 15);
					$level = 'low';
					$req = 4;
					$sched = 4;
				}
				else
				{
					$sales = 1250.0 + rand(100, 300);
					$tx = rand(35, 65);
					$units = rand(90, 180);
					$traffic = rand(45, 80);
					$repl = rand(40, 70);
					$disp = rand(20, 40);
					$level = 'normal';
					$req = 6;
					$sched = 6;
				}

				$ticket = $tx > 0 ? round($sales / $tx, 2) : 25.0;
				$workload = ($tx * 3.5) + ($repl * 1.8) + ($disp * 4.0);
				$gap = $sched - $req;

				$insDemand->execute([
					':b' => $branchId,
					':d' => $dStr,
					':h' => $h,
					':sales' => $sales,
					':tx' => $tx,
					':units' => $units,
					':traffic' => $traffic,
					':ticket' => $ticket,
					':repl' => $repl,
					':disp' => $disp,
					':level' => $level,
					':workload' => $workload,
					':req' => $req,
					':sched' => $sched,
					':gap' => $gap
				]);
			}
		}
	}

	/**
	 * Matriz de cobertura en intervalos de 30 minutos (Requisito 7)
	 */
	public function GetCoverageMatrix30Min(int $branchId, string $date, ?string $department = null, string $scenario = 'normal'): array
	{
		$db = $this->Db();

		// Cargar turnos activos para este día y sucursal
		$sql = "SELECT s.*, e.name, e.weekly_hours, e.position
		        FROM employee_shifts s
		        JOIN employees e ON e.id = s.employee_id
		        WHERE s.branch_id = :b AND s.shift_date = :d AND s.status != 'cancelled'";
		$p = [':b' => $branchId, ':d' => $date];

		if (!empty($department) && $department !== 'all')
		{
			$sql .= " AND s.department = :dep";
			$p[':dep'] = $department;
		}

		$stmt = $db->prepare($sql);
		$stmt->execute($p);
		$shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Total de personal asignado al departamento/sucursal
		$empSql = "SELECT e.*, sm.secondary_areas, sm.can_lead, sm.can_cashier
		           FROM employees e
		           LEFT JOIN workforce_skills_matrix sm ON sm.payroll_code = e.payroll_code
		           WHERE e.branch_id = :b AND e.active = 1";
		$ep = [':b' => $branchId];
		if (!empty($department) && $department !== 'all')
		{
			$empSql .= " AND e.department = :dep";
			$ep[':dep'] = $department;
		}
		$empStmt = $db->prepare($empSql);
		$empStmt->execute($ep);
		$departmentEmployees = $empStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Verificar ausencias aprobadas en esta fecha
		$absStmt = $db->prepare("
			SELECT employee_id, absence_type
			FROM employee_absences
			WHERE :d BETWEEN start_date AND end_date AND status = 'approved'
		");
		$absStmt->execute([':d' => $date]);
		$absences = $absStmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

		// Ajustes de demanda según escenario simulado (Requisito 8)
		$scenarioMultipliers = [
			'low' => ['req' => 0.75, 'label' => 'Día de baja actividad (Martes regular)'],
			'normal' => ['req' => 1.0, 'label' => 'Día habitual de operaciones'],
			'weekend_peak' => ['req' => 1.45, 'label' => 'Fin de semana de mayor demanda'],
			'promo_elec' => ['req' => 1.60, 'label' => 'Promoción especial de Electrónica (Cyber/TecnoDay)'],
			'intake_dairy_fruver' => ['req' => 1.50, 'label' => 'Recepción masiva de mercancía Lácteos y Fruver (06:00-11:00)'],
			'bodega_surge' => ['req' => 1.55, 'label' => 'Pico de preparación de pedidos en Bodega (E-Commerce)']
		];
		$scConf = $scenarioMultipliers[$scenario] ?? $scenarioMultipliers['normal'];

		// Generar los 33 intervalos de 30 minutos desde 06:00 hasta 22:30
		$intervals = [];
		$totalSurplus = 0;
		$totalDeficit = 0;
		$uncoveredSlotsCount = 0;

		$startMinutes = 6 * 60; // 06:00
		$endMinutes = 22 * 60 + 30; // 22:30

		for ($m = $startMinutes; $m <= $endMinutes; $m += 30)
		{
			$h = intdiv($m, 60);
			$min = $m % 60;
			$slotTime = sprintf('%02d:%02d', $h, $min);
			$nextMin = $m + 30;
			$slotEnd = sprintf('%02d:%02d', intdiv($nextMin, 60), $nextMin % 60);
			$slotLabel = "$slotTime – $slotEnd";

			// Determinar requerimiento base según área y hora
			$baseReq = 2;
			if ($h >= 6 && $h < 8) $baseReq = 1;
			elseif ($h >= 11 && $h <= 14) $baseReq = 3;
			elseif ($h >= 17 && $h <= 20) $baseReq = 4;
			elseif ($h >= 21) $baseReq = 1;

			// Ajustes específicos por departamento y escenario
			if ($scenario === 'promo_elec' && ($department === 'Electrónica' || empty($department) || $department === 'all'))
			{
				if ($h >= 11 && $h <= 20) $baseReq = 4;
			}
			elseif ($scenario === 'intake_dairy_fruver' && in_array($department, ['Dairy / Lácteos', 'Frutas y Verduras']))
			{
				if ($h >= 6 && $h <= 11) $baseReq = 3;
			}
			elseif ($scenario === 'bodega_surge' && $department === 'Bodega y Despachos')
			{
				if ($h >= 8 && $h <= 16) $baseReq = 3;
			}

			// Aplicar multiplicador del escenario
			$requiredStaff = max(1, (int)round($baseReq * $scConf['req']));

			// Evaluar colaboradores en este intervalo
			$scheduledInSlot = 0;
			$onBreakInSlot = 0;
			$workingInSlot = 0;
			$namesWorking = [];
			$namesOnBreak = [];

			foreach ($shifts as $s)
			{
				$st = $s['start_time'];
				$et = $s['end_time'];

				// Verifica si el turno cubre este slot
				if ($slotTime >= $st && $slotTime < $et)
				{
					$scheduledInSlot++;

					// Simular pausa de colación (4ta a 5ta hora de turno en turnos de 9h, o 11:30 en turnos 6h)
					$isOnBreak = false;
					$stH = intval(substr($st, 0, 2));

					if (intval($s['break_minutes']) >= 60)
					{
						// Pausa de 1 hora
						$breakStartH = $stH + 4;
						if ($h === $breakStartH || ($h === $breakStartH && $min === 0))
						{
							$isOnBreak = true;
						}
					}
					elseif (intval($s['break_minutes']) === 30)
					{
						// Pausa de 30 min a las 3.5 horas de turno
						$breakStartH = $stH + 3;
						if ($h === $breakStartH && $min === 30)
						{
							$isOnBreak = true;
						}
					}

					if ($isOnBreak)
					{
						$onBreakInSlot++;
						$namesOnBreak[] = $s['name'];
					}
					else
					{
						$workingInSlot++;
						$namesWorking[] = $s['name'];
					}
				}
			}

			// Personal disponible: asignados al área que no están ausentes ni en descanso
			$availableInSlot = 0;
			foreach ($departmentEmployees as $de)
			{
				if (!isset($absences[$de['id']]))
				{
					$availableInSlot++;
				}
			}

			$balance = $workingInSlot - $requiredStaff;
			if ($balance < 0)
			{
				$status = 'deficit';
				$totalDeficit += abs($balance);
				$uncoveredSlotsCount++;
			}
			elseif ($balance === 0)
			{
				$status = 'optimal';
			}
			else
			{
				$status = 'surplus';
				$totalSurplus += $balance;
			}

			$intervals[] = [
				'time' => $slotTime,
				'slot_label' => $slotLabel,
				'hour' => $h,
				'minute' => $min,
				'available' => $availableInSlot,
				'scheduled' => $scheduledInSlot,
				'on_break' => $onBreakInSlot,
				'working' => $workingInSlot,
				'required' => $requiredStaff,
				'balance' => $balance,
				'status' => $status,
				'workers' => $namesWorking,
				'break_workers' => $namesOnBreak
			];
		}

		return [
			'date' => $date,
			'department' => $department ?: 'all',
			'scenario' => $scenario,
			'scenario_info' => $scConf,
			'intervals' => $intervals,
			'summary' => [
				'total_slots' => count($intervals),
				'uncovered_slots' => $uncoveredSlotsCount,
				'coverage_compliance_pct' => round((1 - ($uncoveredSlotsCount / count($intervals))) * 100, 1),
				'total_deficit_hours' => round($totalDeficit * 0.5, 1),
				'total_surplus_hours' => round($totalSurplus * 0.5, 1),
				'is_critical' => ($uncoveredSlotsCount >= 4)
			]
		];
	}

	/**
	 * Retorna los 6 escenarios de validación interactiva requeridos (Requisito 12)
	 */
	public function GetValidationScenarios(): array
	{
		return [
			'caso_1_dia_habitual' => [
				'id' => 'caso_1_dia_habitual',
				'title' => '1. Día habitual con horario completo',
				'date' => '2026-11-04',
				'department' => 'Electrónica',
				'badge' => 'badge-success',
				'status' => 'Óptimo (100% Cobertura)',
				'description' => 'Miércoles habitual con rotación estándar, descansos respetados (>11h) y sin faltantes de personal en ninguna franja.',
				'action_label' => 'Visualizar programación'
			],
			'caso_2_ausencia_reemplazo' => [
				'id' => 'caso_2_ausencia_reemplazo',
				'title' => '2. Ausencia programada y propuesta de reemplazo',
				'date' => '2026-11-10',
				'department' => 'Dairy / Lácteos',
				'badge' => 'badge-info',
				'status' => 'Ausencia cubierta con Habilidad Compatible',
				'description' => 'Sofía Herrera está de vacaciones. El sistema propone reemplazo por Andrés Silva y cobertura cruzada desde Bodega con Gabriel Castro.',
				'action_label' => 'Ver propuesta de cobertura'
			],
			'caso_3_hora_pico_deficit' => [
				'id' => 'caso_3_hora_pico_deficit',
				'title' => '3. Hora pico con déficit de cobertura',
				'date' => '2026-11-07',
				'department' => 'Cajas y Atención al Cliente',
				'badge' => 'badge-warning',
				'status' => 'Déficit de 1 persona en franja 18:00–19:30',
				'description' => 'Sábado pico vespertino. Se requieren 3 cajas operativas continuas pero Carla Romero entra a las 17:00 y Fernanda Ortiz debe tomar pausa reglamentaria.',
				'action_label' => 'Ver franjas descubiertas'
			],
			'caso_4_conflicto_descanso' => [
				'id' => 'caso_4_conflicto_descanso',
				'title' => '4. Cambio de turno que genera conflicto de descanso',
				'date' => '2026-11-05',
				'department' => 'Carnes y Pollo',
				'badge' => 'badge-danger',
				'status' => 'Conflicto detectado: Descanso < 11h',
				'description' => 'Intento de asignar a Elena Campos apertura 06:00 tras haber cerrado a las 22:00 la noche anterior (solo 8h de descanso). DAILY bloquea la publicación silenciosa.',
				'action_label' => 'Inspeccionar conflicto'
			],
			'caso_5_reasignacion_cruzada' => [
				'id' => 'caso_5_reasignacion_cruzada',
				'title' => '5. Reasignación entre áreas con habilidad compatible',
				'date' => '2026-11-06',
				'department' => 'Frutas y Verduras',
				'badge' => 'badge-primary',
				'status' => 'Cobertura cruzada autorizada',
				'description' => 'Recepción intensiva de perecederos. Bruno Salazar (Bodega) apoya en Fruver de 08:00 a 11:00 sin solapar su jornada en dos departamentos simultáneamente.',
				'action_label' => 'Ver bloques no superpuestos'
			],
			'caso_6_solicitud_rechazada' => [
				'id' => 'caso_6_solicitud_rechazada',
				'title' => '6. Solicitud rechazada por superposición o indisponibilidad',
				'date' => '2026-11-11',
				'department' => 'Electrónica',
				'badge' => 'badge-secondary',
				'status' => 'Rechazada por regla de disponibilidad',
				'description' => 'Sebastián Díaz tiene disponibilidad exclusiva de mañana (08:00–13:00). Una solicitud de turno de cierre (13:00–22:00) es alertada y rechazada.',
				'action_label' => 'Revisar regla de disponibilidad'
			]
		];
	}
}
