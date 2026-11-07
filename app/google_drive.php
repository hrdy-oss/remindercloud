<?php

function google_drive_config(): array
{
    $config = is_file(__DIR__ . '/google_drive.local.php')
        ? require __DIR__ . '/google_drive.local.php'
        : [];
    $setting = static function (string $environment, string $key) use ($config): string {
        $value = getenv($environment);

        return (string) (
            $value !== false && $value !== ''
                ? $value
                : ($config[$key] ?? '')
        );
    };

    return [
        'client_id' => $setting('PENGINGAT_GOOGLE_CLIENT_ID', 'client_id'),
        'client_secret' => $setting('PENGINGAT_GOOGLE_CLIENT_SECRET', 'client_secret'),
        'redirect_uri' => $setting('PENGINGAT_GOOGLE_REDIRECT_URI', 'redirect_uri'),
        'folder_id' => $setting('PENGINGAT_GOOGLE_DRIVE_FOLDER_ID', 'folder_id'),
    ];
}

function google_drive_connected(): bool
{
    return (bool) q('SELECT 1 FROM google_drive_tokens WHERE id = 1')->fetchColumn();
}

function google_drive_authorize_url(): string
{
    $config = google_drive_config();
    if ($config['client_id'] === '' || $config['client_secret'] === '' || $config['redirect_uri'] === '') {
        throw new RuntimeException('Atur PENGINGAT_GOOGLE_CLIENT_ID, PENGINGAT_GOOGLE_CLIENT_SECRET, dan PENGINGAT_GOOGLE_REDIRECT_URI pada environment server.');
    }

    $_SESSION['google_drive_oauth_state'] = bin2hex(random_bytes(24));
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $_SESSION['google_drive_oauth_state'],
    ]);
}

function google_http(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL perlu diaktifkan di server.');
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('Koneksi ke Google gagal: ' . $error);
    }
    return [$status, (string) $response];
}

function google_drive_exchange_code(string $code): void
{
    $config = google_drive_config();
    if ($config['client_secret'] === '') {
        throw new RuntimeException('Client Secret Google belum diatur pada konfigurasi server.');
    }
    [$status, $body] = google_http('https://oauth2.googleapis.com/token', 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]));
    $tokens = json_decode($body, true);
    if ($status >= 300 || !is_array($tokens) || empty($tokens['access_token'])) {
        throw new RuntimeException('Google tidak memberikan izin Drive. Coba hubungkan ulang.');
    }

    $oldTokens = q('SELECT token_json FROM google_drive_tokens WHERE id = 1')->fetchColumn();
    $oldTokens = $oldTokens ? json_decode($oldTokens, true) : [];
    if (empty($tokens['refresh_token']) && !empty($oldTokens['refresh_token'])) {
        $tokens['refresh_token'] = $oldTokens['refresh_token'];
    }
    if (empty($tokens['refresh_token'])) {
        throw new RuntimeException('Google tidak mengirim refresh token. Putuskan akses aplikasi dari Akun Google lalu hubungkan ulang.');
    }
    $tokens['created_at'] = time();

    $json = json_encode($tokens, JSON_UNESCAPED_SLASHES);
    if (q('SELECT 1 FROM google_drive_tokens WHERE id = 1')->fetchColumn()) {
        q('UPDATE google_drive_tokens SET token_json = ? WHERE id = 1', [$json]);
    } else {
        q('INSERT INTO google_drive_tokens(id, token_json) VALUES(1, ?)', [$json]);
    }
}

function google_drive_access_token(): string
{
    $row = q('SELECT token_json FROM google_drive_tokens WHERE id = 1')->fetchColumn();
    $tokens = $row ? json_decode($row, true) : null;
    if (!is_array($tokens) || empty($tokens['refresh_token'])) {
        throw new RuntimeException('Drive belum dihubungkan admin.');
    }
    if (!empty($tokens['access_token']) && (int) ($tokens['created_at'] ?? 0) + (int) ($tokens['expires_in'] ?? 0) > time() + 60) {
        return $tokens['access_token'];
    }

    $config = google_drive_config();
    [$status, $body] = google_http('https://oauth2.googleapis.com/token', 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => $tokens['refresh_token'],
        'grant_type' => 'refresh_token',
    ]));
    $fresh = json_decode($body, true);
    if ($status >= 300 || !is_array($fresh) || empty($fresh['access_token'])) {
        throw new RuntimeException('Izin Google Drive kedaluwarsa atau dicabut. Admin perlu menghubungkan ulang.');
    }
    $tokens = array_merge($tokens, $fresh, ['created_at' => time()]);
    q('UPDATE google_drive_tokens SET token_json = ? WHERE id = 1', [json_encode($tokens, JSON_UNESCAPED_SLASHES)]);
    return $tokens['access_token'];
}

function google_drive_api(string $path, string $method = 'GET', ?string $body = null, array $extraHeaders = []): array
{
    $headers = array_merge(['Authorization: Bearer ' . google_drive_access_token()], $extraHeaders);
    [$status, $response] = google_http('https://www.googleapis.com/drive/v3/' . ltrim($path, '/'), $method, $headers, $body);
    if ($status >= 300) {
        throw new RuntimeException('Google Drive gagal memproses file. Periksa izin Drive API dan coba lagi.');
    }
    return [$status, $response];
}

