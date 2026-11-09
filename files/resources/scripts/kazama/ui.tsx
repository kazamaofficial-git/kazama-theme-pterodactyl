import React from 'react';
import styled from 'styled-components/macro';
import { kz } from '@/kazama/theme';

const Back = styled.div`
    position: fixed;
    inset: 0;
    z-index: 200;
    background: rgba(2, 6, 14, 0.72);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
`;
const Box = styled.div`
    width: 100%;
    max-width: 34rem;
    max-height: 92vh;
    overflow: auto;
    background: #0b1220;
    border: 1px solid ${kz.border};
    border-radius: 1.1rem;
    padding: 1.3rem;
    color: ${kz.text};
`;

export const Modal = ({ title, onClose, children, wide }: { title: string; onClose: () => void; children: React.ReactNode; wide?: boolean }) => (
    <Back onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
        <Box style={wide ? { maxWidth: '44rem' } : undefined}>
            <h3 style={{ fontSize: '1.15rem', fontWeight: 800, marginBottom: '1rem' }}>{title}</h3>
            {children}
        </Box>
    </Back>
);

export const Btn = styled.button<{ tone?: 'primary' | 'ghost' | 'danger' }>`
    border: 1px solid ${(p) => (p.tone === 'primary' ? kz.blue : p.tone === 'danger' ? '#7f1d1d' : kz.border)};
    background: ${(p) => (p.tone === 'primary' ? kz.blue : p.tone === 'danger' ? '#3b1220' : 'transparent')};
    color: ${(p) => (p.tone === 'danger' ? '#fca5a5' : '#fff')};
    padding: 0.55rem 1rem;
    border-radius: 0.7rem;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
`;

export const Input = styled.input`
    width: 100%;
    background: ${kz.bg};
    border: 1px solid ${kz.border};
    border-radius: 0.7rem;
    color: ${kz.text};
    padding: 0.6rem 0.8rem;
`;

export const Select = styled.select`
    width: 100%;
    background: ${kz.bg};
    border: 1px solid ${kz.border};
    border-radius: 0.7rem;
    color: ${kz.text};
    padding: 0.6rem 0.8rem;
`;

export const Label = styled.div`
    margin: 0.9rem 0 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: ${kz.muted};
    letter-spacing: 0.04em;
`;

/** Tombol power (on/off). */
export const PowerToggle = ({ on, onChange }: { on: boolean; onChange: (v: boolean) => void }) => (
    <button
        type={'button'}
        role={'switch'}
        aria-checked={on}
        onClick={() => onChange(!on)}
        style={{
            width: '2.6rem', height: '2.6rem', borderRadius: '50%', cursor: 'pointer', border: `2px solid ${on ? kz.green : kz.border}`,
            background: on ? 'rgba(34,197,94,0.15)' : 'transparent', color: on ? kz.green : kz.muted, fontSize: '1.1rem',
            boxShadow: on ? '0 0 14px rgba(34,197,94,0.45)' : 'none',
        }}
    >
        ⏻
    </button>
);

export const Card = styled.div`
    background: ${kz.card};
    border: 1px solid ${kz.border};
    border-radius: 1.1rem;
    padding: 1.1rem;
    margin-bottom: 1rem;
`;
