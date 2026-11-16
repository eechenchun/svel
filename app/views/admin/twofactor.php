<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>两步验证 - SvelCMS 后台</title>
<link rel="stylesheet" href="<?= e(asset_v('app/assets/admin.css')) ?>">
</head>
<body class="admin">
<div class="login-wrap">
  <h1>两步验证</h1>
  <div class="card">
    <p class="hint">账号：<strong><?= e($username) ?></strong>。请输入验证器中的 6 位验证码，或使用一条恢复码。</p>
    <?php if ($error !== ''): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(admin_url('2fa')) ?>" class="form">
      <?= \Svel\Core\Csrf::field() ?>
      <label class="field"><span>验证码 / 恢复码</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required></label>
      <button class="btn btn-primary" type="submit">验证并进入后台</button>
    </form>
    <p class="hint" style="margin-top:14px"><a href="<?= e(admin_url('login')) ?>">返回登录</a></p>
  </div>
</div>
</body>
</html>