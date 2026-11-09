import React, { useEffect, useState } from 'react';
import { Link, useHistory, useLocation } from 'react-router-dom';
import login from '@/api/auth/login';
import { httpErrorToHuman } from '@/api/http';
import AuthShell, { ErrorBox, Field, FieldLabel, OAuthButtons, Primary } from '@/kazama/AuthShell';
import { startTranslator, t, tx } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';

export default () => {
    const history = useHistory();
    const location = useLocation();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(new URLSearchParams(location.search).get('kz_error') || '');

    useEffect(() => {
        startTranslator();
    }, []);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setBusy(true);
        // Login hanya dengan email + password (username ditolak server).
        login({ username: email.trim(), password })
            .then((response) => {
                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended || '/';
                    return;
                }
                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((err) => {
                setBusy(false);
                setError(httpErrorToHuman(err));
            });
    };

    return (
        <AuthShell>
            <h1 style={{ textAlign: 'center', fontSize: '1.5rem', fontWeight: 800 }}>{t('loginTitle')}</h1>
            <p style={{ textAlign: 'center', color: kz.muted, marginTop: '0.3rem', fontSize: '0.9rem' }}>{t('loginSub', { brand: 'Kazama Cloud' })}</p>

            <OAuthButtons label={(p) => (p === 'google' ? t('google') : tx('Lanjutkan dengan GitHub', 'Continue with GitHub'))} />

            {error && <ErrorBox>{error}</ErrorBox>}

            <form onSubmit={submit}>
                <FieldLabel htmlFor={'kz-email'}>{t('email')}</FieldLabel>
                <Field id={'kz-email'} type={'email'} autoComplete={'email'} value={email} onChange={(e) => setEmail(e.target.value)} required />
                <FieldLabel htmlFor={'kz-pass'}>{t('password')}</FieldLabel>
                <Field id={'kz-pass'} type={'password'} autoComplete={'current-password'} value={password} onChange={(e) => setPassword(e.target.value)} required />
                <div style={{ textAlign: 'right', marginTop: '0.5rem', fontSize: '0.8rem' }}>
                    <Link to={'/auth/password'} style={{ color: kz.blue }}>
                        {t('forgot')}
                    </Link>
                </div>
                <Primary type={'submit'} disabled={busy}>
                    {busy ? t('signingIn') : t('signIn')}
                </Primary>
            </form>

            <p style={{ textAlign: 'center', marginTop: '1.2rem', color: kz.muted, fontSize: '0.85rem' }}>
                {t('noAccount')}{' '}
                <Link to={'/auth/register'} style={{ color: kz.blue, fontWeight: 700 }}>
                    {t('register')}
                </Link>
            </p>
        </AuthShell>
    );
};
