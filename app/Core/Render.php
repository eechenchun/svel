<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Render.php
 * @desc 页面装配：主题模板 → layout → 输出过滤器，控制器只管准备数据
 */

namespace Svel\Core;

use Svel\Models\Category;

final class Render
{
    public static function page(string $template, array $data, array $seo): string
    {
        $site = [
            'title'       => (string)option('site.title', 'SvelCMS'),
            'tagline'     => (string)option('site.tagline', ''),
            'description' => (string)option('site.description', ''),
            'url'         => abs_url(''),
            'icp'         => (string)option('site.icp', ''),
            'footer'      => (string)option('site.footer_text', ''),
            'theme'       => (string)option('site.theme', 'default'),
        ];

        $data['site'] = $site;
        $data['nav'] = Category::withCounts();
        $data['year'] = (int)date('Y');
        $data['seo'] = $seo;
        $data['current'] = (string)($data['current'] ?? '');

        $content = View::render($template, $data);
        $html = View::render('layout', $data + ['content' => $content, 'seoTags' => Seo::tags($seo)]);

        if (class_exists('\Svel\Core\Hook')) {
            $html = (string)Hook::filter('output', $html);
        }
        return $html;
    }

    public static function notFound(): string
    {
        Response::status(404);
        return self::page('404', ['current' => ''], Seo::notFound());
    }
}