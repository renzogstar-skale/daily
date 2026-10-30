import React from 'react';
import { Text, TextInput } from 'react-native';
import { router } from 'expo-router';
import { api, loadConfig, Me, saveConfig } from '@/lib/api';
import { useSession } from '@/lib/session';
import { Button, Card, ErrorBox, Muted, Screen, s, Title } from '@/components/ui';

export default function Setup() {
  const { refresh } = useSession();
  const [server, setServer] = React.useState('');
  const [apiKey, setApiKey] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);
  React.useEffect(() => { loadConfig().then((c) => { if (c) { setServer(c.server); setApiKey(c.apiKey); } }); }, []);

  const connect = async () => {
    const url = server.trim();
    if (!/^https?:\/\/[^\s]+$/i.test(url)) {
      setError('Escriba la dirección completa, por ejemplo https://daily.suempresa.com');
      return;
    }
    if (url.startsWith('http://') && !/^http:\/\/(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(url)) {
      setError('Use https:// para servidores en internet (la clave API viajaría sin cifrar).');
      return;
    }
    await saveConfig({ server: url, apiKey });
    try {
      await api.get<Me>('/m/me');
      setError(null);
      await refresh();
      router.replace('/(tabs)');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo conectar');
    }
  };

  return (
    <Screen>
      <Title>Conectar con DAILY</Title>
      <Muted>Pida a su gerente la dirección del servidor y su clave API personal (DAILY › Usuario › Claves API).</Muted>
      <Card style={{ marginTop: 16 }}>
        <Text style={s.label}>Dirección del servidor</Text>
        <TextInput style={s.input} value={server} onChangeText={setServer} placeholder="https://daily.suempresa.com" autoCapitalize="none" autoCorrect={false} keyboardType="url" accessibilityLabel="Dirección del servidor" />
        <Text style={s.label}>Clave API</Text>
        <TextInput style={s.input} value={apiKey} onChangeText={setApiKey} placeholder="Clave API personal" autoCapitalize="none" autoCorrect={false} secureTextEntry accessibilityLabel="Clave API" />
        <Button label="Conectar" onPress={connect} />
      </Card>
      {error && <ErrorBox text={error} />}
      <Muted>La clave se guarda cifrada en el almacén seguro del teléfono.</Muted>
    </Screen>
  );
}
