<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/SearchController.php
 * @desc 站内搜索：只做 LIKE，不引入分词依赖；结果页 noindex
 */

namespace Svel\Controllers;

use Svel\Core\Render;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Seo;
use Svel\Core\Throttle;
use Svel\Models\Post;

final class SearchController
{
    public function index(Request $req, array $params = []): void
    {
        if (!Throttle::allow('search:' . $req->ip(), 30, 60)) {
            Response::status(429);
            echo '<!doctype html><meta charset="utf-8"><h1>请求过于频繁</h1><p>请稍后再试。</p>';
            return;
        }
        $q = trim((string)$req->query('q', ''));
        $q = mb_substr($q, 0, 60, 'UTF-8');

        $res = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        if ($q !== '') {
            $res = Post::paginate(['page' => 1, 'per' => 20, 'q' => $q]);
        }

        echo Render::page('search', [
            'q'      => $q,
            'posts'  => $res['items'],
            'total'  => $res['total'],
            'current' => url('search'),
        ], Seo::search($q));
    }
}