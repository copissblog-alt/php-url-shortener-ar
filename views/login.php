<section class="card narrow">
  <h1>دخول</h1>
  <?php if ($error): ?><p class="msg bad"><?= h($error) ?></p><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label>اسم المستخدم<input name="username" dir="ltr" required autocomplete="username"></label>
    <label>كلمة المرور<input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn">دخول</button>
  </form>
</section>
