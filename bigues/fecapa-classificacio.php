<?php

declare(strict_types=1);

$competition = $_GET['competition'] ?? '1a';
$competitions = [
    '1a' => [
        'endpoint' => 'https://www.server2.sidgad.es/fecapa/fecapa_clasif_idc_4776_1.php',
        'origin' => 'https://www.hoqueipatins.fecapa.cat',
        'referer' => 'https://www.hoqueipatins.fecapa.cat/league/4776',
        'form' => ['idc' => '4776', 'tipo_stats' => '', 'site_lang' => 'ca'],
    ],
    'ok' => [
        'endpoint' => 'https://www.server2.sidgad.es/rfep/rfep_clasif_idc_3569_1.php',
        'origin' => 'https://www.hockeypatines.fep.es',
        'referer' => 'https://www.hockeypatines.fep.es/league/3569',
        'form' => ['idc' => '3569', 'tipo_stats' => '', 'site_lang' => 'es'],
    ],
];

if (!isset($competitions[$competition])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'La competició indicada no és vàlida.';
    exit;
}

$config = $competitions[$competition];
$endpoint = $config['endpoint'];
$referer = $config['referer'];
$form = http_build_query($config['form']);
$headers = [
    'Accept: */*',
    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
    'Origin: ' . $config['origin'],
    'Referer: ' . $referer,
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
    'X-Requested-With: XMLHttpRequest',
];
$response = false;
$status = 0;

if (function_exists('curl_init')) {
    $request = curl_init($endpoint);
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $form,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_REFERER => $referer,
    ]);
    $response = curl_exec($request);
    $status = (int)curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $form,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int)$matches[1];
        }
    }
}

if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'La classificació de FECAPA no està disponible.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $response;