<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/HomeController.php
 * @desc 首页与首页分页
 */

namespace Svel\Controllers;

use Svel\Core\Pager;
use Svel\Core\Render;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Seo;
use Svel\Models\Post;

final class HomeController
{
    public function index(Request $req, array $params = []): void
    {
        $per = self::perPage();
        $res = Post::paginate(['page' => 1, 'per' => $per]);
        $pager = Pager::build(1, $res['pages'], static fn(int $n): string => page_url($n));

        echo Render::page('home', [
            'posts'   => $res['items'],
            'pager'   => $pager,
            'heading' => '',
            'current' => url(''),
        ], Seo::home());
    }

    public function page(Request $req, array $params = []): void
    {
        $raw = (string)($params['n'] ?? '');
        if (!ctype_digit($raw) || (int)$raw < 1) {
            echo Render::notFound();
            return;
        }
        $n = (int)$raw;
        if ($n === 1) {
            Response::redirect(url(''), 301);
        }

        $per = self::perPage();
        $res = Post::paginate(['page' => $n, 'per' => $per]);
        if ($res['items'] === [] && $n > $res['pages']) {
            echo Render::notFound();
            return;
        }
        $pager = Pager::build($n, $res['pages'], static fn(int $i): string => page_url($i));

        $seo = Seo::home();
        $seo['canonical'] = abs_url('page/' . $n);
        $seo['title'] = '第 ' . $n . ' 页 - ' . Seo::siteTitle();

        echo Render::page('home', [
            'posts'   => $res['items'],
            'pager'   => $pager,
            'heading' => '第 ' . $n . ' 页',
            'current' => url('page/' . $n),
        ], $seo);
    }

    /** 主题可通过应用设置覆盖首页每页条数，0 表示跟随站点设置 */
    private static function perPage(): int
    {
        $themePer = (int)app_setting('default', 'per_page', 0);
        if ($themePer > 0) {
            return max(1, min(50, $themePer));
        }
        return max(1, (int)option('site.posts_per_page', 10));
    }
}
