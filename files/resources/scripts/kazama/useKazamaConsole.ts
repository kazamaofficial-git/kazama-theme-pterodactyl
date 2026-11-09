import { useEffect } from 'react';
import { Terminal } from 'xterm';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

/** Menampilkan baris "[Claude-MCP]: Restart Server" (aksi dari MCP) langsung di console server. */
export default (terminal: Terminal) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);

    useEffect(() => {
        if (!uuid) return;
        let last: number | null = null;
        let stop = false;
        const tick = () => {
            http.get(`/kazama/api/servers/${uuid}/console`, { params: last === null ? {} : { after: last } })
                .then(({ data }) => {
                    if (stop) return;
                    if (last !== null) {
                        (data.lines as string[]).forEach((l) => terminal.writeln(`\u001b[1;35m${l}\u001b[0m`));
                    }
                    last = data.last;
                })
                .catch(() => undefined);
        };
        tick();
        const i = window.setInterval(tick, 3000);
        return () => {
            stop = true;
            window.clearInterval(i);
        };
    }, [uuid, terminal]);
};
