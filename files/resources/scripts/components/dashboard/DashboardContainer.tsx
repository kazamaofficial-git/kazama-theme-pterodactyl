import React, { useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowRight, faExclamationTriangle, faHdd, faLayerGroup, faMicrochip, faServer, faPlug } from '@fortawesome/free-solid-svg-icons';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import { ApplicationStore } from '@/state';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { useOverview, KzServer, KzUsage } from '@/kazama/api';
import { fmtBytes } from '@/kazama/format';
import { t } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';
import useLiveServers from '@/kazama/useLiveServers';
import WaveChart from '@/kazama/WaveChart';
import NoServerNotice from '@/kazama/NoServerNotice';

const Card = styled.div`
    background: ${kz.card};
    border: 1px solid ${kz.border};
    border-radius: 1.2rem;
    padding: 1.4rem;
    color: ${kz.text};
    margin-bottom: 1rem;
`;
const Hero = styled(Card)`
    background: linear-gradient(135deg, #0f1a33, ${kz.card});
    h1 {
        font-size: 1.7rem;
        font-weight: 800;
        margin: 0.7rem 0 0.4rem;
    }
    p {
        color: ${kz.muted};
    }
`;
const Pill = styled.span<{ color?: string }>`
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    color: ${(p) => p.color || kz.green};
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    &:before {
        content: '';
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 50%;
        background: currentColor;
    }
`;
const Cta = styled(Link)`
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    margin-top: 0.8rem;
    padding: 0.8rem 1.2rem;
    border-radius: 0.9rem;
    background: ${kz.blue};
    color: #fff;
    font-weight: 700;
    text-decoration: none;
    &:hover {
        color: #fff;
        filter: brightness(1.12);
    }
`;
const Grid = styled.div`
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
    margin-bottom: 1rem;
`;
const StatTop = styled.div`
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    span.title {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: ${kz.muted};
    }
`;
const Chip = styled.div<{ color: string }>`
    width: 2.8rem;
    height: 2.8rem;
    border-radius: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: ${(p) => p.color};
    border: 1px solid ${(p) => p.color}55;
    background: ${(p) => p.color}1a;
`;
const Value = styled.div`
    margin: 1rem 0 0.3rem;
    font-size: 2rem;
    font-weight: 800;
    small {
        font-size: 0.85rem;
        font-weight: 600;
        color: ${kz.muted};
        margin-left: 0.4rem;
    }
`;
const Bar = styled.div<{ color: string; pct: number }>`
    height: 6px;
    border-radius: 99px;
    background: #111a30;
    margin-top: 0.9rem;
    overflow: hidden;
    &:after {
        content: '';
        display: block;
        height: 100%;
        width: ${(p) => Math.max(0, Math.min(100, p.pct))}%;
        background: ${(p) => p.color};
        transition: width 0.6s;
    }
`;
const Sub = styled.div`
    color: ${kz.muted};
    font-size: 0.82rem;
`;
const Tabs = styled.div`
    display: inline-flex;
    gap: 0.3rem;
    padding: 0.3rem;
    margin: 0.8rem 0;
    border-radius: 0.9rem;
    background: ${kz.bg};
    border: 1px solid ${kz.border};
    button {
        padding: 0.45rem 0.9rem;
        border-radius: 0.6rem;
        border: 0;
        background: transparent;
        color: ${kz.muted};
        font-weight: 600;
        cursor: pointer;
    }
    button.on {
        background: #0f1a33;
        color: #fff;
    }
`;
const Alert = styled(Card)`
    border-color: ${kz.amber}66;
    background: rgba(245, 158, 11, 0.07);
    display: flex;
    gap: 0.8rem;
    align-items: center;
    padding: 0.9rem 1.2rem;
`;

const POINTS = 25;
const push = (arr: number[], v: number) => [...arr, v].slice(-POINTS);

const Stat = ({ title, icon, color, value, unit, note, pct }: { title: string; icon: IconDefinition; color: string; value: string; unit: string; note: string; pct: number }) => (
    <Card style={{ marginBottom: 0 }}>
        <StatTop>
            <span className={'title'}>{title}</span>
            <Chip color={color}>
                <FontAwesomeIcon icon={icon} />
            </Chip>
        </StatTop>
        <Value>
            {value}
            <small>{unit}</small>
        </Value>
        <Sub>{note}</Sub>
        <Bar color={color} pct={pct} />
    </Card>
);

