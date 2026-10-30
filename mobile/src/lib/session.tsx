// Sesión: configuración del servidor + usuario actual (/m/me). Permisos reales los valida el servidor en cada acción.
import React from 'react';
import { api, loadConfig, Me } from './api';

type Session = { me: Me | null; configured: boolean; ready: boolean; error: string | null; refresh: () => Promise<void> };

const Ctx = React.createContext<Session>({ me: null, configured: false, ready: false, error: null, refresh: async () => {} });

export function SessionProvider({ children }: { children: React.ReactNode }) {
  const [me, setMe] = React.useState<Me | null>(null);
  const [configured, setConfigured] = React.useState(false);
  const [ready, setReady] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const refresh = React.useCallback(async () => {
    const c = await loadConfig();
    setConfigured(!!c);
    if (!c) {
      setMe(null);
      setReady(true);
      return;
    }
    try {
      setMe(await api.get<Me>('/m/me'));
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error');
    } finally {
      setReady(true);
    }
  }, []);
  React.useEffect(() => { refresh(); }, [refresh]);
  return <Ctx.Provider value={{ me, configured, ready, error, refresh }}>{children}</Ctx.Provider>;
}

export const useSession = () => React.useContext(Ctx);
