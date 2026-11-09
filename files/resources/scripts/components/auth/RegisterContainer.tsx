import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import http, { httpErrorToHuman } from '@/api/http';
import AuthShell, { ErrorBox, Field, FieldLabel, OAuthButtons, Primary } from '@/kazama/AuthShell';
import { startTranslator, t, tx } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';

export default () => {
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        startTranslator();
    }, []);

    const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [key]: e.target.value });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setBusy(true);
        http.get('/sanctum/csrf-cookie')
            .then(() => http.post('/auth/register', form))
            .then(({ data }) => {
                // @ts-expect-error this is valid
                window.location = data.intended || '/';
            })
            .catch((err) => {
                setBusy(false);
                setError(httpErrorToHuman(err));
            });
    };

    return (
        <AuthShell>
            <h1 style={{ textAlign: 'center', fontSize: '1.5rem', fontWeight: 800 }}>{t('registerTitle')}</h1>
            <p style={{ textAlign: 'center', color: kz.muted, marginTop: '0.3rem', fontSize: '0.9rem' }}>{t('registerSub')}</p>

            <OAuthButtons intent={'register'} label={(p) => (p === 'google' ? t('google') : tx('Daftar dengan GitHub', 'Sign up with GitHub'))} />

            {error && <ErrorBox>{error}</ErrorBox>}

            <form onSubmit={submit}>
                <FieldLabel htmlFor={'kz-name'}>{t('fullName')}</FieldLabel>
                <Field id={'kz-name'} value={form.name} onChange={set('name')} required minLength={2} />
                <FieldLabel htmlFor={'kz-remail'}>{t('email')}</FieldLabel>
                <Field id={'kz-remail'} type={'email'} autoComplete={'email'} value={form.email} onChange={set('email')} required />
                <FieldLabel htmlFor={'kz-rpass'}>{t('password')}</FieldLabel>
                <Field id={'kz-rpass'} type={'password'} autoComplete={'new-password'} value={form.password} onChange={set('password')} required minLength={8} />
                <FieldLabel htmlFor={'kz-rpass2'}>{t('confirmPassword')}</FieldLabel>
                <Field id={'kz-rpass2'} type={'password'} autoComplete={'new-password'} value={form.password_confirmation} onChange={set('password_confirmation')} required minLength={8} />
                <Primary type={'submit'} disabled={busy}>
                    {busy ? t('signingIn') : t('register')}
                </Primary>
            </form>

            <p style={{ textAlign: 'center', marginTop: '1.2rem', color: kz.muted, fontSize: '0.85rem' }}>
                {t('haveAccount')}{' '}
                <Link to={'/auth/login'} style={{ color: kz.blue, fontWeight: 700 }}>
                    {t('signIn')}
                </Link>
            </p>
        </AuthShell>
    );
};
