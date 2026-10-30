import React from 'react';
import { Tabs } from 'expo-router';
import { ColorValue, Text } from 'react-native';
import { C } from '@/components/ui';

// Íconos de texto (sin dependencias extra): legibles y con etiqueta para lectores de pantalla
const icon = (glyph: string) => ({ color }: { color: ColorValue }) => <Text style={{ color, fontSize: 20 }} accessibilityElementsHidden>{glyph}</Text>;

export default function TabsLayout() {
  return (
    <Tabs screenOptions={{ tabBarActiveTintColor: C.primaryDark, tabBarInactiveTintColor: C.muted, headerTitleStyle: { fontWeight: '700' } }}>
      <Tabs.Screen name="index" options={{ title: 'Inicio', tabBarIcon: icon('⌂') }} />
      <Tabs.Screen name="schedule" options={{ title: 'Mi horario', tabBarIcon: icon('▦') }} />
      <Tabs.Screen name="operate" options={{ title: 'Operar', tabBarIcon: icon('▤') }} />
      <Tabs.Screen name="openshifts" options={{ title: 'Turnos', tabBarIcon: icon('＋') }} />
      <Tabs.Screen name="messages" options={{ title: 'Mensajes', tabBarIcon: icon('✉') }} />
      <Tabs.Screen name="more" options={{ title: 'Más', tabBarIcon: icon('≡') }} />
    </Tabs>
  );
}
