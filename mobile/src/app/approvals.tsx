import React from 'react';
import { Alert, Text, View } from 'react-native';
import { api, Approvals as ApprovalsResp } from '@/lib/api';
import { Button, C, Card, Empty, ErrorBox, Muted, Screen, Section, useLoad, when } from '@/components/ui';

// Aprobaciones según permisos: mermas pendientes y ajustes por conteo (quien registró no puede aprobar lo suyo; lo valida el servidor)
export default function Approvals() {
  const q = useLoad(() => api.get<ApprovalsResp>('/m/approvals'));
  const decide = async (kind: 'waste' | 'count', id: number, approve: boolean) => {
    try {
      const r = await api.post<{ message?: string }>('/m/approvals/' + kind + '/' + id, { approve });
      Alert.alert(approve ? 'Aprobado' : 'Rechazado', r.message || '');
      q.reload();
    } catch (e) {
      Alert.alert('No se pudo', e instanceof Error ? e.message : 'Error');
    }
  };
  if (q.error) return <Screen><ErrorBox text={q.error} onRetry={q.reload} /></Screen>;
  const d = q.data;
  return (
    <Screen refreshing={q.loading} onRefresh={q.reload}>
      <Section>Mermas por aprobar</Section>
      {(d?.waste.length ?? 0) === 0 ? <Empty text="Sin mermas pendientes" /> : d!.waste.map((w) => (
        <Card key={'w' + w.id}>
          <Text style={{ fontWeight: '700', color: C.text }}>{w.product} · {w.amount} u.</Text>
          <Muted>{w.reason} · {w.branch} · ${Number(w.total_cost).toFixed(2)} · {when(w.created_timestamp)}</Muted>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Button small label="Aprobar" onPress={() => decide('waste', w.id, true)} />
            <Button small tone="ghost" label="Rechazar" onPress={() => decide('waste', w.id, false)} />
          </View>
        </Card>
      ))}
      <Section>Ajustes por conteo</Section>
      {(d?.counts.length ?? 0) === 0 ? <Empty text="Sin conteos con diferencia" /> : d!.counts.map((c) => (
        <Card key={'c' + c.id}>
          <Text style={{ fontWeight: '700', color: C.text }}>{c.product}</Text>
          <Muted>{c.branch} · sistema {c.theoretical_stock} → contado {c.counted_stock} ({c.difference_amount > 0 ? '+' : ''}{c.difference_amount} u., ${Number(c.difference_cost).toFixed(2)})</Muted>
          <Button small label="Aprobar ajuste" onPress={() => decide('count', c.id, true)} />
        </Card>
      ))}
    </Screen>
  );
}
