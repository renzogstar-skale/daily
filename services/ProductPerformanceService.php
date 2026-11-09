<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * DAILY — Productos por Departamento (Ventas y Rentabilidad)
 * 
 * Centro de decisiones comerciales a nivel SKU:
 * Explorador inteligente, Ficha 360°, Matriz ABC-XYZ, Rotación vs Cobertura,
 * Matriz Margen x Rotación, Optimización del Surtido, Venta Perdida, Riesgo FEFO,
 * Acciones Prioritarias y Simulador Comercial What-If.
 */
class ProductPerformanceService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public function GetProductCatalogDashboard(array $filters = []): array
	{
		$db = $this->Db();
		
		// 1. Catálogos base de DAILY
		$branches = [];
		try {
			$stmtBr = $db->query("SELECT id, name FROM branches ORDER BY id ASC");
			if ($stmtBr) {
				$branches = $stmtBr->fetchAll(\PDO::FETCH_ASSOC);
			}
		} catch (\Exception $e) {}

		$departments = [
			['code' => '90', 'name' => 'Frozen', 'key' => 'frozen'],
			['code' => '91', 'name' => 'Dairy', 'key' => 'dairy'],
			['code' => '92', 'name' => 'Pescadería', 'key' => 'pescaderia'],
			['code' => '93', 'name' => 'Carnes', 'key' => 'carnes'],
			['code' => '94', 'name' => 'Bakery', 'key' => 'bakery']
		];

		$categories = [
			'Leches', 'Helados', 'Pescado fresco', 'Res', 'Pan dulce',
			'Yogurt', 'Comidas prep.', 'Aves', 'Quesos', 'Embutidos'
		];

		$subcategories = [
			'Entera', 'Deslactosada', 'Potes 1.5L', 'Filetes blancos', 'Cortes finos',
			'Bollería', 'Griego', 'Pizzas congeladas', 'Pollo fresco'
		];

		$brands = [
			'Lácteos del Valle', 'Helados del Sur', 'Mar y Selva', 'Hacienda Real',
			'La Baguette', 'Yogurísimo', 'Pizza Bella', 'Granja Dorada'
		];

		$suppliers = [
			'Distribuidora Láctea Central', 'Congelados del Plata', 'Pesquera Pacífico',
			'Carnes y Derivados S.A.', 'Molinos y Panes', 'Avícola Santa Rosa'
		];

		// 2. KPIs de la Primera Fila
		$kpis = [
			'active_skus' => [
				'value' => '1,248',
				'num' => 1248,
				'sub' => '5 departamentos',
				'label' => 'SKU activos',
				'icon' => 'boxes-stacked'
			],
			'availability_pct' => [
				'value' => '94.2 %',
				'num' => 94.2,
				'sub' => '1,176 SKU vendibles',
				'label' => 'Disponibilidad de productos',
				'icon' => 'circle-check'
			],
			'low_rotation_count' => [
				'value' => '137',
				'num' => 137,
				'sub' => 'bajo la velocidad objetivo',
				'label' => 'Productos de baja rotación',
				'icon' => 'arrow-down-right'
			],
			'stockout_count' => [
				'value' => '72',
				'num' => 72,
				'sub' => '<span class="text-danger font-weight-bold">11 en alta demanda</span>',
				'sub_plain' => '11 en alta demanda',
				'label' => 'Productos agotados',
				'icon' => 'triangle-alert'
			],
			'insufficient_margin' => [
				'value' => '96',
				'num' => 96,
				'sub' => 'bajo el margen objetivo',
				'label' => '% Margen insuficiente',
				'icon' => 'percent'
			],
			'expiring_soon' => [
				'value' => '58',
				'num' => 58,
				'sub' => 'lotes o SKU en riesgo',
				'label' => 'Próximos a vencer',
				'icon' => 'clock'
			],
			'commercial_opportunity' => [
				'value' => '≈ $18,900',
				'num' => 18900,
				'sub' => 'estimación · confianza media',
				'label' => 'Oportunidad comercial estimada',
				'is_green' => true,
				'icon' => 'circle-dollar-sign'
			]
		];

		// 3. Catálogo de Productos con datos precisos de la foto
		$products = [
			[
				'id' => 1,
				'name' => 'Leche entera 1 L',
				'sku' => 'DAI-1001',
				'upc' => '7501055310001',
				'dept_code' => '91',
				'department' => '91 Dairy',
				'category' => 'Leches',
				'subcategory' => 'Entera',
				'brand' => 'Lácteos del Valle',
				'supplier' => 'Distribuidora Láctea Central',
				'initial' => 'L',
				'avatar_bg' => '#e0f2fe',
				'avatar_color' => '#0284c7',
				'price' => 1.89,
				'cost' => 1.47,
				'unit_margin' => 0.42,
				'margin_pct' => 22.2,
				'stock' => 148,
				'sales_per_day' => 96,
				'coverage_days' => 1.5,
				'status' => 'critica',
				'status_label' => 'Crítica',
				'status_badge' => 'badge-danger',
				'demand_trend' => '+9 %',
				'elasticity' => -0.8,
				'risk_level' => 'Alto',
				'stockout_est' => '07/11 ~12:00',
				'next_reception' => '08/11 (sin confirmar)',
				'lifecycle' => 'Crecimiento',
				'abc' => 'A',
				'xyz' => 'X',
				'quadrant' => 'high_rot_high_margin',
				'surtido_action' => 'mantener',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '22.8 %', 'sales' => 41, 'coverage' => '0.9 d', 'coverage_state' => 'critica'],
					['branch' => 'Norte', 'margin' => '22.0 %', 'sales' => 33, 'coverage' => '1.8 d', 'coverage_state' => 'atencion'],
					['branch' => 'Sur', 'margin' => '21.8 %', 'sales' => 22, 'coverage' => '2.4 d', 'coverage_state' => 'atencion']
				]
			],
			[
				'id' => 2,
				'name' => 'Helado vainilla 1.5 L',
				'sku' => 'FRZ-2210',
				'upc' => '7501055322104',
				'dept_code' => '90',
				'department' => '90 Frozen',
				'category' => 'Helados',
				'subcategory' => 'Potes 1.5L',
				'brand' => 'Helados del Sur',
				'supplier' => 'Congelados del Plata',
				'initial' => 'H',
				'avatar_bg' => '#f3e8ff',
				'avatar_color' => '#9333ea',
				'price' => 5.92,
				'cost' => 4.45,
				'unit_margin' => 1.47,
				'margin_pct' => 24.8,
				'stock' => 612,
				'sales_per_day' => 7,
				'coverage_days' => 87.0,
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_badge' => 'badge-warning',
				'demand_trend' => '-3 %',
				'elasticity' => -1.2,
				'risk_level' => 'Medio',
				'stockout_est' => '15/12 ~18:00',
				'next_reception' => 'No requerida',
				'lifecycle' => 'Estable',
				'abc' => 'A',
				'xyz' => 'Z',
				'quadrant' => 'low_rot_high_margin',
				'surtido_action' => 'revisar',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '25.1 %', 'sales' => 3, 'coverage' => '92.0 d', 'coverage_state' => 'atencion'],
					['branch' => 'Norte', 'margin' => '24.5 %', 'sales' => 2, 'coverage' => '85.0 d', 'coverage_state' => 'atencion'],
					['branch' => 'Sur', 'margin' => '24.6 %', 'sales' => 2, 'coverage' => '84.0 d', 'coverage_state' => 'atencion']
				]
			],
			[
				'id' => 3,
				'name' => 'Filete de tilapia 500 g',
				'sku' => 'PES-3105',
				'upc' => '7501055331052',
				'dept_code' => '92',
				'department' => '92 Pescadería',
				'category' => 'Pescado fresco',
				'subcategory' => 'Filetes blancos',
				'brand' => 'Mar y Selva',
				'supplier' => 'Pesquera Pacífico',
				'initial' => 'F',
				'avatar_bg' => '#e0f2fe',
				'avatar_color' => '#0369a1',
				'price' => 7.99,
				'cost' => 5.82,
				'unit_margin' => 2.17,
				'margin_pct' => 27.1,
				'stock' => 64,
				'sales_per_day' => 6,
				'coverage_days' => 10.7,
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_badge' => 'badge-warning',
				'demand_trend' => '+1 %',
				'elasticity' => -0.9,
				'risk_level' => 'Alto',
				'stockout_est' => '17/11 ~14:00',
				'next_reception' => '10/11 programada',
				'lifecycle' => 'Estable',
				'abc' => 'B',
				'xyz' => 'Y',
				'quadrant' => 'low_rot_high_margin',
				'surtido_action' => 'revisar',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '28.0 %', 'sales' => 3, 'coverage' => '9.5 d', 'coverage_state' => 'atencion'],
					['branch' => 'Norte', 'margin' => '26.8 %', 'sales' => 2, 'coverage' => '11.0 d', 'coverage_state' => 'atencion'],
					['branch' => 'Sur', 'margin' => '26.2 %', 'sales' => 1, 'coverage' => '12.0 d', 'coverage_state' => 'normal']
				]
			],
			[
				'id' => 4,
				'name' => 'Lomo de res premium kg',
				'sku' => 'CAR-4020',
				'upc' => '7501055340207',
				'dept_code' => '93',
				'department' => '93 Carnes',
				'category' => 'Res',
				'subcategory' => 'Cortes finos',
				'brand' => 'Hacienda Real',
				'supplier' => 'Carnes y Derivados S.A.',
				'initial' => 'L',
				'avatar_bg' => '#ffedd5',
				'avatar_color' => '#c2410c',
				'price' => 12.47,
				'cost' => 11.00,
				'unit_margin' => 1.47,
				'margin_pct' => 11.8,
				'stock' => 210,
				'sales_per_day' => 31,
				'coverage_days' => 6.8,
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_badge' => 'badge-warning',
				'demand_trend' => '+4 %',
				'elasticity' => -0.6,
				'risk_level' => 'Medio',
				'stockout_est' => '13/11 ~20:00',
				'next_reception' => '09/11 programada',
				'lifecycle' => 'Estable',
				'abc' => 'A',
				'xyz' => 'Y',
				'quadrant' => 'high_rot_low_margin',
				'surtido_action' => 'revisar',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '12.2 %', 'sales' => 15, 'coverage' => '6.0 d', 'coverage_state' => 'atencion'],
					['branch' => 'Norte', 'margin' => '11.5 %', 'sales' => 10, 'coverage' => '7.2 d', 'coverage_state' => 'atencion'],
					['branch' => 'Sur', 'margin' => '11.4 %', 'sales' => 6, 'coverage' => '7.8 d', 'coverage_state' => 'atencion']
				]
			],
			[
				'id' => 5,
				'name' => 'Croissant mantequilla',
				'sku' => 'BAK-5007',
				'upc' => '7501055350077',
				'dept_code' => '94',
				'department' => 'Bakery',
				'category' => 'Pan dulce',
				'subcategory' => 'Bollería',
				'brand' => 'La Baguette',
				'supplier' => 'Molinos y Panes',
				'initial' => 'C',
				'avatar_bg' => '#ffedd5',
				'avatar_color' => '#d97706',
				'price' => 1.25,
				'cost' => 0.74,
				'unit_margin' => 0.51,
				'margin_pct' => 41.0,
				'stock' => 0,
				'sales_per_day' => 74,
				'coverage_days' => 0.0,
				'status' => 'critica',
				'status_label' => 'Crítica',
				'status_badge' => 'badge-danger',
				'demand_trend' => '+14 %',
				'elasticity' => -1.1,
				'risk_level' => 'Alto',
				'stockout_est' => 'Agotado actualmente',
				'next_reception' => '07/11 06:00 (hornada diaria)',
				'lifecycle' => 'Crecimiento',
				'abc' => 'B',
				'xyz' => 'X',
				'quadrant' => 'high_rot_high_margin',
				'surtido_action' => 'potenciar',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '41.5 %', 'sales' => 35, 'coverage' => '0.0 d', 'coverage_state' => 'critica'],
					['branch' => 'Norte', 'margin' => '40.8 %', 'sales' => 24, 'coverage' => '0.0 d', 'coverage_state' => 'critica'],
					['branch' => 'Sur', 'margin' => '40.2 %', 'sales' => 15, 'coverage' => '0.0 d', 'coverage_state' => 'critica']
				]
			],
			[
				'id' => 6,
				'name' => 'Yogurt griego 500 g',
				'sku' => 'DAI-1044',
				'upc' => '7501055310445',
				'dept_code' => '91',
				'department' => '91 Dairy',
				'category' => 'Yogurt',
				'subcategory' => 'Griego',
				'brand' => 'Yogurísimo',
				'supplier' => 'Distribuidora Láctea Central',
				'initial' => 'Y',
				'avatar_bg' => '#e0f2fe',
				'avatar_color' => '#0284c7',
				'price' => 3.23,
				'cost' => 2.43,
				'unit_margin' => 0.80,
				'margin_pct' => 24.6,
				'stock' => 320,
				'sales_per_day' => 28,
				'coverage_days' => 11.4,
				'status' => 'normal',
				'status_label' => 'Normal',
				'status_badge' => 'badge-success',
				'demand_trend' => '+6 %',
				'elasticity' => -0.7,
				'risk_level' => 'Bajo',
				'stockout_est' => '18/11 ~15:00',
				'next_reception' => '12/11 programada',
				'lifecycle' => 'Estable',
				'abc' => 'B',
				'xyz' => 'Y',
				'quadrant' => 'high_rot_high_margin',
				'surtido_action' => 'mantener',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '25.0 %', 'sales' => 14, 'coverage' => '11.0 d', 'coverage_state' => 'normal'],
					['branch' => 'Norte', 'margin' => '24.4 %', 'sales' => 8, 'coverage' => '12.1 d', 'coverage_state' => 'normal'],
					['branch' => 'Sur', 'margin' => '24.2 %', 'sales' => 6, 'coverage' => '11.5 d', 'coverage_state' => 'normal']
				]
			],
			[
				'id' => 7,
				'name' => 'Pizza congelada 4 quesos',
				'sku' => 'FRZ-3087',
				'upc' => '7501055330871',
				'dept_code' => '90',
				'department' => '90 Frozen',
				'category' => 'Comidas prep.',
				'subcategory' => 'Pizzas congeladas',
				'brand' => 'Pizza Bella',
				'supplier' => 'Congelados del Plata',
				'initial' => 'P',
				'avatar_bg' => '#f3e8ff',
				'avatar_color' => '#7e22ce',
				'price' => 6.93,
				'cost' => 4.75,
				'unit_margin' => 2.18,
				'margin_pct' => 31.5,
				'stock' => 190,
				'sales_per_day' => 21,
				'coverage_days' => 9.0,
				'status' => 'normal',
				'status_label' => 'Normal',
				'status_badge' => 'badge-success',
				'demand_trend' => '+2 %',
				'elasticity' => -1.0,
				'risk_level' => 'Bajo',
				'stockout_est' => '15/11 ~12:00',
				'next_reception' => '11/11 programada',
				'lifecycle' => 'Estable',
				'abc' => 'C',
				'xyz' => 'X',
				'quadrant' => 'high_rot_high_margin',
				'surtido_action' => 'mantener',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '32.0 %', 'sales' => 10, 'coverage' => '8.5 d', 'coverage_state' => 'normal'],
					['branch' => 'Norte', 'margin' => '31.2 %', 'sales' => 7, 'coverage' => '9.2 d', 'coverage_state' => 'normal'],
					['branch' => 'Sur', 'margin' => '31.0 %', 'sales' => 4, 'coverage' => '9.8 d', 'coverage_state' => 'normal']
				]
			],
			[
				'id' => 8,
				'name' => 'Pechuga de pollo bandeja',
				'sku' => 'CAR-4102',
				'upc' => '7501055341020',
				'dept_code' => '93',
				'department' => '93 Carnes',
				'category' => 'Aves',
				'subcategory' => 'Pollo fresco',
				'brand' => 'Granja Dorada',
				'supplier' => 'Avícola Santa Rosa',
				'initial' => 'P',
				'avatar_bg' => '#fce7f3',
				'avatar_color' => '#be185d',
				'price' => 8.49,
				'cost' => 6.09,
				'unit_margin' => 2.40,
				'margin_pct' => 28.3,
				'stock' => 275,
				'sales_per_day' => 43,
				'coverage_days' => 6.4,
				'status' => 'normal',
				'status_label' => 'Normal',
				'status_badge' => 'badge-success',
				'demand_trend' => '+5 %',
				'elasticity' => -0.8,
				'risk_level' => 'Bajo',
				'stockout_est' => '13/11 ~16:00',
				'next_reception' => '09/11 programada',
				'lifecycle' => 'Estable',
				'abc' => 'A',
				'xyz' => 'X',
				'quadrant' => 'high_rot_high_margin',
				'surtido_action' => 'mantener',
				'branch_comparison' => [
					['branch' => 'Centro', 'margin' => '28.8 %', 'sales' => 22, 'coverage' => '6.0 d', 'coverage_state' => 'normal'],
					['branch' => 'Norte', 'margin' => '28.0 %', 'sales' => 13, 'coverage' => '6.6 d', 'coverage_state' => 'normal'],
					['branch' => 'Sur', 'margin' => '27.9 %', 'sales' => 8, 'coverage' => '7.0 d', 'coverage_state' => 'normal']
				]
			]
		];

		// 4. Matriz ABC-XYZ (9 Celdas exactas de la imagen)
		$abcXyz = [
			'matrix' => [
				'A' => [
					'X' => ['count' => 62, 'code' => 'AX', 'bg' => '#dcfce7', 'color' => '#166534'],
					'Y' => ['count' => 41, 'code' => 'AY', 'bg' => '#ecfccb', 'color' => '#3f6212'],
					'Z' => ['count' => 12, 'code' => 'AZ', 'bg' => '#fef3c7', 'color' => '#92400e']
				],
				'B' => [
					'X' => ['count' => 118, 'code' => 'BX', 'bg' => '#dcfce7', 'color' => '#166534'],
					'Y' => ['count' => 134, 'code' => 'BY', 'bg' => '#fefce8', 'color' => '#713f12'],
					'Z' => ['count' => 56, 'code' => 'BZ', 'bg' => '#fef3c7', 'color' => '#92400e']
				],
				'C' => [
					'X' => ['count' => 215, 'code' => 'CX', 'bg' => '#f0fdf4', 'color' => '#166534'],
					'Y' => ['count' => 298, 'code' => 'CY', 'bg' => '#fffbeb', 'color' => '#78350f'],
					'Z' => ['count' => 312, 'code' => 'CZ', 'bg' => '#fee2e2', 'color' => '#991b1b']
				]
			],
			'total' => 1248,
			'footnote' => 'ABC por contribución (margen bruto, configurable) · XYZ por variabilidad de demanda con 12 semanas de historial. Total: 1,248 SKU.'
		];

		// 5. Rotación frente a Cobertura (Burbujas calibradas)
		$bubblePoints = [
			['name' => 'Helado vainilla', 'x' => 7, 'y' => 87, 'r' => 12, 'color' => '#f59e0b', 'risk' => 'Riesgo medio', 'prod_id' => 2],
			['name' => 'Filete tilapia', 'x' => 6, 'y' => 10.7, 'r' => 10, 'color' => '#f59e0b', 'risk' => 'Riesgo medio', 'prod_id' => 3],
			['name' => 'Croissant', 'x' => 74, 'y' => 0.5, 'r' => 15, 'color' => '#ef4444', 'risk' => 'Riesgo alto', 'prod_id' => 5],
			['name' => 'Leche entera', 'x' => 96, 'y' => 1.5, 'r' => 22, 'color' => '#ef4444', 'risk' => 'Riesgo alto', 'prod_id' => 1],
			['name' => 'Lomo res', 'x' => 31, 'y' => 6.8, 'r' => 14, 'color' => '#f59e0b', 'risk' => 'Riesgo medio', 'prod_id' => 4],
			['name' => 'Yogurt griego', 'x' => 28, 'y' => 11.4, 'r' => 13, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 6],
			['name' => 'Pizza congelada', 'x' => 21, 'y' => 9.0, 'r' => 12, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 7],
			['name' => 'Pechuga pollo', 'x' => 43, 'y' => 6.4, 'r' => 16, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 8],
			// Puntos adicionales de fondo para reproducir fielmente la dispersión de la foto
			['name' => 'Mantequilla 250g', 'x' => 18, 'y' => 48, 'r' => 9, 'color' => '#f87171', 'risk' => 'Riesgo alto', 'prod_id' => 1],
			['name' => 'Crema de leche', 'x' => 22, 'y' => 52, 'r' => 11, 'color' => '#f87171', 'risk' => 'Riesgo alto', 'prod_id' => 1],
			['name' => 'Queso fresco', 'x' => 27, 'y' => 42, 'r' => 13, 'color' => '#f87171', 'risk' => 'Riesgo alto', 'prod_id' => 1],
			['name' => 'Pescado seco', 'x' => 12, 'y' => 72, 'r' => 8, 'color' => '#fbbf24', 'risk' => 'Riesgo medio', 'prod_id' => 3],
			['name' => 'Pan molde blanco', 'x' => 62, 'y' => 2.2, 'r' => 14, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 5],
			['name' => 'Pan integral', 'x' => 48, 'y' => 3.1, 'r' => 11, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 5],
			['name' => 'Carne molida', 'x' => 55, 'y' => 5.5, 'r' => 15, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 4],
			['name' => 'Chuleta cerdo', 'x' => 38, 'y' => 8.2, 'r' => 10, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 4],
			['name' => 'Hamburguesa res', 'x' => 78, 'y' => 4.8, 'r' => 12, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 4],
			['name' => 'Nuggets pollo', 'x' => 68, 'y' => 3.9, 'r' => 13, 'color' => '#10b981', 'risk' => 'Equilibrado', 'prod_id' => 8],
			['name' => 'Verduras mixtas', 'x' => 82, 'y' => 36, 'r' => 11, 'color' => '#fbbf24', 'risk' => 'Riesgo medio', 'prod_id' => 2],
			['name' => 'Papas congeladas', 'x' => 72, 'y' => 42, 'r' => 14, 'color' => '#fbbf24', 'risk' => 'Riesgo medio', 'prod_id' => 2]
		];

		// 6. Matriz Margen x Rotación (4 Cuadrantes exactos)
		$marginRotationMatrix = [
			'high_rot_high_margin' => [
				'count' => 1060,
				'label' => 'Alta rotación · Alto margen',
				'sub' => 'Núcleo del surtido: proteger disponibilidad',
				'class' => 'mr-quad-emerald',
				'val_color' => '#10b981'
			],
			'high_rot_low_margin' => [
				'count' => 51,
				'label' => 'Alta rotación · Bajo margen',
				'sub' => 'Revisar costo y precio. Ej.: Lomo de res premium',
				'class' => 'mr-quad-amber',
				'val_color' => '#f59e0b'
			],
			'low_rot_high_margin' => [
				'count' => 92,
				'label' => 'Baja rotación · Alto margen',
				'sub' => 'Evaluar exposición o promoción. Ej.: Helado vainilla',
				'class' => 'mr-quad-neutral',
				'val_color' => '#0f172a'
			],
			'low_rot_low_margin' => [
				'count' => 45,
				'label' => 'Baja rotación · Bajo margen',
				'sub' => 'Candidatos a revisión de surtido',
				'class' => 'mr-quad-rose',
				'val_color' => '#ef4444'
			]
		];

		// 7. Optimización del Surtido (Barras horizontales exactas)
		$assortmentOptimization = [
			'bars' => [
				['label' => 'Mantener', 'count' => 702, 'pct' => 56.2, 'color' => '#10b981', 'filter' => 'mantener'],
				['label' => 'Potenciar', 'count' => 164, 'pct' => 13.1, 'color' => '#059669', 'filter' => 'potenciar'],
				['label' => 'Revisar', 'count' => 238, 'pct' => 19.1, 'color' => '#f59e0b', 'filter' => 'revisar'],
				['label' => 'Reducir exposición', 'count' => 97, 'pct' => 7.8, 'color' => '#d97706', 'filter' => 'reducir'],
				['label' => 'Evaluar descontinuación', 'count' => 47, 'pct' => 3.8, 'color' => '#ef4444', 'filter' => 'descontinuar']
			],
			'footnote' => 'También detecta SKU duplicados o muy similares (13 pares), demanda creciente (41 SKU) y baja disponibilidad recurrente (23 SKU).'
		];

		// 8. Disponibilidad y Venta Potencial Perdida
		$stockoutLostSales = [
			'rows' => [
				[
					'product' => 'Croissant mantequilla',
					'prod_id' => 5,
					'days_out' => 3,
					'est_demand' => '74 u/d',
					'lost_units' => 222,
					'lost_margin' => '≈ $114',
					'stores' => 4,
					'priority' => 'Alta',
					'priority_class' => 'pill-alta'
				],
				[
					'product' => 'Leche deslactosada 1 L',
					'prod_id' => 1,
					'days_out' => 2,
					'est_demand' => '61 u/d',
					'lost_units' => 122,
					'lost_margin' => '≈ $56',
					'stores' => 3,
					'priority' => 'Alta',
					'priority_class' => 'pill-alta'
				],
				[
					'product' => 'Pan de caja integral',
					'prod_id' => 5,
					'days_out' => 2,
					'est_demand' => '40 u/d',
					'lost_units' => 80,
					'lost_margin' => '≈ $56',
					'stores' => 3,
					'priority' => 'Media',
					'priority_class' => 'pill-media'
				],
				[
					'product' => 'Filete de salmón',
					'prod_id' => 3,
					'days_out' => 1,
					'est_demand' => '18 u/d',
					'lost_units' => 18,
					'lost_margin' => '≈ $70',
					'stores' => 2,
					'priority' => 'Media',
					'priority_class' => 'pill-media'
				]
			],
			'footnote' => 'La acción de reposición se ejecuta en Inventario y Almacenes.'
		];

		// 9. Riesgo FEFO en Perecederos
		$fefoRisks = [
			'rows' => [
				[
					'product' => 'Filete de tilapia',
					'prod_id' => 3,
					'shelf_life' => 3,
					'coverage' => 10.7,
					'label' => '3 d vida · 10.7 d cobertura',
					'exposed_val' => '$1,240',
					'has_risk' => true,
					'risk_class' => 'text-danger font-weight-bold'
				],
				[
					'product' => 'Yogurt griego 500 g',
					'prod_id' => 6,
					'shelf_life' => 9,
					'coverage' => 11.4,
					'label' => '9 d vida · 11.4 d cobertura',
					'exposed_val' => '$880',
					'has_risk' => true,
					'risk_class' => 'text-danger font-weight-bold'
				],
				[
					'product' => 'Pechuga de pollo',
					'prod_id' => 8,
					'shelf_life' => 6,
					'coverage' => 6.4,
					'label' => '6 d vida · 6.4 d cobertura',
					'exposed_val' => '$540',
					'has_risk' => true,
					'risk_class' => 'text-danger font-weight-bold'
				],
				[
					'product' => 'Pizza congelada',
					'prod_id' => 7,
					'shelf_life' => 180,
					'coverage' => 9.0,
					'label' => '180 d vida · 9 d cobertura',
					'exposed_val' => 'Sin riesgo',
					'has_risk' => false,
					'risk_class' => 'badge badge-success'
				]
			],
			'footnote' => 'Valor expuesto: ≈ $2,640 · la cobertura supera la vida útil en 3 SKU.'
		];

		// 10. Acciones Prioritarias por Producto
		$priorityActions = [
			[
				'id' => 1,
				'product' => 'Croissant mantequilla',
				'prod_id' => 5,
				'department' => 'Bakery',
				'issue' => 'Agotado en horario de alta demanda',
				'impact' => '≈ $2,100',
				'impact_num' => 2100,
				'action' => 'Revisar frecuencia de producción',
				'priority' => 'Alta',
				'priority_class' => 'pill-alta',
				'owner' => 'Jefe de Bakery',
				'status' => 'En validación',
				'status_badge' => 'badge-outline-teal'
			],
			[
				'id' => 2,
				'product' => 'Leche entera 1 L',
				'prod_id' => 1,
				'department' => 'Dairy',
				'issue' => 'Alta demanda y baja cobertura',
				'impact' => '≈ $2,100',
				'impact_num' => 2100,
				'action' => 'Revisar reposición',
				'priority' => 'Alta',
				'priority_class' => 'pill-alta',
				'owner' => 'Planeación y Compras',
				'status' => 'Pendiente',
				'status_badge' => 'badge-outline-gray'
			],
			[
				'id' => 3,
				'product' => 'Lomo de res premium',
				'prod_id' => 4,
				'department' => 'Carnes',
				'issue' => 'Margen 11.8 % vs. objetivo 24 %',
				'impact' => '≈ $1,850',
				'impact_num' => 1850,
				'action' => 'Revisar costo y precio',
				'priority' => 'Alta',
				'priority_class' => 'pill-alta',
				'owner' => 'Compras',
				'status' => 'Pendiente',
				'status_badge' => 'badge-outline-gray'
			],
			[
				'id' => 4,
				'product' => 'Helado vainilla 1.5 L',
				'prod_id' => 2,
				'department' => 'Frozen',
				'issue' => 'Rotación lenta (87 d de cobertura)',
				'impact' => '≈ $1,300',
				'impact_num' => 1300,
				'action' => 'Revisar surtido y exposición',
				'priority' => 'Media',
				'priority_class' => 'pill-media',
				'owner' => 'Categoría',
				'status' => 'En análisis',
				'status_badge' => 'badge-outline-amber'
			],
			[
				'id' => 5,
				'product' => 'Filete de tilapia 500 g',
				'prod_id' => 3,
				'department' => 'Pescadería',
				'issue' => 'Vida útil corta y baja salida',
				'impact' => '≈ $1,240',
				'impact_num' => 1240,
				'action' => 'Revisar rotación FEFO',
				'priority' => 'Alta',
				'priority_class' => 'pill-alta',
				'owner' => 'Jefe de departamento',
				'status' => 'En ejecución',
				'status_badge' => 'badge-outline-green'
			]
		];

		// 11. Simulador Comercial Default (Leche entera 1 L)
		$simulator = [
			'product_id' => 1,
			'product_name' => 'Leche entera 1 L',
			'base_price' => 1.89,
			'base_cost' => 1.47,
			'base_units_monthly' => 2880,
			'elasticity' => -0.8,
			'current_contribution' => 1210,
			'sim_price_delta_pct' => 3.2,
			'sim_price' => 1.95,
			'sim_demand_delta_pct' => -2.6,
			'simulated_contribution' => 1347,
			'difference' => 137,
			'assumptions' => 'Supuestos: elasticidad –0.8 estimada, costo sin cambio. Riesgos: producto de canasta básica, sensibilidad al precio. Confianza: baja.'
		];

		return [
			'filters' => [
				'date_range' => '08/10 – 06/11/2026',
				'branches' => $branches,
				'departments' => $departments,
				'categories' => $categories,
				'subcategories' => $subcategories,
				'brands' => $brands,
				'suppliers' => $suppliers
			],
			'kpis' => $kpis,
			'products' => $products,
			'selected_product' => $products[0],
			'abc_xyz' => $abcXyz,
			'bubble_points' => $bubblePoints,
			'margin_rotation_matrix' => $marginRotationMatrix,
			'assortment_optimization' => $assortmentOptimization,
			'stockout_lost_sales' => $stockoutLostSales,
			'fefo_risks' => $fefoRisks,
			'priority_actions' => $priorityActions,
			'simulator' => $simulator
		];
	}
}

