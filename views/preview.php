<section class="card center">
  <h1>هذا الرابط يأخذك إلى:</h1>
  <p class="dest" dir="ltr"><?= h($link['url']) ?></p>
  <?php if ($link['title'] !== ''): ?><p class="muted"><?= h($link['title']) ?></p><?php endif; ?>
  <p><a class="btn" href="<?= h($link['url']) ?>" rel="noopener noreferrer">متابعة</a></p>
</section>
