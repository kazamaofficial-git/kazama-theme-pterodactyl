<?php

return [
    'brand' => 'Kazama Cloud',
    'developer' => 'Kazama Tech',
    'repository' => 'https://github.com/kazamaofficial-git/kazama-theme-pterodactyl',
    'developer_telegram' => 'https://t.me/DhilzzKZ',

    // Semua tampilan waktu (admin & UI) memakai zona ini. Database tetap UTC.
    'timezone' => 'Asia/Jakarta',

    // Hanya admin dengan ID ini yang punya akses penuh. Admin lain = akses terbatas.
    'super_admin_id' => 1,

    // Kontak admin untuk pembelian server (bisa diubah dari Admin > Kazama > Pengaturan).
    'contact' => [
        'telegram' => env('KAZAMA_CONTACT_TELEGRAM', 'https://t.me/DhilzzKZ'),
        'whatsapp' => env('KAZAMA_CONTACT_WHATSAPP', ''),
    ],

    'server' => [
        'active_days' => 30,   // masa aktif server saat dibuat
        'grace_days' => 3,     // setelah expired: suspend, lalu hapus bila tidak diperpanjang
    ],

    'registration' => (bool) env('KAZAMA_REGISTRATION', true),

    // Nama label akses terbatas (bisa diubah di Admin > Settings > Kazama).
    'protect_name' => 'Kazama Protect',

    'mcp' => [
        'default_name' => 'Kazama Cloud (MCP)',
        // Jika panel tidak bisa memanggil dirinya lewat APP_URL (mis. di belakang Cloudflare/firewall),
        // isi dengan alamat internal, contoh: http://127.0.0.1 (header Host otomatis memakai APP_URL).
        'internal_url' => env('KAZAMA_MCP_INTERNAL_URL'),
        'verify_ssl' => env('KAZAMA_MCP_VERIFY_SSL', true),
    ],

    'github' => [
        'client_id' => env('KAZAMA_GITHUB_CLIENT_ID'),
        'client_secret' => env('KAZAMA_GITHUB_CLIENT_SECRET'),
        'redirect' => env('KAZAMA_GITHUB_REDIRECT'), // default: APP_URL/auth/github/callback
    ],

    'google' => [
        'client_id' => env('KAZAMA_GOOGLE_CLIENT_ID'),
        'client_secret' => env('KAZAMA_GOOGLE_CLIENT_SECRET'),
        'redirect' => env('KAZAMA_GOOGLE_REDIRECT'), // default: APP_URL/auth/google/callback
    ],

    'backup' => [
        'disk' => env('KAZAMA_BACKUP_DISK'), // opsional: nama disk Laravel (s3, dll) untuk salinan cloud
        'keep' => 14,
        'mysqldump' => env('KAZAMA_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('KAZAMA_MYSQL', 'mysql'),
    ],
];
