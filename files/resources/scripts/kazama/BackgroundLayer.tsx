import React, { useEffect } from 'react';
import { useOverview } from '@/kazama/api';

/** Background kustom (foto/URL) dengan blur wajib. Dirender di belakang seluruh halaman. */
export default () => {
    const { data } = useOverview();
    const bg = data && data.background;
    const on = !!(bg && bg.enabled && bg.url);

    useEffect(() => {
        const id = 'kz-bg-style';
        let el = document.getElementById(id);
        if (!on) {
            if (el) el.remove();
            return;
        }
        if (!el) {
            el = document.createElement('style');
            el.id = id;
            document.head.appendChild(el);
        }
        el.textContent = 'html, body, #app { background: transparent !important; }';
        return () => {
            const e = document.getElementById(id);
            if (e) e.remove();
        };
    }, [on]);

    if (!on || !bg) return null;
    const blur = Math.max(bg.min || 4, bg.blur);

    return (
        <div aria-hidden style={{ position: 'fixed', inset: 0, zIndex: -1, overflow: 'hidden', background: '#070b14' }}>
            <div
                style={{
                    position: 'absolute',
                    inset: -blur * 2,
                    backgroundImage: `url("${bg.url.replace(/"/g, '%22')}")`,
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    filter: `blur(${blur}px)`,
                }}
            />
            <div style={{ position: 'absolute', inset: 0, background: 'rgba(7,11,20,0.55)' }} />
        </div>
    );
};
