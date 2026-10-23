<?php
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!SessionManager::isUserLoggedIn()) { http_response_code(401); echo json_encode(['message' => 'Please sign in.']); exit; }
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['reservation_id'] ?? '';
$attachmentId = $_GET['attachment_id'] ?? '';
$headers = [];
$body = null;
if ($action === 'live' && $method === 'GET') $route = '/live';
elseif ($action === 'attachments' && ctype_digit((string)$id) && in_array($method, ['GET', 'POST'])) {
    $route = '/reservations/' . $id . '/attachments';
    if ($method === 'POST') {
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) { http_response_code(400); echo json_encode(['message' => 'Choose a file up to 5 MB.']); exit; }
        $body = file_get_contents($file['tmp_name']);
        $headers[] = 'Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $headers[] = 'X-File-Name: ' . rawurlencode(basename($file['name']));
    }
} elseif (in_array($action, ['download', 'delete_attachment']) && preg_match('/^[a-f0-9-]{36}$/', $attachmentId) && (($action === 'download' && $method === 'GET') || ($action === 'delete_attachment' && $method === 'POST'))) {
    $route = '/attachments/' . $attachmentId;
    if ($action === 'delete_attachment') $method = 'DELETE';
} else { http_response_code(400); echo json_encode(['message' => 'Invalid request']); exit; }
try {
    $headers[] = 'Authorization: Bearer ' . neonAuthToken();
    $headers[] = 'X-Secret: ' . requiredEnv('ORIGIN_SECRET');
    $curl = curl_init(rtrim(requiredEnv('NEON_FUNCTION_HELLO_BASE_URL'), '/') . $route);
    $responseHeaders = [];
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
            if (preg_match('/^(Content-Type|Content-Disposition):/i', $header)) $responseHeaders[] = trim($header);
            return strlen($header);
        }
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($result === false) throw new RuntimeException('Function could not be reached');
    curl_close($curl);
    http_response_code($status);
    foreach ($responseHeaders as $header) header($header);
    if ($action === 'live' && $status === 200) {
        $data = json_decode($result, true);
        $data['realtimeUrl'] = requiredEnv('NEON_REALTIME_URL');
        echo json_encode($data);
    } else echo $result;
} catch (Throwable $e) { error_log($e->getMessage()); http_response_code(502); echo json_encode(['message' => 'Connected service unavailable. Please try again.']); }