const sumUsage = (servers: KzServer[], usage: Record<string, KzUsage | null>) => {
    let ram = 0, disk = 0, cpu = 0, ramLimit = 0, diskLimit = 0, online = 0;
    servers.forEach((s) => {
        const u = usage[s.uuid];
        if (u) {
            ram += u.memory;
            disk += u.disk;
            cpu += u.cpu;
            if (u.state === 'running') online++;
        }
        ramLimit += s.limits.memory > 0 ? s.limits.memory * 1024 * 1024 : 0;
        diskLimit += s.limits.disk > 0 ? s.limits.disk * 1024 * 1024 : 0;
    });
    return { ram, disk, cpu, ramLimit, diskLimit, online };
};

export default () => {
    const username = useStoreState((state: ApplicationStore) => state.user.data!.username);
    const { data: overview } = useOverview();
    const [tab, setTab] = useState<'all' | 'ram' | 'disk'>('all');
    const [hist, setHist] = useState<{ ram: number[]; disk: number[]; cpu: number[] }>({ ram: [], disk: [], cpu: [] });
    const maxRef = useRef({ ram: 1, disk: 1 });

    const live = useLiveServers(2500, (usage, servers) => {
        const s = sumUsage(servers, usage);
        maxRef.current.ram = Math.max(maxRef.current.ram, s.ram);
        maxRef.current.disk = Math.max(maxRef.current.disk, s.disk);
        const ramPct = s.ramLimit > 0 && servers.every((x) => x.limits.memory > 0) ? (s.ram / s.ramLimit) * 100 : (s.ram / maxRef.current.ram) * (s.ram > 0 ? 100 : 0);
        const diskPct = s.diskLimit > 0 && servers.every((x) => x.limits.disk > 0) ? (s.disk / s.diskLimit) * 100 : (s.disk / maxRef.current.disk) * (s.disk > 0 ? 100 : 0);
        const cpuPct = servers.length ? s.cpu / servers.length : 0;
        setHist((h) => ({ ram: push(h.ram, ramPct), disk: push(h.disk, diskPct), cpu: push(h.cpu, Math.min(100, cpuPct)) }));
    });

    const s = sumUsage(live.servers, live.usage);
    const total = live.servers.length;
    const cpuAvg = total ? s.cpu / total : 0;
    const lastPct = (arr: number[]) => (arr.length ? arr[arr.length - 1] : 0);
    const diskUnlimited = live.servers.some((x) => x.limits.disk === 0);
    const ramUnlimited = live.servers.some((x) => x.limits.memory === 0);

    const warnings = Object.keys(overview ? overview.expiries : {})
        .map((id) => ({ id, e: overview!.expiries[id], server: live.servers.find((x) => x.identifier === id) }))
        .filter((x) => x.server && x.e.expiresAt && (x.e.expired || (x.e.daysLeft !== null && x.e.daysLeft <= 3)));

    return (
        <PageContentBlock title={'Dashboard'}>
            <Hero>
                <Pill>{t('streaming')}</Pill>
                <h1>{t('hello', { name: username })}</h1>
                <p>{t('heroText')}</p>
                <Cta to={'/server'}>
                    <FontAwesomeIcon icon={faServer} /> {t('openServers')} <FontAwesomeIcon icon={faArrowRight} />
                </Cta>{' '}
                <Cta to={'/mcp'} style={{ marginLeft: '0.5rem', background: 'transparent', border: '1px solid rgba(255,255,255,0.35)' }}>
                    <FontAwesomeIcon icon={faPlug} /> MCP
                </Cta>
            </Hero>

            {!live.loading && total === 0 && <NoServerNotice />}

            {warnings.map((w) => (
                <Alert key={w.id}>
                    <FontAwesomeIcon icon={faExclamationTriangle} style={{ color: kz.amber }} />
                    <span style={{ color: kz.text }}>
                        {t('expiringWarn', { name: w.server!.name, when: w.e.expired || (w.e.daysLeft !== null && w.e.daysLeft <= 0) ? t('expiredWarn') : t('expiringIn', { n: w.e.daysLeft as number }) })}
                    </span>
                </Alert>
            ))}

            {total > 0 && (
                <>
                    <Grid>
                        <Stat title={t('serverStatus')} icon={faServer} color={kz.green} value={String(s.online)} unit={`/ ${total} ${t('active')}`} note={s.online === total ? t('allOnline') : t('someOffline')} pct={total ? (s.online / total) * 100 : 0} />
                        <Stat title={t('totalStorage')} icon={faHdd} color={kz.purple} value={fmtBytes(s.disk).split(' ')[0]} unit={`${fmtBytes(s.disk).split(' ')[1]} ${t('used')}`} note={diskUnlimited ? t('unlimitedAlloc') : `/ ${fmtBytes(s.diskLimit)}`} pct={lastPct(hist.disk) || (diskUnlimited ? 40 : 0)} />
                        <Stat title={t('ramUsage')} icon={faLayerGroup} color={kz.cyan} value={fmtBytes(s.ram).split(' ')[0]} unit={`${fmtBytes(s.ram).split(' ')[1]} ${t('used')}`} note={ramUnlimited ? t('unlimitedRam') : `/ ${fmtBytes(s.ramLimit)}`} pct={lastPct(hist.ram) || (ramUnlimited ? 35 : 0)} />
                        <Stat title={t('cpuLoad')} icon={faMicrochip} color={kz.amber} value={s.cpu.toFixed(1) + '%'} unit={t('total')} note={t('avgPerServer', { v: cpuAvg.toFixed(1) })} pct={Math.min(100, cpuAvg)} />
                    </Grid>

                    <Card>
                        <h3 style={{ fontWeight: 800 }}>{t('storageMemAnalysis')}</h3>
                        <Sub>{t('storageMemSub')}</Sub>
                        <Tabs>
                            <button className={tab === 'all' ? 'on' : ''} onClick={() => setTab('all')}>{t('combined')}</button>
                            <button className={tab === 'ram' ? 'on' : ''} onClick={() => setTab('ram')}>Memory (RAM)</button>
                            <button className={tab === 'disk' ? 'on' : ''} onClick={() => setTab('disk')}>Storage (Disk)</button>
                        </Tabs>
                        <div style={{ display: 'flex', gap: '1.4rem', flexWrap: 'wrap', fontSize: '0.85rem', color: kz.muted, marginBottom: '0.5rem' }}>
                            {tab !== 'disk' && (
                                <span><span style={{ color: kz.cyan }}>●</span> Memory (RAM): <strong style={{ color: kz.text }}>{fmtBytes(s.ram)} ({lastPct(hist.ram).toFixed(1)}%)</strong></span>
                            )}
                            {tab !== 'ram' && (
                                <span><span style={{ color: kz.purple }}>●</span> Storage (Disk): <strong style={{ color: kz.text }}>{fmtBytes(s.disk)} ({lastPct(hist.disk).toFixed(1)}%)</strong></span>
                            )}
                        </div>
                        <WaveChart
                            points={POINTS}
                            series={[
                                ...(tab !== 'disk' ? [{ name: 'RAM', color: kz.cyan, values: hist.ram }] : []),
                                ...(tab !== 'ram' ? [{ name: 'Disk', color: kz.purple, values: hist.disk }] : []),
                            ]}
                        />
                        <Sub style={{ display: 'flex', justifyContent: 'space-between', borderTop: `1px solid ${kz.border}`, paddingTop: '0.7rem', marginTop: '0.5rem' }}>
                            <span style={{ color: kz.green }}>● <span style={{ color: kz.muted }}>{t('streamEvery')}</span></span>
                            <span style={{ fontFamily: 'monospace' }}>{t('range60')}</span>
                        </Sub>
                    </Card>

                    <Card>
                        <h3 style={{ fontWeight: 800 }}>{t('cpuAnalysis')}</h3>
                        <Sub>{t('cpuSub')}</Sub>
                        <div style={{ fontSize: '0.85rem', color: kz.muted, margin: '0.8rem 0 0.5rem' }}>
                            <span style={{ color: kz.amber }}>●</span> {t('cpuLoad')}: <strong style={{ color: kz.text }}>{s.cpu.toFixed(1)}% ({cpuAvg.toFixed(1)}%)</strong>
                        </div>
                        <WaveChart points={POINTS} rightLabels={false} series={[{ name: 'CPU', color: kz.amber, values: hist.cpu }]} />
                        <Sub style={{ display: 'flex', justifyContent: 'space-between', borderTop: `1px solid ${kz.border}`, paddingTop: '0.7rem', marginTop: '0.5rem' }}>
                            <span style={{ color: kz.green }}>● <span style={{ color: kz.muted }}>{t('streamEvery')}</span></span>
                            <span style={{ fontFamily: 'monospace' }}>{t('range60')}</span>
                        </Sub>
                    </Card>
                </>
            )}
        </PageContentBlock>
    );
};
