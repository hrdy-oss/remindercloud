<?php

date_default_timezone_set('Asia/Jakarta');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('pneo_sid');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$localDbConfig = is_file(__DIR__ . '/database.local.php')
    ? require __DIR__ . '/database.local.php'
    : [];

$setting = static function (string $env, string $key, string $default) use ($localDbConfig): string {
    $value = getenv($env);

    return (string) (
        $value !== false && $value !== ''
            ? $value
            : ($localDbConfig[$key] ?? $default)
    );
};

$dbDriver = strtolower(trim($setting('PENGINGAT_DB_DRIVER', 'driver', 'mysql')));
$dbHost = $setting('PENGINGAT_DB_HOST', 'host', '127.0.0.1');
$dbName = $setting('PENGINGAT_DB_NAME', 'database', 'pengingat_neo');
$dbUser = $setting('PENGINGAT_DB_USER', 'username', 'pengingat_app');
$dbPass = $setting('PENGINGAT_DB_PASS', 'password', '');

if ($dbDriver === 'sqlite') {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/siaga.sqlite');
} elseif ($dbDriver === 'mysql') {
    $db = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
} else {
    throw new RuntimeException('PENGINGAT_DB_DRIVER harus mysql atau sqlite.');
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

if ($dbDriver === 'sqlite') {
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users(
            id INTEGER PRIMARY KEY,
            nama TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            pass TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            active INTEGER NOT NULL DEFAULT 1,
            email_verified INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS jadwal(
            id INTEGER PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            mapel TEXT NOT NULL,
            hari INT NOT NULL,
            mulai TEXT NOT NULL,
            selesai TEXT NOT NULL,
            jenis TEXT NOT NULL DEFAULT 'teori',
            lokasi TEXT
        );
        CREATE TABLE IF NOT EXISTS tugas(
            id INTEGER PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            mapel TEXT NOT NULL,
            judul TEXT NOT NULL,
            tipe TEXT NOT NULL DEFAULT 'pr',
            tenggat TEXT NOT NULL,
            selesai INT NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS subtugas(
            id INTEGER PRIMARY KEY,
            tugas_id INT NOT NULL REFERENCES tugas(id) ON DELETE CASCADE,
            judul TEXT NOT NULL,
            selesai INT NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS storage_files(
            id INTEGER PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            original_name TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            file_size INT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS drive_files(
            id INTEGER PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            drive_id TEXT NOT NULL,
            original_name TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            file_size INT NOT NULL DEFAULT 0,
            storage_type TEXT NOT NULL DEFAULT 'admin',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, drive_id)
        );"
    );
    $db->exec("CREATE TABLE IF NOT EXISTS drive_file_shares(
        file_id INTEGER NOT NULL REFERENCES drive_files(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        shared_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(file_id, user_id)
    );
    CREATE TABLE IF NOT EXISTS google_drive_tokens(
        id INTEGER PRIMARY KEY CHECK(id = 1),
        token_json TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS email_verifications(
        user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );");
} else {
    $db->exec("SET time_zone = '+07:00'");
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users(
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(40) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            pass VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            active TINYINT(1) NOT NULL DEFAULT 1,
            email_verified TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS jadwal(
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            mapel VARCHAR(60) NOT NULL,
            hari TINYINT NOT NULL,
            mulai CHAR(5) NOT NULL,
            selesai CHAR(5) NOT NULL,
            jenis VARCHAR(20) NOT NULL DEFAULT 'teori',
            lokasi VARCHAR(60),
            CONSTRAINT fk_jadwal_user FOREIGN KEY(user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS tugas(
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            mapel VARCHAR(60) NOT NULL,
            judul VARCHAR(120) NOT NULL,
            tipe VARCHAR(20) NOT NULL DEFAULT 'pr',
            tenggat DATE NOT NULL,
            selesai TINYINT(1) NOT NULL DEFAULT 0,
            CONSTRAINT fk_tugas_user FOREIGN KEY(user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS subtugas(
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tugas_id INT UNSIGNED NOT NULL,
            judul VARCHAR(120) NOT NULL,
            selesai TINYINT(1) NOT NULL DEFAULT 0,
            CONSTRAINT fk_subtugas_tugas FOREIGN KEY(tugas_id)
                REFERENCES tugas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS storage_files(
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name CHAR(64) NOT NULL,
            mime_type VARCHAR(150) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_storage_user (user_id),
            CONSTRAINT fk_storage_user FOREIGN KEY(user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS drive_files(
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            drive_id VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(150) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            storage_type VARCHAR(20) NOT NULL DEFAULT 'admin',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_drive_user_file (user_id, drive_id),
            CONSTRAINT fk_drive_file_user FOREIGN KEY(user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec("CREATE TABLE IF NOT EXISTS drive_file_shares(
        file_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        shared_by INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(file_id, user_id),
        CONSTRAINT fk_drive_share_file FOREIGN KEY(file_id) REFERENCES drive_files(id) ON DELETE CASCADE,
        CONSTRAINT fk_drive_share_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_drive_share_by FOREIGN KEY(shared_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS google_drive_tokens(
        id TINYINT UNSIGNED PRIMARY KEY,
        token_json LONGTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS email_verifications(
        user_id INT UNSIGNED PRIMARY KEY,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_email_verification_user FOREIGN KEY(user_id)
            REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$driveFileCols = $dbDriver === 'sqlite'
    ? array_column($db->query('PRAGMA table_info(drive_files)')->fetchAll(), 'name')
    : array_column($db->query('SHOW COLUMNS FROM drive_files')->fetchAll(), 'Field');
if (!in_array('storage_type', $driveFileCols, true)) {
    $db->exec("ALTER TABLE drive_files ADD COLUMN storage_type VARCHAR(20) NOT NULL DEFAULT 'legacy'");
}

// Migrate databases created by older versions of Pengingatku.
$userCols = $dbDriver === 'sqlite'
    ? array_column($db->query('PRAGMA table_info(users)')->fetchAll(), 'name')
    : array_column($db->query('SHOW COLUMNS FROM users')->fetchAll(), 'Field');

if (!in_array('role', $userCols, true)) {
    $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
}

if (!in_array('active', $userCols, true)) {
    $db->exec('ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1');
}

if (!in_array('email_verified', $userCols, true)) {
    $db->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1');
}

if (!in_array('created_at', $userCols, true)) {
    $db->exec(
        $dbDriver === 'sqlite'
            ? 'ALTER TABLE users ADD COLUMN created_at TEXT'
            : 'ALTER TABLE users ADD COLUMN created_at DATETIME NULL'
    );
    $db->exec(
        $dbDriver === 'sqlite'
            ? "UPDATE users SET created_at=datetime('now') WHERE created_at IS NULL OR created_at = ''"
            : 'UPDATE users SET created_at=UTC_TIMESTAMP() WHERE created_at IS NULL'
    );
}

$db->exec(
    $dbDriver === 'sqlite'
        ? 'CREATE TABLE IF NOT EXISTS app_meta(key TEXT PRIMARY KEY, value TEXT NOT NULL)'
        : 'CREATE TABLE IF NOT EXISTS app_meta(`key` VARCHAR(80) PRIMARY KEY, value VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

if ($dbDriver === 'sqlite') {
    $db->exec('CREATE TABLE IF NOT EXISTS login_attempts(attempt_key TEXT NOT NULL, attempted_at INTEGER NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_key_time ON login_attempts(attempt_key, attempted_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_age ON login_attempts(attempted_at)');
} else {
    $db->exec('CREATE TABLE IF NOT EXISTS login_attempts(
        attempt_key CHAR(64) NOT NULL,
        attempted_at BIGINT NOT NULL,
        INDEX idx_login_attempts_key_time (attempt_key, attempted_at),
        INDEX idx_login_attempts_age (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

$rootEmail = strtolower(trim((string) getenv('PENGINGAT_SUPERADMIN_EMAIL')));

if ($rootEmail !== '' && filter_var($rootEmail, FILTER_VALIDATE_EMAIL)) {
    q("UPDATE users SET role = 'superadmin' WHERE lower(email) = ?", [$rootEmail]);
}

const HARI = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
];

const TIPE = [
    'pr' => 'PR',
    'proyek' => 'Proyek',
    'ujian' => 'Ujian',
];

function q(string $sql, array $params = []): PDOStatement
{
    global $db;

    $statement = $db->prepare($sql);
    $statement->execute($params);

    return $statement;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function go(string $page, string $message = ''): never
{
    if ($message) {
        $_SESSION['flash'] = $message;
    }

    header('Location: index.php?p=' . $page);
    exit;
}

function uid(): ?int
{
    return $_SESSION['uid'] ?? null;
}

function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = uid() ? q('SELECT * FROM users WHERE id = ?', [uid()])->fetch() : null;
    }

    return $user ?: null;
}

function role(): string
{
    return current_user()['role'] ?? 'user';
}

function can_admin(): bool
{
    return in_array(role(), ['admin', 'superadmin'], true);
}

function is_superadmin(): bool
{
    return role() === 'superadmin';
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_check(): void
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMax = trim((string) ini_get('post_max_size'));
    $unit = strtolower(substr($postMax, -1));
    $postMaxBytes = (float) $postMax;
    if ($unit === 'g') {
        $postMaxBytes *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $postMaxBytes *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $postMaxBytes *= 1024;
    }
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && $_POST === []
        && $contentLength > 0
        && $postMaxBytes > 0
        && $contentLength > $postMaxBytes
    ) {
        http_response_code(413);
        exit('Ukuran unggahan melebihi batas PHP server (' . e($postMax) . '). Admin server perlu menaikkan post_max_size.');
    }

    if (!hash_equals(csrf(), $_POST['_t'] ?? '')) {
        http_response_code(419);
        exit('Sesi tidak valid. Muat ulang halaman.');
    }
}

function warna(string $mapel): string
{
    return 'hsl(' . (crc32(strtolower($mapel)) % 360) . ' 60% 42%)';
}

function label_tenggat(string $date): string
{
    $days = (int) ((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);

    return $days < 0
        ? 'Terlambat ' . -$days . ' hari'
        : ($days === 0 ? 'Hari ini' : ($days === 1 ? 'Besok' : $days . ' hari lagi'));
}

function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
}
