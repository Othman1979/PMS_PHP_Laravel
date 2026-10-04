<?php

/**
 * Router for PHP's built-in web server (`php -S 0.0.0.0:8000 server.php`), used by the
 * local XAMPP launcher. The built-in server sends static files without any cache headers,
 * so every page reload re-downloads Bootstrap, the fonts and scripts; this router serves
 * them with Cache-Control / Last-Modified and answers 304 when the browser already has them.
 * Everything else goes to Laravel's front controller.
 */
$publicPath = (string) realpath(__DIR__.'/public');
$uri = urldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($publicPath.$uri);

if ($uri !== '/' && $file !== false && str_starts_with($file, $publicPath.DIRECTORY_SEPARATOR) && is_file($file) && ! str_ends_with($file, '.php')) {
    $types = [
        'css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8', 'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json', 'webmanifest' => 'application/manifest+json', 'map' => 'application/json',
        'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=UTF-8',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mtime = (int) filemtime($file);
    $etag = '"'.dechex($mtime).'-'.dechex((int) filesize($file)).'"';
    $noCache = in_array($uri, ['/sw.js', '/manifest.webmanifest'], true) || str_starts_with($uri, '/uploads/');

    header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
    header('ETag: '.$etag);
    header('Cache-Control: '.($noCache ? 'no-cache' : 'public, max-age=2592000'));

    $since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
    $match = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
    if ($match === $etag || ($since !== null && strtotime($since) >= $mtime)) {
        http_response_code(304);

        return true;
    }

    header('Content-Length: '.filesize($file));
    readfile($file);

    return true;
}

$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require $publicPath.'/index.php';
