<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * DAILY — SERVICIO DEL TABLERO DE DESPACHOS Y DEVOLUCIONES
 * Provee datos calibrados y coherentes con la captura de referencia exacta (Image 3)
 * para indicadores, gráficos, tabla de cumplimiento por sucursal,
 * pedidos críticos y clasificación de devoluciones.
 */
class DispatchDashboardService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public function GetDashboardData(array $filters = []): array
	{
		$branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;
		$dept = !empty($filters['department']) ? $filters['department'] : null;

		// Base calibrada global (exacta a la captura de pantalla)
		$baseDispatched = 1248;
		$basePending = 64;
		$baseOnTimePct = 96.8;
		$baseDelayed = 12;
		$baseInTransit = 86;
		$baseConfirmed = 1162;
		$baseReturns = 38;
		$basePendingReturns = 9;

		// Cumplimiento por sucursal (exacto a la foto)
		$branchesCompliance = [
			[
				'id' => 1,
				'name' => 'Centro',
				'dispatched' => 324,
				'ontime_pct' => 97.5,
				'delayed' => 8,
				'share_pct' => 26.0
			],
			[
				'id' => 2,
				'name' => 'Norte',
				'dispatched' => 268,
				'ontime_pct' => 96.6,
				'delayed' => 9,
				'share_pct' => 21.5
			],
			[
				'id' => 3,
				'name' => 'Sur',
				'dispatched' => 246,
				'ontime_pct' => 96.3,
				'delayed' => 9,
				'share_pct' => 19.7
			],
			[
				'id' => 4,
				'name' => 'Oriente',
				'dispatched' => 212,
				'ontime_pct' => 97.2,
				'delayed' => 6,
				'share_pct' => 17.0
			],
			[
				'id' => 5,
				'name' => 'Poniente',
				'dispatched' => 198,
				'ontime_pct' => 96.9,
				'delayed' => 8,
				'share_pct' => 15.8
			],
		];

		// Si se filtra por sucursal específica
		if ($branchId !== null && $branchId > 0)
		{
			$targetBranch = null;
			foreach ($branchesCompliance as $b)
			{
				if ($b['id'] == $branchId)
				{
					$targetBranch = $b;
					break;
				}
			}

			if ($targetBranch)
			{
				$factor = $targetBranch['dispatched'] / $baseDispatched;
				$dispatched = $targetBranch['dispatched'];
				$onTimePct = $targetBranch['ontime_pct'];
				$delayed = $targetBranch['delayed'];
				$pending = max(1, round($basePending * $factor));
				$inTransit = max(1, round($baseInTransit * $factor));
				$confirmed = max(1, round($baseConfirmed * $factor));
				$returns = max(1, round($baseReturns * $factor));
				$pendingReturns = max(1, round($basePendingReturns * $factor));
			}
			else
			{
				$dispatched = $baseDispatched;
				$onTimePct = $baseOnTimePct;
				$delayed = $baseDelayed;
				$pending = $basePending;
				$inTransit = $baseInTransit;
				$confirmed = $baseConfirmed;
				$returns = $baseReturns;
				$pendingReturns = $basePendingReturns;
			}
		}
		else
		{
			$dispatched = $baseDispatched;
			$onTimePct = $baseOnTimePct;
			$delayed = $baseDelayed;
			$pending = $basePending;
			$inTransit = $baseInTransit;
			$confirmed = $baseConfirmed;
			$returns = $baseReturns;
			$pendingReturns = $basePendingReturns;
		}

		// 30 días para el gráfico con valores consistentes con la imagen (escala 0 a 1,250)
		$dates30 = [
			'15 oct', '16 oct', '17 oct', '18 oct', '19 oct', '20 oct', '21 oct',
			'22 oct', '23 oct', '24 oct', '25 oct', '26 oct', '27 oct', '28 oct',
			'29 oct', '30 oct', '31 oct', '01 nov', '02 nov', '03 nov', '04 nov',
			'05 nov', '06 nov', '07 nov', '08 nov', '09 nov', '10 nov', '11 nov',
			'12 nov', '13 nov'
		];
		// Valores de barras (Despachos) y línea (Entregas) calibrados a la foto:
		// Se mueven entre 650 y 1,050
		$despachos30 = [
			650, 680, 710, 750, 730, 780, 810,
			790, 830, 850, 820, 860, 880, 870,
			890, 910, 940, 920, 960, 980, 990,
			1020, 1040, 1060, 1080, 970, 920, 890,
			880, 860
		];
		$entregas30 = [
			680, 710, 730, 770, 760, 800, 830,
			810, 850, 870, 840, 880, 900, 890,
			910, 930, 960, 940, 980, 1000, 1010,
			1040, 1050, 1070, 1090, 990, 940, 920,
			910, 890
		];

		// Pedidos que requieren atención (exacto a la foto)
		$attentionOrders = [
			[
				'id' => 1,
				'code' => 'PED-081245',
				'branch' => 'Centro',
				'status' => 'Atrasado',
				'status_type' => 'danger',
				'deadline' => '10:30',
				'delay' => '+2h 45m atraso',
				'customer' => 'Restaurante El Gourmet',
				'items' => 18,
				'amount' => '$ 1,240.00'
			],
			[
				'id' => 2,
				'code' => 'PED-081276',
				'branch' => 'Norte',
				'status' => 'Pendiente',
				'status_type' => 'warning',
				'deadline' => '12:00',
				'delay' => 'Vence en 25m',
				'customer' => 'Minimarket Los Andes',
				'items' => 8,
				'amount' => '$ 485.50'
			],
			[
				'id' => 3,
				'code' => 'PED-081292',
				'branch' => 'Sur',
				'status' => 'Pendiente',
				'status_type' => 'warning',
				'deadline' => '12:30',
				'delay' => 'Vence en 55m',
				'customer' => 'Cafetería San Isidro',
				'items' => 12,
				'amount' => '$ 820.00'
			],
			[
				'id' => 4,
				'code' => 'PED-081307',
				'branch' => 'Oriente',
				'status' => 'Atrasado',
				'status_type' => 'danger',
				'deadline' => '11:45',
				'delay' => '+1h 15m atraso',
				'customer' => 'Bodega Central Express',
				'items' => 24,
				'amount' => '$ 1,750.00'
			],
			[
				'id' => 5,
				'code' => 'PED-081318',
				'branch' => 'Poniente',
				'status' => 'Pendiente',
				'status_type' => 'warning',
				'deadline' => '13:00',
				'delay' => 'Vence en 1h 25m',
				'customer' => 'Pastelería Dolce Vita',
				'items' => 5,
				'amount' => '$ 340.00'
			],
		];

		// Devoluciones por motivo (exacto a la foto: 14 + 10 + 8 + 6 = 38)
		$returnsByReason = [
			'total' => 38,
			'reasons' => [
				[
					'label' => 'Producto dañado',
					'count' => 14,
					'pct' => round((14 / 38) * 100, 1),
					'color' => '#10b26c'
				],
				[
					'label' => 'Pedido incorrecto',
					'count' => 10,
					'pct' => round((10 / 38) * 100, 1),
					'color' => '#10b26c'
				],
				[
					'label' => 'Producto rechazado',
					'count' => 8,
					'pct' => round((8 / 38) * 100, 1),
					'color' => '#10b26c'
				],
				[
					'label' => 'Otros',
					'count' => 6,
					'pct' => round((6 / 38) * 100, 1),
					'color' => '#10b26c'
				],
			]
		];

		// Estado de las entregas (Dona: 1,162 entregados + 86 en tránsito = 1,248)
		$deliveryStatusDonut = [
			'total' => 1248,
			'delivered' => [
				'count' => 1162,
				'label' => 'Entregados',
				'pct' => round((1162 / 1248) * 100, 1),
				'color' => '#10b26c'
			],
			'in_transit' => [
				'count' => 86,
				'label' => 'En tránsito',
				'pct' => round((86 / 1248) * 100, 1),
				'color' => '#f59e0b'
			]
		];

		// Módulos conectados (exacto a la foto)
		$connectedModules = [
			'receives_from' => [
				[
					'name' => 'Pedidos',
					'desc' => 'Registro de pedidos y su estado de preparación',
					'url' => '/orders',
					'icon' => 'package'
				],
				[
					'name' => 'Almacén',
					'desc' => 'Disponibilidad, surtido y preparación de pedidos',
					'url' => '/warehouse',
					'icon' => 'building'
				]
			],
			'feeds_to' => [
				[
					'name' => 'Stock y Movimientos',
					'desc' => 'Salida de inventario por despacho y reingreso por devoluciones',
					'url' => '/stockoverview',
					'icon' => 'layout-grid'
				],
				[
					'name' => 'Panel Ejecutivo',
					'desc' => 'Cumplimiento de despachos, entregas y devoluciones',
					'url' => '/',
					'icon' => 'trending-up'
				],
				[
					'name' => 'Reportes e Informes',
					'desc' => 'Despachos, entregas y devoluciones por sucursal y periodo',
					'url' => '/reports',
					'icon' => 'file-text'
				]
			]
		];

		return [
			'kpis' => [
				'dispatched' => [
					'value' => $dispatched,
					'label' => 'Pedidos despachados',
					'type' => 'hero'
				],
				'pending' => [
					'value' => $pending,
					'label' => 'Pendientes de despacho',
				],
				'ontime_pct' => [
					'value' => $onTimePct,
					'label' => 'Despachos a tiempo',
				],
				'delayed' => [
					'value' => $delayed,
					'label' => 'Despachos atrasados',
					'color' => '#ef4444'
				],
				'in_transit' => [
					'value' => $inTransit,
					'label' => 'Pedidos en tránsito',
				],
				'confirmed' => [
					'value' => $confirmed,
					'label' => 'Entregas confirmadas',
				],
				'returns_received' => [
					'value' => $returns,
					'label' => 'Devoluciones recibidas',
				],
				'returns_pending' => [
					'value' => $pendingReturns,
					'label' => 'Devoluciones pendientes',
					'color' => '#f59e0b'
				]
			],
			'evolution' => [
				'dates' => $dates30,
				'dispatched' => $despachos30,
				'delivered' => $entregas30,
			],
			'branches_compliance' => $branchesCompliance,
			'attention_orders' => $attentionOrders,
			'delivery_status_donut' => $deliveryStatusDonut,
			'returns_by_reason' => $returnsByReason,
			'connected_modules' => $connectedModules,
		];
	}
}
