<?php
/**
 * SvelCMS 后台 - 应用市场
 * @var array $items
 * @var array $installed
 * @var array $index
 * @var string $tab
 * @var int $updates
 * @var array $account
 * @var string $marketUrl
 * @var bool $empty
 * @var array $counts
 */
$byKey = [];
foreach ($installed as $row) {
    $byKey[$row['type'] . ':' . $row['slug']] = $row;
}
$tabs = [
    ''          => '全部',
    'plugin'    => '插件 ' . (int)$counts['plugin'],
    'theme'     => '主题 ' . (int)$counts['theme'],
    'installed' => '已安装 ' . (int)$counts['installed'],
];
?><div class="card">
  <div class="actions" style="justify-content:space-between;margin-bottom:12px">
    <div class="actions">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn-sm<?= $tab === (string)$key ? ' btn-primary' : '' ?>"
           href="<?= e(admin_url('market' . ($key === '' ? '' : '?tab=' . $key))) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="actions">
      <form method="post" action="<?= e(admin_url('market/check')) ?>" class="inline">
        <?= \Svel\Core\Csrf::field() ?>
        <button class="btn btn-sm" type="submit">检查更新<?= $updates > 0 ? '（' . $updates . '）' : '' ?></button>
      </form>
      <a class="btn btn-sm" href="<?= e(admin_url('market?refresh=1' . ($tab !== '' ? '&tab=' . $tab : ''))) ?>">刷新市场</a>
    </div>
  </div>
  <p class="hint">市场地址：<code><?= e($marketUrl !== '' ? $marketUrl : '未配置') ?></code> · 安装后需启用才能设置；卸载前必须先停用。</p>
</div>

<div class="card">
  <h2 class="card-title">市场账号</h2>
  <?php if ($account['token'] !== ''): ?>
    <p>已登录：<strong><?= e($account['user']) ?></strong>
      · 已绑定站点：<code><?= e($account['url']) ?></code>
      <?php if ($account['site_id'] > 0): ?><span class="badge badge-on">站点 ID <?= (int)$account['site_id'] ?></span><?php endif; ?>
    </p>
    <form method="post" action="<?= e(admin_url('market/unbind')) ?>" class="inline" onsubmit="return confirm('解除本站与市场账号的绑定？');">
      <?= \Svel\Core\Csrf::field() ?>
      <button class="btn btn-sm" type="submit">解除绑定</button>
    </form>
  <?php else: ?>
    <p class="hint">用市场账号登录后会自动把当前站点绑定到该账号，便于后续按站点统计与更新。</p>
    <form method="post" action="<?= e(admin_url('market/login')) ?>" class="actions">
      <?= \Svel\Core\Csrf::field() ?>
      <input type="text" name="username" placeholder="市场账号" style="width:180px" required>
      <input type="password" name="password" placeholder="密码" style="width:180px" required>
      <button class="btn btn-sm btn-primary" type="submit">登录并绑定</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($tab === 'installed'): ?>
  <div class="card">
    <h2 class="card-title">已安装应用</h2>
    <table class="list">
      <thead><tr><th>应用</th><th>类型</th><th>版本</th><th>状态</th><th>更新</th><th>操作</th></tr></thead>
      <tbody>
      <?php if ($installed === []): ?>
        <tr><td colspan="6" class="hint">还没有安装任何应用。</td></tr>
      <?php endif; ?>
      <?php foreach ($installed as $row): ?>
        <tr>
          <td><strong><?= e($row['name']) ?></strong><br><span class="num"><?= e($row['slug']) ?></span></td>
          <td class="num"><?= $row['type'] === 'theme' ? '主题' : '插件' ?></td>
          <td class="num">v<?= e($row['version']) ?></td>
          <td><span class="badge <?= $row['enabled'] ? 'badge-on' : 'badge-off' ?>"><?= $row['enabled'] ? '已启用' : '已停用' ?></span></td>
          <td>
            <?php if ($row['update'] !== ''): ?>
              <form method="post" action="<?= e(admin_url('market/update')) ?>" class="inline">
                <?= \Svel\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="<?= e($row['type']) ?>">
                <input type="hidden" name="slug" value="<?= e($row['slug']) ?>">
                <button class="btn btn-sm btn-primary" type="submit">更新到 v<?= e($row['update']) ?></button>
              </form>
            <?php elseif ($row['market']): ?>
              <span class="num">最新</span>
            <?php else: ?>
              <span class="num">市场暂无此应用</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="actions">
              <form method="post" action="<?= e(admin_url('market/toggle')) ?>" class="inline">
                <?= \Svel\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="<?= e($row['type']) ?>">
                <input type="hidden" name="slug" value="<?= e($row['slug']) ?>">
                <button class="btn btn-sm" type="submit"><?= $row['enabled'] ? '停用' : '启用' ?></button>
              </form>

              <?php if ($row['enabled']): ?>
                <a class="btn btn-sm" href="<?= e(admin_url('market/settings?type=' . $row['type'] . '&slug=' . $row['slug'])) ?>">设置</a>
              <?php else: ?>
                <span class="btn btn-sm" style="opacity:.45;cursor:not-allowed" title="启用后才能设置">设置</span>
              <?php endif; ?>

              <?php if (!$row['enabled']): ?>
                <form method="post" action="<?= e(admin_url('market/uninstall')) ?>" class="inline" onsubmit="return confirm('卸载会删除该应用的全部文件与设置，确定吗？');">
                  <?= \Svel\Core\Csrf::field() ?>
                  <input type="hidden" name="type" value="<?= e($row['type']) ?>">
                  <input type="hidden" name="slug" value="<?= e($row['slug']) ?>">
                  <button class="btn btn-sm btn-danger" type="submit">卸载</button>
                </form>
              <?php else: ?>
                <span class="btn btn-sm btn-danger" style="opacity:.45;cursor:not-allowed" title="请先停用">卸载</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php elseif ($empty): ?>
  <div class="card">
    <h2 class="card-title">暂时拿不到市场清单</h2>
    <p class="hint">检查 <code>config.php</code> 里的 <code>market_url</code> 是否指向市场站点，以及市场是否可访问。</p>
  </div>
