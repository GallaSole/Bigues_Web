<?php

declare(strict_types=1);

const BR_APP_NAME = 'Bigues Resultats';
const BR_DATA_DIR = __DIR__ . '/../data';
const BR_UPLOAD_DIR = __DIR__ . '/../uploads';
const BR_RESULTS_FILE = BR_DATA_DIR . '/results.php';
const BR_NEWS_FILE = BR_DATA_DIR . '/news.php';
const BR_SETTINGS_FILE = BR_DATA_DIR . '/settings.php';
const BR_MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const BR_WEBP_QUALITY = 90;

function br_dir_writable(string $dir): bool
{
    // is_writable() falla a Windows/OneDrive amb l'atribut de només lectura de la carpeta.
    if (is_writable($dir)) {
        return true;
    }

    $probe = $dir . '/.write-test-' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($probe, '') !== false;
    if ($ok) {
        @unlink($probe);
    }
    return $ok;
}

function br_bootstrap(): void
{
    foreach ([BR_DATA_DIR, BR_UPLOAD_DIR] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No s\'ha pogut crear la carpeta: ' . basename($dir));
        }
        if (!br_dir_writable($dir)) {
            throw new RuntimeException('La carpeta ' . basename($dir) . ' no té permisos d\'escriptura.');
        }
    }

    if (!file_exists(BR_RESULTS_FILE)) {
        br_write_php_array(BR_RESULTS_FILE, []);
    }

    if (!file_exists(BR_NEWS_FILE)) {
        br_write_php_array(BR_NEWS_FILE, []);
    }
}

function br_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('bigues_resultats_admin');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function br_read_php_array(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    // results.php/settings.php són fitxers PHP generats dinàmicament.
    // Si OPcache està actiu, invalidem explícitament la còpia compilada
    // perquè una pujada o eliminació es vegi immediatament.
    clearstatcache(true, $file);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }

    $data = require $file;
    return is_array($data) ? $data : [];
}

function br_write_php_array(string $file, array $data): void
{
    $payload = "<?php\nreturn " . var_export($data, true) . ";\n";
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));

    if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
        throw new RuntimeException('No s\'ha pogut escriure al directori data.');
    }

    @chmod($tmp, 0664);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('No s\'ha pogut desar la informació.');
    }

    clearstatcache(true, $file);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
}

function br_results(): array
{
    br_bootstrap();
    $items = br_read_php_array(BR_RESULTS_FILE);

    usort($items, static function (array $a, array $b): int {
        $aTime = strtotime((string)($a['created_at'] ?? '')) ?: 0;
        $bTime = strtotime((string)($b['created_at'] ?? '')) ?: 0;
        if ($aTime === $bTime) {
            return ((int)($a['week'] ?? 0)) <=> ((int)($b['week'] ?? 0));
        }
        return $aTime <=> $bTime;
    });

    return $items;
}

function br_next_week(array $items): int
{
    $maxWeek = 0;
    foreach ($items as $item) {
        $maxWeek = max($maxWeek, (int)($item['week'] ?? 0));
    }
    return $maxWeek + 1;
}

const BR_MAX_LOGIN_FAILS = 5;
const BR_LOGIN_LOCK_SECONDS = 900;

// settings.php guarda només els hashes: ['users' => ['nom' => hash, ...]].
function br_users(): array
{
    $users = br_read_php_array(BR_SETTINGS_FILE)['users'] ?? [];
    return is_array($users) ? $users : [];
}

function br_is_configured(): bool
{
    return count(br_users()) > 0;
}

