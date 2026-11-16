<?php
/**
 * 默认主题 - 首页
 * @var array $posts
 * @var array $pager
 * @var string $heading
 */
?><div class="wrap">
  <?php if (!empty($heading)): ?>
    <header class="page-head"><h1 class="page-title"><?= e($heading) ?></h1></header>
  <?php endif; ?>
  <section class="post-list">
    <?php if ($posts === []): ?>
      <p class="empty">还没有文章。去后台写下第一篇吧。</p>
    <?php else: ?>
      <?php foreach ($posts as $post): ?>
        <?= \Svel\Core\View::partial('card', ['post' => $post]) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
  <?= \Svel\Core\View::partial('pagination', ['pager' => $pager]) ?>
</div>