function google_drive_stream_media(string $driveId, string $name, string $mimeType): never
{
    $headers = ['Authorization: Bearer ' . google_drive_access_token()];
    $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '' && preg_match('/^bytes=\d*-\d*(,\d*-\d*)*$/', $range)) {
        $headers[] = 'Range: ' . $range;
    }

    $responseHeaders = [];
    $responseStatus = 0;
    $headersSent = false;
    $errorBody = '';
    $safeInlineTypes = ['application/pdf', 'text/plain', 'text/csv', 'application/json'];
    if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) {
        $safeInline = true;
    } else {
        $safeInline = in_array($mimeType, $safeInlineTypes, true);
    }
    $contentType = $safeInline ? $mimeType : 'application/octet-stream';

    $curl = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($driveId) . '?alt=media');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders, &$responseStatus): int {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $header, $matches)) {
                $responseStatus = (int) $matches[1];
                $responseHeaders = [];
            } elseif (str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $responseHeaders[strtolower(trim($key))] = trim($value);
            }
            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseHeaders, &$responseStatus, &$headersSent, &$errorBody, $name, $contentType): int {
            if ($responseStatus >= 300) {
                if (strlen($errorBody) < 8192) {
                    $errorBody .= substr($chunk, 0, 8192 - strlen($errorBody));
                }
                return strlen($chunk);
            }
            if (!$headersSent) {
                http_response_code($responseStatus === 206 ? 206 : 200);
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: inline; filename="preview"; filename*=UTF-8\'\'' . rawurlencode($name));
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store');
                if (!empty($responseHeaders['content-length'])) {
                    header('Content-Length: ' . $responseHeaders['content-length']);
                }
                if (!empty($responseHeaders['content-range'])) {
                    header('Content-Range: ' . $responseHeaders['content-range']);
                }
                header('Accept-Ranges: bytes');
                $headersSent = true;
            }
            echo $chunk;
            flush();
            return strlen($chunk);
        },
    ]);
    $result = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($result === false || $responseStatus >= 300) {
        if (!$headersSent) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=UTF-8');
            exit('Pratinjau gagal dimuat dari Google Drive.');
        }
        error_log('Google Drive preview stream failed: ' . ($error ?: $errorBody));
    }
    exit;
}

function google_drive_upload_folder_id(): string
{
    $configuredId = google_drive_config()['folder_id'];
    if ($configuredId !== '') {
        return $configuredId;
    }

    $folderName = 'Pengingatku Uploads';
    $query = "name = '" . $folderName . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    [, $body] = google_drive_api('files?q=' . rawurlencode($query) . '&fields=files(id)&pageSize=1');
    $result = json_decode($body, true);
    if (is_array($result) && !empty($result['files'][0]['id'])) {
        return (string) $result['files'][0]['id'];
    }

    [, $body] = google_drive_api('files?fields=id', 'POST', json_encode([
        'name' => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder',
    ], JSON_UNESCAPED_SLASHES), ['Content-Type: application/json; charset=UTF-8']);
    $folder = json_decode($body, true);
    if (!is_array($folder) || empty($folder['id'])) {
        throw new RuntimeException('Google Drive tidak dapat membuat folder penyimpanan.');
    }

    return (string) $folder['id'];
}

function google_drive_upload(array $upload, string $name, string $mimeType): array
{
    $folderId = google_drive_upload_folder_id();

    $path = (string) ($upload['tmp_name'] ?? '');
    $size = is_file($path) ? filesize($path) : false;
    if ($size === false) {
        throw new RuntimeException('File unggahan tidak bisa dibaca.');
    }

    $token = google_drive_access_token();
    $curl = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,mimeType,size');
    $sessionUrl = '';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $name,
            'parents' => [$folderId],
        ], JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . $mimeType,
            'X-Upload-Content-Length: ' . $size,
        ],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$sessionUrl): int {
            if (stripos($header, 'Location:') === 0) {
                $sessionUrl = trim(substr($header, strlen('Location:')));
            }
            return strlen($header);
        },
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($response === false) {
        throw new RuntimeException('Google Drive tidak dapat memulai unggahan: ' . $error);
    }
    if ($status >= 300 || $sessionUrl === '') {
        throw new RuntimeException('Google Drive tidak dapat memulai sesi unggahan. Periksa izin Drive API dan coba lagi.');
    }

    $fileHandle = fopen($path, 'rb');
    if ($fileHandle === false) {
        throw new RuntimeException('File unggahan tidak bisa dibaca.');
    }
    $curl = curl_init($sessionUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $fileHandle,
        CURLOPT_INFILESIZE => $size,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType,
        ],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 600,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($fileHandle);
    if ($response === false) {
        throw new RuntimeException('Koneksi saat mengirim file ke Google Drive gagal: ' . $error);
    }

    $file = json_decode($response, true);
    if ($status >= 300 || !is_array($file) || empty($file['id'])) {
        throw new RuntimeException('Drive gagal menyimpan file. Periksa izin Drive API dan coba lagi.');
    }
    return $file;
}
