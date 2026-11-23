<?php
// اختبارات تكامل: يشغّل خادم PHP المدمج على قاعدة مؤقتة ويجرب المسارات كما يفعل المتصفح.
// التشغيل: php tests/run.php
declare(strict_types=1);

$root = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . '/sl-test-' . bin2hex(random_bytes(4));
mkdir($tmp);
$cfg = "$tmp/config.php";
file_put_contents($cfg, "<?php return ['base_url' => 'http://127.0.0.1:8099', 'setup_token' => 'test-setup-token-123', 'secret' => 'test-secret', 'db_path' => '$tmp/links.sqlite', 'code_length' => 6, 'redirect_code' => 302];");

$port = 8099;
$proc = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$root/public", "$root/public/index.php"],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes, null, ['SL_CONFIG' => $cfg]
);
usleep(400000);

$jar = "$tmp/cookies";
function req(string $method, string $path, array $post = [], array $hdr = []): array
{
    global $port, $jar;
    $ch = curl_init("http://127.0.0.1:$port$path");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $hdr, CURLOPT_PROXY => '',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hs);
    preg_match('/^Location: (.+)$/mi', $head, $m);
    return ['code' => $code, 'head' => $head, 'body' => substr($raw, $hs), 'loc' => trim($m[1] ?? '')];
}
function token(string $html): string
{
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%s %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? "  ($detail)" : '');
}
$UA = ['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) AppleWebKit/605.1.15 Mobile Safari/604.1', 'Referer: https://t.co/abc'];

// الإعداد الأول
$r = req('GET', '/admin/login');
check('no admin yet -> /admin/login redirects to /setup', $r['code'] === 303 && $r['loc'] === '/setup', "{$r['code']} {$r['loc']}");
$t = token(req('GET', '/setup')['body']);
$r = req('POST', '/setup', ['csrf' => $t, 'token' => 'wrong', 'username' => 'admin', 'password' => 'a-very-long-password']);
check('setup with wrong token is refused', str_contains($r['body'], 'رمز الإعداد غير صحيح'));
$r = req('POST', '/setup', ['csrf' => $t, 'token' => 'test-setup-token-123', 'username' => 'admin', 'password' => 'short']);
check('setup refuses a short password', str_contains($r['body'], '12 حرفاً'));
$r = req('POST', '/setup', ['csrf' => $t, 'token' => 'test-setup-token-123', 'username' => 'admin', 'password' => 'a-very-long-password']);
check('setup creates the admin and logs in', $r['code'] === 303 && $r['loc'] === '/admin', "{$r['code']} {$r['loc']}");
check('setup page is closed afterwards', req('GET', '/setup')['code'] === 404);

// إنشاء الروابط
$t = token(req('GET', '/admin')['body']);
$r = req('POST', '/admin/create', ['url' => 'https://example.com/a', 'csrf' => 'forged']);
check('POST without the right CSRF token -> 403', $r['code'] === 403, (string) $r['code']);
req('POST', '/admin/create', ['csrf' => $t, 'url' => 'https://example.com/some/long/path?x=1&y=2', 'title' => 'test']);
$html = req('GET', '/admin')['body'];
preg_match('#id="new-link">http://127\.0\.0\.1:8099/([A-Za-z0-9]{6})<#', $html, $m);
$code = $m[1] ?? '';
check('random 6-char code created', strlen($code) === 6, $code);
foreach ([
    'javascript:alert(1)'               => 'يبدأ بـ http',
    'ftp://example.com/file'            => 'يبدأ بـ http',
    'http://127.0.0.1:8099/' . $code    => 'حلقة تحويل',
    'https://'                          => 'غير صحيح',
] as $bad => $expect) {
    req('POST', '/admin/create', ['csrf' => $t, 'url' => $bad]);
    $h = req('GET', '/admin')['body'];
    check("rejects $bad", str_contains($h, $expect));
}
req('POST', '/admin/create', ['csrf' => $t, 'url' => 'https://example.org/', 'alias' => 'offer2026']);
check('custom alias works', str_contains(req('GET', '/admin')['body'], '/offer2026'));
req('POST', '/admin/create', ['csrf' => $t, 'url' => 'https://example.org/2', 'alias' => 'offer2026']);
check('duplicate alias refused', str_contains(req('GET', '/admin')['body'], 'مستعمل'));
req('POST', '/admin/create', ['csrf' => $t, 'url' => 'https://example.org/3', 'alias' => 'admin']);
check('reserved alias refused', str_contains(req('GET', '/admin')['body'], 'محجوز'));
req('POST', '/admin/create', ['csrf' => $t, 'url' => 'https://example.org/4', 'alias' => "x' OR '1'='1"]);
check("SQL-injection-looking alias refused by the pattern", str_contains(req('GET', '/admin')['body'], 'من 3 إلى 32'));
req('POST', '/admin/create', ['csrf' => $t, 'url' => 'https://example.org/?q=<script>alert(1)</script>', 'title' => '<img src=x onerror=alert(1)>']);
$h = req('GET', '/admin')['body'];
check('HTML in title/url is escaped', !str_contains($h, '<img src=x') && str_contains($h, '&lt;img src=x'));

