<?php
declare(strict_types=1);

function is_bot(string $ua): bool
{
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|httpclient/i', $ua);
}

function device_of(string $ua): string
{
    if (preg_match('/ipad|tablet/i', $ua)) {
        return 'جهاز لوحي';
    }
    return preg_match('/mobile|android|iphone/i', $ua) ? 'جوال' : 'كمبيوتر';
}

function browser_of(string $ua): string
{
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $k => $name) {
        if (stripos($ua, $k) !== false) {
            return $name;
        }
    }
    return 'أخرى';
}

/** نسجل النقرة في معاملة واحدة: صف في clicks وزيادة العداد */
function record_click(array $link): void
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (is_bot($ua)) {
        return; // معاينات الروابط وزواحف البحث لا تُحسب
    }
    $ref = (string) (parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST) ?? '');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO clicks (link_id, ts, day, referrer, device, browser, ip_hash) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$link['id'], time(), gmdate('Y-m-d'), mb_substr($ref, 0, 120), device_of($ua), browser_of($ua), ip_hash()]);
        $pdo->prepare('UPDATE links SET clicks = clicks + 1 WHERE id = ?')->execute([$link['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function link_stats(int $id, int $days = 30): array
{
    $pdo = db();
    $from = gmdate('Y-m-d', time() - ($days - 1) * 86400);
    $q = function (string $sql, array $args) use ($pdo) {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    };
    $perDay = [];
    foreach ($q('SELECT day, COUNT(*) n, COUNT(DISTINCT ip_hash) u FROM clicks WHERE link_id = ? AND day >= ? GROUP BY day', [$id, $from]) as $r) {
        $perDay[$r['day']] = $r;
    }
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = gmdate('Y-m-d', time() - $i * 86400);
        $series[] = ['day' => $d, 'n' => (int) ($perDay[$d]['n'] ?? 0), 'u' => (int) ($perDay[$d]['u'] ?? 0)];
    }
    $one = $q('SELECT COUNT(*) total, COUNT(DISTINCT ip_hash || day) uniq FROM clicks WHERE link_id = ?', [$id])[0];
    return [
        'total'    => (int) $one['total'],
        'unique'   => (int) $one['uniq'],
        'series'   => $series,
        'referrer' => $q("SELECT CASE referrer WHEN '' THEN 'مباشر' ELSE referrer END k, COUNT(*) n FROM clicks WHERE link_id = ? GROUP BY k ORDER BY n DESC LIMIT 10", [$id]),
        'device'   => $q('SELECT device k, COUNT(*) n FROM clicks WHERE link_id = ? GROUP BY k ORDER BY n DESC', [$id]),
        'browser'  => $q('SELECT browser k, COUNT(*) n FROM clicks WHERE link_id = ? GROUP BY k ORDER BY n DESC', [$id]),
    ];
}

function export_csv(array $link): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clicks-' . $link['code'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // ليقرأ Excel العربية
    fputcsv($out, ['time_utc', 'referrer', 'device', 'browser', 'visitor']);
    $st = db()->prepare('SELECT ts, referrer, device, browser, ip_hash FROM clicks WHERE link_id = ? ORDER BY id');
    $st->execute([$link['id']]);
    while ($r = $st->fetch()) {
        // نمنع حقن الصيغ في Excel: خلية تبدأ بـ = أو + أو - أو @
        $cells = array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, [gmdate('Y-m-d H:i:s', (int) $r['ts']), $r['referrer'], $r['device'], $r['browser'], $r['ip_hash']]);
        fputcsv($out, $cells);
    }
    fclose($out);
    exit;
}
