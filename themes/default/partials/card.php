<?php
/**
 * 默认主题 - 文章卡片（列表页复用）
 * @var array $post
 */
?><article class="card">
  <h2 class="card-title"><a href="<?= e(post_url($post)) ?>"><?= e($post['title']) ?></a></h2>
  <p class="card-meta">
    <time datetime="<?= e(iso_date((int)$post['published_at'])) ?>"><?= e(post_date($post)) ?></time>
    <?php if ((int)($post['views'] ?? 0) > 0 && (string)app_setting('default', 'show_views', '1') === '1'): ?>
      <span class="dot">·</span><span><?= (int)$post['views'] ?> 次阅读</span>
    <?php endif; ?>
  </p>
  <p class="card-excerpt"><?= e(post_excerpt($post, max(20, (int)app_setting('default', 'excerpt_length', 140)))) ?></p>
</article>