<?php
/**
 * SvelCMS 后台 - 系统升级
 * @var array $check
 * @var bool $auto
 * @var string $channel
 * @var int $lastAt
 * @var string $lastFrom
 * @var string $lastTo
 * @var string $autoResult
 * @var array $history
 */
$autoRow = json_decode($autoResult, true);
$sizeText = $check['size'] > 0
    ? ($check['size'] >= 1048576 ? round($check['size'] / 1048576, 1) . ' MB' : round($check['size'] / 1024) . ' KB')
    : '';
?><div class="card">
  <h2 class="card-title">当前版本</h2>
  <p style="font-size:20px;margin:0 0 8px">
    <strong>v<?= e($check['current']) ?></strong>
    <span class="hint" style="font-size:13px">（<?= $channel === 'beta' ? '测试通道' : '稳定通道' ?>）</span>
  </p>
  <?php if (!$check['ok']): ?>
    <div class="flash flash-error"><?= e($check['msg']) ?></div>
  <?php elseif ($check['has_update']): ?>
    <div class="flash flash-ok">
      发现<?= $check['channel'] === 'beta' ? '测试版' : '稳定版' ?> <strong>v<?= e($check['latest']) ?></strong>
      <?php if ($sizeText !== ''): ?>，安装包 <?= e($sizeText) ?><?php endif; ?>
      <?php if ((int)$check['published_at'] > 0): ?>，发布于 <?= e(date('Y-m-d H:i', (int)$check['published_at'])) ?><?php endif; ?>。
    </div>
    <?php if ($check['notes'] !== ''): ?>
      <div class="panel" style="margin:8px 0 14px">
        <h3 style="margin:0 0 6px;font-size:14px">更新说明</h3>
        <p class="hint" style="white-space:pre-wrap;margin:0"><?= e($check['notes']) ?></p>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="flash flash-ok">已是最新版本，无需升级。</div>
  <?php endif; ?>

  <div class="actions" style="margin-top:12px">
    <form method="post" action="<?= e(admin_url('upgrade/check')) ?>" class="inline">
      <?= \Svel\Core\Csrf::field() ?>
      <button class="btn" type="submit">检查更新</button>
    </form>
    <?php if ($check['ok'] && $check['has_update']): ?>
      <form method="post" action="<?= e(admin_url('upgrade/run')) ?>" class="inline" onsubmit="return confirm('升级会先备份内核，再替换 app/ 与入口文件，随后同步表结构。主题、插件、上传文件与数据库内容均不受影响。确定升级到 v<?= e($check['latest']) ?>？');">
        <?= \Svel\Core\Csrf::field() ?>
        <button class="btn btn-primary" type="submit">立即升级到 v<?= e($check['latest']) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <p class="hint">检查更新会实时访问应用市场；打开本页时优先使用 10 分钟内的检查结果，不会每次阻塞等待。<br>
    升级只覆盖内核 <code>app/</code> 与 <code>index.php</code>、<code>router.php</code>；主题、插件、上传文件、配置与数据库内容都不动。升级前自动备份到数据目录 <code>backups/</code>，失败会还原旧内核。</p>
</div>

<div class="card">
  <h2 class="card-title">自动升级与通道</h2>
  <form method="post" action="<?= e(admin_url('upgrade/toggle')) ?>" class="actions">
    <?= \Svel\Core\Csrf::field() ?>
    <select name="auto" style="width:260px">
      <option value="0"<?= $auto ? '' : ' selected' ?>>关闭自动升级（只提示）</option>
      <option value="1"<?= $auto ? ' selected' : '' ?>>开启自动升级（后台加载时执行）</option>
    </select>
    <select name="channel" style="width:220px">
      <option value="stable"<?= $channel === 'stable' ? ' selected' : '' ?>>稳定通道（只升稳定版）</option>
      <option value="beta"<?= $channel === 'beta' ? ' selected' : '' ?>>测试通道（含测试版）</option>
    </select>
    <button class="btn" type="submit">保存</button>
  </form>
  <p class="hint">开启后，进入后台仪表盘时若距上次检查超过 6 小时，会先向市场取当前通道的最新版本；有新版本就自动升级，并带文件锁避免并发。测试通道适合先在测试站验证新版本，正式站建议用稳定通道。</p>
  <?php if (is_array($autoRow) && !empty($autoRow['time'])): ?>
    <p class="hint">上次自动升级结果：<?= e((string)($autoRow['msg'] ?? '')) ?>（<?= e(date('Y-m-d H:i', (int)$autoRow['time'])) ?>）</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="card-title">升级记录</h2>
  <?php if ($lastAt > 0): ?>
    <p>最近一次成功升级：<?= e(date('Y-m-d H:i', $lastAt)) ?>，从 v<?= e($lastFrom) ?> 升级到 v<?= e($lastTo) ?>。</p>
    <p class="hint">备份在数据目录 <code>backups/cms-<?= e($lastFrom) ?>-*.zip</code>，需要回滚时把其中的 <code>app/</code>、<code>index.php</code>、<code>router.php</code> 还原即可。</p>
  <?php else: ?>
    <p class="hint">还没有成功升级记录。</p>
  <?php endif; ?>

  <?php if ($history !== []): ?>
    <table class="list" style="margin-top:10px">
      <thead><tr><th>时间</th><th>版本</th><th>结果</th><th>说明</th></tr></thead>
      <tbody>
      <?php foreach ($history as $row): ?>
        <?php if (!is_array($row)) { continue; } ?>
        <tr>
          <td class="num"><?= e(date('Y-m-d H:i', (int)($row['time'] ?? 0))) ?></td>
          <td>v<?= e((string)($row['from'] ?? '')) ?> → v<?= e((string)($row['to'] ?? '')) ?></td>
          <td><?= !empty($row['ok']) ? '成功' : '失败' ?><?= empty($row['upgraded']) ? '（未执行）' : '' ?></td>
          <td class="hint"><?= e((string)($row['msg'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>