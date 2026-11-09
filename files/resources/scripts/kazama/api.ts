import useSWR from 'swr';
import http from '@/api/http';

export interface KazamaConfig {
    brand: string;
    developer: string;
    telegram: string;
    whatsapp: string;
    registration: boolean;
    protectName?: string;
}

export interface KazamaExpiry {
    expiresAt: string | null;
    expired: boolean;
    daysLeft: number | null;
    suspendedBy?: string | null;
    suspendReason?: string | null;
    suspendedAt?: string | null;
}

export interface KazamaTombstone {
    id: number;
    name: string;
    address: string | null;
    deletedBy: string;
    reason: string;
    deletedAt: string;
}

export interface KazamaBackground {
    enabled: boolean;
    type: 'url' | 'upload';
    url: string;
    blur: number;
    min: number;
    max: number;
}

export interface KazamaOverview {
    config: KazamaConfig;
    unread: number;
    expiries: Record<string, KazamaExpiry>;
    tombstones: KazamaTombstone[];
    background: KazamaBackground;
    returnTo: string | null;
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

// ---- Background ----
export const saveBackground = (p: { enabled: boolean; type: 'url' | 'upload'; url: string; blur: number }): Promise<KazamaBackground> =>
    http.post('/kazama/api/background', p).then(({ data }) => data);
export const uploadBackground = (file: File): Promise<KazamaBackground> => {
    const f = new FormData();
    f.append('photo', file);
    return http.post('/kazama/api/background/upload', f).then(({ data }) => data);
};

// ---- Provider login ----
export const getProviders = (): Promise<{ google: boolean; github: boolean; registration: boolean }> =>
    http.get('/auth/providers').then(({ data }) => data);

// ---- Trash ----
export interface TrashItem {
    id: number;
    name: string;
    originalPath: string;
    isDir: boolean;
    size: number;
    deletedBy: string;
    deletedAt: string;
    expiresAt: string;
    daysLeft: number;
}
export const getTrash = (uuid: string): Promise<{ data: TrashItem[]; days: number }> => http.get(`/kazama/api/servers/${uuid}/trash`).then(({ data }) => data);
export const restoreTrash = (uuid: string, id: number) => http.post(`/kazama/api/servers/${uuid}/trash/${id}/restore`).then(({ data }) => data);
export const purgeTrash = (uuid: string, id: number) => http.delete(`/kazama/api/servers/${uuid}/trash/${id}`).then(({ data }) => data);
export const emptyTrash = (uuid: string) => http.post(`/kazama/api/servers/${uuid}/trash/empty`).then(({ data }) => data);

// ---- MCP ----
export interface McpSettings {
    access: 'full' | 'read';
    servers_mode: 'all' | 'selected';
    server_ids: number[];
    daily_limit: number;
    auto_disconnect_days: number;
    auto_backup: boolean;
}
export interface McpConnection {
    id: number;
    name: string;
    platform: string;
    authType: 'manual' | 'oauth';
    label: string;
    settings: McpSettings;
    callsToday: number;
    lastUsedAt: string | null;
    expiresAt: string | null;
    createdAt: string;
}
export interface McpOverview {
    endpoint: string;
    defaultName: string;
    platforms: { id: string; label: string; name: string }[];
    defaults: McpSettings;
    options: { limits: number[]; disconnect: number[] };
    connections: McpConnection[];
    servers: { id: number; name: string; identifier: string }[];
    role: 'user' | 'admin' | 'super';
}
export interface McpActivity {
    id: number;
    client: string | null;
    action: string;
    tool: string | null;
    server: string | null;
    status: string;
    detail: string | null;
    at: string;
}
export interface McpTool {
    name: string;
    group: string;
    groupLabel: string;
    role: string;
    write: boolean;
    needsConfirm: boolean;
    autoBackup: boolean;
    description: string;
}
export interface McpStats {
    total: number;
    successRate: number | null;
    days: { date: string; count: number }[];
    tools: { name: string; count: number }[];
    clients: { name: string; count: number }[];
    connections: number;
}
export const mcpOverview = (): Promise<McpOverview> => http.get('/kazama/api/mcp').then(({ data }) => data);
export const mcpSaveDefaults = (s: Partial<McpSettings>): Promise<McpSettings> => http.post('/kazama/api/mcp/defaults', s).then(({ data }) => data);
export const mcpCreate = (p: { name: string; platform: string; settings: Partial<McpSettings> }): Promise<{ connection: McpConnection; token: string; endpoint: string }> =>
    http.post('/kazama/api/mcp/connections', p).then(({ data }) => data);
export const mcpUpdate = (id: number, p: Partial<McpSettings> & { name?: string; platform?: string }): Promise<McpConnection> =>
    http.patch(`/kazama/api/mcp/connections/${id}`, p).then(({ data }) => data);
export const mcpDisconnect = (id: number) => http.delete(`/kazama/api/mcp/connections/${id}`).then(({ data }) => data);
export const mcpActivity = (): Promise<McpActivity[]> => http.get('/kazama/api/mcp/activity').then(({ data }) => data.data);
export const mcpTools = (): Promise<{ data: McpTool[]; groups: Record<string, string> }> => http.get('/kazama/api/mcp/tools').then(({ data }) => data);
export const mcpStats = (): Promise<McpStats> => http.get('/kazama/api/mcp/stats').then(({ data }) => data);