<?php else: ?>
  <div class="market-grid">
    <?php foreach ($items as $item): ?>
      <?php
      $key = (string)$item['type'] . ':' . (string)$item['slug'];
      $local = $byKey[$key] ?? null;
      $enabled = $local !== null && $local['enabled'];
      $installedHere = $local !== null;
      $isTheme = (string)$item['type'] === 'theme';
      ?>
      <div class="market-card">
        <div class="market-media">
          <?php if ($isTheme && (string)($item['thumbnail'] ?? '') !== ''): ?>
            <img src="<?= e((string)$item['thumbnail']) ?>" alt="<?= e((string)$item['name']) ?> 缩略图" loading="lazy">
          <?php elseif ((string)($item['icon'] ?? '') !== ''): ?>
            <img class="market-icon" src="<?= e((string)$item['icon']) ?>" alt="<?= e((string)$item['name']) ?> 图标" loading="lazy">
          <?php else: ?>
            <span class="market-letter"><?= e(mb_substr((string)$item['name'], 0, 1)) ?></span>
          <?php endif; ?>
        </div>
        <div class="market-body">
          <h3 class="market-title"><?= e((string)$item['name']) ?></h3>
          <p class="market-desc"><?= e(mb_substr((string)$item['description'], 0, 64)) ?></p>
          <p class="market-meta">
            <span class="badge <?= $isTheme ? 'badge-off' : '' ?>"><?= $isTheme ? '主题' : '插件' ?></span>
            <span>v<?= e((string)$item['version']) ?></span>
            <span><?= e((string)$item['author']) ?></span>
            <?php if ($installedHere): ?>
              <span class="badge <?= $enabled ? 'badge-on' : 'badge-off' ?>"><?= $enabled ? '已启用' : '已安装' ?></span>
            <?php endif; ?>
          </p>

          <div class="actions market-actions">
            <?php if (!$installedHere): ?>
              <form method="post" action="<?= e(admin_url('market/install')) ?>" class="inline">
                <?= \Svel\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="<?= e((string)$item['type']) ?>">
                <input type="hidden" name="slug" value="<?= e((string)$item['slug']) ?>">
                <button class="btn btn-sm btn-primary" type="submit">安装</button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= e(admin_url('market/toggle')) ?>" class="inline">
                <?= \Svel\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="<?= e((string)$item['type']) ?>">
                <input type="hidden" name="slug" value="<?= e((string)$item['slug']) ?>">
                <button class="btn btn-sm" type="submit"><?= $enabled ? '停用' : '启用' ?></button>
              </form>

              <?php if ($enabled): ?>
                <a class="btn btn-sm" href="<?= e(admin_url('market/settings?type=' . (string)$item['type'] . '&slug=' . (string)$item['slug'])) ?>">设置</a>
              <?php else: ?>
                <span class="btn btn-sm" style="opacity:.45;cursor:not-allowed" title="启用后才能设置">设置</span>
              <?php endif; ?>

              <?php if (!$enabled): ?>
                <form method="post" action="<?= e(admin_url('market/uninstall')) ?>" class="inline" onsubmit="return confirm('卸载会删除该应用的全部文件与设置，确定吗？');">
                  <?= \Svel\Core\Csrf::field() ?>
                  <input type="hidden" name="type" value="<?= e((string)$item['type']) ?>">
                  <input type="hidden" name="slug" value="<?= e((string)$item['slug']) ?>">
                  <button class="btn btn-sm btn-danger" type="submit">卸载</button>
                </form>
              <?php else: ?>
                <span class="btn btn-sm btn-danger" style="opacity:.45;cursor:not-allowed" title="请先停用">卸载</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>