<?php
/**
 * 默认主题 - 独立页面
 * @var array $page
 */
?><div class="wrap">
  <article class="post">
    <header class="post-head">
      <h1 class="post-title"><?= e($page['title']) ?></h1>
      <?php if ((int)$page['updated_at'] > 0): ?>
        <p class="post-meta">更新于 <time datetime="<?= e(iso_date((int)$page['updated_at'])) ?>"><?= e(fmt_date((int)$page['updated_at'], 'Y年n月j日')) ?></time></p>
      <?php endif; ?>
    </header>
    <div class="post-content">
      <?= content_html($page) ?>
    </div>
  </article>
</div>