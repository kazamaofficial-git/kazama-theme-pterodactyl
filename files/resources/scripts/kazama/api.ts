import useSWR from 'swr';
import http from '@/api/http';

export interface KazamaConfig {
    brand: string;
    developer: string;
    telegram: string;
    whatsapp: string;
    registration: boolean;
}

export interface KazamaExpiry {
    expiresAt: string | null;
    expired: boolean;
    daysLeft: number | null;
}

export interface KazamaOverview {
    config: KazamaConfig;
    unread: number;
    expiries: Record<string, KazamaExpiry>;
}

export interface KazamaNotification {
    id: number;
    type: 'info' | 'success' | 'warning' | 'danger';
    title: string;
    message: string;
    serverName: string | null;
    createdAt: string;
    read: boolean;
}

export interface AutoBackup {
    enabled: boolean;
    frequency: 'hourly' | 'daily' | 'weekly';
    time: string;
    weekday: number;
    nextRun: string | null;
    lastRun: string | null;
}

export const useOverview = () =>
    useSWR<KazamaOverview>('kazama-overview', () => http.get('/kazama/api/overview').then(({ data }) => data), {
        refreshInterval: 30000,
        revalidateOnFocus: true,
    });

export const getNotifications = (): Promise<KazamaNotification[]> =>
    http.get('/kazama/api/notifications').then(({ data }) => data.data);

export const markRead = (id: number): Promise<void> => http.post(`/kazama/api/notifications/${id}/read`).then(() => undefined);
export const markAllRead = (): Promise<void> => http.post('/kazama/api/notifications/read-all').then(() => undefined);

export const getAutoBackup = (uuid: string): Promise<AutoBackup> =>
    http.get(`/kazama/api/servers/${uuid}/auto-backup`).then(({ data }) => data);

export const saveAutoBackup = (uuid: string, payload: Pick<AutoBackup, 'enabled' | 'frequency' | 'time' | 'weekday'>): Promise<AutoBackup> =>
    http.post(`/kazama/api/servers/${uuid}/auto-backup`, payload).then(({ data }) => data);

// ---- data server (Client API bawaan Pterodactyl) ----
export interface KzServer {
    uuid: string;
    identifier: string;
    name: string;
    address: string;
    suspended: boolean;
    limits: { memory: number; disk: number; cpu: number };
}

export interface KzUsage {
    state: string;
    suspended: boolean;
    memory: number;
    disk: number;
    cpu: number;
}

export const fetchServers = async (): Promise<KzServer[]> => {
    const { data } = await http.get('/api/client', { params: { page: 1 } });
    return (data.data as any[]).map((s) => {
        const a = s.attributes;
        const alloc = ((a.relationships?.allocations?.data as any[]) || []).map((x) => x.attributes);
        const main = alloc.find((x) => x.is_default) || alloc[0];
        return {
            uuid: a.uuid,
            identifier: a.identifier,
            name: a.name,
            address: main ? `${main.ip_alias || main.ip}:${main.port}` : '-',
            suspended: !!a.is_suspended,
            limits: { memory: a.limits.memory, disk: a.limits.disk, cpu: a.limits.cpu },
        } as KzServer;
    });
};

export const fetchUsage = (uuid: string): Promise<KzUsage | null> =>
    http
        .get(`/api/client/servers/${uuid}/resources`)
        .then(({ data }) => {
            const r = data.attributes;
            return {
                state: r.current_state,
                suspended: !!r.is_suspended,
                memory: r.resources.memory_bytes,
                disk: r.resources.disk_bytes,
                cpu: r.resources.cpu_absolute,
            } as KzUsage;
        })
        .catch(() => null);
