export type Lang = 'id' | 'en';
const KEY = 'kazama_lang';

export const getLang = (): Lang => {
    try {
        return (localStorage.getItem(KEY) as Lang) === 'en' ? 'en' : 'id';
    } catch (e) {
        return 'id';
    }
};

export const setLang = (lang: Lang) => {
    try {
        localStorage.setItem(KEY, lang);
    } catch (e) {
        /* abaikan */
    }
    window.location.reload();
};

const dict: Record<string, { id: string; en: string }> = {
    dashboard: { id: 'Dashboard', en: 'Dashboard' },
    serverList: { id: 'Daftar Server', en: 'Server List' },
    mainDashboard: { id: 'Dashboard Utama', en: 'Main Dashboard' },
    settings: { id: 'PENGATURAN', en: 'SETTINGS' },
    myAccount: { id: 'Akun Saya', en: 'My Account' },
    serverMenu: { id: 'MENU SERVER', en: 'SERVER MENU' },
    accountSettings: { id: 'Pengaturan Akun', en: 'Account Settings' },
    logout: { id: 'Keluar', en: 'Log out' },
    logoutAccount: { id: 'Keluar Akun', en: 'Log out' },
    adminPanel: { id: 'Panel Admin', en: 'Admin Panel' },
    streaming: { id: 'Streaming Realtime Aktif', en: 'Realtime Streaming Active' },
    hello: { id: 'Hai, {name}!', en: 'Hi, {name}!' },
    heroText: { id: 'Pantau analitik beban komputasi, pemakaian memori, dan storage secara realtime di diagram bawah.', en: 'Monitor compute load, memory and storage usage in realtime in the charts below.' },
    openServers: { id: 'Buka Daftar Server', en: 'Open Server List' },
    serverStatus: { id: 'STATUS SERVER', en: 'SERVER STATUS' },
    active: { id: 'Aktif', en: 'Active' },
    allOnline: { id: 'Semua server beroperasi online', en: 'All servers are online' },
    someOffline: { id: 'Ada server yang tidak online', en: 'Some servers are not online' },
    totalStorage: { id: 'TOTAL STORAGE (DISK)', en: 'TOTAL STORAGE (DISK)' },
    ramUsage: { id: 'PEMAKAIAN RAM', en: 'RAM USAGE' },
    cpuLoad: { id: 'BEBAN CPU', en: 'CPU LOAD' },
    used: { id: 'Terpakai', en: 'Used' },
    total: { id: 'Total', en: 'Total' },
    unlimitedAlloc: { id: 'Termasuk alokasi Unlimited', en: 'Includes Unlimited allocation' },
    unlimitedRam: { id: 'Bebas batas kuota RAM', en: 'No RAM quota limit' },
    avgPerServer: { id: 'Rata-rata: {v}% / server', en: 'Average: {v}% / server' },
    storageMemAnalysis: { id: 'Analisis Storage & Memori', en: 'Storage & Memory Analysis' },
    storageMemSub: { id: 'Fluktuasi live pemakaian alokasi disk dan RAM', en: 'Live fluctuation of disk and RAM usage' },
    cpuAnalysis: { id: 'Analisis Beban CPU', en: 'CPU Load Analysis' },
    cpuSub: { id: 'Aktivitas live komputasi seluruh instans server', en: 'Live compute activity of all server instances' },
    combined: { id: 'Gabungan', en: 'Combined' },
    streamEvery: { id: 'Streaming metrik realtime per 2.5 detik', en: 'Streaming realtime metrics every 2.5 seconds' },
    range60: { id: 'Rentang: 60 Detik Terakhir', en: 'Range: Last 60 Seconds' },
    serverListSub: { id: 'Akses dan kelola seluruh instans server game dan cloud Anda.', en: 'Access and manage all your game and cloud server instances.' },
    instances: { id: '{n} Instans', en: '{n} Instances' },
    noServerTitle: { id: 'Anda belum memiliki server', en: 'You do not have any server yet' },
    noServerText: { id: 'Untuk membeli server, hubungi admin melalui Telegram atau WhatsApp. Pembelian server dilakukan oleh admin.', en: 'To buy a server, contact the admin via Telegram or WhatsApp. Servers are provisioned by the admin.' },
    contactTelegram: { id: 'Hubungi via Telegram', en: 'Contact via Telegram' },
    contactWhatsapp: { id: 'Hubungi via WhatsApp', en: 'Contact via WhatsApp' },
    suspended: { id: 'Suspended', en: 'Suspended' },
    online: { id: 'Online', en: 'Online' },
    offline: { id: 'Offline', en: 'Offline' },
    starting: { id: 'Starting', en: 'Starting' },
    stopping: { id: 'Stopping', en: 'Stopping' },
    expiresAt: { id: 'Aktif hingga {date}', en: 'Active until {date}' },
    daysLeft: { id: '{n} hari lagi', en: '{n} days left' },
    expiredTitle: { id: 'Kedaluwarsa', en: 'Expired' },
    expiredInfo: { id: 'Masa aktif habis. Hubungi admin untuk perpanjang.', en: 'Expired. Contact the admin to renew.' },
    unlimited: { id: 'UNLIMITED', en: 'UNLIMITED' },
    notifications: { id: 'Notifikasi', en: 'Notifications' },
    markAllRead: { id: 'Tandai semua dibaca', en: 'Mark all read' },
    noNotifications: { id: 'Belum ada notifikasi.', en: 'No notifications yet.' },
    expiringWarn: { id: 'Server {name} {when}. Segera perpanjang lewat admin.', en: 'Server {name} {when}. Renew soon via the admin.' },
    expiringIn: { id: 'berakhir dalam {n} hari', en: 'expires in {n} days' },
    expiredWarn: { id: 'sudah kedaluwarsa', en: 'has expired' },
    loginTitle: { id: 'Selamat datang kembali', en: 'Welcome back' },
    loginSub: { id: 'Masuk ke {brand} untuk mengelola server Anda.', en: 'Sign in to {brand} to manage your servers.' },
    google: { id: 'Lanjutkan dengan Google', en: 'Continue with Google' },
    or: { id: 'atau dengan email', en: 'or with email' },
    email: { id: 'Email', en: 'Email' },
    password: { id: 'Password', en: 'Password' },
    confirmPassword: { id: 'Konfirmasi Password', en: 'Confirm Password' },
    fullName: { id: 'Nama Lengkap', en: 'Full Name' },
    signIn: { id: 'Masuk', en: 'Sign In' },
    signingIn: { id: 'Memproses...', en: 'Processing...' },
    forgot: { id: 'Lupa password?', en: 'Forgot password?' },
    noAccount: { id: 'Belum punya akun?', en: "Don't have an account?" },
    haveAccount: { id: 'Sudah punya akun?', en: 'Already have an account?' },
    register: { id: 'Daftar', en: 'Sign Up' },
    registerTitle: { id: 'Buat akun baru', en: 'Create a new account' },
    registerSub: { id: 'Daftar gratis, server dibeli lewat admin.', en: 'Sign up for free, servers are bought via the admin.' },
    closed: { id: 'Pendaftaran sedang ditutup.', en: 'Registration is closed.' },
    autoBackup: { id: 'Backup Otomatis', en: 'Automatic Backup' },
    autoBackupDesc: { id: 'Buat backup server secara otomatis sesuai jadwal (waktu WIB).', en: 'Create server backups automatically on a schedule (WIB time).' },
    frequency: { id: 'Frekuensi', en: 'Frequency' },
    hourly: { id: 'Setiap jam', en: 'Hourly' },
    daily: { id: 'Harian', en: 'Daily' },
    weekly: { id: 'Mingguan', en: 'Weekly' },
    timeWib: { id: 'Jam (WIB)', en: 'Time (WIB)' },
    day: { id: 'Hari', en: 'Day' },
    save: { id: 'Simpan', en: 'Save' },
    saved: { id: 'Tersimpan.', en: 'Saved.' },
    nextBackup: { id: 'Backup berikutnya: {v}', en: 'Next backup: {v}' },
    lastBackup: { id: 'Terakhir: {v}', en: 'Last run: {v}' },
    language: { id: 'Bahasa', en: 'Language' },
    poweredBy: { id: 'Theme oleh', en: 'Theme by' },
};

