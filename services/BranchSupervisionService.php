<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * DAILY — Centro de Gestión Multisucursal (Sucursales)
 * 
 * Supervisión integral de la red de tiendas:
 * - Mapa interactivo con marcadores y vista de tarjetas
 * - Ranking BHI (Branch Health Index 0-100 con ponderaciones configurables)
 * - Matriz de supervisión 10 tiendas × 7 procesos clave
 * - Ficha 360° reactiva con enlaces directos prefiltrados
 * - Centro de incidencias operativas y asignación
 * - Detección de patrones multisucursal (hipótesis, correlación, causa confirmada)
 * - Centro de intervenciones regionales y validación de resultados
 * - Seguimiento de responsables con métricas de cumplimiento
 * - Comparador normalizado de 2 a 4 sucursales
 * - Bitácora de visitas y auditorías con registro directo
 */
class BranchSupervisionService extends BaseService
{
	private static ?self $instance = null;

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

	public function GetBranchSupervisionDashboard(array $filters = []): array
	{
		$db = $this->Db();

		// Ponderaciones estándar del BHI (Branch Health Index)
		$bhiWeights = [
			'operational_compliance' => 0.25, // 25% Cumplimiento operativo
			'commercial_availability' => 0.20, // 20% Disponibilidad comercial
			'incident_control'        => 0.20, // 20% Control de incidencias
			'staff_coverage'          => 0.15, // 15% Cobertura de personal
			'process_compliance'      => 0.10, // 10% Cumplimiento de procesos
			'action_resolution'       => 0.10  // 10% Resolución de acciones
		];

		// 1. Catálogo maestro de las 10 sucursales
		$branches = [
			[
				'id' => 1,
				'name' => 'Hallandale Beach',
				'code_alias' => 'Sucursal 01',
				'display_name' => 'Hallandale Beach',
				'code' => 'DIR-HB-01',
				'city' => 'Hallandale Beach',
				'region' => 'Costa Este',
				'manager' => 'Gerente 02',
				'phone' => '+1 (305) 555-0101',
				'address' => '1000 E Hallandale Beach Blvd',
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_badge_class' => 'badge-atencion',
				'bhi' => 63,
				'bhi_trend' => -6,
				'bhi_trend_text' => '▼ 6',
				'bhi_trend_class' => 'trend-down',
				'bhi_grade' => 'Atención',
				'active_incidents' => 5,
				'critical_incidents' => 1,
				'pending_actions' => 4,
				'overdue_actions' => 2,
				'critical_processes' => 0,
				'last_review' => '28/10',
				'next_review' => '11/11',
				'coverage_pct' => 98.5,
				'size_volume' => 'Grande',
				'tickets_month' => 18450,
				'map_coords' => ['x' => 48, 'y' => 28],
				'requires_intervention' => true,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 65,
					'availability' => 68,
					'incidents' => 58,
					'staff' => 62,
					'process' => 64,
					'resolution' => 60
				]
			],
			[
				'id' => 4,
				'name' => 'Sucursal 04',
				'code_alias' => 'Sucursal 04',
				'display_name' => 'Sucursal 04',
				'code' => 'DIR-AV-04',
				'city' => 'Aventura',
				'region' => 'Norte',
				'manager' => 'Gerente 04',
				'phone' => '+1 (305) 555-0104',
				'address' => '19501 Biscayne Blvd, Aventura',
				'status' => 'operativa',
				'status_label' => 'Excelente',
				'status_badge_class' => 'badge-operativa',
				'bhi' => 86,
				'bhi_trend' => 1,
				'bhi_trend_text' => '▲ 1',
				'bhi_trend_class' => 'trend-up',
				'bhi_grade' => 'Excelente',
				'active_incidents' => 1,
				'critical_incidents' => 0,
				'pending_actions' => 1,
				'overdue_actions' => 0,
				'critical_processes' => 0,
				'last_review' => '15/10',
				'next_review' => '20/11',
				'coverage_pct' => 99.0,
				'size_volume' => 'Mediana',
				'tickets_month' => 14200,
				'map_coords' => ['x' => 42, 'y' => 35],
				'requires_intervention' => false,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 88,
					'availability' => 90,
					'incidents' => 85,
					'staff' => 84,
					'process' => 86,
					'resolution' => 82
				]
			],
			[
				'id' => 6,
				'name' => 'Sucursal 06',
				'code_alias' => 'Sucursal 06',
				'display_name' => 'Sucursal 06',
				'code' => 'DIR-MC-06',
				'city' => 'Miami Centro',
				'region' => 'Centro',
				'manager' => 'Supervisor 06',
				'phone' => '+1 (305) 555-0106',
				'address' => '200 S Biscayne Blvd, Miami',
				'status' => 'operativa',
				'status_label' => 'Estable',
				'status_badge_class' => 'badge-operativa',
				'bhi' => 79,
				'bhi_trend' => 0,
				'bhi_trend_text' => '—',
				'bhi_trend_class' => 'trend-neutral',
				'bhi_grade' => 'Estable',
				'active_incidents' => 2,
				'critical_incidents' => 0,
				'pending_actions' => 2,
				'overdue_actions' => 1,
				'critical_processes' => 0,
				'last_review' => '25/10',
				'next_review' => '28/11',
				'coverage_pct' => 95.0,
				'size_volume' => 'Grande',
				'tickets_month' => 19100,
				'map_coords' => ['x' => 45, 'y' => 60],
				'requires_intervention' => false,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 80,
					'availability' => 82,
					'incidents' => 78,
					'staff' => 77,
					'process' => 79,
					'resolution' => 76
				]
			],
			[
				'id' => 9,
				'name' => 'Sucursal 09',
				'code_alias' => 'Sucursal 09',
				'display_name' => 'Sucursal 09',
				'code' => 'DIR-DO-09',
				'city' => 'Doral',
				'region' => 'Oeste',
				'manager' => 'Gerente 09',
				'phone' => '+1 (305) 555-0109',
				'address' => '8700 NW 36th St, Doral',
				'status' => 'operativa',
				'status_label' => 'Estable',
				'status_badge_class' => 'badge-operativa',
				'bhi' => 74,
				'bhi_trend' => 3,
				'bhi_trend_text' => '▲ 3',
				'bhi_trend_class' => 'trend-up',
				'bhi_grade' => 'Estable',
				'active_incidents' => 2,
				'critical_incidents' => 0,
				'pending_actions' => 2,
				'overdue_actions' => 1,
				'critical_processes' => 0,
				'last_review' => '29/10',
				'next_review' => '25/11',
				'coverage_pct' => 98.0,
				'size_volume' => 'Grande',
				'tickets_month' => 17600,
				'map_coords' => ['x' => 25, 'y' => 52],
				'requires_intervention' => false,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 75,
					'availability' => 78,
					'incidents' => 72,
					'staff' => 74,
					'process' => 73,
					'resolution' => 71
				]
			],
			[
				'id' => 3,
				'name' => 'Sunny Isles',
				'code_alias' => 'Sunny Isles',
				'display_name' => 'Sunny Isles',
				'code' => 'DIR-SI-03',
				'city' => 'Sunny Isles Beach',
				'region' => 'Costa Este',
				'manager' => 'Supervisor 05',
				'phone' => '+1 (305) 555-0103',
				'address' => '17070 Collins Ave, Sunny Isles',
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_badge_class' => 'badge-atencion',
				'bhi' => 68,
				'bhi_trend' => -4,
				'bhi_trend_text' => '▼ 4',
				'bhi_trend_class' => 'trend-down',
				'bhi_grade' => 'Atención',
				'active_incidents' => 4,
				'critical_incidents' => 1,
				'pending_actions' => 3,
				'overdue_actions' => 2,
				'critical_processes' => 1,
				'last_review' => '21/10',
				'next_review' => '15/11',
				'coverage_pct' => 94.2,
				'size_volume' => 'Mediana',
				'tickets_month' => 13500,
				'map_coords' => ['x' => 55, 'y' => 38],
				'requires_intervention' => true,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 70,
					'availability' => 72,
					'incidents' => 64,
					'staff' => 66,
					'process' => 68,
					'resolution' => 65
				]
			],
			[
				'id' => 5,
				'name' => 'Sucursal 05',
				'code_alias' => 'Sucursal 05',
				'display_name' => 'Sucursal 05',
				'code' => 'DIR-NM-05',
				'city' => 'North Miami',
				'region' => 'Norte',
				'manager' => 'Gerente 05',
				'phone' => '+1 (305) 555-0105',
				'address' => '12500 Biscayne Blvd, North Miami',
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_badge_class' => 'badge-atencion',
				'bhi' => 59,
				'bhi_trend' => 0,
				'bhi_trend_text' => '—',
				'bhi_trend_class' => 'trend-neutral',
				'bhi_grade' => 'Atención',
				'active_incidents' => 3,
				'critical_incidents' => 1,
				'pending_actions' => 2,
				'overdue_actions' => 1,
				'critical_processes' => 0,
				'last_review' => '19/10',
				'next_review' => '22/11',
				'coverage_pct' => 91.5,
				'size_volume' => 'Mediana',
				'tickets_month' => 12800,
				'map_coords' => ['x' => 38, 'y' => 45],
				'requires_intervention' => true,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 60,
					'availability' => 62,
					'incidents' => 56,
					'staff' => 58,
					'process' => 59,
					'resolution' => 57
				]
			],
			[
				'id' => 2,
				'name' => 'Miami Beach',
				'code_alias' => 'Miami Beach',
				'display_name' => 'Miami Beach',
				'code' => 'DIR-MB-02',
				'city' => 'Miami Beach',
				'region' => 'Costa Este',
				'manager' => 'Gerente 03',
				'phone' => '+1 (305) 555-0102',
				'address' => '1100 Lincoln Rd, Miami Beach',
				'status' => 'critico',
				'status_label' => 'Crítico',
				'status_badge_class' => 'badge-critico',
				'bhi' => 47,
				'bhi_trend' => -9,
				'bhi_trend_text' => '▼ 9',
				'bhi_trend_class' => 'trend-down',
				'bhi_grade' => 'Crítico',
				'active_incidents' => 8,
				'critical_incidents' => 3,
				'pending_actions' => 7,
				'overdue_actions' => 4,
				'critical_processes' => 3,
				'last_review' => '04/11',
				'next_review' => '18/11',
				'coverage_pct' => 96.0,
				'size_volume' => 'Grande',
				'tickets_month' => 21200,
				'map_coords' => ['x' => 58, 'y' => 66],
				'requires_intervention' => true,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 45,
					'availability' => 49,
					'incidents' => 44,
					'staff' => 48,
					'process' => 50,
					'resolution' => 44
				]
			],
			[
				'id' => 7,
				'name' => 'Sucursal 07',
				'code_alias' => 'Sucursal 07',
				'display_name' => 'Sucursal 07',
				'code' => 'DIR-HW-07',
				'city' => 'Hollywood',
				'region' => 'Norte',
				'manager' => 'Supervisor 07',
				'phone' => '+1 (305) 555-0107',
				'address' => '3300 Hollywood Blvd, Hollywood',
				'status' => 'critico',
				'status_label' => 'Crítico',
				'status_badge_class' => 'badge-critico',
				'bhi' => 41,
				'bhi_trend' => -5,
				'bhi_trend_text' => '▼ 5',
				'bhi_trend_class' => 'trend-down',
				'bhi_grade' => 'Crítico',
				'active_incidents' => 6,
				'critical_incidents' => 2,
				'pending_actions' => 5,
				'overdue_actions' => 3,
				'critical_processes' => 2,
				'last_review' => '01/11',
				'next_review' => '14/11',
				'coverage_pct' => 89.0,
				'size_volume' => 'Mediana',
				'tickets_month' => 11900,
				'map_coords' => ['x' => 20, 'y' => 68],
				'requires_intervention' => true,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 40,
					'availability' => 43,
					'incidents' => 38,
					'staff' => 42,
					'process' => 44,
					'resolution' => 39
				]
			],
			[
				'id' => 8,
				'name' => 'Sucursal 08',
				'code_alias' => 'Sucursal 08',
				'display_name' => 'Sucursal 08',
				'code' => 'DIR-FL-08',
				'city' => 'Fort Lauderdale',
				'region' => 'Norte',
				'manager' => 'Gerente 08',
				'phone' => '+1 (305) 555-0108',
				'address' => '2400 E Las Olas Blvd, Fort Lauderdale',
				'status' => 'operativa',
				'status_label' => 'Estable',
				'status_badge_class' => 'badge-operativa',
				'bhi' => 76,
				'bhi_trend' => 0,
				'bhi_trend_text' => '—',
				'bhi_trend_class' => 'trend-neutral',
				'bhi_grade' => 'Estable',
				'active_incidents' => 1,
				'critical_incidents' => 0,
				'pending_actions' => 1,
				'overdue_actions' => 0,
				'critical_processes' => 0,
				'last_review' => '12/10',
				'next_review' => '30/11',
				'coverage_pct' => 97.0,
				'size_volume' => 'Mediana',
				'tickets_month' => 13100,
				'map_coords' => ['x' => 16, 'y' => 32],
				'requires_intervention' => false,
				'is_provisional' => false,
				'bhi_components' => [
					'operational' => 78,
					'availability' => 80,
					'incidents' => 74,
					'staff' => 75,
					'process' => 76,
					'resolution' => 73
				]
			],
			[
				'id' => 10,
				'name' => 'Sucursal 10',
				'code_alias' => 'Sucursal 10',
				'display_name' => 'Sucursal 10',
				'code' => 'DIR-HI-10',
				'city' => 'Hialeah',
				'region' => 'Oeste',
				'manager' => 'Por asignar',
				'phone' => '+1 (305) 555-0110',
				'address' => '1500 W 49th St, Hialeah',
				'status' => 'sin_datos',
				'status_label' => 'Provisional',
				'status_badge_class' => 'badge-sin-datos',
				'bhi' => null,
				'bhi_trend' => null,
				'bhi_trend_text' => '—',
				'bhi_trend_class' => 'trend-neutral',
				'bhi_grade' => 'Provisional',
				'active_incidents' => 0,
				'critical_incidents' => 0,
				'pending_actions' => 0,
				'overdue_actions' => 0,
				'critical_processes' => 0,
				'last_review' => 'Sin datos',
				'next_review' => 'Pendiente',
				'coverage_pct' => 40.0,
				'size_volume' => 'Pequeña',
				'tickets_month' => 4200,
				'map_coords' => ['x' => 34, 'y' => 22],
				'requires_intervention' => false,
				'is_provisional' => true,
				'bhi_components' => null
			]
		];

		// KPI Cards (Primera Fila)
		// Total: 10 supervisadas, 4 operativas, 3 en atención, 2 críticas, +1 sin información suficiente = 10 mutuamente excluyentes
		$kpis = [
			'supervised_count' => 10,
			'operational_count' => 4,
			'attention_count' => 3,
			'critical_count' => 2,
			'insufficient_info_count' => 1,
			'multisite_incidents_count' => 23,
			'action_compliance_pct' => 78,
			'require_intervention_count' => 4
		];

		// 2. Ranking BHI Ordenado por mejor desempeño por defecto
		$rankingBhi = [
			[
				'rank' => 1,
				'branch_name' => 'Sucursal 01',
				'real_branch_id' => 1,
				'bhi' => 91,
				'status' => 'Excelente',
				'status_badge' => 'badge-operativa',
				'critical_incidents' => 0,
				'overdue_actions' => 0,
				'trend' => '▲ 2',
				'trend_class' => 'trend-up'
			],
			[
				'rank' => 2,
				'branch_name' => 'Sucursal 04',
				'real_branch_id' => 4,
				'bhi' => 86,
				'status' => 'Excelente',
				'status_badge' => 'badge-operativa',
				'critical_incidents' => 0,
				'overdue_actions' => 0,
				'trend' => '▲ 1',
				'trend_class' => 'trend-up'
			],
			[
				'rank' => 3,
				'branch_name' => 'Sucursal 06',
				'real_branch_id' => 6,
				'bhi' => 79,
				'status' => 'Estable',
				'status_badge' => 'badge-operativa',
				'critical_incidents' => 0,
				'overdue_actions' => 1,
				'trend' => '—',
				'trend_class' => 'trend-neutral'
			],
			[
				'rank' => 4,
				'branch_name' => 'Sucursal 09',
				'real_branch_id' => 9,
				'bhi' => 74,
				'status' => 'Estable',
				'status_badge' => 'badge-operativa',
				'critical_incidents' => 0,
				'overdue_actions' => 1,
				'trend' => '▲ 3',
				'trend_class' => 'trend-up'
			],
			[
				'rank' => 5,
				'branch_name' => 'Sunny Isles',
				'real_branch_id' => 3,
				'bhi' => 68,
				'status' => 'Atención',
				'status_badge' => 'badge-atencion',
				'critical_incidents' => 1,
				'overdue_actions' => 2,
				'trend' => '▼ 4',
				'trend_class' => 'trend-down'
			],
			[
				'rank' => 6,
				'branch_name' => 'Hallandale Beach',
				'real_branch_id' => 1,
				'bhi' => 63,
				'status' => 'Atención',
				'status_badge' => 'badge-atencion',
				'critical_incidents' => 1,
				'overdue_actions' => 2,
				'trend' => '▼ 6',
				'trend_class' => 'trend-down'
			],
			[
				'rank' => 7,
				'branch_name' => 'Sucursal 05',
				'real_branch_id' => 5,
				'bhi' => 59,
				'status' => 'Atención',
				'status_badge' => 'badge-atencion',
				'critical_incidents' => 1,
				'overdue_actions' => 1,
				'trend' => '—',
				'trend_class' => 'trend-neutral'
			],
			[
				'rank' => 8,
				'branch_name' => 'Miami Beach',
				'real_branch_id' => 2,
				'bhi' => 47,
				'status' => 'Crítico',
				'status_badge' => 'badge-critico',
				'critical_incidents' => 3,
				'overdue_actions' => 4,
				'trend' => '▼ 9',
				'trend_class' => 'trend-down'
			],
			[
				'rank' => 9,
				'branch_name' => 'Sucursal 07',
				'real_branch_id' => 7,
				'bhi' => 41,
				'status' => 'Crítico',
				'status_badge' => 'badge-critico',
				'critical_incidents' => 2,
				'overdue_actions' => 3,
				'trend' => '▼ 5',
				'trend_class' => 'trend-down'
			],
			[
				'rank' => 10,
				'branch_name' => 'Sucursal 10',
				'real_branch_id' => 10,
				'bhi' => null,
				'status' => 'Provisional',
				'status_badge' => 'badge-sin-datos',
				'critical_incidents' => null,
				'overdue_actions' => null,
				'trend' => '—',
				'trend_class' => 'trend-neutral'
			]
		];

		// 3. Matriz de Supervisión Multisucursal (10 tiendas × 7 procesos)
		// Procesos: Operación, Inventario, Personal, Despachos, Mermas, Prevención, Cumplimiento
		$supervisionMatrix = [
			[
				'branch_name' => 'Sucursal 01',
				'branch_id' => 1,
				'processes' => [
					'operacion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Operación continua 99.4% en turno regular.'],
					'inventario' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Exactitud de conteo 98.7%.'],
					'personal' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Asistencia 100%, 0 ausencias sin justificar.'],
					'despachos' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Entregas a tiempo 97.2%.'],
					'mermas' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Merma controlada en 0.8% s/venta.'],
					'prevencion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Cero incidentes de pérdida reportados.'],
					'cumplimiento' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Checklists operativos cerrados al 100%.']
				]
			],
			[
				'branch_name' => 'Sucursal 04',
				'branch_id' => 4,
				'processes' => [
					'operacion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Piso de venta operativo sin bloqueos.'],
					'inventario' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Stock sincronizado con almacén central.'],
					'personal' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Turnos completos.'],
					'despachos' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Retraso de 15 min en recepción matutina.'],
					'mermas' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Sin desvíos en perecederos.'],
					'prevencion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Auditoría interna aprobada.'],
					'cumplimiento' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Protocolos de apertura al día.']
				]
			],
			[
				'branch_name' => 'Sucursal 06',
				'branch_id' => 6,
				'processes' => [
					'operacion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'POS y balanzas calibradas.'],
					'inventario' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Kárdex conciliado al día.'],
					'personal' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Horas extras superan umbral en 8%.'],
					'despachos' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Rutas locales completadas.'],
					'mermas' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Rotación FEFO adecuada.'],
					'prevencion' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Cámara 3 con intermitencia de red.'],
					'cumplimiento' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Bitácora sanitaria firmada.']
				]
			],
			[
				'branch_name' => 'Sucursal 09',
				'branch_id' => 9,
				'processes' => [
					'operacion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Flujo normal de transacciones.'],
					'inventario' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Diferencia en 2 SKU de lácteos.'],
					'personal' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Dotación completa.'],
					'despachos' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Recepción ASN conforme.'],
					'mermas' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Averías menores a $45.'],
					'prevencion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Candados y alarmas verificados.'],
					'cumplimiento' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Cierres de caja al 99.1%.']
				]
			],
			[
				'branch_name' => 'Sunny Isles',
				'branch_id' => 3,
				'processes' => [
					'operacion' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Pico de afluencia saturó cajas rápidas.'],
					'inventario' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Stock físico coincide con sistema.'],
					'personal' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Turno fin de semana con 1 vacante.'],
					'despachos' => ['status' => 'critico', 'label' => 'Crítica', 'detail' => 'Retraso crítico de 4 h en pedidos online.'],
					'mermas' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Merma en carnes supera objetivo en 1.4%.'],
					'prevencion' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Diferencia en conteo de mermas.'],
					'cumplimiento' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => '1 tarea preventiva vencida.']
				]
			],
			[
				'branch_name' => 'Hallandale Beach',
				'branch_id' => 1,
				'selected' => true,
				'processes' => [
					'operacion' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Rotación lenta en góndolas refrigeradas de pasillo 2.', 'highlight' => true],
					'inventario' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Quiebres reiterados en leche entera 1L y yogures.'],
					'personal' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Cobertura vespertina al 84% de dotación meta.'],
					'despachos' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Rutas de entrega dentro de ventana horaria.'],
					'mermas' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Exposición a vencimiento FEFO en pescadería.'],
					'prevencion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Controles perimetrales en regla.'],
					'cumplimiento' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => '2 acciones correctivas con fecha límite vencida.']
				]
			],
			[
				'branch_name' => 'Sucursal 05',
				'branch_id' => 5,
				'processes' => [
					'operacion' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Tiempos de fila en cajas superaron 6 min.'],
					'inventario' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Desfase en registro de transferencias.'],
					'personal' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Cobertura completa de supervisión.'],
					'despachos' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Recepción de proveedores a tiempo.'],
					'mermas' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Merma panadería dentro de ratio.'],
					'prevencion' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Sin pérdidas registradas.'],
					'cumplimiento' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Acta de inventario pendiente de firma.']
				]
			],
			[
				'branch_name' => 'Miami Beach',
				'branch_id' => 2,
				'processes' => [
					'operacion' => ['status' => 'critico', 'label' => 'Crítico', 'detail' => 'Acciones correctivas vencidas hace más de 12 días.'],
					'inventario' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Diferencias cíclicas en categoría licores.'],
					'personal' => ['status' => 'critico', 'label' => 'Crítico', 'detail' => 'Ausentismo no programado del 18% en fin de semana.'],
					'despachos' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Espacio de carga congestionado en horas pico.'],
					'mermas' => ['status' => 'critico', 'label' => 'Crítico', 'detail' => 'Pérdida por corte de frío en congelador 4.'],
					'prevencion' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Alerta de merma no aclarada.'],
					'cumplimiento' => ['status' => 'critico', 'label' => 'Crítico', 'detail' => 'Auditoría comercial reprobada con score 47/100.']
				]
			],
			[
				'branch_name' => 'Sucursal 07',
				'branch_id' => 7,
				'processes' => [
					'operacion' => ['status' => 'critico', 'label' => 'Crítico', 'detail' => 'Fallas intermitentes en conexión POS principal.'],
					'inventario' => ['status' => 'critico', 'label' => 'Crítico', 'detail' => 'Desviaciones recurrentes vs. conteo físico (-4.2%).'],
					'personal' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Alta rotación de cajeros en último mes.'],
					'despachos' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Demoras en validación de guías de despacho.'],
					'mermas' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Merma en frutas y verduras por sobreabastecimiento.'],
					'prevencion' => ['status' => 'critico', 'label' => 'Crítica', 'detail' => 'Pérdida desconocida detectada en auditoría.'],
					'cumplimiento' => ['status' => 'atencion', 'label' => 'Atención', 'detail' => 'Planes de contingencia desactualizados.']
				]
			],
			[
				'branch_name' => 'Sucursal 10',
				'branch_id' => 10,
				'processes' => [
					'operacion' => ['status' => 'sin_datos', 'label' => 'Sin datos', 'detail' => 'Cobertura de telemetría inferior al 40%.'],
					'inventario' => ['status' => 'sin_datos', 'label' => 'Sin datos', 'detail' => 'Sin conteo físico validado en los últimos 45 días.'],
					'personal' => ['status' => 'sin_datos', 'label' => 'Sin datos', 'detail' => 'Marcaciones de reloj no sincronizadas.'],
					'despachos' => ['status' => 'normal', 'label' => 'Normal', 'detail' => 'Ruta centralizada operativa.'],
					'mermas' => ['status' => 'sin_datos', 'label' => 'Sin datos', 'detail' => 'Sin registros de merma cargados.'],
					'prevencion' => ['status' => 'sin_datos', 'label' => 'Sin datos', 'detail' => 'Cámaras en proceso de instalación.'],
					'cumplimiento' => ['status' => 'sin_datos', 'label' => 'Sin datos', 'detail' => 'En período de habilitación inicial.']
				]
			]
		];

		// 4. Centro de Incidencias por Sucursal
		$incidents = [
			[
				'id' => 101,
				'branch_name' => 'Miami Beach',
				'branch_id' => 2,
				'type' => 'Operativo',
				'type_badge' => 'badge-operativo',
				'description' => 'Acciones correctivas vencidas',
				'severity' => 'Crítica',
				'severity_badge' => 'badge-critico',
				'responsible' => 'Gerente 03',
				'responsible_assigned' => true,
				'days_open' => '12 d',
				'status' => 'Escalado',
				'status_badge' => 'badge-escalado',
				'evidence_attached' => true,
				'evidence_file' => 'informe_escalamiento_mb.pdf',
				'action_label' => 'Abrir'
			],
			[
				'id' => 102,
				'branch_name' => 'Sucursal 07',
				'branch_id' => 7,
				'type' => 'Inventario',
				'type_badge' => 'badge-inventario',
				'description' => 'Desviaciones recurrentes vs. conteo',
				'severity' => 'Crítica',
				'severity_badge' => 'badge-critico',
				'responsible' => 'Supervisor 07',
				'responsible_assigned' => true,
				'days_open' => '9 d',
				'status' => 'En diagnóstico',
				'status_badge' => 'badge-diagnostico',
				'evidence_attached' => true,
				'evidence_file' => 'diferencias_conteo_s07.xlsx',
				'action_label' => 'Abrir'
			],
			[
				'id' => 103,
				'branch_name' => 'Hallandale Beach',
				'branch_id' => 1,
				'type' => 'Inventario',
				'type_badge' => 'badge-inventario',
				'description' => 'Reposición recurrente en góndola',
				'severity' => 'Alta',
				'severity_badge' => 'badge-alta',
				'responsible' => 'Supervisor 02',
				'responsible_assigned' => true,
				'days_open' => '6 d',
				'status' => 'En diagnóstico',
				'status_badge' => 'badge-diagnostico',
				'evidence_attached' => true,
				'evidence_file' => 'quiebres_gondola_hb.jpg',
				'action_label' => 'Abrir'
			],
			[
				'id' => 104,
				'branch_name' => 'Sunny Isles',
				'branch_id' => 3,
				'type' => 'Despachos',
				'type_badge' => 'badge-despachos',
				'description' => 'Retraso en preparación de pedidos',
				'severity' => 'Alta',
				'severity_badge' => 'badge-alta',
				'responsible' => 'Supervisor 05',
				'responsible_assigned' => true,
				'days_open' => '4 d',
				'status' => 'Asignada',
				'status_badge' => 'badge-asignada',
				'evidence_attached' => false,
				'evidence_file' => null,
				'action_label' => 'Abrir'
			],
			[
				'id' => 105,
				'branch_name' => 'Sucursal 05',
				'branch_id' => 5,
				'type' => 'Personal',
				'type_badge' => 'badge-personal',
				'description' => 'Subcobertura en turno de tarde',
				'severity' => 'Media',
				'severity_badge' => 'badge-media',
				'responsible' => 'Sin responsable',
				'responsible_assigned' => false,
				'days_open' => '3 d',
				'status' => 'Abierta',
				'status_badge' => 'badge-abierta',
				'evidence_attached' => false,
				'evidence_file' => null,
				'action_label' => 'Asignar'
			],
			[
				'id' => 106,
				'branch_name' => 'Sucursal 09',
				'branch_id' => 9,
				'type' => 'Sistemas',
				'type_badge' => 'badge-sistemas',
				'description' => 'Caja 1 Mañana sin cerrar',
				'severity' => 'Media',
				'severity_badge' => 'badge-media',
				'responsible' => 'Supervisor 09',
				'responsible_assigned' => true,
				'days_open' => '2 d',
				'status' => 'Asignada',
				'status_badge' => 'badge-asignada',
				'evidence_attached' => true,
				'evidence_file' => 'cierre_caja_incompleto.log',
				'action_label' => 'Abrir'
			]
		];

		// 5. Patrones Multisucursal (Hipótesis, Correlación, Confirmada)
		$multisitePatterns = [
			[
				'id' => 1,
				'title' => 'Agotamientos recurrentes',
				'nature' => 'Hipótesis',
				'nature_badge' => 'badge-hipotesis',
				'stores_affected' => '5 tiendas',
				'cases_count' => '14 casos en 30 d',
				'trend' => 'Creciente',
				'trend_icon' => '▲',
				'trend_class' => 'trend-up-red',
				'cause_description' => 'Posible causa común: ciclo de pedido.',
				'regional_responsible' => 'Planeación y Compras',
				'recommended_action' => 'Ajustar buffers de seguridad por sucursal'
			],
			[
				'id' => 2,
				'title' => 'Retrasos de despacho',
				'nature' => 'Correlación',
				'nature_badge' => 'badge-correlacion',
				'stores_affected' => '3 tiendas',
				'cases_count' => '9 casos',
				'trend' => 'Creciente',
				'trend_icon' => '▲',
				'trend_class' => 'trend-up-red',
				'cause_description' => 'Coincide con picos de pedidos.',
				'regional_responsible' => 'Despachos',
				'recommended_action' => 'Balancear turnos de picking y cross-docking'
			],
			[
				'id' => 3,
				'title' => 'Cajas sin cerrar en turno mañana',
				'nature' => 'Confirmada',
				'nature_badge' => 'badge-confirmada',
				'stores_affected' => '4 tiendas',
				'cases_count' => '11 casos',
				'trend' => 'Estable',
				'trend_icon' => '—',
				'trend_class' => 'trend-neutral',
				'cause_description' => 'Causa: cierre de turno no ejecutado.',
				'regional_responsible' => 'Operaciones',
				'recommended_action' => 'Iniciar tarea regional: bloqueo automático por turno'
			]
		];

		// 6. Centro de Intervenciones Regionales
		$interventionCounters = [
			'pendiente' => 3,
			'diagnostico' => 2,
			'ejecucion' => 3,
			'validacion' => 1,
			'resuelta' => 7
		];

		$interventions = [
			[
				'id' => 201,
				'branch_name' => 'Miami Beach',
				'branch_id' => 2,
				'problem' => 'Acciones vencidas',
				'priority' => 'Alta',
				'priority_badge' => 'badge-alta',
				'responsible' => 'Gerente Regional',
				'corrective_action' => 'Escalar y cerrar acciones',
				'due_date' => '12/11',
				'evidence' => 'Informe de cierre',
				'status' => 'En ejecución',
				'status_badge' => 'badge-ejecucion',
				'is_resolved' => false
			],
			[
				'id' => 202,
				'branch_name' => 'Hallandale Beach',
				'branch_id' => 1,
				'problem' => 'Reposición recurrente',
				'priority' => 'Media',
				'priority_badge' => 'badge-media',
				'responsible' => 'Gerente 02',
				'corrective_action' => 'Revisión operativa',
				'due_date' => '15/11',
				'evidence' => 'Checklist de visita',
				'status' => 'En diagnóstico',
				'status_badge' => 'badge-diagnostico',
				'is_resolved' => false
			],
			[
				'id' => 203,
				'branch_name' => 'Sunny Isles',
				'branch_id' => 3,
				'problem' => 'Retraso de despacho',
				'priority' => 'Media',
				'priority_badge' => 'badge-media',
				'responsible' => 'Supervisor 05',
				'corrective_action' => 'Revisar preparación',
				'due_date' => '18/11',
				'evidence' => 'Tiempos de proceso',
				'status' => 'Pendiente',
				'status_badge' => 'badge-pendiente',
				'is_resolved' => false
			],
			[
				'id' => 204,
				'branch_name' => 'Sucursal 07',
				'branch_id' => 7,
				'problem' => 'Desviaciones de inventario',
				'priority' => 'Alta',
				'priority_badge' => 'badge-alta',
				'responsible' => 'Supervisor 07',
				'corrective_action' => 'Revisión de conciliación',
				'due_date' => '20/11',
				'evidence' => 'Acta de conciliación',
				'status' => 'En validación',
				'status_badge' => 'badge-validacion',
				'is_resolved' => false
			]
		];

		// 7. Seguimiento de Responsables
		$responsibleFollowup = [
			[
				'responsible_name' => 'Gerente 03',
				'branch_name' => 'Miami Beach',
				'branch_id' => 2,
				'compliance_pct' => 48,
				'bar_color_class' => 'bar-critico',
				'assigned_actions' => 12,
				'overdue_actions' => 6
			],
			[
				'responsible_name' => 'Gerente 02',
				'branch_name' => 'Hallandale Beach',
				'branch_id' => 1,
				'compliance_pct' => 70,
				'bar_color_class' => 'bar-atencion',
				'assigned_actions' => 10,
				'overdue_actions' => 3
			],
			[
				'responsible_name' => 'Supervisor 05',
				'branch_name' => 'Sunny Isles',
				'branch_id' => 3,
				'compliance_pct' => 76,
				'bar_color_class' => 'bar-atencion',
				'assigned_actions' => 8,
				'overdue_actions' => 2
			],
			[
				'responsible_name' => 'Supervisor 07',
				'branch_name' => 'Sucursal 07',
				'branch_id' => 7,
				'compliance_pct' => 62,
				'bar_color_class' => 'bar-atencion',
				'assigned_actions' => 9,
				'overdue_actions' => 3
			],
			[
				'responsible_name' => 'Gerente 09',
				'branch_name' => 'Sucursal 09',
				'branch_id' => 9,
				'compliance_pct' => 96,
				'bar_color_class' => 'bar-excelente',
				'assigned_actions' => 7,
				'overdue_actions' => 0
			]
		];

		// 8. Comparador de Sucursales (Normalizado por tamaño/volumen)
		// Sucursales activas en comparador inicial: Hallandale Beach (1), Sunny Isles (3), Miami Beach (2)
		$comparatorBranches = [
			'branch_1' => [
				'id' => 1,
				'name' => 'Hallandale Beach',
				'bhi' => 63,
				'process_compliance' => '81 %',
				'incident_resolution_time' => '5.2 d',
				'operational_coverage' => '88 %',
				'actions_completed' => '72 %',
				'recurrent_incidents_rate' => '2.1', // por 1,000 tickets
				'risk_level' => 'Medio',
				'risk_level_badge' => 'badge-atencion'
			],
			'branch_3' => [
				'id' => 3,
				'name' => 'Sunny Isles',
				'bhi' => 68,
				'process_compliance' => '84 %',
				'incident_resolution_time' => '4.1 d',
				'operational_coverage' => '91 %',
				'actions_completed' => '78 %',
				'recurrent_incidents_rate' => '1.8',
				'risk_level' => 'Medio',
				'risk_level_badge' => 'badge-atencion'
			],
			'branch_2' => [
				'id' => 2,
				'name' => 'Miami Beach',
				'bhi' => 47,
				'process_compliance' => '62 %',
				'incident_resolution_time' => '9.8 d',
				'operational_coverage' => '78 %',
				'actions_completed' => '51 %',
				'recurrent_incidents_rate' => '3.4',
				'risk_level' => 'Alto',
				'risk_level_badge' => 'badge-critico'
			]
		];

		// 9. Bitácora de Visitas y Auditorías
		$visitLog = [
			[
				'id' => 301,
				'branch_name' => 'Miami Beach',
				'branch_id' => 2,
				'visit_date' => '04/11',
				'visit_type' => 'Seguimiento de acciones vencidas',
				'responsible' => 'Auditor Regional Norte',
				'findings_count' => 2,
				'findings_badge' => '2 hallazgos',
				'findings_details' => 'Incumplimiento de horario de reposición en frío y desbalance de caja 2.',
				'next_review' => '18/11',
				'status' => 'completada'
			],
			[
				'id' => 302,
				'branch_name' => 'Hallandale Beach',
				'branch_id' => 1,
				'visit_date' => '28/10',
				'visit_type' => 'Auditoría de reposición',
				'responsible' => 'Gerente de Operaciones',
				'findings_count' => 2,
				'findings_badge' => '2 hallazgos',
				'findings_details' => 'Quiebres visuales en góndolas lácteas y falta de etiquetado FEFO en pescados.',
				'next_review' => '11/11',
				'status' => 'completada'
			],
			[
				'id' => 303,
				'branch_name' => 'Sunny Isles',
				'branch_id' => 3,
				'visit_date' => '21/10',
				'visit_type' => 'Revisión de despachos',
				'responsible' => 'Coordinador Logístico',
				'findings_count' => 1,
				'findings_badge' => '1 hallazgo',
				'findings_details' => 'Retraso de 40 min en carga de transporte de última milla.',
				'next_review' => '15/11',
				'status' => 'completada'
			]
		];

		return [
			'filters' => [
				'period' => '08/10 – 06/11/2026',
				'region' => 'Todas',
				'city' => 'Todas',
				'branch' => 'Todas (10)',
				'manager' => 'Todos',
				'status' => 'Todos',
				'priority' => 'Todas',
				'incident_type' => 'Todas'
			],
			'kpis' => $kpis,
			'bhi_weights' => $bhiWeights,
			'branches' => $branches,
			'selected_branch' => $branches[0], // Hallandale Beach por defecto
			'ranking_bhi' => $rankingBhi,
			'supervision_matrix' => $supervisionMatrix,
			'incidents' => $incidents,
			'multisite_patterns' => $multisitePatterns,
			'intervention_counters' => $interventionCounters,
			'interventions' => $interventions,
			'responsible_followup' => $responsibleFollowup,
			'comparator_branches' => $comparatorBranches,
			'visit_log' => $visitLog
		];
	}
}

