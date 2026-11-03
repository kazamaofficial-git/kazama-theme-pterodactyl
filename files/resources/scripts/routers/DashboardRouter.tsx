import React from 'react';
import { Route, Switch, useLocation } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import ServerListPage from '@/components/dashboard/ServerListPage';
import TransitionRouter from '@/TransitionRouter';
import { NotFound } from '@/components/elements/ScreenBlock';
import routes from '@/routers/routes';

export default () => {
    const location = useLocation();

    return (
        <>
            <NavigationBar />
            <TransitionRouter>
                <Switch location={location}>
                    <Route path={'/'} exact>
                        <DashboardContainer />
                    </Route>
                    <Route path={'/server'} exact>
                        <ServerListPage />
                    </Route>
                    {(routes as any).account.map(({ path, component: Component }: any) => (
                        <Route key={path} path={`/account${path === '/' ? '' : path}`} exact>
                            <Component />
                        </Route>
                    ))}
                    <Route path={'*'}>
                        <NotFound />
                    </Route>
                </Switch>
            </TransitionRouter>
        </>
    );
};
