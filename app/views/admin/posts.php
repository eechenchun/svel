<?php
/**
 * SvelCMS 后台 - 内容列表
 * @var array $rows
 * @var array $categories
 * @var string $type
 * @var string $status
 * @var string $q
 * @var array $pager
 * @var int $total
 */
$isPage = $type === 'page';
?><div class="card">
  <div class="actions" style="justify-content:space-between;margin-bottom:12px">
    <div class="actions">
      <a class="btn btn-sm<?= $isPage ? '' : ' btn-primary' ?>" href="<?= e(admin_url('post?type=post')) ?>">文章</a>
      <a class="btn btn-sm<?= $isPage ? ' btn-primary' : '' ?>" href="<?= e(admin_url('post?type=page')) ?>">独立页面</a>
      <span class="hint">共 <?= (int)$total ?> 条</span>
    </div>
    <a class="btn btn-primary" href="<?= e(admin_url('post/edit' . ($isPage ? '?type=page' : ''))) ?>">新建<?= $isPage ? '页面' : '文章' ?></a>
  </div>

  <form method="get" action="<?= e(admin_url('post')) ?>" class="actions" style="margin-bottom:14px">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <select name="status" style="width:auto">
      <option value=""<?= $status === '' ? ' selected' : '' ?>>全部状态</option>
      <option value="publish"<?= $status === 'publish' ? ' selected' : '' ?>>已发布</option>
      <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>草稿</option>
    </select>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="按标题搜索" style="width:200px">
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>

  <table class="list">
    <thead>
      <tr><th>标题</th><th>地址</th><?php if (!$isPage): ?><th>分类</th><?php endif; ?><th>状态</th><th>更新时间</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="6" class="hint">还没有内容。</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><strong><?= e($row['title']) ?></strong></td>
        <td class="num"><?= e(post_url($row)) ?></td>
        <?php if (!$isPage): ?>
          <td class="num"><?= e($categories[(int)$row['category_id']] ?? '未分类') ?></td>
        <?php endif; ?>
        <td>
          <span class="badge <?= $row['status'] === 'publish' ? 'badge-on' : 'badge-off' ?>">
            <?= $row['status'] === 'publish' ? '已发布' : '草稿' ?>
          </span>
        </td>
        <td class="num"><?= e(fmt_date((int)$row['updated_at'], 'Y-m-d H:i')) ?></td>
        <td>
          <div class="actions">
            <a class="btn btn-sm" href="<?= e(admin_url('post/edit?id=' . (int)$row['id'])) ?>">编辑</a>
            <form method="post" action="<?= e(admin_url('post/toggle')) ?>" class="inline">
              <?= \Svel\Core\Csrf::field() ?>
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-sm" type="submit"><?= $row['status'] === 'publish' ? '转草稿' : '发布' ?></button>
            </form>
            <form method="post" action="<?= e(admin_url('post/delete')) ?>" class="inline" onsubmit="return confirm('删除后不可恢复，确定删除吗？');">
              <?= \Svel\Core\Csrf::field() ?>
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">删除</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ((int)($pager['pages'] ?? 1) > 1): ?>
    <nav class="actions" style="margin-top:14px">
      <?php if (!empty($pager['prev'])): ?><a class="btn btn-sm" href="<?= e($pager['prev']) ?>">上一页</a><?php endif; ?>
      <span class="hint">第 <?= (int)$pager['page'] ?> / <?= (int)$pager['pages'] ?> 页</span>
      <?php if (!empty($pager['next'])): ?><a class="btn btn-sm" href="<?= e($pager['next']) ?>">下一页</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</div>