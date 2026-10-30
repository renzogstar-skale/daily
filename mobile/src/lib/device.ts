// Notificaciones push (Expo) y ubicación puntual para el fichaje.
import * as Notifications from 'expo-notifications';
import * as Location from 'expo-location';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { api } from './api';

Notifications.setNotificationHandler({
  handleNotification: async () => ({ shouldPlaySound: true, shouldSetBadge: true, shouldShowBanner: true, shouldShowList: true }),
});

export type PushResult = { ok: true; token: string } | { ok: false; reason: string };

/**
 * Registra el dispositivo en DAILY. Devuelve el motivo real si no se pudo (sin permiso, emulador, sin projectId de EAS,
 * Expo Go en Android). No se informa "activado" si el servidor no guardó el token.
 */
export async function registerPush(): Promise<PushResult> {
  if (Platform.OS === 'web') return { ok: false, reason: 'La versión web no recibe notificaciones push' };
  if (!Device.isDevice) return { ok: false, reason: 'Las notificaciones push requieren un teléfono físico' };
  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', { name: 'DAILY', importance: Notifications.AndroidImportance.MAX, vibrationPattern: [0, 250, 250, 250] });
  }
  let { status } = await Notifications.getPermissionsAsync();
  if (status !== 'granted') status = (await Notifications.requestPermissionsAsync()).status;
  if (status !== 'granted') return { ok: false, reason: 'Permiso de notificaciones denegado en el teléfono' };
  const projectId = Constants.expoConfig?.extra?.eas?.projectId || Constants.easConfig?.projectId;
  if (!projectId) return { ok: false, reason: 'Falta el projectId de EAS en app.json (extra.eas.projectId)' };
  try {
    const token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
    await api.post('/m/push/register', { token, device_name: Device.deviceName ?? Device.modelName ?? Platform.OS });
    return { ok: true, token };
  } catch (e) {
    return { ok: false, reason: e instanceof Error ? e.message : 'No se pudo obtener el token' };
  }
}

export async function unregisterPush(token: string): Promise<void> {
  try {
    await api.post('/m/push/unregister', { token });
  } catch {
    // sin conexión: el servidor revoca el token cuando Expo informe DeviceNotRegistered
  }
}

export type Geo = { lat: number; lng: number; accuracy: number | null } | null;

/** Un solo punto, solo si la sucursal usa geocerca. Sin permiso devuelve null y el servidor decide según su modo. */
export async function clockLocation(): Promise<Geo> {
  const gf = await api.get<{ mode: string }>('/m/geofence').catch(() => ({ mode: 'off' }));
  if (gf.mode === 'off') return null;
  const perm = await Location.requestForegroundPermissionsAsync();
  if (!perm.granted) return null;
  try {
    const p = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
    return { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy !== null ? Math.round(p.coords.accuracy) : null };
  } catch {
    return null;
  }
}
