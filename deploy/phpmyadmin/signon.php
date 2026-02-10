<?php
/**
 * phpMyAdmin Single Sign-On handler for VSISPanel
 *
 * This script handles auto-login to phpMyAdmin from the panel.
 * It receives a signed token, validates it, and sets up the session.
 */

declare(strict_types=1);

// Set session cookie parameters before starting session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start session with phpMyAdmin signon session name
session_name('SignonSession');
session_start();

// Get token from query string
$token = $_GET['token'] ?? '';

if (empty($token)) {
    // If no token, show error page
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>phpMyAdmin - Authentication Error</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 50px; background: #f5f5f5; }
        .error { background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-top: 0; }
        p { color: #666; }
        a { color: #1976d2; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Authentication Error</h1>
        <p>Invalid request: missing authentication token.</p>
        <p>Please use the panel to access phpMyAdmin with auto-login, or <a href="/phpmyadmin/index.php?server=1">login manually</a>.</p>
    </div>
</body>
</html>';
    exit;
}

// Token format: base64(json({data: {user, pass, db, host, exp}, sig}))
// Signature is HMAC-SHA256 of the data with the secret key

// Read the secret key from VSISPanel
$secretKeyFile = '/opt/vsispanel/storage/app/phpmyadmin_secret.key';
if (!file_exists($secretKeyFile)) {
    die('Configuration error: secret key not found');
}

$secretKey = trim(file_get_contents($secretKeyFile));

// Decode the token
$tokenData = json_decode(base64_decode($token), true);

if (!$tokenData || !isset($tokenData['data']) || !isset($tokenData['sig'])) {
    die('Invalid token format');
}

$data = $tokenData['data'];
$signature = $tokenData['sig'];

// Verify signature
$expectedSignature = hash_hmac('sha256', json_encode($data), $secretKey);

if (!hash_equals($expectedSignature, $signature)) {
    die('Invalid token signature');
}

// Check expiration (token valid for 60 seconds)
if (!isset($data['exp']) || $data['exp'] < time()) {
    die('Token expired. Please try again from the panel.');
}

// Extract credentials
$user = $data['user'] ?? '';
$pass = $data['pass'] ?? '';
$db = $data['db'] ?? '';
$host = $data['host'] ?? 'localhost';

if (empty($user)) {
    die('Invalid credentials');
}

// Set session variables for phpMyAdmin signon
$_SESSION['PMA_single_signon_user'] = $user;
$_SESSION['PMA_single_signon_password'] = $pass;
$_SESSION['PMA_single_signon_host'] = $host;
$_SESSION['PMA_single_signon_port'] = 3306;

// Force write session before redirect
session_write_close();

// Detect which server index has signon auth
// Read phpMyAdmin config to find the correct server number
$signonServer = null;
$cfg = [];
$i = 0;

// Load phpMyAdmin config to find server index
$configFiles = [
    '/etc/phpmyadmin/config.inc.php',
    '/usr/share/phpmyadmin/config.inc.php',
];
foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        // Suppress any output from config loading
        ob_start();
        try {
            include $configFile;
        } catch (\Throwable $e) {
            // ignore
        }
        ob_end_clean();
        break;
    }
}

// Find server with signon auth
if (!empty($cfg['Servers'])) {
    foreach ($cfg['Servers'] as $idx => $server) {
        if (($server['auth_type'] ?? '') === 'signon') {
            $signonServer = $idx;
            break;
        }
    }
}

// Default to server=2 if detection fails
$serverIdx = $signonServer ?? 2;

$redirectUrl = '/phpmyadmin/index.php?server=' . $serverIdx;
if (!empty($db)) {
    $redirectUrl .= '&db=' . urlencode($db);
}

// Redirect to phpMyAdmin
header('Location: ' . $redirectUrl);
exit;
