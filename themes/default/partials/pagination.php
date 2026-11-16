<?php
/**
 * 默认主题 - 分页
 * @var array $pager
 */
if (empty($pager) || (int)$pager['pages'] <= 1) {
    return;
}
?><nav class="pagination" aria-label="分页">
  <?php if (!empty($pager['prev'])): ?>
    <a class="page-link" href="<?= e($pager['prev']) ?>" rel="prev">上一页</a>
  <?php endif; ?>
  <?php foreach ($pager['items'] as $item): ?>
    <?php if ($item['n'] === '…'): ?>
      <span class="page-gap">…</span>
    <?php elseif ($item['current']): ?>
      <span class="page-current" aria-current="page"><?= (int)$item['n'] ?></span>
    <?php else: ?>
      <a class="page-link" href="<?= e($item['url']) ?>"><?= (int)$item['n'] ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!empty($pager['next'])): ?>
    <a class="page-link" href="<?= e($pager['next']) ?>" rel="next">下一页</a>
  <?php endif; ?>
</nav>