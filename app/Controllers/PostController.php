<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/PostController.php
 * @desc 文章页：slug 失效时先查 301 映射，再回 404
 */

namespace Svel\Controllers;

use Svel\Core\Render;
use Svel\Core\Redirect;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Seo;
use Svel\Models\Category;
use Svel\Models\Post;

final class PostController
{
    public function show(Request $req, array $params = []): void
    {
        $slug = (string)($params['slug'] ?? '');
        $post = $slug === '' ? null : Post::findPublishedBySlug($slug, 'post');

        if ($post === null) {
            $target = Redirect::find('post/' . $slug . '.html');
            if ($target !== null) {
                $this->permanent($target);
                return;
            }
            echo Render::notFound();
            return;
        }

        Post::incrementViews((int)$post['id']);

        $category = (int)$post['category_id'] > 0 ? Category::findById((int)$post['category_id']) : null;
        $tags = Post::tags((int)$post['id']);
        $related = Post::related($post, 4);
        $neighbours = Post::neighbours($post);

        echo Render::page('post', [
            'post'       => $post,
            'category'   => $category,
            'tags'       => $tags,
            'related'    => $related,
            'prev'       => $neighbours['prev'],
            'next'       => $neighbours['next'],
            'current'    => post_url($post),
        ], Seo::post($post, $category));
    }

    /** 输出 301，并清掉旧地址缓存，避免旧 HTML 顶掉跳转 */
    public static function permanent(string $relative): void
    {
        $target = $relative;
        $url = str_starts_with($target, 'http') ? $target : abs_url($target);
        Response::header('Cache-Control', 'public, max-age=3600');
        Response::redirect($url, 301);
    }
}