		<div class="card border-0 shadow-xs bg-white mb-3 dairy-bordered">
			<div class="card-body p-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<div>
						<h5 class="font-weight-bold text-dark mb-1">
							<i class="fa-solid fa-hand-holding-dollar text-success mr-2"></i>Recuperación de Pérdida Financiera
						</h5>
						<p class="text-muted mb-0 dairy-fs-base">
							Monto total recuperado en el período: <strong>${{ number_format($photoKpis['recovered_amount'], 2) }}</strong> (Tasa de Recuperación: <strong>{{ $photoKpis['recovery_rate'] }}%</strong>).
						</p>
					</div>
					<button class="btn btn-sm btn-success" onclick="dairyNewClaimDialog();">
						<i class="fa-solid fa-plus mr-1"></i>Generar Reclamo a Proveedor
					</button>
				</div>
				<div class="table-responsive">
					<table class="table table-hover dairy-fs-sm">
						<thead class="dairy-bg-base">
							<tr>
								<th>Nº RECLAMO</th>
								<th>PROVEEDOR</th>
								<th>PRODUCTO</th>
								<th>VALOR RECLAMADO</th>
								<th>VALOR RECUPERADO</th>
								<th>DOC. RESPALDO</th>
								<th>ESTADO</th>
							</tr>
						</thead>
						<tbody>
							@forelse($supplierReturns as $ret)
							<tr>
								<td class="font-mono font-weight-bold">{{ $ret['case_number'] }}</td>
								<td class="font-weight-500">{{ $ret['supplier_name'] }}</td>
								<td>{{ $ret['product_name'] }}</td>
								<td>${{ number_format(floatval($ret['credit_requested_amount']), 2) }}</td>
								<td class="text-success font-weight-bold">${{ number_format(floatval($ret['credit_recovered_amount']), 2) }}</td>
								<td>
									@if($ret['status'] == 'pending_credit')
									<button class="btn btn-xs btn-outline-success" onclick='dairyRecordCreditDialog({{ $ret['id'] }}, @json($ret['case_number']), {{ floatval($ret['credit_requested_amount']) }});'>Registrar NC</button>
									@else
									<span class="badge badge-light border">{{ $ret['resolved_timestamp'] ? substr($ret['resolved_timestamp'], 0, 10) : '—' }}</span>
									@endif
									<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/document/supplier_return/' . $ret['id']) }}" title="Documento imprimible"><i class="fa-solid fa-print"></i></a>
								</td>
								<td><span class="badge badge-{{ $ret['status'] == 'credit_recovered' ? 'success' : 'warning' }}">{{ ['credit_recovered' => 'Recuperado', 'pending_credit' => 'Pendiente NC'][$ret['status']] ?? $ret['status'] }}</span></td>
							</tr>
							@empty
							<tr>
								<td colspan="7" class="text-center py-4 text-muted">Sin reclamos registrados en este período.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
