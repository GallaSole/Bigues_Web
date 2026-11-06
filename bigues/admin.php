<?php

declare(strict_types=1);
require __DIR__ . '/inc/common.php';

try {
    br_bootstrap();
    br_start_session();
} catch (Throwable $e) {
    http_response_code(500);
    $fatal = $e->getMessage();
}

if (!isset($fatal) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $isAjax = (string)($_POST['ajax'] ?? '') === '1';

    try {
        if ($action === 'setup') {
            if (br_is_configured()) {
                throw new RuntimeException('El portal ja està configurat.');
            }
            br_verify_csrf($_POST['csrf'] ?? null);
            $password = (string)($_POST['password'] ?? '');
            $repeat = (string)($_POST['password_repeat'] ?? '');
            if ($password !== $repeat) {
                throw new InvalidArgumentException('Les contrasenyes no coincideixen.');
            }
            br_set_password($password);
            br_login();
            br_flash('success', 'Accés configurat.');
            br_redirect('admin.php');
        }

        if ($action === 'login') {
            br_verify_csrf($_POST['csrf'] ?? null);
            if (!br_check_password((string)($_POST['password'] ?? ''))) {
                throw new RuntimeException('Contrasenya incorrecta.');
            }
            br_login();
            br_redirect('admin.php');
        }

        if ($action === 'logout') {
            br_verify_csrf($_POST['csrf'] ?? null);
            br_logout();
            br_redirect('admin.php');
        }

        if ($action === 'upload') {
            if (!br_is_logged_in()) {
                throw new RuntimeException('Has de tornar a iniciar sessió.');
            }
            br_verify_csrf($_POST['csrf'] ?? null);
            $upload = br_upload_image($_FILES['image'] ?? []);
            $entry = br_add_result($upload);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'week' => (int)$entry['week']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            br_flash('success', 'Resultats de la setmana ' . (int)$entry['week'] . ' publicats.');
            br_redirect('admin.php');
        }

        if ($action === 'delete') {
            if (!br_is_logged_in()) {
                throw new RuntimeException('Has de tornar a iniciar sessió.');
            }
            br_verify_csrf($_POST['csrf'] ?? null);
            br_delete_result((string)($_POST['id'] ?? ''));
            br_flash('success', 'Eliminat.');
            br_redirect('admin.php');
        }
    } catch (Throwable $e) {
        if (!empty($isAjax)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        br_flash('error', $e->getMessage());
        br_redirect('admin.php');
    }
}

$configured = !isset($fatal) && br_is_configured();
$loggedIn = !isset($fatal) && br_is_logged_in();
$items = $loggedIn ? br_results() : [];
$flash = !isset($fatal) ? br_take_flash() : null;
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#10170d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Bigues Resultats">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <link rel="icon" href="icons/icon-192.png">
    <link rel="stylesheet" href="assets/style.css?v=5">
    <title>Bigues Resultats</title>
</head>
<body class="admin-page">
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="admin.php" aria-label="Bigues Resultats">
            <span class="brand-mark">BR</span>
            <strong>Bigues Resultats</strong>
        </a>
        <?php if ($loggedIn): ?>
            <div class="top-actions">
                <a class="icon-button" href="index.php" aria-label="Veure web">↗</a>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= br_h(br_csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="icon-button" type="submit" aria-label="Tancar sessió">×</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <main>
        <?php if (isset($fatal)): ?>
            <section class="panel error-panel">
                <h1>Error</h1>
                <p><?= br_h($fatal) ?></p>
            </section>

        <?php elseif (!$configured): ?>
            <h1 class="admin-title">Configurar accés</h1>
            <?php if ($flash): ?><div class="flash <?= br_h($flash['type']) ?>"><?= br_h($flash['message']) ?></div><?php endif; ?>

            <form method="post" class="panel form-card">
                <input type="hidden" name="csrf" value="<?= br_h(br_csrf_token()) ?>">
                <input type="hidden" name="action" value="setup">
                <label>Contrasenya
                    <input type="password" name="password" minlength="6" required autocomplete="new-password">
                </label>
                <label>Repeteix-la
                    <input type="password" name="password_repeat" minlength="6" required autocomplete="new-password">
                </label>
                <button class="primary-button" type="submit">Guardar</button>
            </form>

        <?php elseif (!$loggedIn): ?>
            <h1 class="admin-title">Accés</h1>
            <?php if ($flash): ?><div class="flash <?= br_h($flash['type']) ?>"><?= br_h($flash['message']) ?></div><?php endif; ?>

            <form method="post" class="panel form-card">
                <input type="hidden" name="csrf" value="<?= br_h(br_csrf_token()) ?>">
                <input type="hidden" name="action" value="login">
                <label>Contrasenya
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button class="primary-button" type="submit">Entrar</button>
            </form>

        <?php else: ?>
            <h1 class="admin-title">Pujar resultats</h1>

            <?php if ($flash): ?><div class="flash <?= br_h($flash['type']) ?>"><?= br_h($flash['message']) ?></div><?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="panel upload-card" id="uploadForm">
                <input type="hidden" name="csrf" value="<?= br_h(br_csrf_token()) ?>">
                <input type="hidden" name="action" value="upload">

                <label class="file-picker" for="imageInput">
                    <span class="file-icon">＋</span>
                    <strong id="fileLabel">Seleccionar imatge</strong>
                    <input id="imageInput" type="file" name="image" accept="image/*" required>
                </label>

                <div id="previewWrap" class="preview-wrap" hidden>
                    <img id="previewImage" alt="Previsualització">
                </div>

                <button class="primary-button large" id="uploadButton" type="submit">Publicar</button>
            </form>

            <section class="admin-list-section">
                <h2>Publicats</h2>

                <?php if (!$items): ?>
                    <p class="empty-state">No hi ha cap publicació.</p>
                <?php else: ?>
                    <div class="admin-grid">
                    <?php foreach (array_reverse($items) as $item): ?>
                        <article class="admin-result-card">
                            <img src="<?= br_h(br_public_image_url($item)) ?>" alt="Resultat publicat">
                            <time datetime="<?= br_h((string)$item['created_at']) ?>"><?= br_h(date('d/m/Y · H:i', strtotime((string)$item['created_at']))) ?></time>
                            <form method="post" onsubmit="return confirm('Vols eliminar aquesta publicació?');">
                                <input type="hidden" name="csrf" value="<?= br_h(br_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= br_h((string)$item['id']) ?>">
                                <button class="danger-button" type="submit">Eliminar</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/app.js?v=5"></script>
</body>
</html>
