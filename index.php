<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/actions.php';
require __DIR__ . '/app/views.php';
require __DIR__ . '/app/google_drive.php';

if (uid() && (!current_user() || !(int) current_user()['active'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    go('masuk', 'Akun ini tidak aktif atau sudah dihapus.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    act($_GET['a'] ?? '');
}

$p = $_GET['p'] ?? 'hari';

if ($p === 'storage' && isset($_GET['download'])) {
    download_drive_file((int) $_GET['download']);
}

if ($p === 'storage' && isset($_GET['preview'])) {
    preview_drive_file((int) $_GET['preview']);
}

if (!uid() && !in_array($p, ['masuk', 'daftar', 'verifikasi'], true)) {
    go('masuk');
}

if ($p === 'storage' && isset($_GET['connect_drive'])) {
    if (!can_admin()) {
        go('storage', 'Hanya admin yang dapat menghubungkan Google Drive.');
    }
    try {
        header('Location: ' . google_drive_authorize_url());
        exit;
    } catch (Throwable $error) {
        go('storage', $error->getMessage());
    }
}

match ($p) {
    'masuk' => v_auth(false),
    'daftar' => v_auth(true),
    'verifikasi' => v_verifikasi_email(),
    'jadwal' => v_jadwal(),
    'tugas' => v_tugas(),
    'storage' => v_storage(),
    'profil' => v_profil(),
    'admin' => v_admin(),
    default => v_hari(),
};
