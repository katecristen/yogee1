<?php
// Use this router (or equivalent web-server rules) to protect secrets and source.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) { http_response_code(404); exit; }
if ($path === '/') { header('Location: /landing%20page.html'); exit; }
if (preg_match('#^/api/(login|logout|register|check_session|user_update|reservation|neon|admin_login|admin_logout|check_admin_session|admin_stats|admin_users|admin_packages|admin_reservations)\.php$#', $path) && is_file(__DIR__ . $path)) {
    // JSON mutations and the same-site session must originate from this app.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    if ($origin && $origin !== $scheme . '://' . $_SERVER['HTTP_HOST']) { http_response_code(403); exit; }
    try { require __DIR__ . $path; }
    catch (Throwable $e) { error_log($e->getMessage()); http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Service unavailable. Please try again.']); }
    return true;
}
if (preg_match('#^/[A-Za-z0-9 _-]+\.(html|css|js)$#', $path) || preg_match('#^/(images|assets)/[A-Za-z0-9 /_.-]+\.(png|jpg|jpeg|gif|webp|svg|ico|css|js)$#i', $path)) {
    if (is_file(__DIR__ . $path)) {
        if (PHP_SAPI === 'cli-server') return false;
        $mime = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mime[$extension] ?? (new finfo(FILEINFO_MIME_TYPE))->file(__DIR__ . $path)));
        header('X-Content-Type-Options: nosniff');
        readfile(__DIR__ . $path);
        return true;
    }
}
http_response_code(404);
echo 'Not found';
