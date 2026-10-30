import React from 'react';
import { Alert, Text, TextInput } from 'react-native';
import { router } from 'expo-router';
import { api, ScheduleResp } from '@/lib/api';
import { Button, C, Card, ErrorBox, Loading, s, Screen, useLoad } from '@/components/ui';

export default function Absence() {
  const q = useLoad(() => api.get<ScheduleResp>('/m/schedule'));
  const today = new Date().toISOString().slice(0, 10);
  const [v, setV] = React.useState({ absence_type: '', start_date: today, end_date: today, notes: '' });
  if (q.loading) return <Loading />;
  if (q.error) return <Screen><ErrorBox text={q.error} onRetry={q.reload} /></Screen>;
  const types = q.data?.data?.absence_types ?? {};
  return (
    <Screen>
      <Card>
        <Text style={s.label}>Tipo</Text>
        {Object.entries(types).map(([k, label]) => (
          <Text key={k} accessibilityRole="radio" accessibilityState={{ selected: v.absence_type === k }} onPress={() => setV({ ...v, absence_type: k })}
            style={{ paddingVertical: 8, fontSize: 16, color: v.absence_type === k ? C.primaryDark : C.text, fontWeight: v.absence_type === k ? '700' : '400' }}>{v.absence_type === k ? '● ' : '○ '}{label}</Text>
        ))}
        <Text style={s.label}>Desde (AAAA-MM-DD)</Text>
        <TextInput style={s.input} value={v.start_date} onChangeText={(t) => setV({ ...v, start_date: t })} accessibilityLabel="Desde" />
        <Text style={s.label}>Hasta (AAAA-MM-DD)</Text>
        <TextInput style={s.input} value={v.end_date} onChangeText={(t) => setV({ ...v, end_date: t })} accessibilityLabel="Hasta" />
        <Text style={s.label}>Notas para su gerente</Text>
        <TextInput style={[s.input, { minHeight: 70 }]} value={v.notes} onChangeText={(t) => setV({ ...v, notes: t })} multiline maxLength={500} />
        <Button label="Enviar solicitud" disabled={!v.absence_type} onPress={async () => {
          try {
            await api.post('/schedule/absences', v);
            Alert.alert('Solicitud enviada', 'Su gerente la aprobará o rechazará.');
            router.back();
          } catch (e) {
            Alert.alert('No se envió', e instanceof Error ? e.message : 'Error');
          }
        }} />
      </Card>
    </Screen>
  );
}
