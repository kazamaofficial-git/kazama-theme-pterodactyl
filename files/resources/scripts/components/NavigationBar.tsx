import React, { useEffect, useState } from 'react';
import { Link, NavLink, useLocation, useRouteMatch } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArchive, faBars, faChevronDown, faChevronUp, faCog, faDatabase, faFolder, faHeartbeat, faKey, faNetworkWired,
    faPlay, faServer, faSignOutAlt, faTerminal, faThLarge, faTimes, faUser, faUserShield, faUsers, faClock,
} from '@fortawesome/free-solid-svg-icons';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import routes from '@/routers/routes';
import Can from '@/components/elements/Can';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import http from '@/api/http';
import LogoMark from '@/kazama/LogoMark';
import NotificationBell from '@/kazama/NotificationBell';
import { getLang, setLang, startTranslator, t } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';

const Bar = styled.div`
    position: sticky;
    top: 0;
    z-index: 40;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.8rem 1.2rem;
    background: ${kz.bg};
    border-bottom: 1px solid ${kz.border};
    color: ${kz.text};
`;
const IconBtn = styled.button`
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 0.8rem;
    border: 1px solid transparent;
    background: transparent;
    color: ${kz.text};
    cursor: pointer;
    font-size: 1.1rem;
    &:hover {
        background: ${kz.card};
    }
`;
const Brand = styled(Link)`
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: ${kz.text};
    font-weight: 800;
    font-size: 1.15rem;
    text-decoration: none;
    &:hover {
        color: #fff;
    }
`;
const Avatar = styled.div`
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.7rem;
    background: linear-gradient(135deg, #f59e0b 0 30%, #6aaa64 30% 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #0b1220;
`;
const Overlay = styled.div`
    position: fixed;
    inset: 0;
    z-index: 70;
    background: rgba(2, 6, 14, 0.6);
    backdrop-filter: blur(4px);
`;
const Drawer = styled.aside`
    position: fixed;
    z-index: 71;
    top: 0;
    bottom: 0;
    left: 0;
    width: min(20.5rem, 86vw);
    display: flex;
    flex-direction: column;
    background: ${kz.bg};
    border-right: 1px solid ${kz.border};
    overflow-y: auto;
`;
const Label = styled.div`
    padding: 1rem 1.2rem 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: ${kz.muted};
`;
const itemCss = `
    display: flex;
    align-items: center;
    gap: 0.9rem;
    margin: 0.15rem 0.7rem;
    padding: 0.75rem 0.9rem;
    border-radius: 0.8rem;
    border: 1px solid transparent;
    color: ${kz.muted};
    font-weight: 600;
    text-decoration: none;
    background: transparent;
    cursor: pointer;
    width: calc(100% - 1.4rem);
    text-align: left;
    &:hover { color: #fff; background: ${kz.card}; }
    &.active, &.kz-active { color: #fff; background: #0f1a33; border-color: ${kz.border}; }
`;
const Item = styled(NavLink)`
    ${itemCss}
`;
const ItemBtn = styled.button`
    ${itemCss}
`;
const Sub = styled(NavLink)`
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-left: 2.1rem;
    padding: 0.6rem 0.9rem;
    color: ${kz.muted};
    font-size: 0.9rem;
    text-decoration: none;
    &:hover,
    &.active {
        color: #fff;
    }
`;
const Menu = styled.div`
    position: absolute;
    right: 0;
    top: 3.4rem;
    width: min(18rem, 92vw);
    z-index: 60;
    background: ${kz.card};
    border: 1px solid ${kz.border};
    border-radius: 1rem;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    a,
    button {
        display: flex;
        width: 100%;
        align-items: center;
        gap: 0.8rem;
        padding: 0.85rem 1.1rem;
        color: ${kz.text};
        background: none;
        border: 0;
        border-top: 1px solid ${kz.border};
        text-decoration: none;
        cursor: pointer;
        text-align: left;
    }
    a:hover,
    button:hover {
        background: #0f1a33;
    }
`;

const serverIcons: Record<string, IconDefinition> = {
    Console: faTerminal, Files: faFolder, Databases: faDatabase, Schedules: faClock, Users: faUsers, Backups: faArchive,
    Network: faNetworkWired, Startup: faPlay, Settings: faCog, Activity: faHeartbeat,
};
const accountIcons: Record<string, IconDefinition> = { Account: faUser, 'API Credentials': faKey, 'SSH Keys': faTerminal, Activity: faHeartbeat };
const accountLabels: Record<string, string> = { Account: 'Profil & Akun', Activity: 'Log Aktivitas' };

const ServerMenu = ({ onNavigate }: { onNavigate: () => void }) => {
    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    if (!id) return null;
    const entries = (routes as any).server.filter((r: any) => !!r.name);

    return (
        <>
            <Label>{t('serverMenu')}</Label>
            {entries.map((r: any) => {
                const to = `/server/${id}${r.path === '/' ? '' : r.path}`;
                const link = (
                    <Item key={r.path} to={to} exact={!!r.exact || r.path === '/'} activeClassName={'active'} onClick={onNavigate}>
                        <FontAwesomeIcon icon={serverIcons[r.name] || faCog} fixedWidth />
                        {r.name}
                    </Item>
                );
                return r.permission ? (
                    <Can key={r.path} action={r.permission} matchAny>
                        {link}
                    </Can>
                ) : (
                    link
                );
            })}
        </>
    );
};