function br_set_users(array $credentials): void
{
    $users = [];
    foreach ($credentials as $name => $password) {
        $name = strtolower(trim((string)$name));
        if (!preg_match('/^[a-z0-9._-]{2,30}$/', $name)) {
            throw new InvalidArgumentException('El nom d\'usuari ha de tenir entre 2 i 30 lletres o xifres, sense espais.');
        }
        if (strlen((string)$password) < 8) {
            throw new InvalidArgumentException('Cada contrasenya ha de tenir com a mínim 8 caràcters.');
        }
        $users[$name] = password_hash((string)$password, PASSWORD_DEFAULT);
    }
    if (count($users) !== count($credentials)) {
        throw new InvalidArgumentException('Els noms d\'usuari han de ser diferents.');
    }

    br_write_php_array(BR_SETTINGS_FILE, ['users' => $users, 'created_at' => date(DATE_ATOM)]);
}

function br_login_attempts_file(): string
{
    return BR_DATA_DIR . '/login_attempts.php';
}

function br_login_is_locked(): bool
{
    $entry = br_read_php_array(br_login_attempts_file())[hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''))] ?? null;
    return is_array($entry)
        && (int)$entry['fails'] >= BR_MAX_LOGIN_FAILS
        && time() - (int)$entry['last'] < BR_LOGIN_LOCK_SECONDS;
}

function br_login_register(bool $success): void
{
    $key = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $all = br_read_php_array(br_login_attempts_file());
    $entry = $all[$key] ?? ['fails' => 0, 'last' => 0];
    if ($success || time() - (int)$entry['last'] >= BR_LOGIN_LOCK_SECONDS) {
        $entry['fails'] = 0;
    }
    if (!$success) {
        $entry = ['fails' => (int)$entry['fails'] + 1, 'last' => time()];
    }
    $all[$key] = $entry;
    br_write_php_array(br_login_attempts_file(), $all);
}

function br_check_login(string $user, string $password): bool
{
    if (br_login_is_locked()) {
        throw new RuntimeException('Massa intents fallits. Torna-ho a provar d\'aquí a 15 minuts.');
    }

    $users = br_users();
    $user = strtolower(trim($user));
    // Es verifica sempre un hash per no revelar si l'usuari existeix.
    $hash = (string)($users[$user] ?? password_hash('dummy', PASSWORD_DEFAULT));
    $ok = isset($users[$user]) && password_verify($password, $hash);
    br_login_register($ok);
    return $ok;
}

function br_is_logged_in(): bool
{
    br_start_session();
    return !empty($_SESSION['br_admin']);
}

function br_login(string $user): void
{
    br_start_session();
    session_regenerate_id(true);
    $_SESSION['br_admin'] = strtolower(trim($user));
}

