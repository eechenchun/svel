<?php
/**
 * 默认主题 - 404
 * @var array $recent
 */
$recent = $recent ?? [];
?><div class="wrap">
  <div class="notfound">
    <h1 class="page-title">页面不存在</h1>
    <p class="notfound-tip">地址可能已经变更，或者内容已被删除。试试下面的搜索。</p>
    <form class="search-form notfound-search" method="get" action="<?= e(url('search')) ?>" role="search">
      <input type="search" name="q" placeholder="搜索站内内容" aria-label="站内搜索">
      <button type="submit">搜</button>
    </form>
    <?php if ($recent !== []): ?>
      <h2 class="related-title">最近发布</h2>
      <ul class="related-list">
        <?php foreach ($recent as $post): ?>
          <li><a href="<?= e(post_url($post)) ?>"><?= e($post['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p><a href="<?= e(url('')) ?>">回到首页</a></p>
  </div>
</div>