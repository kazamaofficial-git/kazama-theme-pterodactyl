import React from 'react';
import { Link } from 'react-router-dom';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBan, faClock, faGlobe, faHdd, faLayerGroup, faMicrochip, faServer } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { KzServer, KzUsage, useOverview } from '@/kazama/api';
import { fmtBytes, fmtWib } from '@/kazama/format';
import { t, tx } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';
import useLiveServers from '@/kazama/useLiveServers';
import NoServerNotice from '@/kazama/NoServerNotice';

const Card = styled(Link)<{ tone: string }>`
    display: block;
    background: ${kz.card};
    border: 1px solid ${kz.border};
    border-left: 4px solid ${(p) => p.tone};
    border-radius: 1.1rem;
    padding: 1.1rem;
    margin-bottom: 1rem;
    color: ${kz.text};
    text-decoration: none;
    &:hover {
        color: ${kz.text};
        border-color: ${kz.blue};
    }
`;
const Row = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
`;
const Badge = styled.span<{ color: string }>`
    padding: 0.25rem 0.8rem;
    border-radius: 0.6rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: ${(p) => p.color};
    background: ${(p) => p.color}1a;
    border: 1px solid ${(p) => p.color}55;
`;
const Metrics = styled.div`
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
    gap: 0.7rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid ${kz.border};
`;
const Metric = styled.div`
    background: ${kz.bg};
    border: 1px solid ${kz.border};
    border-radius: 0.8rem;
    padding: 0.6rem 0.8rem;
    font-size: 0.85rem;
    div.h {
        display: flex;
        justify-content: space-between;
        color: ${kz.muted};
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    strong {
        display: block;
        margin-top: 0.2rem;
    }
`;

const status = (s: KzServer, u: KzUsage | null | undefined): { label: string; color: string } => {
    if (s.suspended || (u && u.suspended)) return { label: t('suspended'), color: kz.red };
    switch (u && u.state) {
        case 'running': return { label: t('online'), color: kz.green };
        case 'starting': return { label: t('starting'), color: kz.green };
        case 'stopping': return { label: t('stopping'), color: kz.amber };
        default: return { label: u ? t('offline') : t('starting'), color: u ? kz.muted : kz.green };
    }
};

export default () => {
    const { data: overview } = useOverview();
    const live = useLiveServers(5000);

    return (
        <PageContentBlock title={t('serverList')}>
            <div style={{ color: kz.text, marginBottom: '1.2rem' }}>
                <h2 style={{ fontSize: '1.6rem', fontWeight: 800, display: 'flex', alignItems: 'center', gap: '0.8rem', flexWrap: 'wrap' }}>
                    <FontAwesomeIcon icon={faServer} style={{ color: kz.blue }} /> {t('serverList')}
                    <Badge color={kz.blue}>{t('instances', { n: live.servers.length })}</Badge>
                </h2>
                <p style={{ color: kz.muted }}>{t('serverListSub')}</p>
            </div>

            {!live.loading && live.servers.length === 0 && <NoServerNotice />}

            {live.servers.map((s) => {
                const u = live.usage[s.uuid];
                const st = status(s, u);
                const exp = overview && overview.expiries[s.identifier];
                const expTone = exp && exp.expiresAt ? (exp.expired || (exp.daysLeft !== null && exp.daysLeft <= 3) ? kz.red : kz.muted) : kz.muted;
                return (
                    <Card key={s.uuid} to={`/server/${s.identifier}`} tone={st.color}>
                        <Row>
                            <div>
                                <h3 style={{ fontSize: '1.15rem', fontWeight: 800 }}>{s.name}</h3>
                                <div style={{ marginTop: '0.4rem', fontFamily: 'monospace', fontSize: '0.8rem', color: kz.muted }}>
                                    <FontAwesomeIcon icon={faGlobe} /> {s.address}
                                </div>
                            </div>
                            <Badge color={st.color}>{st.label}</Badge>
                        </Row>
                        {exp && exp.expiresAt && (
                            <div style={{ marginTop: '0.7rem', fontSize: '0.8rem', color: expTone }}>
                                <FontAwesomeIcon icon={faClock} />{' '}
                                {exp.expired ? `${t('expiredTitle')} — ${t('expiredInfo')}` : `${t('expiresAt', { date: fmtWib(exp.expiresAt) })} · ${t('daysLeft', { n: Math.max(0, exp.daysLeft || 0) })}`}
                            </div>
                        )}
                        {exp && exp.suspendedBy && (
                            <div style={{ marginTop: '0.6rem', fontSize: '0.8rem', color: kz.red }}>
                                <FontAwesomeIcon icon={faBan} /> {tx('Disuspend oleh', 'Suspended by')} <strong>{exp.suspendedBy}</strong>
                                {exp.suspendedAt ? ` · ${fmtWib(exp.suspendedAt)}` : ''} — {exp.suspendReason || tx('Tanpa keterangan', 'No reason given')}
                            </div>
                        )}
                        <Metrics>
                            <Metric>
                                <div className={'h'}><span><FontAwesomeIcon icon={faMicrochip} /> CPU</span><span>{s.limits.cpu > 0 ? `${s.limits.cpu}%` : t('unlimited')}</span></div>
                                <strong>{u ? u.cpu.toFixed(1) : '0.0'}%</strong>
                            </Metric>
                            <Metric>
                                <div className={'h'}><span><FontAwesomeIcon icon={faLayerGroup} /> RAM</span><span>{s.limits.memory > 0 ? fmtBytes(s.limits.memory * 1048576) : t('unlimited')}</span></div>
                                <strong>{fmtBytes(u ? u.memory : 0)}</strong>
                            </Metric>
                            <Metric>
                                <div className={'h'}><span><FontAwesomeIcon icon={faHdd} /> DISK</span><span>{s.limits.disk > 0 ? fmtBytes(s.limits.disk * 1048576) : t('unlimited')}</span></div>
                                <strong>{fmtBytes(u ? u.disk : 0)}</strong>
                            </Metric>
                        </Metrics>
                    </Card>
                );
            })}

            {overview && overview.tombstones && overview.tombstones.map((d) => (
                <div
                    key={`del-${d.id}`}
                    style={{ background: kz.card, border: `1px dashed #7f1d1d`, borderLeft: `4px solid ${kz.red}`, borderRadius: '1.1rem', padding: '1.1rem', marginBottom: '1rem', color: kz.muted, opacity: 0.85, cursor: 'not-allowed' }}
                >
                    <Row>
                        <div>
                            <h3 style={{ fontSize: '1.15rem', fontWeight: 800, color: kz.text }}>{d.name}</h3>
                            {d.address && <div style={{ marginTop: '0.4rem', fontFamily: 'monospace', fontSize: '0.8rem' }}><FontAwesomeIcon icon={faGlobe} /> {d.address}</div>}
                        </div>
                        <Badge color={kz.red}>{tx('Dihapus', 'Deleted')}</Badge>
                    </Row>
                    <div style={{ marginTop: '0.7rem', fontSize: '0.8rem', color: '#fca5a5' }}>
                        {tx('Dihapus oleh', 'Deleted by')} <strong>{d.deletedBy}</strong> · {fmtWib(d.deletedAt)}
                        <br />{tx('Keterangan', 'Reason')}: {d.reason}
                        <br /><span style={{ color: kz.muted }}>{tx('Server tidak bisa diakses. Kartu ini hilang otomatis dalam 1 hari.', 'This server can no longer be accessed. This card disappears automatically within 1 day.')}</span>
                    </div>
                </div>
            ))}
        </PageContentBlock>
    );
};
