<?php

declare(strict_types=1);
require __DIR__ . '/inc/common.php';

try {
    br_bootstrap();
    br_start_session();
    br_no_cache_headers();
} catch (Throwable $e) {
    http_response_code(500);
    $fatal = $e->getMessage();
}

if (!isset($fatal) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $isAjax = (string)($_POST['ajax'] ?? '') === '1';

    $requireLogin = static function (): void {
        if (!br_is_logged_in()) {
            throw new RuntimeException('Has de tornar a iniciar sessió.');
        }
        br_verify_csrf($_POST['csrf'] ?? null);
    };
    $json = static function (array $payload): never {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };

    try {
        // Només DEMO: configura els dos usuaris la primera vegada.
        if ($action === 'setup') {
            if (br_is_configured()) {
                throw new RuntimeException('Els usuaris ja estan configurats.');
            }
            br_verify_csrf($_POST['csrf'] ?? null);
            $user1 = (string)($_POST['user1'] ?? '');
            $user2 = (string)($_POST['user2'] ?? '');
            br_set_users([
                $user1 => (string)($_POST['password1'] ?? ''),
                $user2 => (string)($_POST['password2'] ?? ''),
            ]);
            br_login($user1);
            br_redirect('admin.php');
        }

        if ($action === 'login') {
            br_verify_csrf($_POST['csrf'] ?? null);
            $user = (string)($_POST['user'] ?? '');
            if (!br_check_login($user, (string)($_POST['password'] ?? ''))) {
                throw new RuntimeException('Usuari o contrasenya incorrectes.');
            }
            br_login($user);
            br_redirect('admin.php');
        }

        if ($action === 'logout') {
            br_verify_csrf($_POST['csrf'] ?? null);
            br_logout();
            br_redirect('admin.php');
        }

        if ($action === 'upload') {
            $requireLogin();
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || strlen($title) > 300) {
                throw new InvalidArgumentException('Escriu un títol (màxim 300 caràcters).');
            }
            $upload = br_upload_image($_FILES['image'] ?? []);
            br_add_result($upload, '', $title, br_focus($_POST));
            if ($isAjax) {
                $json(['ok' => true]);
            }
            br_flash('success', 'Resultat publicat a la web.');
            br_redirect('admin.php');
        }

        if ($action === 'news_add') {
            $requireLogin();
            br_add_news((string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''), $_FILES['image'] ?? null, br_focus($_POST));
            if ($isAjax) {
                $json(['ok' => true]);
            }
            br_flash('success', 'Notícia publicada a la web.');
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
$flash = !isset($fatal) ? br_take_flash() : null;
if (!$flash && isset($_GET['uploaded'])) {
    $flash = ['type' => 'success', 'message' => 'Publicat a la web.'];
}
$csrf = isset($fatal) ? '' : br_csrf_token();
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111111">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Publicar Bigues">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <link rel="icon" href="icons/icon-192.png">
    <link rel="stylesheet" href="assets/style.css?v=10">
    <title>Publicar · Bigues i Riells</title>
</head>
<body class="admin-page">
<div class="app-shell">
    <header class="topbar">
        <span class="brand">
            <img class="brand-logo" src="../logo%20club.JPG.jpeg" alt="">
            <span class="brand-text"><small>CLUB HOQUEI PATÍ</small><strong>BIGUES I RIELLS</strong></span>
        </span>
        <?php if ($loggedIn): ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= br_h($csrf) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="icon-button" type="submit" aria-label="Tancar sessió">Sortir</button>
            </form>
        <?php endif; ?>
    </header>

    <div class="demo-banner">MODE DEMO · servidor local de proves</div>

    <main>
        <?php if (isset($fatal)): ?>
            <section class="panel error-panel">
                <h1>Error</h1>
                <p><?= br_h($fatal) ?></p>
            </section>

        <?php elseif (!$configured): ?>
            <h1 class="admin-title">Crear els dos usuaris</h1>
            <p class="lead">Només es fa una vegada. Les contrasenyes es guarden xifrades al servidor.</p>
            <?php if ($flash): ?><div class="flash <?= br_h($flash['type']) ?>"><?= br_h($flash['message']) ?></div><?php endif; ?>

            <form method="post" class="panel form-card">
                <input type="hidden" name="csrf" value="<?= br_h($csrf) ?>">
                <input type="hidden" name="action" value="setup">
                <label class="field">Usuari 1
                    <input type="text" name="user1" required autocapitalize="none" autocomplete="off">
                </label>
                <label class="field">Contrasenya 1 (mín. 8)
                    <input type="password" name="password1" minlength="8" required autocomplete="new-password">
                </label>
                <label class="field">Usuari 2
                    <input type="text" name="user2" required autocapitalize="none" autocomplete="off">
                </label>
                <label class="field">Contrasenya 2 (mín. 8)
                    <input type="password" name="password2" minlength="8" required autocomplete="new-password">
                </label>
                <button class="primary-button" type="submit">Guardar</button>
            </form>

        <?php elseif (!$loggedIn): ?>
            <h1 class="admin-title">Accés</h1>
            <?php if ($flash): ?><div class="flash <?= br_h($flash['type']) ?>"><?= br_h($flash['message']) ?></div><?php endif; ?>

            <form method="post" class="panel form-card">
                <input type="hidden" name="csrf" value="<?= br_h($csrf) ?>">
                <input type="hidden" name="action" value="login">
                <label class="field">Usuari
                    <input type="text" name="user" required autocapitalize="none" autocomplete="username">
                </label>
                <label class="field">Contrasenya
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button class="primary-button" type="submit">Entrar</button>
            </form>

        <?php else: ?>
            <?php if ($flash): ?><div class="flash <?= br_h($flash['type']) ?>">
                <?= br_h($flash['message']) ?>
                <a href="../noticies.html" target="_blank" rel="noopener">Veure-ho a la web ↗</a>
            </div><?php endif; ?>

            <section data-view="home">
                <h1 class="admin-title">Què vols publicar?</h1>
                <div class="home-actions">
                    <button type="button" class="home-button" data-go="result">
                        <span class="home-icon">📷</span>
                        <strong>Publicar resultat</strong>
                        <small>Una foto amb els resultats</small>
                    </button>
                    <button type="button" class="home-button" data-go="news">
                        <span class="home-icon">📰</span>
                        <strong>Publicar notícia</strong>
                        <small>Títol, text i foto opcional</small>
                    </button>
                </div>
            </section>

            <section data-view="result" hidden>
                <button type="button" class="back-button" data-go="home">‹ Enrere</button>
                <h1 class="admin-title">Publicar resultat</h1>

                <form method="post" enctype="multipart/form-data" class="panel upload-card" data-upload data-confirm="Vols publicar aquest resultat a la web?">
                    <input type="hidden" name="csrf" value="<?= br_h($csrf) ?>">
                    <input type="hidden" name="action" value="upload">

                    <label class="field">Títol
                        <input type="text" name="title" maxlength="300" required placeholder="Ex: Resultats 28/10/26 - 02/11/26">
                    </label>

                    <label class="file-picker" for="imageInput">
                        <span class="file-icon">＋</span>
                        <strong id="fileLabel">Triar foto de la galeria</strong>
                        <input id="imageInput" type="file" name="image" accept="image/*" required>
                    </label>

                    <div id="previewWrap" class="preview-wrap crop-frame" hidden>
                        <img id="previewImage" alt="Previsualització" draggable="false">
                    </div>
                    <p class="crop-hint" hidden>Arrossega la foto per triar com es retalla a la web.</p>
                    <input type="hidden" name="focus_x" value="50">
                    <input type="hidden" name="focus_y" value="50">

                    <button class="primary-button large" type="submit">Publicar</button>
                </form>
            </section>

            <section data-view="news" hidden>
                <button type="button" class="back-button" data-go="home">‹ Enrere</button>
                <h1 class="admin-title">Publicar notícia</h1>

                <form method="post" enctype="multipart/form-data" class="panel upload-card" id="newsForm" data-upload data-confirm="Vols publicar aquesta notícia a la web?">
                    <input type="hidden" name="csrf" value="<?= br_h($csrf) ?>">
                    <input type="hidden" name="action" value="news_add">

                    <label class="field">Títol
                        <input type="text" name="title" maxlength="300" required>
                    </label>
                    <label class="field">Contingut
                        <textarea name="body" rows="7" required></textarea>
                    </label>

                    <label class="file-picker">
                        <span class="file-icon">＋</span>
                        <strong>Afegir foto (opcional)</strong>
                        <input type="file" name="image" accept="image/*">
                    </label>

                    <div class="news-preview" id="newsPreview">
                        <span class="preview-label">PREVISUALITZACIÓ</span>
                        <div class="crop-frame" hidden><img alt="" draggable="false"></div>
                        <p class="crop-hint" hidden>Arrossega la foto per triar com es retalla a la web.</p>
                        <input type="hidden" name="focus_x" value="50">
                        <input type="hidden" name="focus_y" value="50">
                        <h3></h3>
                        <p class="news-preview-body"></p>
                    </div>

                    <button class="primary-button large" type="submit">Publicar</button>
                </form>
            </section>

            <dialog id="confirmDialog" class="confirm-dialog">
                <p data-confirm-text></p>
                <div class="confirm-actions">
                    <button type="button" class="secondary-button" data-no>Cancel·lar</button>
                    <button type="button" class="primary-button" data-yes>Sí, publicar</button>
                </div>
            </dialog>
        <?php endif; ?>
    </main>
</div>
<script src="assets/app.js?v=9"></script>
</body>
</html>
