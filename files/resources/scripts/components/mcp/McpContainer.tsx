import React, { useEffect, useState } from 'react';
import useSWR, { mutate } from 'swr';
import styled from 'styled-components/macro';
import PageContentBlock from '@/components/elements/PageContentBlock';
import {
    McpActivity, McpConnection, McpOverview, McpSettings, McpStats, McpTool, mcpActivity, mcpCreate, mcpDisconnect, mcpOverview, mcpSaveDefaults,
    mcpStats, mcpTools, mcpUpdate,
} from '@/kazama/api';
import { fmtWib } from '@/kazama/format';
import { tx } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';
import { Btn, Card, Input, Label, Modal, PowerToggle, Select } from '@/kazama/ui';

const Tabs = styled.div`
    display: flex;
    gap: 0.4rem;
    margin: 1.2rem 0 1rem;
    button {
        flex: 1;
        padding: 0.65rem;
        border-radius: 0.8rem;
        border: 1px solid ${kz.border};
        background: transparent;
        color: ${kz.muted};
        font-weight: 700;
        cursor: pointer;
    }
    button.on {
        background: ${kz.blue};
        border-color: ${kz.blue};
        color: #fff;
    }
`;
const Pill = styled.span<{ c?: string }>`
    padding: 0.15rem 0.6rem;
    border-radius: 99px;
    font-size: 0.72rem;
    font-weight: 700;
    color: ${(p) => p.c || kz.muted};
    background: ${(p) => (p.c || kz.muted) + '1f'};
    border: 1px solid ${(p) => (p.c || kz.muted) + '55'};
`;
const Row = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
    flex-wrap: wrap;
`;
const Code = styled.pre`
    background: ${kz.bg};
    border: 1px solid ${kz.border};
    border-radius: 0.7rem;
    padding: 0.7rem 0.9rem;
    overflow: auto;
    font-size: 0.8rem;
    white-space: pre-wrap;
    word-break: break-all;
