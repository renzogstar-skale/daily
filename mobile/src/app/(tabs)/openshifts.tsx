import React from 'react';
import { Alert, Text, TextInput, View } from 'react-native';
import { api, OpenShiftsResp } from '@/lib/api';
import { useSession } from '@/lib/session';
import { Button, C, Card, dayLabel, Empty, ErrorBox, hhmm, Loading, Muted, s, Screen, Section, Tag, useLoad } from '@/components/ui';

const ST: Record<string, [string, 'warn' | 'ok' | 'danger' | 'muted']> = { pending: ['Pendiente', 'warn'], approved: ['Asignado', 'ok'], rejected: ['No asignado', 'danger'], withdrawn: ['Retirado', 'muted'] };

export default function OpenShifts() {
  const { me } = useSession();
  const q = useLoad(() => api.get<OpenShiftsResp>('/m/open-shifts'));
  const [form, setForm] = React.useState(false);
  const act = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      await fn();
      if (ok) Alert.alert(ok);
      q.reload();
    } catch (e) {
      Alert.alert('No se pudo', e instanceof Error ? e.message : 'Error');
    }
  };
  if (q.loading && !q.data) return <Loading />;
  if (q.error) return <Screen><ErrorBox text={q.error} onRetry={q.reload} /></Screen>;
  const d = q.data!;
  return (
    <Screen refreshing={q.loading} onRefresh={q.reload}>
      {d.linked && (
        <>
          <Section>Disponibles en su sucursal</Section>
          {d.open.length === 0 ? <Empty text="No hay turnos abiertos ahora" /> : d.open.map((o) => (
            <Card key={o.id}>
              <Text style={{ fontSize: 17, fontWeight: '700', color: C.text }}>{dayLabel(o.request_date)} {hhmm(o.start_time)}–{hhmm(o.end_time)}</Text>
              <Muted>{[o.role, o.department, o.reason].filter(Boolean).join(' · ')} · {o.remaining} cupo(s)</Muted>
              {o.blocking.length > 0 && <Text style={{ color: C.danger, marginTop: 4 }}>{o.blocking.join('; ')}</Text>}
              {o.blocking.length === 0 && o.warnings.length > 0 && <Text style={{ color: C.warn, marginTop: 4 }}>{o.warnings.join('; ')}</Text>}
              {o.my_status ? <Tag label={ST[o.my_status][0]} tone={ST[o.my_status][1]} /> : o.blocking.length === 0 && (
                <Button label={o.auto_approve ? 'Tomar turno' : 'Solicitar turno'} onPress={() => act(async () => {
                  const r = await api.post<{ status: string }>('/m/open-shifts/' + o.id + '/claim');
                  Alert.alert(r.status === 'approved' ? 'Turno asignado: confírmelo en Mi horario' : 'Solicitud enviada a su gerente');
                }, '')} />
              )}
            </Card>
          ))}
          {d.mine.length > 0 && <Section>Mis solicitudes</Section>}
          {d.mine.map((c) => (
            <Card key={c.id}>
              <Text style={{ fontWeight: '700', color: C.text }}>{dayLabel(c.request_date)} {hhmm(c.start_time)}–{hhmm(c.end_time)}</Text>
              <Tag label={ST[c.status][0]} tone={ST[c.status][1]} />
              {c.decision_notes ? <Muted>{c.decision_notes}</Muted> : null}
              {c.status === 'pending' && <Button small tone="ghost" label="Retirar solicitud" onPress={() => act(() => api.post('/m/open-shift-claims/' + c.id + '/withdraw'), '')} />}
            </Card>
          ))}
        </>
      )}
      {!d.linked && !d.can_manage && <Empty text="Su usuario no está vinculado a una ficha de empleado." />}
      {d.can_manage && d.manager && (
        <>
          <Section>Gestión · {d.manager.pending} por aprobar</Section>
          <Button label={form ? 'Cerrar formulario' : 'Publicar turno abierto'} tone={form ? 'ghost' : 'primary'} onPress={() => setForm(!form)} />
          {form && <NewOpenShift branches={me?.branches ?? []} onDone={() => { setForm(false); q.reload(); }} />}
          {d.manager.requests.length === 0 && <Empty text="Sin turnos abiertos publicados" />}
          {d.manager.requests.map((r) => (
            <Card key={r.id}>
              <Text style={{ fontWeight: '700', color: C.text }}>{dayLabel(r.request_date)} {hhmm(r.start_time)}–{hhmm(r.end_time)} · {r.branch}</Text>
              <Muted>{r.filled}/{r.staff_needed} cubiertos{r.auto_approve ? ' · aprobación automática' : ''}</Muted>
              {r.claims.map((c) => (
                <View key={c.id} style={{ marginTop: 8, borderTopWidth: 1, borderTopColor: C.line, paddingTop: 8 }}>
                  <Text style={{ color: C.text }}>{c.employee}{c.position ? ' · ' + c.position : ''}</Text>
                  <View style={{ flexDirection: 'row', gap: 8 }}>
                    <View style={{ flex: 1 }}><Button small label="Aprobar" onPress={() => act(() => api.post('/m/open-shift-claims/' + c.id + '/decide', { approve: 1 }), 'Turno asignado y publicado')} /></View>
                    <View style={{ flex: 1 }}><Button small tone="ghost" label="Rechazar" onPress={() => act(() => api.post('/m/open-shift-claims/' + c.id + '/decide', { approve: 0, notes: 'Rechazado por el gerente' }), 'Solicitud rechazada')} /></View>
                  </View>
                </View>
              ))}
            </Card>
          ))}
        </>
      )}
    </Screen>
  );
}

