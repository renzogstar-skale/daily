// Componentes base con la identidad de DAILY (verde corporativo, tarjetas blancas, botones grandes para el pulgar).
import React from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View, RefreshControl, ViewStyle } from 'react-native';

export const C = {
  primary: '#00C83B', primaryDark: '#00A030', bg: '#F5F6FA', card: '#FFFFFF', text: '#1F2937', muted: '#6B7280',
  line: '#E5E7EB', danger: '#DC2626', warn: '#B45309', ok: '#047857', info: '#0369A1',
};

export function Screen({ children, refreshing, onRefresh }: { children: React.ReactNode; refreshing?: boolean; onRefresh?: () => void }) {
  return (
    <ScrollView style={s.screen} contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={C.primary} /> : undefined}>
      {children}
    </ScrollView>
  );
}

export function Card({ children, style }: { children: React.ReactNode; style?: ViewStyle }) {
  return <View style={[s.card, style]}>{children}</View>;
}

export function Title({ children }: { children: React.ReactNode }) {
  return <Text style={s.title} accessibilityRole="header">{children}</Text>;
}

export function Muted({ children }: { children: React.ReactNode }) {
  return <Text style={s.muted}>{children}</Text>;
}

export function Section({ children }: { children: React.ReactNode }) {
  return <Text style={s.section} accessibilityRole="header">{children}</Text>;
}

type BtnProps = { label: string; onPress: () => void | Promise<void>; tone?: 'primary' | 'ghost' | 'danger'; small?: boolean; disabled?: boolean };

/** Botón con bloqueo mientras la acción corre: un doble toque no envía dos veces */
export function Button({ label, onPress, tone = 'primary', small, disabled }: BtnProps) {
  const [busy, setBusy] = React.useState(false);
  const bg = tone === 'primary' ? C.primary : tone === 'danger' ? C.danger : 'transparent';
  const fg = tone === 'ghost' ? C.text : '#fff';
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} disabled={busy || disabled}
      onPress={async () => { setBusy(true); try { await onPress(); } finally { setBusy(false); } }}
      style={({ pressed }) => [s.btn, small && s.btnSm, { backgroundColor: bg, borderColor: tone === 'ghost' ? C.line : bg, opacity: pressed || busy || disabled ? 0.6 : 1 }]}>
      {busy ? <ActivityIndicator color={fg} /> : <Text style={[s.btnText, small && { fontSize: 14 }, { color: fg }]}>{label}</Text>}
    </Pressable>
  );
}

export function Tag({ label, tone = 'muted' }: { label: string; tone?: 'ok' | 'warn' | 'danger' | 'muted' | 'info' }) {
  const color = { ok: C.ok, warn: C.warn, danger: C.danger, muted: C.muted, info: C.info }[tone];
  return <Text style={[s.tag, { color, borderColor: color }]}>{label}</Text>;
}

export function Empty({ text }: { text: string }) {
  return <Card><Text style={[s.muted, { textAlign: 'center' }]}>{text}</Text></Card>;
}

export function ErrorBox({ text, onRetry }: { text: string; onRetry?: () => void }) {
  return (
    <Card style={{ borderColor: C.danger }}>
      <Text style={{ color: C.danger, fontWeight: '600', marginBottom: onRetry ? 10 : 0 }}>{text}</Text>
      {onRetry && <Button label="Reintentar" tone="ghost" small onPress={onRetry} />}
    </Card>
  );
}

export function Loading() {
  return <View style={{ padding: 40 }}><ActivityIndicator color={C.primary} size="large" /></View>;
}

export const s = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  card: { backgroundColor: C.card, borderRadius: 14, borderWidth: 1, borderColor: C.line, padding: 14, marginBottom: 12 },
  title: { fontSize: 20, fontWeight: '700', color: C.text, marginBottom: 4 },
  section: { fontSize: 13, fontWeight: '700', color: C.muted, textTransform: 'uppercase', marginTop: 8, marginBottom: 8 },
  muted: { fontSize: 14, color: C.muted },
  text: { fontSize: 16, color: C.text },
  btn: { minHeight: 50, borderRadius: 12, borderWidth: 1, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16, marginTop: 8 },
  btnSm: { minHeight: 40, marginTop: 6 },
  btnText: { fontSize: 16, fontWeight: '700' },
  tag: { alignSelf: 'flex-start', fontSize: 12, fontWeight: '700', borderWidth: 1, borderRadius: 8, paddingHorizontal: 8, paddingVertical: 2, marginTop: 4, overflow: 'hidden' },
  input: { borderWidth: 1, borderColor: C.line, borderRadius: 10, padding: 12, fontSize: 16, backgroundColor: '#fff', color: C.text, marginBottom: 10 },
  label: { fontSize: 13, fontWeight: '600', color: C.muted, marginBottom: 4 },
});

export function hhmm(t?: string | null): string {
  return t ? String(t).slice(0, 5) : '';
}

export function dayLabel(d: string): string {
  const date = new Date(d + 'T12:00:00');
  const today = new Date();
  const diff = Math.round((new Date(date.toDateString()).getTime() - new Date(today.toDateString()).getTime()) / 86400000);
  if (diff === 0) return 'Hoy';
  if (diff === 1) return 'Mañana';
  const t = date.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'short' });
  return t.charAt(0).toUpperCase() + t.slice(1);
}

export function when(ts?: string | null): string {
  if (!ts) return '';
  const d = new Date(String(ts).replace(' ', 'T'));
  return isNaN(d.getTime()) ? String(ts) : d.toLocaleString('es', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

/** Carga con estado de error y recarga (tirar hacia abajo) */
export function useLoad<T>(fn: () => Promise<T>, deps: unknown[] = []) {
  const [data, setData] = React.useState<T | null>(null);
  const [error, setError] = React.useState<string | null>(null);
  const [loading, setLoading] = React.useState(true);
  const load = React.useCallback(async () => {
    setLoading(true);
    try {
      setData(await fn());
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error');
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
  React.useEffect(() => { load(); }, [load]);
  return { data, error, loading, reload: load, setData };
}
