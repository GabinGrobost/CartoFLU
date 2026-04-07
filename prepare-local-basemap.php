<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$localUrlTemplate = (string)($data['localUrlTemplate'] ?? '');
$seedUrlTemplate  = (string)($data['seedUrlTemplate'] ?? '');
$z = max(0, min(18, (int)($data['z'] ?? 0)));
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);

if ($localUrlTemplate === '' || $seedUrlTemplate === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing localUrlTemplate or seedUrlTemplate.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$path = (string)parse_url($localUrlTemplate, PHP_URL_PATH);
if ($path === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unable to parse local URL path.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$basePart = $path;
$needle = '/{z}';
$pos = strpos($path, $needle);
if ($pos !== false) {
    $basePart = substr($path, 0, $pos);
} else {
    $basePart = dirname($path);
}

$basePart = trim($basePart, '/');
if ($basePart === '' || str_contains($basePart, '..')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid local storage path.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$targetExt = 'png';
if (preg_match('/\{y\}\.([a-zA-Z0-9]+)/', $path, $m)) {
    $targetExt = strtolower($m[1]);
}

$root = __DIR__;
$localRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $basePart);
if (!is_dir($localRoot) && !mkdir($localRoot, 0775, true) && !is_dir($localRoot)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to create local directory.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$downloaded = 0;
$failed = 0;

for ($dx = -1; $dx <= 1; $dx++) {
    for ($dy = -1; $dy <= 1; $dy++) {
        $tx = $x + $dx;
        $ty = $y + $dy;
        if ($tx < 0 || $ty < 0) continue;

        $targetDir = $localRoot . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $tx;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $failed++;
            continue;
        }

        $sourceUrl = str_replace(['{z}', '{x}', '{y}'], [(string)$z, (string)$tx, (string)$ty], $seedUrlTemplate);
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $ty . '.' . $targetExt;

        $ctx = stream_context_create(['http' => ['timeout' => 7]]);
        $content = @file_get_contents($sourceUrl, false, $ctx);
        if ($content === false || $content === '') {
            $failed++;
            continue;
        }

        if (@file_put_contents($targetFile, $content, LOCK_EX) === false) {
            $failed++;
            continue;
        }
        $downloaded++;
    }
}

echo json_encode([
    'ok' => true,
    'downloaded' => $downloaded,
    'failed' => $failed,
    'path' => $basePart
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
