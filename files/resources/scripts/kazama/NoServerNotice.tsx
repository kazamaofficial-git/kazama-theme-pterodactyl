import React from 'react';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faShoppingCart } from '@fortawesome/free-solid-svg-icons';
import { useOverview } from '@/kazama/api';
import { t } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';

const Box = styled.div`
    background: linear-gradient(135deg, #0f1a33, ${kz.card});
    border: 1px solid ${kz.border};
    border-radius: 1.2rem;
    padding: 1.6rem;
    color: ${kz.text};
    p {
        color: ${kz.muted};
        margin: 0.4rem 0 1rem;
    }
`;
const Btn = styled.a<{ color: string }>`
    display: inline-flex;
    align-items: center;
    margin: 0 0.6rem 0.4rem 0;
    padding: 0.65rem 1.1rem;
    border-radius: 0.8rem;
    background: ${(p) => p.color};
    color: #fff;
    font-weight: 700;
    text-decoration: none;
    &:hover {
        color: #fff;
        filter: brightness(1.1);
    }
`;

export default () => {
    const { data } = useOverview();
    const tg = (data && data.config.telegram) || 'https://t.me/DhilzzKZ';
    const wa = data && data.config.whatsapp;

    return (
        <Box>
            <h3 style={{ fontWeight: 800, fontSize: '1.15rem' }}>
                <FontAwesomeIcon icon={faShoppingCart} style={{ marginRight: '0.6rem', color: kz.amber }} />
                {t('noServerTitle')}
            </h3>
            <p>{t('noServerText')}</p>
            <Btn color={'#229ED9'} href={tg} target={'_blank'} rel={'noopener noreferrer'}>
                {t('contactTelegram')}
            </Btn>
            {wa && (
                <Btn color={'#22a559'} href={wa} target={'_blank'} rel={'noopener noreferrer'}>
                    {t('contactWhatsapp')}
                </Btn>
            )}
        </Box>
    );
};
