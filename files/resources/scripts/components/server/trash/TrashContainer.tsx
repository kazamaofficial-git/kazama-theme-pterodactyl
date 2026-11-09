import React from 'react';
import useSWR, { mutate } from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faFile, faFolder, faTrashRestore } from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import { TrashItem, emptyTrash, getTrash, purgeTrash, restoreTrash } from '@/kazama/api';
import { fmtBytes, fmtWib } from '@/kazama/format';
import { tx } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';
import { Btn, Card } from '@/kazama/ui';

export default () => {
    const uuid = ServerContext.useStoreState((s) => s.server.data!.uuid);
    const key = `kazama-trash-${uuid}`;
    const { data, error } = useSWR<{ data: TrashItem[]; days: number }>(key, () => getTrash(uuid), { refreshInterval: 15000 });
    const [msg, setMsg] = React.useState('');

    const act = (p: Promise<any>) =>
        p.then(() => mutate(key)).catch((e) => setMsg(e?.response?.data?.message || e?.response?.data?.errors?.[0]?.detail || tx('Aksi gagal.', 'Action failed.')));

    return (
        <ServerContentBlock title={'Trash'}>
            <div style={{ color: kz.text }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
                    <div>
                        <h2 style={{ fontSize: '1.4rem', fontWeight: 800 }}>Trash</h2>
                        <p style={{ color: kz.muted, fontSize: '0.85rem' }}>{tx(`File yang dihapus disimpan ${data ? data.days : 7} hari sebelum dihapus permanen.`, `Deleted files are kept for ${data ? data.days : 7} days before being removed permanently.`)}</p>
                    </div>
                    {data && data.data.length > 0 && (
                        <Btn tone={'danger'} onClick={() => window.confirm(tx('Kosongkan Trash? Tidak bisa dibatalkan.', 'Empty the Trash? This cannot be undone.')) && act(emptyTrash(uuid))}>
                            {tx('Kosongkan Trash', 'Empty Trash')}
                        </Btn>
                    )}
                </div>
                {msg && <p style={{ color: kz.red, margin: '0.6rem 0' }}>{msg}</p>}
                {error && <p style={{ color: kz.red }}>{tx('Gagal memuat Trash.', 'Failed to load Trash.')}</p>}
                {data && data.data.length === 0 && <Card style={{ color: kz.muted, marginTop: '1rem' }}>{tx('Trash kosong.', 'Trash is empty.')}</Card>}
                {data && data.data.map((i) => (
                    <Card key={i.id} style={{ marginTop: '0.8rem', padding: '0.8rem 1rem' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.8rem', alignItems: 'center', flexWrap: 'wrap' }}>
                            <div style={{ minWidth: 0 }}>
                                <strong><FontAwesomeIcon icon={i.isDir ? faFolder : faFile} style={{ color: kz.muted }} /> {i.name}</strong>
                                <div style={{ color: kz.muted, fontSize: '0.78rem', wordBreak: 'break-all' }}>{i.originalPath}</div>
                                <div style={{ color: kz.muted, fontSize: '0.75rem' }}>
                                    {tx('Dihapus oleh', 'Deleted by')} {i.deletedBy} · {fmtWib(i.deletedAt)} · {fmtBytes(i.size)} ·{' '}
                                    <span style={{ color: i.daysLeft <= 1 ? kz.red : kz.muted }}>{tx(`${i.daysLeft} hari lagi`, `${i.daysLeft} days left`)}</span>
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: '0.4rem' }}>
                                <Btn tone={'primary'} onClick={() => act(restoreTrash(uuid, i.id))}><FontAwesomeIcon icon={faTrashRestore} /> {tx('Pulihkan', 'Restore')}</Btn>
                                <Btn tone={'danger'} onClick={() => window.confirm(tx('Hapus permanen?', 'Delete permanently?')) && act(purgeTrash(uuid, i.id))}>{tx('Hapus', 'Delete')}</Btn>
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </ServerContentBlock>
    );
};
