import React from 'react';
import { Stack, router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import * as Notifications from 'expo-notifications';
import { SessionProvider, useSession } from '@/lib/session';
import { C, Loading } from '@/components/ui';

// Tocar una notificación abre la pantalla indicada por el servidor (data.route)
const ROUTES: Record<string, string> = { schedule: '/(tabs)/schedule', 'open-shifts': '/(tabs)/openshifts' };
function openFromNotification(data: Record<string, unknown> | undefined) {
  const route = String(data?.route ?? '');
  if (route.startsWith('messages/')) router.push(('/messages/' + route.split('/')[1]) as never);
  else if (ROUTES[route]) router.push(ROUTES[route] as never);
}

function Gate() {
  const { ready, configured } = useSession();
  React.useEffect(() => {
    const sub = Notifications.addNotificationResponseReceivedListener((r) => openFromNotification(r.notification.request.content.data as Record<string, unknown>));
    return () => sub.remove();
  }, []);
  React.useEffect(() => {
    if (ready && !configured) router.replace('/setup');
  }, [ready, configured]);
  if (!ready) return <Loading />;
  return (
    <Stack screenOptions={{ headerTintColor: C.text, headerStyle: { backgroundColor: '#fff' }, contentStyle: { backgroundColor: C.bg } }}>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="setup" options={{ title: 'Conectar con DAILY' }} />
      <Stack.Screen name="messages/[id]" options={{ title: 'Conversación' }} />
      <Stack.Screen name="messages/new" options={{ title: 'Nuevo mensaje', presentation: 'modal' }} />
      <Stack.Screen name="absence" options={{ title: 'Solicitar ausencia', presentation: 'modal' }} />
      <Stack.Screen name="approvals" options={{ title: 'Aprobaciones' }} />
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <SessionProvider>
      <StatusBar style="dark" />
      <Gate />
    </SessionProvider>
  );
}
