import React, { useEffect, useRef, useState } from 'react';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBell } from '@fortawesome/free-solid-svg-icons';
import { getNotifications, KazamaNotification, markAllRead, markRead, useOverview } from '@/kazama/api';
import { fmtWib } from '@/kazama/format';
import { t } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';

const Btn = styled.button`
    position: relative;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.8rem;
    border: 1px solid ${kz.border};
    background: ${kz.card};
    color: ${kz.text};
    cursor: pointer;
`;
const Badge = styled.span`
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 1.1rem;
    height: 1.1rem;
    padding: 0 0.25rem;
    border-radius: 999px;
    background: ${kz.red};
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 1.1rem;
`;
const Panel = styled.div`
    position: absolute;
    right: 0;
    top: 3.2rem;
    width: min(22rem, 92vw);
    max-height: 70vh;
    overflow: auto;
    z-index: 60;
    background: ${kz.card};
    border: 1px solid ${kz.border};
    border-radius: 1rem;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
`;
const Item = styled.div<{ unread: boolean; tone: string }>`
    padding: 0.8rem 1rem;
    border-bottom: 1px solid ${kz.border};
    border-left: 3px solid ${(p) => p.tone};
    background: ${(p) => (p.unread ? 'rgba(37,99,235,0.08)' : 'transparent')};
    cursor: pointer;
    strong {
        display: block;
        color: ${kz.text};
        font-size: 0.85rem;
    }
    p {
        color: ${kz.muted};
        font-size: 0.78rem;
        margin: 0.2rem 0;
    }
    small {
        color: #5d6b89;
        font-size: 0.68rem;
    }
`;

const tones: Record<string, string> = { info: kz.blue, success: kz.green, warning: kz.amber, danger: kz.red };

export default () => {
    const { data, mutate } = useOverview();
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<KazamaNotification[]>([]);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        getNotifications().then(setItems).catch(() => setItems([]));
    }, [open]);

    useEffect(() => {
        const close = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const unread = data ? data.unread : 0;

    const readOne = (n: KazamaNotification) => {
        if (n.read) return;
        markRead(n.id).then(() => {
            setItems((list) => list.map((i) => (i.id === n.id ? { ...i, read: true } : i)));
            mutate();
        });
    };

    const readAll = () =>
        markAllRead().then(() => {
            setItems((list) => list.map((i) => ({ ...i, read: true })));
            mutate();
        });

    return (
        <div ref={ref} style={{ position: 'relative' }}>
            <Btn type={'button'} onClick={() => setOpen((o) => !o)} aria-label={t('notifications')}>
                <FontAwesomeIcon icon={faBell} />
                {unread > 0 && <Badge>{unread > 9 ? '9+' : unread}</Badge>}
            </Btn>
            {open && (
                <Panel>
                    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0.8rem 1rem', borderBottom: `1px solid ${kz.border}` }}>
                        <strong style={{ color: kz.text }}>{t('notifications')}</strong>
                        <button type={'button'} onClick={readAll} style={{ color: kz.blue, fontSize: '0.75rem', background: 'none', border: 0, cursor: 'pointer' }}>
                            {t('markAllRead')}
                        </button>
                    </div>
                    {items.length === 0 && <p style={{ padding: '1.2rem', color: kz.muted, fontSize: '0.8rem' }}>{t('noNotifications')}</p>}
                    {items.map((n) => (
                        <Item key={n.id} unread={!n.read} tone={tones[n.type] || kz.blue} onClick={() => readOne(n)}>
                            <strong>{n.title}</strong>
                            <p>{n.message}</p>
                            <small>{fmtWib(n.createdAt)}</small>
                        </Item>
                    ))}
                </Panel>
            )}
        </div>
    );
};
