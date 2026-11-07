<?php

function owns_tugas(int $id): bool
{
    return (bool) q(
        'SELECT 1 FROM tugas WHERE id = ? AND user_id = ?',
        [$id, uid()]
    )->fetch();
}

function download_storage(int $id): never
{
    if (!uid()) {
        go('masuk');
    }

    $file = q(
        'SELECT * FROM storage_files WHERE id = ? AND user_id = ?',
        [$id, uid()]
    )->fetch();
    $path = __DIR__ . '/../data/storage/' . ($file['stored_name'] ?? '');

    if (!$file || !is_file($path)) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }

    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function download_drive_file(int $id): never
{
    if (!uid()) {
        go('masuk');
    }
    $file = q(
        "SELECT f.* FROM drive_files f
         LEFT JOIN drive_file_shares s ON s.file_id = f.id AND s.user_id = ?
         WHERE f.id = ? AND f.storage_type = 'admin' AND (f.user_id = ? OR s.user_id = ? OR ? = 1)",
        [uid(), $id, uid(), uid(), can_admin() ? 1 : 0]
    )->fetch();
    if (!$file) {
        download_storage($id);
    }

    try {
        [, $bytes] = google_drive_api('files/' . rawurlencode($file['drive_id']) . '?alt=media');
    } catch (Throwable $error) {
        error_log('Google Drive download: ' . $error->getMessage());
        http_response_code(502);
        exit('File gagal diambil dari Google Drive.');
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . strlen($bytes));
    header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit;
}

function preview_drive_file(int $id): never
{
    if (!uid()) {
        go('masuk');
    }
    $file = q(
        "SELECT f.* FROM drive_files f
         LEFT JOIN drive_file_shares s ON s.file_id = f.id AND s.user_id = ?
         WHERE f.id = ? AND f.storage_type = 'admin' AND (f.user_id = ? OR s.user_id = ? OR ? = 1)",
        [uid(), $id, uid(), uid(), can_admin() ? 1 : 0]
    )->fetch();
    if (!$file) {
        http_response_code(404);
        exit('File tidak ditemukan atau kamu tidak memiliki akses.');
    }

    google_drive_stream_media($file['drive_id'], $file['original_name'], $file['mime_type']);
}

function send_email_verification(string $email, string $name, string $token): void
{
    $mailConfig = is_file(__DIR__ . '/mail.local.php')
        ? require __DIR__ . '/mail.local.php'
        : [];
    $smtpUser = trim((string) (getenv('PENGINGAT_SMTP_USER') ?: ($mailConfig['smtp_user'] ?? '')));
    $smtpPassword = preg_replace('/\s+/', '', (string) (getenv('PENGINGAT_SMTP_APP_PASSWORD') ?: ($mailConfig['smtp_app_password'] ?? '')));
    $baseUrl = rtrim(trim((string) (getenv('PENGINGAT_BASE_URL') ?: ($mailConfig['base_url'] ?? ''))), '/');
    $parts = parse_url($baseUrl);

    if (!filter_var($smtpUser, FILTER_VALIDATE_EMAIL) || $smtpPassword === '' || !$parts || empty($parts['host'])) {
        throw new RuntimeException('Konfigurasi Gmail SMTP belum lengkap.');
    }
    if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        throw new RuntimeException('URL situs untuk email verifikasi tidak valid.');
    }
    if (
        strtolower((string) $parts['scheme']) !== 'https'
        && !in_array(strtolower((string) $parts['host']), ['localhost', '127.0.0.1', '::1'], true)
    ) {
        throw new RuntimeException('Email verifikasi di situs publik membutuhkan PENGINGAT_BASE_URL HTTPS.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL harus diaktifkan untuk mengirim email.');
    }

    $link = $baseUrl . '/index.php?p=verifikasi&token=' . rawurlencode($token);
    $safeName = preg_replace('/[\r\n]+/', ' ', $name) ?: 'pengguna';
    $body = "Halo {$safeName},\r\n\r\n"
        . "Buka tautan berikut untuk memverifikasi email dan membuat kata sandi akun Pengingatku:\r\n"
        . $link . "\r\n\r\n"
        . "Tautan ini berlaku selama 24 jam. Jika kamu tidak mendaftar, abaikan email ini.\r\n";
    $message = implode("\r\n", [
        'Date: ' . date(DATE_RFC2822),
        'From: Pengingatku <' . $smtpUser . '>',
        'To: <' . $email . '>',
        'Subject: Verifikasi email Pengingatku',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $body,
    ]);
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('Email verifikasi tidak dapat disiapkan.');
    }
    fwrite($stream, $message);
    rewind($stream);

    $curl = curl_init('smtp://smtp.gmail.com:587');
    curl_setopt_array($curl, [
        CURLOPT_USERNAME => $smtpUser,
        CURLOPT_PASSWORD => $smtpPassword,
        CURLOPT_USE_SSL => CURLUSESSL_ALL,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAIL_FROM => '<' . $smtpUser . '>',
        CURLOPT_MAIL_RCPT => ['<' . $email . '>'],
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $stream,
        CURLOPT_INFILESIZE => strlen($message),
        CURLOPT_READFUNCTION => static fn($curl, $handle, int $length) => fread($handle, $length),
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
    ]);
    $sent = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($stream);

    if ($sent === false) {
        error_log('Gmail SMTP: ' . $error);
        throw new RuntimeException('Gmail gagal mengirim email verifikasi. Periksa konfigurasi SMTP server.');
    }
}

