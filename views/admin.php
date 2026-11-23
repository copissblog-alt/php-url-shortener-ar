<section class="card">
  <h1>رابط قصير جديد</h1>
  <?php if ($flash): ?>
    <p class="msg <?= $flash[0] === 'ok' ? 'ok' : 'bad' ?>">
      <?php if ($flash[0] === 'ok' && str_starts_with($flash[1], 'http')): ?>
        جاهز: <a href="<?= h($flash[1]) ?>" dir="ltr" id="new-link"><?= h($flash[1]) ?></a> <button class="link" data-copy="<?= h($flash[1]) ?>">نسخ</button>
      <?php else: ?><?= h($flash[1]) ?><?php endif; ?>
    </p>
  <?php endif; ?>
  <form method="post" action="/admin/create" class="grid"><?= csrf_field() ?>
    <label class="wide">الرابط الطويل<input name="url" type="url" dir="ltr" required placeholder="https://"></label>
    <label>اسم مخصص (اختياري)<input name="alias" dir="ltr" pattern="[A-Za-z0-9_\-]{3,32}" placeholder="offer2026"></label>
    <label>وصف لك (اختياري)<input name="title" maxlength="120"></label>
    <button class="btn">اختصر</button>
  </form>
</section>
<section class="card">
  <h2>الروابط (<?= count($links) ?>)</h2>
  <?php if (!$links): ?><p class="muted">لا روابط بعد.</p><?php else: ?>
  <div class="scroll"><table>
    <thead><tr><th>الرابط القصير</th><th>الوجهة</th><th>النقرات</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($links as $l): ?>
      <tr class="<?= $l['active'] ? '' : 'off' ?>">
        <td dir="ltr"><a href="<?= h(short_url($l['code'])) ?>"><?= h($l['code']) ?></a> <button class="link" data-copy="<?= h(short_url($l['code'])) ?>">نسخ</button><?php if ($l['title'] !== ''): ?><div class="muted"><?= h($l['title']) ?></div><?php endif; ?></td>
        <td class="url" dir="ltr" title="<?= h($l['url']) ?>"><?= h(mb_strimwidth($l['url'], 0, 60, '…')) ?></td>
        <td><a href="/admin/stats/<?= h($l['code']) ?>"><?= number_format((int) $l['clicks']) ?></a></td>
        <td class="actions">
          <form method="post" action="/admin/toggle" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="on" value="<?= $l['active'] ? '0' : '1' ?>"><button class="link"><?= $l['active'] ? 'إيقاف' : 'تشغيل' ?></button></form>
          <form method="post" action="/admin/delete" class="inline" data-confirm="حذف الرابط وكل إحصائياته؟"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="link danger">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
