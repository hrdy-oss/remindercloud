<?php

function head(string $title): void
{
    $flash = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title><?= e($title) ?> – Pengingatku</title>
        <meta name="theme-color" content="#1769aa">
        <link rel="icon" type="image/png" href="assets/logo-pengingat.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/assets/pwa-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Pengingatku">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Lilita+One&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>-dynamix-home-stats">
        <script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>-login-password-toggle" defer></script>
    </head>
    <body>
    <div class="shell<?= uid() ? '' : ' guest' ?>">
        <?php if ($flash): ?>
            <div class="toast" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php side(); ?>
        <main>
    <?php
}

function menu_items(): array
{
    $items = [
        'hari' => 'Hari ini',
        'jadwal' => 'Jadwal',
        'tugas' => 'Tugas',
        'storage' => 'Storage',
    ];

    if (can_admin()) {
        $items['admin'] = 'Dashboard';
    }

    return $items;
}

function foot(): void
{
    $page = $_GET['p'] ?? 'hari';

    if (uid()):
        ?>
        </main>
        <nav class="dock" aria-label="Menu utama">
            <?php foreach (menu_items() as $key => $label): ?>
                <a class="nv" href="index.php?p=<?= $key ?>" <?= $page === $key ? 'aria-current="page"' : '' ?>>
                    <?= ic($key) ?>
                    <em class="lb"><?= $label ?></em>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    else:
        echo '</main>';
    endif;
    ?>
    <?php if (uid()): ?>
        <dialog class="logout-dialog" id="logout-dialog" aria-labelledby="logout-dialog-title" aria-describedby="logout-dialog-description">
            <div class="logout-dialog__icon" aria-hidden="true">↪</div>
            <h2 id="logout-dialog-title">Keluar dari akun?</h2>
            <p id="logout-dialog-description">Kamu perlu masuk lagi untuk menggunakan akun ini.</p>
            <div class="logout-dialog__actions">
                <button type="button" class="logout-dialog__cancel" data-logout-cancel>Batal</button>
                <button type="button" class="logout-dialog__confirm" data-logout-confirm>Ya, keluar</button>
            </div>
        </dialog>
    <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
}

function ic(string $name): string
{
    $paths = [
        'hari' => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'jadwal' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'tugas' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
        'storage' => '<path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 11h18"/>',
        'admin' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 11a3 3 0 1 0 0-6M18 14a5 5 0 0 1 3 4v2"/>',
        'keluar' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10"/>',
    ];

    return '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">'
        . $paths[$name]
        . '</svg>';
}

function side(): void
{
    if (!uid()) {
        return;
    }

    $page = $_GET['p'] ?? 'hari';
    $user = current_user();
    $name = $user['nama'] ?? '';
    ?>
    <aside class="side">
        <details class="profile-menu">
            <summary class="profile-trigger" aria-label="Buka menu profil">
                <span class="ava"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                <span class="profile-copy"><b><?= e($name) ?></b><small>Profil</small></span>
            </summary>
            <div class="profile-popover">
                <div class="profile-popover__identity">
                    <b><?= e($name) ?></b>
                    <small><?= e($user['role'] ?? 'user') ?></small>
                </div>
                <a href="index.php?p=profil">Profil &amp; tambah data</a>
                <form method="post" action="index.php?a=keluar" data-logout-form>
                    <?= tok() ?>
                    <button class="out"><?= ic('keluar') ?>Keluar</button>
                </form>
            </div>
        </details>
        <div class="logo"><img src="assets/logo-pengingat.png" alt="">Pengingatku</div>
        <nav class="sidenav" aria-label="Menu utama">
            <?php foreach (menu_items() as $key => $label): ?>
                <a href="index.php?p=<?= $key ?>" <?= $page === $key ? 'aria-current="page"' : '' ?>>
                    <?= ic($key) ?><?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <?php
}

function tok(): string
{
    return '<input type="hidden" name="_t" value="' . csrf() . '">';
}

