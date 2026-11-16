<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/CategoryController.php
 * @desc 分类页与分类分页
 */

namespace Svel\Controllers;

use Svel\Core\Pager;
use Svel\Core\Redirect;
use Svel\Core\Render;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Seo;
use Svel\Models\Category;
use Svel\Models\Post;

final class CategoryController
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
            Response::redirect(category_url(['slug' => (string)$params['slug']], 1), 301);
        }
        $this->render($params, (int)$raw);
    }

    private function render(array $params, int $page): void
    {
        $slug = (string)($params['slug'] ?? '');
        $category = $slug === '' ? null : Category::findBySlug($slug);

        if ($category === null) {
            $target = Redirect::find('category/' . $slug);
            if ($target !== null) {
                PostController::permanent($target);
                return;
            }
            echo Render::notFound();
            return;
        }

        $per = max(1, (int)option('site.posts_per_page', 10));
        $res = Post::paginate(['page' => $page, 'per' => $per, 'category_id' => (int)$category['id']]);

        if ($page > 1 && $res['items'] === []) {
            echo Render::notFound();
            return;
        }

        $pager = Pager::build($page, $res['pages'], static fn(int $n): string => category_url($category, $n));
        $seo = Seo::category($category, $page);
        if ($page > 1) {
            $seo['title'] = $category['name'] . ' - 第 ' . $page . ' 页 - ' . Seo::siteTitle();
        }

        echo Render::page('category', [
            'category' => $category,
            'posts'    => $res['items'],
            'pager'    => $pager,
            'heading'  => (string)$category['name'],
            'current'  => category_url($category, 1),
        ], $seo);
    }
}