import React from 'react';
import styled from 'styled-components/macro';
import LogoMark from '@/kazama/LogoMark';
import KazamaCopyright from '@/kazama/KazamaCopyright';
import { kz } from '@/kazama/theme';

const Full = styled.div`
    position: fixed;
    inset: 0;
    z-index: 50;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    background: radial-gradient(1200px 600px at 20% -10%, #14234a 0, transparent 60%), radial-gradient(900px 500px at 100% 100%, #0e3a4a 0, transparent 55%), ${kz.bg};
    color: ${kz.text};
`;
const Card = styled.div`
    width: 100%;
    max-width: 26rem;
    background: ${kz.card};
    border: 1px solid ${kz.border};
    border-radius: 1.4rem;
    padding: 2rem 1.6rem;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.55);
`;

export const Field = styled.input`
    width: 100%;
    margin-top: 0.4rem;
    padding: 0.8rem 1rem;
    border-radius: 0.8rem;
    border: 1px solid ${kz.border};
    background: ${kz.bg};
    color: ${kz.text};
    outline: none;
    &:focus {
        border-color: ${kz.blue};
    }
`;
export const FieldLabel = styled.label`
    display: block;
    margin-top: 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: ${kz.muted};
`;
export const Primary = styled.button`
    width: 100%;
    margin-top: 1.4rem;
    padding: 0.85rem;
    border: 0;
    border-radius: 0.9rem;
    background: ${kz.blue};
    color: #fff;
    font-weight: 800;
    cursor: pointer;
    &:disabled {
        opacity: 0.6;
        cursor: wait;
    }
`;
export const GoogleButton = styled.a`
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    padding: 0.8rem;
    border-radius: 0.9rem;
    border: 1px solid ${kz.border};
    background: #fff;
    color: #1f2937;
    font-weight: 700;
    text-decoration: none;
    &:hover {
        color: #111827;
        filter: brightness(0.96);
    }
`;
export const ErrorBox = styled.div`
    margin-top: 1rem;
    padding: 0.7rem 0.9rem;
    border-radius: 0.8rem;
    border: 1px solid ${kz.red}66;
    background: ${kz.red}14;
    color: #fca5a5;
    font-size: 0.85rem;
`;
export const Divider = styled.div`
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin: 1.3rem 0 0.2rem;
    color: ${kz.muted};
    font-size: 0.78rem;
    &:before,
    &:after {
        content: '';
        flex: 1;
        height: 1px;
        background: ${kz.border};
    }
`;

export const GoogleIcon = () => (
    <svg width={18} height={18} viewBox={'0 0 48 48'} aria-hidden>
        <path fill={'#EA4335'} d={'M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z'} />
        <path fill={'#4285F4'} d={'M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z'} />
        <path fill={'#FBBC05'} d={'M10.5 28.7a14.5 14.5 0 0 1 0-9.4l-7.9-6.1a24 24 0 0 0 0 21.6l7.9-6.1z'} />
        <path fill={'#34A853'} d={'M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.8 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z'} />
    </svg>
);

export default ({ children }: { children: React.ReactNode }) => (
    <Full>
        <Card>
            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '1rem' }}>
                <LogoMark size={56} />
            </div>
            {children}
        </Card>
        <div style={{ marginTop: '1.2rem' }}>
            <KazamaCopyright />
        </div>
    </Full>
);
