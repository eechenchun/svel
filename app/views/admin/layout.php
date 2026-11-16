<?php
/**
 * SvelCMS 后台 - 布局
 * @var string $adminTitle
 * @var string $content
 * @var array $user
 * @var string $active
 * @var array|null $flash
 */
$navItems = [
    ['dash', admin_url(''), '仪表盘'],
    ['post', admin_url('post'), '文章'],
    ['category', admin_url('category'), '分类'],
    ['tag', admin_url('tag'), '标签'],
    ['nav', admin_url('nav'), '导航'],
    ['setting', admin_url('setting'), '设置'],
    ['market', admin_url('market'), '应用市场'],
['upgrade', admin_url('upgrade'), '系统升级'],
    ['audit', admin_url('audit'), '审计日志'],
];
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($adminTitle) ?> - SvelCMS 后台</title>
<link rel="stylesheet" href="<?= e(asset_v('app/assets/admin.css')) ?>">
<?= $editorAssets ?? '' ?>
</head>
<body class="admin">
<aside class="side">
  <a class="side-brand" href="<?= e(url('')) ?>" target="_blank" rel="noopener" title="打开网站前台">SvelCMS</a>
  <nav class="side-nav">
    <?php foreach ($navItems as [$key, $href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $active === $key ? ' class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">查看站点</a>
    <a href="<?= e(admin_url('logout')) ?>">退出</a>
  </div>
</aside>
<main class="main">
  <header class="top">
    <h1><?= e($adminTitle) ?></h1>
    <span class="who"><?= e((string)($user['nickname'] ?: $user['username'])) ?></span>
  </header>
  <?php if (is_array($flash) && !empty($flash['msg'])): ?>
    <div class="flash flash-<?= e((string)($flash['type'] ?? 'ok')) ?>"><?= e((string)$flash['msg']) ?></div>
  <?php endif; ?>
  <?= $content ?>
</main>
</body>
</html>