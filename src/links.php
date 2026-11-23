<?php
declare(strict_types=1);

const CODE_ALPHABET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // بلا 0 O 1 l I

/** رمز عشوائي آمن: random_int لا rand */
function random_code(int $len): string
{
    $max = strlen(CODE_ALPHABET) - 1;
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= CODE_ALPHABET[random_int(0, $max)];
    }
    return $s;
}

/** يعيد الرابط بعد التنظيف، أو رسالة خطأ */
function validate_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048) {
        return [null, 'الرابط فارغ أو أطول من 2048 حرفاً.'];
    }
    $parts = parse_url($url);
    if ($parts === false) {
        return [null, 'الرابط غير صحيح.'];
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [null, 'يُقبل فقط رابط يبدأ بـ http:// أو https://'];
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false || empty($parts['host'])) {
        return [null, 'الرابط غير صحيح.'];
    }
    $own = parse_url((string) cfg('base_url'), PHP_URL_HOST);
    if ($own && strcasecmp($parts['host'], $own) === 0) {
        return [null, 'لا يمكن اختصار رابط من المختصر نفسه: يصنع حلقة تحويل.'];
    }
    return [$url, null];
}

function validate_alias(string $alias): ?string
{
    if (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $alias)) {
        return 'الاسم المخصص من 3 إلى 32 حرفاً: حروف إنجليزية وأرقام و - و _ فقط.';
    }
    if (in_array(strtolower($alias), RESERVED, true)) {
        return 'هذا الاسم محجوز للنظام.';
    }
    return null;
}

function create_link(string $url, string $alias = '', string $title = ''): array
{
    [$url, $err] = validate_url($url);
    if ($err) {
        return [null, $err];
    }
    $title = mb_substr(trim($title), 0, 120);
    $ins = db()->prepare('INSERT INTO links (code, url, title, created_at) VALUES (?, ?, ?, ?)');
    if ($alias !== '') {
        if ($e = validate_alias($alias)) {
            return [null, $e];
        }
        try {
            $ins->execute([$alias, $url, $title, time()]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            return [null, 'الاسم «' . $alias . '» مستعمل، اختر غيره.'];
        }
        return [$alias, null];
    }
    // رمز عشوائي: نعيد المحاولة إن صادف رمزاً موجوداً
    for ($i = 0; $i < 10; $i++) {
        $code = random_code((int) cfg('code_length'));
        try {
            $ins->execute([$code, $url, $title, time()]);
            return [$code, null];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }
    return [null, 'تعذر توليد رمز فريد، أعد المحاولة.'];
}

function find_link(string $code): ?array
{
    $st = db()->prepare('SELECT * FROM links WHERE code = ?');
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function all_links(): array
{
    return db()->query('SELECT * FROM links ORDER BY id DESC')->fetchAll();
}

function set_active(int $id, bool $on): void
{
    db()->prepare('UPDATE links SET active = ? WHERE id = ?')->execute([$on ? 1 : 0, $id]);
}

function delete_link(int $id): void
{
    db()->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
}
