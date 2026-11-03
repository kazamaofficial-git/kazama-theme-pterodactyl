import React from 'react';
import styled from 'styled-components/macro';
import { t } from '@/kazama/i18n';

const Footer = styled.p`
    text-align: center;
    font-size: 0.75rem;
    color: #8a97b2;
    a {
        color: #8a97b2;
        text-decoration: none;
    }
    a:hover {
        color: #e6edf7;
    }
`;

export default () => (
    <Footer>
        &copy; {new Date().getFullYear()} <strong style={{ color: '#e6edf7' }}>Kazama Cloud</strong>. All rights reserved. &middot; {t('poweredBy')}{' '}
        <a href={'https://t.me/DhilzzKZ'} target={'_blank'} rel={'noopener noreferrer'}>
            Kazama Tech
        </a>{' '}
        &middot;{' '}
        <a href={'https://pterodactyl.io'} target={'_blank'} rel={'noopener noreferrer'}>
            Pterodactyl&reg;
        </a>
    </Footer>
);
