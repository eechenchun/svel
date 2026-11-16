<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/RobotsController.php
 * @desc robots.txt：放行静态资源，屏蔽后台与搜索结果，声明 sitemap
 */

namespace Svel\Controllers;

use Svel\Core\Request;
use Svel\Core\Response;

final class RobotsController
{
    public function index(Request $req, array $params = []): void
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /search',
            'Disallow: /install.php',
            '',
            'Sitemap: ' . abs_url('sitemap.xml'),
            '',
        ];

        $extra = trim((string)option('seo.robots_extra', ''));
        if ($extra !== '') {
            foreach (explode(chr(10), str_replace(chr(13), '', $extra)) as $line) {
                $lines[] = rtrim($line);
            }
            $lines[] = '';
        }

        Response::header('Content-Type', 'text/plain; charset=utf-8');
        Response::header('Cache-Control', 'public, max-age=86400');
        echo implode("\n", $lines);
    }
}