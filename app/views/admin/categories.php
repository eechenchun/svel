<?php
/**
 * SvelCMS 后台 - 分类
 * @var array $rows
 */
?><div class="card">
  <h2 class="card-title">新增分类</h2>
  <form method="post" action="<?= e(admin_url('category/save')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <div class="row">
      <label class="field"><span>名称</span><input type="text" name="name" required></label>
      <label class="field"><span>slug（留空自动生成）</span><input type="text" name="slug"></label>
      <label class="field"><span>排序（小的在前）</span><input type="number" name="sort" value="0"></label>
    </div>
    <label class="field"><span>描述（用于分类页 description）</span><input type="text" name="description"></label>
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
</div>

<div class="card">
  <h2 class="card-title">现有分类</h2>
  <table class="list">
    <thead><tr><th>名称</th><th>slug</th><th>文章数</th><th>排序</th><th>操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="5" class="hint">还没有分类。</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td>
          <form method="post" action="<?= e(admin_url('category/save')) ?>" class="actions">
            <?= \Svel\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="text" name="name" value="<?= e($row['name']) ?>" style="width:160px">
            <input type="text" name="slug" value="<?= e($row['slug']) ?>" style="width:160px">
            <input type="number" name="sort" value="<?= (int)$row['sort'] ?>" style="width:80px">
            <input type="text" name="description" value="<?= e($row['description']) ?>" placeholder="描述" style="width:220px">
            <button class="btn btn-sm" type="submit">保存</button>
          </form>
        </td>
        <td class="num"><a href="<?= e(category_url($row)) ?>" target="_blank" rel="noopener"><?= e($row['slug']) ?></a></td>
        <td class="num"><?= (int)$row['total'] ?></td>
        <td class="num"><?= (int)$row['sort'] ?></td>
        <td>
          <form method="post" action="<?= e(admin_url('category/delete')) ?>" onsubmit="return confirm('删除分类后，其文章会归入未分类，确定吗？');">
            <?= \Svel\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint">改 slug 后旧地址会自动 301 到新地址。</p>
</div>