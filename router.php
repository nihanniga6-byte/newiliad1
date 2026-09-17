<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri === '/' || $uri === '') {
    $uri = '/login.html';
}

if (file_exists(__DIR__ . $uri) && pathinfo($uri, PATHINFO_EXTENSION) === 'php') {
    return false;
}

if (file_exists(__DIR__ . $uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $types = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'webp' => 'image/webp',
    ];
    $type = $types[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $type);
    readfile(__DIR__ . $uri);
    return true;
}

http_response_code(404);
echo 'Not Found';
