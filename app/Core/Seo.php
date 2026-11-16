<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Seo.php
 * @desc 标题、描述、canonical、robots、OG、JSON-LD 统一组装，模板只负责输出
 */

namespace Svel\Core;

final class Seo
{
    public static function siteTitle(): string
    {
        return (string)option('site.title', 'SvelCMS');
    }

    public static function siteTagline(): string
    {
        return trim((string)option('site.tagline', ''));
    }

    /** 首页标题：站点名 - 副标题；副标题为空时只显示站点名 */
    public static function home(): array
    {
        $title = self::siteTitle();
        $tagline = self::siteTagline();
        $headline = $tagline === '' ? $title : $title . ' - ' . $tagline;

        $site = abs_url('');
        return [
            'title'       => $headline,
            'description' => (string)option('site.description', $tagline),
            'keywords'    => (string)option('site.keywords', ''),
            'canonical'   => $site,
            'robots'      => 'index,follow',
            'type'        => 'website',
            'image'       => self::imageUrl(''),
            'jsonld'      => array_filter([
                [
                    '@context' => 'https://schema.org',
                    '@type'    => 'WebSite',
                    'name'     => $title,
                    'url'      => $site,
                    'description' => (string)option('site.description', ''),
                    'inLanguage'  => 'zh-CN',
                    'potentialAction' => [
                        '@type'       => 'SearchAction',
                        'target'      => $site . 'search?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type'    => 'Organization',
                    'name'     => $title,
                    'url'      => $site,
                    'logo'     => self::imageUrl(''),
                ],
            ]),
        ];
    }

    public static function post(array $post, ?array $category = null): array
    {
        $siteTitle = self::siteTitle();
        $title = trim((string)($post['seo_title'] ?? ''));
        if ($title === '') {
            $title = trim((string)$post['title']) . ' - ' . $siteTitle;
        }

        $desc = trim((string)($post['seo_desc'] ?? ''));
        if ($desc === '') {
            $desc = excerpt_text((string)$post['content'], 120);
        }

        $url = abs_url(post_url($post));
        $image = self::imageUrl((string)($post['cover'] ?? ''));

        $crumb = [['name' => '首页', 'url' => abs_url('')]];
        if ($category !== null) {
            $crumb[] = ['name' => (string)$category['name'], 'url' => abs_url(category_url($category))];
        }
        $crumb[] = ['name' => (string)$post['title'], 'url' => $url];

        return [
            'title'       => $title,
            'description' => $desc,
            'keywords'    => (string)($post['seo_keywords'] ?? ''),
            'canonical'   => $url,
            'robots'      => 'index,follow',
            'type'        => 'article',
            'image'       => $image,
            'published'   => iso_date((int)$post['published_at']),
            'modified'    => iso_date((int)($post['updated_at'] ?: $post['published_at'])),
            'breadcrumb'  => $crumb,
            'jsonld'      => [
                [
                    '@context'      => 'https://schema.org',
                    '@type'         => 'Article',
                    'headline'      => (string)$post['title'],
                    'description'   => $desc,
                    'datePublished' => iso_date((int)$post['published_at']),
                    'dateModified'  => iso_date((int)($post['updated_at'] ?: $post['published_at'])),
                    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
                    'image'         => [$image],
                    'inLanguage'    => 'zh-CN',
                    'author'        => ['@type' => 'Person', 'name' => (string)option('site.title', '站长')],
                    'publisher'     => [
                        '@type' => 'Organization',
                        'name'  => $siteTitle,
                        'logo'  => ['@type' => 'ImageObject', 'url' => self::imageUrl('')],
                    ],
                ],
                self::breadcrumbLd($crumb),
            ],
        ];
    }

    public static function category(array $category, int $page = 1): array
    {
        $name = (string)$category['name'];
        $desc = trim((string)($category['description'] ?? ''));
        if ($desc === '') {
            $desc = '「' . $name . '」分类下的全部文章';
        }
        $path = ltrim(category_url(['slug' => $category['slug']], $page), '/');
        $url = abs_url($path);
        $crumb = [
            ['name' => '首页', 'url' => abs_url('')],
            ['name' => $name, 'url' => abs_url(category_url(['slug' => $category['slug']], 1))],
        ];

        return [
            'title'       => $name . ' - ' . self::siteTitle(),
            'description' => $desc,
            'canonical'   => $url,
            'robots'      => 'index,follow',
            'type'        => 'website',
            'image'       => self::imageUrl(''),
            'breadcrumb'  => $crumb,
            'jsonld'      => [self::breadcrumbLd($crumb)],
        ];
    }

    public static function tag(array $tag, int $page = 1): array
    {
        $name = (string)$tag['name'];
        $path = ltrim(tag_url(['slug' => $tag['slug']], $page), '/');
        $crumb = [
            ['name' => '首页', 'url' => abs_url('')],
            ['name' => $name, 'url' => abs_url(tag_url(['slug' => $tag['slug']], 1))],
        ];

        return [
            'title'       => $name . ' - ' . self::siteTitle(),
            'description' => '标签「' . $name . '」下的全部文章',
            'canonical'   => abs_url($path),
            'robots'      => 'index,follow',
            'type'        => 'website',
            'image'       => self::imageUrl(''),
            'breadcrumb'  => $crumb,
            'jsonld'      => [self::breadcrumbLd($crumb)],
        ];
    }

    public static function search(string $q): array
    {
        return [
            'title'       => '搜索：' . $q . ' - ' . self::siteTitle(),
            'description' => '站内搜索「' . $q . '」的结果',
            'canonical'   => abs_url('search', ['q' => $q]),
            'robots'      => 'noindex,follow',
            'type'        => 'website',
            'image'       => self::imageUrl(''),
            'jsonld'      => [],
        ];
    }

    public static function notFound(): array
    {
        return [
            'title'       => '页面不存在 - ' . self::siteTitle(),
            'description' => '',
            'canonical'   => '',
            'robots'      => 'noindex,follow',
            'type'        => 'website',
            'image'       => self::imageUrl(''),
            'jsonld'      => [],
        ];
    }

    public static function imageUrl(string $cover): string
    {
        $cover = trim($cover);
        if ($cover !== '') {
            return str_starts_with($cover, 'http') ? $cover : abs_url($cover);
        }
        $default = trim((string)option('seo.default_image', ''));
        return $default !== '' ? (str_starts_with($default, 'http') ? $default : abs_url($default)) : '';
    }

    private static function breadcrumbLd(array $crumb): array
    {
        $items = [];
        foreach ($crumb as $i => $c) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => (string)$c['name'],
                'item'     => (string)$c['url'],
            ];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** 输出 head 内的 SEO 标签 */
    public static function tags(array $seo): string
    {
        $out = '<title>' . e((string)$seo['title']) . "</title>\n";
        if ((string)($seo['description'] ?? '') !== '') {
            $out .= '<meta name="description" content="' . e((string)$seo['description']) . "\">\n";
        }
        if ((string)($seo['keywords'] ?? '') !== '') {
            $out .= '<meta name="keywords" content="' . e((string)$seo['keywords']) . "\">\n";
        }
        if ((string)($seo['canonical'] ?? '') !== '') {
            $out .= '<link rel="canonical" href="' . e((string)$seo['canonical']) . "\">\n";
        }
        $out .= '<meta name="robots" content="' . e((string)($seo['robots'] ?? 'index,follow')) . "\">\n";

        $out .= '<meta property="og:site_name" content="' . e(self::siteTitle()) . "\">\n";
        $out .= '<meta property="og:type" content="' . e((string)($seo['type'] ?? 'website')) . "\">\n";
        $out .= '<meta property="og:title" content="' . e((string)$seo['title']) . "\">\n";
        if ((string)($seo['description'] ?? '') !== '') {
            $out .= '<meta property="og:description" content="' . e((string)$seo['description']) . "\">\n";
        }
        if ((string)($seo['canonical'] ?? '') !== '') {
            $out .= '<meta property="og:url" content="' . e((string)$seo['canonical']) . "\">\n";
        }
        if ((string)($seo['image'] ?? '') !== '') {
            $out .= '<meta property="og:image" content="' . e((string)$seo['image']) . "\">\n";
        }
        if (isset($seo['published'])) {
            $out .= '<meta property="article:published_time" content="' . e((string)$seo['published']) . "\">\n";
            $out .= '<meta property="article:modified_time" content="' . e((string)($seo['modified'] ?? $seo['published'])) . "\">\n";
        }
        $out .= '<meta name="twitter:card" content="' . ((string)($seo['image'] ?? '') !== '' ? 'summary_large_image' : 'summary') . "\">\n";

        foreach ((array)($seo['jsonld'] ?? []) as $block) {
            if (!$block) {
                continue;
            }
            $json = json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            if ($json !== false) {
                $out .= '<script type="application/ld+json">' . $json . "</script>\n";
            }
        }

        return $out;
    }
}