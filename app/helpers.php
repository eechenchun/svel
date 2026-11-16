<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/helpers.php
 * @desc 全局函数：转义、URL 生成、配置读取、时间格式化。保持短小，重逻辑一律进类
 */

use Svel\Core\Config;
use Svel\Core\Db;

if (!function_exists('e')) {
    /** 所有输出到 HTML 的变量必须过这里 */
    function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('url')) {
    /**
     * 生成站内相对地址。逻辑路径形如 post/hello.html、category/news、page/2，空串为首页。
     */
    function url(string $path = '', array $params = []): string
    {
        $base = rtrim((string)config('site.base', '/'), '/');
        $mode = (int)config('url_mode', 1);
        $p = ltrim($path, '/');

        // 首页永远是站点根，不因档位变成 /index.php
        if ($p === '') {
            $u = $base . '/';
            if ($params !== []) {
                $u .= '?' . http_build_query($params);
            }
            return $u;
        }

        if ($mode === 2) {
            $u = $base . ($p === '' ? '/index.php' : '/index.php/' . $p);
        } elseif ($mode === 3) {
            $u = $base . '/index.php' . ($p === '' ? '' : '?p=' . $p);
        } else {
            $u = $base . '/' . $p;
        }
        if ($params !== []) {
            $q = http_build_query($params);
            $u .= (str_contains($u, '?') ? '&' : '?') . $q;
        }
        return $u === '' ? '/' : $u;
    }
}

if (!function_exists('abs_url')) {
    /** 绝对地址，canonical / og:url / sitemap 用 */
    function abs_url(string $path = '', array $params = []): string
    {
        $site = rtrim((string)config('site.url', ''), '/');
        if ($site === '') {
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $site = $scheme . '://' . $host;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        // 已经是站内根相对地址（/post/x.html、/index.php/...）就直接拼，避免二次加档位前缀
        $rel = str_starts_with($path, '/') ? $path : url($path, $params);
        return $site . $rel;
    }
}

if (!function_exists('asset')) {
    function asset(string $rel): string
    {
        $base = rtrim((string)config('site.base', '/'), '/');
        return $base . '/' . ltrim($rel, '/');
    }
}

if (!function_exists('option')) {
    /** 站点配置项，同一请求只查一次库 */
    function option(string $name, mixed $default = null): mixed
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $db = Db::inst();
                $rows = $db->all('SELECT ' . $db->qi('name') . ',' . $db->qi('value') . ' FROM ' . $db->qi($db->table('option')));
                foreach ($rows as $row) {
                    $cache[(string)$row['name']] = $row['value'];
                }
            } catch (Throwable) {
                // 安装前或库表缺失时退回默认值
            }
        }
        return $cache[$name] ?? $default;
    }
}

if (!function_exists('now')) {
    function now(): int
    {
        return time();
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(int $timestamp, string $format = 'Y-m-d'): string
    {
        return $timestamp > 0 ? date($format, $timestamp) : '';
    }
}

if (!function_exists('iso_date')) {
    function iso_date(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return date('c');
        }
        return date('c', $timestamp);
    }
}

if (!function_exists('excerpt_text')) {
    /** 从 Markdown 或 HTML 正文里取纯文本摘要 */
    function excerpt_text(string $content, int $length = 120): string
    {
        $text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $content) ?? $content;
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[`*_>#~\-]{1,}/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text, 'UTF-8') > $length) {
            $text = mb_substr($text, 0, $length, 'UTF-8') . '…';
        }
        return $text;
    }
}

if (!function_exists('post_url')) {
    function post_url(array $post): string
    {
        $type = (string)($post['type'] ?? 'post');
        $slug = (string)($post['slug'] ?? '');
        return $type === 'page' ? url($slug . '.html') : url('post/' . $slug . '.html');
    }
}

if (!function_exists('category_url')) {
    function category_url(array $category, int $page = 1): string
    {
        $path = 'category/' . (string)($category['slug'] ?? '');
        return $page > 1 ? url($path . '/page/' . $page) : url($path);
    }
}

if (!function_exists('tag_url')) {
    function tag_url(array $tag, int $page = 1): string
    {
        $path = 'tag/' . (string)($tag['slug'] ?? '');
        return $page > 1 ? url($path . '/page/' . $page) : url($path);
    }
}

if (!function_exists('page_url')) {
    function page_url(int $page): string
    {
        return $page <= 1 ? url('') : url('page/' . $page);
    }
}
if (!function_exists('content_html')) {
    /** 按 content_format 渲染正文：markdown 走内置解析器，html 走白名单过滤，最后统一过滤钩子 */
    function content_html(array $post): string
    {
        $raw = (string)($post['content'] ?? '');
        $format = (string)($post['content_format'] ?? 'markdown');
        $html = $format === 'html'
            ? \Svel\Core\Html::clean($raw)
            : \Svel\Core\Html::clean(\Svel\Core\Markdown::toHtml($raw));

        if (class_exists('\Svel\Core\Hook')) {
            $html = (string)\Svel\Core\Hook::filter('the_content', $html, $post);
        }
        return $html;
    }
}

if (!function_exists('post_excerpt')) {
    function post_excerpt(array $post, int $length = 120): string
    {
        $manual = trim((string)($post['excerpt'] ?? ''));
        if ($manual !== '') {
            return $manual;
        }
        return excerpt_text((string)($post['content'] ?? ''), $length);
    }
}

if (!function_exists('post_date')) {
    function post_date(array $post, string $format = 'Y-m-d'): string
    {
        return fmt_date((int)($post['published_at'] ?? 0), $format);
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return url($path === '' ? 'admin' : 'admin/' . $path);
    }
}

if (!function_exists('app_setting')) {
    /** 应用（插件/主题）设置项，键为 app.{slug}.{key}，只由后台应用设置页写入 */
    function app_setting(string $slug, string $key, mixed $default = null): mixed
    {
        $value = option('app.' . $slug . '.' . $key, null);
        if ($value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('asset_v')) {
    /** 静态资源带文件修改时间，改过样式或脚本立刻生效，不用手工清缓存 */
    function asset_v(string $rel): string
    {
        $rel = ltrim($rel, '/');
        $file = SVEL_ROOT . '/' . $rel;
        $ver = is_file($file) ? (string)filemtime($file) : SVEL_VERSION;
        return asset($rel) . '?v=' . $ver;
    }
}
