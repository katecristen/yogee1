<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$db->beginTransaction();
try {
    $db->exec(file_get_contents(__DIR__ . '/../database/001-neon.sql'));
    $db->commit();
    echo "PASS PostgreSQL schema applied; packages: " . $db->query('SELECT count(*) FROM packages')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) { $db->rollBack(); throw $e; }
