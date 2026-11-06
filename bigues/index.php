<?php

declare(strict_types=1);
require __DIR__ . '/inc/common.php';

try {
    br_bootstrap();
    $items = array_reverse(br_results());
} catch (Throwable $e) {
    $error = $e->getMessage();
    $items = [];
}
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f2">
    <link rel="icon" href="icons/icon-192.png">
    <link rel="stylesheet" href="assets/style.css?v=5">
    <title>Resultats de la setmana · Bigues i Riells</title>
</head>
<body class="public-page">
<main class="public-shell">
    <h1 class="public-title">Resultats de la setmana</h1>

    <?php if (isset($error)): ?>
        <p class="public-empty">No s'han pogut carregar els resultats.</p>
    <?php elseif (!$items): ?>
        <p class="public-empty">No hi ha resultats publicats.</p>
    <?php else: ?>
        <section class="results-grid" aria-label="Resultats de la setmana">
            <?php foreach ($items as $item): ?>
                <a class="result-card" href="<?= br_h(br_public_image_url($item)) ?>" target="_blank" rel="noopener">
                    <img
                        src="<?= br_h(br_public_image_url($item)) ?>"
                        loading="lazy"
                        alt="Resultats publicats el <?= br_h(date('d/m/Y', strtotime((string)$item['created_at']))) ?>"
                    >
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