function br_logout(): void
{
    br_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function br_csrf_token(): string
{
    br_start_session();
    if (empty($_SESSION['br_csrf'])) {
        $_SESSION['br_csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['br_csrf'];
}

function br_verify_csrf(?string $token): void
{
    br_start_session();
    if (!$token || empty($_SESSION['br_csrf']) || !hash_equals((string)$_SESSION['br_csrf'], $token)) {
        throw new RuntimeException('La sessió ha caducat. Torna-ho a provar.');
    }
}

function br_flash(string $type, string $message): void
{
    br_start_session();
    $_SESSION['br_flash'] = ['type' => $type, 'message' => $message];
}

function br_take_flash(): ?array
{
    br_start_session();
    $flash = $_SESSION['br_flash'] ?? null;
    unset($_SESSION['br_flash']);
    return is_array($flash) ? $flash : null;
}


function br_no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function br_redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function br_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function br_detect_image_mime(string $path): string
{
    // 1) Fileinfo, si està disponible. És la via preferida al hosting.
    if (class_exists('finfo') && defined('FILEINFO_MIME_TYPE')) {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return strtolower(trim($mime));
            }
        } catch (Throwable $e) {
            // Continuem amb els fallbacks de sota.
        }
    }

    // 2) getimagesize forma part del nucli de PHP i no necessita Fileinfo.
    // Ens cobreix els formats que realment rebem del navegador (JPG/PNG/WEBP/GIF).
    $info = @getimagesize($path);
    if (is_array($info) && !empty($info['mime']) && is_string($info['mime'])) {
        return strtolower(trim($info['mime']));
    }

    // 3) Últim fallback: signatures bàsiques del fitxer.
    $head = @file_get_contents($path, false, null, 0, 32);
    if (!is_string($head) || $head === '') {
        return '';
    }

    if (strlen($head) >= 3 && substr($head, 0, 3) === "\xFF\xD8\xFF") {
        return 'image/jpeg';
    }
    if (strlen($head) >= 8 && substr($head, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A") {
        return 'image/png';
    }
    if (strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
        return 'image/gif';
    }

    // AVIF/HEIF/HEIC utilitzen contenidor ISO-BMFF amb una marca "ftyp".
    if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
        $brand = strtolower(substr($head, 8, 4));
        if (in_array($brand, ['avif', 'avis'], true)) {
            return 'image/avif';
        }
        if (in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'], true)) {
            return 'image/heic';
        }
    }

    return '';
}

function br_upload_image(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('No s\'ha rebut cap imatge vàlida.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Selecciona una imatge.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('La imatge supera el límit de pujada del servidor.');
        default:
            throw new RuntimeException('S\'ha produït un error durant la pujada.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > BR_MAX_UPLOAD_BYTES) {
        throw new RuntimeException('La imatge ha de pesar menys de 8 MB.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('La pujada no és vàlida.');
    }

    // Detectem el tipus real de la imatge sense dependre obligatòriament
    // de l'extensió Fileinfo. Això permet que funcioni també amb una
    // instal·lació local mínima de PHP a Windows.
    $mime = br_detect_image_mime($tmp);

    $allowed = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/heic',
        'image/heif',
        'image/avif',
    ];

    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Format no admès. Selecciona una imatge JPG, PNG, WEBP, AVIF o una foto de l\'iPhone.');
    }

    // Es converteix a WEBP si es pot; si no, es guarda JPG/PNG/GIF tal com ve.
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.webp';
    $destination = BR_UPLOAD_DIR . '/' . $name;
    $storedMime = 'image/webp';

    if ($mime === 'image/webp') {
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('No s\'ha pogut guardar la imatge al servidor.');
        }
    } elseif (!br_convert_to_webp($tmp, $destination)) {
        $plainExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'][$mime] ?? null;
        if ($plainExt === null) {
            throw new RuntimeException('Aquest format d\'imatge no es pot publicar. Fes la foto en JPG o PNG.');
        }
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $plainExt;
        $destination = BR_UPLOAD_DIR . '/' . $name;
        $storedMime = $mime;
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('No s\'ha pogut guardar la imatge al servidor.');
        }
    }

    @chmod($destination, 0664);

    clearstatcache(true, $destination);
    $finalSize = is_file($destination) ? (int)filesize($destination) : 0;
    if ($finalSize <= 0) {
        @unlink($destination);
        throw new RuntimeException('La conversió a WEBP no ha generat una imatge vàlida.');
    }

    return [
        'filename' => $name,
        'mime' => $storedMime,
        'size' => $finalSize,
    ];
}

function br_convert_to_webp(string $source, string $destination): bool
{
    // Primer intent: GD, habitual en hostings compartits.
    if (function_exists('imagewebp') && function_exists('imagecreatefromstring')) {
        $bytes = @file_get_contents($source);
        if ($bytes !== false) {
            $image = @imagecreatefromstring($bytes);
            if ($image !== false) {
                if (function_exists('imagepalettetotruecolor')) {
                    @imagepalettetotruecolor($image);
                }
                @imagealphablending($image, true);
                @imagesavealpha($image, true);

                $ok = @imagewebp($image, $destination, BR_WEBP_QUALITY);
                imagedestroy($image);

                if ($ok && is_file($destination) && filesize($destination) > 0) {
                    return true;
                }
            }
        }
    }

    // Segon intent: Imagick, si el hosting el té disponible.
    if (class_exists('Imagick')) {
        try {
            $image = new Imagick($source);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(BR_WEBP_QUALITY);
            if (method_exists($image, 'stripImage')) {
                $image->stripImage();
            }
            $ok = $image->writeImage($destination);
            $image->clear();
            $image->destroy();

            if ($ok && is_file($destination) && filesize($destination) > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // El client ja intenta convertir abans de pujar; aquest bloc és només fallback.
        }
    }

    return false;
}

function br_result_text(string $text): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if (strlen($text) > 5000) {
        throw new InvalidArgumentException('El text és massa llarg (màxim 5000 caràcters).');
    }
    return $text;
}

