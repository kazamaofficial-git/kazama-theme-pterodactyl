import { useEffect, useRef, useState } from 'react';
import { fetchServers, fetchUsage, KzServer, KzUsage } from '@/kazama/api';

export interface LiveState {
    loading: boolean;
    servers: KzServer[];
    usage: Record<string, KzUsage | null>;
}

/** Ambil daftar server lalu polling pemakaian resource secara berkala. */
export default (intervalMs: number, onTick?: (usage: Record<string, KzUsage | null>, servers: KzServer[]) => void): LiveState => {
    const [state, setState] = useState<LiveState>({ loading: true, servers: [], usage: {} });
    const tick = useRef(onTick);
    tick.current = onTick;

    useEffect(() => {
        let alive = true;
        let timer: ReturnType<typeof setTimeout>;
        let servers: KzServer[] = [];

        const poll = async () => {
            const entries = await Promise.all(servers.map(async (s) => [s.uuid, await fetchUsage(s.uuid)] as const));
            if (!alive) return;
            const usage: Record<string, KzUsage | null> = {};
            entries.forEach(([id, u]) => (usage[id] = u));
            setState({ loading: false, servers, usage });
            if (tick.current) tick.current(usage, servers);
            timer = setTimeout(poll, intervalMs);
        };

        fetchServers()
            .then((list) => {
                servers = list;
                if (!alive) return;
                if (list.length === 0) {
                    setState({ loading: false, servers: [], usage: {} });
                    return;
                }
                poll();
            })
            .catch(() => alive && setState({ loading: false, servers: [], usage: {} }));

        return () => {
            alive = false;
            clearTimeout(timer);
        };
    }, [intervalMs]);

    return state;
};
