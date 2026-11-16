<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/FeedController.php
 * @desc RSS 2.0 输出，带 atom:link self，方便抓取器识别
 */

namespace Svel\Controllers;

use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Seo;
use Svel\Models\Post;

final class FeedController
{
    public function index(Request $req, array $params = []): void
    {
        $posts = Post::recent(20);
        $site = abs_url('');
        $title = Seo::siteTitle();
        $desc = (string)option('site.description', '');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n<channel>\n";
        $xml .= '<title>' . e($title) . "</title>\n";
        $xml .= '<link>' . e($site) . "</link>\n";
        $xml .= '<description>' . e($desc) . "</description>\n";
        $xml .= '<language>zh-CN</language>' . "\n";
        $xml .= '<lastBuildDate>' . date(DATE_RSS) . "</lastBuildDate>\n";
        $xml .= '<atom:link href="' . e(abs_url('feed')) . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ($posts as $post) {
            $link = abs_url(post_url($post));
            $xml .= "<item>\n";
            $xml .= '<title>' . e((string)$post['title']) . "</title>\n";
            $xml .= '<link>' . e($link) . "</link>\n";
            $xml .= '<guid isPermaLink="true">' . e($link) . "</guid>\n";
            $xml .= '<pubDate>' . date(DATE_RSS, (int)$post['published_at']) . "</pubDate>\n";
            $xml .= '<description>' . e(excerpt_text((string)$post['content'], 200)) . "</description>\n";
            $xml .= "</item>\n";
        }
        $xml .= "</channel>\n</rss>\n";

        Response::header('Content-Type', 'application/rss+xml; charset=utf-8');
        Response::header('Cache-Control', 'public, max-age=600');
        echo $xml;
    }
}