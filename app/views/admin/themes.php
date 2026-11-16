<?php
/**
 * SvelCMS 后台 - 主题
 * @var array $rows
 * @var string $active
 */
?><div class="card">
  <h2 class="card-title">已安装主题</h2>
  <p class="hint">主题放在 <code>themes/</code> 目录，根文件为 <code>theme.json</code>；模板缺失会自动回退到 default。</p>
  <table class="list">
    <thead><tr><th>主题</th><th>目录</th><th>版本</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><strong><?= e($row['name']) ?></strong></td>
        <td class="num">themes/<?= e($row['slug']) ?></td>
        <td class="num"><?= e($row['version']) ?></td>
        <td>
          <span class="badge <?= $row['status'] === 1 ? 'badge-on' : 'badge-off' ?>"><?= $row['status'] === 1 ? '启用中' : '未启用' ?></span>
        </td>
        <td>
          <?php if ($row['status'] === 1): ?>
            <span class="num">当前主题</span>
          <?php else: ?>
            <form method="post" action="<?= e(admin_url('theme/activate')) ?>" class="inline">
              <?= \Svel\Core\Csrf::field() ?>
              <input type="hidden" name="slug" value="<?= e($row['slug']) ?>">
              <button class="btn btn-sm" type="submit">启用</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>