<?php
/**
 * SvelCMS 后台 - 设置（选项卡）
 * @var string $tab
 * @var array $opt
 * @var array $user
 * @var array $timezones
 * @var array $rewrite
 */
$tabs = [
    'basic'  => '基本设置',
    'global' => '全局设置',
    'seo'    => 'SEO 设置',
    'upload' => '附件设置',
    'rewrite' => '伪静态',
    'admin'  => '后台设置',
];
$descs = [
    1 => 'L1 干净 URL：/post/hello.html。需要重写规则生效，SEO 最理想。',
    2 => 'L2 PATH_INFO：/index.php/post/hello.html。重写不可用时使用，仍可被收录。',
    3 => 'L3 查询串：/index.php?p=post/hello.html。最后兜底，能用但不美观。',
];
$iniMax = (string)ini_get('upload_max_filesize');
?><nav class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="<?= e(admin_url('setting?tab=' . $key)) ?>"<?= $tab === $key ? ' class="active"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'basic'): ?>
<div class="card">
  <h2 class="card-title">基本设置</h2>
  <form method="post" action="<?= e(admin_url('setting')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="section" value="site">
    <input type="hidden" name="tab" value="basic">
    <div class="row">
      <label class="field"><span>站点名称</span><input type="text" name="title" value="<?= e($opt['title']) ?>" required></label>
      <label class="field"><span>副标题（首页 title 为「站点名 - 副标题」，留空只显示站点名）</span>
        <input type="text" name="tagline" value="<?= e($opt['tagline']) ?>">
      </label>
    </div>
    <label class="field"><span>站点描述（SEO description）</span><input type="text" name="description" value="<?= e($opt['description']) ?>"></label>
    <label class="field"><span>关键词（逗号分隔）</span><input type="text" name="keywords" value="<?= e($opt['keywords']) ?>"></label>
    <div class="row">
      <label class="field"><span>备案号（展示在页脚）</span><input type="text" name="icp" value="<?= e($opt['icp']) ?>" placeholder="黔ICP备XXXXXXXX号-2"></label>
      <label class="field"><span>页脚文字</span><input type="text" name="footer_text" value="<?= e($opt['footer_text']) ?>"></label>
    </div>
    <button class="btn btn-primary" type="submit">保存基本设置</button>
  </form>
</div>
<?php endif; ?>

<?php if ($tab === 'global'): ?>
<div class="card">
  <h2 class="card-title">全局设置</h2>
  <form method="post" action="<?= e(admin_url('setting')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="section" value="global">
    <input type="hidden" name="tab" value="global">
    <div class="row">
      <label class="field"><span>每页文章数（1-50）</span><input type="number" name="per_page" value="<?= e($opt['per_page']) ?>" min="1" max="50"></label>
      <label class="field"><span>页面缓存秒数（0 表示关闭缓存，最长 86400）</span><input type="number" name="cache_ttl" value="<?= e($opt['cache_ttl']) ?>" min="0" max="86400"></label>
    </div>
    <label class="field"><span>站点时区（影响文章发布时间与日期显示）</span>
      <select name="timezone">
        <?php foreach ($timezones as $tz => $label): ?>
          <option value="<?= e($tz) ?>"<?= $opt['timezone'] === $tz ? ' selected' : '' ?>><?= e($label) ?>（<?= e($tz) ?>）</option>
        <?php endforeach; ?>
        <?php if (!isset($timezones[$opt['timezone']])): ?>
          <option value="<?= e($opt['timezone']) ?>" selected>当前：<?= e($opt['timezone']) ?></option>
        <?php endif; ?>
      </select>
    </label>
    <p class="hint">保存后清空页面缓存，前台下次请求按新配置渲染。</p>
    <button class="btn btn-primary" type="submit">保存全局设置</button>
  </form>
</div>
<?php endif; ?>

<?php if ($tab === 'seo'): ?>
<div class="card">
  <h2 class="card-title">SEO 设置</h2>
  <form method="post" action="<?= e(admin_url('setting')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="section" value="seo">
    <input type="hidden" name="tab" value="seo">
    <label class="field"><span>默认分享图 URL（文章没封面时的 og:image）</span><input type="text" name="default_image" value="<?= e($opt['default_image']) ?>"></label>
    <label class="field"><span>robots.txt 追加规则（每行一条，追加在内置规则之后）</span>
      <textarea name="robots_extra" rows="6" placeholder="Disallow: /private/&#10;Allow: /private/public/"><?= e($opt['robots_extra']) ?></textarea>
    </label>
    <label class="field"><span>head 附加代码（插入 &lt;/head&gt; 前，可放统计或站点验证，仅管理员可改）</span>
      <textarea name="head_code" rows="5" placeholder="&lt;meta name=&quot;verify&quot; content=&quot;...&quot;&gt;"><?= e($opt['head_code']) ?></textarea>
    </label>
    <p class="hint">robots 追加规则会实时写入 <code>/robots.txt</code>；head 代码在主题输出前原样插入，请只粘贴可信内容。</p>
    <button class="btn btn-primary" type="submit">保存 SEO 设置</button>
  </form>
</div>
<?php endif; ?>