`;

const copy = (text: string) => {
    try {
        navigator.clipboard.writeText(text);
    } catch (e) {
        /* abaikan */
    }
};

/* ---------------- Form pengaturan (dipakai default, koneksi baru, dan edit) ---------------- */
const SettingsForm = ({ value, onChange, o }: { value: McpSettings; onChange: (v: McpSettings) => void; o: McpOverview }) => {
    const set = (p: Partial<McpSettings>) => onChange({ ...value, ...p });
    const toggleServer = (id: number) => set({ server_ids: value.server_ids.indexOf(id) === -1 ? [...value.server_ids, id] : value.server_ids.filter((x) => x !== id) });

    return (
        <>
            <Label>{tx('AKSES', 'ACCESS')}</Label>
            <Select value={value.access} onChange={(e) => set({ access: e.target.value as any })}>
                <option value={'full'}>{tx('Penuh', 'Full')}</option>
                <option value={'read'}>{tx('Baca saja', 'Read only')}</option>
            </Select>
            <Label>{tx('SERVER YANG BOLEH DIPAKAI', 'ALLOWED SERVERS')}</Label>
            <Select value={value.servers_mode} onChange={(e) => set({ servers_mode: e.target.value as any })}>
                <option value={'all'}>{tx('Semua server', 'All servers')}</option>
                <option value={'selected'}>{tx('Hanya dipilih', 'Selected only')}</option>
            </Select>
            {value.servers_mode === 'selected' && (
                <div style={{ maxHeight: '9rem', overflow: 'auto', marginTop: '0.5rem', border: `1px solid ${kz.border}`, borderRadius: '0.7rem', padding: '0.5rem 0.7rem' }}>
                    {o.servers.length === 0 && <em style={{ color: kz.muted }}>{tx('Belum ada server.', 'No servers yet.')}</em>}
                    {o.servers.map((s) => (
                        <label key={s.id} style={{ display: 'flex', gap: '0.5rem', margin: '0.25rem 0' }}>
                            <input type={'checkbox'} checked={value.server_ids.indexOf(s.id) !== -1} onChange={() => toggleServer(s.id)} /> {s.name}
                        </label>
                    ))}
                </div>
            )}
            <Label>{tx('BATAS AKSI PER HARI', 'DAILY ACTION LIMIT')}</Label>
            <Select value={value.daily_limit} onChange={(e) => set({ daily_limit: parseInt(e.target.value, 10) })}>
                {o.options.limits.map((n) => (
                    <option key={n} value={n}>{n === 0 ? tx('Tanpa batas', 'Unlimited') : n}</option>
                ))}
            </Select>
            <Label>{tx('PUTUSKAN OTOMATIS', 'AUTO DISCONNECT')}</Label>
            <Select value={value.auto_disconnect_days} onChange={(e) => set({ auto_disconnect_days: parseInt(e.target.value, 10) })}>
                {o.options.disconnect.map((n) => (
                    <option key={n} value={n}>{n === 0 ? tx('Tidak pernah', 'Never') : tx(`${n} hari`, `${n} days`)}</option>
                ))}
            </Select>
            <Row style={{ marginTop: '1rem' }}>
                <div>
                    <strong>{tx('Cadangkan otomatis', 'Auto backup')}</strong>
                    <div style={{ color: kz.muted, fontSize: '0.8rem' }}>{tx('Backup dulu sebelum aksi berisiko (mengedit file, restore, dll).', 'Back up first before risky actions (editing files, restore, etc).')}</div>
                </div>
                <PowerToggle on={value.auto_backup} onChange={(v) => set({ auto_backup: v })} />
            </Row>
        </>
    );
};

/* ---------------- Modal hubungkan ---------------- */
const ConnectModal = ({ o, onClose }: { o: McpOverview; onClose: () => void }) => {
    const [mode, setMode] = useState<'auto' | 'manual'>('auto');
    const [platform, setPlatform] = useState('claude');
    const [name, setName] = useState('');
    const [created, setCreated] = useState<{ token: string; endpoint: string } | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const create = () => {
        setBusy(true);
        setError('');
        mcpCreate({ name: name.trim() || o.defaultName, platform, settings: o.defaults })
            .then((r) => {
                setCreated({ token: r.token, endpoint: r.endpoint });
                mutate('kazama-mcp');
            })
            .catch(() => setError(tx('Gagal membuat koneksi.', 'Failed to create the connection.')))
            .finally(() => setBusy(false));
    };

    const header = created ? `Authorization: Bearer ${created.token}` : '';

    return (
        <Modal title={tx('Hubungkan AI', 'Connect an AI')} onClose={onClose}>
            {!created ? (
                <>
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                        <Btn tone={mode === 'auto' ? 'primary' : 'ghost'} onClick={() => setMode('auto')}>{tx('Otomatis (OAuth)', 'Automatic (OAuth)')}</Btn>
                        <Btn tone={mode === 'manual' ? 'primary' : 'ghost'} onClick={() => setMode('manual')}>{tx('Manual (token)', 'Manual (token)')}</Btn>
                    </div>
                    <Label>PLATFORM</Label>
                    <Select value={platform} onChange={(e) => setPlatform(e.target.value)}>
                        {o.platforms.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </Select>
                    {mode === 'auto' ? (
                        <>
                            <p style={{ color: kz.muted, fontSize: '0.85rem', marginTop: '0.9rem' }}>
                                {tx('Tambahkan alamat ini sebagai remote MCP server di aplikasi AI Anda. Saat diminta masuk, halaman persetujuan akan terbuka dan koneksi dibuat otomatis dengan nama', 'Add this address as a remote MCP server in your AI app. When asked to sign in, a consent page opens and the connection is created automatically named')}{' '}
                                <strong>{o.defaultName}</strong>.
                            </p>
                            <Code>{o.endpoint}</Code>
                            <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end', marginTop: '0.8rem' }}>
                                <Btn tone={'ghost'} onClick={() => copy(o.endpoint)}>{tx('Salin URL', 'Copy URL')}</Btn>
                                <a href={'/guide/mcp'} target={'_blank'} rel={'noopener noreferrer'}><Btn tone={'primary'}>{tx('Lihat panduan', 'Open guide')}</Btn></a>
                            </div>
                        </>
                    ) : (
                        <>
                            <Label>{tx('NAMA KONEKSI', 'CONNECTION NAME')}</Label>
                            <Input value={name} placeholder={o.defaultName} onChange={(e) => setName(e.target.value)} maxLength={120} />
                            <p style={{ color: kz.muted, fontSize: '0.8rem', marginTop: '0.6rem' }}>{tx('Memakai pengaturan default Anda (ubah lewat tombol Pengaturan). Token hanya tampil sekali.', 'Uses your default settings (change via Settings). The token is shown only once.')}</p>
                            {error && <p style={{ color: kz.red }}>{error}</p>}
                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
                                <Btn tone={'ghost'} onClick={onClose}>{tx('Batal', 'Cancel')}</Btn>
                                <Btn tone={'primary'} disabled={busy} onClick={create}>{tx('Buat Token', 'Create Token')}</Btn>
                            </div>
                        </>
                    )}
                </>
            ) : (
                <>
                    <p style={{ color: kz.amber, fontSize: '0.85rem' }}>{tx('Simpan token ini sekarang. Tidak akan ditampilkan lagi.', 'Store this token now. It will not be shown again.')}</p>
                    <Label>URL</Label><Code>{created.endpoint}</Code>
                    <Label>HEADER</Label><Code>{header}</Code>
                    <Label>Claude Code</Label>
                    <Code>{`claude mcp add --transport http kazama ${created.endpoint} --header "${header}"`}</Code>
                    <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end', marginTop: '1rem' }}>
                        <Btn tone={'ghost'} onClick={() => copy(created.token)}>{tx('Salin token', 'Copy token')}</Btn>
                        <Btn tone={'primary'} onClick={onClose}>{tx('Selesai', 'Done')}</Btn>
                    </div>
                </>
            )}
        </Modal>
    );
};

/* ---------------- Modal pengaturan (default atau per koneksi) ---------------- */
const SettingsModal = ({ o, conn, onClose }: { o: McpOverview; conn?: McpConnection; onClose: () => void }) => {
    const [v, setV] = useState<McpSettings>(conn ? conn.settings : o.defaults);
    const [name, setName] = useState(conn ? conn.name : '');
    const [busy, setBusy] = useState(false);

    const save = () => {
        setBusy(true);
        const p = conn ? mcpUpdate(conn.id, { ...v, name: name.trim() || conn.name }) : mcpSaveDefaults(v);
        p.then(() => mutate('kazama-mcp')).then(onClose).catch(() => setBusy(false));
    };

    return (
        <Modal title={conn ? `${tx('Atur', 'Manage')}: ${conn.name}` : tx('Pengaturan Default', 'Default Settings')} onClose={onClose}>
            {!conn && <p style={{ color: kz.muted, fontSize: '0.85rem' }}>{tx('Dipakai sebagai nilai awal setiap koneksi baru.', 'Used as the starting values of every new connection.')}</p>}
            {conn && (
                <>
                    <Label>{tx('NAMA', 'NAME')}</Label>
                    <Input value={name} onChange={(e) => setName(e.target.value)} maxLength={120} />
                </>
            )}
            <SettingsForm value={v} onChange={setV} o={o} />
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1.2rem' }}>
                <Btn tone={'ghost'} onClick={onClose}>{tx('Batal', 'Cancel')}</Btn>
                <Btn tone={'primary'} disabled={busy} onClick={save}>{tx('Simpan', 'Save')}</Btn>
            </div>
        </Modal>
    );
};

/* ---------------- Tab-tab ---------------- */
const ActivityTab = () => {
    const { data } = useSWR<McpActivity[]>('kazama-mcp-activity', mcpActivity, { refreshInterval: 10000 });
    if (!data) return <p style={{ color: kz.muted }}>…</p>;
    if (!data.length) return <Card style={{ color: kz.muted }}>{tx('Belum ada aktivitas MCP.', 'No MCP activity yet.')}</Card>;
    const tone = (s: string) => (s === 'ok' ? kz.green : s === 'pending' ? kz.amber : kz.red);

    return (
        <>
            {data.map((a) => (
                <Card key={a.id} style={{ padding: '0.8rem 1rem' }}>
                    <Row>
                        <div style={{ minWidth: 0 }}>
                            <strong>{a.client ? `[${a.client}]` : ''} {a.tool || a.action}</strong>
                            {a.server && <span style={{ color: kz.muted }}> · {a.server}</span>}
                            {a.detail && <div style={{ color: kz.muted, fontSize: '0.78rem', wordBreak: 'break-word' }}>{a.detail.slice(0, 220)}</div>}
                        </div>
                        <div style={{ textAlign: 'right' }}>
                            <Pill c={tone(a.status)}>{a.status}</Pill>
                            <div style={{ color: kz.muted, fontSize: '0.72rem', marginTop: '0.3rem' }}>{fmtWib(a.at)}</div>
                        </div>
                    </Row>
                </Card>
            ))}
        </>
    );
};

const ToolsTab = () => {
    const { data } = useSWR<{ data: McpTool[]; groups: Record<string, string> }>('kazama-mcp-tools', mcpTools);
    if (!data) return <p style={{ color: kz.muted }}>…</p>;
    const groups = Object.keys(data.groups).filter((g) => data.data.some((t) => t.group === g));

    return (
        <>
            {groups.map((g) => (
                <Card key={g}>
                    <h4 style={{ fontWeight: 800, marginBottom: '0.6rem' }}>{data.groups[g]}</h4>
                    {data.data.filter((t) => t.group === g).map((t) => (
                        <div key={t.name} style={{ padding: '0.4rem 0', borderTop: `1px solid ${kz.border}` }}>
                            <code style={{ color: '#c4b5fd' }}>{t.name}</code>{' '}
                            {!t.write && <Pill>{tx('baca', 'read')}</Pill>} {t.needsConfirm && <Pill c={kz.amber}>{tx('konfirmasi', 'confirm')}</Pill>} {t.autoBackup && <Pill c={kz.green}>backup</Pill>}
                            <div style={{ color: kz.muted, fontSize: '0.8rem' }}>{t.description}</div>
                        </div>
                    ))}
                </Card>
            ))}
        </>
    );
};

const StatsTab = () => {
    const { data } = useSWR<McpStats>('kazama-mcp-stats', mcpStats, { refreshInterval: 30000 });
    if (!data) return <p style={{ color: kz.muted }}>…</p>;
    const max = Math.max(1, ...data.days.map((d) => d.count));

    return (
        <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(9rem,1fr))', gap: '0.8rem', marginBottom: '1rem' }}>
                <Card style={{ marginBottom: 0 }}><div style={{ color: kz.muted, fontSize: '0.72rem' }}>{tx('TOTAL AKSI (14 HARI)', 'TOTAL ACTIONS (14D)')}</div><strong style={{ fontSize: '1.6rem' }}>{data.total}</strong></Card>
                <Card style={{ marginBottom: 0 }}><div style={{ color: kz.muted, fontSize: '0.72rem' }}>{tx('BERHASIL', 'SUCCESS')}</div><strong style={{ fontSize: '1.6rem' }}>{data.successRate === null ? '-' : `${data.successRate}%`}</strong></Card>
                <Card style={{ marginBottom: 0 }}><div style={{ color: kz.muted, fontSize: '0.72rem' }}>{tx('KONEKSI AKTIF', 'ACTIVE CONNECTIONS')}</div><strong style={{ fontSize: '1.6rem' }}>{data.connections}</strong></Card>
            </div>
            <Card>
                <div style={{ color: kz.muted, fontSize: '0.72rem', marginBottom: '0.6rem' }}>{tx('AKSI PER HARI (WIB)', 'ACTIONS PER DAY (WIB)')}</div>
                <div style={{ display: 'flex', alignItems: 'flex-end', gap: '4px', height: '6rem' }}>
                    {data.days.map((d) => (
                        <div key={d.date} title={`${d.date}: ${d.count}`} style={{ flex: 1, height: `${Math.max(4, (d.count / max) * 100)}%`, background: d.count ? kz.blue : kz.border, borderRadius: '4px 4px 0 0' }} />
                    ))}
                </div>
            </Card>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(15rem,1fr))', gap: '0.8rem' }}>
                <Card style={{ marginBottom: 0 }}>
                    <strong>{tx('Alat terbanyak', 'Top tools')}</strong>
                    {data.tools.length === 0 && <div style={{ color: kz.muted }}>-</div>}
                    {data.tools.map((t) => (<Row key={t.name} style={{ marginTop: '0.4rem' }}><code>{t.name}</code><span>{t.count}</span></Row>))}
                </Card>
                <Card style={{ marginBottom: 0 }}>
                    <strong>{tx('Per klien', 'By client')}</strong>
                    {data.clients.length === 0 && <div style={{ color: kz.muted }}>-</div>}
                    {data.clients.map((t) => (<Row key={t.name} style={{ marginTop: '0.4rem' }}><span>{t.name}</span><span>{t.count}</span></Row>))}
                </Card>
            </div>
        </>
    );
};

/* ---------------- Halaman ---------------- */
export default () => {
    const { data: o } = useSWR<McpOverview>('kazama-mcp', mcpOverview, { refreshInterval: 20000 });
    const [tab, setTab] = useState<'activity' | 'tools' | 'stats'>('activity');
    const [modal, setModal] = useState<null | 'connect' | 'settings' | { conn: McpConnection }>(null);

    useEffect(() => {
        document.title = 'MCP';
    }, []);

    const disconnect = (c: McpConnection) => {
        if (!window.confirm(tx(`Putuskan koneksi "${c.name}"? AI tidak akan bisa mengakses akun Anda lagi.`, `Disconnect "${c.name}"? The AI will lose access to your account.`))) return;
        mcpDisconnect(c.id).then(() => mutate('kazama-mcp'));
    };

    return (
        <PageContentBlock title={'MCP'}>
            <div style={{ color: kz.text }}>
                <h2 style={{ fontSize: '1.6rem', fontWeight: 800 }}>MCP</h2>
                <p style={{ color: kz.muted }}>{tx('Kontrol server dan akun Anda dari AI favorit: Claude, Codex, Cursor, ChatGPT, dan lainnya.', 'Control your servers and account from your favorite AI: Claude, Codex, Cursor, ChatGPT and more.')}</p>
                <div style={{ display: 'flex', gap: '0.6rem', flexWrap: 'wrap', margin: '1rem 0' }}>
                    <Btn tone={'primary'} onClick={() => setModal('connect')} disabled={!o}>{tx('Hubungkan', 'Connect')}</Btn>
                    <Btn tone={'ghost'} onClick={() => setModal('settings')} disabled={!o}>{tx('Pengaturan', 'Settings')}</Btn>
                    <a href={'/guide/mcp'} target={'_blank'} rel={'noopener noreferrer'}><Btn tone={'ghost'}>{tx('Dokumentasi', 'Documentation')}</Btn></a>
                </div>

                <h4 style={{ fontWeight: 800, margin: '1rem 0 0.6rem' }}>{tx('Terhubung', 'Connected')} ({o ? o.connections.length : 0})</h4>
                {o && o.connections.length === 0 && <Card style={{ color: kz.muted }}>{tx('Belum ada AI yang terhubung.', 'No AI connected yet.')}</Card>}
                {o && o.connections.map((c) => (
                    <Card key={c.id}>
                        <Row>
                            <div>
                                <strong>{c.name}</strong> <Pill>{c.platform}</Pill> <Pill c={c.authType === 'oauth' ? kz.cyan : kz.purple}>{c.authType === 'oauth' ? 'OAuth' : 'token'}</Pill>
                                <div style={{ color: kz.muted, fontSize: '0.78rem', marginTop: '0.3rem' }}>
                                    {c.settings.access === 'read' ? tx('Baca saja', 'Read only') : tx('Akses penuh', 'Full access')} · {c.settings.servers_mode === 'all' ? tx('semua server', 'all servers') : tx(`${c.settings.server_ids.length} server`, `${c.settings.server_ids.length} servers`)} · {tx('hari ini', 'today')}: {c.callsToday}{c.settings.daily_limit ? `/${c.settings.daily_limit}` : ''}
                                    <br />{tx('Terakhir dipakai', 'Last used')}: {c.lastUsedAt ? fmtWib(c.lastUsedAt) : '-'}{c.expiresAt ? ` · ${tx('putus otomatis', 'auto-disconnect')}: ${fmtWib(c.expiresAt)}` : ''}
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: '0.4rem' }}>
                                <Btn tone={'ghost'} onClick={() => setModal({ conn: c })}>{tx('Atur', 'Manage')}</Btn>
                                <Btn tone={'danger'} onClick={() => disconnect(c)}>{tx('Putuskan', 'Disconnect')}</Btn>
                            </div>
                        </Row>
                    </Card>
                ))}

                <Tabs>
                    <button className={tab === 'activity' ? 'on' : ''} onClick={() => setTab('activity')}>{tx('Aktivitas', 'Activity')}</button>
                    <button className={tab === 'tools' ? 'on' : ''} onClick={() => setTab('tools')}>{tx('Alat', 'Tools')}</button>
                    <button className={tab === 'stats' ? 'on' : ''} onClick={() => setTab('stats')}>{tx('Statistik', 'Statistics')}</button>
                </Tabs>
                {tab === 'activity' && <ActivityTab />}
                {tab === 'tools' && <ToolsTab />}
                {tab === 'stats' && <StatsTab />}
            </div>

            {o && modal === 'connect' && <ConnectModal o={o} onClose={() => setModal(null)} />}
            {o && modal === 'settings' && <SettingsModal o={o} onClose={() => setModal(null)} />}
            {o && modal && typeof modal === 'object' && <SettingsModal o={o} conn={modal.conn} onClose={() => setModal(null)} />}
        </PageContentBlock>
    );
};
