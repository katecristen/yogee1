<?php
require_once __DIR__ . '/env.php';
// Bridge existing HttpOnly PHP sessions to Managed Better Auth. Upstream
// cookies and JWTs stay on the server and never enter browser localStorage.
function neonAuthRequest($route, $data = null, $authenticated = true) {
    $curl = curl_init(rtrim(requiredEnv('NEON_AUTH_BASE_URL'), '/') . '/' . $route);
    $cookies = [];
    $headers = ['Content-Type: application/json', 'Accept: application/json', 'Origin: ' . (getenv('APP_ORIGIN') ?: 'http://127.0.0.1:8080')];
    if ($authenticated && !empty($_SESSION['neon_auth_cookie'])) $headers[] = 'Cookie: ' . $_SESSION['neon_auth_cookie'];
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$cookies) {
            if (stripos($header, 'set-cookie:') === 0) $cookies[] = trim(explode(';', substr($header, 11), 2)[0]);
            return strlen($header);
        }
    ]);
    if ($data !== null) { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data)); }
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) throw new RuntimeException('Auth service could not be reached: ' . $error);
    $result = json_decode($body, true);
    if ($status >= 400) throw new RuntimeException($result['message'] ?? 'Authentication failed');
    if ($cookies && session_status() === PHP_SESSION_ACTIVE) {
        $jar = [];
        foreach (array_merge(explode('; ', $_SESSION['neon_auth_cookie'] ?? ''), $cookies) as $cookie) {
            $pair = explode('=', $cookie, 2);
            if (count($pair) === 2) $jar[$pair[0]] = $pair[1];
        }
        $_SESSION['neon_auth_cookie'] = implode('; ', array_map(function ($name) use ($jar) { return $name . '=' . $jar[$name]; }, array_keys($jar)));
    }
    return $result;
}
function neonAuthToken() {
    $session = neonAuthRequest('get-session');
    if (empty($session['user']['id'])) throw new RuntimeException('Please sign in again.');
    $token = neonAuthRequest('token');
    if (empty($token['token'])) throw new RuntimeException('No authentication token returned.');
    return $token['token'];
}