function NewOpenShift({ branches, onDone }: { branches: { id: number; name: string }[]; onDone: () => void }) {
  const tomorrow = new Date(Date.now() + 86400000);
  const [v, setV] = React.useState({
    branch_id: String(branches[0]?.id ?? ''), request_date: tomorrow.toISOString().slice(0, 10), start_time: '08:00', end_time: '14:00', staff_needed: '1', role: '', reason: '', auto: false,
  });
  const set = (k: keyof typeof v) => (t: string) => setV({ ...v, [k]: t });
  return (
    <Card>
      <Text style={s.label}>Sucursal (id)</Text>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 10 }}>
        {branches.map((b) => <Text key={b.id} onPress={() => set('branch_id')(String(b.id))} accessibilityRole="button" style={[{ borderWidth: 1, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6, borderColor: C.line, color: C.text }, String(b.id) === v.branch_id && { borderColor: C.primary, color: C.primaryDark, fontWeight: '700' }]}>{b.name}</Text>)}
      </View>
      <Text style={s.label}>Fecha (AAAA-MM-DD)</Text>
      <TextInput style={s.input} value={v.request_date} onChangeText={set('request_date')} accessibilityLabel="Fecha" />
      <View style={{ flexDirection: 'row', gap: 8 }}>
        <View style={{ flex: 1 }}><Text style={s.label}>Inicio</Text><TextInput style={s.input} value={v.start_time} onChangeText={set('start_time')} accessibilityLabel="Hora de inicio" /></View>
        <View style={{ flex: 1 }}><Text style={s.label}>Fin</Text><TextInput style={s.input} value={v.end_time} onChangeText={set('end_time')} accessibilityLabel="Hora de fin" /></View>
        <View style={{ flex: 1 }}><Text style={s.label}>Personas</Text><TextInput style={s.input} value={v.staff_needed} onChangeText={set('staff_needed')} keyboardType="number-pad" accessibilityLabel="Personas" /></View>
      </View>
      <Text style={s.label}>Puesto (opcional)</Text>
      <TextInput style={s.input} value={v.role} onChangeText={set('role')} />
      <Text style={s.label}>Motivo</Text>
      <TextInput style={s.input} value={v.reason} onChangeText={set('reason')} placeholder="Ej. refuerzo por recepción" />
      <Text onPress={() => setV({ ...v, auto: !v.auto })} accessibilityRole="checkbox" accessibilityState={{ checked: v.auto }} style={{ color: C.text, marginBottom: 6 }}>{v.auto ? '☑' : '☐'} Asignar al primero que cumpla las reglas</Text>
      <Button label="Publicar y avisar" onPress={async () => {
        try {
          const r = await api.post<{ notified_users: number }>('/m/open-shifts', { ...v, branch_id: Number(v.branch_id), staff_needed: Number(v.staff_needed), auto_approve: v.auto ? 1 : 0 });
          Alert.alert('Turno publicado', `Avisados ${r.notified_users} empleados`);
          onDone();
        } catch (e) {
          Alert.alert('No se publicó', e instanceof Error ? e.message : 'Error');
        }
      }} />
    </Card>
  );
}
