import React from 'react';
import { Alert, Text, View } from 'react-native';
import { api, ScheduleResp, TeamPerson } from '@/lib/api';
import { clockLocation } from '@/lib/device';
import { useSession } from '@/lib/session';
import { Button, C, Card, dayLabel, ErrorBox, hhmm, Loading, Muted, Screen, Section, Tag, Title, useLoad } from '@/components/ui';

const LABEL: Record<string, string> = {
  off: 'Sin turno ahora', upcoming: 'Su turno empieza pronto', due: 'Es hora de marcar entrada', late: 'Turno iniciado sin marcar entrada',
  on_shift: 'En turno', overtime: 'Turno terminado: falta marcar salida', done: 'Turno de hoy completado',
};

export default function Home() {
  const { me, error: meErr, refresh } = useSession();
  const sched = useLoad(() => api.get<ScheduleResp>('/m/schedule'), [me?.user_id]);
  const team = useLoad(async () => (me?.can_team ? api.get<{ people: TeamPerson[]; coverage: { gaps: unknown[] } }>('/m/team' + (me.employee ? '?branch_id=' + me.employee.branch_id : '')) : null), [me?.user_id]);

  const clock = async (kind: 'in' | 'out') => {
    try {
      const geo = await clockLocation();
      const r = await api.post<{ late_minutes?: number; hours?: number; geo?: { status: string; distance_m: number } }>('/schedule/clock-' + kind, { source: 'mobile', geo });
      const g = r.geo ? (r.geo.status === 'inside' ? ' · en la sucursal' : r.geo.status === 'outside' ? ` · fuera del radio (${Math.round(r.geo.distance_m)} m)` : '') : '';
      Alert.alert(kind === 'in' ? 'Entrada registrada' : 'Salida registrada', (kind === 'in' ? (r.late_minutes ? `${r.late_minutes} min tarde` : 'A tiempo') : `${r.hours ?? 0} h trabajadas`) + g);
      sched.reload();
    } catch (e) {
      Alert.alert('No se registró', e instanceof Error ? e.message : 'Error');
    }
  };
  const confirm = async (id: number) => {
    try {
      await api.post('/m/shifts/' + id + '/confirm');
      sched.reload();
    } catch (e) {
      Alert.alert('No se confirmó', e instanceof Error ? e.message : 'Error');
    }
  };

  if (meErr) return <Screen><ErrorBox text={meErr} onRetry={refresh} /></Screen>;
  if (!me) return <Loading />;
  const st = sched.data?.state;
  const present = team.data?.people.filter((p) => p.status === 'present' || p.clock_in).length ?? 0;
  const absent = team.data?.people.filter((p) => p.status === 'absent').length ?? 0;

  return (
    <Screen refreshing={sched.loading} onRefresh={() => { sched.reload(); team.reload(); }}>
      <Title>Hola, {me.name.split(' ')[0]}</Title>
      <Muted>{me.role_label}{me.employee?.position ? ' · ' + me.employee.position : ''}</Muted>
      <View style={{ height: 12 }} />
      {sched.error && <ErrorBox text={sched.error} onRetry={sched.reload} />}
      {sched.data && !sched.data.linked && me.role === 'employee' && (
        <Card><Text style={{ color: C.text }}>Su usuario aún no está vinculado a una ficha de empleado. Pida a su gerente que lo vincule en Horarios.</Text></Card>
      )}
      {st && (
        <Card>
          <Text style={{ fontWeight: '700', fontSize: 16, color: st.state === 'late' ? C.danger : C.text }}>{LABEL[st.state]}</Text>
          {st.current && <Text style={{ fontSize: 28, fontWeight: '700', marginTop: 6, color: C.text }}>{hhmm(st.current.start_time)} – {hhmm(st.current.end_time)}</Text>}
          {st.open_attendance && <Muted>Entrada marcada a las {String(st.open_attendance.clock_in).slice(11, 16)}</Muted>}
          {!st.current && st.next && (
            <View style={{ marginTop: 6 }}>
              <Muted>Próximo turno: {dayLabel(st.next.shift_date)} {hhmm(st.next.start_time)}–{hhmm(st.next.end_time)}</Muted>
              {st.next.confirmed_at ? <Tag label="Confirmado" tone="ok" /> : <Button small label="Confirmar turno" onPress={() => confirm(st.next!.id)} />}
            </View>
          )}
          {!st.current && !st.next && <Muted>No tiene turnos publicados en los próximos 14 días.</Muted>}
          {['upcoming', 'due', 'late'].includes(st.state) && !st.open_attendance && <Button label="Marcar entrada" tone={st.state === 'late' ? 'danger' : 'primary'} onPress={() => clock('in')} />}
          {st.open_attendance && <Button label="Marcar salida" tone="ghost" onPress={() => clock('out')} />}
          <Muted>Esta semana: {st.week_worked} h{st.contract ? ` de ${st.contract} h` : ''}</Muted>
        </Card>
      )}
      {me.can_team && (
        <>
          <Section>Mi equipo hoy</Section>
          {team.loading ? <Loading /> : team.error ? <ErrorBox text={team.error} onRetry={team.reload} /> : team.data && (
            <Card>
              <Text style={{ fontSize: 16, color: C.text }}>{present} presentes · {absent} ausentes · {team.data.coverage.gaps.length} huecos de cobertura</Text>
              {team.data.people.filter((p) => p.status === 'absent').slice(0, 6).map((p) => (
                <Text key={p.employee_id} style={{ color: C.danger, marginTop: 6 }}>• {p.name} ({p.start}–{p.end}) sin marcar</Text>
              ))}
            </Card>
          )}
        </>
      )}
    </Screen>
  );
}
