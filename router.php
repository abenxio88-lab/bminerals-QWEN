<?php
/**
 * Balochistan Minerals - Development Server Router
 * Emulates Apache mod_rewrite clean URLs for PHP built-in web server (php -S).
 * Maps clean URLs like /contact -> /contact.html, /blogs -> /blogs.html, /logistics -> /logistics.html.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);

// Strip trailing slash except for root
$cleanUri = rtrim($uri, '/');
if ($cleanUri === '') {
    $cleanUri = '/';
}

$root = __DIR__;
$filePath = $root . $uri;

// 1. Direct Physical File Exists
if ($uri !== '/' && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === 'avif') {
        header('Content-Type: image/avif');
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
    if ($ext === 'webmanifest') {
        header('Content-Type: application/manifest+json');
        readfile($filePath);
        exit;
    }
    // Return false to let PHP serve static file or execute .php natively
    return false;
}

// 2. Directory without trailing slash -> redirect to add slash
if (is_dir($filePath) && substr($uri, -1) !== '/') {
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $uri . '/' . $qs);
    exit;
}

// 3. Directory Index
if (is_dir($filePath)) {
    if (is_file($filePath . '/index.php')) {
        require $filePath . '/index.php';
        exit;
    }
    if (is_file($filePath . '/index.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($filePath . '/index.html');
        exit;
    }
}

// 4. Clean URL -> .html file (e.g. /contact -> /contact.html, /blogs -> /blogs.html, /minerals/chromite-ore -> /minerals/chromite-ore.html)
if (is_file($root . $cleanUri . '.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($root . $cleanUri . '.html');
    exit;
}

// 5. Clean URL -> .php file (e.g. /api/inquiry -> /api/inquiry.php)
if (is_file($root . $cleanUri . '.php')) {
    require $root . $cleanUri . '.php';
    exit;
}

// 6. Homepage / Root URL
if ($uri === '/' || $uri === '') {
    if (is_file($root . '/index.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($root . '/index.html');
        exit;
    }
    return false;
}

// 7. Not Found -> 404 Error Page
http_response_code(404);
if (is_file($root . '/404.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($root . '/404.html');
} else {
    echo '<!DOCTYPE html><html lang="en"><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
}
exit;
