<?php
/**
 * SvelCMS 后台 - 审计日志
 * @var array $rows
 */
?><div class="card">
  <h2 class="card-title">最近 300 条操作</h2>
  <p class="hint">记录后台写操作、登录成功/失败、两步验证与升级动作；按时间倒序，便于事后追溯。IP 来自可信代理配置；未配置可信代理时记录连接地址。</p>
  <form method="post" action="<?= e(admin_url('maintenance')) ?>" class="actions" style="margin-bottom:12px">
    <?= \Svel\Core\Csrf::field() ?>
    <button class="btn" type="submit">清理过期会话、日志与旧备份</button>
  </form>
  <?php if ($rows === []): ?>
    <p class="hint">还没有审计记录。</p>
  <?php else: ?>
    <table class="list">
      <thead><tr><th>时间</th><th>账号</th><th>动作</th><th>对象/路径</th><th>IP</th><th>说明</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="num"><?= e(date('Y-m-d H:i:s', (int)$row['created_at'])) ?></td>
          <td><?= e((string)($row['username'] ?? '')) ?></td>
          <td><code><?= e((string)$row['action']) ?></code></td>
          <td class="hint"><?= e((string)$row['target']) ?></td>
          <td class="num"><?= e((string)$row['ip']) ?></td>
          <td class="hint"><?= e((string)$row['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>