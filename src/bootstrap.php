<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const RESERVED = ['admin', 'setup', 'assets', 'login', 'logout', 'api', 'favicon.ico', 'robots.txt'];

$cfgFile = getenv('SL_CONFIG') ?: APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('config.php غير موجود: انسخ config.sample.php إلى config.php وعدّله.');
}
$CONFIG = require $cfgFile;

function cfg(string $key)
{
    global $CONFIG;
    return $CONFIG[$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . cfg('db_path'), null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // WAL: القراءة لا تنتظر الكتابة، والكتابات المتزامنة تنتظر دورها بدل أن تفشل
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000; PRAGMA foreign_keys = ON; PRAGMA synchronous = NORMAL;');
    require_once __DIR__ . '/schema.php';
    migrate($pdo);
    return $pdo;
}

/** هروب النص قبل طباعته في HTML */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('sl_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
}

/** تمويه عنوان IP: نفس العنوان يعطي نفس القيمة، ولا يمكن استرجاعه منها */
function ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr(hash_hmac('sha256', $ip, (string) cfg('secret')), 0, 16);
}

function redirect_to(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/views/layout.php';
}

function not_found(): void
{
    http_response_code(404);
    render('404', ['title' => 'غير موجود']);
    exit;
}

function short_url(string $code): string
{
    return rtrim((string) cfg('base_url'), '/') . '/' . $code;
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/links.php';
require_once __DIR__ . '/stats.php';
