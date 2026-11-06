<?php

declare(strict_types=1);
require __DIR__ . '/inc/common.php';

header('Content-Type: application/json; charset=utf-8');
br_no_cache_headers();

try {
    // Les rutes són relatives a l'arrel del web, que conté la carpeta bigues-resultats/.
    $imageUrl = static fn(array $item): string => ($item['filename'] ?? '') === ''
        ? ''
        : 'bigues-resultats/' . br_public_image_url($item);

    $results = array_map(static fn(array $item): array => [
        'id' => (string)$item['id'],
        'week' => (int)($item['week'] ?? 0),
        'title' => (string)($item['title'] ?? ''),
        'focus' => (string)($item['focus'] ?? '50% 50%'),
        'image' => $imageUrl($item),
        'text' => (string)($item['text'] ?? ''),
        'created_at' => (string)$item['created_at'],
    ], array_reverse(br_results()));

    $news = array_map(static fn(array $item): array => [
        'id' => (string)$item['id'],
        'title' => (string)$item['title'],
        'body' => (string)$item['body'],
        'image' => $imageUrl($item),
        'focus' => (string)($item['focus'] ?? '50% 50%'),
        'created_at' => (string)$item['created_at'],
    ], br_news());

    echo json_encode(['results' => $results, 'news' => $news], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No s\'han pogut carregar les dades.'], JSON_UNESCAPED_UNICODE);
}