export default () => {
    const location = useLocation();
    const inServer = !!useRouteMatch('/server/:id');
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const user = useStoreState((state: ApplicationStore) => state.user.data!);
    const [open, setOpen] = useState(false);
    const [account, setAccount] = useState(location.pathname.startsWith('/account'));
    const [menu, setMenu] = useState(false);

    useEffect(() => {
        startTranslator();
    }, []);
    useEffect(() => {
        setOpen(false);
        setMenu(false);
    }, [location.pathname]);

    const logout = () => http.post('/auth/logout').finally(() => window.location.assign('/'));
    const close = () => setOpen(false);
    const brand = name && name !== 'Pterodactyl' ? name : 'Kazama Cloud';

    return (
        <>
            <Bar>
                <IconBtn type={'button'} onClick={() => setOpen(true)} aria-label={'Menu'}>
                    <FontAwesomeIcon icon={faBars} />
                </IconBtn>
                <Brand to={'/'}>
                    <LogoMark />
                    {brand}
                </Brand>
                <div style={{ flex: 1 }} />
                <SearchContainer />
                <NotificationBell />
                <div style={{ position: 'relative' }}>
                    <IconBtn type={'button'} style={{ width: 'auto', padding: '0 0.4rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }} onClick={() => setMenu((m) => !m)}>
                        <Avatar>{(user.username || '?').charAt(0).toUpperCase()}</Avatar>
                        <FontAwesomeIcon icon={menu ? faChevronUp : faChevronDown} style={{ fontSize: '0.7rem' }} />
                    </IconBtn>
                    {menu && (
                        <Menu>
                            <div style={{ display: 'flex', gap: '0.8rem', alignItems: 'center', padding: '1rem 1.1rem' }}>
                                <Avatar>{(user.username || '?').charAt(0).toUpperCase()}</Avatar>
                                <div style={{ minWidth: 0 }}>
                                    <div style={{ fontWeight: 700 }}>@{user.username}</div>
                                    <div style={{ color: kz.muted, fontSize: '0.8rem', overflow: 'hidden', textOverflow: 'ellipsis' }}>{user.email}</div>
                                </div>
                            </div>
                            <Link to={'/account'}>
                                <FontAwesomeIcon icon={faCog} fixedWidth /> {t('accountSettings')}
                            </Link>
                            {user.rootAdmin && (
                                <a href={'/admin'}>
                                    <FontAwesomeIcon icon={faUserShield} fixedWidth /> {t('adminPanel')}
                                </a>
                            )}
                            <button type={'button'} onClick={() => setLang(getLang() === 'id' ? 'en' : 'id')}>
                                <FontAwesomeIcon icon={faServer} fixedWidth /> {t('language')}: {getLang() === 'id' ? 'Indonesia → English' : 'English → Indonesia'}
                            </button>
                            <button type={'button'} style={{ color: '#f87171' }} onClick={logout}>
                                <FontAwesomeIcon icon={faSignOutAlt} fixedWidth /> {t('logout')}
                            </button>
                        </Menu>
                    )}
                </div>
            </Bar>

            {open && (
                <>
                    <Overlay onClick={close} />
                    <Drawer>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '1rem 1.2rem' }}>
                            <LogoMark size={44} />
                            <IconBtn type={'button'} onClick={close} aria-label={'Tutup'}>
                                <FontAwesomeIcon icon={faTimes} />
                            </IconBtn>
                        </div>
                        <div style={{ borderTop: `1px solid ${kz.border}`, paddingTop: '0.6rem', flex: 1 }}>
                            <Item to={'/'} exact activeClassName={'active'} onClick={close}>
                                <FontAwesomeIcon icon={faThLarge} fixedWidth /> {inServer ? t('mainDashboard') : t('dashboard')}
                            </Item>
                            <Item to={'/server'} exact activeClassName={'active'} onClick={close}>
                                <FontAwesomeIcon icon={faServer} fixedWidth /> {t('serverList')}
                            </Item>
                            {inServer ? (
                                <ServerMenu onNavigate={close} />
                            ) : (
                                <>
                                    <Label>{t('settings')}</Label>
                                    <ItemBtn type={'button'} className={location.pathname.startsWith('/account') ? 'kz-active' : ''} onClick={() => setAccount((a) => !a)}>
                                        <FontAwesomeIcon icon={faUser} fixedWidth />
                                        <span style={{ flex: 1 }}>{t('myAccount')}</span>
                                        <FontAwesomeIcon icon={account ? faChevronUp : faChevronDown} style={{ fontSize: '0.75rem' }} />
                                    </ItemBtn>
                                    {account &&
                                        (routes as any).account
                                            .filter((r: any) => !!r.name)
                                            .map((r: any) => (
                                                <Sub key={r.path} to={`/account${r.path === '/' ? '' : r.path}`} exact={r.path === '/'} activeClassName={'active'} onClick={close}>
                                                    <FontAwesomeIcon icon={accountIcons[r.name] || faUser} fixedWidth />
                                                    {accountLabels[r.name] || r.name}
                                                </Sub>
                                            ))}
                                </>
                            )}
                        </div>
                        <div style={{ borderTop: `1px solid ${kz.border}` }}>
                            <ItemBtn type={'button'} style={{ color: '#f87171' }} onClick={logout}>
                                <FontAwesomeIcon icon={faSignOutAlt} fixedWidth /> {t('logoutAccount')}
                            </ItemBtn>
                        </div>
                    </Drawer>
                </>
            )}
        </>
    );
};
