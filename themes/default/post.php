<?php
/**
 * 默认主题 - 文章页
 * @var array $post
 * @var array|null $category
 * @var array $tags
 * @var array $related
 * @var array|null $prev
 * @var array|null $next
 */
?><div class="wrap">
  <article class="post">
    <header class="post-head">
      <h1 class="post-title"><?= e($post['title']) ?></h1>
      <p class="post-meta">
        <time datetime="<?= e(iso_date((int)$post['published_at'])) ?>"><?= e(post_date($post, 'Y年n月j日')) ?></time>
        <?php if ($category !== null): ?>
          <span class="dot">·</span>
          <a href="<?= e(category_url($category)) ?>"><?= e($category['name']) ?></a>
        <?php endif; ?>
        <?php if ((int)$post['views'] > 0 && (string)app_setting('default', 'show_views', '1') === '1'): ?>
          <span class="dot">·</span><span><?= (int)$post['views'] ?> 次阅读</span>
        <?php endif; ?>
      </p>
    </header>

    <?php if (trim((string)$post['cover']) !== ''): ?>
      <img class="post-cover" src="<?= e(str_starts_with((string)$post['cover'], 'http') ? (string)$post['cover'] : asset((string)$post['cover'])) ?>" alt="<?= e($post['title']) ?>" loading="eager" fetchpriority="high" decoding="async">
    <?php endif; ?>

    <div class="post-content">
      <?= content_html($post) ?>
    </div>

    <?php if ($tags !== []): ?>
      <footer class="post-tags">
        <?php foreach ($tags as $tag): ?>
          <a class="tag" href="<?= e(tag_url($tag)) ?>">#<?= e($tag['name']) ?></a>
        <?php endforeach; ?>
      </footer>
    <?php endif; ?>
  </article>

  <?php if ($prev !== null || $next !== null): ?>
    <nav class="post-nav" aria-label="上下篇">
      <?php if ($prev !== null): ?>
        <a class="post-nav-item" href="<?= e(post_url($prev)) ?>" rel="prev"><span>上一篇</span><?= e($prev['title']) ?></a>
      <?php endif; ?>
      <?php if ($next !== null): ?>
        <a class="post-nav-item" href="<?= e(post_url($next)) ?>" rel="next"><span>下一篇</span><?= e($next['title']) ?></a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <?php if ($related !== []): ?>
    <section class="related">
      <h2 class="related-title">相关文章</h2>
      <ul class="related-list">
        <?php foreach ($related as $item): ?>
          <li><a href="<?= e(post_url($item)) ?>"><?= e($item['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>