<?php
// Existing process variables take precedence over local credentials.
foreach (['.env.local', '.env'] as $filename) {
    $path = dirname(__DIR__) . '/' . $filename;
    if (!is_file($path)) continue;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', trim($line), $match)) continue;
        if (getenv($match[1]) !== false) continue;
        $value = trim($match[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
        putenv($match[1] . '=' . $value);
    }
}
date_default_timezone_set('Asia/Manila');
function requiredEnv($name) {
    $value = getenv($name);
    if (!$value) throw new RuntimeException('Missing server configuration: ' . $name);
    return $value;
}
