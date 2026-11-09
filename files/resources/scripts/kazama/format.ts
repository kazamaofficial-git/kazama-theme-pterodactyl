export const fmtBytes = (bytes: number): string => {
    if (!bytes || bytes <= 0) return '0 Bytes';
    const units = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    const value = bytes / Math.pow(1024, i);
    return `${i === 0 ? Math.round(value) : value >= 100 ? Math.round(value) : Math.round(value * 10) / 10} ${units[i]}`;
};

export const fmtWib = (iso: string | null | undefined): string => {
    if (!iso) return '-';
    return (
        new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(iso)) + ' WIB'
    );
};
