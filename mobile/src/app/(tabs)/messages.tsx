import React from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api, Thread } from '@/lib/api';
import { Button, C, Card, Empty, ErrorBox, Loading, Muted, Screen, useLoad, when } from '@/components/ui';

const KIND: Record<string, string> = { direct: '👤', group: '👥', announcement: '📢' };

export default function Messages() {
  const q = useLoad(() => api.get<{ threads: Thread[]; unread: number }>('/m/messages'));
  useFocusEffect(React.useCallback(() => { q.reload(); }, [q.reload]));
  if (q.loading && !q.data) return <Loading />;
  if (q.error) return <Screen><ErrorBox text={q.error} onRetry={q.reload} /></Screen>;
  return (
    <Screen refreshing={q.loading} onRefresh={q.reload}>
      <Button label="Nuevo mensaje" onPress={() => router.push('/messages/new')} />
      <View style={{ height: 12 }} />
      {q.data!.threads.length === 0 ? <Empty text="Sin conversaciones" /> : q.data!.threads.map((t) => (
        <Pressable key={t.id} accessibilityRole="button" accessibilityLabel={t.title + (t.unread ? `, ${t.unread} sin leer` : '')} onPress={() => router.push(('/messages/' + t.id) as never)}>
          <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
            <Text style={{ fontSize: 22 }} accessibilityElementsHidden>{KIND[t.kind]}</Text>
            <View style={{ flex: 1 }}>
              <Text style={{ fontSize: 16, fontWeight: t.unread ? '700' : '500', color: C.text }} numberOfLines={1}>{t.title}</Text>
              <Muted>{String(t.last_body ?? 'Sin mensajes').slice(0, 70)}</Muted>
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Muted>{when(t.last_message_at ?? t.created_at)}</Muted>
              {t.unread > 0 && <Text style={{ backgroundColor: C.primary, color: '#fff', borderRadius: 10, paddingHorizontal: 7, marginTop: 4, fontWeight: '700', overflow: 'hidden' }}>{t.unread}</Text>}
            </View>
          </Card>
        </Pressable>
      ))}
    </Screen>
  );
}
