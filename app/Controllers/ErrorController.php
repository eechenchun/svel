<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/ErrorController.php
 * @desc 404 兜底：返回真实 404 状态码，并给出热门文章，降低跳出
 */

namespace Svel\Controllers;

use Svel\Core\Render;
use Svel\Core\Request;
use Svel\Models\Post;

final class ErrorController
{
    public function notFound(Request $req, array $params = []): void
    {
        \Svel\Core\Response::status(404);
        $recent = [];
        try {
            $recent = Post::recent(5);
        } catch (\Throwable) {
            $recent = [];
        }

        echo Render::page('404', [
            'recent'  => $recent,
            'current' => '',
            'path'    => (string)($params['path'] ?? ''),
        ], \Svel\Core\Seo::notFound());
    }
}