<?php
/**
 * SvelCMS 后台 - 两步验证设置
 * @var bool $enabled
 * @var string $secret
 * @var string $uri
 * @var array $recoveryCodes
 * @var array $user
 */
?><div class="card">
  <h2 class="card-title">两步验证（TOTP）</h2>
  <p>状态：
    <span class="badge <?= $enabled ? 'badge-on' : 'badge-off' ?>"><?= $enabled ? '已开启' : '未开启' ?></span>
  </p>
  <p class="hint">开启后，登录除密码外还需输入验证器（Google Authenticator、1Password、Microsoft Authenticator 等）中的 6 位动态码。恢复码只显示一次，请离线保存。</p>

  <?php if ($recoveryCodes !== []): ?>
    <div class="flash flash-ok">两步验证已开启。下面 8 个恢复码只显示这一次，请立即保存：</div>
    <pre class="snippet"><?= e(implode("\n", $recoveryCodes)) ?></pre>
  <?php endif; ?>

  <?php if ($enabled): ?>
    <h3 style="font-size:15px;margin:18px 0 6px">关闭两步验证</h3>
    <form method="post" action="<?= e(admin_url('2fa/setup')) ?>">
      <?= \Svel\Core\Csrf::field() ?>
      <input type="hidden" name="action" value="disable">
      <div class="row">
        <label class="field"><span>当前密码</span><input type="password" name="current_password" autocomplete="current-password" required></label>
        <label class="field"><span>当前动态码</span><input type="text" name="code" inputmode="numeric" required></label>
      </div>
      <button class="btn" type="submit">关闭两步验证</button>
    </form>
  <?php else: ?>
    <h3 style="font-size:15px;margin:18px 0 6px">绑定验证器</h3>
    <p>1. 在验证器里添加账户，手动输入以下密钥（或打开 otpauth 链接）：</p>
    <pre class="snippet"><?= e($secret) ?></pre>
    <p class="hint">otpauth：<code style="word-break:break-all"><?= e($uri) ?></code></p>
    <p>2. 输入验证器当前显示的 6 位数字完成绑定：</p>
    <form method="post" action="<?= e(admin_url('2fa/setup')) ?>">
      <?= \Svel\Core\Csrf::field() ?>
      <input type="hidden" name="action" value="enable">
      <label class="field"><span>6 位验证码</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
      <button class="btn btn-primary" type="submit">开启两步验证</button>
    </form>
  <?php endif; ?>

  <p class="hint" style="margin-top:16px"><a href="<?= e(admin_url('setting?tab=admin')) ?>">返回后台设置</a></p>
</div>