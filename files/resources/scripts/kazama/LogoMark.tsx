import React from 'react';

export default ({ size = 34 }: { size?: number }) => (
    <svg width={size} height={size} viewBox={'0 0 48 48'} fill={'none'} aria-hidden>
        <defs>
            <linearGradient id={'kzg'} x1={'0'} y1={'0'} x2={'1'} y2={'1'}>
                <stop offset={'0'} stopColor={'#818cf8'} />
                <stop offset={'1'} stopColor={'#22d3ee'} />
            </linearGradient>
        </defs>
        <path d={'M24 6 6 15l18 9 18-9-18-9Z'} fill={'url(#kzg)'} />
        <path d={'m6 24 18 9 18-9'} stroke={'url(#kzg)'} strokeWidth={4} strokeLinecap={'round'} strokeLinejoin={'round'} />
        <path d={'m6 33 18 9 18-9'} stroke={'url(#kzg)'} strokeWidth={4} strokeLinecap={'round'} strokeLinejoin={'round'} />
        <circle cx={24} cy={15} r={2.2} fill={'#0b1220'} />
    </svg>
);
