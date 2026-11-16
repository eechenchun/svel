<?php
/**
 * SvelCMS 默认主题 - 页面骨架
 * @var array $site
 * @var string $seoTags
 * @var string $content
 */
$themeVersion = '1.0.0';
$themeMeta = @json_decode((string)@file_get_contents(__DIR__ . '/theme.json'), true);
if (is_array($themeMeta) && !empty($themeMeta['version'])) {
    $themeVersion = (string)$themeMeta['version'];
}
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= $seoTags ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($site['title']) ?> RSS" href="<?= e(abs_url('feed')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('themes/' . $site['theme'] . '/assets/style.css')) ?>">
<?php $accent = (string)app_setting('default', 'accent_color', '#1a5fb4'); ?>
<style>:root{--link:<?= e($accent) ?>}</style>
<?= (string)option('seo.head_code', '') ?>
</head>
<body>
<?= \Svel\Core\View::partial('header', ['site' => $site, 'nav' => $nav, 'current' => $current]) ?>
<main class="main" id="main">
<?= $content ?>
</main>
<?= \Svel\Core\View::partial('footer', ['site' => $site, 'year' => $year]) ?>
</body>
</html>