<?php
/**
 * SvelCMS 后台 - 插件
 * @var array $rows
 * @var array|null $provider
 */
?><div class="card">
  <h2 class="card-title">编辑器接管情况</h2>
  <p class="hint">
    当前编辑区由
    <strong><?= $provider === null ? '内核纯文本框' : e((string)$provider['name']) ?></strong>
    负责。两个编辑器插件互斥，启用一个会自动停用另一个；全部停用时回到纯文本框。
  </p>
</div>

<div class="card">
  <h2 class="card-title">已登记插件</h2>
  <table class="list">
    <thead><tr><th>插件</th><th>版本</th><th>钩子</th><th>来源</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="6" class="hint">plugins 目录里还没有插件。</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <?php $hooks = json_decode((string)$row['hooks'], true); ?>
      <tr>
        <td><strong><?= e($row['name']) ?></strong><br><span class="num"><?= e($row['slug']) ?></span></td>
        <td class="num"><?= e($row['version']) ?></td>
        <td class="num">
          <?php
          $names = is_array($hooks) ? (array_is_list($hooks) ? $hooks : array_keys($hooks)) : [];
          echo e(implode(', ', array_map('strval', $names)));
          ?>
        </td>
        <td class="num"><?= e($row['source']) ?></td>
        <td><span class="badge <?= (int)$row['status'] === 1 ? 'badge-on' : 'badge-off' ?>"><?= (int)$row['status'] === 1 ? '已启用' : '已停用' ?></span></td>
        <td>
          <form method="post" action="<?= e(admin_url('plugin/toggle')) ?>" class="inline">
            <?= \Svel\Core\Csrf::field() ?>
            <input type="hidden" name="slug" value="<?= e($row['slug']) ?>">
            <button class="btn btn-sm" type="submit"><?= (int)$row['status'] === 1 ? '停用' : '启用' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2 class="card-title">安装与扫描</h2>
  <div class="row">
    <form method="post" action="<?= e(admin_url('plugin/upload')) ?>" enctype="multipart/form-data">
      <?= \Svel\Core\Csrf::field() ?>
      <label class="field"><span>上传插件包（.zip，根目录含 plugin.json）</span>
        <input type="file" name="package" accept=".zip" required>
      </label>
      <button class="btn btn-primary" type="submit">上传并安装</button>
    </form>
    <form method="post" action="<?= e(admin_url('plugin/scan')) ?>">
      <?= \Svel\Core\Csrf::field() ?>
      <p class="hint">手工把插件目录放进 <code>plugins/</code> 后，点这里让它出现在列表中。</p>
      <button class="btn" type="submit">扫描插件目录</button>
    </form>
  </div>
</div>