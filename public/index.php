<?php
declare(strict_types=1);

// خادم PHP المدمج (للتجربة المحلية): الملفات الموجودة تُخدم كما هي
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// 1) التحويل: أسرع مسار، بلا جلسة ولا قوالب
if ($method === 'GET' && preg_match('#^/([A-Za-z0-9_-]{3,32})$#', $path, $m) && !in_array(strtolower($m[1]), RESERVED, true)) {
    $link = find_link($m[1]);
    if (!$link || !$link['active']) {
        security_headers();
        not_found();
    }
    try {
        record_click($link);
    } catch (Throwable $e) {
        // الإحصائيات لا يجب أن تمنع الزائر من الوصول: نسجل الخطأ ونكمل التحويل
        error_log('shortlink: click not recorded: ' . $e->getMessage());
    }
    header('Cache-Control: private, max-age=0');
    header('Location: ' . $link['url'], true, (int) cfg('redirect_code') === 301 ? 301 : 302);
    exit;
}

// 2) المعاينة: الرمز مع + في آخره يعرض الوجهة قبل الذهاب إليها
if ($method === 'GET' && preg_match('#^/([A-Za-z0-9_-]{3,32})\+$#', $path, $m)) {
    security_headers();
    $link = find_link($m[1]);
    if (!$link || !$link['active']) {
        not_found();
    }
    render('preview', ['title' => 'إلى أين يأخذك هذا الرابط؟', 'link' => $link]);
    exit;
}

// 3) بقية الصفحات تحتاج جلسة
security_headers();
start_session();

switch (true) {
    case $path === '/':
        redirect_to(current_user() ? '/admin' : '/admin/login');

    case $path === '/setup':
        if (has_admin()) {
            not_found(); // الإعداد يُغلق نهائياً بعد إنشاء المدير
        }
        $error = null;
        if ($method === 'POST') {
            csrf_check();
            $token = (string) ($_POST['token'] ?? '');
            $user  = trim((string) ($_POST['username'] ?? ''));
            $pass  = (string) ($_POST['password'] ?? '');
            if (cfg('setup_token') === null || str_starts_with((string) cfg('setup_token'), 'CHANGE-ME') || !hash_equals((string) cfg('setup_token'), $token)) {
                $error = 'رمز الإعداد غير صحيح. غيّر setup_token في config.php واكتبه هنا.';
            } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $user)) {
                $error = 'اسم المستخدم من 3 إلى 32 حرفاً إنجليزياً أو رقماً.';
            } elseif (strlen($pass) < 12) {
                $error = 'كلمة المرور 12 حرفاً على الأقل.';
            } else {
                create_admin($user, $pass);
                attempt_login($user, $pass);
                redirect_to('/admin');
            }
        }
        render('setup', ['title' => 'الإعداد الأول', 'error' => $error]);
        exit;

    case $path === '/admin/login':
        if (!has_admin()) {
            redirect_to('/setup');
        }
        $error = null;
        if ($method === 'POST') {
            csrf_check();
            if (login_blocked()) {
                http_response_code(429);
                $error = 'محاولات خاطئة كثيرة. انتظر 15 دقيقة.';
            } elseif (attempt_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
                redirect_to('/admin');
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
            }
        }
        render('login', ['title' => 'دخول', 'error' => $error]);
        exit;

    case $path === '/admin/logout' && $method === 'POST':
        csrf_check();
        $_SESSION = [];
        session_destroy();
        redirect_to('/admin/login');

    case $path === '/admin':
        $user = require_login();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        render('admin', ['title' => 'روابطي', 'user' => $user, 'links' => all_links(), 'flash' => $flash]);
        exit;

    case $path === '/admin/create' && $method === 'POST':
        require_login();
        csrf_check();
        [$code, $err] = create_link((string) ($_POST['url'] ?? ''), trim((string) ($_POST['alias'] ?? '')), (string) ($_POST['title'] ?? ''));
        $_SESSION['flash'] = $err ? ['bad', $err] : ['ok', short_url($code)];
        redirect_to('/admin');

    case in_array($path, ['/admin/toggle', '/admin/delete'], true) && $method === 'POST':
        require_login();
        csrf_check();
        $id = (int) ($_POST['id'] ?? 0);
        if ($path === '/admin/delete') {
            delete_link($id);
            $_SESSION['flash'] = ['ok', 'حُذف الرابط وإحصائياته.'];
        } else {
            set_active($id, ($_POST['on'] ?? '') === '1');
        }
        redirect_to('/admin');

    case (bool) preg_match('#^/admin/stats/([A-Za-z0-9_-]{3,32})$#', $path, $m):
        require_login();
        $link = find_link($m[1]) ?? not_found();
        render('stats', ['title' => 'إحصائيات ' . $link['code'], 'link' => $link, 's' => link_stats((int) $link['id'])]);
        exit;

    case (bool) preg_match('#^/admin/export/([A-Za-z0-9_-]{3,32})\.csv$#', $path, $m):
        require_login();
        export_csv(find_link($m[1]) ?? not_found());

    default:
        not_found();
}