// التحويل والإحصائيات
$r = req('GET', "/$code", [], $UA);
check('redirect 302 to the long URL', $r['code'] === 302 && $r['loc'] === 'https://example.com/some/long/path?x=1&y=2', "{$r['code']} {$r['loc']}");
req('GET', "/$code", [], $UA);
req('GET', "/$code", [], ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0 Safari/537.36']);
req('GET', "/$code", [], ['User-Agent: Googlebot/2.1 (+http://www.google.com/bot.html)']);
req('GET', "/$code", [], ['User-Agent: TelegramBot (like TwitterBot)']);
$s = req('GET', "/admin/stats/$code")['body'];
preg_match('#<b>([0-9,]+)</b><span>نقرة#u', $s, $n);
check('3 human clicks counted, 2 bots ignored', ($n[1] ?? '') === '3', $n[1] ?? '?');
check('referrer t.co recorded', str_contains($s, 't.co'));
check('device split: mobile + desktop', str_contains($s, 'جوال') && str_contains($s, 'كمبيوتر'));
$r = req('GET', "/$code+");
check('preview page with + shows destination', $r['code'] === 200 && str_contains($r['body'], 'example.com/some/long/path'));
check('unknown code -> 404', req('GET', '/zzzzzz')['code'] === 404);
$csv = req('GET', "/admin/export/$code.csv");
check('CSV export has header + 3 rows', $csv['code'] === 200 && substr_count(trim($csv['body']), "\n") === 3, (string) substr_count(trim($csv['body']), "\n"));

// الإيقاف والحذف
$h = req('GET', '/admin')['body'];
preg_match('#/' . $code . '".*?name="id" value="(\d+)"#s', $h, $idm);
$id = $idm[1] ?? '0';
req('POST', '/admin/toggle', ['csrf' => $t, 'id' => $id, 'on' => '0']);
check('disabled link -> 404', req('GET', "/$code")['code'] === 404);
req('POST', '/admin/toggle', ['csrf' => $t, 'id' => $id, 'on' => '1']);
check('enabled again -> 302', req('GET', "/$code", [], $UA)['code'] === 302);
preg_match('#/offer2026".*?name="id" value="(\d+)"#s', $h, $om);
req('POST', '/admin/delete', ['csrf' => $t, 'id' => $om[1] ?? '0']);
check('deleted link -> 404', req('GET', '/offer2026')['code'] === 404);

// الحماية
$r = req('GET', '/admin');
check('security headers sent', str_contains($r['head'], 'X-Frame-Options: DENY') && str_contains($r['head'], 'Content-Security-Policy'));
req('POST', '/admin/logout', ['csrf' => $t]);
check('logged out -> /admin redirects to login', req('GET', '/admin')['loc'] === '/admin/login');
check('stats need login', req('GET', "/admin/stats/$code")['loc'] === '/admin/login');
$t2 = token(req('GET', '/admin/login')['body']);
for ($i = 0; $i < 5; $i++) {
    req('POST', '/admin/login', ['csrf' => $t2, 'username' => 'admin', 'password' => 'wrong' . $i]);
}
$r = req('POST', '/admin/login', ['csrf' => $t2, 'username' => 'admin', 'password' => 'a-very-long-password']);
check('6th attempt after 5 wrong passwords is blocked (429), even with the right password', $r['code'] === 429, (string) $r['code']);
check('database file not reachable from the web root', req('GET', '/../data/links.sqlite')['code'] === 404);

proc_terminate($proc);
array_map('unlink', glob("$tmp/*"));
rmdir($tmp);
printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
