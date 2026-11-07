<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/google_drive.php';

if (!uid() || !can_admin()) {
    go('masuk', 'Hanya admin yang dapat menghubungkan Google Drive.');
}

$expectedState = $_SESSION['google_drive_oauth_state'] ?? '';
unset($_SESSION['google_drive_oauth_state']);
if ($expectedState === '' || !hash_equals($expectedState, (string) ($_GET['state'] ?? ''))) {
    go('storage', 'Sesi penghubung Google tidak valid. Coba hubungkan ulang.');
}

if (isset($_GET['error']) || empty($_GET['code'])) {
    go('storage', 'Izin Google Drive dibatalkan atau tidak diberikan.');
}

try {
    google_drive_exchange_code((string) $_GET['code']);
    go('storage', 'Drive admin berhasil dihubungkan. Pengguna situs tidak perlu login Google.');
} catch (Throwable $error) {
    error_log('Google Drive OAuth callback: ' . $error->getMessage());
    go('storage', 'Drive belum berhasil dihubungkan. Periksa pengaturan OAuth lalu coba lagi.');
}