function v_auth(bool $daftar): void
{
    head($daftar ? 'Daftar' : 'Masuk');
    ?>
    <div class="brand"><img src="assets/logo-pengingat.png" alt="">Pengingatku</div>
    <h1><?= $daftar ? 'Buat akun Pengingatku' : 'Masuk ke Pengingatku' ?></h1>
    <p class="sub">Pengingat jadwal dan tugas untuk siswa SMK.</p>

    <form method="post" action="index.php?a=<?= $daftar ? 'daftar' : 'masuk' ?>" class="card form">
        <?= tok() ?>
        <?php if ($daftar): ?>
            <label>Nama panggilan<input name="nama" required maxlength="40"></label>
        <?php endif; ?>
        <label>Email<input type="email" name="email" required></label>
        <?php if (!$daftar): ?>
            <label>Kata sandi
                <span class="password-field">
                    <input id="login-password" type="password" name="pass" required minlength="6" autocomplete="current-password">
                    <button class="password-toggle" type="button" data-password-toggle aria-controls="login-password" aria-pressed="false">Lihat kata sandi</button>
                </span>
            </label>
        <?php endif; ?>
        <button class="btn"><?= $daftar ? 'Daftar' : 'Masuk' ?></button>
    </form>

    <p class="sub">
        <?= $daftar
            ? 'Kami akan mengirim tautan verifikasi. Kamu akan membuat kata sandi setelah membuka tautan itu. Sudah punya akun? <a href="index.php?p=masuk">Masuk</a>'
            : 'Belum punya akun? <a href="index.php?p=daftar">Daftar</a>' ?>
    </p>
    <?php
    foot();
}

function v_verifikasi_email(): void
{
    $token = trim((string) ($_GET['token'] ?? ''));
    $validToken = (bool) preg_match('/^[a-f0-9]{64}$/', $token);

    head('Verifikasi email');
    ?>
    <div class="brand"><img src="assets/logo-pengingat.png" alt="">Pengingatku</div>
    <h1>Verifikasi email</h1>
    <?php if ($validToken): ?>
        <p class="sub">Buat kata sandi untuk mengaktifkan akun ini. Gunakan kata sandi yang hanya kamu ketahui.</p>
        <form method="post" action="index.php?a=verifikasi_email" class="card form">
            <?= tok() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>Kata sandi baru<input type="password" name="pass" required minlength="12" autocomplete="new-password"></label>
            <label>Ulangi kata sandi<input type="password" name="pass_confirm" required minlength="12" autocomplete="new-password"></label>
            <button class="btn" type="submit">Verifikasi email dan buat akun</button>
        </form>
    <?php else: ?>
        <p class="sub">Tautan verifikasi tidak valid atau tidak lengkap. Minta tautan baru dari halaman masuk.</p>
        <a class="btn" href="index.php?p=masuk">Kembali ke halaman masuk</a>
    <?php endif; ?>
    <?php
    foot();
}

