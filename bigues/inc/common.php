<?php

declare(strict_types=1);

const BR_APP_NAME = 'Bigues Resultats';
const BR_DATA_DIR = __DIR__ . '/../data';
const BR_UPLOAD_DIR = __DIR__ . '/../uploads';
const BR_RESULTS_FILE = BR_DATA_DIR . '/results.php';
const BR_SETTINGS_FILE = BR_DATA_DIR . '/settings.php';
const BR_MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const BR_WEBP_QUALITY = 90;

function br_bootstrap(): void
{
    foreach ([BR_DATA_DIR, BR_UPLOAD_DIR] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No s\'ha pogut crear la carpeta: ' . basename($dir));
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('La carpeta ' . basename($dir) . ' no té permisos d\'escriptura.');
        }
    }

    if (!file_exists(BR_RESULTS_FILE)) {
        br_write_php_array(BR_RESULTS_FILE, []);
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

function br_is_configured(): bool
{
    $settings = br_read_php_array(BR_SETTINGS_FILE);
    return !empty($settings['password_hash']);
}

function br_set_password(string $password): void
{
    if (strlen($password) < 6) {
        throw new InvalidArgumentException('La contrasenya ha de tenir com a mínim 6 caràcters.');
    }

    br_write_php_array(BR_SETTINGS_FILE, [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date(DATE_ATOM),
    ]);
}

function br_check_password(string $password): bool
{
    $settings = br_read_php_array(BR_SETTINGS_FILE);
    $hash = (string)($settings['password_hash'] ?? '');
    return $hash !== '' && password_verify($password, $hash);
}

function br_is_logged_in(): bool
{
    br_start_session();
    return !empty($_SESSION['br_admin']);
}

function br_login(): void
{
    br_start_session();
    session_regenerate_id(true);
    $_SESSION['br_admin'] = true;
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

function br_redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function br_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);

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

    // Totes les imatges queden normalitzades a WEBP.
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.webp';
    $destination = BR_UPLOAD_DIR . '/' . $name;

    if ($mime === 'image/webp') {
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('No s\'ha pogut guardar la imatge al servidor.');
        }
    } else {
        if (!br_convert_to_webp($tmp, $destination)) {
            throw new RuntimeException(
                'El dispositiu ha enviat una imatge compatible, però aquest servidor no té disponible cap conversor WEBP (GD amb WEBP o Imagick). ' .
                'Activa WEBP a PHP/GD o Imagick al panell del hosting.'
            );
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
        'mime' => 'image/webp',
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

function br_add_result(array $upload): array
{
    $items = br_results();
    $week = br_next_week($items);

    $entry = [
        'id' => bin2hex(random_bytes(8)),
        'week' => $week,
        'filename' => (string)$upload['filename'],
        'mime' => (string)$upload['mime'],
        'size' => (int)$upload['size'],
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

function br_php_upload_limit_text(): string
{
    return (string)ini_get('upload_max_filesize');
}
