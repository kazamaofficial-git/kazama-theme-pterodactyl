import React from 'react';

export interface Series {
    name: string;
    color: string;
    values: number[]; // persen 0-100
}

const W = 640;
const H = 220;
const PAD = { l: 38, r: 44, t: 10, b: 26 };

const path = (values: number[], points: number) => {
    const iw = W - PAD.l - PAD.r;
    const ih = H - PAD.t - PAD.b;
    const pad = Array(Math.max(0, points - values.length)).fill(values.length ? values[0] : 0).concat(values);
    return pad
        .map((v, i) => `${i === 0 ? 'M' : 'L'}${(PAD.l + (iw * i) / (points - 1)).toFixed(1)},${(PAD.t + ih - (Math.min(100, Math.max(0, v)) / 100) * ih).toFixed(1)}`)
        .join(' ');
};

export default ({ series, points = 25, rightLabels = true }: { series: Series[]; points?: number; rightLabels?: boolean }) => {
    const iw = W - PAD.l - PAD.r;
    const ih = H - PAD.t - PAD.b;
    const grid = [0, 25, 50, 75, 100];
    const right = ['0%', 'Low', 'Med', 'Med', 'Maks'];
    const xs = ['-60s', '-45s', '-30s', '-15s', 'Live'];

    return (
        <svg viewBox={`0 0 ${W} ${H}`} width={'100%'} role={'img'} style={{ display: 'block' }}>
            {grid.map((g, idx) => {
                const y = PAD.t + ih - (g / 100) * ih;
                return (
                    <g key={g}>
                        <line x1={PAD.l} x2={W - PAD.r} y1={y} y2={y} stroke={'#1b2742'} strokeDasharray={'3 4'} />
                        <text x={PAD.l - 6} y={y + 3} fontSize={9} textAnchor={'end'} fill={'#5d6b89'} fontFamily={'monospace'}>
                            {g}%
                        </text>
                        {rightLabels && (
                            <text x={W - PAD.r + 6} y={y + 3} fontSize={9} fill={'#5d6b89'} fontFamily={'monospace'}>
                                {right[idx]}
                            </text>
                        )}
                    </g>
                );
            })}
            {xs.map((label, i) => {
                const x = PAD.l + (iw * i) / (xs.length - 1);
                return (
                    <g key={label}>
                        <line x1={x} x2={x} y1={PAD.t} y2={PAD.t + ih} stroke={'#1b2742'} strokeDasharray={'3 4'} />
                        <text x={x} y={H - 8} fontSize={9} textAnchor={'middle'} fill={label === 'Live' ? '#34d399' : '#5d6b89'} fontFamily={'monospace'}>
                            {label}
                        </text>
                    </g>
                );
            })}
            {series.map((s) => {
                const last = s.values.length ? s.values[s.values.length - 1] : 0;
                return (
                    <g key={s.name}>
                        <path d={path(s.values, points)} fill={'none'} stroke={s.color} strokeWidth={2} strokeLinejoin={'round'} strokeLinecap={'round'} />
                        <circle cx={W - PAD.r} cy={PAD.t + ih - (Math.min(100, last) / 100) * ih} r={3.5} fill={s.color} />
                    </g>
                );
            })}
        </svg>
    );
};
