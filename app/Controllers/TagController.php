<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/TagController.php
 * @desc 标签页与标签分页
 */

namespace Svel\Controllers;

use Svel\Core\Pager;
use Svel\Core\Redirect;
use Svel\Core\Render;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Seo;
use Svel\Models\Post;
use Svel\Models\Tag;

final class TagController
{
    public function show(Request $req, array $params = []): void
    {
        $this->render($params, 1);
    }

    public function page(Request $req, array $params = []): void
    {
        $raw = (string)($params['n'] ?? '');
        if (!ctype_digit($raw) || (int)$raw < 1) {
            echo Render::notFound();
            return;
        }
        if ((int)$raw === 1) {
            Response::redirect(tag_url(['slug' => (string)$params['slug']], 1), 301);
        }
        $this->render($params, (int)$raw);
    }

    private function render(array $params, int $page): void
    {
        $slug = (string)($params['slug'] ?? '');
        $tag = $slug === '' ? null : Tag::findBySlug($slug);

        if ($tag === null) {
            $target = Redirect::find('tag/' . $slug);
            if ($target !== null) {
                PostController::permanent($target);
                return;
            }
            echo Render::notFound();
            return;
        }

        $per = max(1, (int)option('site.posts_per_page', 10));
        $res = Post::paginate(['page' => $page, 'per' => $per, 'tag_id' => (int)$tag['id']]);
        if ($page > 1 && $res['items'] === []) {
            echo Render::notFound();
            return;
        }

        $pager = Pager::build($page, $res['pages'], static fn(int $n): string => tag_url($tag, $n));
        $seo = Seo::tag($tag, $page);
        if ($page > 1) {
            $seo['title'] = $tag['name'] . ' - 第 ' . $page . ' 页 - ' . Seo::siteTitle();
        }

        echo Render::page('tag', [
            'tag'     => $tag,
            'posts'   => $res['items'],
            'pager'   => $pager,
            'heading' => (string)$tag['name'],
            'current' => tag_url($tag, 1),
        ], $seo);
    }
}