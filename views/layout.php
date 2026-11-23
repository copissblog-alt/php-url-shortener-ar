<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h($title ?? 'مختصر الروابط') ?></title>
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<header class="top"><a href="/admin" class="brand">مختصر الروابط</a>
<?php if (!empty($user)): ?>
  <form method="post" action="/admin/logout" class="inline"><?= csrf_field() ?><span class="muted"><?= h($user['username']) ?></span> <button class="link">خروج</button></form>
<?php endif; ?>
</header>
<main><?= $content ?></main>
</body>
</html>
