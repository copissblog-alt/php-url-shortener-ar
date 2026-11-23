<?php $max = max(1, ...array_column($s['series'], 'n')); ?>
<section class="card">
  <p><a href="/admin">← الروابط</a></p>
  <h1 dir="ltr" class="code"><?= h(short_url($link['code'])) ?></h1>
  <p class="dest" dir="ltr"><?= h($link['url']) ?></p>
  <div class="kpis">
    <div><b><?= number_format($s['total']) ?></b><span>نقرة</span></div>
    <div><b><?= number_format($s['unique']) ?></b><span>زائر مختلف (في اليوم)</span></div>
    <div><b><?= $link['active'] ? 'يعمل' : 'موقوف' ?></b><span>الحالة</span></div>
  </div>
  <h2>آخر 30 يوماً</h2>
  <div class="bars" role="img" aria-label="النقرات اليومية">
    <?php foreach ($s['series'] as $d): ?>
      <div class="bar" title="<?= h($d['day']) ?>: <?= $d['n'] ?>"><i data-h="<?= round($d['n'] / $max * 100) ?>"></i></div>
    <?php endforeach; ?>
  </div>
  <div class="three">
    <?php foreach (['referrer' => 'من أين جاؤوا', 'device' => 'الجهاز', 'browser' => 'المتصفح'] as $k => $label): ?>
      <div><h3><?= $label ?></h3><table><?php foreach ($s[$k] as $r): ?><tr><td><?= h($r['k']) ?></td><td><?= number_format((int) $r['n']) ?></td></tr><?php endforeach; ?></table></div>
    <?php endforeach; ?>
  </div>
  <p><a class="btn ghost" href="/admin/export/<?= h($link['code']) ?>.csv">تنزيل النقرات CSV</a></p>
</section>
