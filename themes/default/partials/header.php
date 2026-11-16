<?php
/**
 * 默认主题 - 页头
 * @var array $site
 * @var array $nav
 * @var string $current
 */
$here = (string)($current ?? '');
$navLinks = Svel\Models\Nav::active();
?><header class="site-header">
  <div class="wrap header-inner">
    <div class="brand">
      <a class="brand-title" href="<?= e(url('')) ?>"><?= e($site['title']) ?></a>
      <?php if ($site['tagline'] !== '' && (string)app_setting('default', 'show_tagline', '1') === '1'): ?>
        <span class="brand-tagline"><?= e($site['tagline']) ?></span>
      <?php endif; ?>
    </div>
    <nav class="site-nav" aria-label="主导航">
      <?php if ($navLinks !== []): ?>
        <?php foreach ($navLinks as $item): ?>
          <?php
          $raw = (string)$item['url'];
          if (preg_match('#^https?://#i', $raw)) {
              $href = $raw;
          } elseif ($raw === '/' || $raw === '') {
              $href = url('');
          } elseif (str_starts_with($raw, '#')) {
              $href = $raw;
          } else {
              $href = url(ltrim($raw, '/'));
          }
          $blank = (string)$item['target'] === '_blank';
          ?>
          <a href="<?= e($href) ?>"<?= $href === $here ? ' class="active"' : '' ?><?= $blank ? ' target="_blank" rel="noopener"' : '' ?>><?= e($item['title']) ?></a>
        <?php endforeach; ?>
      <?php else: ?>
        <a href="<?= e(url('')) ?>"<?= $here === url('') ? ' class="active"' : '' ?>>首页</a>
        <?php foreach ($nav as $cat): ?>
          <?php if ((int)$cat['total'] === 0) { continue; } ?>
          <a href="<?= e(category_url($cat, 1)) ?>"<?= $here === category_url($cat, 1) ? ' class="active"' : '' ?>><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <form class="search-form" method="get" action="<?= e(url('search')) ?>" role="search">
      <input type="search" name="q" placeholder="搜索" aria-label="站内搜索" value="">
      <button type="submit">搜</button>
    </form>
  </div>
</header>