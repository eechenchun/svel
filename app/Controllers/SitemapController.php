<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/SitemapController.php
 * @desc 站点地图：文章超过 5000 条自动切成索引 + 分片，避免单文件过大
 */

namespace Svel\Controllers;

use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Models\Category;
use Svel\Models\Post;
use Svel\Models\Tag;

final class SitemapController
{
    private const PER_PART = 5000;

    public function index(Request $req, array $params = []): void
    {
        $total = Post::countPublished('post');
        $this->head();

        if ($total > self::PER_PART) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            $parts = (int)ceil($total / self::PER_PART);
            for ($i = 1; $i <= $parts; $i++) {
                echo '<sitemap><loc>' . e(abs_url('sitemap-' . $i . '.xml')) . '</loc><lastmod>' . date('c') . "</lastmod></sitemap>\n";
            }
            echo '<sitemap><loc>' . e(abs_url('sitemap-pages.xml')) . '</loc><lastmod>' . date('c') . "</lastmod></sitemap>\n";
            echo '<sitemap><loc>' . e(abs_url('sitemap-taxonomy.xml')) . '</loc><lastmod>' . date('c') . "</lastmod></sitemap>\n";
            echo '</sitemapindex>';
            return;
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $this->url(abs_url(''), date('c'), '1.0', 'daily');
        foreach (Post::chunk(self::PER_PART, 0, 'post') as $row) {
            $this->url(abs_url(post_url($row)), $this->lastmod($row), '0.8', 'weekly');
        }
        foreach (Category::withCounts() as $cat) {
            if ((int)$cat['total'] > 0) {
                $this->url(abs_url(category_url($cat)), date('c'), '0.6', 'weekly');
            }
        }
        foreach (Tag::withPublished() as $tag) {
            $this->url(abs_url(tag_url($tag)), date('c'), '0.5', 'weekly');
        }
        foreach (Post::pages() as $page) {
            $this->url(abs_url(post_url($page)), $this->lastmod($page), '0.6', 'monthly');
        }
        echo '</urlset>';
    }

    public function posts(Request $req, array $params = []): void
    {
        $raw = (string)($params['n'] ?? '');
        if (!ctype_digit($raw) || (int)$raw < 1) {
            Response::status(404);
            Response::header('Content-Type', 'text/plain; charset=utf-8');
            echo 'Not Found';
            return;
        }
        $n = (int)$raw;
        $rows = Post::chunk(self::PER_PART, ($n - 1) * self::PER_PART, 'post');

        $this->head();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($rows as $row) {
            $this->url(abs_url(post_url($row)), $this->lastmod($row), '0.8', 'weekly');
        }
        echo '</urlset>';
    }

    public function pages(Request $req, array $params = []): void
    {
        $this->head();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (Post::pages() as $page) {
            $this->url(abs_url(post_url($page)), $this->lastmod($page), '0.6', 'monthly');
        }
        echo '</urlset>';
    }

    public function taxonomy(Request $req, array $params = []): void
    {
        $this->head();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (Category::withCounts() as $cat) {
            if ((int)$cat['total'] > 0) {
                $this->url(abs_url(category_url($cat)), date('c'), '0.6', 'weekly');
            }
        }
        foreach (Tag::withPublished() as $tag) {
            $this->url(abs_url(tag_url($tag)), date('c'), '0.5', 'weekly');
        }
        echo '</urlset>';
    }

    private function head(): void
    {
        Response::header('Content-Type', 'application/xml; charset=utf-8');
        Response::header('Cache-Control', 'public, max-age=1800');
    }

    private function lastmod(array $row): string
    {
        $ts = (int)($row['updated_at'] ?? 0) ?: (int)($row['published_at'] ?? 0);
        return iso_date($ts);
    }

    private function url(string $loc, string $lastmod, string $priority, string $changefreq): void
    {
        echo '<url><loc>' . e($loc) . '</loc><lastmod>' . e($lastmod) . '</lastmod>'
            . '<changefreq>' . $changefreq . '</changefreq><priority>' . $priority . "</priority></url>\n";
    }
}