<?php if ($tab === 'upload'): ?>
<div class="card">
  <h2 class="card-title">附件设置</h2>
  <form method="post" action="<?= e(admin_url('setting')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="section" value="upload">
    <input type="hidden" name="tab" value="upload">
    <div class="row">
      <label class="field"><span>单张图片上限（MB，1-20）</span><input type="number" name="upload_max_mb" value="<?= e($opt['upload_max_mb']) ?>" min="1" max="20"></label>
      <label class="field"><span>允许的图片格式（逗号分隔）</span><input type="text" name="upload_exts" value="<?= e($opt['upload_exts']) ?>" placeholder="jpg,jpeg,png,gif,webp,ico"></label>
    </div>
    <p class="hint">可选格式：jpg / jpeg / png / gif / webp / ico，至少保留一种。服务器 PHP 上限当前为 <code><?= e($iniMax === '' ? '未知' : $iniMax) ?></code>，超过仍会被 PHP 拦截。图片会校验真实类型，改扩展名无效。</p>
    <p class="hint">上传目录：<code>/storage/uploads/年/月/</code>，文件名随机生成，不覆盖旧文件。</p>
    <button class="btn btn-primary" type="submit">保存附件设置</button>
  </form>
</div>
<?php endif; ?>

<?php if ($tab === 'rewrite'): ?>
<div class="card">
  <h2 class="card-title">当前伪静态档位</h2>
  <p><span class="badge badge-on">L<?= (int)$rewrite['mode'] ?></span> <?= e($descs[(int)$rewrite['mode']] ?? '') ?></p>
  <p class="hint">站点子目录：<code><?= e($rewrite['base'] === '' ? '/' : $rewrite['base']) ?></code>。修改域名或子目录后请重新检测。</p>
  <div class="actions">
    <form method="post" action="<?= e(admin_url('setting/rewrite')) ?>" class="inline">
      <?= \Svel\Core\Csrf::field() ?>
      <input type="hidden" name="mode" value="auto">
      <button class="btn btn-primary" type="submit">重新检测</button>
    </form>
    <form method="post" action="<?= e(admin_url('setting/rewrite')) ?>" class="actions">
      <?= \Svel\Core\Csrf::field() ?>
      <select name="mode" style="width:auto">
        <option value="1">手动切到 L1</option>
        <option value="2">手动切到 L2</option>
        <option value="3">手动切到 L3</option>
      </select>
      <button class="btn" type="submit">切换并自检</button>
    </form>
  </div>
</div>

<div class="card">
  <h2 class="card-title">Apache（站点根 .htaccess）</h2>
  <pre class="snippet"><?= e($rewrite['apache'] ?: '暂无，重新检测后会生成') ?></pre>
</div>

<div class="card">
  <h2 class="card-title">Nginx（放入 server{}，在 location ~ \.php$ 之前）</h2>
  <pre class="snippet"><?= e($rewrite['nginx'] ?: '暂无，重新检测后会生成') ?></pre>
</div>

<div class="card">
  <h2 class="card-title">Caddy / IIS</h2>
  <p class="hint">Caddyfile：</p>
  <pre class="snippet"><?= e($rewrite['caddy'] ?: '暂无') ?></pre>
  <p class="hint">IIS web.config：</p>
  <pre class="snippet"><?= e($rewrite['iis'] ?: '暂无') ?></pre>
</div>
<?php endif; ?>

<?php if ($tab === 'admin'): ?>
<div class="card">
  <h2 class="card-title">后台设置</h2>
  <form method="post" action="<?= e(admin_url('setting')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="section" value="admin">
    <input type="hidden" name="tab" value="admin">
    <label class="field"><span>后台列表每页条数（5-100，文章与用户等列表通用）</span>
      <input type="number" name="admin_per_page" value="<?= e($opt['admin_per_page']) ?>" min="5" max="100">
    </label>
    <p class="hint">程序版本 v<?= e(SVEL_VERSION) ?>，升级与自动升级开关在 <a href="<?= e(admin_url('upgrade')) ?>">系统升级</a> 页；登录保护可设置 <a href="<?= e(admin_url('2fa/setup')) ?>">两步验证</a>。</p>
    <button class="btn btn-primary" type="submit">保存后台设置</button>
  </form>
</div>

<div class="card">
  <h2 class="card-title">管理员账号</h2>
  <form method="post" action="<?= e(admin_url('setting')) ?>">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="section" value="account">
    <input type="hidden" name="tab" value="admin">
    <div class="row">
      <label class="field"><span>登录名（不可改）</span><input type="text" value="<?= e($user['username']) ?>" disabled></label>
      <label class="field"><span>昵称</span><input type="text" name="nickname" value="<?= e($user['nickname']) ?>"></label>
      <label class="field"><span>邮箱</span><input type="email" name="email" value="<?= e($user['email']) ?>"></label>
    </div>
    <div class="row">
      <label class="field"><span>当前密码（改密码时必须填写）</span><input type="password" name="current_password" autocomplete="current-password"></label>
    </div>
    <div class="row">
      <label class="field"><span>新密码（不改留空，至少 10 位且含字母和数字）</span><input type="password" name="password" autocomplete="new-password"></label>
      <label class="field"><span>再输一次</span><input type="password" name="password2" autocomplete="new-password"></label>
    </div>
    <button class="btn" type="submit">更新账号</button>
  </form>
</div>
<?php endif; ?>