function br_focus(array $post): string
{
    $clamp = static fn($v): int => max(0, min(100, (int)round((float)$v)));
    return $clamp($post['focus_x'] ?? 50) . '% ' . $clamp($post['focus_y'] ?? 50) . '%';
}

function br_add_result(array $upload, string $text = '', string $title = '', string $focus = '50% 50%'): array
{
    $items = br_results();
    $week = br_next_week($items);

    $entry = [
        'id' => bin2hex(random_bytes(8)),
        'week' => $week,
        'focus' => $focus,
        'title' => $title,
        'filename' => (string)$upload['filename'],
        'mime' => (string)$upload['mime'],
        'size' => (int)$upload['size'],
        'text' => $text,
        'created_at' => date(DATE_ATOM),
    ];

    $items[] = $entry;
    br_write_php_array(BR_RESULTS_FILE, $items);

    return $entry;
}

function br_delete_result(string $id): void
{
    $items = br_results();
    $newItems = [];
    $deletedFile = null;

    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            $deletedFile = basename((string)($item['filename'] ?? ''));
            continue;
        }
        $newItems[] = $item;
    }

    br_write_php_array(BR_RESULTS_FILE, $newItems);

    if ($deletedFile) {
        $path = BR_UPLOAD_DIR . '/' . $deletedFile;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function br_public_image_url(array $item): string
{
    return 'uploads/' . rawurlencode(basename((string)($item['filename'] ?? '')));
}

// Notícies: més noves primer.
function br_news(): array
{
    br_bootstrap();
    $items = br_read_php_array(BR_NEWS_FILE);

    usort($items, static function (array $a, array $b): int {
        $aTime = strtotime((string)($a['created_at'] ?? '')) ?: 0;
        $bTime = strtotime((string)($b['created_at'] ?? '')) ?: 0;
        return $bTime <=> $aTime;
    });

    return $items;
}

// Valida el text abans de pujar la imatge, per no deixar fitxers orfes.
function br_add_news(string $title, string $body, ?array $file, string $focus = '50% 50%'): array
{
    $title = trim($title);
    $body = trim(str_replace(["\r\n", "\r"], "\n", $body));

    if ($title === '' || strlen($title) > 300) {
        throw new InvalidArgumentException('Escriu un títol (màxim 300 caràcters).');
    }
    if ($body === '' || strlen($body) > 20000) {
        throw new InvalidArgumentException('Escriu el text de la notícia.');
    }

    $filename = '';
    if ($file !== null && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $filename = br_upload_image($file)['filename'];
    }

    $items = br_news();
    $entry = [
        'id' => bin2hex(random_bytes(8)),
        'title' => $title,
        'body' => $body,
        'filename' => $filename,
        'focus' => $focus,
        'created_at' => date(DATE_ATOM),
    ];

    $items[] = $entry;
    br_write_php_array(BR_NEWS_FILE, $items);

    return $entry;
}

function br_delete_news(string $id): void
{
    $newItems = [];
    $deletedFile = '';

    foreach (br_news() as $item) {
        if (($item['id'] ?? '') === $id) {
            $deletedFile = basename((string)($item['filename'] ?? ''));
            continue;
        }
        $newItems[] = $item;
    }

    br_write_php_array(BR_NEWS_FILE, $newItems);

    if ($deletedFile !== '') {
        $path = BR_UPLOAD_DIR . '/' . $deletedFile;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function br_php_upload_limit_text(): string
{
    return (string)ini_get('upload_max_filesize');
}
