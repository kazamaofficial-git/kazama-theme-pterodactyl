import React, { useState } from 'react';
import { mutate } from 'swr';
import { KazamaBackground, saveBackground, uploadBackground } from '@/kazama/api';
import { tx } from '@/kazama/i18n';
import { kz } from '@/kazama/theme';
import { Modal, Btn, Input, Label } from '@/kazama/ui';

export default ({ initial, onClose }: { initial: KazamaBackground; onClose: () => void }) => {
    const [enabled, setEnabled] = useState(initial.enabled);
    const [type, setType] = useState<'url' | 'upload'>(initial.type);
    const [url, setUrl] = useState(initial.type === 'url' ? initial.url : '');
    const [uploaded, setUploaded] = useState(initial.type === 'upload' ? initial.url : '');
    const [blur, setBlur] = useState(initial.blur);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const pick = (file?: File | null) => {
        if (!file) return;
        setBusy(true);
        setError('');
        uploadBackground(file)
            .then((b) => {
                setUploaded(b.url);
                setType('upload');
            })
            .catch(() => setError(tx('Gagal mengunggah. Gunakan JPG/PNG/WEBP maks 5 MB.', 'Upload failed. Use JPG/PNG/WEBP up to 5 MB.')))
            .finally(() => setBusy(false));
    };

    const save = () => {
        setBusy(true);
        setError('');
        saveBackground({ enabled, type, url: type === 'url' ? url.trim() : uploaded, blur })
            .then(() => mutate('kazama-overview'))
            .then(onClose)
            .catch((e) => {
                setBusy(false);
                setError(e?.response?.data?.message || tx('Gagal menyimpan.', 'Failed to save.'));
            });
    };

    const preview = type === 'url' ? url : uploaded;

    return (
        <Modal title={tx('Background Kustom', 'Custom Background')} onClose={onClose}>
            <label style={{ display: 'flex', gap: '0.6rem', alignItems: 'center', marginBottom: '0.8rem' }}>
                <input type={'checkbox'} checked={enabled} onChange={(e) => setEnabled(e.target.checked)} /> {tx('Aktifkan background kustom', 'Enable custom background')}
            </label>
            <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '0.8rem' }}>
                <Btn tone={type === 'upload' ? 'primary' : 'ghost'} onClick={() => setType('upload')}>{tx('Pilih Foto', 'Choose Photo')}</Btn>
                <Btn tone={type === 'url' ? 'primary' : 'ghost'} onClick={() => setType('url')}>{tx('Dari URL', 'From URL')}</Btn>
            </div>
            {type === 'upload' ? (
                <input type={'file'} accept={'image/png,image/jpeg,image/webp'} onChange={(e) => pick(e.target.files && e.target.files[0])} style={{ color: kz.muted }} />
            ) : (
                <Input placeholder={'https://...'} value={url} onChange={(e) => setUrl(e.target.value)} />
            )}
            <Label>
                Blur: {blur}px <span style={{ color: kz.muted }}>({tx('wajib, min', 'required, min')} {initial.min})</span>
            </Label>
            <input type={'range'} min={initial.min} max={initial.max} value={blur} onChange={(e) => setBlur(parseInt(e.target.value, 10))} style={{ width: '100%' }} />
            {preview && (
                <div style={{ height: '8rem', borderRadius: '0.8rem', marginTop: '0.8rem', overflow: 'hidden', position: 'relative', border: `1px solid ${kz.border}` }}>
                    <div style={{ position: 'absolute', inset: -blur, backgroundImage: `url("${preview}")`, backgroundSize: 'cover', backgroundPosition: 'center', filter: `blur(${blur}px)` }} />
                </div>
            )}
            {error && <p style={{ color: kz.red, marginTop: '0.6rem', fontSize: '0.85rem' }}>{error}</p>}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
                <Btn tone={'ghost'} onClick={onClose}>{tx('Batal', 'Cancel')}</Btn>
                <Btn tone={'primary'} disabled={busy} onClick={save}>{tx('Simpan', 'Save')}</Btn>
            </div>
        </Modal>
    );
};
