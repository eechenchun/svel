<?php
/**
 * SvelCMS 后台 - 登录页
 * @var string $error
 * @var string $username
 */
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>登录 - SvelCMS 后台</title>
<link rel="stylesheet" href="<?= e(asset('app/assets/admin.css')) ?>?v=<?= e(SVEL_VERSION) ?>">
</head>
<body class="admin">
<div class="login-wrap">
  <h1><?= e((string)option('site.title', 'SvelCMS')) ?> 后台</h1>
  <div class="card">
    <?php if ($error !== ''): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(admin_url('login')) ?>">
      <?= \Svel\Core\Csrf::field() ?>
      <label class="field"><span>账号</span>
        <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
      </label>
      <label class="field"><span>密码</span>
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button class="btn btn-primary" type="submit">登录</button>
    </form>
  </div>
  <p class="hint" style="text-align:center">忘记密码？用数据库工具重置 <code>svel_user</code> 表里的 <code>password_hash</code>。</p>
</div>
</body>
</html>