// Cliente de la API de DAILY: la app nativa usa los mismos endpoints que la web móvil /m (fuente única de datos).
import * as SecureStore from 'expo-secure-store';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Platform } from 'react-native';

export type Config = { server: string; apiKey: string };

const KEY = 'daily.config';

// Clave API en el almacén seguro del sistema (Keychain / Keystore); en web no existe, se usa AsyncStorage
async function read(): Promise<string | null> {
  return Platform.OS === 'web' ? AsyncStorage.getItem(KEY) : SecureStore.getItemAsync(KEY);
}
async function write(v: string | null): Promise<void> {
  if (Platform.OS === 'web') {
    if (v === null) await AsyncStorage.removeItem(KEY); else await AsyncStorage.setItem(KEY, v);
    return;
  }
  if (v === null) await SecureStore.deleteItemAsync(KEY); else await SecureStore.setItemAsync(KEY, v);
}

let cfg: Config | null = null;

export async function loadConfig(): Promise<Config | null> {
  if (cfg) return cfg;
  const raw = await read();
  cfg = raw ? (JSON.parse(raw) as Config) : null;
  return cfg;
}

export async function saveConfig(c: Config | null): Promise<void> {
  cfg = c ? { server: c.server.replace(/\/+$/, ''), apiKey: c.apiKey.trim() } : null;
  await write(cfg ? JSON.stringify(cfg) : null);
}

export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

function idemKey(): string {
  return 'n-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
}

async function request<T>(method: 'GET' | 'POST', path: string, body?: unknown, idem?: string): Promise<T> {
  const c = await loadConfig();
  if (!c) throw new ApiError('Configure el servidor de DAILY', 0);
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (c.apiKey) headers['GROCY-API-KEY'] = c.apiKey;
  if (method === 'POST') {
    headers['Content-Type'] = 'application/json';
    headers['X-Idempotency-Key'] = idem ?? idemKey();
  }
  let res: Response;
  try {
    res = await fetch(c.server + '/api' + path, { method, headers, body: method === 'POST' ? JSON.stringify(body ?? {}) : undefined });
  } catch {
    throw new ApiError('No se pudo conectar con el servidor. Revise su conexión.', 0);
  }
  if (!res.ok) {
    let msg = 'Error ' + res.status;
    try {
      const j = await res.json();
      msg = j.error_message || j.message || msg;
    } catch {
      // respuesta sin JSON
    }
    if (res.status === 401) msg = 'Clave API inválida o sesión vencida';
    throw new ApiError(msg, res.status);
  }
  const text = await res.text();
  return (text ? JSON.parse(text) : {}) as T;
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  // idem: la misma clave en reintentos de un mismo toque evita duplicar la operación en el servidor
  post: <T>(path: string, body?: unknown, idem?: string) => request<T>('POST', path, body, idem),
  newKey: idemKey,
};

/* ------------------------------------------------------------------ Tipos de las respuestas usadas */
export type Me = {
  user_id: number; name: string; role: string; role_label: string; can_team: boolean; can_approve: boolean;
  employee: { id: number; name: string; branch_id: number; position?: string } | null;
  branches: { id: number; name: string }[]; all_branches: boolean; permissions: string[];
};

// Escaneo y operaciones de piso (/api/m/scan, /api/m/ops/*, /api/m/approvals)
export type ScanResult = {
  kind: 'product' | 'location' | 'unknown'; message?: string;
  product?: { id: number; name: string; sku?: string; group_name?: string };
  availability?: { available: number; physical: number; reserved?: number; quarantine?: number };
  entries?: { stock_id: string; location_name?: string; lot_number?: string; best_before_date?: string; amount: number }[];
  parsed?: { code?: string; lot?: string; best_before?: string };
};
export type OpResult = { success?: boolean; message?: string; movement_code?: string; status?: string; difference_amount?: number; replayed?: boolean };
export type Approvals = {
  waste: { id: number; product: string; amount: number; total_cost: number; created_timestamp: string; branch: string; reason: string }[];
  counts: { id: number; product: string; theoretical_stock: number; counted_stock: number; difference_amount: number; difference_cost: number; executed_date: string; branch: string }[];
};
export const opId = (kind: string) => kind + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
export type Shift = { id: number; shift_date: string; start_time: string; end_time: string; break_minutes: number; department?: string; role?: string; status: string; confirmed_at?: string | null; hours?: number };
export type ShiftState = {
  state: 'off' | 'upcoming' | 'due' | 'late' | 'on_shift' | 'overtime' | 'done';
  current: Shift | null; next: Shift | null; open_attendance: { clock_in: string } | null; week_worked: number; contract: number;
};
export type ScheduleResp = { linked: boolean; data: { shifts: Shift[]; week_worked: number; contract: number; absence_types: Record<string, string> } | null; state: ShiftState | null };
export type OpenShift = { id: number; request_date: string; start_time: string; end_time: string; role?: string; department?: string; reason?: string; remaining: number; blocking: string[]; warnings: string[]; auto_approve: boolean; my_status: string | null };
export type Claim = { id: number; request_date: string; start_time: string; end_time: string; status: string; decision_notes?: string; employee?: string; position?: string };
export type OpenShiftsResp = {
  linked: boolean; open: OpenShift[]; mine: Claim[]; can_manage: boolean;
  manager: { requests: (OpenShift & { branch?: string; filled: number; staff_needed: number; claims: Claim[] })[]; pending: number } | null;
};
export type Thread = { id: number; kind: 'direct' | 'group' | 'announcement'; title: string; unread: number; last_body?: string; last_message_at?: string; created_at: string };
export type Message = { id: number; sender: string; body: string; created_at: string; mine: boolean; read_count: number; recipients: number; read_by: string[] };
export type Notice = { key: string; title: string; detail?: string; at: string; unread: boolean; type: string };
export type TeamPerson = { employee_id: number; name: string; position?: string; start: string; end: string; status: string; clock_in: string | null };
