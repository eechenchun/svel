<?php
/**
 * SvelCMS 后台 - 导航管理
 * @var array $rows
 */
?><div class="card">
  <h2 class="card-title">新增导航</h2>
  <form method="post" action="<?= e(admin_url('nav/save')) ?>" class="actions">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="text" name="title" placeholder="标题" required maxlength="40" style="width:150px">
    <input type="text" name="url" placeholder="/category/news 或 https://…" required style="width:240px">
    <input type="number" name="sort" value="0" title="排序" style="width:80px">
    <select name="target" style="width:110px">
      <option value="_self">当前窗口</option>
      <option value="_blank">新窗口</option>
    </select>
    <select name="status" style="width:90px">
      <option value="1">显示</option>
      <option value="0">隐藏</option>
    </select>
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
  <p class="hint">导航为空时前台回退显示分类列表。链接填 <code>/</code> 为首页，<code>/category/xxx</code> 为分类页，<code>#</code> 开头的锚点原样输出。</p>
</div>

<div class="card">
  <h2 class="card-title">现有导航</h2>
  <?php if ($rows === []): ?>
    <p class="hint">还没有导航项。</p>
  <?php endif; ?>
  <?php foreach ($rows as $row): ?>
    <div class="nav-row">
      <form method="post" action="<?= e(admin_url('nav/save')) ?>" class="actions">
        <?= \Svel\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="number" name="sort" value="<?= (int)$row['sort'] ?>" style="width:70px" title="排序">
        <input type="text" name="title" value="<?= e($row['title']) ?>" style="width:150px">
        <input type="text" name="url" value="<?= e($row['url']) ?>" style="width:240px">
        <select name="target" style="width:110px">
          <option value="_self"<?= $row['target'] === '_self' ? ' selected' : '' ?>>当前窗口</option>
          <option value="_blank"<?= $row['target'] === '_blank' ? ' selected' : '' ?>>新窗口</option>
        </select>
        <select name="status" style="width:90px">
          <option value="1"<?= (int)$row['status'] === 1 ? ' selected' : '' ?>>显示</option>
          <option value="0"<?= (int)$row['status'] === 0 ? ' selected' : '' ?>>隐藏</option>
        </select>
        <button class="btn btn-sm" type="submit">保存</button>
      </form>
      <form method="post" action="<?= e(admin_url('nav/delete')) ?>" class="inline" onsubmit="return confirm('删除这条导航？');">
        <?= \Svel\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <button class="btn btn-sm btn-danger" type="submit">删除</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>