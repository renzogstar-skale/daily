import React from 'react';
import { Alert, Text, TextInput, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { api, opId, OpResult, ScanResult } from '@/lib/api';
import { useSession } from '@/lib/session';
import { Button, C, Card, ErrorBox, Muted, s, Screen, Section, Tag } from '@/components/ui';

type Mode = 'receive' | 'count' | 'waste';
const LABEL: Record<Mode, string> = { receive: 'Recibir mercancía', count: 'Conteo físico', waste: 'Registrar merma' };

// Escanear un UPC/EAN y operar sobre el producto con las mismas reglas de la web (recepción, conteo ciego, merma)
export default function Operate() {
  const { me } = useSession();
  const can = (p: string) => !!me?.permissions?.includes(p) || !!me?.permissions?.includes('ADMIN');
  const [perm, requestPerm] = useCameraPermissions();
  const [scanning, setScanning] = React.useState(false);
  const [code, setCode] = React.useState('');
  const [res, setRes] = React.useState<ScanResult | null>(null);
  const [error, setError] = React.useState<string | null>(null);
  const [mode, setMode] = React.useState<Mode | null>(null);
  const [qty, setQty] = React.useState('');
  const [reasons, setReasons] = React.useState<{ id: number; name: string }[]>([]);
  const [reason, setReason] = React.useState<number | null>(null);
  const [busy, setBusy] = React.useState(false);
  // Un identificador por intento: un reintento por mala señal no duplica el movimiento
  const op = React.useRef<string>('');
  const last = React.useRef({ code: '', t: 0 });

  const lookup = async (c: string) => {
    const value = c.trim();
    if (!value) return;
    if (value === last.current.code && Date.now() - last.current.t < 2500) return;
    last.current = { code: value, t: Date.now() };
    setScanning(false);
    setError(null);
    setMode(null);
    try {
      const r = await api.get<ScanResult>('/m/scan?code=' + encodeURIComponent(value) + (me?.employee ? '&branch_id=' + me.employee.branch_id : ''));
      setRes(r);
      if (r.kind !== 'product') setError(r.message || 'El código no corresponde a un producto');
    } catch (e) {
      setRes(null);
      setError(e instanceof Error ? e.message : 'No se pudo consultar el código');
    }
  };

  const start = async (m: Mode) => {
    setMode(m);
    setQty('');
    op.current = opId(m);
    if (m === 'waste' && reasons.length === 0) {
      try { setReasons((await api.get<{ reasons: { id: number; name: string }[] }>('/m/ops/waste-reasons')).reasons); } catch { /* el envío informará el error */ }
    }
  };

  const submit = async () => {
    if (!res?.product || !mode) return;
    const n = parseFloat(qty.replace(',', '.'));
    if (!(n >= 0) || (mode !== 'count' && n <= 0)) {
      Alert.alert('Cantidad', mode === 'count' ? 'Ingrese lo contado (cero o más)' : 'Ingrese una cantidad mayor que cero');
      return;
    }
    if (mode === 'waste' && !reason) {
      Alert.alert('Motivo', 'Elija el motivo de la merma');
      return;
    }
    setBusy(true);
    try {
      const body: Record<string, unknown> = { product_id: res.product.id, operation_id: op.current, branch_id: me?.employee?.branch_id };
      let r: OpResult;
      if (mode === 'receive') r = await api.post<OpResult>('/m/ops/receive', { ...body, quantity: n });
      else if (mode === 'count') r = await api.post<OpResult>('/m/ops/count', { ...body, counted: n });
      else r = await api.post<OpResult>('/m/ops/waste', { ...body, quantity: n, reason_id: reason, stock_id: res.entries?.[0]?.stock_id });
      Alert.alert(LABEL[mode], r.message || (r.movement_code ? 'Registrado ' + r.movement_code : 'Registrado'));
      setMode(null);
      last.current = { code: '', t: 0 };
      await lookup(code || res.parsed?.code || '');
    } catch (e) {
      Alert.alert('No se registró', e instanceof Error ? e.message : 'Error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <Section>Escanear producto</Section>
      <Card>
        {scanning ? (
          perm?.granted ? (
            <View style={{ height: 260, borderRadius: 12, overflow: 'hidden' }}>
              <CameraView style={{ flex: 1 }} facing="back" barcodeScannerSettings={{ barcodeTypes: ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'qr'] }}
                onBarcodeScanned={({ data }) => { setCode(data); lookup(data); }} />
            </View>
          ) : (
            <View>
              <Muted>DAILY necesita la cámara para leer códigos de barras.</Muted>
              <Button small label="Permitir cámara" onPress={async () => { await requestPerm(); }} />
            </View>
          )
        ) : (
          <Button label="Abrir escáner" onPress={() => setScanning(true)} />
        )}
        <Text style={s.label}>O escriba el código</Text>
        <TextInput style={s.input} value={code} onChangeText={setCode} placeholder="UPC / EAN / SKU" autoCapitalize="characters" keyboardType="default" onSubmitEditing={() => lookup(code)} accessibilityLabel="Código del producto" />
        <Button small tone="ghost" label="Buscar" onPress={() => lookup(code)} />
      </Card>

      {error && <ErrorBox text={error} />}

      {res?.kind === 'product' && res.product && (
        <Card>
          <Muted>{res.product.group_name || 'Producto'}{res.product.sku ? ' · ' + res.product.sku : ''}</Muted>
          <Text style={{ fontSize: 18, fontWeight: '700', color: C.text }}>{res.product.name}</Text>
          {res.availability ? (
            <View style={{ flexDirection: 'row', gap: 8, marginVertical: 6 }}>
              <Tag label={'Disponible ' + res.availability.available} tone="ok" />
              <Tag label={'Físico ' + res.availability.physical} tone="info" />
            </View>
          ) : <Muted>Su perfil no ve existencias.</Muted>}
          {(res.entries ?? []).slice(0, 3).map((e, i) => (
            <Muted key={e.stock_id + i}>{i === 0 ? '▲ FEFO · ' : ''}{e.location_name || 'Ubicación'}{e.lot_number ? ' · lote ' + e.lot_number : ''}{e.best_before_date && e.best_before_date !== '2999-12-31' ? ' · vence ' + e.best_before_date : ''} · {e.amount} u.</Muted>
          ))}
          {!mode && (
            <View style={{ gap: 6, marginTop: 8 }}>
              {can('STOCK_PURCHASE') && <Button small label="Recibir" onPress={() => start('receive')} />}
              {can('STOCK_INVENTORY') && <Button small label="Contar" onPress={() => start('count')} />}
              {can('WASTE_REPORT') && <Button small tone="ghost" label="Registrar merma" onPress={() => start('waste')} />}
            </View>
          )}
          {mode && (
            <View style={{ marginTop: 8 }}>
              <Text style={{ fontWeight: '700', color: C.text }}>{LABEL[mode]}</Text>
              {mode === 'count' && <Muted>Conteo ciego: escriba lo que ve en la ubicación. Si hay diferencia, queda pendiente de aprobación (no ajusta solo).</Muted>}
              <Text style={s.label}>{mode === 'count' ? 'Unidades contadas' : 'Cantidad'}</Text>
              <TextInput style={s.input} value={qty} onChangeText={setQty} keyboardType="decimal-pad" accessibilityLabel="Cantidad" />
              {mode === 'waste' && reasons.map((r) => (
                <Text key={r.id} accessibilityRole="radio" accessibilityState={{ selected: reason === r.id }} onPress={() => setReason(r.id)}
                  style={{ paddingVertical: 6, color: reason === r.id ? C.primaryDark : C.text, fontWeight: reason === r.id ? '700' : '400' }}>{reason === r.id ? '● ' : '○ '}{r.name}</Text>
              ))}
              <Button label={busy ? 'Enviando…' : 'Confirmar'} disabled={busy} onPress={submit} />
              <Button small tone="ghost" label="Cancelar" onPress={() => setMode(null)} />
            </View>
          )}
        </Card>
      )}
    </Screen>
  );
}
