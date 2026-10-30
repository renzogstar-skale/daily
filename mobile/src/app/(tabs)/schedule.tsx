import React from 'react';
import { Alert, Text, View } from 'react-native';
import { router } from 'expo-router';
import { api, ScheduleResp, Shift } from '@/lib/api';
import { Button, C, Card, dayLabel, Empty, ErrorBox, hhmm, Loading, Muted, Screen, Tag, useLoad } from '@/components/ui';

export default function Schedule() {
  const q = useLoad(() => api.get<ScheduleResp>('/m/schedule'));
  const confirm = async (s: Shift) => {
    try {
      await api.post('/m/shifts/' + s.id + '/confirm');
      q.reload();
    } catch (e) {
      Alert.alert('No se confirmó', e instanceof Error ? e.message : 'Error');
    }
  };
  if (q.loading && !q.data) return <Loading />;
  if (q.error) return <Screen><ErrorBox text={q.error} onRetry={q.reload} /></Screen>;
  if (!q.data?.linked || !q.data.data) return <Screen><Empty text="Su usuario no está vinculado a una ficha de empleado. Pida a su gerente que lo vincule." /></Screen>;
  const shifts = q.data.data.shifts;
  const days = Array.from({ length: 14 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() + i);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  });
  const pending = shifts.filter((s) => s.status === 'published' && !s.confirmed_at).length;
  return (
    <Screen refreshing={q.loading} onRefresh={q.reload}>
      <Card>
        <Text style={{ fontSize: 16, color: C.text }}>Esta semana: <Text style={{ fontWeight: '700' }}>{q.data.data.week_worked} h</Text> de {q.data.data.contract} h</Text>
        {pending > 0 && <Text style={{ color: C.warn, marginTop: 4 }}>{pending} turno(s) por confirmar</Text>}
        <Button label="Solicitar ausencia" tone="ghost" small onPress={() => router.push('/absence')} />
      </Card>
      {days.map((d) => {
        const list = shifts.filter((s) => s.shift_date === d);
        return (
          <Card key={d} style={{ paddingVertical: 10 }}>
            <Text style={{ fontWeight: '700', color: C.text }}>{dayLabel(d)}</Text>
            {list.length === 0 ? <Muted>Libre</Muted> : list.map((s) => (
              <View key={s.id} style={{ marginTop: 6 }}>
                <Text style={{ fontSize: 18, fontWeight: '700', color: C.text }}>{hhmm(s.start_time)}–{hhmm(s.end_time)}</Text>
                <Muted>{[s.department, s.role, s.break_minutes ? `descanso ${s.break_minutes} min` : ''].filter(Boolean).join(' · ')}</Muted>
                {s.status === 'published' && (s.confirmed_at ? <Tag label="Confirmado" tone="ok" /> : <Button small label="Confirmar turno" onPress={() => confirm(s)} />)}
              </View>
            ))}
          </Card>
        );
      })}
    </Screen>
  );
}
