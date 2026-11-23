<section class="card narrow">
  <h1>الإعداد الأول</h1>
  <p class="muted">أنشئ حساب المدير. هذه الصفحة تُغلق نهائياً بعد إنشائه.</p>
  <?php if ($error): ?><p class="msg bad"><?= h($error) ?></p><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label>رمز الإعداد (من config.php)<input name="token" type="password" required autocomplete="off"></label>
    <label>اسم المستخدم<input name="username" dir="ltr" required pattern="[A-Za-z0-9_.\-]{3,32}"></label>
    <label>كلمة المرور (12 حرفاً على الأقل)<input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
    <button class="btn">إنشاء المدير</button>
  </form>
</section>
