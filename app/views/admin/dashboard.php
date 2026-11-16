<?php
/**
 * SvelCMS 后台 - 仪表盘
 * @var array $counts
 * @var array $recent
 * @var array $env
 * @var string $version
 */
?><div class="stat-grid">
  <div class="stat"><b><?= (int)$counts['published'] ?></b><span>已发布文章</span></div>
  <div class="stat"><b><?= (int)$counts['draft'] ?></b><span>草稿</span></div>
  <div class="stat"><b><?= (int)$counts['category'] ?></b><span>分类</span></div>
  <div class="stat"><b><?= (int)$counts['tag'] ?></b><span>标签</span></div>
</div>

<div class="card">
  <h2 class="card-title">最近发布</h2>
  <?php if ($recent === []): ?>
    <p class="hint">还没有文章，去 <a href="<?= e(admin_url('post/edit')) ?>">写第一篇</a>。</p>
  <?php else: ?>
    <table class="list">
      <thead><tr><th>标题</th><th>发布时间</th><th>阅读</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $post): ?>
        <tr>
          <td><?= e($post['title']) ?></td>
          <td class="num"><?= e(post_date($post)) ?></td>
          <td class="num"><?= (int)$post['views'] ?></td>
          <td><a class="btn btn-sm" href="<?= e(admin_url('post/edit?id=' . (int)$post['id'])) ?>">编辑</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="card-title">运行环境</h2>
  <table class="list">
    <tbody>
    <?php foreach ($env as $k => $v): ?>
      <tr><th style="width:150px"><?= e((string)$k) ?></th><td><?= e((string)$v) ?></td></tr>
    <?php endforeach; ?>
    <tr><th>SvelCMS 版本</th><td><?= e($version) ?></td></tr>
    </tbody>
  </table>
  <p class="hint">出现白屏或 500 时，先看上面「日志文件」指向的文件末尾几行。</p>
</div>