<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/PageController.php
 * @desc 独立页面（如「关于」），slug 变更同样走 301
 */

namespace Svel\Controllers;

use Svel\Core\Redirect;
use Svel\Core\Render;
use Svel\Core\Request;
use Svel\Core\Seo;
use Svel\Models\Post;

final class PageController
{
    public function show(Request $req, array $params = []): void
    {
        $slug = (string)($params['slug'] ?? '');
        $page = $slug === '' ? null : Post::findPublishedBySlug($slug, 'page');

        if ($page === null) {
            $target = Redirect::find($slug . '.html');
            if ($target !== null) {
                PostController::permanent($target);
                return;
            }
            echo Render::notFound();
            return;
        }

        echo Render::page('page', [
            'page'    => $page,
            'current' => post_url($page),
        ], Seo::post($page, null));
    }
}