function create_email_verification(int $userId, string $email, string $name): void
{
    $token = bin2hex(random_bytes(32));
    q('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
    q(
        'INSERT INTO email_verifications(user_id, token_hash, expires_at) VALUES(?, ?, ?)',
        [$userId, hash('sha256', $token), time() + 86400]
    );
    send_email_verification($email, $name, $token);
}

function login_attempt_key(string $email): string
{
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return hash('sha256', strtolower(trim($email)) . "\0" . $ipAddress);
}

function login_retry_after(string $attemptKey): int
{
    $now = time();
    $window = 15 * 60;
    $cutoff = $now - $window;
    q('DELETE FROM login_attempts WHERE attempted_at < ?', [$cutoff]);
    $attempts = q(
        'SELECT COUNT(*) AS total, MIN(attempted_at) AS first_attempt FROM login_attempts WHERE attempt_key = ? AND attempted_at >= ?',
        [$attemptKey, $cutoff]
    )->fetch();

    if ((int) ($attempts['total'] ?? 0) < 5) {
        return 0;
    }

    return max(1, $window - ($now - (int) $attempts['first_attempt']));
}

function login_throttled_message(int $seconds): string
{
    $minutes = max(1, (int) ceil($seconds / 60));
    return 'Terlalu banyak percobaan login. Coba lagi dalam ' . $minutes . ' menit.';
}

function act(string $a): void
{
    $P = fn($key) => trim((string) ($_POST[$key] ?? ''));

    if ($a === 'daftar') {
        $name = mb_substr($P('nama'), 0, 40);
        $email = strtolower($P('email'));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            go('daftar', 'Isi nama dan alamat email yang valid.');
        }

        if (q('SELECT 1 FROM users WHERE lower(email) = ?', [$email])->fetch()) {
            go('daftar', 'Email sudah terdaftar.');
        }

        global $db;
        $db->beginTransaction();
        try {
            $randomPassword = bin2hex(random_bytes(32));
            q(
                'INSERT INTO users(nama, email, pass, email_verified) VALUES(?, ?, ?, 0)',
                [$name, $email, password_hash($randomPassword, PASSWORD_DEFAULT)]
            );
            $newId = (int) $db->lastInsertId();
            $rootEmail = strtolower(trim((string) getenv('PENGINGAT_SUPERADMIN_EMAIL')));

            if ($rootEmail !== '' && $email === $rootEmail) {
                q("UPDATE users SET role = 'superadmin' WHERE id = ?", [$newId]);
            }

            create_email_verification($newId, $email, $name);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Email verification signup: ' . $error->getMessage());
            go('daftar', 'Email verifikasi gagal dikirim. Coba lagi nanti atau hubungi administrator.');
        }

        go('masuk', 'Tautan verifikasi dikirim. Buka emailmu untuk memverifikasi alamat dan membuat kata sandi.');
    }

    if ($a === 'verifikasi_kirim') {
        $email = strtolower($P('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = q('SELECT id, nama FROM users WHERE lower(email) = ? AND active = 1 AND email_verified = 0', [$email])->fetch();
            if ($user) {
                global $db;
                $db->beginTransaction();
                try {
                    create_email_verification((int) $user['id'], $email, $user['nama']);
                    $db->commit();
                } catch (Throwable $error) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('Email verification resend: ' . $error->getMessage());
                }
            }
        }
        go('masuk', 'Jika akun tersebut belum diverifikasi, tautan baru akan dikirim ke emailnya.');
    }

    if ($a === 'verifikasi_email') {
        $token = $P('token');
        $password = (string) ($_POST['pass'] ?? '');
        $confirmation = (string) ($_POST['pass_confirm'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            go('masuk', 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.');
        }
        if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
            go('verifikasi&token=' . rawurlencode($token), 'Kata sandi minimal 12 karakter dan kedua isian harus sama.');
        }

        $verification = q(
            'SELECT v.user_id, v.expires_at FROM email_verifications v
             JOIN users u ON u.id = v.user_id
             WHERE v.token_hash = ? AND u.active = 1',
            [hash('sha256', $token)]
        )->fetch();
        if (!$verification || (int) $verification['expires_at'] < time()) {
            if ($verification) {
                q('DELETE FROM email_verifications WHERE user_id = ?', [$verification['user_id']]);
            }
            go('masuk', 'Tautan verifikasi tidak valid atau sudah kedaluwarsa. Minta tautan baru.');
        }

        global $db;
        $db->beginTransaction();
        try {
            q('UPDATE users SET email_verified = 1, pass = ? WHERE id = ? AND active = 1', [
                password_hash($password, PASSWORD_DEFAULT),
                $verification['user_id'],
            ]);
            q('DELETE FROM email_verifications WHERE user_id = ?', [$verification['user_id']]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Email verification confirmation: ' . $error->getMessage());
            go('masuk', 'Verifikasi gagal diproses. Coba lagi.');
        }
        go('masuk', 'Email berhasil diverifikasi. Silakan masuk dengan email dan kata sandi baru.');
    }

    if ($a === 'masuk') {
        $email = strtolower($P('email'));
        $attemptKey = login_attempt_key($email);
        $retryAfter = login_retry_after($attemptKey);
        if ($retryAfter > 0) {
            go('masuk', login_throttled_message($retryAfter));
        }

        $user = q('SELECT * FROM users WHERE lower(email) = ?', [$email])->fetch();

        if (!$user || !password_verify($P('pass'), $user['pass'])) {
            q('INSERT INTO login_attempts(attempt_key, attempted_at) VALUES(?, ?)', [$attemptKey, time()]);
            $retryAfter = login_retry_after($attemptKey);
            if ($retryAfter > 0) {
                go('masuk', login_throttled_message($retryAfter));
            }
            go('masuk', 'Email atau kata sandi salah.');
        }

        q('DELETE FROM login_attempts WHERE attempt_key = ?', [$attemptKey]);

        if (!(int) $user['active']) {
            go('masuk', 'Akun ini dinonaktifkan. Hubungi administrator.');
        }

        if (!(int) $user['email_verified']) {
            go('masuk', 'Email belum diverifikasi. Periksa inbox atau kirim ulang tautan verifikasi.');
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        go('hari');
    }

    if ($a === 'keluar') {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: index.php?p=masuk', true, 303);
        exit;
    }

    if (!uid()) {
        go('masuk');
    }

    switch ($a) {
        case 'akun_simpan':
            if (!can_admin()) {
                go('hari', 'Akses ditolak.');
            }

            $id = (int) $P('id');
            $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();

            if (!$target || (!is_superadmin() && $target['role'] !== 'user')) {
                go('admin', 'Akun tidak ditemukan atau Anda tidak berhak mengelolanya.');
            }

            $nama = mb_substr($P('nama'), 0, 40);
            $email = strtolower($P('email'));

            if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                go('admin', 'Nama dan email harus diisi dengan benar.');
            }

            if (q('SELECT 1 FROM users WHERE lower(email) = ? AND id <> ?', [$email, $id])->fetch()) {
                go('admin', 'Email sudah digunakan akun lain.');
            }

            q('UPDATE users SET nama = ?, email = ? WHERE id = ?', [$nama, $email, $id]);
            go('admin', 'Data akun diperbarui.');

        case 'akun_password':
            if (!can_admin()) {
                go('hari', 'Akses ditolak.');
            }

            $id = (int) $P('id');
            $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();

            if (!$target || $id === uid() || (!is_superadmin() && $target['role'] !== 'user')) {
                go('admin', 'Akun tidak ditemukan atau Anda tidak berhak mengubah kata sandinya.');
            }

            $password = (string) ($_POST['pass'] ?? '');
            $confirmation = (string) ($_POST['pass_confirm'] ?? '');
            if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
                go('admin', 'Kata sandi minimal 12 karakter dan kedua isian harus sama.');
            }

            q('UPDATE users SET pass = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            go('admin', 'Kata sandi akun berhasil diganti.');

        case 'akun_status':
            if (!can_admin()) {
                go('hari', 'Akses ditolak.');
            }

            $id = (int) $P('id');
            $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();

            if (!$target || $id === uid() || (!is_superadmin() && $target['role'] !== 'user')) {
                go('admin', 'Akun tidak ditemukan atau Anda tidak berhak mengelolanya.');
            }

            q('UPDATE users SET active = 1 - active WHERE id = ?', [$id]);
            go('admin', (int) $target['active'] ? 'Akun dinonaktifkan.' : 'Akun diaktifkan.');

        case 'akun_role':
            if (!can_admin()) {
                go('admin', 'Akses ditolak.');
            }

            $id = (int) $P('id');
            $newRole = $P('role');
            $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();

            $canChangeRole = $target && (
                is_superadmin()
                    ? in_array($newRole, ['user', 'admin', 'superadmin'], true)
                    : ($target['role'] === 'user' && $newRole === 'admin')
            );

            if (!$target || $id === uid() || !$canChangeRole) {
                go('admin', 'Perubahan peran tidak valid.');
            }

            if (
                $target['role'] === 'superadmin'
                && $newRole !== 'superadmin'
                && (int) q("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn() < 2
            ) {
                go('admin', 'Perlu ada setidaknya satu superadmin.');
            }

            q('UPDATE users SET role = ? WHERE id = ?', [$newRole, $id]);
            go('admin', 'Peran akun diperbarui.');

        case 'akun_hapus':
            if (!can_admin()) {
                go('hari', 'Akses ditolak.');
            }

            $id = (int) $P('id');
            $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();

            if (!$target || $id === uid() || (!is_superadmin() && $target['role'] !== 'user')) {
                go('admin', 'Akun tidak ditemukan atau Anda tidak berhak menghapusnya.');
            }

            if (
                $target['role'] === 'superadmin'
                && (int) q("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn() < 2
            ) {
                go('admin', 'Superadmin terakhir tidak dapat dihapus.');
            }

            foreach (q('SELECT stored_name FROM storage_files WHERE user_id = ?', [$id])->fetchAll() as $storedFile) {
                $path = __DIR__ . '/../data/storage/' . $storedFile['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
            }

            q('DELETE FROM users WHERE id = ?', [$id]);
            go('admin', 'Akun dan seluruh data terkait dihapus.');

        case 'jadwal_add':
            $hari = (int) $P('hari');
            $jenis = $P('jenis') === 'praktik' ? 'praktik' : 'teori';

            if (
                $P('mapel') === ''
                || !isset(HARI[$hari])
                || !valid_time($P('mulai'))
                || !valid_time($P('selesai'))
                || $P('selesai') <= $P('mulai')
            ) {
                go('profil', 'Data jadwal belum lengkap atau jam tidak sesuai.');
            }

            q(
                'INSERT INTO jadwal(user_id, mapel, hari, mulai, selesai, jenis, lokasi) VALUES(?, ?, ?, ?, ?, ?, ?)',
                [
                    uid(),
                    mb_substr($P('mapel'), 0, 60),
                    $hari,
                    $P('mulai'),
                    $P('selesai'),
                    $jenis,
                    mb_substr($P('lokasi'), 0, 60),
                ]
            );

            go('profil', 'Jadwal ditambahkan.');

        case 'jadwal_del':
            q('DELETE FROM jadwal WHERE id = ? AND user_id = ?', [(int) $P('id'), uid()]);
            go('jadwal&h=' . (int) $P('h'), 'Jadwal dihapus.');

        case 'tugas_add':
            $tipe = array_key_exists($P('tipe'), TIPE) ? $P('tipe') : 'pr';

            if (
                $P('judul') === ''
                || $P('mapel') === ''
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $P('tenggat'))
            ) {
                go('profil', 'Isi judul, mata pelajaran, dan tenggat.');
            }

            q(
                'INSERT INTO tugas(user_id, mapel, judul, tipe, tenggat) VALUES(?, ?, ?, ?, ?)',
                [
                    uid(),
                    mb_substr($P('mapel'), 0, 60),
                    mb_substr($P('judul'), 0, 120),
                    $tipe,
                    $P('tenggat'),
                ]
            );

            go('profil', 'Tugas disimpan.');

        case 'storage_add':
            $upload = $_FILES['file'] ?? null;

            if (!$upload || !isset($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK) {
                go('storage', 'Pilih file yang berhasil diunggah.');
            }

            if ((int) $upload['size'] > 19 * 1024 * 1024) {
                go('storage', 'Ukuran file maksimal 19 MB.');
            }

            $originalName = trim(basename(str_replace('\\', '/', (string) $upload['name'])));
            $originalName = mb_substr($originalName, 0, 255);

            if ($originalName === '' || !is_uploaded_file($upload['tmp_name'])) {
                go('storage', 'File tidak valid. Coba pilih file lain.');
            }

            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: 'application/octet-stream';
            try {
                $driveFile = google_drive_upload($upload, $originalName, $mimeType);
                q(
                    'INSERT INTO drive_files(user_id, drive_id, original_name, mime_type, file_size, storage_type) VALUES(?, ?, ?, ?, ?, \'admin\')',
                    [uid(), $driveFile['id'], $driveFile['name'] ?? $originalName, $driveFile['mimeType'] ?? $mimeType, (int) ($driveFile['size'] ?? $upload['size'])]
                );
            } catch (Throwable $error) {
                error_log('Google Drive upload: ' . $error->getMessage());
                go('storage', $error->getMessage());
            }

            go('storage', 'File berhasil disimpan di Drive admin.');

        case 'storage_del':
            $file = q(
                'SELECT id, stored_name FROM storage_files WHERE id = ? AND user_id = ?',
                [(int) $P('id'), uid()]
            )->fetch();

            if ($file) {
                $path = __DIR__ . '/../data/storage/' . $file['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
                q('DELETE FROM storage_files WHERE id = ? AND user_id = ?', [$file['id'], uid()]);
            }

            go('storage', $file ? 'File dihapus dari penyimpanan.' : 'File tidak ditemukan.');

        case 'drive_file_save':
            go('storage');

        case 'drive_file_forget':
            go('storage');

        case 'drive_file_del':
            $file = q("SELECT * FROM drive_files WHERE id = ? AND storage_type = 'admin'", [(int) $P('id')])->fetch();
            if (!$file || ((int) $file['user_id'] !== uid() && !can_admin())) {
                go('storage', 'File tidak ditemukan atau Anda tidak berhak menghapusnya.');
            }
            try {
                google_drive_api('files/' . rawurlencode($file['drive_id']), 'PATCH', json_encode(['trashed' => true]), ['Content-Type: application/json']);
                q('DELETE FROM drive_files WHERE id = ?', [$file['id']]);
                go('storage', 'File dipindahkan ke sampah Drive.');
            } catch (Throwable $error) {
                error_log('Google Drive trash: ' . $error->getMessage());
                go('storage', 'File gagal dihapus dari Drive.');
            }

        case 'drive_file_share':
        case 'drive_file_unshare':
            $fileId = (int) $P('id');
            $file = q("SELECT * FROM drive_files WHERE id = ? AND storage_type = 'admin'", [$fileId])->fetch();
            if (!$file || ((int) $file['user_id'] !== uid() && !can_admin())) {
                go('storage', 'File tidak ditemukan atau Anda tidak berhak mengelolanya.');
            }
            $email = strtolower($P('email'));
            if ($a === 'drive_file_share') {
                $recipient = q('SELECT id FROM users WHERE lower(email) = ? AND active = 1', [$email])->fetch();
                if (!$recipient || (int) $recipient['id'] === (int) $file['user_id']) {
                    go('storage', 'Pilih email akun situs aktif yang bukan pemilik file.');
                }
                if (!q('SELECT 1 FROM drive_file_shares WHERE file_id = ? AND user_id = ?', [$fileId, $recipient['id']])->fetch()) {
                    q('INSERT INTO drive_file_shares(file_id, user_id, shared_by) VALUES(?, ?, ?)', [$fileId, $recipient['id'], uid()]);
                }
                go('storage', 'File dibagikan ke ' . $email . '.');
            }
            $recipientId = (int) $P('user_id');
            q('DELETE FROM drive_file_shares WHERE file_id = ? AND user_id = ?', [$fileId, $recipientId]);
            go('storage', 'Akses file dicabut.');

        case 'tugas_toggle':
            $id = (int) $P('id');

            if (!owns_tugas($id)) {
                go('tugas');
            }

            $now = (int) q('SELECT selesai FROM tugas WHERE id = ?', [$id])->fetchColumn() ? 0 : 1;
            q('UPDATE tugas SET selesai = ? WHERE id = ?', [$now, $id]);

            if ($now) {
                q('UPDATE subtugas SET selesai = 1 WHERE tugas_id = ?', [$id]);
            }

            go(
                $P('back') === 'hari' ? 'hari' : 'tugas',
                $now ? 'Selesai! Satu tugas lagi beres.' : ''
            );

        case 'tugas_del':
            if (owns_tugas((int) $P('id'))) {
                q('DELETE FROM tugas WHERE id = ?', [(int) $P('id')]);
            }

            go('tugas', 'Tugas dihapus.');

        case 'sub_add':
            if (owns_tugas((int) $P('id')) && $P('judul') !== '') {
                q(
                    'INSERT INTO subtugas(tugas_id, judul) VALUES(?, ?)',
                    [(int) $P('id'), mb_substr($P('judul'), 0, 120)]
                );
            }

            go('tugas');

        case 'sub_toggle':
            $subtask = q(
                'SELECT s.id, s.tugas_id FROM subtugas s JOIN tugas t ON t.id = s.tugas_id WHERE s.id = ? AND t.user_id = ?',
                [(int) $P('id'), uid()]
            )->fetch();

            if ($subtask) {
                q('UPDATE subtugas SET selesai = 1 - selesai WHERE id = ?', [$subtask['id']]);

                $progress = q(
                    'SELECT COUNT(*) n, SUM(selesai) d FROM subtugas WHERE tugas_id = ?',
                    [$subtask['tugas_id']]
                )->fetch();

                q(
                    'UPDATE tugas SET selesai = ? WHERE id = ?',
                    [
                        $progress['n'] > 0 && $progress['n'] == $progress['d'] ? 1 : 0,
                        $subtask['tugas_id'],
                    ]
                );
            }

            go('tugas');
    }

    go('hari');
}
