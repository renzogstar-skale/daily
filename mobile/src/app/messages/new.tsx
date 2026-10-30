import React from 'react';
import { Alert, Pressable, Text, TextInput } from 'react-native';
import { router } from 'expo-router';
import { api } from '@/lib/api';
import { Button, C, Card, Empty, ErrorBox, Loading, Muted, s, Screen, Section, useLoad } from '@/components/ui';

type Contacts = { contacts: { id: number; name: string; position?: string; branch?: string }[]; can_announce: boolean; branches: { id: number; name: string }[] };

export default function NewMessage() {
  const q = useLoad(() => api.get<Contacts>('/m/messages/contacts'));
  const [sel, setSel] = React.useState<number[]>([]);
  const [title, setTitle] = React.useState('');
  const [ann, setAnn] = React.useState({ branch_id: 0, title: '', body: '' });
  if (q.loading) return <Loading />;
  if (q.error) return <Screen><ErrorBox text={q.error} onRetry={q.reload} /></Screen>;
  const d = q.data!;
  const toggle = (id: number) => setSel(sel.includes(id) ? sel.filter((x) => x !== id) : [...sel, id]);
  const open = async () => {
    try {
      const r = sel.length === 1 ? await api.post<{ thread_id: number }>('/m/messages/direct', { user_id: sel[0] }) : await api.post<{ thread_id: number }>('/m/messages/group', { title, member_ids: sel });
      router.replace(('/messages/' + r.thread_id) as never);
    } catch (e) {
      Alert.alert('No se pudo', e instanceof Error ? e.message : 'Error');
    }
  };
  return (
    <Screen>
      <Section>Para</Section>
      {d.contacts.length === 0 ? <Empty text="No hay compañeros vinculados en sus sucursales" /> : d.contacts.map((c) => (
        <Pressable key={c.id} accessibilityRole="checkbox" accessibilityState={{ checked: sel.includes(c.id) }} onPress={() => toggle(c.id)}>
          <Card style={{ paddingVertical: 10, borderColor: sel.includes(c.id) ? C.primary : C.line }}>
            <Text style={{ fontSize: 16, color: C.text }}>{sel.includes(c.id) ? '☑ ' : '☐ '}{c.name}</Text>
            <Muted>{[c.position, c.branch].filter(Boolean).join(' · ')}</Muted>
          </Card>
        </Pressable>
      ))}
      {sel.length > 1 && <TextInput style={s.input} value={title} onChangeText={setTitle} placeholder="Nombre del grupo" maxLength={80} />}
      {sel.length > 0 && <Button label={sel.length === 1 ? 'Abrir conversación' : 'Crear grupo'} onPress={open} />}
      {d.can_announce && (
        <>
          <Section>Aviso a toda una sucursal</Section>
          <Card>
            <Text style={s.label}>Sucursal</Text>
            {d.branches.map((b) => (
              <Text key={b.id} accessibilityRole="radio" accessibilityState={{ selected: ann.branch_id === b.id }} onPress={() => setAnn({ ...ann, branch_id: b.id })} style={{ paddingVertical: 6, color: ann.branch_id === b.id ? C.primaryDark : C.text, fontWeight: ann.branch_id === b.id ? '700' : '400' }}>{ann.branch_id === b.id ? '● ' : '○ '}{b.name}</Text>
            ))}
            <TextInput style={s.input} value={ann.title} onChangeText={(t) => setAnn({ ...ann, title: t })} placeholder="Título" maxLength={80} />
            <TextInput style={[s.input, { minHeight: 80 }]} value={ann.body} onChangeText={(t) => setAnn({ ...ann, body: t })} placeholder="Mensaje del aviso" multiline maxLength={2000} />
            <Button label="Enviar aviso" disabled={!ann.branch_id || !ann.body.trim()} onPress={async () => {
              try {
                const r = await api.post<{ thread_id: number; recipients: number }>('/m/messages/announcement', ann);
                Alert.alert('Aviso enviado', `${r.recipients} destinatarios`);
                router.replace(('/messages/' + r.thread_id) as never);
              } catch (e) {
                Alert.alert('No se envió', e instanceof Error ? e.message : 'Error');
              }
            }} />
          </Card>
        </>
      )}
    </Screen>
  );
}
