<?php
/**
 * 默认主题 - 页脚
 * @var array $site
 * @var int $year
 */
?><footer class="site-footer">
  <div class="wrap">
    <p class="foot-line">
      © <?= (int)$year ?> <?= e($site['title']) ?>
      <?php $footerExtra = app_setting('default', 'footer_text', '') ?: $site['footer']; ?>
      <?php if ($footerExtra !== ''): ?> · <?= e($footerExtra) ?><?php endif; ?>
      · <a href="<?= e(url('feed')) ?>">RSS</a>
      · <a href="<?= e(url('sitemap.xml')) ?>">站点地图</a>
    </p>
    <?php if ($site['icp'] !== ''): ?>
      <p class="foot-line"><a href="https://beian.miit.gov.cn/" rel="noopener" target="_blank"><?= e($site['icp']) ?></a></p>
    <?php endif; ?>
  </div>
</footer>