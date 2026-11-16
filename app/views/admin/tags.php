<?php
/**
 * SvelCMS 后台 - 标签
 * @var array $rows
 */
?><div class="card">
  <h2 class="card-title">新增标签</h2>
  <form method="post" action="<?= e(admin_url('tag/save')) ?>" class="actions">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="text" name="name" placeholder="标签名" required style="width:220px">
    <input type="text" name="slug" placeholder="slug（可留空）" style="width:200px">
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
</div>

<div class="card">
  <h2 class="card-title">现有标签</h2>
  <table class="list">
    <thead><tr><th>名称</th><th>slug</th><th>文章数</th><th>操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="4" class="hint">还没有标签。</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td>
          <form method="post" action="<?= e(admin_url('tag/save')) ?>" class="actions">
            <?= \Svel\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="text" name="name" value="<?= e($row['name']) ?>" style="width:180px">
            <input type="text" name="slug" value="<?= e($row['slug']) ?>" style="width:180px">
            <button class="btn btn-sm" type="submit">保存</button>
          </form>
        </td>
        <td class="num"><a href="<?= e(tag_url($row)) ?>" target="_blank" rel="noopener"><?= e($row['slug']) ?></a></td>
        <td class="num"><?= (int)$row['total'] ?></td>
        <td>
          <form method="post" action="<?= e(admin_url('tag/delete')) ?>" onsubmit="return confirm('删除标签会解除它与文章的关联，文章本身不受影响。确定吗？');">
            <?= \Svel\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>