export const days = (lang: Lang = getLang()): string[] =>
    lang === 'id' ? ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export const t = (key: string, vars: Record<string, string | number> = {}): string => {
    const entry = dict[key];
    let text = entry ? entry[getLang()] : key;
    Object.keys(vars).forEach((k) => {
        text = text.replace(`{${k}}`, String(vars[k]));
    });
    return text;
};

/* ---- Penerjemah bawaan Pterodactyl (teks Inggris persis → Indonesia) ---- */
const builtin: Record<string, string> = {
    Console: 'Konsol', Files: 'File', Databases: 'Database', Schedules: 'Jadwal', Users: 'Pengguna', Backups: 'Backup',
    Network: 'Jaringan', Startup: 'Startup', Settings: 'Pengaturan', Activity: 'Aktivitas',
    'Account Overview': 'Ringkasan Akun', 'Account': 'Akun', 'API Credentials': 'Kredensial API', 'SSH Keys': 'Kunci SSH',
    'Activity Log': 'Log Aktivitas', 'Update Password': 'Ubah Password', 'Update Email Address': 'Ubah Alamat Email',
    'Current Password': 'Password Saat Ini', 'New Password': 'Password Baru', 'Confirm New Password': 'Konfirmasi Password Baru',
    'Email Address': 'Alamat Email', 'Two-Step Verification': 'Verifikasi Dua Langkah', 'Disable Two-Step Verification': 'Nonaktifkan Verifikasi Dua Langkah',
    'Enable Two-Step Verification': 'Aktifkan Verifikasi Dua Langkah', 'Create API Key': 'Buat API Key', 'Create SSH Key': 'Buat Kunci SSH',
    'Start': 'Mulai', 'Restart': 'Mulai Ulang', 'Stop': 'Hentikan', 'Kill': 'Paksa Berhenti',
    'Address': 'Alamat', 'Uptime': 'Uptime', 'CPU Load': 'Beban CPU', 'Memory': 'Memori', 'Disk': 'Disk',
    'Network (Inbound)': 'Jaringan (Masuk)', 'Network (Outbound)': 'Jaringan (Keluar)',
    'Save': 'Simpan', 'Save Changes': 'Simpan Perubahan', 'Cancel': 'Batal', 'Delete': 'Hapus', 'Create': 'Buat', 'Edit': 'Ubah',
    'Close': 'Tutup', 'Confirm': 'Konfirmasi', 'Download': 'Unduh', 'Upload': 'Unggah', 'Rename': 'Ganti Nama', 'Move': 'Pindahkan',
    'Archive': 'Arsipkan', 'Unarchive': 'Ekstrak', 'Copy': 'Salin', 'Search': 'Cari', 'Loading': 'Memuat', 'Name': 'Nama',
    'Description': 'Deskripsi', 'Size': 'Ukuran', 'Modified': 'Diubah', 'Actions': 'Aksi', 'Status': 'Status', 'Owner': 'Pemilik',
    'New File': 'File Baru', 'Create Directory': 'Buat Folder', 'Upload File': 'Unggah File', 'New Folder': 'Folder Baru',
    'Server Name': 'Nama Server', 'Debug Information': 'Informasi Debug', 'SFTP Details': 'Detail SFTP', 'Change Server Details': 'Ubah Detail Server',
    'Reinstall Server': 'Instal Ulang Server', 'Reinstall': 'Instal Ulang', 'Launch SFTP': 'Buka SFTP', 'Server Address': 'Alamat Server', 'Username': 'Username',
    'Create Backup': 'Buat Backup', 'Backup Name': 'Nama Backup', 'Restore': 'Pulihkan', 'Lock': 'Kunci', 'Unlock': 'Buka Kunci',
    'Create Database': 'Buat Database', 'Create Schedule': 'Buat Jadwal', 'Add User': 'Tambah Pengguna', 'Invite User': 'Undang Pengguna',
    'Allocations': 'Alokasi', 'Primary': 'Utama', 'Make Primary': 'Jadikan Utama', 'Startup Command': 'Perintah Startup', 'Variables': 'Variabel',
    'Docker Image': 'Docker Image', 'Command': 'Perintah', 'Type your command...': 'Ketik perintah Anda...',
    'Your Servers': 'Server Anda', 'Other Servers': 'Server Lain', 'Admin': 'Admin', 'Sign Out': 'Keluar',
    'Login': 'Masuk', 'Password': 'Password', 'Forgot password?': 'Lupa password?', 'Send Email': 'Kirim Email',
    'Not Found': 'Tidak Ditemukan', 'Go Back': 'Kembali', 'Online': 'Online', 'Offline': 'Offline', 'Starting': 'Memulai', 'Stopping': 'Menghentikan',
    'Suspended': 'Disuspend', 'Installing': 'Menginstal', 'Never': 'Tidak pernah', 'Yes': 'Ya', 'No': 'Tidak',
};

let started = false;
const SKIP = '.xterm, [data-kz-notranslate], pre, code, textarea, input, script, style';

const translateNode = (node: Node) => {
    if (node.nodeType === 3) {
        const value = node.nodeValue;
        if (!value) return;
        const trimmed = value.trim();
        const replacement = builtin[trimmed];
        const parent = node.parentElement;
        if (replacement && !(parent && parent.closest(SKIP))) {
            node.nodeValue = value.replace(trimmed, replacement);
        }
    } else if (node.nodeType === 1) {
        const el = node as Element;
        if (el.matches && el.matches(SKIP)) return;
        node.childNodes.forEach(translateNode);
    }
};

export const startTranslator = () => {
    if (started || getLang() !== 'id' || typeof document === 'undefined') return;
    started = true;
    translateNode(document.body);
    new MutationObserver((mutations) => {
        mutations.forEach((m) => {
            if (m.type === 'characterData') translateNode(m.target);
            m.addedNodes.forEach(translateNode);
        });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
};
