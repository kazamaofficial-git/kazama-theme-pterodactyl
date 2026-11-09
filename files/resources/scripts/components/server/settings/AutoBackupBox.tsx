import React, { useEffect, useState } from 'react';
import styled from 'styled-components/macro';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Switch from '@/components/elements/Switch';
import { Button } from '@/components/elements/button/index';
import { AutoBackup, getAutoBackup, saveAutoBackup } from '@/kazama/api';
import { days, t } from '@/kazama/i18n';

const Row = styled.div`
    display: grid;
    gap: 0.8rem;
    grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
    margin-top: 1rem;
    label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 0.3rem;
    }
    select,
    input {
        width: 100%;
        padding: 0.55rem 0.7rem;
        border-radius: 0.4rem;
        border: 1px solid #4b5563;
        background: #1f2937;
        color: #e5e7eb;
    }
`;

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [cfg, setCfg] = useState<AutoBackup | null>(null);
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState('');

    useEffect(() => {
        getAutoBackup(uuid).then(setCfg).catch(() => setCfg(null));
    }, [uuid]);

    if (!cfg) return null;

    const persist = (next: AutoBackup) => {
        setBusy(true);
        setMsg('');
        saveAutoBackup(uuid, { enabled: next.enabled, frequency: next.frequency, time: next.time, weekday: next.weekday })
            .then((saved) => {
                setCfg(saved);
                setMsg(t('saved'));
            })
            .catch(() => setMsg('Gagal menyimpan.'))
            .finally(() => setBusy(false));
    };

    return (
        <TitledGreyBox title={t('autoBackup')} className={'mt-6 md:mt-0'}>
            {/* Default mati (bulatan di kiri). Klik untuk menghidupkan (bulatan ke kanan). */}
            <Switch
                key={String(cfg.enabled)}
                name={'kazama_auto_backup'}
                label={t('autoBackup')}
                description={t('autoBackupDesc')}
                defaultChecked={cfg.enabled}
                readOnly={busy}
                onChange={(e) => persist({ ...cfg, enabled: e.currentTarget.checked })}
            />
            {cfg.enabled && (
                <>
                    <Row>
                        <div>
                            <label>{t('frequency')}</label>
                            <select value={cfg.frequency} onChange={(e) => setCfg({ ...cfg, frequency: e.target.value as AutoBackup['frequency'] })}>
                                <option value={'hourly'}>{t('hourly')}</option>
                                <option value={'daily'}>{t('daily')}</option>
                                <option value={'weekly'}>{t('weekly')}</option>
                            </select>
                        </div>
                        <div>
                            <label>{t('timeWib')}</label>
                            <input type={'time'} value={cfg.time} onChange={(e) => setCfg({ ...cfg, time: e.target.value })} />
                        </div>
                        {cfg.frequency === 'weekly' && (
                            <div>
                                <label>{t('day')}</label>
                                <select value={cfg.weekday} onChange={(e) => setCfg({ ...cfg, weekday: parseInt(e.target.value, 10) })}>
                                    {days().map((d, i) => (
                                        <option key={d} value={i}>{d}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </Row>
                    <div className={'mt-4 flex items-center justify-between'}>
                        <p className={'text-xs text-neutral-400'}>
                            {cfg.nextRun && t('nextBackup', { v: cfg.nextRun })}
                            {cfg.lastRun && <><br />{t('lastBackup', { v: cfg.lastRun })}</>}
                            {msg && <><br /><span className={'text-green-400'}>{msg}</span></>}
                        </p>
                        <Button disabled={busy} onClick={() => persist(cfg)}>
                            {t('save')}
                        </Button>
                    </div>
                </>
            )}
        </TitledGreyBox>
    );
};
