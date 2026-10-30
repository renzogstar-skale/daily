import React from 'react';
import { FlatList, KeyboardAvoidingView, Platform, Pressable, Text, TextInput, View } from 'react-native';
import { Stack, useLocalSearchParams } from 'expo-router';
import { api, Message } from '@/lib/api';
import { C, ErrorBox, Loading, Muted, s, when } from '@/components/ui';

type Resp = { thread: { id: number; title: string; can_post: boolean }; messages: Message[] };

export default function ThreadScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [data, setData] = React.useState<Resp | null>(null);
  const [error, setError] = React.useState<string | null>(null);
  const [body, setBody] = React.useState('');
  const [sending, setSending] = React.useState(false);
  const uuid = React.useRef<string | null>(null);
  const load = React.useCallback(async () => {
    try {
      setData(await api.get<Resp>('/m/messages/' + id));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error');
    }
  }, [id]);
  // Sin canal de tiempo real: se consulta cada 10 s mientras la conversación está abierta (el push avisa si está cerrada)
  React.useEffect(() => {
    load();
    const t = setInterval(load, 10000);
    return () => clearInterval(t);
  }, [load]);

  const send = async () => {
    if (!body.trim() || sending) return;
    setSending(true);
    uuid.current = uuid.current ?? api.newKey();
    try {
      await api.post('/m/messages/' + id, { body, client_uuid: uuid.current });
      uuid.current = null;
      setBody('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se envió');
    } finally {
      setSending(false);
    }
  };

  if (!data && !error) return <Loading />;
  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
      <Stack.Screen options={{ title: data?.thread.title ?? 'Conversación' }} />
      {error && <View style={{ padding: 12 }}><ErrorBox text={error} onRetry={load} /></View>}
      <FlatList
        data={data ? [...data.messages].reverse() : []}
        inverted
        keyExtractor={(m) => String(m.id)}
        contentContainerStyle={{ padding: 12 }}
        renderItem={({ item: m }) => (
          <View style={{ alignSelf: m.mine ? 'flex-end' : 'flex-start', maxWidth: '85%', marginVertical: 4 }}>
            {!m.mine && <Muted>{m.sender}</Muted>}
            <View style={{ backgroundColor: m.mine ? '#E8FBE9' : '#fff', borderColor: C.line, borderWidth: 1, borderRadius: 14, padding: 10 }}>
              <Text style={{ fontSize: 16, color: C.text }}>{m.body}</Text>
            </View>
            <Text style={{ fontSize: 12, color: C.muted, textAlign: m.mine ? 'right' : 'left' }}>
              {when(m.created_at)}{m.mine ? ' · ' + (m.recipients > 1 ? `Leído por ${m.read_count} de ${m.recipients}` : m.read_count ? 'Leído' : 'Enviado') : ''}
            </Text>
          </View>
        )}
      />
      {data?.thread.can_post ? (
        <View style={{ flexDirection: 'row', gap: 8, padding: 10, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: C.line }}>
          <TextInput style={[s.input, { flex: 1, marginBottom: 0, maxHeight: 120 }]} value={body} onChangeText={setBody} placeholder="Escriba un mensaje" multiline maxLength={2000} accessibilityLabel="Mensaje" />
          <Pressable accessibilityRole="button" accessibilityLabel="Enviar" onPress={send} disabled={sending || !body.trim()}
            style={{ backgroundColor: C.primary, borderRadius: 12, paddingHorizontal: 16, justifyContent: 'center', opacity: sending || !body.trim() ? 0.5 : 1 }}>
            <Text style={{ color: '#fff', fontWeight: '700' }}>Enviar</Text>
          </Pressable>
        </View>
      ) : data ? <View style={{ padding: 12 }}><Muted>Este aviso es informativo: no admite respuestas.</Muted></View> : null}
    </KeyboardAvoidingView>
  );
}
