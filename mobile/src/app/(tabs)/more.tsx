import React from 'react';
import { Alert, Text, View } from 'react-native';
import { router } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api, Notice, saveConfig } from '@/lib/api';
import { registerPush, unregisterPush } from '@/lib/device';
import { useSession } from '@/lib/session';
import { Button, C, Card, Empty, ErrorBox, Muted, Screen, Section, Tag, useLoad, when } from '@/components/ui';

type Task = { type: string; id: number; title: string; detail?: string; status?: string; due?: string; overdue?: boolean };

export default function More() {
  const { me, refresh } = useSession();
  const notes = useLoad(() => api.get<{ items: Notice[]; unread: number }>('/m/notifications'));
  const tasks = useLoad(() => api.get<{ items: Task[] }>('/m/tasks?scope=mine'));
  const devices = useLoad(() => api.get<{ devices: { id: number; device_name: string; last_seen_at: string }[] }>('/m/push/devices'));
  const [push, setPush] = React.useState<string | null>(null);

  const enablePush = async () => {
    const r = await registerPush();
    if (r.ok) {
      await AsyncStorage.setItem('daily.pushToken', r.token);
      setPush('Notificaciones activadas en este teléfono');
      devices.reload();
    } else {
      setPush('No activadas: ' + r.reason);
    }
  };
  const logout = async () => {
    const t = await AsyncStorage.getItem('daily.pushToken');
    if (t) await unregisterPush(t);
    await AsyncStorage.removeItem('daily.pushToken');
    await saveConfig(null);
    await refresh();
    router.replace('/setup');
  };

  return (
    <Screen refreshing={notes.loading} onRefresh={() => { notes.reload(); tasks.reload(); devices.reload(); }}>
      <Section>Notificaciones push</Section>
      <Card>
        {devices.data && devices.data.devices.length > 0
          ? <Text style={{ color: C.ok }}>Registrado en {devices.data.devices.length} dispositivo(s): {devices.data.devices.map((d) => d.device_name).join(', ')}</Text>
          : <Muted>Este usuario no tiene dispositivos registrados: los avisos solo se ven dentro de la app.</Muted>}
        <Button small label="Activar en este teléfono" onPress={enablePush} />
        {push && <Muted>{push}</Muted>}
      </Card>

      {me?.can_approve && (
        <Card>
          <Text style={{ fontWeight: '700', color: C.text }}>Aprobaciones</Text>
          <Muted>Mermas y ajustes por conteo pendientes de su decisión.</Muted>
          <Button small label="Abrir aprobaciones" onPress={() => router.push('/approvals')} />
        </Card>
      )}

      <Section>Mis tareas</Section>
      {tasks.error ? <ErrorBox text={tasks.error} onRetry={tasks.reload} /> : (tasks.data?.items.length ?? 0) === 0 ? <Empty text="Sin tareas pendientes" /> : tasks.data!.items.slice(0, 15).map((t) => (
        <Card key={t.type + t.id}>
          <Text style={{ fontWeight: '700', color: C.text }}>{t.title}</Text>
          {t.detail ? <Muted>{t.detail.slice(0, 140)}</Muted> : null}
          <View style={{ flexDirection: 'row', gap: 6 }}>
            {t.status ? <Tag label={t.status} tone="info" /> : null}
            {t.overdue ? <Tag label="Vencida" tone="danger" /> : null}
          </View>
          {t.type === 'task' && <Button small label="Marcar como hecha" onPress={async () => {
            try {
              await api.post('/m/tasks/' + t.id + '/complete', { notes: 'Completada desde la app' });
              tasks.reload();
            } catch (e) {
              Alert.alert('No se pudo', e instanceof Error ? e.message : 'Error');
            }
          }} />}
        </Card>
      ))}

      <Section>Avisos recientes</Section>
      {notes.error ? <ErrorBox text={notes.error} onRetry={notes.reload} /> : (notes.data?.items.length ?? 0) === 0 ? <Empty text="Sin avisos" /> : notes.data!.items.slice(0, 20).map((n) => (
        <Card key={n.key} style={n.unread ? { borderColor: C.primary } : undefined}>
          <Text style={{ fontWeight: n.unread ? '700' : '500', color: C.text }}>{n.title}</Text>
          {n.detail ? <Muted>{n.detail}</Muted> : null}
          <Muted>{when(n.at)}</Muted>
        </Card>
      ))}

      <Section>Cuenta</Section>
      <Card>
        <Text style={{ color: C.text }}>{me?.name} · {me?.role_label}</Text>
        <Button small tone="ghost" label="Desconectar este teléfono" onPress={logout} />
      </Card>
    </Screen>
  );
}