function item_jadwal(array $item, bool $del = false, int $hari = 0): void
{
    ?>
    <div class="card row cj-<?= e($item['jenis']) ?>">
        <div class="time"><?= e($item['mulai']) ?><br><?= e($item['selesai']) ?></div>
        <div class="dot" style="background:<?= warna($item['mapel']) ?>"></div>
        <div class="grow">
            <div class="t"><?= e($item['mapel']) ?></div>
            <div class="m">
                <span class="chip <?= $item['jenis'] ?>"><?= e($item['jenis']) ?></span>
                <?= e($item['lokasi']) ?>
            </div>
        </div>
        <?php if ($del): ?>
            <form method="post" action="index.php?a=jadwal_del">
                <?= tok() ?>
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <input type="hidden" name="h" value="<?= $hari ?>">
                <button class="x" aria-label="Hapus jadwal">✕</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

function item_tugas(array $task, bool $full): void
{
    $subtasks = q('SELECT * FROM subtugas WHERE tugas_id = ? ORDER BY id', [$task['id']])->fetchAll();
    $doneCount = count(array_filter($subtasks, fn($subtask) => $subtask['selesai']));
    $percent = $subtasks
        ? (int) round($doneCount / count($subtasks) * 100)
        : ($task['selesai'] ? 100 : 0);
    $late = !$task['selesai'] && $task['tenggat'] < date('Y-m-d');
    ?>
    <div class="card tp-<?= e($task['tipe']) ?> <?= $task['selesai'] ? 'done' : '' ?>">
        <div class="row">
            <form method="post" action="index.php?a=tugas_toggle">
                <?= tok() ?>
                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                <input type="hidden" name="back" value="<?= $full ? 'tugas' : 'hari' ?>">
                <button class="chk" aria-label="Tandai selesai">✓</button>
            </form>
            <div class="grow">
                <div class="t"><?= e($task['judul']) ?></div>
                <div class="m">
                    <span class="chip <?= $task['tipe'] === 'ujian' ? 'ujian' : '' ?>">
                        <?= TIPE[$task['tipe']] ?>
                    </span>
                    <?= e($task['mapel']) ?> ·
                    <span class="<?= $late ? 'late' : '' ?>"><?= label_tenggat($task['tenggat']) ?></span>
                </div>
            </div>
            <?php if ($full): ?>
                <form method="post" action="index.php?a=tugas_del" onsubmit="return confirm('Hapus tugas ini?')">
                    <?= tok() ?>
                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                    <button class="x" aria-label="Hapus tugas">✕</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($full): ?>
            <?php if ($subtasks): ?>
                <div class="bar"><i style="width:<?= $percent ?>%"></i></div>
                <div class="subs">
                    <?php foreach ($subtasks as $subtask): ?>
                        <form method="post" action="index.php?a=sub_toggle">
                            <?= tok() ?>
                            <input type="hidden" name="id" value="<?= $subtask['id'] ?>">
                            <button class="sub-i <?= $subtask['selesai'] ? 'on' : '' ?>">
                                <?= $subtask['selesai'] ? '☑' : '☐' ?> <?= e($subtask['judul']) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php?a=sub_add" class="inl">
                <?= tok() ?>
                <input type="hidden" name="id" value="<?= $task['id'] ?>">
                <input name="judul" placeholder="Tambah langkah" maxlength="120" aria-label="Tambah langkah">
                <button class="btn sm">+</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

function v_hari(): void
{
    $name = q('SELECT nama FROM users WHERE id = ?', [uid()])->fetchColumn();
    $dayOfWeek = (int) date('N');
    $tomorrow = $dayOfWeek % 7 + 1;
    $days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $schedule = q(
        'SELECT * FROM jadwal WHERE user_id = ? AND hari = ? ORDER BY mulai',
        [uid(), $dayOfWeek]
    )->fetchAll();
    $tomorrowPractical = q(
        "SELECT * FROM jadwal WHERE user_id = ? AND hari = ? AND jenis = 'praktik' ORDER BY mulai",
        [uid(), $tomorrow]
    )->fetchAll();
    $urgentTasks = q(
        'SELECT * FROM tugas WHERE user_id = ? AND selesai = 0 AND tenggat <= ? ORDER BY tenggat',
        [uid(), date('Y-m-d', strtotime('+3 day'))]
    )->fetchAll();

    head('Hari ini');
    ?>
    <div class="top">
        <div class="ava"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
        <div>
            <p class="sub"><?= $days[$dayOfWeek] ?>, <?= date('j M Y') ?></p>
            <h1>
                <?= (($hour = (int) date('G')) < 11
                    ? 'Selamat pagi'
                    : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'))) ?>,
                <span class="hl"><?= e($name) ?></span>
            </h1>
        </div>
    </div>

    <div class="herorow">
        <?php if ($schedule): ?>
            <div class="next">
                <small>Pelajaran pertama · <?= e($schedule[0]['mulai']) ?></small>
                <b><?= e($schedule[0]['mapel']) ?></b>
                <small><?= e($schedule[0]['lokasi']) ?> · <?= e($schedule[0]['jenis']) ?></small>
            </div>
        <?php endif; ?>
        <div class="stats home-stats">
            <div><b><?= count($schedule) ?></b><span>pelajaran</span></div>
            <div><b><?= count($urgentTasks) ?></b><span>tugas mendesak</span></div>
            <div><b><?= count($tomorrowPractical) ?></b><span>praktik besok</span></div>
        </div>
    </div>

    <div class="cols">
        <section>
            <h2>Jadwal hari ini</h2>
            <?php foreach ($schedule as $item): ?>
                <?php item_jadwal($item); ?>
            <?php endforeach; ?>
            <?php if (!$schedule): ?>
                <div class="empty"><i>✨</i>Tidak ada pelajaran hari ini.</div>
            <?php endif; ?>
        </section>

        <section>
            <h2>Tugas dalam 3 hari</h2>
            <?php foreach ($urgentTasks as $task): ?>
                <?php item_tugas($task, false); ?>
            <?php endforeach; ?>
            <?php if (!$urgentTasks): ?>
                <div class="empty"><i>✨</i>Tidak ada tugas mendesak.</div>
            <?php endif; ?>

            <?php if ($tomorrowPractical): ?>
                <h2>Siapkan untuk besok</h2>
                <div class="card bag">
                    🎒 Praktik besok: <?= e(implode(', ', array_column($tomorrowPractical, 'mapel'))) ?>.
                    Siapkan baju dan perlengkapan praktik.
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    foot();
}

function v_jadwal(): void
{
    $day = (int) ($_GET['h'] ?? date('N'));

    if (!isset(HARI[$day])) {
        $day = 1;
    }

    $schedule = q(
        'SELECT * FROM jadwal WHERE user_id = ? AND hari = ? ORDER BY mulai',
        [uid(), $day]
    )->fetchAll();

    head('Jadwal');
    ?>
    <h1>Jadwal pelajaran</h1>
    <p class="sub">Lihat jadwal pelajaran yang tersimpan untuk setiap hari.</p>

    <div class="days">
        <?php foreach (HARI as $key => $label): ?>
            <a href="index.php?p=jadwal&amp;h=<?= $key ?>" <?= $key === $day ? 'aria-current="true"' : '' ?>>
                <?= substr($label, 0, 3) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <section>
        <?php foreach ($schedule as $item): ?>
            <?php item_jadwal($item, true, $day); ?>
        <?php endforeach; ?>
        <?php if (!$schedule): ?>
            <div class="empty"><i>✨</i>Belum ada jadwal di hari <?= HARI[$day] ?>.</div>
        <?php endif; ?>
    </section>
    <?php
    foot();
}

function v_tugas(): void
{
    $filter = $_GET['f'] ?? 'semua';
    $where = 'user_id = ? AND selesai = 0';
    $params = [uid()];

    if ($filter === 'selesai') {
        $where = 'user_id = ? AND selesai = 1';
    } elseif (array_key_exists($filter, TIPE)) {
        $where .= ' AND tipe = ?';
        $params[] = $filter;
    } else {
        $filter = 'semua';
    }

    $tasks = q("SELECT * FROM tugas WHERE $where ORDER BY tenggat", $params)->fetchAll();

    head('Tugas');
    ?>
    <h1>Tugas dan ujian</h1>
    <div class="filters">
        <?php foreach (['semua' => 'Semua'] + TIPE + ['selesai' => 'Selesai'] as $key => $label): ?>
            <a href="index.php?p=tugas&amp;f=<?= $key ?>" <?= $key === $filter ? 'aria-current="true"' : '' ?>>
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <section>
        <?php foreach ($tasks as $task): ?>
            <?php item_tugas($task, true); ?>
        <?php endforeach; ?>
        <?php if (!$tasks): ?>
            <div class="empty"><i>✨</i>Belum ada tugas di sini.</div>
        <?php endif; ?>
    </section>
    <?php
    foot();
}

function v_storage(): void
{
    $files = q(
        "SELECT f.*, owner.nama AS owner_name, owner.email AS owner_email,
                CASE WHEN f.user_id = ? THEN 1 ELSE 0 END AS is_owner
         FROM drive_files f
         JOIN users owner ON owner.id = f.user_id
         LEFT JOIN drive_file_shares s ON s.file_id = f.id AND s.user_id = ?
         WHERE f.storage_type = 'admin' AND (f.user_id = ? OR s.user_id = ? OR ? = 1)
         ORDER BY f.created_at DESC, f.id DESC",
        [uid(), uid(), uid(), uid(), can_admin() ? 1 : 0]
    )->fetchAll();
    $localFiles = q(
        'SELECT * FROM storage_files WHERE user_id = ? ORDER BY created_at DESC, id DESC',
        [uid()]
    )->fetchAll();
    $totalBytes = array_sum(array_map(static fn($file) => (int) $file['file_size'], $files));
    $connected = google_drive_connected();
    $formatSize = static function (int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        foreach ($units as $unit) {
            if ($size < 1024 || $unit === 'TB') {
                return number_format($size, $size < 10 ? 1 : 0, ',', '.') . ' ' . $unit;
            }
            $size /= 1024;
        }

        return '0 B';
    };

    head('Storage');
    ?>
    <div class="storage-heading">
        <div>
            <p class="sub">Ruang file pribadimu</p>
            <h1>Storage</h1>
        </div>
        <span class="storage-cloud-mark" aria-hidden="true">☁</span>
    </div>
    <p class="sub">Unggah file ke Drive admin. Hanya kamu dan orang yang diberi akses yang dapat melihatnya.</p>

    <section class="drive-card card">
        <div class="drive-logo" aria-hidden="true">G</div>
        <div class="grow">
            <b>Google Drive</b>
            <span>File disimpan menggunakan kuota Drive admin.</span>
        </div>
        <span class="connection-pill"><?= $connected ? 'Terhubung' : 'Belum terhubung' ?></span>
        <p>Pengguna mengunggah melalui situs tanpa login Google. Isi Drive admin tidak ditampilkan kepada pengguna.</p>
        <?php if (can_admin()): ?>
            <a class="btn storage-connect-button" href="index.php?p=storage&amp;connect_drive=1"><?= $connected ? 'Hubungkan ulang Drive' : 'Hubungkan Google Drive admin' ?></a>
        <?php endif; ?>
    </section>

    <div class="stats storage-stats">
        <div><b><?= count($files) ?></b><span>File yang bisa kamu akses</span></div>
        <div><b><?= e($formatSize($totalBytes)) ?></b><span>Ukuran tercatat</span></div>
        <div><b>Drive</b><span>Kuota Google One</span></div>
    </div>

    <section class="storage-upload card">
        <div class="upload-icon" aria-hidden="true">＋</div>
        <div>
            <h2>Unggah file</h2>
            <p>Pilih file dari perangkat. File akan masuk ke Drive admin tanpa meminta login Google.</p>
        </div>
        <?php if ($connected): ?>
            <form method="post" enctype="multipart/form-data" action="index.php?a=storage_add" class="upload-form">
                <?= tok() ?>
                <label for="storage-file-input">Pilih file</label>
                <input id="storage-file-input" type="file" name="file" required>
                <small>Ukuran maksimal 19 MB.</small>
                <button class="btn" type="submit">Unggah ke Storage</button>
            </form>
        <?php else: ?>
            <p class="drive-picker-status" role="status"><?= can_admin() ? 'Hubungkan Drive admin terlebih dahulu untuk mengaktifkan unggahan.' : 'Storage sedang menunggu admin menghubungkan Drive.' ?></p>
        <?php endif; ?>
    </section>

    <div class="storage-list-heading">
        <h2>File yang bisa saya akses</h2>
        <span><?= count($files) ?> file</span>
    </div>
    <?php if ($files): ?>
        <section class="storage-file-list" aria-label="Daftar file tersimpan">
            <?php foreach ($files as $file): ?>
                <article class="card storage-file">
                    <div class="file-symbol" aria-hidden="true">▤</div>
                    <div class="grow">
                        <b title="<?= e($file['original_name']) ?>"><?= e($file['original_name']) ?></b>
                        <span><?= e($formatSize((int) $file['file_size'])) ?> · <?= e(date('j M Y', strtotime($file['created_at']))) ?></span>
                    </div>
                    <span class="file-owner"><?= (int) $file['is_owner'] ? 'Milik saya' : (can_admin() ? 'Diunggah oleh ' . e($file['owner_name']) : 'Dibagikan oleh ' . e($file['owner_name'])) ?></span>
                    <button class="file-preview" type="button" data-file-preview="<?= (int) $file['id'] ?>" data-file-name="<?= e($file['original_name']) ?>" data-file-type="<?= e($file['mime_type']) ?>">Lihat</button>
                    <a class="file-download" href="index.php?p=storage&amp;download=<?= (int) $file['id'] ?>" aria-label="Unduh <?= e($file['original_name']) ?>">Unduh</a>
                    <?php if ((int) $file['is_owner'] || can_admin()): ?>
                        <form method="post" action="index.php?a=drive_file_share" class="file-share-form">
                            <?= tok() ?>
                            <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                            <label class="sr-only" for="share-<?= (int) $file['id'] ?>">Bagikan file ke email akun situs</label>
                            <input id="share-<?= (int) $file['id'] ?>" type="email" name="email" placeholder="Email akun situs" required>
                            <button type="submit">Bagikan</button>
                        </form>
                        <form class="file-delete-form" method="post" action="index.php?a=drive_file_del" onsubmit="return confirm('Pindahkan file ke sampah Drive?')">
                            <?= tok() ?><input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                            <button class="x" type="submit" aria-label="Hapus <?= e($file['original_name']) ?>">✕</button>
                        </form>
                    <?php endif; ?>
                </article>
                <?php
                $shares = q('SELECT s.user_id, u.nama, u.email FROM drive_file_shares s JOIN users u ON u.id = s.user_id WHERE s.file_id = ? ORDER BY u.nama', [$file['id']])->fetchAll();
                if ($shares && ((int) $file['is_owner'] || can_admin())): ?>
                    <div class="file-share-list">
                        <span>Dibagikan ke:</span>
                        <?php foreach ($shares as $share): ?>
                            <form method="post" action="index.php?a=drive_file_unshare">
                                <?= tok() ?><input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $share['user_id'] ?>">
                                <span><?= e($share['nama']) ?> (<?= e($share['email']) ?>)</span>
                                <button type="submit" aria-label="Cabut akses <?= e($share['email']) ?>">Cabut</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <div class="empty"><i>📁</i>Belum ada file yang bisa kamu akses.</div>
    <?php endif; ?>
    <dialog class="storage-preview-dialog" id="storage-preview-dialog" aria-labelledby="storage-preview-title">
        <header>
            <h2 id="storage-preview-title">Pratinjau file</h2>
            <button type="button" data-preview-close aria-label="Tutup pratinjau">Tutup</button>
        </header>
        <div class="storage-preview-content" id="storage-preview-content" aria-live="polite"></div>
    </dialog>
    <?php if ($localFiles): ?>
        <div class="storage-list-heading">
            <h2>File lama di server aplikasi</h2>
            <span><?= count($localFiles) ?> file</span>
        </div>
        <section class="storage-file-list" aria-label="File lama di server aplikasi">
            <?php foreach ($localFiles as $file): ?>
                <article class="card storage-file">
                    <div class="file-symbol" aria-hidden="true">▤</div>
                    <div class="grow">
                        <b title="<?= e($file['original_name']) ?>"><?= e($file['original_name']) ?></b>
                        <span><?= e($formatSize((int) $file['file_size'])) ?> · Server aplikasi</span>
                    </div>
                    <a class="file-download" href="index.php?p=storage&amp;download=<?= (int) $file['id'] ?>">Unduh</a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php
    foot();
}

function v_profil(): void
{
    $user = current_user();
    $name = $user['nama'] ?? '';
    head('Profil');
    ?>
    <h1>Profil</h1>
    <p class="sub">Kelola data akun, jadwal pelajaran, dan tugas dari sini.</p>

    <section class="profile-card card">
        <div class="ava"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
        <div class="grow">
            <b><?= e($name) ?></b>
            <span><?= e($user['email'] ?? '') ?></span>
            <small><?= e($user['role'] ?? 'user') ?></small>
        </div>
    </section>

    <div class="profile-forms">
        <section class="panel">
            <h2>Tambah jadwal</h2>
            <form method="post" action="index.php?a=jadwal_add" class="card form">
                <?= tok() ?>
                <label>Mata pelajaran<input name="mapel" required maxlength="60" placeholder="Administrasi Jaringan"></label>
                <label>Hari
                    <select name="hari">
                        <?php foreach (HARI as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $key === (int) date('N') ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="two">
                    <label>Mulai<input type="time" name="mulai" required value="07:00"></label>
                    <label>Selesai<input type="time" name="selesai" required value="08:30"></label>
                </div>
                <label>Jenis
                    <select name="jenis">
                        <option value="teori">Teori</option>
                        <option value="praktik">Praktik</option>
                    </select>
                </label>
                <label>Lokasi<input name="lokasi" maxlength="60" placeholder="Lab Jaringan"></label>
                <button class="btn">Simpan jadwal</button>
            </form>
        </section>

        <section class="panel">
            <h2>Tambah tugas</h2>
            <form method="post" action="index.php?a=tugas_add" class="card form">
                <?= tok() ?>
                <label>Judul<input name="judul" required maxlength="120" placeholder="Laporan praktik router"></label>
                <label>Mata pelajaran<input name="mapel" required maxlength="60"></label>
                <label>Jenis
                    <select name="tipe">
                        <?php foreach (TIPE as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Tenggat<input type="date" name="tenggat" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></label>
                <button class="btn">Simpan tugas</button>
            </form>
        </section>
    </div>
    <?php
    foot();
}

function v_admin(): void
{
    if (!can_admin()) {
        go('hari', 'Akses ditolak.');
    }

    $term = trim((string) ($_GET['q'] ?? ''));
    $where = '';
    $params = [];

    if ($term !== '') {
        $where = 'WHERE nama LIKE ? OR email LIKE ?';
        $params = ['%' . $term . '%', '%' . $term . '%'];
    }

    $users = q(
        "SELECT id, nama, email, role, active, created_at FROM users $where ORDER BY id DESC",
        $params
    )->fetchAll();

    global $dbDriver;

    $todayExpr = $dbDriver === 'sqlite'
        ? "date(created_at, '+7 hours') = date('now', '+7 hours')"
        : 'DATE(created_at) = CURDATE()';
    $stats = q(
        "SELECT COUNT(*) total, SUM(active = 1) aktif, SUM(active = 0) nonaktif, SUM($todayExpr) hari_ini FROM users"
    )->fetch();

    head('Manajemen akun');
    ?>
    <div class="admin-head">
        <div>
            <p class="sub">Panel <?= is_superadmin() ? 'superadmin' : 'admin' ?></p>
            <h1>Manajemen akun</h1>
        </div>
        <span class="role-pill"><?= is_superadmin() ? 'SUPERADMIN' : 'ADMIN' ?></span>
    </div>

    <p class="sub">Pantau akun yang terdaftar dan kelola aksesnya.</p>
    <div class="stats admin-stats">
        <div><b><?= (int) $stats['total'] ?></b><span>Total akun</span></div>
        <div><b><?= (int) $stats['hari_ini'] ?></b><span>Daftar hari ini</span></div>
        <div><b><?= (int) $stats['aktif'] ?></b><span>Akun aktif</span></div>
        <div><b><?= (int) $stats['nonaktif'] ?></b><span>Nonaktif</span></div>
    </div>

    <form class="searchbar" method="get" action="index.php">
        <input type="hidden" name="p" value="admin">
        <input type="search" name="q" value="<?= e($term) ?>" placeholder="Cari nama atau email…" aria-label="Cari nama atau email">
        <button class="btn">Cari akun</button>
        <?php if ($term !== ''): ?>
            <a class="clear-search" href="index.php?p=admin">Hapus pencarian</a>
        <?php endif; ?>
    </form>

    <div class="account-list">
        <div class="account-list-head">
            <h2><?= $term !== '' ? 'Hasil pencarian' : 'Semua akun' ?></h2>
            <span><?= count($users) ?> akun</span>
        </div>

        <?php foreach ($users as $user): ?>
            <?php $createdAt = strtotime($user['created_at'] . ($dbDriver === 'sqlite' ? ' UTC' : '')); ?>
            <article class="card account-card">
                <div class="account-summary">
                    <div class="ava"><?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?></div>
                    <div class="grow">
                        <b><?= e($user['nama']) ?></b>
                        <span><?= e($user['email']) ?></span>
                        <small>Terdaftar <?= e(date('j M Y, H:i', $createdAt)) ?></small>
                    </div>
                    <span class="status <?= $user['active'] ? 'is-active' : 'is-off' ?>">
                        <?= $user['active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>

                <div class="account-controls">
                    <?php if (is_superadmin() || $user['role'] === 'user'): ?>
                        <form method="post" action="index.php?a=akun_simpan" class="account-edit">
                            <?= tok() ?>
                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                            <label>Nama<input name="nama" value="<?= e($user['nama']) ?>" required maxlength="40"></label>
                            <label>Email<input type="email" name="email" value="<?= e($user['email']) ?>" required></label>
                            <button class="btn sm">Simpan</button>
                        </form>
                    <?php else: ?>
                        <p class="account-note">Hanya superadmin yang dapat mengubah akun admin.</p>
                    <?php endif; ?>

                    <?php if ((int) $user['id'] !== uid() && (is_superadmin() || $user['role'] === 'user')): ?>
                        <details class="account-password-reset">
                            <summary>Ganti kata sandi akun</summary>
                            <form method="post" action="index.php?a=akun_password">
                                <?= tok() ?>
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <label>Kata sandi baru<input type="password" name="pass" required minlength="12" autocomplete="new-password"></label>
                                <label>Ulangi kata sandi<input type="password" name="pass_confirm" required minlength="12" autocomplete="new-password"></label>
                                <button class="btn sm">Ganti kata sandi</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <div class="account-actions">
                        <?php if (is_superadmin()): ?>
                            <form method="post" action="index.php?a=akun_role" class="role-form">
                                <?= tok() ?>
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <label>Peran
                                    <select name="role" <?= (int) $user['id'] === uid() ? 'disabled' : '' ?>>
                                        <?php foreach (['user' => 'User', 'admin' => 'Admin', 'superadmin' => 'Superadmin'] as $roleKey => $roleLabel): ?>
                                            <option value="<?= $roleKey ?>" <?= $user['role'] === $roleKey ? 'selected' : '' ?>>
                                                <?= $roleLabel ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php if ((int) $user['id'] !== uid()): ?>
                                    <button class="btn sm">Ubah peran</button>
                                <?php endif; ?>
                            </form>
                        <?php elseif ($user['role'] === 'user'): ?>
                            <form method="post" action="index.php?a=akun_role" class="role-form">
                                <?= tok() ?>
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <input type="hidden" name="role" value="admin">
                                <button class="btn sm">Jadikan admin</button>
                            </form>
                        <?php else: ?>
                            <span class="role-pill small"><?= e(strtoupper($user['role'])) ?></span>
                        <?php endif; ?>

                        <?php if ((int) $user['id'] !== uid() && (is_superadmin() || $user['role'] === 'user')): ?>
                            <div class="danger-actions">
                                <form method="post" action="index.php?a=akun_status">
                                    <?= tok() ?>
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button class="btn sm secondary">
                                        <?= $user['active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                                <form method="post" action="index.php?a=akun_hapus" onsubmit="return confirm('Hapus akun ini beserta semua jadwal dan tugasnya?')">
                                    <?= tok() ?>
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button class="btn sm danger">Hapus akun</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$users): ?>
            <div class="empty">
                <i>🔎</i>
                <?= $term !== '' ? 'Akun tidak ditemukan.' : 'Belum ada akun terdaftar.' ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    foot();
}
