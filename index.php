<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file index.php
 * @desc 唯一入口。未安装时把 HTML 请求转到 install.php，静态资源直接放行
 */

require __DIR__ . '/app/bootstrap.php';

// 安装器的伪静态探测：必须在安装态判断之前响应
$probePath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (is_string($probePath) && str_contains($probePath, '__svel_probe_')) {
    if (!headers_sent()) {
        header('X-Svel-Rewrite: 1');
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'SVEL_PROBE_OK';
    exit;
}

\Svel\Core\App::run();