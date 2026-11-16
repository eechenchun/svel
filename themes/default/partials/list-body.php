<?php
/**
 * 默认主题 - 列表页通用主体（分类 / 标签 / 搜索）
 * @var string $heading
 * @var array $posts
 * @var array $pager
 * @var string $emptyText
 */
?><div class="wrap">
  <header class="page-head">
    <h1 class="page-title"><?= e($heading) ?></h1>
  </header>
  <section class="post-list">
    <?php if ($posts === []): ?>
      <p class="empty"><?= e($emptyText ?? '这里还没有内容。') ?></p>
    <?php else: ?>
      <?php foreach ($posts as $post): ?>
        <?= \Svel\Core\View::partial('card', ['post' => $post]) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
  <?= \Svel\Core\View::partial('pagination', ['pager' => $pager ?? []]) ?>
</div>